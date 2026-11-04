( function( $ ){

	'use strict';

	$( document ).ready( function() {

		/**
		 * The event select field.
		 *
		 * @since 3.7.0
		 *
		 * @type {Element}
		 */
		const eventsChoicesDom = document.getElementById( 'sugar-calendar-ticketing-event' );

		// Only initialize if the element exists.
		if ( eventsChoicesDom ) {
			initEventsChoices();
		}

		/**
		 * Initialize the events choices.
		 *
		 * @since 3.7.0
		 */
		function initEventsChoices() {

			const eventsChoices = new Choices(
				eventsChoicesDom,
				{
					duplicateItemsAllowed: false,
					itemSelectText: '',
					placeholderValue: sc_admin_ticketing.strings.select_event,
					removeItemButton: true,
					removeItems: true,
					searchFloor: 0,
					searchPlaceholderValue: sc_admin_ticketing.strings.select_event,
					shouldSort: false,
					// The Coupons tab takes several events (-1); Tickets and Orders take one.
					maxItemCount: parseInt( eventsChoicesDom.dataset.maxItems, 10 ) || 1,
					// Closes after a pick only with one event allowed, so Coupons stays open for the next pick.
					closeDropdownOnSelect: 'auto',
					singleModeForMultiSelect: true,
					noChoicesText: sc_admin_ticketing.strings.no_results_text,
					loadingText: sc_admin_ticketing.strings.select_event,
					callbackOnInit: function() {
						const $selected = $( '.sugar-calendar-ticketing__admin__list__actions__choices-events > .choices .choices__list--multiple' ).first();
						const $input = $( '.sugar-calendar-ticketing__admin__list__actions__choices-events .choices .choices__inner input.choices__input[type="search"]' ).first();

						if ( $selected.length && ! $selected.is( ':empty' ) ) {
							$input.attr( 'placeholder', '' );
							$input.addClass( 'sc-et-hide-carat' );
						} else {
							$input.attr( 'placeholder', sc_admin_ticketing.strings.select_event );
							$input.removeClass( 'sc-et-hide-carat' );
						}
					}
				}
			);

			eventsChoices.setChoices( async () => {
				try {
					const response = await fetchEventsData();

					if ( ! response.ok ) {
						throw new Error(`Response status: ${response.status}`);
					}

					const res = await response.json();

					if ( ! res.success ) {
						throw new Error( 'Response: Unsuccessful!' );
					}

					const selected = Array.from( eventsChoicesDom.selectedOptions, ( option ) => option.value );

					return res.data.filter( ( choice ) => ! selected.includes( String( choice.value ) ) );
				} catch ( err ) {
					console.error( err );
				}
			});

			const debouncedFetchEventsData = debounce( updateEventsChoices, 500 );

			eventsChoicesDom.addEventListener(
				'search',
				debouncedFetchEventsData
			);

			// Mirror RSVP UX: manage placeholder visibility when item added/removed.
			eventsChoicesDom.addEventListener( 'addItem', function() {
				const $input = $( '.sugar-calendar-ticketing__admin__list__actions__choices-events .choices .choices__inner input.choices__input[type="search"]' ).first();
				$input.attr( 'placeholder', '' );
				$input.addClass( 'sc-et-hide-carat' );
			} );

			eventsChoicesDom.addEventListener( 'removeItem', function() {
				const $selected = $( '.sugar-calendar-ticketing__admin__list__actions__choices-events > .choices .choices__list--multiple' ).first();
				const $input = $( '.sugar-calendar-ticketing__admin__list__actions__choices-events .choices .choices__inner input.choices__input[type="search"]' ).first();

				if ( $selected.length && $selected.is( ':empty' ) ) {
					$input.attr( 'placeholder', sc_admin_ticketing.strings.select_event );
					$input.removeClass( 'sc-et-hide-carat' );
				}
			} );

			const manyWrap = eventsChoicesDom.closest( '.sugar-calendar-ticketing__admin__list__actions__choices-events--many' );

			if ( manyWrap ) {
				initChipOverflow( manyWrap );
			}

			/**
			 * Update the Events choices.
			 *
			 * @since 3.7.0
			 *
			 * @param {*} event ChoiceJS event object.
			 */
			async function updateEventsChoices( event ) {

				try {
					const response = await fetchEventsData( event.detail.value );

					if ( ! response.ok ) {
						throw new Error(`Response status: ${response.status}`);
					}

					const res = await response.json();

					if ( ! res.success ) {
						alert( res.data );
					} else {
						eventsChoices.setChoices( res.data, 'value', 'label', true );
						eventsChoices.enable();
					}
				} catch ( err ) {
					console.error( err );
				}
			}
		}

		/**
		 * Keep the closed several-events filter on one row: the chips that do not fit hide behind one "..." tag.
		 *
		 * @since 3.17.0
		 *
		 * @param {Element} wrap The filter wrapper.
		 */
		function initChipOverflow( wrap ) {
			const parts = {
				outer: wrap.querySelector( '.choices' ),
				inner: wrap.querySelector( '.choices__inner' ),
				list: wrap.querySelector( '.choices__list--multiple' ),
				tag: document.createElement( 'span' ),
			};
			const update = () => updateChipOverflow( parts );
			let frame = 0;

			// Choices can fire addItem and removeItem before it renders the chip, so those measure on the next frame.
			const scheduleUpdate = () => {
				cancelAnimationFrame( frame );
				frame = requestAnimationFrame( update );
			};

			parts.tag.className = 'sc-et-overflow-tag';
			parts.tag.innerHTML = '<span aria-hidden="true">...</span><span class="screen-reader-text"></span>';

			// Choices sets aria-expanded before it fires these, so the update runs at once and no frame shows the wrong row.
			eventsChoicesDom.addEventListener( 'showDropdown', update );
			eventsChoicesDom.addEventListener( 'hideDropdown', update );
			eventsChoicesDom.addEventListener( 'addItem', scheduleUpdate );
			eventsChoicesDom.addEventListener( 'removeItem', scheduleUpdate );

			scheduleUpdate();
		}

		/**
		 * Hide the chips past the row end of the closed filter, keeping room for the "..." tag.
		 *
		 * @since 3.17.0
		 *
		 * @param {Object}  parts       The filter parts.
		 * @param {Element} parts.outer The Choices container, which carries aria-expanded.
		 * @param {Element} parts.inner The box the chips sit in.
		 * @param {Element} parts.list  The chip list.
		 * @param {Element} parts.tag   The "..." tag.
		 */
		function updateChipOverflow( { outer, inner, list, tag } ) {
			const chips = Array.from( list.querySelectorAll( ':scope > .choices__item' ) );

			tag.remove();
			chips.forEach( ( chip ) => chip.classList.remove( 'sc-et-overflowed', 'sc-et-truncated' ) );

			if ( chips.length < 2 || outer.getAttribute( 'aria-expanded' ) === 'true' ) {
				return;
			}

			const style = getComputedStyle( inner );
			const rowEnd = inner.getBoundingClientRect().right - parseFloat( style.borderRightWidth ) - parseFloat( style.paddingRight );
			const chipEnd = ( index ) => chips[ index ].getBoundingClientRect().right;

			if ( chipEnd( chips.length - 1 ) <= rowEnd ) {
				return;
			}

			list.insertAdjacentElement( 'afterend', tag );

			const tagRoom = ( parseFloat( style.columnGap ) || 0 ) + tag.getBoundingClientRect().width;
			let visible = chips.length - 1;

			while ( visible > 1 && chipEnd( visible - 1 ) + tagRoom > rowEnd ) {
				visible--;
			}

			chips.slice( visible ).forEach( ( chip ) => chip.classList.add( 'sc-et-overflowed' ) );
			chips[ 0 ].classList.toggle( 'sc-et-truncated', chipEnd( 0 ) + tagRoom > rowEnd );
			tag.querySelector( '.screen-reader-text' ).textContent = window.sc_admin_ticketing.strings.more_selected.replace( '%d', chips.length - visible );
		}

		/**
		 * Remotely fetch the events data.
		 *
		 * @since 3.7.0
		 *
		 * @param {string} searchTerm Optional. Search term. Default ''.
		 *
		 * @returns {Promise}
		 */
		function fetchEventsData( searchTerm = '' ) {

			return fetch(
				sc_admin_ticketing.ajaxurl,
				{
					body: new URLSearchParams({
						action: sc_admin_ticketing.action,
						nonce: sc_admin_ticketing.nonce,
						searchTerm: searchTerm,
						isAdminList: 1,
					}),
					method: "POST",
					headers: {
						"Content-Type": "application/x-www-form-urlencoded"
					}
				}
			);
		}

		/**
		 * Debouncer.
		 *
		 * @since 3.7.0
		 *
		 * @param {*}      func  Function to invoke.
		 * @param {number} delay Delay of the debouncer.
		 */
		function debounce( func, delay = 500 ) {

			let timer;

			const debouncedFunc = function( ...args ) {

				const context = this;

				clearTimeout( timer );

				timer = setTimeout(
					() => { func.apply( context, args ); },
					delay
				);
			}

			debouncedFunc.cancel = function() {

				clearTimeout( timer );
				timer = null;
			}

			return debouncedFunc;
		}
	} );
})( jQuery );
