/**
 * Settings → Emails.
 *
 * Owns the Lite education prompts on gated email rows.
 *
 * The modal is deliberately a per-surface opener rather than a call into
 * SugarCalendar.Admin.Education. That object exposes no parameterised opener:
 * openFeedsUpgradeModal() is bound to the feeds list DOM and reads its own
 * localized key, and admin-event-lite.js hardcodes `upgrade-modal-event-metabox-`
 * into every UTM. Reusing either would misattribute clicks from this screen. One
 * opener per surface, each naming itself in utm_medium, is the existing pattern.
 *
 * @since 3.14.0
 */
/* global jQuery, SugarCalendar, wp */
( function( $ ) {
	'use strict';

	const settings = window.sugar_calendar_admin_settings_emails;

	// The shared thank-you helper reads this established global at call time.
	// Admin\Education::enqueue_assets() localizes it with ONLY `ajax_url`, so
	// without this assignment openUpradeThankYouModal() throws on
	// `.sce_admin_upgrade_thank_you_modal.title`. Assigning one key preserves
	// `ajax_url` — re-localizing the whole global from PHP would clobber it and
	// silently break education-notice dismissal.
	window.sugar_calendar_admin_education = window.sugar_calendar_admin_education || {};
	window.sugar_calendar_admin_education.sce_admin_upgrade_thank_you_modal =
		settings.thank_you;

	const app = {

		/**
		 * Start the engine.
		 *
		 * @since 3.14.0
		 */
		init() {
			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.14.0
		 */
		ready() {
			app.applyTemplateVisibility();
			app.bindEvents();
		},

		/**
		 * Bind events.
		 *
		 * @since 3.14.0
		 */
		bindEvents() {
			$( document ).on(
				'click',
				'[data-sc-email-preview]',
				app.onPreviewEmail
			);

			// Keyed off the prompt's own class, not the configurable-email section.
			// The Hide Branding prompt is rendered after its wrapped toggle in the
			// separate Email Styling section, so a section-scoped selector would match
			// the row prompts and silently miss it.
			$( document ).on(
				'click keydown',
				'.sugar-calendar-settings-emails__pro-prompt.sce-lite-education-modal-link',
				app.onEducationLinkActivate
			);

			// The gated Hide Branding row is one click surface: the label text and
			// the disabled toggle open the same modal as the badge. Mouse only; the
			// badge keeps role="button" and stays the single keyboard stop.
			$( document ).on(
				'click',
				'.sugar-calendar-settings-emails__branding--locked',
				app.onLockedBrandingRowClick
			);

			$( document ).on(
				'change',
				'.sugar-calendar-settings-email-styling .sugar-calendar-image-radio-group__input',
				app.onTemplateChange
			);

			$( document ).on(
				'click',
				'.sugar-calendar-settings-email-styling .sugar-calendar-image-upload__upload',
				app.onUploadImage
			);

			$( document ).on(
				'click',
				'.sugar-calendar-settings-email-styling .sugar-calendar-image-upload__remove',
				app.onRemoveImage
			);

			$( document ).on(
				'change',
				'.sugar-calendar-settings-email-styling .sugar-calendar-image-upload__size select',
				app.onSizeChange
			);
		},

		/**
		 * Create an unsaved email preview in a new tab.
		 *
		 * Orchestration only. Every step runs through `session.attempt()`, so a
		 * failure anywhere closes the popup, shows one message, and releases the
		 * button exactly once; later steps are skipped.
		 *
		 * @param {Object} event Click event.
		 *
		 * @since 3.14.0
		 */
		onPreviewEmail( event ) {
			event.preventDefault();

			const $button = $( event.currentTarget );

			if ( $button.attr( 'aria-busy' ) === 'true' ) {
				return;
			}

			const session = app.createPreviewSession( $button );
			let data = null;

			const steps = [
				[ () => $button.prop( 'disabled', true ).attr( 'aria-busy', 'true' ), settings.preview_error ],
				[ app.flushEditors, settings.preview_error ],
				[ session.openWindow, settings.popup_blocked ],
				[ session.detachOpener, settings.preview_error ],
				[ () => {
					data = app.buildPreviewPayload( $button );
				}, settings.preview_error ],
				[ () => app.submitPreview( data, session ), settings.preview_error ],
			];

			steps.every( ( [ step, message ] ) => session.attempt( step, message ) );
		},

		/**
		 * Track one preview attempt: the busy button, its popup, and cleanup.
		 *
		 * `fail()` and `cleanup()` are idempotent, so the AJAX `always` handler and
		 * an earlier failure can both run without double-restoring the control.
		 *
		 * @param {jQuery} $button Preview button.
		 *
		 * @return {Object} Session.
		 *
		 * @since 3.14.0
		 */
		createPreviewSession( $button ) {
			const wasDisabled = $button.prop( 'disabled' );
			const session = {
				window: null,
				failed: false,
				cleanedUp: false,

				attempt( step, message ) {
					try {
						step();

						return true;
					} catch ( error ) {
						session.fail( message );

						return false;
					}
				},

				openWindow() {
					session.window = window.open( 'about:blank', '_blank' );

					if ( session.window === null ) {
						throw new Error( 'Popup blocked.' );
					}
				},

				detachOpener() {
					session.window.opener = null;
				},

				navigate( href ) {
					if ( session.window.closed ) {
						return;
					}

					session.window.location.href = href;
				},

				closeWindow() {
					if ( session.window === null ) {
						return;
					}

					try {
						if ( session.window.closed ) {
							return;
						}
					} catch ( error ) {
						// A cross-window access failure should not prevent a best-effort close.
					}

					try {
						session.window.close();
					} catch ( error ) {
						// The window may already be unavailable or controlled by the browser.
					}
				},

				fail( message ) {
					if ( session.failed ) {
						session.cleanup();

						return;
					}

					session.failed = true;
					session.closeWindow();
					app.showPreviewError( message );
					session.cleanup();
				},

				cleanup() {
					if ( session.cleanedUp ) {
						return;
					}

					session.cleanedUp = true;

					try {
						$button.prop( 'disabled', wasDisabled );
					} catch ( error ) {
						// The original state cannot be restored when the control itself is unavailable.
					}

					try {
						$button.removeAttr( 'aria-busy' );
					} catch ( error ) {
						// The busy marker cannot be removed when the control itself is unavailable.
					}
				},
			};

			return session;
		},

		/**
		 * Report a preview failure without letting a blocked alert keep the button busy.
		 *
		 * @param {string} message Message.
		 *
		 * @since 3.14.0
		 */
		showPreviewError( message ) {
			try {
				// eslint-disable-next-line no-alert
				window.alert( message );
			} catch ( error ) {
				// Browser UI failures must not leave the preview control busy.
			}
		},

		/**
		 * Copy TinyMCE content back into the textareas the form will serialize.
		 *
		 * @since 3.14.0
		 */
		flushEditors() {
			if ( window.tinymce && typeof window.tinymce.triggerSave === 'function' ) {
				window.tinymce.triggerSave();
			}
		},

		/**
		 * Serialize the settings form plus the preview creator's own fields.
		 *
		 * @param {jQuery} $button Preview button.
		 *
		 * @return {Array} jQuery `serializeArray()` pairs.
		 *
		 * @since 3.14.0
		 */
		buildPreviewPayload( $button ) {
			const $form = $button.closest( 'form' );
			const data = $form.serializeArray();
			const $branding = $form.find( 'input[name="sugar-calendar[email_hide_branding]"]:enabled' );

			if ( $branding.length && ! $branding.prop( 'checked' ) ) {
				data.push( { name: $branding.attr( 'name' ), value: '0' } );
			}

			data.push(
				{
					name: 'action',
					value: settings.preview_action,
				},
				{
					name: 'nonce',
					value: settings.preview_nonce,
				},
				{
					name: 'route',
					value: $button.attr( 'data-sc-email-preview' ),
				},
				{
					name: 'email_id',
					value: $button.attr( 'data-email-id' ),
				}
			);

			return data;
		},

		/**
		 * Create the preview and send the popup to it.
		 *
		 * @param {Array}  data    Payload from buildPreviewPayload().
		 * @param {Object} session Session from createPreviewSession().
		 *
		 * @since 3.14.0
		 */
		submitPreview( data, session ) {
			$.ajax( {
				url: settings.preview_ajax_url,
				type: 'POST',
				data,
			} )
				.done( ( response ) => app.onPreviewCreated( response, session ) )
				.fail( () => session.fail( settings.preview_error ) )
				.always( session.cleanup );
		},

		/**
		 * Send the popup to the URL the preview creator minted.
		 *
		 * @param {Object} response AJAX response.
		 * @param {Object} session  Session from createPreviewSession().
		 *
		 * @since 3.14.0
		 */
		onPreviewCreated( response, session ) {
			if ( session.failed ) {
				return;
			}

			let previewUrl = null;

			const parsed = session.attempt( () => {
				previewUrl = app.parsePreviewUrl( response );
			}, settings.preview_error );

			if ( parsed ) {
				session.attempt( () => session.navigate( previewUrl.href ), settings.preview_error );
			}
		},

		/**
		 * Accept only an http(s) URL from the preview creator.
		 *
		 * @param {Object} response AJAX response.
		 *
		 * @return {URL} Parsed URL.
		 *
		 * @since 3.14.0
		 */
		parsePreviewUrl( response ) {
			const url = response && response.success && response.data ? response.data.url : null;

			if ( typeof url !== 'string' || url.trim() === '' ) {
				throw new Error( 'Invalid preview URL.' );
			}

			const previewUrl = new URL( url.trim() );

			if ( previewUrl.protocol !== 'http:' && previewUrl.protocol !== 'https:' ) {
				throw new Error( 'Invalid preview URL protocol.' );
			}

			return previewUrl;
		},

		/**
		 * Move the selected template-card treatment and update visibility.
		 *
		 * @param {Object} event Change event.
		 *
		 * @since 3.14.0
		 */
		onTemplateChange( event ) {
			const $input = $( event.currentTarget );
			const $group = $input.closest( '.sugar-calendar-image-radio-group' );

			$group
				.find( '.sugar-calendar-image-radio-group__option' )
				.removeClass( 'sugar-calendar-image-radio-group__option--selected' );

			$input
				.closest( '.sugar-calendar-image-radio-group__option' )
				.addClass( 'sugar-calendar-image-radio-group__option--selected' );

			app.applyTemplateVisibility();
		},

		/**
		 * Hide Modern-only fields when Plain Text is selected.
		 *
		 * @since 3.14.0
		 */
		applyTemplateVisibility() {
			const selected = $(
				'.sugar-calendar-settings-email-styling .sugar-calendar-image-radio-group__input:checked'
			).val();

			if ( typeof selected === 'undefined' ) {
				return;
			}

			$( '.sugar-calendar-settings-email-styling .sugar-calendar-settings-emails__modern-only' )
				.prop( 'hidden', selected === 'plain-text' );
		},

		/**
		 * Open the WordPress media library for Header Image.
		 *
		 * @param {Object} event Click event.
		 *
		 * @since 3.14.0
		 */
		onUploadImage( event ) {
			event.preventDefault();

			if ( typeof wp === 'undefined' || typeof wp.media !== 'function' ) {
				return;
			}

			const $control = $( event.currentTarget ).closest( '.sugar-calendar-image-upload' );
			const frame = wp.media( {
				title: settings.media_title,
				button: {
					text: settings.media_button,
				},
				library: {
					type: 'image',
				},
				multiple: false,
			} );

			frame.on( 'select', () => {
				const attachment = frame.state().get( 'selection' ).first().toJSON();

				// select fires while the media modal is still open, so
				// document.activeElement is the modal's own confirm button, not
				// Upload — setImageState()'s snapshot guard can't see this path,
				// and the frame's own focus-restore (on close) targets Upload,
				// which is about to be hidden, so it silently no-ops. Snapshot
				// here, before anything is mutated: focus is still genuinely
				// "inside our media flow" only while it's inside the modal
				// itself. If it has already moved to something unrelated on the
				// page (a user tabbing away, another script), that move must be
				// respected rather than overridden below.
				const focusInModal = $( document.activeElement ).closest( '.media-modal' ).length > 0;

				$control.find( '.sugar-calendar-image-upload__input' ).val( attachment.id );
				// Full-size URL: it is what PHP renders after save, and the preview
				// is shown at the real email width, where the 150px thumbnail blurs.
				$control.find( '.sugar-calendar-image-upload__thumb' ).attr( 'src', attachment.url );
				app.applyPreviewWidth( $control );
				app.setImageState( $control, true );

				// Reaching this callback at all means the user opened the frame
				// from this control, so once we know focus was still in the
				// modal a moment ago, returning it to Remove Image (rather than
				// letting it fall through to the now-hidden Upload button) is
				// unambiguous.
				const $remove = $control.find( '.sugar-calendar-image-upload__remove' );

				if ( focusInModal && $remove.length && ! $remove.prop( 'hidden' ) ) {
					$remove.trigger( 'focus' );
				}
			} );

			frame.open();
		},

		/**
		 * Clear the session-only Header Image selection.
		 *
		 * @param {Object} event Click event.
		 *
		 * @since 3.14.0
		 */
		onRemoveImage( event ) {
			event.preventDefault();

			const $control = $( event.currentTarget ).closest( '.sugar-calendar-image-upload' );

			$control.find( '.sugar-calendar-image-upload__input' ).val( '' );
			$control.find( '.sugar-calendar-image-upload__thumb' ).removeAttr( 'src' );
			$control.find( '.sugar-calendar-image-upload__size select' ).val( 'medium' );
			// .val() fires no change event, so sync the preview width by hand.
			app.applyPreviewWidth( $control );
			app.setImageState( $control, false );
		},

		/**
		 * Resize the Header Image preview when the size changes.
		 *
		 * @param {Object} event Change event.
		 *
		 * @since 3.14.0
		 */
		onSizeChange( event ) {
			app.applyPreviewWidth(
				$( event.currentTarget ).closest( '.sugar-calendar-image-upload' )
			);
		},

		/**
		 * Give the preview image the pixel width of the selected size.
		 *
		 * Mirrors the `width` the Modern template puts on the email's <img>, so
		 * the preview shows the logo at the width it will actually render.
		 *
		 * @param {Object} $control Image control.
		 *
		 * @since 3.14.0
		 */
		applyPreviewWidth( $control ) {
			const width = $control
				.find( '.sugar-calendar-image-upload__size option:selected' )
				.data( 'width' );
			const $thumb = $control.find( '.sugar-calendar-image-upload__thumb' );

			if ( width ) {
				$thumb.attr( 'width', width );
			} else {
				$thumb.removeAttr( 'width' );
			}
		},

		/**
		 * Swap the Header Image control between empty and selected states.
		 *
		 * @param {Object}  $control Image control.
		 * @param {boolean} hasImage Whether an image is selected.
		 *
		 * @since 3.14.0
		 */
		setImageState( $control, hasImage ) {
			const $upload = $control.find( '.sugar-calendar-image-upload__upload' );
			const $remove = $control.find( '.sugar-calendar-image-upload__remove' );

			// Remove Image hides itself on click, and hiding a focused element
			// synchronously blurs it to <body> — so this snapshot MUST be taken
			// before the `.prop( 'hidden', … )` calls run, not after; reading
			// document.activeElement afterward always finds <body> and the fix
			// would be a no-op. That covers the click-driven Remove path. The
			// media-select path is handled separately at its own call site
			// (onUploadImage()'s `select` callback): at the moment this method
			// runs there, the media modal is still open and owns focus, so this
			// snapshot can't see it — and the frame's own restore-on-close
			// targets Upload, which is hidden by then and silently no-ops.
			// Below, only the Remove path is decided from the snapshot: move
			// focus onto the control becoming visible, but only when focus was
			// actually on the control we're about to hide, so a click elsewhere
			// on the page is never redirected here.
			const $justHiding = hasImage ? $upload : $remove;
			const $justRevealing = hasImage ? $remove : $upload;
			const focusWasInside = $( document.activeElement ).closest( $justHiding ).length > 0;

			$control.find(
				'.sugar-calendar-image-upload__preview, ' +
				'.sugar-calendar-image-upload__size, ' +
				'.sugar-calendar-image-upload__remove'
			).prop( 'hidden', ! hasImage );

			$upload.prop( 'hidden', hasImage );

			if ( focusWasInside && $justRevealing.length && ! $justRevealing.prop( 'hidden' ) ) {
				$justRevealing.trigger( 'focus' );
			}
		},

		/**
		 * Open the Pro upgrade modal for a gated control.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} event Click or keydown event.
		 */
		onEducationLinkActivate( event ) {
			// Keyboard parity with the click handler: the prompt is a
			// role="button", so Enter and Space must activate it.
			if ( event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ' ) {
				return;
			}

			event.preventDefault();

			const featId = $( this ).data( 'feat-id' ) || 'emails';
			const featName = $( this ).data( 'feat-name' ) || '';
			const content = settings.education;
			const medium = 'emails-settings-' + featId;

			$.alert( {
				theme: 'light,sce-admin-education',
				title: featName + ' ' + content.upgrade_title,
				content: content.upgrade_content.replace( '[feat-name]', featName ),
				bootstrapClasses: {
					container: 'container sce-jquery-confirm-container',
					containerFluid: 'container-fluid',
					row: 'row',
				},
				backgroundDismiss: true,
				boxWidth: '550px',
				buttons: {
					confirm: {
						text: settings.upgrade_button,
						btnClass: 'btn-confirm sce-jquery-confirm-button sugar-calendar-btn-primary sugar-calendar-btn-lg',
						keys: [ 'enter' ],
						action() {
							const url = new URL( 'https://sugarcalendar.com/lite-upgrade/' );

							url.searchParams.set( 'utm_source', 'WordPress' );
							url.searchParams.set( 'utm_medium', 'upgrade-modal-' + medium );
							url.searchParams.set( 'utm_content', 'Upgrade to Pro' );
							url.searchParams.set( 'utm_campaign', 'liteplugin' );
							url.searchParams.set( 'utm_locale', content.utm_locale );

							window.open( url, '_blank' );

							SugarCalendar.Admin.Education.openUpradeThankYouModal( medium );
						},
					},
				},

				/**
				 * Append the discount note and the already-purchased escape hatch.
				 *
				 * Mirrors openFeedsUpgradeModal(). Omitting these ships a Lite
				 * upsell with no way out other than buying.
				 *
				 * @since 3.14.0
				 */
				onOpenBefore() {
					const $buttons = $( '.jconfirm-buttons' );
					const purchasedUrl = new URL(
						'https://sugarcalendar.com/docs/events/upgrading-from-sugar-calendar-lite-to-a-paid-license/'
					);

					purchasedUrl.searchParams.set( 'utm_source', 'WordPress' );
					purchasedUrl.searchParams.set( 'utm_medium', 'upgrade-modal-' + medium );
					purchasedUrl.searchParams.set( 'utm_content', 'Already purchased' );
					purchasedUrl.searchParams.set( 'utm_campaign', 'liteplugin' );
					purchasedUrl.searchParams.set( 'utm_locale', content.utm_locale );

					$buttons.after(
						'<div class="discount-note"><p>' + content.upgrade_bonus + '</p></div>' +
						'<a href="' + purchasedUrl.toString() + '" target="_blank" rel="noopener noreferrer" class="already-purchased">' +
							settings.already_purchased +
						'</a>'
					);
				},
				icon: 'sce-icon sce-icon__lock',
				escapeKey: true,
				useBootstrap: false,
				closeIcon: true,
			} );
		},

		/**
		 * Forward a click anywhere on the locked Hide Branding row to its badge.
		 *
		 * @param {Object} event Click event.
		 *
		 * @since 3.14.0
		 */
		onLockedBrandingRowClick( event ) {
			// A click on the badge itself already ran onEducationLinkActivate on
			// the way up; running it again here would stack two modals.
			if ( $( event.target ).closest( '.sugar-calendar-settings-emails__pro-prompt' ).length ) {
				return;
			}

			const $prompt = $( this ).find(
				'.sugar-calendar-settings-emails__pro-prompt.sce-lite-education-modal-link'
			);

			if ( ! $prompt.length ) {
				return;
			}

			app.onEducationLinkActivate.call( $prompt[ 0 ], event );
		},
	};

	app.init();
}( jQuery ) );
