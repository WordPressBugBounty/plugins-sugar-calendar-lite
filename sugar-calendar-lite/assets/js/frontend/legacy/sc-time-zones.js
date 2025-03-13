/* global wp, sc_vars, Intl */

var SCTimeZones = window.SCTimeZones || ( function( document, window, $ ) {

	const app = {
		/**
		 * Start the engine.
		 *
		 * @since 3.1.2
		 */
		init() {
			// Document ready.
			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.1.2
		 */
		ready() {
			app.convertEventsTime();
		},

		/**
		 * Convert the event dates and time to the visitor's timezone.
		 *
		 * @since 3.1.2
		 */
		convertEventsTime() {
			// Get elements and browser time zone
			var dates   = $( '.sc-date-start time, .sc-date-end time, .sc-frontend-single-event__details__val-date time, .sc-event-ticketing-checkout-totals__summary-block__date time, .sc_event_date time, .sc-frontend-single-event__details__val time' ),
				times   = $( '.sc_event_time time, .sc_event_start_time time, .sc_event_end_time time, .sc-frontend-single-event__details__time time, .sc-event-ticketing-checkout-totals__summary-block__time time, .sugar-calendar-block__event-cell__time time, .sc-frontend-single-event__details__val-time time' ),
				tz      = Intl.DateTimeFormat().resolvedOptions().timeZone,
				convert = wp.date.dateI18n;

			// Bail if no browser time zone
			if ( ! tz.length ) {
				return;
			}

			// Update date HTML
			dates.each( function() {
				var date = $( this ),
					dt   = date.attr( 'datetime' ),
					org  = date.html(),
					html = convert( SCTimezoneConvert.date_format, dt, tz );

				// Set original to data attribute, and update HTML
				date
					.attr( 'data-original', org )
					.html( html );
			} );

			// Update time HTML
			times.each( function() {
				var time = $( this ),
					dt   = time.attr( 'datetime' ),
					org  = time.html(),
					html = convert( SCTimezoneConvert.time_format, dt, tz );

				// Set original to data attribute, and update HTML
				time
					.attr( 'data-original', org )
					.html( html );
			} );
		}
	}

	return app;

} ( document, window, jQuery ) );

SCTimeZones.init();
