/* globals jQuery, Stripe, sc_event_ticket_vars, sc_event_ticket_stripe_vars */
( function ( $ ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Stripe = SugarCalendar.Stripe || {};

	SugarCalendar.Stripe = {

		/**
		 * Stripe instance.
		 *
		 * @since 3.6.0
		 */
		stripe: null,

		/**
		 * Stripe elements.
		 *
		 * @since 3.6.0
		 */
		elements: null,

		/**
		 * Stripe payment elements.
		 *
		 * @since 3.6.0
		 */
		paymentElement: null,

		/**
		 * jQuery DOM elements.
		 *
		 * @since 3.6.0
		 */
		$el: {
			$body: null,
			$modalPaymentFieldset: null,
			$checkoutForm: null,
			$errorContainer: null,
		},

		/**
		 * Initialize.
		 *
		 * @since 3.6.0
		 */
		init: function () {

			// Set elements.
			this.$el.$body = $( 'body' );
			this.$el.$modalPaymentFieldset = $( '#sc-event-ticketing-modal-payment-fieldset' );

			if ( sc_event_ticket_vars.publishable_key ) {
				this.setupStripe();
			} else {
				this.$el.$modalPaymentFieldset.hide();
			}
		},

		/**
		 * Setup Stripe.
		 *
		 * @since 3.6.0
		 */
		setupStripe: function() {

			this.stripe = Stripe( sc_event_ticket_vars.publishable_key );
			this.elements = this.stripe.elements({
				amount: parseInt( sc_event_ticket_stripe_vars.min_charge, 10 ),
				currency: sc_event_ticket_stripe_vars.currency.toLowerCase(),
				mode: 'payment',
			});

			this.paymentElement = this.elements.create( 'payment' );
			this.paymentElement.mount( '#sc-event-ticketing-card-element' );

			this.$el.$checkoutForm = $( '#sc-event-ticketing-checkout' );
			this.$el.$errorContainer = $( '#sc-event-ticketing-card-errors' );

			this.$el.$body.on( 'sc_et_gateway_ajax', this.performStripeProcess.bind( this ) );
		},

		/**
		 * Perform the Stripe process.
		 *
		 * @since 3.6.0
		 * @since 3.6.1 Support free tickets.
		 */
		performStripeProcess: function() {

			const that = this,
				nonce = $( '#sc_et_nonce' ).val();

			if ( ! nonce ) {
				return;
			}

			// Fetch the Stripe Payment Intent data.
			$.post(
				sc_event_ticket_vars.ajaxurl,
				{
					'action': 'sc_et_stripe_fetch_data',
					'nonce': nonce,
					'email': this.$el.$checkoutForm.find( '#sc-event-ticketing-email' ).val(),
					'first_name': this.$el.$checkoutForm.find( '#sc-event-ticketing-first-name' ).val(),
					'last_name': this.$el.$checkoutForm.find( '#sc-event-ticketing-last-name' ).val(),
					'event_id': this.$el.$checkoutForm.find( '#sc_et_event_id' ).val(),
					'quantity': this.$el.$checkoutForm.find( '#sc_et_quantity' ).val(),
				},
				function ( res ) {

					if ( ! res.success ) {
						that.hideSpinner();

						that.$el.$errorContainer
							.append( '<div class="sc-et-error alert alert-danger" role="alert">' + res.data.error_msg + '</div>' );

						return;
					}

					if ( res.data.is_free_event ) {
						that.$el.$checkoutForm.get(0).submit();
					} else {
						that.confirmPayment( res.data );
					}
				}
			);
		},

		/**
		 * Confirm the Stripe payment.
		 *
		 * @since 3.6.0
		 *
		 * @param {Object} fetchPaymentIntentData
		 *
		 * @return {Promise<boolean>}
		 */
		async confirmPayment( fetchPaymentIntentData ) {

			this.elements.update( {
				mode: 'payment',
				amount: fetchPaymentIntentData.amount,
				currency: fetchPaymentIntentData.currency,
			} );

			const submitResults = await this.elements.submit();

			if ( submitResults.error ) {
				this.hideSpinner();

				this.$el.$errorContainer
					.append( '<div class="sc-et-error alert alert-danger" role="alert">' + submitResults.error.message + '</div>' );

				return false;
			}

			const redirectUrl = new URL( window.location.href );

			// Confirm the payment.
			const confirmPayment = await this.stripe.confirmPayment({
				elements: this.elements,
				clientSecret: fetchPaymentIntentData.payment_intent_client_secret,
				confirmParams: {
					return_url: redirectUrl.toString()
				},
				redirect: 'if_required'
			});

			if ( confirmPayment.error ) {
				this.hideSpinner();

				this.$el.$errorContainer
					.append( '<div class="sc-et-error alert alert-danger" role="alert">' + confirmPayment.error.message + '</div>' );

				return false;
			}

			if ( confirmPayment.paymentIntent.status === 'succeeded' ) {
				this.$el.$checkoutForm.append( '<input type="hidden" name="sc_et_payment_intent" value="' + confirmPayment.paymentIntent.id + '"/>' );
				this.$el.$checkoutForm.append( '<input type="hidden" name="sc_et_payment_amount" value="' + confirmPayment.paymentIntent.amount + '"/>' );

				// Trigger the checkout (saving of data)
				this.$el.$checkoutForm.get(0).submit();

				return true;
			}
		},

		/**
		 * Hide the spinner.
		 *
		 * @since 3.6.0
		 */
		hideSpinner: function() {

			$( '#sc-event-ticketing-modal .sc-et-spinner-border' ).hide();
		},
	};

	$( document ).ready( SugarCalendar.Stripe.init.bind( SugarCalendar.Stripe ) );

	window.SugarCalendar = SugarCalendar;

} )( jQuery );
