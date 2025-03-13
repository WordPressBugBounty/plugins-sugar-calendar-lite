/* global sc_event_ticket_vars */

// Set this to true by gateway JS when checkout payment data is valid
window.sc_checkout_valid = false;

jQuery( document ).ready( function( $ ) {
	'use strict';

	const // Elements.
		$modal = $( '#sc-event-ticketing-modal' ),
		$quantityField = $( '#sc-event-ticketing-quantity' ),
		$eventIdField = $( 'input#sc_et_event_id' ),
		$checkoutForm = $( "#sc-event-ticketing-checkout" ),
		$attendeeListItem = $( '.sc-event-ticketing-attendee:first' ).clone();

	$modal.modal( 'handleUpdate' );

	$modal.on( 'show.bs.modal', function () {

		// Ticket quantity.
		const qty = parseInt( $quantityField.val() );

		// Remove all errors.
		$( '.sc-et-error', $checkoutForm ).remove();

		// Get initial price.
		$.ajax({
			type: "POST",
			url: sc_event_ticket_vars.ajaxurl,
			data: {
				action : 'sc_et_get_price',
				event_id: $eventIdField.val(),
				quantity: qty
			},
			dataType: 'json',
			success: function( response ) {
				$( '#sc-event-ticketing-checkout-total' ).html( response.data.data.price );
			}
		});

		$( '#sc-event-ticketing-quantity-span' ).text( qty );

		if ( qty > 1 ) {

			$( '#sc_et_quantity' ).val( qty );

			// Attendee index count.
			let attendeeIndex = 1;

			// Loop through all attendees except the first one.
			// If any of the input fields are not empty, do not remove the attendee.
			// If we're passed the quantity limit, remove the remaining attendees.
			$( '.sc-event-ticketing-attendee' )
				.not( ':first' )
				.each( function() {

					attendeeIndex++;

					// If we're passed the quantity limit, remove the remaining attendees.
					if ( attendeeIndex > qty ) {

						$( this ).remove();

						return;
					}

					// If we're here, we're not passed the quantity limit.
					// Check if any of the input fields are not empty.
					const shouldPreserve = $( this )
						.find( 'input' )
						.toArray()
						.some( input => $( input ).val() !== '' );

					// If any of the input fields are not empty, do not remove the attendee.
					if ( shouldPreserve ) {

						// Set the attendee key.
						$( this ).attr( 'attendee-key', attendeeIndex );

						// Set the attendee data-key.
						$( this ).attr( 'data-key', attendeeIndex );

						return;
					}

					$( this ).remove();
				});

			// Loop starts from the number of attendees preserved in the list.
			for (
				let i = $( '.sc-event-ticketing-attendee' ).length;
				i < qty;
				i++
			) {

				// Prepare the attendee list item clone.
				const $attendeeListItemClone = $attendeeListItem.clone();

				// Remove default inactive class.
				$attendeeListItemClone
					.find( '.sc-event-ticketing-remove-attendee' )
					.removeClass( 'sc-event-ticketing-control-inactive' );

				// Setup fields with clear inputs.
				setup_attendee_input_attributes(
					$attendeeListItemClone,
					i + 1,
					true
				);

				// Add the clone to the attendee list.
				$attendeeListItemClone.appendTo( '#sc-event-ticketing-modal-attendee-list' );
			}
		} else {

			// Remove all attendees.
			$( '.sc-event-ticketing-attendee' ).not( ':first' ).remove();
		}

		refresh_attendee_informations();
	});

	// Focus on first name field after modal is fully shown.
	$modal.on( 'shown.bs.modal', function () {
		$( '#sc-event-ticketing-first-name' ).focus();
	});

	/**
	 * Setup attendee input id and name attributes.
	 *
	 * @since 3.6.0
	 *
	 * @param {jQuery} $attendee Attendee row.
	 * @param {number} index Attendee index.
	 * @param {boolean} clearInputs Clear inputs values.
	 */
	function setup_attendee_input_attributes( $attendee, index, clearInputs = false ) {

		const intIndex = parseInt( index );
		const $attendeeDOM = $( $attendee );

		if ( clearInputs ) {
			$attendeeDOM.find( 'input, select, textarea' ).val( '' );
		}

		// Setup input id and name attributes.
		$attendeeDOM.find( 'input, select, textarea' ).each(function() {
			let name = $( this ).attr( 'name' ),
				id   = $( this ).attr( 'id' );

			if ( name ) {
				name = name.replace( /\[(\d+)\]/, '[' + intIndex + ']' );
				$( this ).attr( 'name', name );
			}

			if ( typeof id !== 'undefined' ) {
				id = id.replace( /(\d+)/, intIndex );
				$( this ).attr( 'id', id );
			}
		});

		// Update label.
		$attendeeDOM.find( '.sc-event-ticketing-attendee__input-group__attendee-label' ).text( `Attendee ${intIndex}` );

		// Update key.
		$attendeeDOM.attr( 'attendee-key', intIndex );

		// Update data-key.
		$attendeeDOM.attr( 'data-key', intIndex );
	}

	/**
	 * Refresh the attendee informations.
	 *
	 * @since 3.1.0
	 * @since 3.6.0 Refresh all attendees informations.
	 */
	function refresh_attendee_informations() {

		let attendee_count = 1;

		// Loop each attendee.
		$( '.sc-event-ticketing-attendee' ).each( function() {

			// Refresh fields but preserve inputs values.
			setup_attendee_input_attributes( $( this ), attendee_count );

			attendee_count++;
		});
	}

	$( '#sc-event-ticketing-modal-attendee-list' ).on(
		'click',
		'.sc-event-ticketing-add-attendee',
		function() {

			let // Elements.
				$current_attendee_row = $( this ).parents( '.sc-event-ticketing-attendee' ),
				$insertAfterElement = $current_attendee_row.next( '.sc-et-error' ).length
					? $current_attendee_row.next( '.sc-et-error' )
					: $current_attendee_row;

			var qty = $( '.sc-event-ticketing-attendee' ).length,
			max = $quantityField.attr( 'max' );

			if ( qty >= max ) {
				alert( sc_event_ticket_vars.qty_limit_reached );
				return;
			}

			if ( qty === 1 ) {
				$( '.sc-event-ticketing-attendee-controls-group' ).find( '.sc-event-ticketing-remove-attendee' )
					.removeClass( 'sc-event-ticketing-control-inactive' );
			}

			var clone = $( '.sc-event-ticketing-attendee:last' ).clone(),
				key   = clone.data( 'key' );

			key += 1;

			clone.attr( 'data-key', key );
			clone.find( 'input, select, textarea' ).val( '' ).each(function() {
				var name = $( this ).attr( 'name' ),
					id   = $( this ).attr( 'id' );

				if ( name ) {
					name = name.replace( /\[(\d+)\]/, '[' + parseInt( key ) + ']' );
					$( this ).attr( 'name', name );
				}

				if ( typeof id !== 'undefined' ) {
					id = id.replace( /(\d+)/, parseInt( key ) );
					$( this ).attr( 'id', id );
				}
			});

			clone.insertAfter( $insertAfterElement )
				.find( 'input, textarea, select' )
				.filter( ':visible' ).eq(0).focus();

			refresh_attendee_informations();

			$( '#sc_et_quantity, #sc-event-ticketing-quantity' ).val( qty + 1 );
			$( '#sc-event-ticketing-quantity-span' ).text( qty + 1 );

			// Get new price
			$.ajax({
				type: "POST",
				url: sc_event_ticket_vars.ajaxurl,
				data: {
					action : 'sc_et_get_price',
					event_id: $eventIdField.val(),
					quantity: $( 'input#sc_et_quantity' ).val()
				},
				dataType: 'json',
				success: function( response ) {
					$( '#sc-event-ticketing-checkout-total' ).html( response.data.data.price );
				}
			});
		}
	);

	$( 'body' ).on( 'click', '.sc-event-ticketing-remove-attendee', function() {

		const $attendee = $( this ).closest( '.sc-event-ticketing-attendee' );

		let attendee_count = $( '.sc-event-ticketing-attendee' ).length;

		if ( attendee_count > 1 ) {

			// Delete next if it's an error.
			$attendee.next( '.sc-et-error' ).remove();

			// Delete the parent attendee row.
			$attendee.remove();

			if ( attendee_count === 2 ) {
				$( '.sc-event-ticketing-attendee-controls-group' ).find( '.sc-event-ticketing-remove-attendee' )
					.addClass( 'sc-event-ticketing-control-inactive' );
			}

		} else {

			// Clear the input fields
			$( 'input', '.sc-event-ticketing-attendee' ).val( '' );
		}

		refresh_attendee_informations();

		var qty = $( '.sc-event-ticketing-attendee' ).length;

		$( '#sc_et_quantity, #sc-event-ticketing-quantity' ).val( qty );
		$( '#sc-event-ticketing-quantity-span' ).text( qty );

		// Get new price
		$.ajax({
			type: "POST",
			url: sc_event_ticket_vars.ajaxurl,
			data: {
				action : 'sc_et_get_price',
				event_id: $( 'input#sc_et_event_id' ).val(),
				quantity: qty
			},
			dataType: 'json',
			success: function( response ) {
				$( '#sc-event-ticketing-checkout-total' ).html( response.data.data.price );
			}
		});
	});

	$( '#sc-event-ticketing-copy-billing-attendee' ).on( 'click', function (event) {

		event.preventDefault();

		$( 'input[name="attendees[1][first_name]"]', '.sc-event-ticketing-attendee' ).val( $( '#sc-event-ticketing-first-name' ).val() );
		$( 'input[name="attendees[1][last_name]"]',  '.sc-event-ticketing-attendee' ).val( $( '#sc-event-ticketing-last-name'  ).val() );
		$( 'input[name="attendees[1][email]"]',      '.sc-event-ticketing-attendee' ).val( $( '#sc-event-ticketing-email'      ).val() );
	});

	$( '#sc-event-ticketing-cancel' ).on( 'click', function () {
		$( '#sc-event-ticketing-modal .sc-et-spinner-border' ).hide();
	});

	$( '#sc-event-ticketing-purchase' ).on( 'click', function () {

		$checkoutForm.first().trigger( "submit" );
	});

	$checkoutForm.on( 'submit', function (event) {

		event.preventDefault();

		let form = $( this );

		$( '#sc-event-ticketing-modal .sc-et-spinner-border' ).show();

		$( '.sc-et-error', form ).remove();

		$.ajax({
			type:     'POST',
			url:      sc_event_ticket_vars.ajaxurl,
			dataType: 'json',
			data: {
				action: 'sc_et_validate_checkout',
				data:   $( this ).serialize()
			},
			success: function( response ) {

				if ( response.success ) {
					$( 'body' ).trigger( 'sc_et_gateway_ajax', response );
				} else {
					// Validation failed, display errors
					$( '#sc-event-ticketing-modal .sc-et-spinner-border' ).hide();

					$.each( response.data.errors, function( index, error ) {
						$( '<div class="sc-et-error alert alert-danger" role="alert">' + error.msg + '</div>' ).insertAfter( error.selector );
					});
				}
			}

		}).done(function() {

		}).fail(function() {

		}).always(function() {

		});
	});

	$quantityField.on('change', function() {

		const $scWooBtn = $( '#sc-event-ticketing-buy-button-woocommerce' );

		let link = $scWooBtn.attr( 'href' );

		// Modify WooCommerce button link if it's available.
		if ( $scWooBtn.length ) {
			link = $scWooBtn.attr( 'href').replace(/[0-9]+(?!.*[0-9])/, $(this).val() );
		}

		$scWooBtn.attr( 'href', link );
	});
});
