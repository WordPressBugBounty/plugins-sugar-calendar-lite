/* global sugar_calendar_admin_notifications, ajaxurl */

/**
 * Sugar Calendar Admin Notifications.
 *
 * @since 3.0.0
 */

'use strict';

var SugarCalendarAdminNotifications = window.SugarCalendarAdminNotifications || ( function ( document, window, $ ) {

	/**
	 * Elements holder.
	 *
	 * @since 3.0.0
	 *
	 * @type {object}
	 */
	var el = {
		$notifications: $( '#sugar-calendar-notifications' ),
		$nextButton: $( '#sugar-calendar-notifications .navigation .next' ),
		$prevButton: $( '#sugar-calendar-notifications .navigation .prev' ),
		$adminBarCounter: $( '#wp-admin-bar-sugar-calendar-menu .sugar-calendar-admin-bar-menu-notification-counter' ),
	};

	/**
	 * Public functions and properties.
	 *
	 * @since 3.0.0
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 3.0.0
		 */
		init: function () {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.0.0
		 */
		ready: function () {

			app.updateNavigation();
			app.events();
		},

		/**
		 * Register JS events.
		 *
		 * @since 3.0.0
		 */
		events: function () {

			el.$notifications
				.on( 'click', '.dismiss', app.dismiss )
				.on( 'click', '.next', app.navNext )
				.on( 'click', '.prev', app.navPrev );
		},

		/**
		 * Click on the Dismiss notification button.
		 *
		 * @since 3.0.0
		 *
		 * @param {object} event Event object.
		 */
		dismiss: function ( event ) {

			if ( el.$currentMessage.length === 0 ) {
				return;
			}

			// AJAX call - update option.
			var data = {
				action: 'sugar_calendar_notification_dismiss',
				nonce: sugar_calendar_admin_notifications.nonce,
				id: el.$currentMessage.data( 'message-id' ),
			};

			$.post( ajaxurl, data, function ( response ) {
				if ( ! response.success ) {
					return;
				}

				// Update counter.
				var count = parseInt( el.$adminBarCounter.text(), 10 );
				if ( count > 1 ) {
					--count;
					el.$adminBarCounter.html( '<span>' + count + '</span>' );
				} else {
					el.$adminBarCounter.remove();
				}

				// Remove notification.
				var $nextMessage = el.$nextMessage.length < 1 ? el.$prevMessage : el.$nextMessage;

				if ( $nextMessage.length === 0 ) {
					el.$notifications.remove();
				} else {
					el.$currentMessage.remove();
					$nextMessage.addClass( 'current' );
					app.updateNavigation();
				}
			} );
		},

		/**
		 * Click on the Next notification button.
		 *
		 * @since 3.0.0
		 *
		 * @param {object} event Event object.
		 */
		navNext: function ( event ) {

			if ( el.$nextButton.hasClass( 'disabled' ) ) {
				return;
			}

			el.$currentMessage.removeClass( 'current' );
			el.$nextMessage.addClass( 'current' );

			app.updateNavigation();
		},

		/**
		 * Click on the Previous notification button.
		 *
		 * @since 3.0.0
		 *
		 * @param {object} event Event object.
		 */
		navPrev: function ( event ) {

			if ( el.$prevButton.hasClass( 'disabled' ) ) {
				return;
			}

			el.$currentMessage.removeClass( 'current' );
			el.$prevMessage.addClass( 'current' );

			app.updateNavigation();
		},

		/**
		 * Update navigation buttons.
		 *
		 * @since 3.0.0
		 */
		updateNavigation: function () {

			el.$currentMessage = el.$notifications.find( '.sugar-calendar-notifications-message.current' );
			el.$nextMessage = el.$currentMessage.next( '.sugar-calendar-notifications-message' );
			el.$prevMessage = el.$currentMessage.prev( '.sugar-calendar-notifications-message' );

			if ( el.$nextMessage.length === 0 ) {
				el.$nextButton.addClass( 'disabled' );
			} else {
				el.$nextButton.removeClass( 'disabled' );
			}

			if ( el.$prevMessage.length === 0 ) {
				el.$prevButton.addClass( 'disabled' );
			} else {
				el.$prevButton.removeClass( 'disabled' );
			}
		},
	};

	return app;

}( document, window, jQuery ) );

// Initialize.
SugarCalendarAdminNotifications.init();
