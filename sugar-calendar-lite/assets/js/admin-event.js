/* globals jQuery */
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

			// Register localized scripts. Set defaults if not available.
			this.getLocalizedScripts();

			this.bindEvents();

			// Element manipulation.
			this.manipulateElements();

			// Run if using the block editor.
			if ( 'object' === typeof( wp.blockEditor ) ) {
				this.blockEditorCustomValidation();
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
		 *
		 * @returns {void}
		 */
		toggleActivatePublishGroupButtons: function () {

			// If title is empty, disable the default publish metabox buttons.
			if ( this.$eventTitle.val() === '' ) {
				this.$eventSubmitButton.attr( 'disabled', true );
				this.$eventSaveDraftButton.attr( 'disabled', true );
				this.$eventPreviewButton.attr( 'disabled', true );
			} else {
				this.$eventSubmitButton.removeAttr( 'disabled' );
				this.$eventSaveDraftButton.removeAttr( 'disabled' );
				this.$eventPreviewButton.removeAttr( 'disabled' );
			}
		},

		/**
		 * Prevent the preview button from being clicked if title is empty.
		 *
		 * @since 3.8.2
		 *
		 * @returns {void}
		 */
		preventPreviewButtonClick: function ( e ) {

			if ( this.$eventTitle.val() === '' ) {
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
