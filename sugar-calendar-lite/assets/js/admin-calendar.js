/* globals jQuery, Choices, sugar_calendar_admin_calendar */
( function ( $, Choices, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.Calendar = {

		/**
		 * Initialize.
		 *
		 * @since 3.0.0
		 */
		init: function ( settings ) {

			this.settings = settings;
			this.$settingsMetabox = $( '#calendar_settings' );

			this.bindEvents();

			// Initialize ChoiceJS dropdowns.
			this.initChoicesJS();

			// Initialize color pickers.
			this.initColorPickers();
		},

		bindEvents: function () {

			$( 'button.handlediv', this.$settingsMetabox ).on( 'click', this.toggleMetabox.bind( this ) );
		},

		initChoicesJS: function () {

			$( '.choicesjs-select' ).each( ( i, el ) => {
				new Choices( el, {
					itemSelectText: '',
				} );
			} );
		},

		initColorPickers: function () {
			$( '#term-color' ).wpColorPicker( {
				palettes: this.settings.palette,
			} );
		},

		toggleMetabox: function () {
			this.$settingsMetabox.toggleClass( 'closed' );
		},
	};

	SugarCalendar.Admin.Calendar.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, Choices, sugar_calendar_admin_calendar );
