/**
 * Integrations widget: refresh an out-of-date credits read after load.
 *
 * The server fetches (`dashboard_refresh_credits`) and sends back the usage indicator, and the
 * integration cards too when the out-of-credits answer changed, so their badges agree with it.
 *
 * @since 3.18.0
 *
 * @param {Object} document Document object.
 * @param {Object} window   Window object.
 * @param {jQuery} $        jQuery object.
 * @param {Object} app      Dashboard page app.
 *
 * @return {Object} Module API.
 */
export default function( document, window, $, app ) {
	const selectors = {
		box: '[data-credits-refresh]',
		toggle: '[data-credits-toggle]',
		status: '[data-status]',
	};

	const credits = {

		/**
		 * Ask for a fresh read when the indicator's container says one is due.
		 *
		 * @since 3.18.0
		 */
		init() {
			const $box = app.el.$page.find( selectors.box );

			if ( ! $box.length ) {
				return;
			}

			app.post( 'dashboard_refresh_credits', {} )
				.done( ( response ) => {
					if ( ! response?.success ) {
						window.console?.warn?.( 'Sugar Calendar Dashboard: could not refresh the integration usage.', response?.data );

						return;
					}

					if ( response.data.refreshed ) {
						credits.apply( $box, response.data );
					}
				} )
				.fail( ( jqXHR, textStatus ) => {
					window.console?.warn?.( 'Sugar Calendar Dashboard: could not refresh the integration usage.', textStatus );
				} );
		},

		/**
		 * Swap in the new indicator, and the integration cards the server redrew.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery} $box Indicator's container.
		 * @param {Object} data Response data: `credits_html`, and `cards_html` by card slug.
		 */
		apply( $box, data ) {
			const hadFocus = $box[ 0 ].contains( $box[ 0 ].ownerDocument.activeElement );

			$box.html( data.credits_html );

			// The swap removes the focused element; hand focus to the new toggle.
			if ( hadFocus ) {
				$box.find( selectors.toggle ).trigger( 'focus' );
			}

			Object.entries( data.cards_html ).forEach( ( [ slug, html ] ) => {
				const $card = app.el.$page.find( `.sc-dashboard-integration-card--${ slug }` );
				const cardHadFocus = $card.length > 0 && $card[ 0 ].contains( $card[ 0 ].ownerDocument.activeElement );

				app.installer.redraw( $card, html );

				if ( cardHadFocus ) {
					app.installer.focus( $card, selectors.status );
				}
			} );
		},
	};

	return credits;
}
