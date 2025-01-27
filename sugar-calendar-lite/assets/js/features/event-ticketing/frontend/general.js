/* global sc_event_ticket_vars */

// Set this to true by gateway JS when checkout payment data is valid
window.sc_checkout_valid = false;

jQuery( document ).ready( function( $ ) {
	'use strict';

	$( '#sc-event-ticketing-modal' ).modal( 'handleUpdate' );

	$( '#sc-event-ticketing-modal' ).on( 'show.bs.modal', function () {
		var modal = $( this ),
			qty   = $( '#sc-event-ticketing-quantity' ).val(),
			max   = $( '#sc-event-ticketing-quantity' ).attr( 'max' );

		// Get initial price
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

		$( '#sc-event-ticketing-quantity-span' ).text( qty );

		if ( qty > 1 && qty > $( '.sc-event-ticketing-attendee' ).length ) {

			$( '.sc-event-ticketing-attendee-controls-group' ).find( '.sc-event-ticketing-remove-attendee' )
				.removeClass( 'sc-event-ticketing-control-inactive' );

			$( '#sc_et_quantity' ).val( qty );

			var i,
				clone = $( '.sc-event-ticketing-attendee:first' ).clone();

			$( '.sc-event-ticketing-attendee' ).not( ':first' ).remove();

			for ( i = 2; i <= max; i++ ) {

				clone.find( 'input, select, textarea' ).val( '' ).each(function() {
					var name = $( this ).attr( 'name' ),
						id   = $( this ).attr( 'id' );

					if ( name ) {
						name = name.replace( /\[(\d+)\]/, '[' + parseInt( i ) + ']' );
						$( this ).attr( 'name', name );
					}

					if ( typeof id !== 'undefined' ) {
						id = id.replace( /(\d+)/, parseInt( i ) );
						$( this ).attr( 'id', id );
					}
				});

				clone.data( 'key', i );
				clone.appendTo( '#sc-event-ticketing-modal-attendee-list' );
				clone.find( 'input, textarea, select' ).eq(0).focus();

				if ( i < qty ) {
					clone = clone.clone();
				}
			}
		}

		refresh_attendee_labels();
	});

	/**
	 * Refresh the attendee labels.
	 *
	 * @since 3.1.0
	 */
	function refresh_attendee_labels() {

		let attendee_count = 1;

		$( '.sc-event-ticketing-attendee__input-group__attendee-label' ).each( function() {
			$( this ).text( `Attendee ${attendee_count}`);
			attendee_count++;
		} );
	}

	$( '#sc-event-ticketing-modal-attendee-list' ).on(
		'click',
		'.sc-event-ticketing-add-attendee',
		function() {

			let $current_attendee_row = $( this ).parents( '.sc-event-ticketing-attendee' );

			var qty = $( '.sc-event-ticketing-attendee' ).length,
			max = $( '#sc-event-ticketing-quantity' ).attr( 'max' );

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

			// '.sc-event-ticketing-attendee:last'
			clone.insertAfter( $current_attendee_row )
				.find( 'input, textarea, select' )
				.filter( ':visible' ).eq(0).focus();

			refresh_attendee_labels();

			$( '#sc_et_quantity, #sc-event-ticketing-quantity' ).val( qty + 1 );
			$( '#sc-event-ticketing-quantity-span' ).text( qty + 1 );

			// Get new price
			$.ajax({
				type: "POST",
				url: sc_event_ticket_vars.ajaxurl,
				data: {
					action : 'sc_et_get_price',
					event_id: $( 'input#sc_et_event_id' ).val(),
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

		let attendee_count = $( '.sc-event-ticketing-attendee' ).length;

		if ( attendee_count > 1 ) {

			// Delete the nearest attendee row
			$( this ).closest( '.sc-event-ticketing-attendee' ).remove();

			if ( attendee_count === 2 ) {
				$( '.sc-event-ticketing-attendee-controls-group' ).find( '.sc-event-ticketing-remove-attendee' )
					.addClass( 'sc-event-ticketing-control-inactive' );
			}

		} else {

			// Clear the input fields
			$( 'input', '.sc-event-ticketing-attendee' ).val( '' );
		}

		refresh_attendee_labels();

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
		$( "#sc-event-ticketing-checkout" ).first().trigger( "submit" );
	});

	$( '#sc-event-ticketing-checkout' ).on( 'submit', function (event) {

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

					// Validation succeeded, submit for backend processing
					setTimeout( function() {
						if ( window.sc_checkout_valid ) {
							form.get(0).submit();
						}
					}, 4000 );

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

	$('#sc-event-ticketing-quantity').on('change', function() {
		var link = $('#sc-event-ticketing-buy-button-woocommerce').attr( 'href' );
		link = $('#sc-event-ticketing-buy-button-woocommerce').attr( 'href').replace(/[0-9]+(?!.*[0-9])/, $(this).val() );
		$('#sc-event-ticketing-buy-button-woocommerce').attr( 'href', link );
	});
});
