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
					maxItemCount: 1,
					closeDropdownOnSelect: true,
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

					return res.data.filter( ( choice ) => choice.value != eventsChoicesDom.value );
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
