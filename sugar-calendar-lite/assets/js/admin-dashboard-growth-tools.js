/**
 * Growth Tools widget: install or activate a recommended plugin in place.
 *
 * The server does the work (`dashboard_growth_tool`) and sends the tile back redrawn; the
 * installer module runs the request.
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
		action: '.sc-dashboard-recommended-growth-tools button[data-action]',
		tool: '.sc-dashboard-recommended-growth-tools__tool',
		installed: '.sc-dashboard-recommended-growth-tools__installed',
	};

	const growthTools = {

		/**
		 * Listen for clicks.
		 *
		 * @since 3.18.0
		 */
		init() {
			app.el.$page.on( 'click', selectors.action, growthTools.onClick );
		},

		/**
		 * Send the tile's plugin to the endpoint.
		 *
		 * @since 3.18.0
		 *
		 * @param {Event} event Click event.
		 */
		onClick( event ) {
			const $button = $( event.currentTarget );

			app.installer.run( $button, {
				item: selectors.tool,
				task: 'dashboard_growth_tool',
				data: { key: $button.attr( 'data-key' ) },
				html: 'tile_html',
				fallbackFocus: selectors.installed,
			} );
		},
	};

	return growthTools;
}
