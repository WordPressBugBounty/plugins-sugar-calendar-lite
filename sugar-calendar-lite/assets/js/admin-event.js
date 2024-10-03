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
			this.$eventTitle.on( 'input propertychange', this.toggleActivateDefaultSubmitButton.bind( this ) );
			this.$eventTitle.on( 'input propertychange', this.toggleAlertTitleEmpty.bind( this ) );

			// Register submit button on hover listener.
			this.$eventSubmitButton.on( 'mouseenter', this.toggleAlertTitleEmpty.bind( this ) );
		},

		manipulateElements: function () {

			// Disable the default submit button if title is empty.
			this.toggleActivateDefaultSubmitButton();
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
			} else {
				this.$eventSubmitButton.removeAttr( 'title' );
			}

			// Toggle title input border color.
			this.$eventTitle.toggleClass( 'sugar-calendar-field-title-empty', isTitleEmpty );
		},

		/**
		 * Toggle disabled state of the default submit button.
		 * If title is empty, disable the default submit button.
		 *
		 * @since 3.3.0
		 *
		 * @returns {void}
		 */
		toggleActivateDefaultSubmitButton: function () {

			// If title is empty, disable the default submit button.
			if ( this.$eventTitle.val() === '' ) {
				this.$eventSubmitButton.attr( 'disabled', true );
			} else {
				this.$eventSubmitButton.removeAttr( 'disabled' );
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

			/**
			 * State of lock and notice.
			 *
			 * @var {boolean} isLocked - Save post locked state.
			 * @var {boolean} showError - Showing error notice.
			 */
			let isLocked = false,
				showError = false;

			// Localized error notice. Revert to default if not available.
			const errorNoticeTitleMissing = this.localizedScripts.notice_title_required;

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
				 * @var {string} editedPostStatus - The current post status.
				 * @var {boolean} newPostStatus - If the post is new (draft or auto-draft).
				 * @var {boolean} isPublishing - If the editor post status is set to publish or publish sidebar is opened.
				 */
				const
					title = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' ),
					editedPostStatus = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' ),
					newPostStatus = $.inArray( editedPostStatus, [ 'auto-draft', 'draft', undefined ] ) > -1,
					isPublishing = isPublishSidebarOpened || ! newPostStatus;

				// If title is empty, lock the editor save function.
				if ( '' === title ) {

					// Lock the editor if not locked. Avoid maximum call stack error.
					if ( ! isLocked ) {

						// Set the locked state to true.
						isLocked = true;

						// Lock the editor.
						wp.data.dispatch( 'core/editor' ).lockPostSaving( 'save-lock-title' );
					}

					// Show notice if publish sidebar is opened or changing post status.
					// Avoid maximum call stack error.
					if ( ! showError && isPublishing ) {

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
