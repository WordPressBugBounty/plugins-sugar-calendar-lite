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
		 */
		init: function ( settings ) {

			this.settings = settings;
			this.$el = $( '.sugar-calendar-event-details-metabox' );
			this.$sectionButtons = $( '.sugar-calendar-metabox__navigation__button', this.$el );
			this.$sections = $( '.sugar-calendar-metabox__section', this.$el );
			this.$startDate = $( '#start_date', this.$el );
			this.$endDate = $( '#end_date', this.$el );
			this.$allDay = $( '#all_day', this.$el );
			this.$timezones = $( '.sugar-calendar-metabox__field-row--time-zone, .event-time-zone, .event-time', this.$el );

			// Bind events.
			this.bindEvents();

			// Initialize ChoiceJS dropdowns.
			this.initChoicesJS();

			// Initialize date pickers.
			this.initDatepickers();
		},

		bindEvents: function () {

			this.$sectionButtons.on( 'click', this.onSectionButtonClick.bind( this ) );
			this.$allDay.on( 'change', this.toggleTimezones.bind( this ) );
		},

		onSectionButtonClick: function ( e ) {

			const $button = $( e.currentTarget );
			const id = $button.attr( 'data-id' );
			const $section = this.$sections.filter( `[data-id=${id}]` );

			this.$sectionButtons.removeClass( 'selected' );
			this.$sections.removeClass( 'selected' );

			$button.addClass( 'selected' );
			$section.addClass( 'selected' );
		},

		initChoicesJS: function () {

			$( '.choicesjs-select', this.$el ).each( ( i, el ) => {
				new Choices( el, {
					itemSelectText: '',
				} );
			} );
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

			this.$startDate.on( 'change', () => {
				this.$endDate.datepicker( 'option', 'minDate', this.getDate( this.$startDate.val() ) );
			} );

			this.$endDate.on( 'change', () => {
				this.$startDate.datepicker( 'option', 'maxDate', this.getDate( this.$endDate.val() ) );
			} );

			this.$startDate.datepicker( 'option', 'maxDate', this.getDate( this.$endDate.val() ) );
			this.$endDate.datepicker( 'option', 'minDate', this.getDate( this.$startDate.val() ) );
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
	};

	SugarCalendar.Admin.EventMetabox.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, sugar_calendar_admin_event_meta_box );
