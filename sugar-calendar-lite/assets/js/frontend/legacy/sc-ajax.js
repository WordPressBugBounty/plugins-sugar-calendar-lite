/* global sc_vars */
jQuery( document ).ready( function( $ ) {

	let sc_events_cal = $( '.sc_events_calendar' );

	// Add the visitor's timezone in the form.
	let tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

	if ( sc_events_cal.length > 0 && sc_vars.cal_sc_visitor_tz && sc_vars.cal_sc_visitor_tz === '1' ) {
		// Get the TZ and reload the calendar.
		scPopulateVisitorTzInput();
		scReloadCalendar( $( '.sc_events_calendar' ).attr( 'id' ), $( '#sc_event_select' ).serialize() );
	}

	/**
	 * Populate the visitor timezone input field.
	 *
	 * @since 3.1.2
	 */
	function scPopulateVisitorTzInput() {

		let tz_input = $( 'form input[name="sc_visitor_tz"]' );

		if ( tz && tz.length > 0 && tz_input.length > 0 ) {
			tz_input.val( tz );
		}
	}

	/**
	 * Reload the calendar.
	 *
	 * @param {string} calendar The calendar ID.
	 * @param {string} data     The serialized form data.
	 *
	 * @since 3.1.2
	 */
	function scReloadCalendar( calendar, data ) {

		$.post( sc_vars.ajaxurl, data, function ( response ) {
			$( '#' + calendar ).parent().html( response );
			scResizeCal();
			scPopulateVisitorTzInput();

	 	} ).done( function() {
	 		document.body.style.cursor = 'default';
	 	} );
	}

	/* Button Click */
	$( 'body' ).on( 'submit', '.sc_events_form', function() {
		document.body.style.cursor = 'wait';

		let calendar = $( this ).parents( 'div.sc_events_calendar' ).attr( 'id' ),
			data = $( this ).serialize();

		scReloadCalendar( calendar, data );

		return false;
	} );

	/* Page Resize */
	function scResizeCal() {
		var winwidth = $( window ).width();

		if ( winwidth <= 480 ) {
			if ( ! $( '.sc_events_calendar' ).hasClass( 'sc_small' ) ) {
				$( '#sc_event_select' ).hide();
			} else {
				$( '#sc_event_select' ).show();
			}
		} else {
			$( '#sc_event_select' ).show();
		}
	}

	if ( sc_events_cal.length > 0 ) {
		/* Listen for resize */
		$( window ).resize( function() {
			scResizeCal();
		} );
	}

	/* Resize on load */
	scResizeCal();
} );
