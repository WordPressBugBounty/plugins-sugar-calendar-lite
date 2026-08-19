/*
 * GENERATED FILE — do not edit.
 *
 * Built by `gulp js:bundles` from, in order:
 *   assets/js/features/registration-form/frontend/parts/shared.js
 *   assets/js/features/registration-form/frontend/parts/adapter-order.js
 *   assets/js/features/registration-form/frontend/parts/adapter-rsvp.js
 *   assets/js/features/registration-form/frontend/parts/attendees.js
 *   assets/js/features/registration-form/frontend/parts/errors.js
 *   assets/js/features/registration-form/frontend/parts/step.js
 *   assets/js/features/registration-form/frontend/parts/entry.js
 *
 * Edit those sources instead; this file is overwritten on every build.
 */

/* ---- assets/js/features/registration-form/frontend/parts/shared.js ---- */

/**
 * Registration Form — front-end shared state and cross-file helpers.
 *
 * Each `parts/` file is its own IIFE, concatenated into `general.js` by
 * `gulp js:bundles`; they share state through
 * `window.SugarCalendar.RegistrationFormInternal` since separate IIFEs don't
 * share scope. Not public API — see `parts/entry.js`.
 *
 * @since 3.13.0
 */
( function() {

	'use strict';

	window.SugarCalendar = window.SugarCalendar || {};
	window.SugarCalendar.RegistrationFormInternal = window.SugarCalendar.RegistrationFormInternal || {};

	const RF = window.SugarCalendar.RegistrationFormInternal;

	/**
	 * Localized copy, read at parse time.
	 *
	 * Localized onto the bundle's own handle, so it's printed before this runs.
	 *
	 * @since 3.13.0
	 *
	 * @type {Object}
	 */
	RF.strings = ( window.sc_registration_form_obj && window.sc_registration_form_obj.strings ) || {};

	/**
	 * Host adapters, in load order (ticketing, then RSVP).
	 *
	 * init() resolves by the step root's data-context, not position, so order
	 * carries no behaviour.
	 *
	 * @since 3.13.0
	 *
	 * @type {Array}
	 */
	RF.adapters = RF.adapters || [];

	/**
	 * Mutable controller state, shared by every part.
	 *
	 * @since 3.13.0
	 *
	 * @type {Object}
	 */
	RF.state = {
		adapter: null,
		step: null,
		template: null,
		ctaButton: null,
		backButton: null,
		originalCtaLabel: '',
		stepOneActive: true,
		stamping: false
	};

	/**
	 * A fallback display name for an attendee with nothing entered yet.
	 *
	 * @since 3.13.0
	 *
	 * @param {number} index Zero-based position.
	 *
	 * @return {string} Display name.
	 */
	RF.attendeeFallbackName = function( index ) {

		return ( RF.strings.attendeeFallback || 'Attendee' ) + ' ' + ( index + 1 );
	};

	/**
	 * Expand or collapse an attendee block.
	 *
	 * @since 3.13.0
	 *
	 * @param {HTMLElement} block    The attendee block.
	 * @param {boolean}     expanded Whether it should be open.
	 */
	RF.setExpanded = function( block, expanded ) {

		block.dataset.expanded = expanded ? 'true' : 'false';
		block.querySelector( '.sc-regform__attendee-header' ).setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
	};

}() );

/* ---- assets/js/features/registration-form/frontend/parts/adapter-order.js ---- */

/* global jQuery */

/**
 * Registration Form — the ticketing checkout host adapter.
 *
 * Every ticketing-specific selector lives in this file — nothing outside it may
 * reference #sc-event-ticketing-* or the two attendee-list ids.
 *
 * @since 3.13.0
 */
