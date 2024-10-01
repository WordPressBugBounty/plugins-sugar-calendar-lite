<?php
/**
 * Stripe API handlers
 */

namespace Sugar_Calendar\AddOn\Ticketing\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
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
	 */
	public function create_payment_intent() {

		// Load the Stripe SDK
		$this->load_sdk();

		// Get the Event ID
		$event_id = ! empty( $_POST['event_id'] )
			? absint( $_POST['event_id'] )
			: 0;

		// Get the quantity
		$quantity = ! empty( $_POST['quantity'] )
			? absint( $_POST['quantity'] )
			: 0;

		// Get the Email
		$email = ! empty( $_POST['email'] )
			? sanitize_email( $_POST['email'] )
			: '';

		// Get the customer from Stripe
		$customer = Functions\get_stripe_secret_key()
			? $this->get_customer( $email )
			: false;

		// Get our start date / time
		$event = sugar_calendar_get_event( $event_id );

		// Format
		if ( ! empty( $event ) ) {
			$format      = sc_get_date_format() . ' ' . sc_get_time_format();
			$datetime    = $event->format_date( $format, $event->start );
			$title       = $event->title;
			$description = sprintf( esc_html__( 'Event ticket for %s on %s', 'sugar-calendar' ), $title, $datetime );

			// Event is missing
		} else {
			$description = esc_html__( 'No event found for this ticket', 'sugar-calendar' );
		}

		// Get payment intent info
		$amount    = $this->get_amount( $event_id, $quantity );
		$currency  = strtolower( Functions\get_currency() );
		$statement = apply_filters( 'sc_et_stripe_statement_descriptor', esc_html__( 'Event Tickets', 'sugar-calendar' ) );

		// Stripe key exists, so create an intent via the API
		if ( ! empty( $event ) && ! empty( $customer->id ) && Functions\get_stripe_secret_key() ) {

			$args = [
				'amount'               => $amount,
				'currency'             => $currency,
				'statement_descriptor' => $statement,
				'receipt_email'        => $email,
				'customer'             => $customer->id,
				'description'          => $description,
				'metadata'             => [
					'event_id' => $event_id,
				],
			];

			if ( ! Helpers::is_license_valid() || ! sugar_calendar()->is_pro() || Helpers::is_application_fee_supported() ) {
				$args['application_fee_amount'] = (int) round( $amount * 0.03, 2 );
			}

			// Send payment intent to Stripe API
			$intent = \Stripe\PaymentIntent::create( $args );

			// Send the response as success
			wp_send_json_success( $intent );
		}

		// Always succeed if sandboxed
		if ( Functions\is_sandbox() ) {
			wp_send_json_success( [ 'sandbox' => true ] );

			// Fail if not sandboxed
		} else {
			wp_send_json_error( [ 'sandbox' => false ] );
		}
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
	 *
	 * @since 1.0.0
	 *
	 * @param int   $order_id
	 * @param array $order_data
	 */
	public function after_complete( $order_id = 0, $order_data = [] ) {

		// Bail if no Stripe connection
		if ( ! Functions\get_stripe_secret_key() ) {
			return;
		}

		// Load the SDK
		$this->load_sdk();

		// Store order ID in Stripe meta data
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
