/**
 * Dashboard stats requests.
 *
 * Fetches the range-dependent pieces and announces them with `sugarCalendarDashboardDataReceived`;
 * each widget module patches its own markup. No init(): nothing binds on load.
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
	const { el } = app;

	const INLINE_ERROR_CLASS = 'sugar-calendar-dashboard-inline-error';
	const SPINNER_SELECTOR = '.sugar-calendar-dashboard-date-range__spinner';

	/**
	 * The request in flight, kept so a newer range can abort it.
	 *
	 * @type {Object|null}
	 */
	let currentRequest = null;

	/**
	 * Token of the newest request; a response with an older token is ignored.
	 *
	 * @type {number}
	 */
	let currentGeneration = 0;

	/**
	 * The last requested range, for Retry.
	 *
	 * @type {string}
	 */
	let lastDate = '';

	/**
	 * The range the numbers on screen belong to.
	 *
	 * @type {string}
	 */
	let loadedDate = window.sugar_calendar_admin_dashboard?.range ?? '';

	/**
	 * Whether the last stats request failed with Retry or Reload on offer. A rejected range does
	 * not count: the control goes back to the loaded range and there is nothing to retry.
	 *
	 * @type {boolean}
	 */
	let failed = false;

	const updater = {

		/**
		 * The range the date control names: the one loading, the one that failed (Retry loads it),
		 * or else the one whose numbers are on screen. The control asks this instead of keeping its
		 * own copy.
		 *
		 * @since 3.18.0
		 *
		 * @return {string} `"Y-m-d - Y-m-d"`.
		 */
		getCurrentRange() {
			return currentRequest || failed ? lastDate : loadedDate;
		},

		/**
		 * Whether picking a range needs no request: it is loading or on screen. After a failure
		 * every pick asks again, the failed range included.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} range `"Y-m-d - Y-m-d"`.
		 *
		 * @return {boolean} Whether the range is current.
		 */
		isCurrentRange( range ) {
			return ! failed && range === updater.getCurrentRange();
		},

		/**
		 * Request the pieces for a range and broadcast the response.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} date `"Y-m-d - Y-m-d"`.
		 */
		fetchStats( date ) {
			lastDate = date;
			failed = false;

			updater.setLoadingState( true );

			// Claim the newest token before aborting, so the aborted request's handlers
			// already see a stale token and skip their work.
			const generation = ++currentGeneration;

			currentRequest?.abort();

			currentRequest = app.post( 'dashboard_get_stats', {
				date,
			} )
				.done( ( response ) => {
					if ( generation === currentGeneration ) {
						updater.applyStats( response, date );
					}
				} )
				.fail( ( jqXHR, textStatus ) => {
					// An abort is this module superseding itself, not a failure.
					if ( generation === currentGeneration && textStatus !== 'abort' ) {
						window.console?.error?.( 'Sugar Calendar Dashboard: unable to load stats.', textStatus );

						failed = true;
						updater.showInlineError( jqXHR.status === 403 ? 'reload' : 'retry' );
					}
				} )
				.always( () => {
					// Leave the spinner up while a newer request still runs.
					if ( generation !== currentGeneration ) {
						return;
					}

					currentRequest = null;

					updater.setLoadingState( false );
				} );
		},

		/**
		 * Broadcast a successful response, or show the error it carries.
		 *
		 * @since 3.18.0
		 *
		 * @param {Object} response JSON response.
		 * @param {string} date     Requested range.
		 */
		applyStats( response, date ) {
			if ( ! response?.success ) {
				updater.showErrorResponse( response?.data ?? {} );

				return;
			}

			loadedDate = date;

			updater.clearInlineError();

			el.$document.trigger( 'sugarCalendarDashboardDataReceived', [ response.data, date ] );

			updater.pushDateUrlState( date );
		},

		/**
		 * Show the error a response carries. A rejected range gets the server's message and
		 * no Retry, since resending it would fail again, and the control goes back to the
		 * range still on screen.
		 *
		 * @since 3.18.0
		 *
		 * @param {Object} data Error data: `code` and `message`.
		 */
		showErrorResponse( data ) {
			if ( data.code !== 'range' ) {
				failed = true;
				updater.showInlineError( 'retry' );

				return;
			}

			updater.insertInlineError( $( '<p></p>' ).text( data.message ?? '' ) );

			app.dateRange?.restore( loadedDate );
		},

		/**
		 * Show a failure notice above the stat cards with Retry or Reload.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} mode `retry` or `reload`.
		 */
		showInlineError( mode ) {
			const $notice = $( '<p></p>' );

			if ( mode === 'reload' ) {
				updater.showSessionExpired( $notice );
			} else {
				const i18n = window.sugar_calendar_admin_dashboard.i18n ?? {};
				const $retry = $( '<a href="#"></a>' )
					.text( i18n.retry )
					.on( 'click', ( event ) => {
						event.preventDefault();
						updater.fetchStats( lastDate );
					} );

				$notice.text( i18n.network_failure + ' ' ).append( $retry );
			}

			updater.insertInlineError( $notice );
		},

		/**
		 * Say the session expired and offer Reload, in the given element.
		 *
		 * The one builder of this message; the install buttons use it too.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery} $target Element to fill.
		 *
		 * @return {jQuery} The Reload link, for focus.
		 */
		showSessionExpired( $target ) {
			const i18n = window.sugar_calendar_admin_dashboard.i18n ?? {};
			const $link = $( '<a href="#"></a>' )
				.text( i18n.reload )
				.on( 'click', ( event ) => {
					event.preventDefault();
					window.location.reload();
				} );

			$target.text( i18n.session_expired + ' ' ).append( $link );

			return $link;
		},

		/**
		 * Replace any inline error with a new one above the stat cards.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery} $content Notice paragraph.
		 */
		insertInlineError( $content ) {
			updater.clearInlineError();

			const $notice = $( `<div class="notice notice-error ${ INLINE_ERROR_CLASS }" role="alert"></div>` ).append( $content );

			el.$attention.before( $notice );

			$notice.get( 0 )?.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		},

		/**
		 * Remove the inline error.
		 *
		 * @since 3.18.0
		 */
		clearInlineError() {
			el.$page.find( `.${ INLINE_ERROR_CLASS }` ).remove();
		},

		/**
		 * Busy cursor on the page and WordPress's spinner in the title bar.
		 *
		 * @since 3.18.0
		 *
		 * @param {boolean} on Whether a request is running.
		 */
		setLoadingState( on ) {
			el.$page.toggleClass( app.classNames.loading, on );

			// The spinner sits in the title bar, outside the page element.
			$( SPINNER_SELECTOR ).toggleClass( 'is-active', on );
		},

		/**
		 * Record the range in the URL so a reload keeps it.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} date Range.
		 */
		pushDateUrlState( date ) {
			const url = new URL( window.location.href );

			if ( url.searchParams.get( 'date' ) === date ) {
				return;
			}

			url.searchParams.set( 'date', date );
			window.history.pushState( {}, '', url.toString() );
		},
	};

	return updater;
}