( function( $ ) {

	'use strict';

	const RF = window.SugarCalendar.RegistrationFormInternal;
	const state = RF.state;

	/**
	 * The ticketing checkout host.
	 *
	 * @since 3.13.0
	 */
	const ORDER_ADAPTER = {

		// Matched against the step root's own data-context, not array order.
		slug: 'order',

		modalEvent: 'show.bs.modal',

		cta: function() {

			return document.getElementById( 'sc-event-ticketing-purchase' );
		},

		modal: function() {

			return $( '#sc-event-ticketing-modal' );
		},

		mainColumn: function() {

			return document.getElementById( 'sc-event-ticketing-checkout-main' );
		},

		// The ticketing CTA always drives the step; there is no equivalent of
		// RSVP's Not Going path to opt out of.
		isActive: function() {

			return true;
		},

		/**
		 * The each-attendee list: one entry per attendee-list row, honouring
		 * the schema's ticket-type targeting.
		 *
		 * @since 3.13.0
		 *
		 * @return {Array} Attendee descriptors.
		 */
		attendees: function() {

			const rows = document.querySelectorAll(
				'#sc-event-ticketing-modal-attendee-list .sc-event-ticketing-attendee,' +
				'#sc-event-ticketing-modal-attendee-list-multiple-tickets .sc-event-ticketing-attendee'
			);

			const targeted = ORDER_ADAPTER.targetedTicketTypes();
			const out = [];

			rows.forEach( function( row, index ) {

				const rawKey = row.getAttribute( 'attendee-key' );

				if ( rawKey === null || rawKey === '' ) {
					return;
				}

				const select = row.querySelector( 'select' );
				const ticketType = select ? ( parseInt( select.value, 10 ) || 0 ) : 0;

				if ( targeted !== null && targeted.indexOf( ticketType ) === -1 ) {
					return;
				}

				out.push( {
					key: 'a' + rawKey,
					name: ORDER_ADAPTER.attendeeName( row, index ),
					ticketType: ticketType,
					// The option's text is the ticket name, so no localized map is needed.
					ticketName: select && select.selectedIndex >= 0
						? select.options[ select.selectedIndex ].text.trim()
						: ''
				} );
			} );

			return out;
		},

		/**
		 * The targeted ticket type ids, or null when the form applies to all.
		 *
		 * @since 3.13.0
		 *
		 * @return {Array|null} Ticket type ids.
		 */
		targetedTicketTypes: function() {

			let parsed;

			try {
				parsed = JSON.parse( state.step.dataset.ticketTypes || '"all"' );
			} catch ( e ) {
				return null;
			}

			return Array.isArray( parsed ) ? parsed.map( Number ) : null;
		},

		/**
		 * A display name for an attendee-list row.
		 *
		 * @since 3.13.0
		 *
		 * @param {HTMLElement} row   The attendee row.
		 * @param {number}      index Zero-based position.
		 *
		 * @return {string} Display name.
		 */
		attendeeName: function( row, index ) {

			const full = row.querySelector( '[name*="[full_name]"]' );

			if ( full && full.value.trim() !== '' ) {
				return full.value.trim();
			}

			const first = row.querySelector( '[name*="[first_name]"]' );
			const last = row.querySelector( '[name*="[last_name]"]' );
			const parts = [ first ? first.value.trim() : '', last ? last.value.trim() : '' ].filter( Boolean );

			if ( parts.length ) {
				return parts.join( ' ' );
			}

			return RF.attendeeFallbackName( index );
		},

		// Lets us paint the structured error map without editing the host's
		// own $.ajax success callback.
		bindAjaxErrors: function() {

			$( document ).on( 'ajaxSuccess', ORDER_ADAPTER.onAjaxSuccess );
		},

		/**
		 * Reveal step one when the gateway writes an error the step is hiding.
		 *
		 * Card declines aren't an ajax response we can inspect — stripe.js appends
		 * them straight into #sc-event-ticketing-card-errors, which the active step
		 * hides. Without this the buyer just sees a stopped spinner.
		 *
		 * @since 3.13.0
		 */
		watchGatewayErrors: function() {

			const box = document.getElementById( 'sc-event-ticketing-card-errors' );

			if ( ! box || typeof window.MutationObserver === 'undefined' ) {
				return;
			}

			new window.MutationObserver( function() {

				if ( box.children.length && ! state.stepOneActive ) {
					RF.resetToStepOne();
				}
			} ).observe( box, { childList: true } );
		},

		/**
		 * Inspect the host's validation response for our structured error map.
		 *
		 * @since 3.13.0
		 *
		 * @param {Event}  event    The jQuery ajaxSuccess event.
		 * @param {Object} xhr      The jqXHR.
		 * @param {Object} settings The ajax settings.
		 */
		onAjaxSuccess: function( event, xhr, settings ) {

			if ( ! settings || ! settings.data || settings.data.indexOf( 'sc_et_validate_checkout' ) === -1 ) {
				return;
			}

			const body = xhr.responseJSON;

			if ( ! body || body.success || ! body.data ) {
				return;
			}

			RF.clearErrors();

			if ( body.data.sc_registration_errors ) {
				RF.showErrors( body.data.sc_registration_errors );
			}

			// Billing/capacity/cart errors point at the host's own panel; reveal it.
			if ( hasHostErrors( body.data.errors ) ) {
				RF.resetToStepOne();
			}
		}
	};

	/**
	 * Whether any returned error points outside the registration step.
	 *
	 * @since 3.13.0
	 *
	 * @param {Object} errors The host's flat error map.
	 *
	 * @return {boolean} True when a host-panel error is present.
	 */
	function hasHostErrors( errors ) {

		if ( ! errors ) {
			return false;
		}

		return Object.keys( errors ).some( function( id ) {

			const selector = errors[ id ] && errors[ id ].selector;

			return ! selector || selector.indexOf( 'sc-regform' ) === -1;
		} );
	}

	RF.adapters.push( ORDER_ADAPTER );

}( jQuery ) );

