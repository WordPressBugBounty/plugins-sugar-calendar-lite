/**
 * Install buttons: install or activate a plugin from a Dashboard tile or card.
 *
 * Shared by Growth Tools and the Pro add-on cards. The server does the work and sends the item back
 * redrawn; the page lock runs one install at a time.
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
		label: '[data-label]',
		status: '[data-status]',
		focusable: 'a[href], button',

		// Every install button on the page.
		installs: 'button[data-action]',
	};

	// After a 403 no request can succeed until the page reloads.
	let expired = false;

	const installer = {

		/**
		 * Send an install button's task to the endpoint.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery} $button Clicked install button.
		 * @param {Object} install `item` (selector of the tile or card it redraws), `task`, `data` (the task's
		 *                         fields), `html` (the response field holding the redrawn item) and
		 *                         `fallbackFocus` (focused when the redrawn item has nothing to click).
		 */
		run( $button, install ) {
			// Two installs at once would race in the upgrader; after a 403 none can succeed.
			if ( app.vars.installing || expired || $button.attr( 'aria-disabled' ) === 'true' ) {
				return;
			}

			const i18n = window.sugar_calendar_admin_dashboard.i18n;
			const $item = $button.closest( install.item );
			const $label = $button.find( selectors.label );
			const label = $label.text();

			app.lockForInstall( $button[ 0 ] );

			installer.setStatus( $item, '' );
			installer.setBusy( $button, true );
			$label.text( $button.attr( 'data-action' ) === 'activate' ? i18n.activating : i18n.installing );

			app.post( install.task, install.data )
				.done( ( response ) => {
					$label.text( label );
					installer.applyResult( $item, $button, install, response?.data || {}, response?.success === true );
				} )
				.fail( ( jqXHR ) => {
					$label.text( label );

					if ( jqXHR.status === 403 ) {
						expired = true;
						installer.showReload( $item, $button );

						return;
					}

					installer.setStatus( $item, i18n.growth_tool_failed );
				} )
				.always( () => {
					app.unlockAfterInstall();
					installer.setBusy( $button, false );
				} );
		},

		/**
		 * Redraw the item and say what went wrong, if anything.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery}  $item     Tile or card.
		 * @param {jQuery}  $button   Its button.
		 * @param {Object}  install   The `run()` options.
		 * @param {Object}  result    Response data: the redrawn item, `message` and, on success, `plugin_state`.
		 * @param {boolean} succeeded Whether the server reported success.
		 */
		applyResult( $item, $button, install, result, succeeded ) {
			const hadFocus = $button[ 0 ] === $button[ 0 ].ownerDocument.activeElement;
			const html = result[ install.html ];

			if ( html ) {
				installer.redraw( $item, html );
			}

			// A plugin that ends active needs no words: its item now says so. A failed request
			// can still leave it active, so a failure always says what went wrong.
			if ( ! succeeded || result.plugin_state !== 'active' ) {
				installer.setStatus( $item, result.message || window.sugar_calendar_admin_dashboard.i18n.growth_tool_failed );
			}

			// Only when the button had it, so a user who moved on is not pulled back.
			if ( hadFocus && html ) {
				installer.focus( $item, install.fallbackFocus );
			}
		},

		/**
		 * Swap the item's markup for the server's redraw, keeping its status line's node in place:
		 * a live region added and filled at once is often not announced.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery} $item Tile or card.
		 * @param {string} html  The same item, redrawn.
		 */
		redraw( $item, html ) {
			let $kept = $item.find( selectors.status );
			let $fresh = $( html ).find( selectors.status );

			// The status line and each element above it up to the item stay; the nodes around them are swapped.
			while ( $kept.length && ! $kept.is( $item ) ) {
				const $around = $fresh.parent().contents();
				const at = $around.index( $fresh );

				$kept.parent().contents().not( $kept ).remove();
				$kept.before( $around.slice( 0, at ) ).after( $around.slice( at + 1 ) );

				$kept = $kept.parent();
				$fresh = $fresh.parent();
			}
		},

		/**
		 * Focus the redrawn item's link or button, else its fallback.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery} $item    Tile or card.
		 * @param {string} fallback Selector inside the item, made focusable to take it.
		 */
		focus( $item, fallback ) {
			const $focusable = $item.find( selectors.focusable ).first();

			if ( $focusable.length ) {
				$focusable.trigger( 'focus' );

				return;
			}

			$item.find( fallback ).attr( 'tabindex', '-1' ).trigger( 'focus' );
		},

		/**
		 * Say the session expired and offer Reload, in the item's status line.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery} $item   Tile or card.
		 * @param {jQuery} $button Its button, about to be disabled.
		 */
		showReload( $item, $button ) {
			const hadFocus = $button[ 0 ] === $button[ 0 ].ownerDocument.activeElement;
			const $link = app.updater.showSessionExpired( $item.find( selectors.status ).prop( 'hidden', false ) );

			// The button is about to be disabled, which would drop focus to the page.
			if ( hadFocus ) {
				$link.trigger( 'focus' );
			}
		},

		/**
		 * Mark the running button busy; the page lock holds the other controls.
		 *
		 * The running button keeps focus, so it is `aria-disabled` rather than `disabled`;
		 * after a 403 every install button is disabled.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery}  $button Running button.
		 * @param {boolean} isBusy  Whether the request is running.
		 */
		setBusy( $button, isBusy ) {
			$button.attr( {
				'aria-disabled': isBusy ? 'true' : 'false',
				'aria-busy': isBusy ? 'true' : 'false',
			} );

			if ( expired ) {
				app.el.$page.find( selectors.installs ).prop( 'disabled', true );
			}
		},

		/**
		 * Show a message in the item's status line, or hide the line.
		 *
		 * @since 3.18.0
		 *
		 * @param {jQuery} $item   Tile or card.
		 * @param {string} message Message; empty hides the line.
		 */
		setStatus( $item, message ) {
			$item.find( selectors.status ).text( message ).prop( 'hidden', message === '' );
		},
	};

	return installer;
}
