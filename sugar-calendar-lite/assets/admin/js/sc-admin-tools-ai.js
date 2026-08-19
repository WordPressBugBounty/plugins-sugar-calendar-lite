/* global sugar_calendar_admin_tools_ai */

/**
 * AI Tools tab.
 *
 * @since 3.13.0
 */

'use strict';

var SugarCalendarAdminToolsAi = window.SugarCalendarAdminToolsAi || ( function( document, window, $ ) {

	/**
	 * Public functions and properties.
	 *
	 * @since 3.13.0
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 3.13.0
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.13.0
		 */
		ready: function() {

			app.events();
		},

		/**
		 * Register JS events.
		 *
		 * @since 3.13.0
		 */
		events: function() {

			$( document )
				.on( 'click', '.sugar-calendar-tools-ai-cta-row [data-action="install"]', app.onInstallClick )
				.on( 'click', '.sugar-calendar-tools-ai-cta-row [data-action="activate"]', app.onActivateClick );
		},

		/**
		 * Install button click.
		 *
		 * @since 3.13.0
		 *
		 * @param {object} event The click event.
		 */
		onInstallClick: function( event ) {

			event.preventDefault();

			app.runTask( $( event.currentTarget ), 'sce_install_vibe_ai', sugar_calendar_admin_tools_ai.installing );
		},

		/**
		 * Activate button click.
		 *
		 * @since 3.13.0
		 *
		 * @param {object} event The click event.
		 */
		onActivateClick: function( event ) {

			event.preventDefault();

			app.runTask( $( event.currentTarget ), 'sce_activate_vibe_ai', sugar_calendar_admin_tools_ai.activating );
		},

		/**
		 * Run an install/activate AJAX task, then reload on success so PHP
		 * re-renders the next state's CTA, or show an inline error on failure.
		 *
		 * @since 3.13.0
		 *
		 * @param {jQuery} $button     Button element that was clicked.
		 * @param {string} task        AJAX task name.
		 * @param {string} loadingText Button text while the request is in flight.
		 */
		runTask: function( $button, task, loadingText ) {

			var originalText = $button.text();

			$button.prop( 'disabled', true ).text( loadingText );

			$.post( sugar_calendar_admin_tools_ai.ajax_url, {
				action: 'sugar-calendar',
				task: task,
				_ajax_nonce: sugar_calendar_admin_tools_ai.nonce,
				page_id: sugar_calendar_admin_tools_ai.page_id,
			} )
				.done( function( response ) {

					if ( response && response.success ) {
						window.location.reload();
						return;
					}

					app.showError( $button, originalText, response && typeof response.data === 'string' ? response.data : '' );
				} )
				.fail( function() {

					app.showError( $button, originalText );
				} );
		},

		/**
		 * Restore the button and show an inline error.
		 *
		 * @since 3.13.0
		 *
		 * @param {jQuery} $button      Button element.
		 * @param {string} originalText Text to restore on the button.
		 * @param {string} [message]    Server-provided error message; falls back
		 *                              to the generic localized error text.
		 */
		showError: function( $button, originalText, message ) {

			$button.prop( 'disabled', false ).text( originalText );

			var $row = $button.closest( '.sugar-calendar-tools-ai-cta-row' );
			var $error = $row.siblings( '.sugar-calendar-tools-ai-install-error' );

			if ( ! $error.length ) {
				$error = $( '<p class="sugar-calendar-tools-ai-install-error" role="alert"></p>' );
				$row.after( $error );
			}

			$error.text( message || sugar_calendar_admin_tools_ai.error_text );
		},
	};

	return app;

}( document, window, jQuery ) );

SugarCalendarAdminToolsAi.init();