/* ---- assets/js/features/registration-form/frontend/parts/adapter-rsvp.js ---- */

/* global jQuery */

/**
 * Registration Form — the RSVP response modal host adapter.
 *
 * Every RSVP-specific selector lives in this file — nothing outside it may
 * reference #sc-rsvp-frontend-modal__* or the additional-attendee row classes.
 *
 * @since 3.13.0
 */
( function( $ ) {

	'use strict';

	const RF = window.SugarCalendar.RegistrationFormInternal;
	const state = RF.state;

	/**
	 * The RSVP response modal host.
	 *
	 * @since 3.13.0
	 */
	const RSVP_ADAPTER = {

		// Matched against the step root's own data-context, not array order.
		slug: 'rsvp',

		// sc-rsvp sets the submit button's data-going on 'show.bs.modal'; we bind
		// to the later 'shown.bs.modal' so that value is already set here.
		modalEvent: 'shown.bs.modal',

		cta: function() {

			return document.getElementById( 'sc-rsvp-frontend-modal__response__submit' );
		},

		modal: function() {

			return $( '#sc-rsvp-frontend-modal__response' );
		},

		mainColumn: function() {

			return document.querySelector( '.sc-rsvp-frontend-modal__response__form__body__content' );
		},

		// Only "Going" is taken over; "Not Going" must reach sc-rsvp's own
		// submit handler untouched so a required question never blocks a decline.
		isActive: function() {

			return $( state.ctaButton ).data( 'going' ) === 1;
		},

		/**
		 * Whether sc-rsvp's own step-one fields already pass its client-side check.
		 *
		 * Checked first so a missing field falls through to sc-rsvp's own submit
		 * handler and error panel instead of our step taking over the click.
		 *
		 * @since 3.13.0
		 *
		 * @return {boolean} Whether the step may take over this click.
		 */
		hostFieldsValid: function() {

			const name = document.getElementById( 'sc-rsvp__form__full-name' );
			const email = document.getElementById( 'sc-rsvp__form__email' );
			const phone = document.getElementById( 'sc-rsvp__form__phone' );

			if ( ! name || name.value.trim() === '' ) {
				return false;
			}

			if ( ! email || email.value.trim() === '' ) {
				return false;
			}

			if ( phone && phone.required && phone.value.trim() === '' ) {
				return false;
			}

			return true;
		},

		/**
		 * The each-attendee list: the RSVP owner plus one entry per additional-
		 * attendee repeater row. RSVP has no ticket types, so ticketName stays
		 * '' for every card.
		 *
		 * @since 3.13.0
		 *
		 * @return {Array} Attendee descriptors.
		 */
		attendees: function() {

			const out = [ {
				key: 'main',
				name: RSVP_ADAPTER.mainAttendeeName(),
				ticketType: 0,
				ticketName: ''
			} ];

			document.querySelectorAll( '.sc-rsvp-frontend-modal__response__additional-attendees__row' ).forEach( function( row ) {

				const rowId = row.getAttribute( 'data-row-id' );

				// data-row-id="0" is the hidden template row; skip it.
				if ( ! rowId || rowId === '0' ) {
					return;
				}

				// out.length accounts for the owner already at index 0.
				out.push( {
					key: 'a' + rowId,
					name: RSVP_ADAPTER.attendeeName( row, out.length ),
					ticketType: 0,
					ticketName: ''
				} );
			} );

			return out;
		},

		/**
		 * A display name for an additional-attendee row.
		 *
		 * @since 3.13.0
		 *
		 * @param {HTMLElement} row   The attendee row.
		 * @param {number}      index Zero-based position.
		 *
		 * @return {string} Display name.
		 */
		attendeeName: function( row, index ) {

			const name = row.querySelector( '.sc-rsvp-frontend-modal__response__field-name' );

			if ( name && name.value.trim() !== '' ) {
				return name.value.trim();
			}

			return RF.attendeeFallbackName( index );
		},

		/**
		 * The RSVP owner's name, as currently typed, or a fallback when blank.
		 *
		 * @since 3.13.0
		 *
		 * @return {string} Display name.
		 */
		mainAttendeeName: function() {

			const input = document.getElementById( 'sc-rsvp__form__full-name' );
			const value = input ? input.value.trim() : '';

			return value !== '' ? value : RF.attendeeFallbackName( 0 );
		},

		/**
		 * Keep the attendee list live as single-event.js adds/removes rows
		 * after this step has mounted.
		 *
		 * @since 3.13.0
		 *
		 * @param {Function} onChange Called whenever the row set changes.
		 */
		watchAttendees: function( onChange ) {

			const container = document.querySelector( '.sc-rsvp-frontend-modal__response__form__body__content__additional-attendees' );

			if ( ! container ) {
				return;
			}

			new MutationObserver( onChange ).observe( container, { childList: true } );
		}
	};

	RF.adapters.push( RSVP_ADAPTER );

}( jQuery ) );

