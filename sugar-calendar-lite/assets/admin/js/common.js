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

			$( '.sugar-calendar-widget-ajax-action' ).on(
				'click',
				function( e ) {
					e.preventDefault();

					const el = $( e.currentTarget );
					app.saveWidgetMeta(
						el.data( 'meta-action' ),
						el.data( 'meta-nonce' ),
						el.data( 'meta-name' ),
						el.data( 'meta-value' )
					);


					if ( el.data( 'callback' ) === 'closeWidgetBlock' ) {
						el.closest( '.sugar-calendar-dash-widget-block' )
							.hide( 'fast' );
					}
				}
			);
		},

		/**
		 * Save dashboard widget meta on a backend.
		 *
		 * @since 3.7.0
		 *
		 * @param {string} metaAction Meta action to save.
		 * @param {string} metaNonce Nonce to save.
		 * @param {string} metaName  Meta name to save.
		 * @param {number} metaValue Value to save.
		 */
		saveWidgetMeta( metaAction, metaNonce, metaName, metaValue ) {

			$.post(
				sugar_calendar_admin_common.ajaxurl,
				{
					nonce   : metaNonce,
					action  : metaAction,
					meta    : {
						name: metaName,
						value: metaValue,
					},
				}
			);
		},
	};

	return app;
}( document, window, jQuery ) );

SCAdminCommon.init();
