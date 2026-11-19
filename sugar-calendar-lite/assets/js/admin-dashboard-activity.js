/* global SugarCalendarChart */

/**
 * Activity widget: the Attendees and Orders tabs, the Orders metric switch, and the graphs.
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
export default function( document, window, $, app ) { // eslint-disable-line max-lines-per-function
	const selectors = {
		root: '[data-widget="activity"]',
		body: '.sugar-calendar-dashboard-activity',
		tab: '.sugar-calendar-dashboard-activity__tab',
		panel: '.sugar-calendar-dashboard-activity__panel',
		canvas: '.sugar-calendar-dashboard-activity__chart',
		metrics: '.sugar-calendar-dashboard-orders-metrics',
		// The live metric switch, never the example one inside the Stripe teaser.
		liveMetrics: '[data-panel="orders"] > .sugar-calendar-dashboard-orders-metrics',
		metric: '.sugar-calendar-dashboard-orders-metric',
	};

	const local = {
		$root: $(),
		series: [],
		tab: '',
		metric: '',
		charts: new Map(),
		saving: false,
		savePending: false,
	};

	const activity = {

		/**
		 * Most x-axis labels shown at once.
		 *
		 * @since 3.18.0
		 *
		 * @type {number}
		 */
		maxTicks: 7,

		/**
		 * Longest series drawn with a dot on every day; longer ones draw the line only.
		 *
		 * @since 3.18.0
		 *
		 * @type {number}
		 */
		maxPoints: 60,

		/**
		 * Bind the widget and draw the visible graph.
		 *
		 * @since 3.18.0
		 */
		init() {
			local.$root = $( selectors.root );

			if ( ! local.$root.length ) {
				return;
			}

			local.series = activity.readJson( local.$root.find( selectors.body ).attr( 'data-series' ) );

			// The page renders the user's remembered tab and metric; start from them.
			local.tab = local.$root.find( `${ selectors.tab }[aria-selected="true"]` ).data( 'tab' ) || '';
			local.metric = local.$root.find( `${ selectors.canvas }[data-metric-switch]` ).attr( 'data-metric' ) || '';

			local.$root.on( 'click', selectors.tab, activity.onTabClick );
			local.$root.on( 'keydown', selectors.tab, activity.onTabKeydown );
			local.$root.on( 'click', selectors.metric, activity.onMetricClick );

			app.el.$document.on( 'sugarCalendarDashboardDataReceived', activity.onDataReceived );

			activity.drawVisible();
		},

		/**
		 * Parse a JSON array attribute.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} value Attribute value.
		 *
		 * @return {Array} Parsed rows, or an empty array.
		 */
		readJson( value ) {
			try {
				const parsed = JSON.parse( value || '[]' );

				return Array.isArray( parsed ) ? parsed : [];
			} catch ( error ) {
				window.console?.error?.( 'Sugar Calendar Dashboard: invalid graph data.', error );

				return [];
			}
		},

		/**
		 * Switch tabs on click.
		 *
		 * @since 3.18.0
		 *
		 * @param {Event} event Click event.
		 */
		onTabClick( event ) {
			activity.selectTab( $( event.currentTarget ).data( 'tab' ) );
		},

		/**
		 * Arrow keys move between tabs (ARIA tabs pattern).
		 *
		 * @since 3.18.0
		 *
		 * @param {KeyboardEvent} event Keydown event.
		 */
		onTabKeydown( event ) {
			if ( event.key !== 'ArrowRight' && event.key !== 'ArrowLeft' ) {
				return;
			}

			event.preventDefault();

			const $tabs = local.$root.find( selectors.tab );
			const step = event.key === 'ArrowRight' ? 1 : -1;
			const $next = $tabs.eq( ( $tabs.index( event.currentTarget ) + step + $tabs.length ) % $tabs.length );

			activity.selectTab( $next.data( 'tab' ) );
			$next.trigger( 'focus' );
		},

		/**
		 * Show one tab's panel, draw its graph and remember the choice.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} tabId Tab id.
		 */
		selectTab( tabId ) {
			if ( tabId !== local.tab ) {
				local.tab = tabId;
				activity.saveSettings();
			}

			local.$root.find( selectors.tab ).each( ( index, tab ) => {
				const isSelected = $( tab ).data( 'tab' ) === tabId;

				$( tab )
					.toggleClass( app.classNames.selected, isSelected )
					.attr( { 'aria-selected': String( isSelected ), tabindex: isSelected ? '0' : '-1' } );
			} );

			local.$root.find( selectors.panel ).each( ( index, panel ) => {
				panel.hidden = panel.dataset.panel !== tabId;
			} );

			activity.drawVisible();
		},

		/**
		 * Switch the Orders graph between Total Payments and Total Sales.
		 *
		 * @since 3.18.0
		 *
		 * @param {Event} event Click event.
		 */
		onMetricClick( event ) {
			const metric = $( event.currentTarget ).data( 'metric' );

			if ( metric !== local.metric ) {
				local.metric = metric;
				activity.saveSettings();
			}

			activity.applyMetric();
			activity.drawVisible();
		},

		/**
		 * Remember the tab and metric for the user. One save runs at a time and each sends both,
		 * so quick changes land in order and none is lost; a change made meanwhile is sent after it.
		 *
		 * @since 3.18.0
		 */
		saveSettings() {
			if ( local.saving ) {
				local.savePending = true;

				return;
			}

			local.saving = true;

			// A panel without a metric switch has no metric to send.
			const settings = local.metric ? { tab: local.tab, metric: local.metric } : { tab: local.tab };

			app.post( 'dashboard_save_widget_settings', { widget: 'activity', settings } )
				.done( ( response ) => {
					if ( response?.success === true ) {
						return;
					}

					window.console?.error?.( 'Sugar Calendar Dashboard: unable to save the setting.', response?.data );
				} )
				.fail( ( jqXHR, textStatus ) => {
					window.console?.error?.( 'Sugar Calendar Dashboard: unable to save the setting.', textStatus );

					if ( jqXHR.status === 403 ) {
						app.updater?.showInlineError( 'reload' );
					}
				} )
				.always( () => {
					local.saving = false;

					if ( local.savePending ) {
						local.savePending = false;
						activity.saveSettings();
					}
				} );
		},

		/**
		 * Reflect the chosen metric on the buttons and the switchable canvas.
		 *
		 * @since 3.18.0
		 */
		applyMetric() {
			local.$root.find( `${ selectors.liveMetrics } ${ selectors.metric }` ).each( ( index, button ) => {
				const isSelected = $( button ).data( 'metric' ) === local.metric;

				// The recipe styles the selected card by `data-active`; null removes it.
				$( button )
					.toggleClass( app.classNames.selected, isSelected )
					.attr( { 'aria-pressed': String( isSelected ), 'data-active': isSelected ? '' : null } );
			} );

			local.$root.find( `${ selectors.canvas }[data-metric-switch]` ).attr( 'data-metric', local.metric );
		},

		/**
		 * Redraw after a range change: new series, and the body rendered again for the range
		 * (graphs, metrics, lists and popups).
		 *
		 * @since 3.18.0
		 *
		 * @param {Event}  event Custom event.
		 * @param {Object} data  get_stats payload.
		 */
		onDataReceived( event, data ) {
			if ( Array.isArray( data?.series ) ) {
				local.series = data.series;
			}

			if ( data?.activity_html ) {
				activity.replaceBody( data.activity_html );

				return;
			}

			activity.drawVisible();
		},

		/**
		 * Swap in a body rendered again, keeping the tab and metric on screen and the keyboard
		 * focus a replaced button held.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} html Body HTML.
		 */
		replaceBody( html ) {
			const body = local.$root.find( selectors.body ).get( 0 );
			const hadFocus = !! body && body.contains( body.ownerDocument.activeElement );
			const focusSelector = activity.getFocusedButtonSelector();

			// The canvases go with the body, so their charts would draw to nothing.
			local.charts.forEach( ( chart ) => chart.destroy() );
			local.charts.clear();

			$( body ).replaceWith( html );

			// The server renders the saved tab and metric, which a save still in flight may trail.
			local.metric = local.metric || local.$root.find( `${ selectors.canvas }[data-metric-switch]` ).attr( 'data-metric' ) || '';

			if ( local.metric ) {
				activity.applyMetric();
			}

			activity.selectTab( local.tab );

			const $focus = focusSelector ? local.$root.find( focusSelector ) : $();

			if ( $focus.length ) {
				$focus.trigger( 'focus' );
			} else if ( hadFocus ) {
				// Whatever held focus went with the old body; the tab list is the nearest stop.
				local.$root.find( `${ selectors.tab }[aria-selected="true"]` ).trigger( 'focus' );
			}
		},

		/**
		 * Selector of the tab or live metric button holding keyboard focus, or an empty string.
		 *
		 * @since 3.18.0
		 *
		 * @return {string} Selector.
		 */
		getFocusedButtonSelector() {
			const focused = local.$root.find( `${ selectors.tab }:focus, ${ selectors.liveMetrics } ${ selectors.metric }:focus` ).get( 0 );

			if ( ! focused ) {
				return '';
			}

			return focused.dataset.tab
				? `${ selectors.tab }[data-tab="${ focused.dataset.tab }"]`
				: `${ selectors.liveMetrics } ${ selectors.metric }[data-metric="${ focused.dataset.metric }"]`;
		},

		/**
		 * Draw every canvas in the visible panel. Hidden canvases have no size, so each
		 * panel draws when it is shown.
		 *
		 * @since 3.18.0
		 */
		drawVisible() {
			local.$root.find( selectors.panel ).not( '[hidden]' ).find( selectors.canvas ).each( ( index, canvas ) => {
				activity.draw( canvas );
			} );
		},

		/**
		 * Draw one canvas.
		 *
		 * @since 3.18.0
		 *
		 * @param {HTMLCanvasElement} canvas Canvas.
		 */
		draw( canvas ) {
			const meta = window.sugar_calendar_admin_dashboard.series_meta?.[ canvas.dataset.metric ];

			// Only the graphs need Chart.js; the tabs and the metric switch keep working if it fails to load.
			if ( ! meta || typeof SugarCalendarChart === 'undefined' ) {
				return;
			}

			const rows = canvas.dataset.teaser ? activity.readJson( canvas.dataset.teaser ) : local.series;
			const values = rows.map( ( row ) => Number( row[ canvas.dataset.metric ] ?? 0 ) );

			local.charts.get( canvas )?.destroy();
			local.charts.set( canvas, new SugarCalendarChart( canvas.getContext( '2d' ), activity.getConfig( rows, values, meta ) ) );
		},

		/**
		 * Chart.js configuration.
		 *
		 * @since 3.18.0
		 *
		 * @param {Array}  rows   Series rows (`label`, `tooltip`, …).
		 * @param {Array}  values Values to plot.
		 * @param {Object} meta   `label`, `token`, `color` (the token's fallback) and, for an amount, `money`.
		 *
		 * @return {Object} Chart configuration.
		 */
		getConfig( rows, values, meta ) {
			const gridColor = activity.token( '--sc-surface-divider', '#dcdcde' );
			const tickColor = activity.token( '--sc-text-secondary', '#50575e' );
			const tokenColor = activity.token( meta.token, meta.color );
			// Chart.js's colour parser rejects oklch() and color(); fall back to the PHP colour for those.
			const lineColor = SugarCalendarChart.helpers.color( tokenColor ).valid ? tokenColor : meta.color;
			const tickFont = { size: 12 };
			const isLabelled = activity.isLabelled( rows );

			// An all-zero graph would collapse to 0-1; show a 0-100 scale instead.
			const isEmpty = values.every( ( value ) => value === 0 );

			const config = {
				type: 'line',
				data: {
					labels: rows.map( ( row ) => row.label ),
					datasets: [ {
						label: meta.label,
						data: values,
						borderColor: lineColor,
						backgroundColor: SugarCalendarChart.helpers.color( lineColor ).alpha( 0.1 ).rgbString(),
						fill: true,
						tension: 0.4,
						borderWidth: 2,

						// Figma's empty graph is the grid alone, under the popup.
						showLine: ! isEmpty,
						pointRadius: isEmpty || rows.length > activity.maxPoints ? 0 : 3,
						pointBackgroundColor: lineColor,
						pointHoverRadius: isEmpty ? 0 : 5,
					} ],
				},
				options: {
					maintainAspectRatio: false,
					responsive: true,
					interaction: { mode: 'index', intersect: false },
					scales: {
						x: {
							// Gridlines only on labelled days, so a long range does not fill the plot.
							grid: { display: true, color: ( context ) => ( isLabelled( context.index ) ? gridColor : 'transparent' ) },

							// Thinned by the callback, not by Chart.js, so the labels do not
							// change with the viewport width.
							ticks: {
								maxRotation: 25,
								minRotation: 25,
								autoSkip: false,
								color: tickColor,
								font: tickFont,
								callback: ( value, index ) => ( isLabelled( index ) ? rows[ index ]?.label ?? '' : '' ),
							},
						},
						y: {
							beginAtZero: true,
							suggestedMax: isEmpty ? 100 : undefined,
							grid: { color: gridColor },
							// Figma's six labels (0 to 100 in 20s), not the eleven a 303px plot fits.
							ticks: { precision: 0, maxTicksLimit: 6, color: tickColor, font: tickFont },
							border: { display: false },
						},
					},
					plugins: {
						legend: { display: false },
						tooltip: {
							displayColors: false,
							callbacks: {
								title: ( items ) => rows[ items[ 0 ].dataIndex ]?.tooltip || items[ 0 ].label,
							},
						},
					},
				},
			};

			// Whole units on the axis and the store's decimals in the tooltip, as the metric card above.
			if ( meta.money ) {
				config.options.scales.y.ticks.callback = ( value ) => activity.formatMoney( value, meta.money, 0 );
				config.options.plugins.tooltip.callbacks.label = ( item ) => `${ meta.label }: ${ activity.formatMoney( item.raw, meta.money, meta.money.decimals ) }`;
			}

			if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
				config.options.animation = false;
			}

			return config;
		},

		/**
		 * Which days get an x-axis label: at most maxTicks, counted from the end so the
		 * latest day keeps its label.
		 *
		 * @since 3.18.0
		 *
		 * @param {Array} rows Series rows.
		 *
		 * @return {Function} Takes a row index, returns whether that day is labelled.
		 */
		isLabelled( rows ) {
			const gap = Math.ceil( rows.length / activity.maxTicks );

			return ( index ) => gap <= 1 || ( rows.length - index - 1 ) % gap === 0;
		},

		/**
		 * Format an amount as `Money::format()` does, e.g. `$1,234.50`.
		 *
		 * @since 3.18.0
		 *
		 * @param {number} value    Amount.
		 * @param {Object} money    `prefix`, `suffix`, `decimal` and `thousands` (`Money::get_format()`).
		 * @param {number} decimals Decimal places.
		 *
		 * @return {string} Formatted amount.
		 */
		formatMoney( value, money, decimals ) {
			const [ whole, fraction ] = Number( value ).toFixed( decimals ).split( '.' );
			const grouped = whole.replace( /\B(?=(\d{3})+(?!\d))/g, money.thousands );

			return money.prefix + grouped + ( fraction ? money.decimal + fraction : '' ) + money.suffix;
		},

		/**
		 * Read a design token from :root.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} name     Custom property.
		 * @param {string} fallback Value when unset.
		 *
		 * @return {string} Value.
		 */
		token( name, fallback ) {
			const value = window.getComputedStyle( document.documentElement ).getPropertyValue( name ).trim();

			return value || fallback;
		},
	};

	return activity;
}