/* ---- assets/js/features/registration-form/frontend/parts/attendees.js ---- */

/**
 * Registration Form — attendee block stamping.
 *
 * Owns the <template> clone/reindex/restore cycle: one block per applicable
 * attendee, entered values preserved across re-stamps, plus the two things that
 * decide what actually posts (control enabling and the leaf count).
 *
 * @since 3.13.0
 */
( function() {

	'use strict';

	const RF = window.SugarCalendar.RegistrationFormInternal;
	const state = RF.state;

	const KEY_PLACEHOLDER = '__KEY__';

	// Every attribute that must be unique per attendee. Without rewriting
	// `name`, all attendees post under one key and answers 2..N are lost silently.
	const KEY_ATTRIBUTES = [ 'name', 'id', 'for', 'aria-controls', 'aria-describedby', 'data-attendee-key' ];

	/**
	 * The attendees the form applies to, in DOM order.
	 *
	 * Keys come from each row's existing identity and are only prefixed, never
	 * renumbered — every host's own attendee numbering must match what
	 * attendees[] posts.
	 *
	 * @since 3.13.0
	 *
	 * @return {Array} Attendee descriptors.
	 */
	function collectAttendees() {

		if ( state.step.dataset.collect !== 'each_attendee' ) {
			return [ { key: 'main', name: '', ticketType: 0, ticketName: '' } ];
		}

		return state.adapter.attendees();
	}

	/**
	 * Render one block per applicable attendee, preserving entered values.
	 *
	 * Guarded by a module-level flag so a future MutationObserver watching this
	 * container's own writes can't loop unbounded.
	 *
	 * @since 3.13.0
	 */
	function stamp() {

		if ( state.stamping ) {
			return;
		}

		state.stamping = true;

		try {
			doStamp();
		} finally {
			state.stamping = false;
		}
	}

	/**
	 * The actual stamp work, run only while re-entrancy is guarded against.
	 *
	 * @since 3.13.0
	 */
	function doStamp() {

		const container = state.step.querySelector( '.sc-regform__attendees' );

		if ( ! container ) {
			return;
		}

		const saved = snapshot( container );
		const attendees = collectAttendees();

		container.textContent = '';

		attendees.forEach( function( attendee, index ) {

			const clone = state.template.content.firstElementChild.cloneNode( true );

			reindex( clone, attendee.key );

			clone.querySelector( '.sc-regform__attendee-name' ).textContent = attendee.name;
			clone.querySelector( '.sc-regform__attendee-ticket' ).textContent = attendee.ticketName;

			// No ticket name means no separator to draw.
			if ( attendee.ticketName === '' ) {
				clone.querySelector( '.sc-regform__attendee-sep' ).hidden = true;
			}

			// Not shown for a single attendee, nor on the last one.
			const next = clone.querySelector( '.sc-regform__next' );

			if ( next && ( attendees.length < 2 || index === attendees.length - 1 ) ) {
				next.hidden = true;
			}

			// Only the first block starts expanded.
			RF.setExpanded( clone, index === 0 );

			restore( clone, attendee.key, saved );

			container.appendChild( clone );
		} );

		// `hidden` alone doesn't exclude a field from jQuery.serialize(); only
		// `disabled` does, so controls stay disabled until the step is revealed.
		setControlsEnabled( ! state.stepOneActive );
	}

	/**
	 * Rewrite every per-attendee attribute on a cloned block.
	 *
	 * @since 3.13.0
	 *
	 * @param {HTMLElement} clone The cloned attendee block.
	 * @param {string}      key   Attendee key.
	 */
	function reindex( clone, key ) {

		const nodes = [ clone ].concat( Array.prototype.slice.call( clone.querySelectorAll( '*' ) ) );

		nodes.forEach( function( node ) {

			KEY_ATTRIBUTES.forEach( function( attribute ) {

				const value = node.getAttribute( attribute );

				if ( value !== null && value.indexOf( KEY_PLACEHOLDER ) !== -1 ) {
					node.setAttribute( attribute, value.split( KEY_PLACEHOLDER ).join( key ) );
				}
			} );
		} );
	}

	/**
	 * Snapshot entered values, keyed by attendee key then field id.
	 *
	 * @since 3.13.0
	 *
	 * @param {HTMLElement} container The attendee list.
	 *
	 * @return {Object} Saved values.
	 */
	function snapshot( container ) {

		const saved = {};

		container.querySelectorAll( '.sc-regform__attendee' ).forEach( function( block ) {

			const key = block.dataset.attendeeKey;

			saved[ key ] = {};

			block.querySelectorAll( '.sc-regform__field' ).forEach( function( field ) {

				const id = field.dataset.fieldId;
				const checkables = field.querySelectorAll( 'input[type="radio"], input[type="checkbox"]' );

				if ( checkables.length ) {
					saved[ key ][ id ] = Array.prototype.filter.call( checkables, function( input ) {

						return input.checked;
					} ).map( function( input ) {

						return input.value;
					} );

					return;
				}

				const control = field.querySelector( 'input, select, textarea' );

				if ( control ) {
					saved[ key ][ id ] = control.value;
				}
			} );
		} );

		return saved;
	}

	/**
	 * Re-apply snapshotted values to a freshly stamped block.
	 *
	 * Keyed by attendee key, so dropping quantity 3 -> 2 drops that attendee's
	 * answers and keeps the rest.
	 *
	 * @since 3.13.0
	 *
	 * @param {HTMLElement} block The stamped attendee block.
	 * @param {string}      key   Attendee key.
	 * @param {Object}      saved Snapshot.
	 */
	function restore( block, key, saved ) {

		if ( ! saved[ key ] ) {
			return;
		}

		block.querySelectorAll( '.sc-regform__field' ).forEach( function( field ) {

			const value = saved[ key ][ field.dataset.fieldId ];

			if ( typeof value === 'undefined' ) {
				return;
			}

			if ( Array.isArray( value ) ) {
				field.querySelectorAll( 'input[type="radio"], input[type="checkbox"]' ).forEach( function( input ) {

					input.checked = value.indexOf( input.value ) !== -1;
				} );

				return;
			}

			const control = field.querySelector( 'input, select, textarea' );

			if ( control ) {
				control.value = value;
			}
		} );
	}

	/**
	 * Enable or disable every control in the step.
	 *
	 * @since 3.13.0
	 *
	 * @param {boolean} enabled Whether controls should submit.
	 */
	function setControlsEnabled( enabled ) {

		state.step.querySelectorAll( 'input, select, textarea' ).forEach( function( control ) {

			control.disabled = ! enabled;
		} );

		if ( enabled ) {
			stampLeafCount();
		}
	}

	/**
	 * Record how many answer leaves are being submitted.
	 *
	 * max_input_vars can silently truncate a large POST; the server compares this
	 * count with what it received so that fails loud instead of looking like a
	 * validation error.
	 *
	 * @since 3.13.0
	 */
	function stampLeafCount() {

		const counter = state.step.querySelector( '[name="sc_regform_leaf_count"]' );

		if ( ! counter ) {
			return;
		}

		let leaves = 0;

		state.step.querySelectorAll( '.sc-regform__field' ).forEach( function( field ) {

			const checked = field.querySelectorAll( 'input[type="checkbox"]:checked, input[type="radio"]:checked' );

			if ( field.querySelector( 'input[type="checkbox"], input[type="radio"]' ) ) {
				leaves += checked.length;

				return;
			}

			const control = field.querySelector( 'input, select, textarea' );

			if ( control && control.value !== '' ) {
				leaves += 1;
			}
		} );

		counter.value = String( leaves );
	}

	RF.stamp = stamp;
	RF.setControlsEnabled = setControlsEnabled;
	RF.stampLeafCount = stampLeafCount;

}() );

