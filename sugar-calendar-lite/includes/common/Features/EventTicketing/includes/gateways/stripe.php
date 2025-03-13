<?php
/**
 * Stripe API handlers
 */

namespace Sugar_Calendar\AddOn\Ticketing\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\Helpers;
use WP_Error;

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

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 3.6.0
	 */
	private function hooks() {

		add_action( 'wp_ajax_sc_et_stripe_fetch_data', [ $this, 'ajax_fetch_data' ] );
		add_action( 'wp_ajax_nopriv_sc_et_stripe_fetch_data', [ $this, 'ajax_fetch_data' ] );

		// Redirect hook.
		add_action( 'sc_et_checkout_pre_redirect', [ $this, 'after_complete' ], 10, 2 );
	}

	/**
	 * Fetch data with the Stripe Payment Intent.
	 *
	 * @since 3.6.0
	 */
	public function ajax_fetch_data() {

		check_ajax_referer( Checkout::NONCE_KEY, 'nonce' );

		if (
			empty( $_POST['event_id'] ) ||
			empty( $_POST['quantity'] ) ||
			empty( $_POST['email'] )
		) {
			wp_send_json_error(
				[
					'error_msg' => esc_html__( 'Missing data.', 'sugar-calendar-lite' ),
				]
			);
		}

		$event_id = absint( $_POST['event_id'] );
		$quantity = absint( $_POST['quantity'] );

		if ( empty( $event_id ) || empty( $quantity ) ) {
			wp_send_json_error(
				[
					'error_msg' => esc_html__( 'Invalid data.', 'sugar-calendar-lite' ),
				]
			);
		}

		$event = sugar_calendar_get_event( $event_id );

		if ( empty( $event ) ) {
			wp_send_json_error(
				[
					'error_msg' => esc_html__( 'Event not found!', 'sugar-calendar-lite' ),
				]
			);
		}

		$name = '';

		if ( ! empty( $_POST['first_name'] ) ) {
			$name = sanitize_text_field( $_POST['first_name'] );
		}

		if ( ! empty( $_POST['last_name'] ) ) {
			$name .= ' ' . sanitize_text_field( $_POST['last_name'] );
		}

		$data = $this->create_payment_intent(
			$event,
			$this->get_amount( $event_id, $quantity ),
			[
				'name'  => trim( $name ),
				'email' => sanitize_email( $_POST['email'] ),
			]
		);

		if ( is_wp_error( $data ) ) {
			wp_send_json_error(
				[
					'error_msg' => sprintf(
						/* translators: %s: Error code. */
						__( 'Error: %s', 'sugar-calendar-lite' ),
						$data->get_error_code()
					),
				]
			);
		}

		wp_send_json_success( $data );
	}

	/**
	 * Contact the Stripe API and attempt to create a Payment Intent.
	 *
	 * @since 1.0.0
	 * @since 3.3.0 Add nonce check and refactor.
	 * @since 3.6.0 Refactor for Payment Element.
	 *
	 * @param \Sugar_Calendar\Event $event          The Event object.
	 * @param int                   $amount         The amount to charge.
	 * @param array                 $customer       Array containing customer email and name.
	 */
	public function create_payment_intent( $event, $amount, $customer ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$is_sandbox = Functions\is_sandbox();

		if ( empty( Functions\get_stripe_secret_key() ) && ! $is_sandbox ) {
			return new WP_Error(
				'sc_et_create_payment_intent_no_secret_key',
				__( 'No Stripe API key found.', 'sugar-calendar-lite' )
			);
		}

		// Load the Stripe SDK.
		$this->load_sdk();

		/**
		 * Filter the statement descriptor for the Stripe payment.
		 *
		 * @since 3.3.0
		 *
		 * @param string $statement_descriptor The statement descriptor.
		 */
		$statement   = apply_filters( 'sc_et_stripe_statement_descriptor', esc_html__( 'Event Tickets', 'sugar-calendar-lite' ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		$description = sprintf(
			/* translators: %1$s: Event title, %2$s: Event date. */
			esc_html__( 'Event ticket for %1$s on %2$s', 'sugar-calendar-lite' ),
			$event->title,
			$event->format_date(
				sc_get_date_format() . ' ' . sc_get_time_format(),
				$event->start
			)
		);

		$args = [
			'amount'                    => $amount,
			'automatic_payment_methods' => [
				'enabled'         => true,
				'allow_redirects' => 'never',
			],
			'currency'                  => strtolower( Functions\get_currency() ),
			'description'               => $description,
			'metadata'                  => [
				'event_id' => $event->id,
			],
			'statement_descriptor'      => $statement,
		];

		if ( ! Helpers::is_license_valid() || ! sugar_calendar()->is_pro() || Helpers::is_application_fee_supported() ) {
			$args['application_fee_amount'] = (int) round( $amount * 0.03, 2 );
		}

		$stripe_customer  = $this->get_customer( $customer['email'], $customer['name'] );
		$args['customer'] = $stripe_customer->id;

		// phpcs:ignore WPForms.PHP.BackSlash.UseShortSyntax
		try {
			$payment_intent = \Stripe\PaymentIntent::create( $args );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'sc_et_create_payment_intent_error',
				$e->getMessage()
			);
		}

		return [
			'payment_intent_client_secret' => $payment_intent->client_secret,
			'amount'                       => $payment_intent->amount,
			'currency'                     => $payment_intent->currency,
			'is_sandbox'                   => $is_sandbox,
		];
	}

	/**
	 * Load up the Stripe SDK.
	 *
	 * @since 1.0.0
	 */
	public function load_sdk() {

		\Stripe\Stripe::setAppInfo(
			'Sugar Calendar - Event Tickets',
			SC_PLUGIN_VERSION,
			'https://sugarcalendar.com',
			'pp_partner_HxGcEqfw4pwJeS'
		);

		\Stripe\Stripe::setApiKey( Functions\get_stripe_secret_key() );
		\Stripe\Stripe::setApiVersion( '2020-08-27' );
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
	 * @since 3.6.0 Added the `$name` parameter.
	 *
	 * @param string $email The customer email.
	 * @param string $name  The customer name.
	 *
	 * @return object
	 */
	public function get_customer( $email = '', $name = '' ) {

		$customers = \Stripe\Customer::all(
			[
				'email' => $email,
				'limit' => 3,
			]
		);

		if ( ! empty( $customers->data ) ) {

			$customer = $customers->data[0];
		} else {

			$name     = ! empty( $name ) ? $name : sanitize_text_field( $_POST['name'] );
			$customer = \Stripe\Customer::create(
				[
					'email' => $email,
					'name'  => $name,
				]
			);
		}

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
