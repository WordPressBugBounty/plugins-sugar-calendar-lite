<?php
/**
 * Stripe API handlers
 */

namespace Sugar_Calendar\AddOn\Ticketing\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
use Sugar_Calendar\Event;
use Sugar_Calendar\Helpers;

/**
 * Stripe checkout class.
 *
 * This class is responsible for abstracting the methods necessary to
 * communicate with the Stripe API.
 *
 * @since 1.0.0
 */
class Stripe extends Checkout {

	/**
	 * Initialize the Stripe checkout.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// AJAX hooks
		add_action( 'wp_ajax_sc_et_stripe_create_payment_intent', [ $this, 'create_payment_intent' ] );
		add_action( 'wp_ajax_nopriv_sc_et_stripe_create_payment_intent', [ $this, 'create_payment_intent' ] );

		// Redirect hook
		add_action( 'sc_et_checkout_pre_redirect', [ $this, 'after_complete' ], 10, 2 );
	}

	/**
	 * Load up the Stripe SDK.
	 *
	 * @since 1.0.0
	 */
	public function load_sdk() {

		// Setup the app info
		\Stripe\Stripe::setAppInfo(
			'Sugar Calendar - Event Tickets',
			SC_PLUGIN_VERSION,
			'https://sugarcalendar.com',
			'pp_partner_HxGcEqfw4pwJeS'
		);

		// Setup the API key
		\Stripe\Stripe::setApiKey( Functions\get_stripe_secret_key() );

		// Setup the API version
		\Stripe\Stripe::setApiVersion( '2020-08-27' );
	}

	/**
	 * Contact the Stripe API and attempt to create a Payment Intent.
	 *
	 * @since 1.0.0
	 * @since 3.3.0 Add nonce check and refactor.
	 */
	public function create_payment_intent() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		check_ajax_referer( Checkout::NONCE_KEY, 'nonce' );