/* ---- assets/js/features/registration-form/frontend/parts/errors.js ---- */

/**
 * Registration Form — validation error painting.
 *
 * Paints the server's structured error map onto the stamped blocks, clears it,
 * and clears a single field's error as soon as the visitor edits it.
 *
 * @since 3.13.0
 */
( function() {

	'use strict';

	const RF = window.SugarCalendar.RegistrationFormInternal;
	const state = RF.state;

	/**
	 * Clear a field's error as soon as it is edited.
	 *
	 * @since 3.13.0
	 *
	 * @param {Event} event The input/change event.
	 */
	function onFieldChanged( event ) {

		const field = event.target.closest( '.sc-regform__field' );

		if ( ! field ) {
			return;
		}

		field.querySelector( '.sc-regform__error' ).textContent = '';

		field.querySelectorAll( '[aria-invalid]' ).forEach( function( control ) {

			control.removeAttribute( 'aria-invalid' );
		} );
	}

	/**
	 * Paint the structured error map.
	 *
	 * Codes only, copy is localized here so no label or answer ever travels
	 * back from the server in a message; textContent throughout since answers
	 * and labels are attacker-influenced.
	 *
	 * @since 3.13.0
	 *
	 * @param {Object} errorMap { attendeeKey: { fieldId: code } }.
	 *
	 * @return {number} How many field errors were painted.
	 */
	function showErrors( errorMap ) {

		if ( ! state.step ) {
			return 0;
		}

		// Clear before painting so a field the server no longer reports doesn't
		// keep last round's message.
		clearErrors();

		let firstOffender = null;
		let painted = 0;

		Object.keys( errorMap ).forEach( function( key ) {

			const block = state.step.querySelector( '.sc-regform__attendee[data-attendee-key="' + key + '"]' );

			if ( ! block ) {
				// eslint-disable-next-line no-console
				console.warn( 'Sugar Calendar: no attendee block for error key "' + key + '".' );

				return;
			}

			// Auto-open a collapsed attendee that has an error.
			RF.setExpanded( block, true );

			Object.keys( errorMap[ key ] ).forEach( function( fieldId ) {

				const field = block.querySelector( '.sc-regform__field[data-field-id="' + fieldId + '"]' );

				if ( ! field ) {
					// eslint-disable-next-line no-console
					console.warn( 'Sugar Calendar: no field "' + fieldId + '" for attendee "' + key + '".' );

					return;
				}

				const code = errorMap[ key ][ fieldId ];

				field.querySelector( '.sc-regform__error' ).textContent = RF.strings[ code ] || RF.strings.generic || '';

				painted += 1;

				const control = field.querySelector( 'input, select, textarea' );

				if ( control ) {
					control.setAttribute( 'aria-invalid', 'true' );

					if ( ! firstOffender ) {
						firstOffender = control;
					}
				}
			} );
		} );

		if ( firstOffender ) {
			firstOffender.scrollIntoView( { block: 'center' } );
			firstOffender.focus();
		}

		return painted;
	}

	/**
	 * Clear every painted error.
	 *
	 * @since 3.13.0
	 */
	function clearErrors() {

		state.step.querySelectorAll( '.sc-regform__error' ).forEach( function( node ) {

			node.textContent = '';
		} );

		state.step.querySelectorAll( '[aria-invalid]' ).forEach( function( control ) {

			control.removeAttribute( 'aria-invalid' );
		} );
	}

	RF.showErrors = showErrors;
	RF.clearErrors = clearErrors;
	RF.onFieldChanged = onFieldChanged;

}() );

