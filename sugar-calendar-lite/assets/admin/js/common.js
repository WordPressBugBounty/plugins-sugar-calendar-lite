'use strict';

const SCAdminCommon = window.SCAdminCommon || ( function( document, window, $ ) {

	const app = {

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

			app.bindEvents();
		},

		/**
		 * Bind events.
		 *
		 * @since 3.3.0
		 */
		bindEvents() {

			const $migrateNoticeDismiss = $( '#sc-admin-tools-migrate-notice-dismiss' );

			if ( $migrateNoticeDismiss.length >= 1 ) {
				$migrateNoticeDismiss.on( 'click', function() {

					const $this = $( this );

					$this.parent( '.sugar-calendar-notice' ).hide();

					const slug = $this.data( 'migration-slug' );
					const nonce = $this.data( 'nonce' );

					if ( ! slug || ! nonce ) {
						return;
					}

					$.post(
						sugar_calendar_admin_common.ajaxurl,
						{
							action: 'sc_admin_dismiss_migration_notice',
							slug: slug,
							nonce: nonce
						},
						function ( response ) {}
					)
				} );
			}
		},
	};

	return app;
}( document, window, jQuery ) );

SCAdminCommon.init();
