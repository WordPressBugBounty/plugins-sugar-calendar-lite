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

			// Initialize onChange events for sandbox toggle control.
			this.initSandboxToggleListener();
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

		/**
		 * Initialize event listeners for sandbox toggle control.
		 *
		 * @since 3.3.0
		 *
		 * @return {void}
		 */
		initSandboxToggleListener: function () {

			$( '#sugar-calendar-setting-sandbox' ).on( 'change', ( e ) => {

				// Toggle the sandbox connect URL.
				this.toggleSandboxConnectURL( $( e.target ).is( ':checked' ) );
			} );
		},

		/**
		 * Toggle the sandbox connect URL.
		 * Changes the URL parameter to live or sandbox mode.
		 *
		 * @since 3.3.0
		 *
		 * @param {bool} isSandbox
		 *
		 * @return {void}
		 */
		toggleSandboxConnectURL: function ( isSandbox ) {

			// Set parent for reference.
			var self = this;

			// Disable sandbox control.
			$( '#sugar-calendar-setting-sandbox' ).off( 'change' );

			// Get URL parameter.
			let $sandboxConnectURL = $( '#sugar-calendar-setting-row-stripe-connect .sugar-calendar-stripe-connect' ).first(),
				stripeConnectURL = $sandboxConnectURL.attr( 'href' ),
				url = new URL( stripeConnectURL ),
				params = new URLSearchParams( url.search );

			// Set live or sandbox mode.
			params.set( 'live_mode', isSandbox ? 0 : 1 );

			// Update element href.
			$sandboxConnectURL.attr(
				'href',
				url.origin + url.pathname + '?' + params.toString()
			);

			// Update sandbox settings.
			this.ajaxSaveOptions( {
				sandbox: isSandbox ? 1 : 0,
			}, {

				// On success, re-enable sandbox control.
				success: function () {
					self.initSandboxToggleListener();
				},

				// Re-enable sandbox control in all cases.
				always: function () {
					self.initSandboxToggleListener();
				},
			} );
		},

		/**
		 * Ajax save options.
		 *
		 * This function requires the sugar_calendar_admin_settings variable to be localized.
		 * This variable should contain page_id, and _wpnonce.
		 *
		 * @since 3.3.0
		 *
		 * @param {object} data
		 * @param {object} callbacks
		 *
		 * @return {void}
		 */
		ajaxSaveOptions: function ( data, callbacks ) {

			const // Clean up ajax URL.
				ajaxUrlObj = new URL( sugar_calendar_admin_settings.ajax_url ),
				ajaxUrlParams = new URLSearchParams( ajaxUrlObj.searchParams ),
				ajaxUrl = ajaxUrlObj.origin + ajaxUrlObj.pathname,
				nonce = ajaxUrlParams.get( '_wpnonce' ),
				pageId = ajaxUrlParams.get( 'page_id' );

			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'sugar_calendar_admin_area_handle_post',
					options: data,
					nonce: nonce,
					pageId: pageId,
				},
				success: function ( response ) {
					if ( 'function' === typeof callbacks.success ) {
						callbacks.success( response );
					}
				},
				error: function ( response ) {
					if ( 'function' === typeof callbacks.error ) {
						callbacks.error( response );
					}
				},
				always: function ( response ) {
					if ( 'function' === typeof callbacks.always ) {
						callbacks.always( response );
					}
				},
			} );
		},
	};

	SugarCalendar.Admin.Settings.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, Choices, sugar_calendar_admin_settings, document );