/* ---- assets/js/features/registration-form/frontend/parts/step.js ---- */

/**
 * Registration Form — the step transition and in-step navigation.
 *
 * Intercepts the host CTA, swaps the host's own panels for the step and back,
 * sets the CTA label, and drives the attendee accordion inside the step.
 *
 * @since 3.13.0
 */
( function() {

	'use strict';

	const RF = window.SugarCalendar.RegistrationFormInternal;
	const state = RF.state;

	/**
	 * Intercept the host CTA.
	 *
	 * A host the adapter reports as inactive (RSVP Not Going), or one whose own
	 * step-one fields aren't valid yet, falls through untouched so the host's
	 * handler can paint its errors while its fields are still visible.
	 *
	 * @since 3.13.0
	 *
	 * @param {MouseEvent} event The click event.
	 */
	function onCtaClick( event ) {

		if ( ! state.stepOneActive ) {

			// The step is already showing, so this click is the real submit.
			// Ticketing serializes the checkout form itself and never calls the
			// exported serialize(), so this is the only place its leaf count can
			// be refreshed. A stale reveal-time count either hides a real
			// max_input_vars truncation (reads 0 on the first pass) or reports a
			// truncation the buyer cannot clear (after Back -> Continue).
			RF.stampLeafCount();

			return;
		}

		if ( ! state.adapter.isActive() ) {
			return;
		}

		if ( state.adapter.hostFieldsValid && ! state.adapter.hostFieldsValid() ) {
			return;
		}

		// Re-stamp here rather than watching quantity: the attendee rows may have
		// changed since the modal opened. Suppress the click only once the step
		// rendered, otherwise a throw would leave the CTA permanently inert with
		// no way to reach the host's own submit.
		try {
			RF.stamp();
			revealStep();
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.error( 'Sugar Calendar: the registration step failed to render; letting the click through to the host.', e );

			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();
	}

	/**
	 * Show the registration step.
	 *
	 * @since 3.13.0
	 */
	function revealStep() {

		state.stepOneActive = false;

		setStepOneVisible( false );
		state.step.hidden = false;
		RF.setControlsEnabled( true );

		state.ctaButton.textContent = state.originalCtaLabel;

		const first = state.step.querySelector( '.sc-regform__attendee input, .sc-regform__attendee select, .sc-regform__attendee textarea' );

		if ( first ) {
			first.focus();
		}
	}

	/**
	 * Go back to the host's own step.
	 *
	 * @since 3.13.0
	 */
	function resetToStepOne() {

		// The static after-checkout host sets `step` with no `adapter`, and reset()
		// is exported for hosts to call on their error paths, so without this guard
		// setStepOneVisible() would hit mainColumn() on a null adapter.
		if ( ! state.adapter ) {
			return;
		}

		state.stepOneActive = true;

		setStepOneVisible( true );
		state.step.hidden = true;
		RF.setControlsEnabled( false );

		syncCtaLabel();
	}

	/**
	 * Swap the host's own panels in the main column for the step, and back.
	 *
	 * Only the panels in that column stand down; the summary in the neighbouring
	 * column stays on screen. Toggles a state class rather than writing inline
	 * display, because the ticketing payment fieldset carries its own
	 * `style="display:none"` on a free event and restoring display would reveal a
	 * card field that must stay hidden.
	 *
	 * @since 3.13.0
	 *
	 * @param {boolean} visible Whether the host's own panels show.
	 */
	function setStepOneVisible( visible ) {

		const main = state.adapter.mainColumn();

		if ( main ) {
			main.classList.toggle( 'sc-regform-step-active', ! visible );
		}

		if ( state.backButton ) {
			state.backButton.hidden = visible;
		}
	}

	/**
	 * Apply or clear the server-supplied CTA label, per the adapter's activation
	 * state. An inactive host (RSVP before Going is chosen) keeps its own label.
	 *
	 * The label travels on the step root because the ticketing CTA is printed
	 * before any hook and has no attribute filter for PHP to use.
	 *
	 * @since 3.13.0
	 */
	function syncCtaLabel() {

		if ( ! state.adapter.isActive() ) {
			state.ctaButton.textContent = state.originalCtaLabel;

			return;
		}

		const label = state.step.dataset.ctaLabel;

		if ( label ) {
			state.ctaButton.textContent = label;
		}
	}

	/**
	 * Handle clicks inside the step.
	 *
	 * @since 3.13.0
	 *
	 * @param {MouseEvent} event The click event.
	 */
	function onStepClick( event ) {

		const header = event.target.closest( '.sc-regform__attendee-header' );

		if ( header ) {
			const block = header.closest( '.sc-regform__attendee' );

			RF.setExpanded( block, block.dataset.expanded !== 'true' );

			return;
		}

		if ( event.target.closest( '.sc-regform__next' ) ) {
			goToNextAttendee( event.target.closest( '.sc-regform__attendee' ) );
		}
	}

	/**
	 * Collapse this attendee and open the next.
	 *
	 * @since 3.13.0
	 *
	 * @param {HTMLElement} block The current attendee block.
	 */
	function goToNextAttendee( block ) {

		const next = block.nextElementSibling;

		RF.setExpanded( block, false );

		if ( ! next ) {
			return;
		}

		RF.setExpanded( next, true );
		next.querySelector( '.sc-regform__attendee-header' ).scrollIntoView( { block: 'nearest' } );
	}

	RF.onCtaClick = onCtaClick;
	RF.resetToStepOne = resetToStepOne;
	RF.syncCtaLabel = syncCtaLabel;
	RF.onStepClick = onStepClick;

}() );

/* ---- assets/js/features/registration-form/frontend/parts/entry.js ---- */

/* global jQuery */

/**
 * Registration Form — front-end collection step (entry point).
 *
 * Renders one instance of the server-rendered <template> per applicable
 * attendee, intercepts the host's primary CTA to insert itself as a step, and
 * paints validation errors returned by the host's own AJAX validation.
 *
 * A host-agnostic core plus one thin adapter per host (ticketing's checkout
 * modal, RSVP's response modal); host selectors live only in their own adapter.
 *
 * Boot and public API only. The implementation lives in the sibling scripts in
 * this directory, which share state through
 * `window.SugarCalendar.RegistrationFormInternal` (see shared.js) since separate
 * IIFEs have no common scope. `gulp js:bundles` concatenates them into
 * `frontend/general.js` in gulpfile-config.json order, this file last.
 *
 * @since 3.13.0
 */
( function( $ ) {

	'use strict';

	const RF = window.SugarCalendar.RegistrationFormInternal;
	const state = RF.state;

	/**
	 * Boot.
	 *
	 * Picks the adapter by the step root's data-context rather than array order,
	 * and returns silently when no host rendered a root. Two roots on one page
	 * shouldn't happen (sc-rsvp forbids ticketing and RSVP on the same event) but
	 * out-of-band data could still produce it, so warn rather than fail quietly.
	 *
	 * @since 3.13.0
	 */
	function init() {

		const roots = document.querySelectorAll( '.sc-regform-step[data-mode="before"]' );

		if ( ! roots.length ) {

			// The after-checkout receipt host (Renderer::render_static()) has no CTA
			// to intercept and no <template> to stamp, but after.js still needs
			// `step` set for serialize()/showErrors() to work against.
			const staticRoot = document.querySelector( '.sc-regform-static[data-mode="after"]' );

			if ( staticRoot ) {
				state.step = staticRoot;

				// The static host still needs the collapse toggle, the Next Attendee
				// advance and stale-error clearing. All three handlers are pure
				// functions of event.target and need no adapter, and both branches
				// onStepClick can reach here — the attendee header and
				// '.sc-regform__next' — are rendered by render_static_attendee().
				state.step.addEventListener( 'click', RF.onStepClick );
				state.step.addEventListener( 'input', RF.onFieldChanged );
				state.step.addEventListener( 'change', RF.onFieldChanged );
			}

			return;
		}

		if ( roots.length > 1 ) {
			// eslint-disable-next-line no-console
			console.warn( 'Sugar Calendar: more than one registration-form step root found on the page; using the first one.' );
		}

		state.step = roots[ 0 ];
		state.adapter = RF.adapters.find( function( candidate ) {

			return candidate.slug === state.step.dataset.context;
		} ) || null;

		if ( ! state.adapter ) {
			state.step = null;

			return;
		}

		state.template = state.step.querySelector( '#sc-regform-template' );
		state.ctaButton = state.adapter.cta();

		if ( ! state.template || ! state.ctaButton ) {
			return;
		}

		state.originalCtaLabel = state.ctaButton.textContent;

		// Rendered into the host's own footer, so it is a sibling of the primary CTA
		// rather than a descendant of the step. Only ticketing has that seam; for
		// RSVP this stays null and every guarded use below is a no-op.
		state.backButton = document.querySelector( '.sc-regform__back' );

		// Capture phase: runs before every bubble-phase jQuery handler, whichever
		// plugin bound it and in whatever order.
		state.ctaButton.addEventListener( 'click', RF.onCtaClick, true );

		if ( state.backButton ) {
			state.backButton.addEventListener( 'click', RF.resetToStepOne );
		}

		state.step.addEventListener( 'click', RF.onStepClick );
		state.step.addEventListener( 'input', RF.onFieldChanged );
		state.step.addEventListener( 'change', RF.onFieldChanged );

		// Bootstrap 4 modal events are jQuery-only.
		state.adapter.modal().on( state.adapter.modalEvent, function() {

			RF.resetToStepOne();

			if ( state.adapter.isActive() ) {
				RF.stamp();
			}
		} );

		if ( state.adapter.bindAjaxErrors ) {
			state.adapter.bindAjaxErrors();
		}

		if ( state.adapter.watchGatewayErrors ) {
			state.adapter.watchGatewayErrors();
		}

		if ( state.adapter.watchAttendees ) {
			state.adapter.watchAttendees( RF.stamp );
		}

		RF.syncCtaLabel();
		RF.stamp();
	}

	/**
	 * Serialize the step's answers.
	 *
	 * Used by hosts that build their POST by hand instead of serializing the form.
	 *
	 * Append the result to the request body; do not assign it to a property. The
	 * field names already begin at `registration[...]` and the fragment carries the
	 * top-level leaf count too, so assigning it to `data.registration` sends one
	 * doubly-encoded scalar the server's is_array() check silently discards.
	 *
	 * Returns an empty string before the step is revealed: its controls are
	 * disabled until then and jQuery.serialize() skips disabled controls.
	 *
	 * @since 3.13.0
	 *
	 * @return {string} URL-encoded answer fields, ready to append to a body.
	 */
	function serialize() {

		if ( ! state.step ) {
			return '';
		}

		// The static after-checkout host never calls setControlsEnabled(), so
		// without this recount its PHP-stamped count stays optimistic (rows x
		// fields) and an unchecked checkbox group reads as a truncated submission.
		RF.stampLeafCount();

		return $( state.step ).find( 'input, select, textarea' ).serialize();
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}

	window.SugarCalendar = window.SugarCalendar || {};
	window.SugarCalendar.RegistrationForm = {
		showErrors: RF.showErrors,
		serialize: serialize,

		/*
		 * For hosts whose own error response points at a panel the step is hiding
		 * (RSVP's main_attendee errors render inside "Your Details"). Without a way
		 * back the visitor cannot reach the field carrying the error.
		 */
		reset: RF.resetToStepOne
	};

}( jQuery ) );
