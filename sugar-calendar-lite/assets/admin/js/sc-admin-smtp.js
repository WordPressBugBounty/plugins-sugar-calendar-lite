/* global sugar_calendar_admin_smtp */

/**
 * SMTP Sub-page.
 *
 * @since 3.11.0
 */

'use strict';

var SugarCalendarAdminSMTP = window.SugarCalendarAdminSMTP || ( function( document, window, $ ) {

	/**
	 * Elements.
	 *
	 * @since 3.11.0
	 *
	 * @type {object}
	 */
	var el = {};

	/**
	 * Public functions and properties.
	 *
	 * @since 3.11.0
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 3.11.0
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.11.0
		 */
		ready: function() {

			app.initVars();
			app.events();
		},

		/**
		 * Init variables.
		 *
		 * @since 3.11.0
		 */
		initVars: function() {

			el = {
				$stepInstall:    $( 'section.step-install' ),
				$stepInstallNum: $( 'section.step-install .num img' ),
				$stepSetup:      $( 'section.step-setup' ),
				$stepSetupNum:   $( 'section.step-setup .num img' ),
			};
		},

		/**
		 * Register JS events.
		 *
		 * @since 3.11.0
		 */
		events: function() {

			// Step 'Install' button click.
			el.$stepInstall.on( 'click', 'button', app.stepInstallClick );

			// Step 'Setup' button click.
			el.$stepSetup.on( 'click', 'button', app.gotoURL );
		},

		/**
		 * Step 'Install' button click.
		 *
		 * @since 3.11.0
		 */
		stepInstallClick: function() {

			const $btn = $( this );

			if ( $btn.hasClass( 'disabled' ) ) {
				return;
			}

			const action = $btn.attr( 'data-action' );
			let ajaxTask = '';

			switch ( action ) {
				case 'activate':
					ajaxTask = 'sce_activate_smtp';
					$btn.text( sugar_calendar_admin_smtp.activating );
					break;

				case 'install':
					ajaxTask = 'sce_install_smtp';
					$btn.text( sugar_calendar_admin_smtp.installing );
					break;

				case 'goto-url':
					window.location.href = $btn.attr( 'data-url' );
					return;

				default:
					return;
			}

			$btn.addClass( 'disabled' );
			app.showSpinner( el.$stepInstallNum );

			const plugin = $btn.attr( 'data-plugin' ),
				source = $btn.attr( 'data-source' );

			const data = {
				action: 'sugar-calendar',
				task: ajaxTask,
				'_ajax_nonce': sugar_calendar_admin_smtp.nonce,
				page_id: 'smtp',
				plugin,
				type: 'plugin',
				source,
			};

			$.post( sugar_calendar_admin_smtp.ajax_url, data )
				.done( function( res ) {
					app.stepInstallDone( res, $btn, action );
				} )
				.always( function() {
					app.hideSpinner( el.$stepInstallNum );
				} );
		},

		/**
		 * Done part of the 'Install' step.
		 *
		 * @since 3.11.0
		 *
		 * @param {object} res    Result of $.post() query.
		 * @param {jQuery} $btn   Button.
		 * @param {string} action Action (for more info look at the app.stepInstallClick() function).
		 */
		stepInstallDone: function( res, $btn, action ) {

			var success = 'install' === action ? res.success && res.data.is_activated : res.success;

			if ( success ) {
				el.$stepInstallNum.attr( 'src', el.$stepInstallNum.attr( 'src' ).replace( 'step-1.', 'step-complete.' ) );
				$btn.addClass( 'grey' ).removeClass( 'button-primary' ).text( sugar_calendar_admin_smtp.activated );
				app.stepInstallPluginStatus();

				return;
			}

			var activationFail = ( 'install' === action && res.success && ! res.data.is_activated ) || 'activate' === action,
				url            = ! activationFail ? sugar_calendar_admin_smtp.manual_install_url : sugar_calendar_admin_smtp.manual_activate_url,
				msg            = ! activationFail ? sugar_calendar_admin_smtp.error_could_not_install : sugar_calendar_admin_smtp.error_could_not_activate,
				btn            = ! activationFail ? sugar_calendar_admin_smtp.download_now : sugar_calendar_admin_smtp.plugins_page;

			$btn.removeClass( 'grey disabled' ).text( btn ).attr( 'data-action', 'goto-url' ).attr( 'data-url', url );
			$btn.after( '<p class="error">' + msg + '</p>' );
		},

		/**
		 * Callback for step 'Install' completion.
		 *
		 * @since 3.11.0
		 */
		stepInstallPluginStatus: function() {

			var data = {
				action: 'sugar-calendar',
				task: 'sce_check_plugin_status',
				'_ajax_nonce': sugar_calendar_admin_smtp.nonce,
				page_id: 'smtp',
			};

			$.post( sugar_calendar_admin_smtp.ajax_url, data )
				.done( app.stepInstallPluginStatusDone );
		},

		/**
		 * Done part of the callback for step 'Install' completion.
		 *
		 * @since 3.11.0
		 *
		 * @param {object} res Result of $.post() query.
		 */
		stepInstallPluginStatusDone: function( res ) {

			if ( ! res.success ) {
				return;
			}

			el.$stepSetup.removeClass( 'grey' );
			el.$stepSetupBtn = el.$stepSetup.find( 'button' );
			el.$stepSetupBtn.removeClass( 'grey disabled' ).addClass( 'button-primary' );

			if ( res.data.setup_status > 0 ) {
				el.$stepSetupNum.attr( 'src', el.$stepSetupNum.attr( 'src' ).replace( 'step-2.svg', 'step-complete.svg' ) );
				el.$stepSetupBtn.attr( 'data-url', sugar_calendar_admin_smtp.smtp_settings_url ).text( sugar_calendar_admin_smtp.smtp_settings );

				return;
			}

			el.$stepSetupBtn.attr( 'data-url', sugar_calendar_admin_smtp.smtp_wizard_url ).text( sugar_calendar_admin_smtp.smtp_wizard );
		},

		/**
		 * Go to URL by click on the button.
		 *
		 * @since 3.11.0
		 */
		gotoURL: function() {

			var $btn = $( this );

			if ( $btn.hasClass( 'disabled' ) ) {
				return;
			}

			window.location.href = $btn.attr( 'data-url' );
		},

		/**
		 * Display spinner.
		 *
		 * @since 3.11.0
		 *
		 * @param {jQuery} $el Section number image jQuery object.
		 */
		showSpinner: function( $el ) {

			$el.siblings( '.loader' ).removeClass( 'hidden' );
		},

		/**
		 * Hide spinner.
		 *
		 * @since 3.11.0
		 *
		 * @param {jQuery} $el Section number image jQuery object.
		 */
		hideSpinner: function( $el ) {

			$el.siblings( '.loader' ).addClass( 'hidden' );
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
SugarCalendarAdminSMTP.init();
