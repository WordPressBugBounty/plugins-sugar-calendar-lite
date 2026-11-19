/* global jQuery */

/**
 * Dashboard page: loads the widget modules and owns the page-level behaviour.
 *
 * Modules are ES modules loaded with import() from the URLs in
 * `sugar_calendar_admin_dashboard.modules`.
 *
 * @since 3.18.0
 */

'use strict';

window.SugarCalendarDashboard = window.SugarCalendarDashboard || ( function( document, window, $ ) {
	const app = {

		/**
		 * Cached elements.
		 *
		 * @since 3.18.0
		 *
		 * @type {Object}
		 */
		el: {},

		/**
		 * Runtime state.
		 *
		 * @since 3.18.0
		 *
		 * @type {Object}
		 */
		vars: {},

		/**
		 * Shared class names.
		 *
		 * @since 3.18.0
		 *
		 * @type {Object}
		 */
		classNames: {
			loading: 'sugar-calendar-dashboard--loading',
			selected: 'is-selected',
		},

		/**
		 * Shared selectors.
		 *
		 * @since 3.18.0
		 *
		 * @type {Object}
		 */
		selectors: {
			page: '#sugar-calendar-dashboard',
			attention: '#sugar-calendar-dashboard-attention',
			sidebar: '#sugar-calendar-dashboard-column-sidebar',
			statCards: '[data-widget="stat_cards"]',
			attentionWidget: '.sugar-calendar-dashboard-widget--attention',
			notice: '.sugar-calendar-dashboard-notice',
			noticeDismiss: '.sugar-calendar-dashboard-notice__dismiss',
			subheader: '.sugar-calendar-dashboard-subheader',

			// What an install holds: the Setup Checklist's controls, the range picker and the Integrations cards' links.
			lockable: 'button, a.sc-button, a.sc-dashboard-integration-card__action, select, input',
		},

		/**
		 * Start.
		 *
		 * @since 3.18.0
		 */
		init() {
			if ( ! $( app.selectors.page ).length ) {
				return;
			}

			app.cacheElements();
			app.setupAttentionHoist();
			app.setupInstallLock();

			app.el.$page.on( 'click', app.selectors.noticeDismiss, app.onNoticeDismiss );

			app.loadModules();
		},

		/**
		 * Cache elements.
		 *
		 * @since 3.18.0
		 */
		cacheElements() {
			app.el.$document = $( document );
			app.el.$page = $( app.selectors.page );
			app.el.$attention = $( app.selectors.attention );
			app.el.$sidebar = $( app.selectors.sidebar );

			// The range picker sits in the title bar, outside the page element.
			app.el.$lockRoots = $( `${ app.selectors.page }, ${ app.selectors.subheader }` );
		},

		/**
		 * Move a sidebar widget that needs attention above the stat cards once the columns stack.
		 *
		 * @since 3.18.0
		 */
		setupAttentionHoist() {
			const $widget = app.el.$sidebar.find( app.selectors.attentionWidget );

			if ( ! $widget.length || ! app.el.$attention.length ) {
				return;
			}

			app.vars.$attentionWidget = $widget;

			// Must match $dashboard-breakpoint-stack in admin-dashboard-page.scss.
			app.vars.attentionMedia = window.matchMedia( '(max-width: 1024px)' );
			app.vars.attentionMedia.addEventListener( 'change', app.toggleAttentionHoist );

			if ( app.vars.attentionMedia.matches ) {
				app.toggleAttentionHoist();
			}
		},

		/**
		 * Hoist or return the attention widget for the current viewport.
		 *
		 * @since 3.18.0
		 */
		toggleAttentionHoist() {
			if ( app.vars.attentionMedia.matches ) {
				// A hidden anchor marks the widget's home in the sidebar.
				if ( ! app.vars.$attentionAnchor ) {
					app.vars.$attentionAnchor = $( '<div hidden></div>' ).insertBefore( app.vars.$attentionWidget );
				}

				app.el.$attention.append( app.vars.$attentionWidget );

				return;
			}

			app.vars.$attentionAnchor?.before( app.vars.$attentionWidget );
		},

		/**
		 * Save a card's dismissal for the user, then remove the card.
		 *
		 * The X stays focusable while the save runs; once the card is gone, focus moves to its
		 * widget's selected tab.
		 *
		 * @since 3.18.0
		 *
		 * @param {Event} event Click event.
		 */
		onNoticeDismiss( event ) {
			event.preventDefault();

			const $button = $( event.currentTarget );

			if ( $button.attr( 'aria-disabled' ) === 'true' ) {
				return;
			}

			const $notice = $button.closest( app.selectors.notice );

			$button.attr( 'aria-disabled', 'true' );

			app.post( 'dashboard_dismiss', { card: $notice.attr( 'data-notice' ) } )
				.done( ( response ) => {
					if ( response?.success === true ) {
						const hadFocus = $button[ 0 ] === $button[ 0 ].ownerDocument.activeElement;
						const $tab = $notice.closest( '[data-widget]' ).find( '[role="tab"][aria-selected="true"]' ).first();

						$notice.remove();

						if ( hadFocus ) {
							$tab.trigger( 'focus' );
						}

						return;
					}

					window.console?.error?.( 'Sugar Calendar Dashboard: dismiss failed.', response?.data );
					$button.removeAttr( 'aria-disabled' );
				} )
				.fail( ( jqXHR, textStatus ) => {
					window.console?.error?.( 'Sugar Calendar Dashboard: dismiss failed.', textStatus );
					$button.removeAttr( 'aria-disabled' );

					if ( jqXHR.status === 403 ) {
						app.updater?.showInlineError( 'reload' );
					}
				} );
		},

		/**
		 * Import the modules, then start them.
		 *
		 * @since 3.18.0
		 *
		 * @return {Promise} Resolves once every module has started.
		 */
		loadModules() {
			const modules = window.sugar_calendar_admin_dashboard.modules || [];

			return Promise.all(
				modules.map( ( module ) => import( module.path ).then( ( mod ) => [ module.name, mod ] ) )
			)
				.then( app.initModules )
				.catch( ( error ) => {
					window.console?.error?.( 'Sugar Calendar Dashboard: module load failed.', error );
				} );
		},

		/**
		 * Create every module first, so an init() may rely on its siblings.
		 *
		 * @since 3.18.0
		 *
		 * @param {Array} imported `[ name, module ]` pairs.
		 */
		initModules( imported ) {
			imported.forEach( ( [ name, mod ] ) => {
				app[ name ] = mod.default( document, window, $, app );
			} );

			imported.forEach( ( [ name ] ) => {
				app[ name ].init?.();
			} );
		},

		/**
		 * Post a Dashboard AJAX task with the page's nonce.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} task Task name.
		 * @param {Object} data Task fields.
		 *
		 * @return {jQuery.jqXHR} Request.
		 */
		post( task, data ) {
			const vars = window.sugar_calendar_admin_dashboard;

			return $.post( vars.ajax_url, {
				action: 'sugar-calendar',
				task,
				page_id: vars.page_id,
				_ajax_nonce: vars.nonce,
				...data,
			} );
		},

		/**
		 * Swallow clicks and ask before leaving while an install runs.
		 *
		 * @since 3.18.0
		 */
		setupInstallLock() {
			app.vars.installing = false;
			app.vars.lockedControls = [];

			app.el.$lockRoots.each( ( index, root ) => {
				root.addEventListener( 'click', app.blockLockedClick, true );
				root.addEventListener( 'auxclick', app.blockLockedClick, true );
			} );

			window.addEventListener( 'beforeunload', app.confirmLeave );
		},

		/**
		 * Disable every other control until a plugin install settles.
		 *
		 * @since 3.18.0
		 *
		 * @param {HTMLElement} control Installing control, which keeps its own busy state.
		 */
		lockForInstall( control ) {
			app.vars.installing = true;

			// Only controls enabled now, so the unlock never re-enables one disabled on purpose.
			app.vars.lockedControls = app.el.$lockRoots.find( app.selectors.lockable ).get().filter( ( other ) => {
				if ( other === control ) {
					return false;
				}

				return other.tagName === 'A' ? other.getAttribute( 'aria-disabled' ) !== 'true' : ! other.disabled;
			} );

			app.vars.lockedControls.forEach( ( other ) => {
				if ( other.tagName === 'A' ) {
					other.setAttribute( 'aria-disabled', 'true' );
				} else {
					other.disabled = true;
				}
			} );
		},

		/**
		 * Re-enable the controls the install lock disabled.
		 *
		 * @since 3.18.0
		 */
		unlockAfterInstall() {
			app.vars.lockedControls.forEach( ( other ) => {
				if ( other.tagName === 'A' ) {
					other.removeAttribute( 'aria-disabled' );
				} else {
					other.disabled = false;
				}
			} );

			app.vars.lockedControls = [];
			app.vars.installing = false;
		},

		/**
		 * Swallow clicks on controls while an install runs.
		 *
		 * A held link still activates from the keyboard, and a control re-enabled mid-install
		 * must not start anything either.
		 *
		 * @since 3.18.0
		 *
		 * @param {Event} event Click or auxclick.
		 */
		blockLockedClick( event ) {
			if ( app.vars.installing && event.target.closest( app.selectors.lockable ) ) {
				event.preventDefault();
				event.stopPropagation();
			}
		},

		/**
		 * Ask before leaving while an install runs.
		 *
		 * @since 3.18.0
		 *
		 * @param {Event} event Beforeunload event.
		 */
		confirmLeave( event ) {
			if ( app.vars.installing ) {
				event.preventDefault();

				// Browsers show their own wording; returnValue covers the ones that ignore preventDefault().
				event.returnValue = '';
			}
		},
	};

	return app;
}( document, window, jQuery ) );

window.SugarCalendarDashboard.init();
