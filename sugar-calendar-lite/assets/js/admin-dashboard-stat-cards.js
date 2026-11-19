/**
 * Dashboard stat cards: swap in the re-rendered row after a range change.
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
export default function( document, window, $, app ) { // eslint-disable-line no-unused-vars
	const statCards = {

		/**
		 * Listen for new data.
		 *
		 * @since 3.18.0
		 */
		init() {
			app.el.$document.on( 'sugarCalendarDashboardDataReceived', statCards.onDataReceived );
		},

		/**
		 * Replace the row.
		 *
		 * @since 3.18.0
		 *
		 * @param {Event}  event Custom event.
		 * @param {Object} data  get_stats payload.
		 */
		onDataReceived( event, data ) {
			if ( ! data?.stat_cards_html ) {
				return;
			}

			app.el.$page.find( app.selectors.statCards ).first().replaceWith( data.stat_cards_html );
		},
	};

	return statCards;
}
