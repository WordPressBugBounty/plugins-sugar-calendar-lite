'use strict';

const SCAdminScreenOptions = window.SCAdminScreenOptions || ( function( document, window, $ ) {

	const app = {

		/**
		 * Start the engine.
		 *
		 * @since 3.8.0
		 */
		init() {
			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.8.0
		 */
		ready() {

			// If there are screen options we have to move them.
			$( '#screen-meta-links, #screen-meta' ).prependTo( '#sugar-calendar-admin-header-temp' ).show();
		},
	};

	return app;
}( document, window, jQuery ) );

SCAdminScreenOptions.init();
