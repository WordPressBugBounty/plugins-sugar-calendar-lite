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
					itemSelectText: sc_admin_ticketing.strings.select_event,
					placeholderValue: sc_admin_ticketing.strings.select_event,
					removeItemButton: true,
					removeItems: true,
					searchFloor: 0,
					searchPlaceholderValue: sc_admin_ticketing.strings.select_event,
					shouldSort: false,
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
						action: 'fetch_ticketing_events_choices',
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
