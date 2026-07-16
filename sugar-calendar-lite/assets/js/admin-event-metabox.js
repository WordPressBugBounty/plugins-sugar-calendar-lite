/* globals jQuery, sugar_calendar_admin_event_meta_box */
( function ( $, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.EventMetabox = {

		/**
		 * Initialize.
		 *
		 * @since 3.0.0
		 * @since 3.8.0 Added Help URL context.
		 */
		init: function ( settings ) {

			this.settings = settings;
			this.$el = $( '.sugar-calendar-event-details-metabox' );
			this.$sectionButtons = $( '.sugar-calendar-metabox__navigation__button', this.$el );
			this.$sections = $( '.sugar-calendar-metabox__section', this.$el );
			this.$startDate = $( '#start_date', this.$el );
			this.$startTimeHour = $( '#start_time_hour', this.$el );
			this.$startTimeMinute = $( '#start_time_minute', this.$el );
			this.$startTimeAmPm = $( '#start_time_am_pm', this.$el );
			this.$startTz = $( '#sugar-calendar_start_tz', this.$el );
			this.$endDate = $( '#end_date', this.$el );
			this.$endTimeHour = $( '#end_time_hour', this.$el );
			this.$endTimeMinute = $( '#end_time_minute', this.$el );
			this.$endTimeAmPm = $( '#end_time_am_pm', this.$el );
			this.$endTz = $( '#sugar-calendar_end_tz', this.$el );
			this.$allDay = $( '#all_day', this.$el );
			this.$timezones = $( '.sugar-calendar-metabox__field-row--time-zone, .event-time-zone, .event-time', this.$el );
			this.$submitButton = $( '#publish' );
			this.$onlineProvider = $( '#online_provider', this.$el );
			this.$createMeetingBtn = $( '.sugar-calendar-metabox__create-meeting', this.$el );
			this.$createMeetingError = $( '.sugar-calendar-metabox__create-meeting-error', this.$el );
			this.$onlineDescription = $( '.sugar-calendar-metabox__online-description', this.$el );
			this.onlineDescriptionDefault = this.$onlineDescription.text();
			this.$onlineCreditsError = $( '.sugar-calendar-metabox__online-credits-error', this.$el );
			this.$recurrence = $( '#recurrence', this.$el );
			this.$tagsSelect = $( '.sugar-calendar-column-tags-form select' );

			// Remember each Online Platform option's server-rendered disabled state,
			// so the recurrence lock can restore it (rather than blanket-enabling
			// options the server intentionally disabled, e.g. out-of-credits) when
			// the event stops being recurring.
			this.$onlineProvider.find( 'option' ).each( function () {
				$( this ).data( 'scBaseDisabled', $( this ).prop( 'disabled' ) );
			} );
			this.$helpUrl = $( '#sugar-calendar-header-help' );
			this.helpUrl = false;

			const scHelpHref = this.$helpUrl.attr( 'href' );

			if ( scHelpHref && scHelpHref.length > 0 ) {
				try {
					this.helpUrl = new URL( scHelpHref );
				} catch ( error ) {
					this.helpUrl = false;
				}
			}

			// Bind events.
			this.bindEvents();

			// Initialize ChoiceJS dropdowns.
			this.initChoicesJS();

			// Initialize date pickers.
			this.initDatepickers();

			// Block adjustments.
			this.initBlockAdjustments();

			// Set the initial Online-section UI state (meeting block + Create button).
			this.syncOnlineMeetingUI();
		},

		bindEvents: function () {

			this.$sectionButtons.on( 'click', this.onSectionButtonClick.bind( this ) );
			this.$allDay.on( 'change', this.toggleTimezones.bind( this ) );

			this.$submitButton.on( 'click', this.validateDates.bind( this ) );

			this.$el.on( 'click', '.sugar-calendar-metabox__copy', this.onCopyClick.bind( this ) );
			this.$onlineProvider.on( 'change', this.syncOnlineMeetingUI.bind( this ) );
			this.$recurrence.on( 'change', this.syncOnlineMeetingUI.bind( this ) );
			this.$createMeetingBtn.on( 'click', this.onCreateMeetingClick.bind( this ) );

			// Delegated: the meeting card (with its Remove button) is re-inserted
			// after a create, so bind on the container rather than the element.
			this.$el.on( 'click', '.sugar-calendar-metabox__online-card-remove', this.onRemoveMeetingClick.bind( this ) );
		},

		/**
		 * On section button click.
		 *
		 * @since 3.0.0
		 * @since 3.8.0 Update the Help URL's fragment based on the section.
		 *
		 * @param {Event} e Event object.
		 */
		onSectionButtonClick: function ( e ) {

			const $button = $( e.currentTarget );
			const id = $button.attr( 'data-id' );
			const $section = this.$sections.filter( `[data-id=${id}]` );

			this.$sectionButtons.removeClass( 'selected' );
			this.$sections.removeClass( 'selected' );

			$button.addClass( 'selected' );
			$section.addClass( 'selected' );

			if ( this.helpUrl ) {
				if ( sugar_calendar_admin_event_meta_box.help_url[ id ] ) {
					this.helpUrl.hash = sugar_calendar_admin_event_meta_box.help_url[ id ];
				} else {
					this.helpUrl.hash = '';
				}

				// Update the Help URL.
				this.$helpUrl.attr( 'href', this.helpUrl.toString() );
			}
		},

		/**
		 * Copy a read-only field's value to the clipboard and flash "Copied!".
		 *
		 * @since 3.12.0
		 *
		 * @param {Event} e Click event.
		 */
		onCopyClick: function ( e ) {

			e.preventDefault();

			const $button = $( e.currentTarget );
			const field = document.getElementById( $button.attr( 'data-copy-target' ) );

			if ( ! field ) {
				return;
			}

			const flash = () => {
				if ( ! $button.data( 'copyLabel' ) ) {
					$button.data( 'copyLabel', $button.text() );
				}

				if ( $button.data( 'copyTimer' ) ) {
					clearTimeout( $button.data( 'copyTimer' ) );
				}

				$button.text( wp.i18n.__( 'Copied!', 'sugar-calendar-lite' ) );

				$button.data( 'copyTimer', setTimeout( () => {
					$button.text( $button.data( 'copyLabel' ) );
					$button.removeData( 'copyTimer' );
				}, 1500 ) );
			};

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( field.value ).then( flash, () => {
					field.select();
					document.execCommand( 'copy' );
					flash();
				} );
			} else {
				field.select();
				document.execCommand( 'copy' );
				flash();
			}
		},

		/**
		 * Read the post id, editor-aware (auto-draft id exists before save).
		 *
		 * @since 3.12.0
		 *
		 * @return {number|string}
		 */
		getEditorPostId: function () {

			if (
				this.settings.editor && this.settings.editor.type === 'block'
				&& window.wp && wp.data && wp.data.select( 'core/editor' )
			) {
				return wp.data.select( 'core/editor' ).getCurrentPostId();
			}

			return $( '#post_ID' ).val();
		},

		/**
		 * Read the post title, editor-aware.
		 *
		 * @since 3.12.0
		 *
		 * @return {string}
		 */
		getEditorTitle: function () {

			if (
				this.settings.editor && this.settings.editor.type === 'block'
				&& window.wp && wp.data && wp.data.select( 'core/editor' )
			) {
				return wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';
			}

			return $( '#title' ).val() || '';
		},

		/**
		 * Lock the Online Platform picker while the event is recurring.
		 *
		 * Online meetings (Zoom) are not supported for recurring events yet — the
		 * save path (EventMeetingManager::sync / CreateMeetingAjax) rejects them.
		 * So when the Recurrence type is anything other than "Never" we reset the
		 * provider to None (a recurring save then never submits a provider),
		 * disable the provider options, and surface the notice. Lite renders a
		 * DISABLED "Repeat" teaser (recurrence is Pro); we treat a disabled
		 * control as not-recurring so the picker is never locked there.
		 *
		 * Unlike the provider-change hide in syncOnlineMeetingUI, this lock is NOT a
		 * reversible "no data loss" affordance when the event already has a
		 * provisioned meeting: the options are disabled so the user cannot re-select
		 * the provider, and EventMeetingManager::sync skips recurring events, so an
		 * existing meeting is left in place at the provider rather than torn down.
		 *
		 * @since 3.12.0
		 */
		applyRecurrenceLock: function () {

			const recurring = !! (
				this.$recurrence
				&& this.$recurrence.length
				&& ! this.$recurrence.prop( 'disabled' )
				&& this.$recurrence.val()
				&& this.$recurrence.val() !== '0'
			);

			// Only external integration providers (e.g. Zoom) are locked for recurring
			// events — they don't support recurrence yet. "None" (value="") and
			// "Custom Link" (a plain URL with no API call) are always allowed.
			const $providerOptions = this.$onlineProvider.find( 'option' ).filter( function () {
				const val = $( this ).val();

				return val !== '' && val !== 'custom';
			} );

			if ( recurring ) {

				// Reset an integration-provider selection to None so a recurring save
				// never carries it, then lock those options. None / Custom Link stay.
				const current = this.$onlineProvider.val();

				if ( current !== '' && current !== 'custom' ) {
					this.$onlineProvider.val( '' );
				}

				$providerOptions.prop( 'disabled', true );

				return;
			}

			// Not recurring: restore each option's server-rendered disabled state.
			$providerOptions.each( function () {
				$( this ).prop( 'disabled', !! $( this ).data( 'scBaseDisabled' ) );
			} );
		},

		/**
		 * Sync the Online Platform UI to the selected provider:
		 *  - show the existing meeting block only when its provider matches the
		 *    current selection (hide it for None or any other provider);
		 *  - show the Create-Meeting button only for a creatable provider that has
		 *    no matching meeting block.
		 *
		 * Hiding the block is a non-destructive, pre-save affordance: the block (and
		 * its meeting meta) stay in the DOM, so re-selecting the provider restores
		 * it with no data loss. The save path (EventMeetingManager) still owns the
		 * destructive None-on-save removal.
		 *
		 * @since 3.12.0
		 */
		syncOnlineMeetingUI: function () {

			if ( ! this.$onlineProvider || ! this.$onlineProvider.length ) {
				return;
			}

			// Recurring events lock the picker to None before anything else runs.
			this.applyRecurrenceLock();

			const $opt = this.$onlineProvider.find( 'option:selected' );
			const slug = this.$onlineProvider.val();
			const $meeting = this.$el.find( '.sugar-calendar-metabox__online-meeting' );
			const $custom = this.$el.find( '.sugar-calendar-metabox__online-custom' );
			const $visibility = this.$el.find( '.sugar-calendar-metabox__field-row--online-visibility' );

			// The provider block belongs to a specific provider (data-provider).
			// Show it only when that provider is the current selection.
			const matches = $meeting.length > 0 && !! slug && slug === String( $meeting.data( 'provider' ) );
			const isCustom = slug === 'custom';

			$meeting.toggle( matches );

			// Custom Link shows the editable Event Link field instead of a card.
			$custom.toggle( isCustom );

			// "Show to" is shared: visible when a provider meeting is shown OR
			// Custom Link is selected.
			$visibility.toggle( matches || isCustom );

			// The selected provider being out of credits drives the invalid field
			// state (red border + credits message) and blocks meeting creation. The
			// server flags out-of-credits options with data-out-of-credits; track it
			// here so switching the dropdown updates the state without a reload.
			const outOfCredits = $opt.attr( 'data-out-of-credits' ) === '1';

			this.$onlineProvider.toggleClass( 'sugar-calendar-metabox__online-provider--invalid', outOfCredits );

			if ( this.$onlineCreditsError && this.$onlineCreditsError.length ) {
				this.$onlineCreditsError.toggle( outOfCredits );
			}

			if ( ! this.$createMeetingBtn || ! this.$createMeetingBtn.length ) {
				return;
			}

			const creatable = slug && slug !== '' && ! isCustom && ! $opt.prop( 'disabled' ) && ! outOfCredits;

			this.$createMeetingError.hide().text( '' );

			if ( creatable && ! matches ) {
				this.$createMeetingBtn
					.text( wp.i18n.sprintf( wp.i18n.__( 'Create %s Meeting', 'sugar-calendar-lite' ), $opt.text().trim() ) )
					.show();

				if ( this.$onlineDescription && this.$onlineDescription.length ) {
					this.$onlineDescription
						.text( wp.i18n.sprintf( wp.i18n.__( 'Click on create link, to generate the %s meeting for this event.', 'sugar-calendar-lite' ), $opt.text().trim() ) )
						.show();
				}
			} else {
				this.$createMeetingBtn.hide();

				if ( this.$onlineDescription && this.$onlineDescription.length ) {
					if ( outOfCredits ) {
						// Out-of-credits: Figma shows only the red credits message.
						this.$onlineDescription.hide();
					} else {
						this.$onlineDescription.text( this.onlineDescriptionDefault ).show();
					}
				}
			}
		},

		/**
		 * Create the meeting via AJAX, then inject the returned card in place.
		 *
		 * @since 3.12.0
		 *
		 * @param {Event} e Click event.
		 */
		onCreateMeetingClick: function ( e ) {

			e.preventDefault();

			// Guard against double-submit: pointer-events:none (is-busy) blocks a
			// second mouse click, but a keyboard user can still re-activate the
			// focused link with Enter while the create request is in flight.
			if ( this.$createMeetingBtn.hasClass( 'is-busy' ) ) {
				return;
			}

			const title = this.getEditorTitle();
			const postId = this.getEditorPostId();
			const provider = this.$onlineProvider.val();
			const cfg = this.settings.create_meeting || {};

			if ( ! title || ! this.$startDate.val() ) {
				this.$createMeetingError
					.text( wp.i18n.__( 'Add a title and start date/time before creating the meeting.', 'sugar-calendar-lite' ) )
					.show();
				return;
			}

			const $btn = this.$createMeetingBtn;
			const providerName = this.$onlineProvider.find( 'option:selected' ).text().trim();

			// Hide the link and drop a full-width loading card into the slot the
			// finished meeting card will occupy (right after the Online row).
			$btn.addClass( 'is-busy' ).hide();
			this.$createMeetingError.hide().text( '' );

			const $loadingCard = $(
				'<div class="sugar-calendar-metabox__online-loading" role="status" aria-live="polite">' +
					'<span class="sugar-calendar-metabox__online-loading-spinner" aria-hidden="true">' +
						'<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">' +
							'<path d="M2 18C2 26.8366 9.16344 34 18 34C26.8366 34 34 26.8366 34 18C34 9.16344 26.8366 2 18 2C9.16345 2 2 9.16344 2 18Z" stroke="#DCDCDE" stroke-width="4"/>' +
							'<path fill-rule="evenodd" clip-rule="evenodd" d="M31.8987 9.71298C32.8889 9.29268 34.0323 9.75465 34.4526 10.7448C36.3434 15.1993 36.536 20.3815 34.579 25.2253C34.176 26.2227 33.0408 26.7045 32.0435 26.3016C31.0461 25.8986 30.5643 24.7634 30.9672 23.7661C32.5217 19.9187 32.3705 15.8092 30.8669 12.2669C30.4466 11.2767 30.9085 10.1333 31.8987 9.71298Z" fill="#C3C4C7"/>' +
						'</svg>' +
					'</span>' +
					'<span class="sugar-calendar-metabox__online-loading-text"></span>' +
				'</div>'
			);

			$loadingCard.find( '.sugar-calendar-metabox__online-loading-text' ).text(
				wp.i18n.sprintf(
					// translators: %s is the online meeting provider name, e.g. "Zoom".
					wp.i18n.__( 'Creating %s Meeting…', 'sugar-calendar-lite' ),
					providerName
				)
			);

			this.$onlineProvider
				.closest( '.sugar-calendar-metabox__field-row' )
				.after( $loadingCard );

			const showError = ( msg, linkUrl, linkText ) => {
				$loadingCard.remove();
				$btn.removeClass( 'is-busy' ).show();

				const $err = this.$createMeetingError.empty();

				// Build with the DOM API so the (server-controlled) URL/text can
				// never inject markup — the anchor is rendered, not the string.
				$err.append(
					document.createTextNode(
						msg || wp.i18n.__( 'Couldn’t create the meeting — try again.', 'sugar-calendar-lite' )
					)
				);

				if ( linkUrl ) {
					$err
						.append( ' ' )
						.append(
							$( '<a>' )
								.attr( 'href', linkUrl )
								.text( linkText || wp.i18n.__( 'Connect', 'sugar-calendar-lite' ) )
						);
				}

				$err.show();
			};

			// All metabox fields by name (start/end/tz/all_day/online_provider…),
			// plus the action/nonce/post id/provider/title.
			const data = this.$el.find( ':input' ).serialize()
				+ '&action=' + encodeURIComponent( cfg.action )
				+ '&nonce=' + encodeURIComponent( cfg.nonce )
				+ '&post_id=' + encodeURIComponent( postId )
				+ '&provider=' + encodeURIComponent( provider )
				+ '&title=' + encodeURIComponent( title );

			$.post( window.ajaxurl, data )
				.done( ( resp ) => {

					if ( ! resp || ! resp.success || ! resp.data ) {
						const data = ( resp && resp.data ) || {};
						showError( data.message, data.settings_url, data.settings_text );
						return;
					}

					// Swap the loading card for the real meeting card in the same
					// slot; the link stays hidden (a meeting now exists).
					$loadingCard.remove();
					$btn.removeClass( 'is-busy' );

					this.$onlineProvider
						.closest( '.sugar-calendar-metabox__field-row' )
						.after( resp.data.card_html );

					// syncOnlineMeetingUI owns the Online-section UI state; run it now
					// that a meeting block exists so the Create button stays hidden and
					// the description reverts from the "click to generate" prompt.
					this.syncOnlineMeetingUI();

					if ( resp.data.nonce ) {
						this.settings.create_meeting.nonce = resp.data.nonce;
					}

					// Classic: reflect the now-real post in the URL so a manual
					// reload keeps editing the same event.
					const isBlock = this.settings.editor && this.settings.editor.type === 'block';

					if ( ! isBlock && resp.data.post_id ) {
						window.history.replaceState( {}, '', 'post.php?post=' + resp.data.post_id + '&action=edit' );
					}
				} )
				.fail( () => showError() );
		},

		/**
		 * Remove the meeting: confirm, then delete via AJAX and revert the Online
		 * dropdown to None. Editor-agnostic (Classic + Block), no reload.
		 *
		 * @since 3.12.0
		 *
		 * @param {Event} e Click event.
		 */
		onRemoveMeetingClick: function ( e ) {

			e.preventDefault();

			const $btn = $( e.currentTarget );

			// Guard against a double-submit while a request is in flight.
			if ( $btn.hasClass( 'is-busy' ) ) {
				return;
			}

			const providerName = $btn.data( 'providerName' ) || wp.i18n.__( 'online', 'sugar-calendar-lite' );

			const title = wp.i18n.sprintf(
				// translators: %s is the online meeting provider name, e.g. "Zoom".
				wp.i18n.__( 'Remove %s meeting?', 'sugar-calendar-lite' ),
				providerName
			);
			const message = wp.i18n.sprintf(
				// translators: %s is the online meeting provider name, e.g. "Zoom".
				wp.i18n.__( 'This deletes the meeting from %s and switches this event’s online location to None. Existing link will no longer work.', 'sugar-calendar-lite' ),
				providerName
			);

			const doRemove = () => this.removeMeeting( $btn );

			// jQuery-Confirm renders `icon` inside `<i class="…"></i>`; closing that
			// tag, inserting the SVG, and reopening it swaps in the image — the same
			// injection the Zoom disconnect modal uses.
			const iconUrl = ( this.settings.remove_meeting || {} ).icon_url;
			const icon = iconUrl
				? '"></i><img src="' + iconUrl + '" alt="" width="36" height="36"><i class="'
				: '';

			// Styled danger-zone modal (same jQuery-Confirm dialog the Zoom
			// disconnect uses). Fall back to the native confirm if it isn't loaded.
			if ( $.confirm ) {
				$.confirm( {
					typeAnimated:       false,
					draggable:          false,
					animateFromElement: false,
					boxWidth:           '400px',
					useBootstrap:       false,
					type:               'red',
					// This bundled jQuery-Confirm has no `boxClass` option (and
					// `columnClass` needs useBootstrap). Add the scoping class to the
					// dialog box directly so the modal-specific CSS can target it.
					onOpenBefore: function () {
						this.$jconfirmBox.addClass( 'sugar-calendar-online-remove-confirm' );
					},
					icon,
					title,
					content:            message,
					buttons: {
						confirm: {
							text:     wp.i18n.__( 'Remove', 'sugar-calendar-lite' ),
							btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-red',
							action:   doRemove,
						},
						cancel: {
							text:     wp.i18n.__( 'Cancel', 'sugar-calendar-lite' ),
							btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg',
						},
					},
				} );
			} else if ( window.confirm( message ) ) { // eslint-disable-line no-alert
				doRemove();
			}
		},

		/**
		 * POST the remove request, then drop the card and reset the dropdown.
		 *
		 * @since 3.12.0
		 *
		 * @param {jQuery} $btn The clicked Remove button.
		 */
		removeMeeting: function ( $btn ) {

			const cfg = this.settings.remove_meeting || {};
			const postId = this.getEditorPostId();

			$btn.addClass( 'is-busy' );

			const data = {
				action:  cfg.action,
				nonce:   cfg.nonce,
				post_id: postId,
			};

			$.post( window.ajaxurl, data )
				.done( ( resp ) => {

					if ( ! resp || ! resp.success ) {
						$btn.removeClass( 'is-busy' );
						window.alert( // eslint-disable-line no-alert
							( resp && resp.data && resp.data.message ) ||
							wp.i18n.__( 'Couldn’t remove the meeting — try again.', 'sugar-calendar-lite' )
						);
						return;
					}

					if ( resp.data && resp.data.nonce ) {
						this.settings.remove_meeting.nonce = resp.data.nonce;
					}

					// Drop the whole meeting block and revert the Online dropdown to
					// None. syncOnlineMeetingUI (bound to change) then restores the
					// default section state.
					$btn.closest( '.sugar-calendar-metabox__online-meeting' ).remove();
					this.$onlineProvider.val( '' ).trigger( 'change' );
				} )
				.fail( () => {
					$btn.removeClass( 'is-busy' );
					window.alert( // eslint-disable-line no-alert
						wp.i18n.__( 'Couldn’t remove the meeting — try again.', 'sugar-calendar-lite' )
					);
				} );
		},

		initChoicesJS: function () {

			$( '.choicesjs-select', this.$el ).each( ( i, el ) => {
				new Choices( el, {
					itemSelectText: '',
					allowHTML: true,
				} );
			} );

			this.initChoicesJSForTags();
		},

		/**
		 * Initialize ChoicesJS for tags.
		 *
		 * @since 3.7.0
		 *
		 * @return {void}
		 */
		initChoicesJSForTags: function () {

			// Create configuration.
			const
				config = this.settings.choicesjs_config,
				select = this.$tagsSelect[ 0 ];

			// Set noResultsText to indicate users can add new tags.
			config.noResultsText = this.settings.strings.add_new_tag;

			const TagsSelect = new Choices(
				select,
				config
			);

			const currentValue = TagsSelect.getValue( true );

			TagsSelect
				.clearStore()
				.setChoices(
					this.settings.all_tags_choices,
					'value',
					'label',
					true
				)
				.setChoiceByValue( currentValue );

			$( select ).data( 'choicesjs', TagsSelect );

			// Bind event for adding custom tags on keyboard input
			$( select ).siblings( 'input' ).on( 'keydown', this.addCustomTagInput.bind( this ) );
		},

		initDatepickers: function () {

			$( '[data-datepicker]', this.$el ).datepicker( {
				dateFormat: 'yy-mm-dd',
				firstDay: this.settings.start_of_week,
				beforeShow: () => {
					$( '#ui-datepicker-div' )
						.removeClass( 'ui-datepicker' )
						.addClass( 'sugar-calendar-datepicker' );
				}
			} );

			// Set the end date min date to the start date.
			// Set the end date to the start date if it is empty.
			this.$startDate.on( 'change', () => {

				const // Get start and end date time for comparison.
					startDateTime = this.getEventDateTime(
						this.$startDate,
						this.$startTimeHour,
						this.$startTimeMinute,
						this.$startTimeAmPm,
						this.$startTz
					),
					endDateTime = this.getEventDateTime(
						this.$endDate,
						this.$endTimeHour,
						this.$endTimeMinute,
						this.$endTimeAmPm,
						this.$endTz
					);

				if (
					this.$endDate.val() === ''
					||
					endDateTime.isBefore( startDateTime )
				) {
					this.$endDate.datepicker( 'setDate', this.$startDate.val() );
				}
			} );

			// Time adjustment for start and end time hour fields.
			this.$startTimeHour.on( 'change', () => this.adjustTime( this.$startTimeHour, this.$endTimeHour, 1 ) );
			this.$endTimeHour.on( 'change', () => this.adjustTime( this.$endTimeHour, this.$startTimeHour, -1 ) );

			// Set end time minute to start time minute if it is empty.
			this.$startTimeMinute.on( 'change', () => {

				if ( this.$endTimeMinute.val() !== '' ) {
					return;
				}

				this.$endTimeMinute.val( this.$startTimeMinute.val() );
			} );

			// Set end time am/pm to start time am/pm if it is empty.
			this.$startTimeAmPm.on( 'change', () => {

				if ( this.$endTimeAmPm.val() !== '' ) {
					return;
				}

				this.$endTimeAmPm.val( this.$startTimeAmPm.val() );
			} );

			// Set the start date to the end date if it is empty.
			// Set the start date max date to the end date.
			this.$endDate.on( 'change', () => {

				const // Get start and end date time for comparison.
					startDateTime = this.getEventDateTime(
						this.$startDate,
						this.$startTimeHour,
						this.$startTimeMinute,
						this.$startTimeAmPm,
						this.$startTz
					),
					endDateTime = this.getEventDateTime(
						this.$endDate,
						this.$endTimeHour,
						this.$endTimeMinute,
						this.$endTimeAmPm,
						this.$endTz
					);

				if (
					this.$startDate.val() === ''
					||
					endDateTime.isBefore( startDateTime )
				) {
					this.$startDate.datepicker( 'setDate', this.$endDate.val() );
				}
			} );

			// Set start time minute to end time minute if it is empty.
			this.$endTimeMinute.on( 'change', () => {

				if ( this.$startTimeMinute.val() !== '' ) {
					return;
				}

				this.$startTimeMinute.val( this.$endTimeMinute.val() );
			} );

			// Set start time am/pm to end time am/pm if it is empty.
			this.$endTimeAmPm.on( 'change', () => {

				if ( this.$startTimeAmPm.val() !== '' ) {
					return;
				}

				this.$startTimeAmPm.val( this.$endTimeAmPm.val() );
			} );

			// Validate dates for block editor. Disable the submit button if the dates are invalid.
			if ( typeof( wp.blockEditor ) === 'object' ) {

				$.each( [
					this.startDate,
					this.$startTimeHour,
					this.$startTimeMinute,
					this.$startTimeAmPm,
					this.$endDate,
					this.$endTimeHour,
					this.$endTimeMinute,
					this.$endTimeAmPm

				], function ( i, e ) {

					$( e ).on( 'change', this.blockEditorDateValidation.bind( this ) );
				}.bind( this ) );
			}
		},

		getDate: function ( date ) {
			try {
				date = $.datepicker.parseDate( 'yy-mm-dd', date );
			} catch ( error ) {
				date = null;
			}

			return date;
		},

		toggleTimezones: function () {

			const checked = this.$allDay.prop( 'checked' );

			if ( checked ) {
				this.$timezones.hide();
			} else {
				this.$timezones.show();
			}
		},

		/**
		 * Adjust the target time based on the source time and increment.
		 *
		 * @since 3.3.0
		 *
		 * @param {jQuery} sourceElement
		 * @param {jQuery} targetElement
		 * @param {int} increment
		 *
		 * @return {void}
		 */
		adjustTime( sourceElement, targetElement, increment ) {

			// If the target time hour is already set, exit.
			if ( targetElement.val() !== '' ) {
				return;
			}

			const clockType = parseInt( sugar_calendar_admin_event_meta_box.clock_type, 10 );
			const sourceHour = parseInt( sourceElement.val(), 10 );

			// Calculate the new hour, adjusting by increment and clock type.
			let newHour = ( sourceHour + increment + clockType ) % clockType;

			// Correction for 12-hour format where 0 should be 12.
			if ( clockType === 12 && newHour === 0 ) {
				newHour = 12;
			}

			targetElement.val(
				newHour.toString().padStart( 2, '0' )
			);
		},

		/**
		 * Check if start and end date is valid.
		 *
		 * @since 3.3.0
		 *
		 * @return {boolean}
		 */
		isStartEndInvalid: function () {

			// If settings timezone type is multi but value of start and end timezone is different, return false.
			if (
				this.settings.timezone_type === 'multi'
				&&
				this.$startTz.val() !== this.$endTz.val()
			) {
				return false;
			}

			const // Get start and end date time for comparison.
				startDateTime = this.getEventDateTime(
					this.$startDate,
					this.$startTimeHour,
					this.$startTimeMinute,
					this.$startTimeAmPm,
					this.$startTz
				),
				endDateTime = this.getEventDateTime(
					this.$endDate,
					this.$endTimeHour,
					this.$endTimeMinute,
					this.$endTimeAmPm,
					this.$endTz.length > 0 ? this.$endTz : this.$startTz
				);

			return endDateTime.isBefore( startDateTime ) || endDateTime.isSame( startDateTime );
		},

		/**
		 * Validate the start and end date time.
		 * If end date time is before start date time,
		 * highlight the fields and prevent submission.
		 *
		 * @since 3.3.0
		 *
		 * @param {Event} e
		 *
		 * @return {void}
		 */
		validateDates: function ( e ) {

			// If end date time is before start date time, show error.
			// If end date time is the same as start date time, show error.
			// Works only if all day is not checked.
			if (
				this.$allDay.prop( 'checked' ) === false
				&&
				this.isStartEndInvalid()
			) {

				e.preventDefault();

				// Open the duration section.
				this.$sectionButtons.filter( '[data-id=duration]' ).click();

				// Add error class to the date time fields.
				this.$sections.filter( '[data-id=duration]' ).addClass( 'sugar-calendar-field-dates-invalid' );

				// Stop submission.
				return;
			}
		},

		/**
		 * Get event date and time currently set based on provided elements, with defaults.
		 *
		 * @since 3.3.0
		 *
		 * @param {jQuery} dateElement - The jQuery element for the date input (optional).
		 * @param {jQuery} hourElement - The jQuery element for the hour input (optional).
		 * @param {jQuery} minuteElement - The jQuery element for the minute input (optional).
		 * @param {jQuery} ampmElement - The jQuery element for the AM/PM input (optional for 24-hour format).
		 * @param {jQuery} tzElement - The jQuery element for the timezone input (optional).
		 *
		 * @return {Date} - A moment.js date object.
		 */
		getEventDateTime: function ( dateElement, hourElement, minuteElement, ampmElement, tzElement ) {

			const clockType = sugar_calendar_admin_event_meta_box.clock_type;
			const defaultDate = moment().format( 'YYYY-MM-DD' );

			// Check if elements are provided and get their values, or set defaults
			const date = ( dateElement && dateElement.val() ) || defaultDate;
			const hour = ( hourElement && hourElement.val() ) || '01';
			const minute = ( minuteElement && minuteElement.val() ) || '00';
			const ampm = ( clockType === '12' && ampmElement ) ? ( ampmElement.val() || 'AM' ) : '';
			const timezone = ( tzElement && tzElement.val() ) || '';

			// Return the moment.js date object.
			return this.createMomentObject( date, hour, minute, ampm, clockType, timezone );
		},

		/**
		 * Create a moment.js date object based on provided date and time values.
		 *
		 * @param {String} date - The date string in YYYY-MM-DD format.
		 * @param {String} hour - The hour string in 00-23 format.
		 * @param {String} minute - The minute string in 00-59 format.
		 * @param {Strimg} ampm  - The AM/PM string in AM/PM format.
		 * @param {String} clockType - The clock type in 12 or 24 value.
		 * @param {String} timezone - The timezone string.
		 *
		 * @return {Date} - A moment.js date object.
		 */
		createMomentObject( date, hour, minute, ampm, clockType, timezone ) {

			let // Convert to integer for calculations.
				hourInt = parseInt( hour, 10 ),
				minuteInt = parseInt( minute, 10 );

			// If clockType is 12-hour and am/pm is provided.
			if ( clockType === '12' && ampm ) {

				const ampmLower = ampm.toLowerCase();

				if ( ampmLower === "pm" && hourInt !== 12 ) {

					// Convert PM to 24-hour format.
					hourInt += 12;

				} else if ( ampmLower === "am" && hourInt === 12 ) {

					// Convert 12 AM to 00.
					hourInt = 0;
				}
			}

			// If clockType is 24-hour, ensure hour is in the valid range.
			if ( clockType === '24' ) {
				hourInt = Math.min( Math.max( hourInt, 0 ), 23 );
			}

			// Ensure minute is in valid range
			minuteInt = Math.min( Math.max( minuteInt, 0 ), 59 );

			// Construct the time string in 24-hour format
			const timeString = `${date} ${hourInt.toString().padStart( 2, '0' )}:${minuteInt.toString().padStart( 2, '0' )}`;

			// Create and return a moment.js object
			return moment.tz( timeString, "YYYY-MM-DD HH:mm", timezone );
		},

		/**
		 * Block editor date validation.
		 *
		 * @since 3.3.0
		 *
		 * @return {void}
		 */
		blockEditorDateValidation: function () {

			// Only work if start and end time fields are not empty.
			if (
				this.$startDate.val() === ''
				||
				this.$startTimeHour.val() === ''
				||
				this.$endDate.val() === ''
				||
				this.$endTimeHour.val() === ''
			) {
				return;
			}

			const // Get start and end date time for comparison.
				startDateTime = this.getEventDateTime(
					this.$startDate,
					this.$startTimeHour,
					this.$startTimeMinute,
					this.$startTimeAmPm,
					this.$startTz
				),
				endDateTime = this.getEventDateTime(
					this.$endDate,
					this.$endTimeHour,
					this.$endTimeMinute,
					this.$endTimeAmPm,
					this.$endTz
				),
				errorLockName = 'invalid-date-error';

			if ( this.isStartEndInvalid() ) {

				// Lock the editor.
				wp.data.dispatch( 'core/editor' ).lockPostSaving( errorLockName );

				// Show error message.
				wp.data.dispatch( 'core/notices' ).createNotice(
					'error',
					wp.i18n.__( 'End date and time cannot be before the start date and time.', 'sugar-calendar-lite' ),
					{ id: errorLockName, isDismissible: true }
				);

			} else if ( endDateTime.isAfter( startDateTime ) ) {

				// Unlock the editor.
				wp.data.dispatch( 'core/editor' ).unlockPostSaving( errorLockName );

				// Remove error message.
				wp.data.dispatch( 'core/notices' ).removeNotice( errorLockName );
			}
		},

		/**
		 * Add custom tag on keyboard input
		 *
		 * @since 3.7.0
		 *
		 * @param {object} event Event object.
		 */
		addCustomTagInput: function( event ) {

			// Only process for Enter or comma key.
			if ( [ 'Enter', ',' ].indexOf( event.key ) < 0 ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			const $input = $( event.target );
			const $select = $input.closest( '.choices' ).find( 'select' );
			const choicesObj = $select.data( 'choicesjs' );

			// Verify we have a Choices instance and input value.
			if ( ! choicesObj || event.target.value.length === 0 ) {
				return;
			}

			// Get the tag label and clean it - add escaping for security.
			const tagLabel = _.escape( event.target.value.trim() );

			// Skip if empty.
			if ( tagLabel === '' ) {
				choicesObj.clearInput();
				return;
			}

			// Get existing tag labels more efficiently.
			const existingLabels = _.map( choicesObj.getValue(), 'label' ).map( ( label ) => {
				return label.toLowerCase().trim();
			} );

			// Skip if already exists.
			if ( existingLabels.indexOf( tagLabel.toLowerCase() ) >= 0 ) {
				choicesObj.clearInput();
				return;
			}

			// Check if tag exists in all available tags first.
			const existingTag = _.find( this.settings.all_tags_choices || [], {
				label: tagLabel,
			} );

			if ( existingTag && existingTag.value ) {
				// Use existing tag if found.
				choicesObj.setChoiceByValue( existingTag.value );
			} else {
				// Add as new tag.
				choicesObj.setChoices(
					[ {
						value: tagLabel,
						label: tagLabel,
						selected: true,
					} ],
					'value',
					'label',
					false
				);
			}

			// Clear the input field.
			choicesObj.clearInput();
		},

		/**
		 * Initialize block adjustments.
		 *
		 * @since 3.8.2
		 *
		 * @return {void}
		 */
		initBlockAdjustments: function () {

			// Only proceed for block editor.
			const editorSettings = this.settings.editor || {};

			if ( editorSettings.type !== 'block' ) {
				return;
			}

			const postTypeSetting = this.settings.post_type || 'sc_event';
			const taxonomiesToHide = Array.isArray( editorSettings.taxonomies_to_hide ) ? editorSettings.taxonomies_to_hide : [];

			wp.domReady( function () {

				const { select, dispatch, subscribe } = wp.data;

				const removePanels = () => {

					const postType = select( 'core/editor' ).getCurrentPostType();

					if ( postType !== postTypeSetting ) {
						return;
					}

					// Remove the sidebar taxonomy panel(s) for the current post type.
					taxonomiesToHide.forEach( ( slug ) => {
						const panelName = 'taxonomy-panel-' + slug;
						dispatch( 'core/edit-post' ).removeEditorPanel( panelName );
					} );
				};

				removePanels();

				subscribe( removePanels );
			} );
		},
	};

	SugarCalendar.Admin.EventMetabox.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, sugar_calendar_admin_event_meta_box );
