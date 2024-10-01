/* globals jQuery, Choices, sugar_calendar_admin_settings */
( function ( $, Choices, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	/**
	 * DateTimeFormat component.
	 */
	$.fn.dateTimeFormat = function ( settings, document ) {

		function init() {

			this.settings = settings;
			this.$options = this.find( '[type="radio"]:not([data-custom-option])' );
			this.$customOption = this.find( '[data-custom-option]' );
			this.$customField = this.find( '[data-custom-field]' );
			this.$formatExample = this.find( '[data-format-example]' );
			this.$spinner = this.find( '[data-spinner]' );
			this.debounce = null;

			this.$options.on( 'click', onOptionClick.bind( this ) );
			this.$customField.on( 'click input', onFieldFocus.bind( this ) );
			this.$customField.on( 'input', onFieldInput.bind( this ) );
		}

		function onFieldFocus() {

			this.$customOption.prop( 'checked', true );
		}

		function onOptionClick( e ) {

			let $option = $( e.target );
			let format = $option.parent().find( '[data-format-i18n]' ).text();

			this.$customField.val( $option.val() );
			this.$formatExample.text( format );
		}

		function onFieldInput() {

			clearTimeout( this.debounce );

			if ( this.$customField.val() === '' ) {
				return;
			}

			this.$spinner.addClass( 'is-active' );

			this.debounce = setTimeout( () => {
				$.post( this.settings.ajax_url, {
					task: 'date_time_format',
					date_time_format: this.$customField.val(),
				} ).done( ( response ) => {
					if ( ! response.success || ! response.data ) {
						return;
					}

					this.$formatExample.text( response.data );
				} ).always( () => this.$spinner.removeClass( 'is-active' ) );
			}, 400 );
		}

		this.each( init.bind( this ) );
	}

	SugarCalendar.Admin.Settings = {

		init: function ( settings ) {

			// If there are screen options we have to move them.
			$( '#screen-meta-links, #screen-meta' ).prependTo( '#sugar-calendar-header-temp' ).show();

			// Initialize DateTimeFormat instances.
			this.initDateTimeFormats();

			// Initialize ChoiceJS dropdowns.
			this.initChoicesJS();
		},

		initChoicesJS: function () {

			$( '.choicesjs-select' ).each( ( i, el ) => {
				new Choices( el, {
					itemSelectText: '',
				} );
			} );
		},

		initDateTimeFormats: function () {

			$( '#sugar-calendar-setting-row-date_format' ).dateTimeFormat( settings );
			$( '#sugar-calendar-setting-row-time_format' ).dateTimeFormat( settings );
		},
	};

	SugarCalendar.Admin.Settings.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, Choices, sugar_calendar_admin_settings, document );
