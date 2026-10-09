/* global jQuery */

/**
 * Setup Checklist page controls.
 *
 * @since 3.15.0
 */
( function( $ ) {
	'use strict';

	const ROOT_ID = 'sugar-calendar-setup-checklist';
	const SELECTOR_ID = 'sugar-calendar-checklist-page-selector';
	// Group toggles only show or hide content, so they stay usable during an install.
	const LOCKABLE = 'button:not(.sugar-calendar-checklist__group-toggle), a.sc-button';
	const settings = window.sugar_calendar_admin_setup_checklist || {};
	const text = settings.text || {};
	let currentSnapshot = JSON.stringify( settings.snapshot || {} );
	let refreshRequest = null;
	let deferRefreshReload = false;
	let reloadOnFocus = false;
	let installRequest = null;
	let lockedControls = [];

	/**
	 * Post one allowlisted checklist task.
	 *
	 * @param {string} task   Task name.
	 * @param {Object} fields Task-specific fields.
	 *
	 * @return {jqXHR} Request.
	 */
	function postTask( task, fields = {} ) {
		return $.post( settings.ajax_url, {
			action: 'sugar_calendar_setup_checklist',
			task,
			nonce: settings.nonce,
			...fields,
		} );
	}

	/**
	 * Read the standard plain error response.
	 *
	 * @param {jqXHR}  request  Failed request.
	 * @param {string} fallback Message for a response that carries none.
	 *
	 * @return {Object} Error data.
	 */
	function getError( request, fallback = '' ) {
		const data = request.responseJSON && request.responseJSON.data;

		if ( data && typeof data === 'object' && ! Array.isArray( data ) ) {
			return data;
		}

		return {
			code: '',
			message: fallback || text.request_failed,
		};
	}

	/**
	 * Route a stale dismissed tab to the server-owned destination.
	 *
	 * @param {Object} error Error data.
	 *
	 * @return {boolean} Whether the response was a dismissal.
	 */
	function handleDismissed( error ) {
		if ( error.code !== 'checklist_dismissed' || ! error.redirect_url ) {
			return false;
		}

		window.location.assign( error.redirect_url );

		return true;
	}

	/**
	 * Find the feedback region belonging to a control.
	 *
	 * @param {HTMLElement} control Action control.
	 *
	 * @return {HTMLElement|null} Feedback region.
	 */
	function getFeedback( control ) {
		const container = control.closest( '.sugar-calendar-checklist__action, .sugar-calendar-checklist__tool' );

		return container ? container.querySelector( '.sugar-calendar-checklist__feedback' ) : null;
	}

	/**
	 * Show a plain action result and optional native page-edit recovery.
	 *
	 * @param {HTMLElement} control Action control.
	 * @param {string}      message Plain message.
	 * @param {number}      pageId  Validated selected page ID.
	 */
	function showFeedback( control, message, pageId = 0 ) {
		const feedback = getFeedback( control );

		if ( ! feedback ) {
			return;
		}

		feedback.replaceChildren( document.createTextNode( message || '' ) );

		if ( pageId > 0 && settings.page_edit_url ) {
			const editUrl = new URL( settings.page_edit_url, window.location.href );
			const link = document.createElement( 'a' );

			editUrl.searchParams.set( 'post', String( pageId ) );
			editUrl.searchParams.set( 'action', 'edit' );
			link.href = editUrl.toString();
			link.textContent = text.edit_page;
			feedback.append( document.createTextNode( ' ' ), link );
		}

		feedback.hidden = false;
	}

	/**
	 * Clear an action's feedback.
	 *
	 * @param {HTMLElement} control Action control.
	 */
	function clearFeedback( control ) {
		const feedback = getFeedback( control );

		if ( feedback ) {
			feedback.hidden = true;
			feedback.replaceChildren();
		}
	}

	/**
	 * Set a control's pending state.
	 *
	 * @param {HTMLElement} control Action control.
	 * @param {boolean}     pending Pending state.
	 */
	function setPending( control, pending ) {
		control.dataset.pending = pending ? 'true' : 'false';
		control.setAttribute( 'aria-busy', pending ? 'true' : 'false' );

		if ( control.tagName === 'BUTTON' ) {
			control.disabled = pending ||
				control.dataset.reconciledActive === 'true' ||
				control.classList.contains( 'sugar-calendar-checklist__tool-action--installed' );
		} else {
			control.setAttribute( 'aria-disabled', pending ? 'true' : 'false' );
		}
	}

	/**
	 * Replace a control label while preserving its decorative icon.
	 *
	 * @param {HTMLElement} control Action control.
	 * @param {string}      label   Plain label.
	 */
	function setControlLabel( control, label ) {
		const icon = control.querySelector( '[aria-hidden="true"]' );

		control.replaceChildren( document.createTextNode( label ) );

		if ( icon ) {
			control.append( document.createTextNode( ' ' ), icon );
		}
	}

	/**
	 * Whether a partner plugin install is still waiting on the server.
	 *
	 * @return {boolean} Whether an install is in flight.
	 */
	function isInstalling() {
		return installRequest !== null && installRequest.state() === 'pending';
	}

	/**
	 * Disable every other control until a partner plugin install settles.
	 *
	 * @param {HTMLElement} control Installing control, which keeps its own pending state.
	 * @param {jqXHR}       request Install request.
	 */
	function lockPage( control, request ) {
		const root = document.getElementById( ROOT_ID );

		installRequest = request;

		// Only controls enabled now, so the unlock never re-enables one disabled on purpose.
		lockedControls = Array.from( root.querySelectorAll( LOCKABLE ) ).filter( ( other ) => {
			if ( other === control ) {
				return false;
			}

			return other.tagName === 'A' ? other.getAttribute( 'aria-disabled' ) !== 'true' : ! other.disabled;
		} );

		lockedControls.forEach( ( other ) => {
			if ( other.tagName === 'A' ) {
				other.setAttribute( 'aria-disabled', 'true' );
			} else {
				other.disabled = true;
			}
		} );
	}

	/**
	 * Re-enable the controls the install lock disabled.
	 */
	function unlockPage() {
		lockedControls.forEach( ( other ) => {
			if ( other.tagName === 'A' ) {
				other.removeAttribute( 'aria-disabled' );
			} else {
				other.disabled = false;
			}
		} );

		lockedControls = [];
	}

	/**
	 * Swallow clicks on controls while the install lock holds.
	 *
	 * A locked link still activates from the keyboard, and a control another request
	 * re-enabled mid-install must not start anything either.
	 *
	 * @param {Event} event Click or auxclick.
	 */
	function blockLockedClick( event ) {
		if ( lockedControls.length > 0 && event.target.closest( LOCKABLE ) ) {
			event.preventDefault();
			event.stopPropagation();
		}
	}

	/**
	 * Navigate an opened tab or the current tab.
	 *
	 * @param {string}      url          Destination.
	 * @param {Window|null} openedWindow Tab opened by the original user gesture.
	 */
	function navigate( url, openedWindow = null ) {
		if ( openedWindow && ! openedWindow.closed ) {
			openedWindow.location.replace( url );

			return;
		}

		window.location.assign( url );
	}

	/**
	 * Apply a plugin's real state to an action control.
	 *
	 * @param {HTMLElement} control     Action control.
	 * @param {string}      pluginState Server-observed state.
	 * @param {boolean}     growthTool  Whether this is a promotional card.
	 */
	function applyPluginState( control, pluginState, growthTool ) {
		const pluginLabels = $( control ).data( 'plugin-labels' );
		const labels = pluginLabels && typeof pluginLabels === 'object' && ! Array.isArray( pluginLabels ) ? pluginLabels : {};

		if ( pluginState === 'active' && growthTool ) {
			setControlLabel( control, text.installed );
			control.classList.add( 'sugar-calendar-checklist__tool-action--installed' );
			control.disabled = true;

			const icon = document.createElement( 'span' );

			icon.className = 'sc:icon-[fa6-regular--circle-check]';
			icon.setAttribute( 'aria-hidden', 'true' );
			control.append( document.createTextNode( ' ' ), icon );
		} else if ( pluginState === 'active' ) {
			// A failed activate whose fresh state reads active: the plugin is on,
			// so the control must not keep offering "Activate" until the reload.
			setControlLabel( control, text.installed );
			control.classList.add( 'sugar-calendar-checklist__tool-action--installed' );
			control.disabled = true;
		} else if ( pluginState === 'installed' ) {
			const label = typeof labels.activate === 'string' && labels.activate.trim() ? labels.activate : text.activate;

			setControlLabel( control, label );
			control.dataset.checklistAction = 'activate';
			control.classList.remove( 'sugar-calendar-checklist__tool-action--installed' );
			control.disabled = false;
		} else if ( pluginState === 'missing' ) {
			const label = typeof labels.install === 'string' && labels.install.trim() ? labels.install : text.install;

			setControlLabel( control, label );
			control.dataset.checklistAction = 'install';
			control.classList.remove( 'sugar-calendar-checklist__tool-action--installed' );
			control.disabled = false;
		}
	}

	/**
	 * Refresh authoritative completion after the tab or plugin state changes.
	 *
	 * @param {boolean} keepPage Whether to keep a real operation error visible.
	 *
	 * @return {jqXHR|null} Active request.
	 */
	function refreshChecklist( keepPage = false ) {
		deferRefreshReload = deferRefreshReload || keepPage;

		if ( refreshRequest ) {
			return refreshRequest;
		}

		refreshRequest = postTask( 'refresh' )
			.done( ( response ) => {
				const snapshot = response && response.data && response.data.snapshot;

				if ( ! snapshot ) {
					return;
				}

				if ( snapshot.dismissed ) {
					window.location.reload();

					return;
				}

				const nextSnapshot = JSON.stringify( snapshot );

				if ( nextSnapshot !== currentSnapshot ) {
					if ( deferRefreshReload || isInstalling() ) {
						reloadOnFocus = true;
					} else {
						window.location.reload();
					}

					return;
				}

				currentSnapshot = nextSnapshot;
			} )
			.fail( ( request ) => {
				handleDismissed( getError( request ) );
			} )
			.always( () => {
				refreshRequest = null;
				deferRefreshReload = false;
			} );

		return refreshRequest;
	}

	/**
	 * Handle a successful checklist item response.
	 *
	 * @param {Object} response Server response.
	 * @param {Object} action   Per-request inputs and mutable outcome.
	 */
	function handleActionSuccess( response, action ) {
		const { control, openedWindow } = action;
		const result = response && response.data ? response.data : {};

		action.pluginState = result.plugin_state || '';

		if ( result.status === 'partial' && action.pluginState === 'installed' ) {
			showFeedback( control, result.message || '' );

			return;
		}

		if ( result.redirect_url ) {
			action.leavingPage = ! openedWindow || openedWindow.closed;
			navigate( result.redirect_url, openedWindow );

			return;
		}

		action.leavingPage = true;
		window.location.reload();
	}

	/**
	 * Handle a failed checklist item request.
	 *
	 * @param {jqXHR}  request Failed request.
	 * @param {Object} action  Per-request inputs and mutable outcome.
	 */
	function handleActionError( request, action ) {
		const { control, fields, fallbackUrl, openedWindow } = action;
		const error = getError( request, control.dataset.failedMessage );

		action.pluginState = error.plugin_state || '';

		if ( handleDismissed( error ) ) {
			return;
		}

		const pageId = error.code === 'checklist_page_editor_unsupported' ?
			Number.parseInt( fields.page_id, 10 ) :
			0;

		showFeedback( control, error.message, pageId );

		if ( error.plugin_state === 'active' ) {
			control.dataset.reconciledActive = 'true';
			refreshChecklist( true );
		}

		if ( fallbackUrl ) {
			navigate( fallbackUrl, openedWindow );
		}
	}

	/**
	 * Restore checklist controls after an item request settles.
	 *
	 * @param {Object} action Per-request inputs and mutable outcome.
	 */
	function resetActionControl( action ) {
		const { control, companions, pendingLabel, originalLabel } = action;

		if ( action.leavingPage ) {
			return;
		}

		if ( pendingLabel ) {
			setControlLabel( control, originalLabel );
		}

		applyPluginState( control, action.pluginState, false );
		setPending( control, false );
		companions.forEach( ( companion ) => {
			companion.disabled = false;
		} );

		if ( action.installing ) {
			unlockPage();
		}
	}

	/**
	 * Run one checklist item action.
	 *
	 * @param {HTMLElement}   control      Action control.
	 * @param {Object}        fields       Permitted item fields.
	 * @param {string}        fallbackUrl  Ordinary navigation fallback.
	 * @param {Window|null}   openedWindow Tab opened from the user gesture.
	 * @param {HTMLElement[]} companions   Other controls disabled while pending.
	 */
	function runItemAction( control, fields = {}, fallbackUrl = '', openedWindow = null, companions = [] ) {
		if ( control.dataset.pending === 'true' ) {
			return;
		}

		const labels = $( control ).data( 'plugin-labels' ) || {};
		const originalLabel = control.textContent.trim();
		const pendingLabel = {
			install: labels.installing,
			activate: labels.activating,
		}[ control.dataset.checklistAction ];
		const action = {
			control,
			fields,
			fallbackUrl,
			openedWindow,
			companions,
			originalLabel,
			pendingLabel,
			installing: [ 'install', 'activate' ].includes( control.dataset.checklistAction ),
			pluginState: '',
			leavingPage: false,
		};

		clearFeedback( control );
		setPending( control, true );

		if ( pendingLabel ) {
			setControlLabel( control, pendingLabel );
		}

		companions.forEach( ( companion ) => {
			companion.disabled = true;
		} );

		const itemRequest = postTask( 'item_action', {
			item: control.dataset.item,
			...fields,
		} );

		if ( action.installing ) {
			lockPage( control, itemRequest );
		}

		itemRequest
			.done( ( response ) => handleActionSuccess( response, action ) )
			.fail( ( request ) => handleActionError( request, action ) )
			.always( () => resetActionControl( action ) );
	}

	/**
	 * Wire the group headings to their bodies.
	 *
	 * @param {HTMLElement} root Page root.
	 */
	function initGroups( root ) {
		root.querySelectorAll( '.sugar-calendar-checklist__group-toggle' ).forEach( ( toggle ) => {
			const body = document.getElementById( toggle.getAttribute( 'aria-controls' ) );

			if ( ! body ) {
				return;
			}

			toggle.addEventListener( 'click', () => {
				const expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';

				toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				body.hidden = expanded;
			} );
		} );
	}

	/**
	 * Wire the dynamic editable-page selector.
	 *
	 * @param {HTMLElement} root Page root.
	 */
	function initPageSelector( root ) {
		const trigger = root.querySelector( '#' + SELECTOR_ID );
		const menu = root.querySelector( '#' + SELECTOR_ID + '-menu' );
		const submit = root.querySelector( '#' + SELECTOR_ID + '-submit' );

		if ( ! trigger || ! menu || ! submit ) {
			return;
		}

		const label = trigger.querySelector( '.sugar-calendar-checklist__page-selector-label' );
		const search = menu.querySelector( '.sugar-calendar-checklist__page-search' );
		const status = menu.querySelector( '.sugar-calendar-checklist__page-status' );
		const list = menu.querySelector( '[role="listbox"]' );
		const empty = menu.querySelector( '.sugar-calendar-checklist__page-empty' );
		const more = menu.querySelector( '.sugar-calendar-checklist__page-more' );
		let loaded = false;
		let loading = false;
		let page = 0;
		let hasMore = false;
		let searchTimer = null;
		let queuedReset = false;

		const getOptions = () => Array.from( list.querySelectorAll( '.sc-dropdown-item' ) );
		const isOpen = () => trigger.getAttribute( 'aria-expanded' ) === 'true';

		function close( returnFocus ) {
			menu.hidden = true;
			trigger.setAttribute( 'aria-expanded', 'false' );

			if ( returnFocus ) {
				trigger.focus();
			}
		}

		function loadPages( reset ) {
			if ( loading ) {
				queuedReset = queuedReset || reset;

				return;
			}

			loading = true;
			status.textContent = text.loading_pages;
			more.setAttribute( 'aria-disabled', 'true' );

			const nextPage = reset ? 1 : page + 1;
			const previousOptionCount = getOptions().length;

			postTask( 'pages', {
				page: String( nextPage ),
				search: search.value,
			} )
				.done( ( response ) => {
					const result = response && response.data ? response.data : {};
					const items = Array.isArray( result.items ) ? result.items : [];

					if ( reset ) {
						if ( list.contains( document.activeElement ) ) {
							search.focus();
						}

						list.replaceChildren();
					}

					items.forEach( ( item ) => {
						const id = Number( item.id );

						if ( ! Number.isInteger( id ) || id < 1 ) {
							return;
						}

						const option = document.createElement( 'button' );

						option.type = 'button';
						option.className = 'sc-dropdown-item';
						option.setAttribute( 'role', 'option' );
						option.setAttribute( 'aria-selected', String( trigger.dataset.value === String( id ) ) );
						option.dataset.value = String( id );
						option.textContent = typeof item.title === 'string' ? item.title : '';
						list.append( option );
					} );

					loaded = true;
					page = nextPage;
					hasMore = result.has_more === true;
					status.textContent = '';
					empty.hidden = getOptions().length > 0 || hasMore;

					if ( document.activeElement === more && ! hasMore ) {
						( getOptions()[ previousOptionCount ] || search ).focus();
					}

					more.hidden = ! hasMore;
				} )
				.fail( ( request ) => {
					const error = getError( request );

					if ( ! handleDismissed( error ) ) {
						status.textContent = error.message;
					}
				} )
				.always( () => {
					loading = false;
					more.removeAttribute( 'aria-disabled' );

					if ( queuedReset ) {
						queuedReset = false;
						loadPages( true );
					}
				} );
		}

		function open( preferOption = false ) {
			menu.hidden = false;
			trigger.setAttribute( 'aria-expanded', 'true' );

			if ( ! loaded ) {
				loadPages( true );
			}

			const options = getOptions();
			const selected = options.find( ( option ) => option.getAttribute( 'aria-selected' ) === 'true' );

			if ( preferOption && ( selected || options[ 0 ] ) ) {
				( selected || options[ 0 ] ).focus();
			} else {
				search.focus();
			}
		}

		function choose( option ) {
			getOptions().forEach( ( other ) => {
				other.setAttribute( 'aria-selected', other === option ? 'true' : 'false' );
			} );

			trigger.dataset.value = option.dataset.value;
			label.textContent = option.textContent.trim();
			trigger.removeAttribute( 'data-state' );
			submit.disabled = false;
			close( true );
		}

		trigger.addEventListener( 'click', () => {
			if ( isOpen() ) {
				close( false );
			} else {
				open();
			}
		} );

		trigger.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
				event.preventDefault();
				open( true );
			} else if ( event.key === 'Escape' && isOpen() ) {
				close( true );
			}
		} );

		search.addEventListener( 'input', () => {
			window.clearTimeout( searchTimer );
			searchTimer = window.setTimeout( () => loadPages( true ), 300 );
		} );

		search.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'ArrowDown' ) {
				const first = getOptions()[ 0 ];

				if ( first ) {
					event.preventDefault();
					first.focus();
				}
			}
		} );

		list.addEventListener( 'click', ( event ) => {
			const option = event.target.closest( '.sc-dropdown-item' );

			if ( option && list.contains( option ) ) {
				choose( option );
			}
		} );

		list.addEventListener( 'keydown', ( event ) => {
			const options = getOptions();
			const index = options.indexOf( document.activeElement );

			if ( index === -1 ) {
				return;
			}

			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					options[ ( index + 1 ) % options.length ].focus();
					break;

				case 'ArrowUp':
					event.preventDefault();
					options[ ( index - 1 + options.length ) % options.length ].focus();
					break;

				case 'Home':
					event.preventDefault();
					options[ 0 ].focus();
					break;

				case 'End':
					event.preventDefault();
					options[ options.length - 1 ].focus();
					break;
			}
		} );

		more.addEventListener( 'click', () => {
			if ( more.getAttribute( 'aria-disabled' ) !== 'true' ) {
				loadPages( false );
			}
		} );

		menu.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				close( true );
			}
		} );

		function closeAfterFocusLeaves( event ) {
			const next = event.relatedTarget;

			if ( isOpen() && ( ! next || ( ! menu.contains( next ) && ! trigger.contains( next ) ) ) ) {
				close( false );
			}
		}

		menu.addEventListener( 'focusout', closeAfterFocusLeaves );
		trigger.addEventListener( 'focusout', closeAfterFocusLeaves );

		document.addEventListener( 'click', ( event ) => {
			if ( isOpen() && ! trigger.contains( event.target ) && ! menu.contains( event.target ) ) {
				close( false );
			}
		} );
	}

	/**
	 * Wire all server-backed item actions.
	 *
	 * @param {HTMLElement} root Page root.
	 */
	function initItemActions( root ) {
		root.querySelectorAll( '[data-checklist-task="item_action"]' ).forEach( ( control ) => {
			function activate( event ) {
				if ( control.dataset.pending === 'true' ) {
					event.preventDefault();

					return;
				}

				if ( control.tagName === 'A' ) {
					if (
						( event.type === 'click' && event.button !== 0 ) ||
						( event.type === 'auxclick' && event.button !== 1 )
					) {
						return;
					}

					const fallbackUrl = control.href;
					let openedWindow = null;
					const separateWindow = control.target === '_blank' ||
						event.type === 'auxclick' ||
						event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

					if ( separateWindow ) {
						openedWindow = window.open( '', '_blank' );
					}

					if ( separateWindow && ! openedWindow ) {
						postTask( 'item_action', { item: control.dataset.item } );

						return;
					}

					event.preventDefault();

					if ( openedWindow ) {
						openedWindow.opener = null;
					}

					runItemAction( control, {}, fallbackUrl, openedWindow );

					return;
				}

				event.preventDefault();

				const fields = {};
				const companions = [];
				const input = ( control.dataset.checklistInput || '' ).split( ' ' );

				if ( input.includes( 'page_id' ) ) {
					const selector = root.querySelector( '#' + SELECTOR_ID );
					const pageId = selector ? Number.parseInt( selector.dataset.value, 10 ) : 0;

					if ( ! Number.isInteger( pageId ) || pageId < 1 ) {
						return;
					}

					fields.page_id = String( pageId );
				}

				if ( input.includes( 'email' ) ) {
					const email = root.querySelector( '#sugar-calendar-checklist-newsletter' );

					fields.email = email ? email.value : '';

					if ( email ) {
						companions.push( email );
					}
				}

				runItemAction( control, fields, '', null, companions );
			}

			control.addEventListener( 'click', activate );

			if ( control.tagName === 'A' ) {
				control.addEventListener( 'auxclick', activate );
			}
		} );
	}

	/**
	 * Wire the promotional plugin actions.
	 *
	 * @param {HTMLElement} root Page root.
	 */
	function initGrowthTools( root ) {
		root.querySelectorAll( '[data-checklist-task="growth_tool"]' ).forEach( ( control ) => {
			control.addEventListener( 'click', () => {
				if ( control.dataset.pending === 'true' || control.disabled ) {
					return;
				}

				const originalLabel = control.textContent.trim();
				const pendingLabel = {
					install: text.installing,
					activate: text.activating,
				}[ control.dataset.checklistAction ];
				let pluginState = '';

				clearFeedback( control );
				setPending( control, true );

				if ( pendingLabel ) {
					setControlLabel( control, pendingLabel );
				}

				const toolRequest = postTask( 'growth_tool', { key: control.dataset.key } );

				lockPage( control, toolRequest );

				toolRequest
					.done( ( response ) => {
						const result = response && response.data ? response.data : {};

						pluginState = result.plugin_state || '';

						if ( pluginState !== 'active' ) {
							showFeedback( control, result.message || '' );
						}
					} )
					.fail( ( request ) => {
						const error = getError( request );

						pluginState = error.plugin_state || '';

						if ( handleDismissed( error ) ) {
							return;
						}

						showFeedback( control, error.message );
					} )
					.always( () => {
						if ( pendingLabel ) {
							setControlLabel( control, originalLabel );
						}

						applyPluginState( control, pluginState, true );

						if ( control.dataset.pending === 'true' ) {
							setPending( control, false );
						}

						unlockPage();
					} );
			} );
		} );
	}

	/**
	 * Keep Tab inside the dialog while it is open.
	 *
	 * @param {jQuery} $box Dialog box element.
	 *
	 * @return {Function} Handler to detach when the dialog closes.
	 */
	function trapFocus( $box ) {
		$box.attr( 'tabindex', '-1' );

		function handler( event ) {
			if ( event.key !== 'Tab' ) {
				return;
			}

			const focusable = $box
				.find( 'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"]):not(:disabled)' )
				.filter( ':visible' )
				.filter( function() {
					return this.getAttribute( 'aria-disabled' ) !== 'true';
				} );
			const box = $box.get( 0 );

			if ( ! focusable.length ) {
				event.preventDefault();

				if ( box ) {
					box.focus();
				}

				return;
			}

			const first = focusable.get( 0 );
			const last = focusable.get( focusable.length - 1 );
			const active = document.activeElement;
			const activeIndex = focusable.toArray().indexOf( active );

			if ( ! box || ! box.contains( active ) || activeIndex === -1 ) {
				event.preventDefault();
				( event.shiftKey ? last : first ).focus();
			} else if ( event.shiftKey && active === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && active === last ) {
				event.preventDefault();
				first.focus();
			}
		}

		document.addEventListener( 'keydown', handler, true );

		return () => document.removeEventListener( 'keydown', handler, true );
	}

	/**
	 * Wire permanent dismissal to the existing confirmation dialog.
	 *
	 * @param {HTMLElement} root Page root.
	 */
	function initDismiss( root ) {
		const trigger = root.querySelector( '.sugar-calendar-checklist__dismiss' );

		if ( ! trigger ) {
			return;
		}

		const dismissText = settings.dismiss || {};

		trigger.addEventListener( 'click', () => {
			let releaseFocus = null;
			let pending = false;

			$.confirm( {
				typeAnimated: false,
				draggable: false,
				animateFromElement: false,
				boxWidth: '400px',
				useBootstrap: false,
				type: 'orange',
				escapeKey: 'cancel',
				icon: 'sc:icon-[fa6-solid--circle-exclamation]',
				title: dismissText.title,
				content: dismissText.message,
				buttons: {
					confirm: {
						text: dismissText.confirm,
						btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-primary',
						action() {
							const dialog = this;

							if ( pending ) {
								return false;
							}

							pending = true;
							dialog.$body.trigger( 'focus' );
							dialog.$content.text( dismissText.message );
							dialog.$$confirm.prop( 'disabled', true );
							dialog.$$cancel.prop( 'disabled', true );

							postTask( 'dismiss' )
								.done( ( response ) => {
									const result = response && response.data ? response.data : {};

									if ( result.redirect_url ) {
										window.location.assign( result.redirect_url );
									}
								} )
								.fail( ( request ) => {
									const error = getError( request );

									if ( ! handleDismissed( error ) ) {
										pending = false;
										dialog.$content.text( error.message );
										dialog.$$confirm.prop( 'disabled', false );
										dialog.$$cancel.prop( 'disabled', false );
										dialog.$$confirm.trigger( 'focus' );
									}
								} );

							return false;
						},
					},
					cancel: {
						text: dismissText.cancel,
						btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-tertiary',
						action() {
							return ! pending;
						},
					},
				},
				onOpenBefore() {
					this.$jconfirmBox.addClass( 'sugar-calendar-checklist-dismiss' );
					this.$content.attr( 'id', 'sugar-calendar-checklist-dismiss-description' );
					this.$title.attr( 'id', 'sugar-calendar-checklist-dismiss-title' );
					this.$body.attr( {
						role: 'dialog',
						'aria-modal': 'true',
						'aria-labelledby': 'sugar-calendar-checklist-dismiss-title',
						'aria-describedby': 'sugar-calendar-checklist-dismiss-description',
					} );
				},
				onOpen() {
					releaseFocus = trapFocus( this.$body );
					this.$$confirm.trigger( 'focus' );
				},
				onDestroy() {
					if ( releaseFocus ) {
						releaseFocus();
					}

					trigger.focus();
				},
			} );
		} );
	}

	$( () => {
		const root = document.getElementById( ROOT_ID );

		if ( ! root ) {
			return;
		}

		initGroups( root );
		initPageSelector( root );
		initItemActions( root );
		initGrowthTools( root );
		initDismiss( root );

		root.addEventListener( 'click', blockLockedClick, true );
		root.addEventListener( 'auxclick', blockLockedClick, true );

		window.addEventListener( 'beforeunload', ( event ) => {
			if ( isInstalling() ) {
				event.preventDefault();
				// Browsers show their own wording; returnValue covers the ones that ignore preventDefault().
				event.returnValue = '';
			}
		} );

		window.addEventListener( 'focus', () => {
			// A reload mid-install would raise the leave warning from our own code.
			if ( document.visibilityState === 'visible' && ! isInstalling() ) {
				if ( reloadOnFocus ) {
					window.location.reload();

					return;
				}

				refreshChecklist();
			}
		} );
	} );
}( jQuery ) );
