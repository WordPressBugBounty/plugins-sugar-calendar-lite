/* globals jQuery */
( function ( $ ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.Event = {

		/**
		 * Initialize.
		 *
		 * @since 3.0.0
		 */
		init: function () {

			this.$clearCalendarButton = $( '#sc_event_category-clear' );
			this.$calendarListRadios = $( '#sc_event_categorychecklist input' );

			this.bindEvents();
		},

		bindEvents: function () {

			this.$clearCalendarButton.on( 'click', this.clearCalendar.bind( this ) );
		},

		clearCalendar: function ( e ) {

			e.preventDefault();

			this.$calendarListRadios.removeAttr( 'checked' );
		},
	};

	SugarCalendar.Admin.Event.init();

	window.SugarCalendar = SugarCalendar;

} )( jQuery );
