/* global sc_event_ticket_vars */
jQuery( document ).ready( function( $ ) {
	'use strict';

	// Stripe key exists
	if ( sc_event_ticket_vars.publishable_key ) {

		// Get variables
		var stripe   = Stripe( sc_event_ticket_vars.publishable_key ),
			elements = stripe.elements(),
			cardArgs = {
				style: {
					base: {
						color:      '#32325d',
						lineHeight: '1.5rem'
					}
				},
				classes: {
					base: 'form-group'
				},
				hidePostalCode: false
			};

		// Check "single-sc_event-dark" class in body.
		if ( $( 'body' ).hasClass( 'single-sc_event-dark' ) ) {
			cardArgs.style.base.iconColor = '#ffffff';
			cardArgs.style.base.color     = 'rgba(255, 255, 255, 0.85)';
		}

		var card = elements.create( 'card', cardArgs );

		// Add Stripe card elements
		card.mount( '#sc-event-ticketing-card-element' );

		// Display dynamic error messages as user types
		card.addEventListener( 'change', ({error}) => {

			// Error, so display it
			if (error) {
				$( '#sc-event-ticketing-card-errors' )
					.append( '<div class="sc-et-error alert alert-danger" role="alert">' + error.message + '</div>' );

			// No error, so remove all errors
			} else {
				$( '#sc-event-ticketing-card-errors' ).text( '' );
			}
		});

	// No Stripe key
	} else {
		var stripe, elements, card;

		// Hide the payment fieldset if no Stripe
		$( '#sc-event-ticketing-modal-payment-fieldset' ).hide();
	}

	$( 'body' ).on( 'sc_et_gateway_ajax', function() {

		const nonce = $( '#sc_et_nonce' ).val();

		if ( ! nonce ) {
			return;
		}

		// Get values
		var email     = $( '#sc-event-ticketing-email' ).val(),
			firstname = $( '#sc-event-ticketing-first-name' ).val(),
			lastname  = $( '#sc-event-ticketing-last-name' ).val(),
			eventid   = $( '#sc_et_event_id' ).val(),
			quantity  = $( '#sc_et_quantity' ).val();

		// Create Payment Intent
		$.ajax({
			type:     'POST',
			url:      sc_event_ticket_vars.ajaxurl,
			dataType: 'json',
			data: {
				action:   'sc_et_stripe_create_payment_intent',
				email:    email,
				name:     firstname + ' ' + lastname,
				event_id: eventid,
				quantity: quantity,
				nonce: nonce,
			},
			success: function( response ) {

				// All info exists to attempt payment confirmation
				if ( response.data.client_secret && stripe && card ) {

					// Confirm the Stripe payment
					stripe.confirmCardPayment(
						response.data.client_secret,
						{
							payment_method: {
								card: card,
								billing_details: {
									name:  firstname + ' ' + lastname,
									email: email
								}
							}
						}

					// Then decide what to do with the result
					).then( function( result ) {

						// Some error occurred, so display it
						if ( result.error && result.error.message ) {

							// Hide the spinner
							$( '#sc-event-ticketing-modal .spinner-border' ).hide();

							// Output the error(s)
							$( '#sc-event-ticketing-card-errors' )
								.text( '' )
								.append( '<div class="sc-et-error alert alert-danger" role="alert">' + result.error.message + '</div>' );

						// The payment has been processed!
						} else if ( 'succeeded' === result.paymentIntent.status ) {

							$( '#sc-event-ticketing-checkout' )
								.append( '<input type="hidden" name="sc_et_payment_intent" value="' + result.paymentIntent.id + '"/>' );

							$( '#sc-event-ticketing-checkout' )
								.append( '<input type="hidden" name="sc_et_payment_amount" value="' + result.paymentIntent.amount + '"/>' );

							// Trigger the checkout (saving of data)
							$( '#sc-event-ticketing-checkout' )
								.get(0)
								.submit();
						}
					});

				// Sandbox mode passes the transaction through
				} else if ( response.data.sandbox || response.data.is_free ) {
					$( '#sc-event-ticketing-checkout' )
						.get(0)
						.submit();
				}
			}
		}).done(function() {

		}).fail(function() {

		}).always(function() {

		});
	});
});
