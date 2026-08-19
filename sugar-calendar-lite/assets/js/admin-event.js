/* globals jQuery, MutationObserver */
( function ( $ ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.Event = {

		/**
		 * Localized scripts or defaults.
		 */
		localizedScripts: {},

		/**
		 * Initialize.
		 *
		 * @since 3.0.0
		 */
		init: function () {

			this.$clearCalendarButton = $( '#sc_event_category-clear' );
			this.$calendarListRadios = $( '#sc_event_categorychecklist input' );

			// Admin Event submit button.
			this.$eventSubmitButton = $( 'body.wp-admin.sugar-calendar #publish' );

			// Admin Event save draft button.
			this.$eventSaveDraftButton = $( 'body.wp-admin.sugar-calendar #save-post' );

			// Admin Event preview button.
			this.$eventPreviewButton = $( 'body.wp-admin.sugar-calendar #post-preview' );

			// Admin Event title field.
			this.$eventTitle = $( 'body.wp-admin.sugar-calendar #title' );

			// Original post status field. Stays "auto-draft" until the first real save.
			this.$originalPostStatus = $( 'body.wp-admin.sugar-calendar #original_post_status' );

			// Register localized scripts. Set defaults if not available.
			this.getLocalizedScripts();

			this.bindEvents();

			// Element manipulation.
			this.manipulateElements();

			// Keep Preview disabled while the event is unsaved, even when core autosave re-enables it.
			this.watchPreviewButton();

			// Run if using the block editor.
			if ( 'object' === typeof( wp.blockEditor ) ) {
				this.blockEditorCustomValidation();
				this.interceptBlockEditorPreview();
			}
		},

		/**
		 * Get localized scripts.
		 * If variable is not available, set defaults.
		 *
		 * @since 3.3.0
		 *
		 * @returns {void}
		 */
		getLocalizedScripts: function () {

			this.localizedScripts = 'undefined' !== typeof( sugar_calendar_admin_event_vars ) ? sugar_calendar_admin_event_vars : {};

			if ( undefined === this.localizedScripts?.notice_title_required ) {
				this.localizedScripts.notice_title_required = 'Event title is required';
			}

			if ( undefined === this.localizedScripts?.notice_preview_requires_save ) {
				this.localizedScripts.notice_preview_requires_save = 'Save a draft to enable preview.';
			}
		},

		bindEvents: function () {

			this.$clearCalendarButton.on( 'click', this.clearCalendar.bind( this ) );

			// Register title input listener.
			this.$eventTitle.on( 'input propertychange', this.toggleActivatePublishGroupButtons.bind( this ) );
			this.$eventTitle.on( 'input propertychange', this.toggleAlertTitleEmpty.bind( this ) );

			// Register submit button on hover listener.
			this.$eventSubmitButton.on( 'mouseenter', this.toggleAlertTitleEmpty.bind( this ) );

			// Register save draft button on hover listener.
			this.$eventSaveDraftButton.on( 'mouseenter', this.toggleAlertTitleEmpty.bind( this ) );

			// Register preview button on hover listener.
			this.$eventPreviewButton.on( 'mouseenter', this.toggleAlertTitleEmpty.bind( this ) );

			// Prevent default the preview button if title is empty.
			this.$eventPreviewButton.on( 'click', this.preventPreviewButtonClick.bind( this ) );
		},

		manipulateElements: function () {

			// Disable the default publish metabox buttons if title is empty.
			this.toggleActivatePublishGroupButtons();
		},

		clearCalendar: function ( e ) {

			e.preventDefault();

			this.$calendarListRadios.removeAttr( 'checked' );
		},

		/**
		 * Show notice if title is empty.
		 * Show tooltip on the default submit button.
		 * Change title input border color.
		 *
		 * @since 3.3.0
		 * @since 3.8.2 Add hanlders for errror notice.
		 *
		 * @returns {void}
		 */
		toggleAlertTitleEmpty: function () {

			const isTitleEmpty = this.$eventTitle.val() === '';

			// Toggle tooltip on the default submit button.
			if ( isTitleEmpty ) {
				this.$eventSubmitButton.attr(
					'title',
					this.localizedScripts.notice_title_required
				);

				// Show inline error below the title input.
				this.showTitleRequiredInline();
			} else {
				this.$eventSubmitButton.removeAttr( 'title' );

				// Hide inline error.
				this.hideTitleRequiredInline();
			}

			// Toggle title input border color.
			this.$eventTitle.toggleClass( 'sugar-calendar-field-title-empty', isTitleEmpty );
		},

		/**
		 * Show inline title required message below the title input.
		 *
		 * @since 3.8.2
		 *
		 * @returns {void}
		 */
		showTitleRequiredInline: function () {

			// Prevent duplicate rendering.
			if ( $( '#sugar-calendar-title-required-inline' ).length > 0 ) {
				return;
			}

			const inlineHtml = '<p id="sugar-calendar-title-required-inline" class="sugar-calendar-inline-error">' +
				this.localizedScripts.notice_title_required +
				'</p>';

			// Insert just below the title input.
			$( '#title' ).after( inlineHtml );
		},

		/**
		 * Hide inline title required message.
		 *
		 * @since 3.8.2
		 *
		 * @returns {void}
		 */
		hideTitleRequiredInline: function () {

			$( '#sugar-calendar-title-required-inline' ).remove();
		},

		/**
		 * Toggle disabled state of the default publish metabox buttons.
		 * If title is empty, disable the default publish metabox buttons.
		 *
		 * @since 3.3.0
		 * @since 3.8.2 Add buttons in Publish metabox.
		 * @since 3.13.0 Preview also requires the event to be saved as a draft.
		 *
		 * @returns {void}
		 */
		toggleActivatePublishGroupButtons: function () {

			const isTitleEmpty = this.isTitleEmpty();

			// Publish and Save Draft only require a title.
			if ( isTitleEmpty ) {
				this.$eventSubmitButton.attr( 'disabled', true );
				this.$eventSaveDraftButton.attr( 'disabled', true );
			} else {
				this.$eventSubmitButton.removeAttr( 'disabled' );
				this.$eventSaveDraftButton.removeAttr( 'disabled' );
			}

			// Preview also requires the event to be saved at least as a draft.
			this.togglePreviewButton( isTitleEmpty );
		},

		/**
		 * Toggle the Preview button.
		 * Preview needs a non-empty title AND the event saved at least as a draft.
		 *
		 * @since 3.13.0
		 *
		 * @param {boolean} isTitleEmpty Whether the title field is empty.
		 *
		 * @returns {void}
		 */
		togglePreviewButton: function ( isTitleEmpty ) {

			const isSaved = this.isEventSaved();
			const disablePreview = isTitleEmpty || ! isSaved;

			// Use the "disabled" class, not the attribute: WordPress core's own preview
			// handler bails on this class. Core autosave strips it, so watchPreviewButton() re-asserts it.
			this.$eventPreviewButton.toggleClass( 'disabled', disablePreview );

			if ( ! disablePreview ) {
				this.$eventPreviewButton.removeAttr( 'aria-disabled' ).removeAttr( 'title' );

				return;
			}

			this.$eventPreviewButton
				.attr( 'aria-disabled', 'true' )
				.attr(
					'title',
					isTitleEmpty ?
						this.localizedScripts.notice_title_required :
						this.localizedScripts.notice_preview_requires_save
				);
		},

		/**
		 * Keep the Preview button disabled while the event is unsaved.
		 * WordPress core autosave periodically re-enables the Preview button (it strips the
		 * "disabled" class from an auto-draft), which would make an unsaved event previewable.
		 * Re-assert the disabled state whenever that happens.
		 *
		 * @since 3.13.0
		 *
		 * @returns {void}
		 */
		watchPreviewButton: function () {

			const button = this.$eventPreviewButton.get( 0 );

			// Nothing to watch outside the classic editor or once the event is saved.
			if ( ! button || this.isEventSaved() ) {
				return;
			}

			const observer = new MutationObserver( () => {
				this.togglePreviewButton( this.isTitleEmpty() );
			} );

			observer.observe( button, { attributes: true, attributeFilter: [ 'class' ] } );
		},

		/**
		 * Whether the event title field is empty.
		 *
		 * @since 3.13.0
		 *
		 * @returns {boolean}
		 */
		isTitleEmpty: function () {

			return this.$eventTitle.val() === '';
		},

		/**
		 * Whether the event has been saved at least as a draft.
		 * A brand-new event stays an "auto-draft" until the first real save.
		 *
		 * @since 3.13.0
		 *
		 * @returns {boolean}
		 */
		isEventSaved: function () {

			const status = this.$originalPostStatus.val();

			return ( typeof status !== 'undefined' ) && ( status !== '' ) && ( status !== 'auto-draft' );
		},

		/**
		 * Prevent the preview button from being clicked if title is empty
		 * or the event has not been saved at least as a draft.
		 *
		 * @since 3.8.2
		 * @since 3.13.0 Also block when the event is not yet saved.
		 *
		 * @returns {void}
		 */
		preventPreviewButtonClick: function ( e ) {

			if ( this.isTitleEmpty() || ! this.isEventSaved() ) {
				e.preventDefault();
				e.stopImmediatePropagation();
			}
		},

		/**
		 * Block editor custom validation.
		 * Prevent the user from saving the event if the title is empty.
		 *
		 * @since 3.3.0
		 *
		 * @returns {void}
		 */
		blockEditorCustomValidation: function () {

			// Localized error notice. Revert to default if not available.
			const errorNoticeTitleMissing = this.localizedScripts.notice_title_required;

			// Check if WordPress supports the editor.preSavePost filter (WP 6.7+).
			if ( this.localizedScripts.supports_editor_pre_save ) {

				// Use the modern filter approach for WordPress 6.7+.
				this.useFilterApproach( errorNoticeTitleMissing );
			} else {

				// Use the legacy lockPostSaving approach for WordPress < 6.7.
				this.useLegacyApproach( errorNoticeTitleMissing );
			}
		},

		/**
		 * Route the block editor's "Preview in new tab" through the classic preview flow.
		 *
		 * Gutenberg previews by autosaving first, which for an event triggers a real
		 * save (for a recurring event: a page reload / the "Edit Recurring Event"
		 * dialog). Instead we intercept the click and submit the same
		 * wp-preview=dopreview request the classic editor sends — the current title
		 * and content plus the event fields — which the server's existing preview
		 * handler turns into a non-destructive, overlaid preview. Parity with classic,
		 * no autosave.
		 *
		 * @since 3.13.0
		 *
		 * @returns {void}
		 */
		interceptBlockEditorPreview: function () {

			// Only on the SC event editor.
			if ( ! $( 'body.wp-admin.sugar-calendar' ).length ) {
				return;
			}

			// Capture phase, so we preempt Gutenberg's own React click handler.
			document.addEventListener( 'click', ( e ) => {

				const button = e.target.closest( '.editor-preview-dropdown__button-external' );

				if ( ! button ) {
					return;
				}

				// Building the classic request needs the post id + update nonce, which
				// only exist once the event is saved. Otherwise let core handle it.
				const fields = this.getBlockPreviewFields();

				if ( ! fields ) {
					return;
				}

				e.preventDefault();
				e.stopImmediatePropagation();

				this.submitBlockPreview( fields );
			}, true );
		},

		/**
		 * Assemble the classic preview submit fields, or null when preview is not
		 * allowed yet — empty title, an unsaved (auto-draft) event, or no post id /
		 * update nonce to build a valid request. Mirrors the classic-path gate.
		 *
		 * @since 3.13.0
		 *
		 * @returns {Array|null}
		 */
		getBlockPreviewFields: function () {

			if ( typeof wp === 'undefined' || ! wp.data || ! wp.data.select( 'core/editor' ) ) {
				return null;
			}

			const editor = wp.data.select( 'core/editor' );
			const postId = editor.getCurrentPostId();

			// Same gate the classic path enforces (isTitleEmpty / isEventSaved), read
			// from the block editor's own state: preview needs a non-empty title AND a
			// saved event. An auto-draft stays "auto-draft" until the first real save.
			const status = editor.getCurrentPost() ? editor.getCurrentPost().status : '';

			if ( ( editor.getEditedPostAttribute( 'title' ) || '' ) === '' || status === 'auto-draft' || status === '' ) {
				return null;
			}

			// The post-edit nonce lives in the base form that also carries post_ID (form#post in classic, .metabox-base-form in block).
			const baseForm = document.getElementById( 'post_ID' )?.closest( 'form' );
			const nonceField = baseForm ? baseForm.querySelector( 'input[name="_wpnonce"]' ) : null;

			if ( ! postId || ! nonceField || ! nonceField.value ) {
				return null;
			}

			const fields = [];
			const seen = {};

			// The post id + update nonce and the event Details fields live in separate
			// forms in the block editor; serialize every event/metabox form. The
			// classic metabox areas are included because Tags posts sc_event_tags[]
			// from form.metabox-location-side.
			$( 'form' ).each( function () {

				if (
					! this.querySelector( '[name="post_ID"]' ) &&
					! this.querySelector( '#start_date' ) &&
					! this.querySelector( '#sugarcalendar_block_editor_flag' ) &&
					! this.matches( '[class*="metabox-location-"]' )
				) {
					return;
				}

				$( this ).find( 'input[name], select[name], textarea[name]' ).each( function () {

					if ( ( this.type === 'checkbox' || this.type === 'radio' ) && ! this.checked ) {
						return;
					}

					// A <select multiple> (e.g. Speakers) submits one value per selected option.
					if ( this.tagName === 'SELECT' && this.multiple ) {
						Array.from( this.selectedOptions ).forEach( ( option ) => {
							fields.push( { name: this.name, value: option.value } );
						} );

						return;
					}

					// Keep bracketed/array names (e.g. tax_input[...]); dedupe scalars.
					const isArray = /\]$/.test( this.name );

					if ( ! isArray && seen[ this.name ] ) {
						return;
					}

					seen[ this.name ] = true;
					fields.push( { name: this.name, value: this.value } );
				} );
			} );

			// Title and content live in Gutenberg, not a classic field.
			fields.push( { name: 'post_title', value: editor.getEditedPostAttribute( 'title' ) || '' } );
			fields.push( { name: 'content', value: editor.getEditedPostContent() || '' } );

			// Featured image and excerpt have no classic input in the block editor either.
			fields.push( { name: '_thumbnail_id', value: editor.getEditedPostAttribute( 'featured_media' ) || 0 } );
			fields.push( { name: 'excerpt', value: editor.getEditedPostAttribute( 'excerpt' ) || '' } );

			// No block-editor form carries the calendar, and an absent value reads as "cleared".
			( editor.getEditedPostAttribute( 'sc_event_category' ) || [] ).forEach( ( termId ) => {
				fields.push( { name: 'tax_input[sc_event_category][]', value: termId } );
			} );

			// The trigger the server preview handler keys on.
			fields.push( { name: 'wp-preview', value: 'dopreview' } );

			return fields;
		},

		/**
		 * Submit the assembled preview request to a new tab.
		 *
		 * @since 3.13.0
		 *
		 * @param {Array} fields Name/value pairs to submit.
		 *
		 * @returns {void}
		 */
		submitBlockPreview: function ( fields ) {

			const target = 'wp-preview-' + ( wp.data.select( 'core/editor' ).getCurrentPostId() || '0' );

			// Open the tab from inside the click so the browser allows the popup.
			window.open( '', target );

			const form = document.createElement( 'form' );
			form.method = 'POST';
			form.action = window.location.pathname;
			form.target = target;

			fields.forEach( ( field ) => {
				const input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = field.name;
				input.value = field.value == null ? '' : field.value;
				form.appendChild( input );
			} );

			document.body.appendChild( form );
			form.submit();
			form.remove();
		},

		/**
		 * Modern validation approach using editor.preSavePost filter (WordPress 6.7+).
		 *
		 * @since 3.8.2
		 *
		 * @param {string} errorNoticeTitleMissing The error message to display.
		 *
		 * @returns {void}
		 */
		useFilterApproach: function ( errorNoticeTitleMissing ) {

			// Track current title to detect changes.
			let currentTitle = '';

			// Create a subscription to monitor title changes.
			const watchTitle = () => {

				const unsubscribeTitleSubscription = wp.data.subscribe( () => {

					// Get the current title from the editor.
					const title = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );

					// If title is not empty, remove any error notices.
					if ( title && title.trim() !== '' ) {

						unsubscribeTitleSubscription();

						wp.data.dispatch( 'core/notices' ).removeNotice( 'editor-save' );
					}
				} );
			};

			// Add preSavePost filter to prevent saving the post without a title.
			wp.hooks.addFilter(
				'editor.preSavePost',
				'sugar-calendar/validate-event-title',
				function( edit, options ) {

					// Get the current title from the editor.
					const title = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );

					// If title is empty, prevent saving.
					if ( '' === title || ! title || title.trim() === '' ) {

						watchTitle();

						// Throw error to prevent saving.
						throw new Error( errorNoticeTitleMissing );
					}

					// Return the edit data to allow saving.
					return edit;
				}
			);
		},

		/**
		 * Legacy validation approach using lockPostSaving (WordPress < 6.7).
		 *
		 * @since 3.8.2
		 *
		 * @param {string} errorNoticeTitleMissing The error message to display.
		 *
		 * @returns {void}
		 */
		useLegacyApproach: function ( errorNoticeTitleMissing ) {

			/**
			 * State of lock and notice.
			 *
			 * @var {boolean} isLocked - Save post locked state.
			 * @var {boolean} showError - Showing error notice.
			 */
			let isLocked = false,
				showError = false;

			// Subscribe to the editor state.
			wp.data.subscribe( () => {

				// Use publish sidebar if available.
				let isPublishSidebarOpened = false;

				if ( typeof( wp.data.select( 'core/edit-post' ).isPublishSidebarOpened ) === 'function' ) {
					isPublishSidebarOpened = wp.data.select( 'core/edit-post' ).isPublishSidebarOpened();
				} else if ( typeof( wp.data.select( 'core/editor' ).isPublishSidebarOpened ) === 'object' ) {
					isPublishSidebarOpened = wp.data.select( 'core/editor' ).isPublishSidebarOpened();
				}

				/**
				 * State identifiers.
				 *
				 * @var {string} title - The current post title value.
				 * @var {boolean} publishSidebarOpened - If publish sidebar is opened.
				 */
				const title = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );

				// If title is empty, lock the editor save function.
				if ( '' === title ) {

					// Lock the editor if not locked. Avoid maximum call stack error.
					if ( ! isLocked ) {

						// Set the locked state to true.
						isLocked = true;

						// Lock the editor.
						wp.data.dispatch( 'core/editor' ).lockPostSaving( 'save-lock-title' );
					}

					// Always show notice when title is empty, regardless of save action.
					// This prevents saving as draft without a title.
					if ( ! showError ) {

						// Set the show error state to true.
						showError = true;

						// Create an error notice.
						wp.data.dispatch( 'core/notices' ).createNotice(
							'error',
							errorNoticeTitleMissing,
							{ id: 'save-lock-title', isDismissible: true }
						);
					}
				}

				// If title is not empty.
				// - Unlock the editor save function if it's locked.
				// - Remove the error notice if it's showing.
				else {

					// Check to avoid maximum call stack error.
					if ( isLocked ) {

						// Set the locked state to false.
						isLocked = false;

						// Unlock the editor.
						wp.data.dispatch( 'core/editor' ).unlockPostSaving( 'save-lock-title' );
					}

					// Check to avoid maximum call stack error.
					if ( showError ) {

						// Set the show error state to false.
						showError = false;

						// Remove the notice.
						wp.data.dispatch( 'core/notices' ).removeNotice( 'save-lock-title' );
					}
				}
			} );
		}
	};

	SugarCalendar.Admin.Event.init();

	window.SugarCalendar = SugarCalendar;

} )( jQuery );