		$event_id = ! empty( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$event    = sugar_calendar_get_event( $event_id );

		if ( empty( $event ) ) {
			wp_send_json_error(
				[
					'msg'     => esc_html__( 'No event found for this request.', 'sugar-calendar' ),
					'sandbox' => false,
				]
			);
		}

		$quantity   = ! empty( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;
		$amount     = $this->get_amount( $event_id, $quantity );
		$is_sandbox = Functions\is_sandbox();

		if ( empty( $amount ) ) {
			// Do not proceed to Stripe processing if the amount is zero (ticket is free).
			wp_send_json_success(
				[
					'is_free' => true,
					'sandbox' => $is_sandbox,
				]
			);
		}

		if ( empty( Functions\get_stripe_secret_key() ) && ! $is_sandbox ) {
			wp_send_json_error(
				[
					'msg'     => esc_html__( 'No Stripe API key found.', 'sugar-calendar' ),
					'sandbox' => false,
				]
			);
		}

		$email  = ! empty( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$intent = $this->send_payment_intent_create( $email, $event, $amount );

		if ( ! empty( $intent ) ) {
			wp_send_json_success( $intent );
		}

		// Always succeed if sandboxed.
		if ( $is_sandbox ) {
			wp_send_json_success( [ 'sandbox' => true ] );
		} else {
			wp_send_json_error( [ 'sandbox' => false ] );
		}
	}

	/**
	 * Send the Create Payment Intent request to the Stripe API.
	 *
	 * @since 3.3.0
	 *
	 * @param string $email  Customer's email address.
	 * @param Event  $event  SC Event object.
	 * @param double $amount The amount to charge.
	 *
	 * @return false|\Stripe\PaymentIntent
	 */
	private function send_payment_intent_create( $email, $event, $amount ) {

		// Load the Stripe SDK.
		$this->load_sdk();

		$customer = $this->get_customer( $email );

		if ( empty( $customer->id ) ) {
			return false;
		}

		/**
		 * Filter the statement descriptor for the Stripe payment.
		 *
		 * @since 3.3.0
		 *
		 * @param string $statement_descriptor The statement descriptor.
		 */
		$statement   = apply_filters( 'sc_et_stripe_statement_descriptor', esc_html__( 'Event Tickets', 'sugar-calendar' ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		$description = sprintf(
			/* translators: %1$s: Event title, %2$s: Event date. */
			esc_html__( 'Event ticket for %1$s on %2$s', 'sugar-calendar' ),
			$event->title,
			$event->format_date(
				sc_get_date_format() . ' ' . sc_get_time_format(),
				$event->start
			)
		);

		$args = [
			'amount'               => $amount,
			'currency'             => strtolower( Functions\get_currency() ),
			'statement_descriptor' => $statement,
			'receipt_email'        => $email,
			'customer'             => $customer->id,
			'description'          => $description,
			'metadata'             => [
				'event_id' => $event->id,
			],
		];

		if ( ! Helpers::is_license_valid() || ! sugar_calendar()->is_pro() || Helpers::is_application_fee_supported() ) {
			$args['application_fee_amount'] = (int) round( $amount * 0.03, 2 );
		}

		// phpcs:ignore WPForms.PHP.BackSlash.UseShortSyntax
		return \Stripe\PaymentIntent::create( $args );
	}

	/**
	 * Process a payment.
	 *
	 * @since 1.0.0
	 */
	public function process() {

		// Default order data array
		$order_data = [];

		// Get amount
		$amount = ! empty( $_POST['sc_et_payment_amount'] )
			? sanitize_text_field( $_POST['sc_et_payment_amount'] )
			: 0;

		// Maybe round
		if ( ! Functions\is_zero_decimal_currency() ) {
			$amount /= 100;
		}

		// Event ID
		$event_id = ! empty( $_POST['sc_et_event_id'] )
			? absint( $_POST['sc_et_event_id'] )
			: 0;

		// Event object
		$event = ! empty( $event_id )
			? sugar_calendar_get_event( $event_id )
			: false;

		// Start date
		$date = ! empty( $event->start )
			? $event->start
			: '0000-00-00 00:00:00';

		// Transaction ID
		$order_data['transaction_id'] = ! empty( $_POST['sc_et_payment_intent'] )
			? sanitize_text_field( $_POST['sc_et_payment_intent'] )
			: '';

		// Currency
		$order_data['currency'] = Functions\get_currency();

		// Status
		$order_data['status'] = 'paid';

		// Discount
		$order_data['discount_id'] = ''; // TODO

		// Totals
		$order_data['subtotal'] = $amount;
		$order_data['tax']      = ''; // TODO
		$order_data['discount'] = ''; // TODO
		$order_data['total']    = $amount;

		// Event ID & Date
		$order_data['event_id']   = $event_id;
		$order_data['event_date'] = $date;

		// Customer data
		$order_data['email']      = ! empty( $_POST['email'] )
			? sanitize_text_field( $_POST['email'] )
			: '';
		$order_data['first_name'] = ! empty( $_POST['first_name'] )
			? sanitize_text_field( $_POST['first_name'] )
			: '';
		$order_data['last_name']  = ! empty( $_POST['last_name'] )
			? sanitize_text_field( $_POST['last_name'] )
			: '';

		// Order data is complete
		parent::complete( $order_data );
	}

	/**
	 * Contact the Stripe API and attempt to retrieve a customer record.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email
	 *
	 * @return object
	 */
	public function get_customer( $email = '' ) {

		// Get customers that match this email address (up to 3)
		$customers = \Stripe\Customer::all( [
			'email' => $email,
			'limit' => 3,
		] );

		// Customers found
		if ( ! empty( $customers->data ) ) {

			// Return the first one for now - we can do more with this later
			$customer = $customers->data[0];

			// Customers not found
		} else {

			// Sanitize the posted name
			$name = sanitize_text_field( $_POST['name'] );

			// Create a new customer
			$customer = \Stripe\Customer::create( [
				'email' => $email,
				'name'  => $name,
			] );
		}

		// Return the customer
		return $customer;
	}

	/**
	 * Get the total amount of the Order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id
	 * @param int $quantity
	 *
	 * @return int
	 */
	public function get_amount( $event_id = 0, $quantity = 1 ) {

		// Quantity needs to be at least 1
		$quantity = max( 1, $quantity );

		// Sanitize the price
		$price = get_event_meta( $event_id, 'ticket_price', true );
		$price = Functions\sanitize_amount( $price );

		// Format the amount
		$amount = Functions\is_zero_decimal_currency()
			? $price
			: $price * 100;

		// Setup the price per ticket to return
		$retval = $amount * $quantity;

		// Return the amount
		return $retval;
	}

	/**
	 * Trigger after the checkout is complete.
	 *
	 * @since 1.0.0
	 * @since 3.3.0 Do not send the request to Stripe if the amount is zero.
	 *
	 * @param int   $order_id   The order ID.
	 * @param array $order_data The order data.
	 */
	public function after_complete( $order_id = 0, $order_data = [] ) {

		if (
			empty( $order_data['total'] ) ||
			(float) $order_data['total'] <= 0
		) {
			return;
		}

		if ( ! Functions\get_stripe_secret_key() ) {
			return;
		}

		$this->load_sdk();

		// Store order ID in Stripe meta data.
		\Stripe\PaymentIntent::update(
			$order_data['transaction_id'],
			[
				'metadata' => [
					'order_id' => $order_id,
				],
			]
		);
	}
}
