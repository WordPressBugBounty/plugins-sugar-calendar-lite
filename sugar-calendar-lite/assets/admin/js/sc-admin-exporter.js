'use strict';

const SCAdminExporter = window.SCAdminExporter || ( function( document, window, $ ) {

	const app = {

		/**
		 * Runtime variables.
		 *
		 * @since 3.3.0
		 *
		 * @type {object}
		 */
		runtime_vars: {

			/**
			 * DOM elements cache.
			 *
			 * @since 3.3.0
			 *
			 * @type {object}
			 */
			doms: {
				/**
				 * jQuery DOM of the Events checkbox.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$events_checkbox: null,

				/**
				 * jQuery DOM of the Custom Fields checkbox.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$custom_fields_checkbox: null,

				/**
				 * jQuery DOM of the Custom Fields list.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$custom_fields_list: null,
			}
		},

		/**
		 * Start the engine.
		 *
		 * @since 3.3.0
		 */
		init() {
			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.3.0
		 */
		ready() {

			app.cacheDom();
			app.bindEvents();
		},

		/**
		 * Cache DOM elements.
		 *
		 * @since 3.3.0
		 */
		cacheDom() {

			app.runtime_vars.doms.$events_checkbox = $( '#sc-admin-tools-export-checkbox-events' );
			app.runtime_vars.doms.$custom_fields_checkbox = $( '#sc-admin-tools-export-checkbox-custom_fields' );
			app.runtime_vars.doms.$custom_fields_list = $( '#sc-admin-tools-export-context-custom_fields' );
		},

		/**
		 * Bind events.
		 *
		 * @since 3.3.0
		 */
		bindEvents() {

			app.runtime_vars.doms.$events_checkbox.on( 'change', function() {
				if ( this.checked ) {
					app.runtime_vars.doms.$custom_fields_list.removeClass( 'sc-admin-tools-disabled' );
				} else {
					app.runtime_vars.doms.$custom_fields_checkbox.prop( 'checked', false );
					app.runtime_vars.doms.$custom_fields_list.addClass( 'sc-admin-tools-disabled' );
				}
			} );
		},
	};

	return app;
}( document, window, jQuery ) );

SCAdminExporter.init();
