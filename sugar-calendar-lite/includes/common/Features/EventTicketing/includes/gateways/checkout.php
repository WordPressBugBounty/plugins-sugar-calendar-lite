<?php
/**
 * Purchase API handlers
 */
namespace Sugar_Calendar\AddOn\Ticketing\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Settings;
use Sugar_Calendar\Event;
use Sugar_Calendar\Helper;

class Checkout {

	public $gateways; // Registered gateways
	public $gateway;  // Selected gateway for purchase
	public $errors;   // Submission errors
	public $stripe;   // Stripe gateway

	/**
	 * Nonce key for the checkout form.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const NONCE_KEY = 'sc_et_nonce';

	public function __construct() {

		$this->gateways = apply_filters( 'sc_et_gateways', [
			'stripe' => __NAMESPACE__ . '\\Stripe'
		] );

		add_action( 'init',                                   array( $this, 'load_gateways' ), 9 );
		add_action( 'init',                                   array( $this, 'process_form' ) );
		add_action( 'wp_ajax_sc_et_get_price',                array( $this, 'get_price_ajax' ) );
		add_action( 'wp_ajax_nopriv_sc_et_get_price',         array( $this, 'get_price_ajax' ) );
		add_action( 'wp_ajax_sc_et_validate_checkout',        array( $this, 'process_ajax_validation' ) );
		add_action( 'wp_ajax_nopriv_sc_et_validate_checkout', array( $this, 'process_ajax_validation' ) );

		$this->init();
	}

	public function init() {
		// Overwritten in gateway classes
	}

	public function load_gateways() {
		if ( empty( $this->gateways ) ) {
			return;
		}

		foreach ( $this->gateways as $gateway_id => $gateway ) {
			$this->{$gateway_id} = new $gateway;
		}
	}

	/**
	 * Get the price for the event via AJAX.
	 *
	 * @since 1.0.0
	 */
	public function get_price_ajax() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$event_id = ! empty( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;

		if ( empty( $event_id ) ) {
			wp_send_json_error(
				[
					'success' => false,
					'data'    => $_POST, // phpcs:ignore WordPress.Security.NonceVerification.Missing
				]
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$quantity = ! empty( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;

		wp_send_json_success(
			[
				'success' => true,
				'data'    => $this->get_price( $event_id, $quantity ),
			]
		);
	}

	/**
	 * Get the price for the event.
	 *
	 * @since 3.6.0
	 *
	 * @param int $event_id Event ID.
	 * @param int $quantity Quantity.
	 *
	 * @return array
	 */
	private function get_price( $event_id, $quantity ) {

		$price = get_event_meta( $event_id, 'ticket_price', true );
		$price = Functions\sanitize_amount( $price );
		$price = $price * max( 1, absint( $quantity ) );

		return [
			'price'     => Functions\currency_filter( $price ),
			'price_raw' => $price,
		];
	}

	/**
	 * Process the checkout form.
	 *
	 * @since 3.1.0
	 * @since 3.3.0 Added nonce verification.
	 */
	public function process_form() {

		if (
			! isset( $_POST['sc_et_action'] ) ||
			$_POST['sc_et_action'] !== 'checkout' ||
			! isset( $_POST['sc_et_nonce'] ) ||
			! wp_verify_nonce( wp_unslash( $_POST['sc_et_nonce'] ), self::NONCE_KEY ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		) {
			return;
		}

		if ( ! $this->validate() ) {
			$this->halt_with_validation_error();
			return;
		}

		$this->send_to_gateway();
	}

	/**
	 * Redirect back to the event page with an error_code on validation failure.
	 *
	 * Mirrors the existing Stripe::process() pattern. If the event can't be
	 * resolved, falls back to home_url() — that path only runs when
	 * sc_et_event_id is malformed, which is itself a validation failure.
	 *
	 * @since 3.11.1
	 */
	private function halt_with_validation_error() {

		$event_id = ! empty( $_POST['sc_et_event_id'] )
			? absint( $_POST['sc_et_event_id'] )
			: 0;

		$event = ! empty( $event_id )
			? sugar_calendar_get_event( $event_id )
			: false;

		$redirect_url = ! empty( $event )
			? Helper::get_event_frontend_url( $event )
			: home_url();

		wp_safe_redirect(
			add_query_arg(
				[ 'error_code' => 'sc_et_validation_failed' ],
				$redirect_url
			)
		);
		exit;
	}

	/**
	 * AJAX validation process.
	 *
	 * @since 1.0.0
	 * @since 3.11.1 Verify sc_et_nonce. The nonce travels inside
	 *                  $_POST['data'] (the serialized modal form), so it can
	 *                  only be checked after parse_str(); parse_str itself has
	 *                  no side effects beyond populating $_POST.
	 *
	 * @return void
	 */
	public function process_ajax_validation() {

		// Fill the POST super global with our form data.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified below after parse_str.
		parse_str( wp_unslash( $_POST['data'] ?? '' ), $_POST );

		if (
			! isset( $_POST['sc_et_nonce'] )
			|| ! wp_verify_nonce( wp_unslash( $_POST['sc_et_nonce'] ), self::NONCE_KEY ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		) {
			wp_send_json_error( [
				'errors' => [
					'invalid_nonce' => [
						'id'       => 'invalid_nonce',
						'msg'      => esc_html__( 'Security token expired. Please refresh the page and try again.', 'sugar-calendar-lite' ),
						'selector' => '#sc-event-ticketing-modal-fieldset',
					],
				],
			] );
		}

		$success = $this->validate();

		if ( $success !== true ) {
			wp_send_json_error( [ 'errors' => $this->errors ] );
		}

		wp_send_json_success();
	}

	/**
	 * Validate the checkout form.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function validate() {

		$this->validate_data();
		$this->validate_gateway();

		$gateway_obj = new $this->gateways[ $this->gateway ];

		if ( is_callable( array( $gateway_obj, 'validate_gateway_data' ) ) ) {
			$gateway_obj->validate_gateway_data();
		}

		if ( ! empty( $gateway_obj->errors ) ) {
			$this->errors = array_merge( $gateway_obj->errors, (array) $this->errors );
		}

		return empty( $this->errors );
	}

	/**
	 * Validate the checkout form data.
	 *
	 * @since 1.0.0
	 * @since 3.6.0 Add required condition for attendee fields.
	 * @since 3.11.0 Fixed the condition for limit capacity.
	 * @since 3.11.1 Reject submissions where count(attendees) > sc_et_quantity.
	 */
	public function validate_data() {

		if ( empty( $_POST['first_name'] ) ) {
			$this->add_error( 'missing_first_name', esc_html__( 'Please enter your first name.', 'sugar-calendar-lite' ), '#sc-event-ticketing-first-name' );
		}

		if ( empty( $_POST['last_name'] ) ) {
			$this->add_error( 'missing_last_name', esc_html__( 'Please enter your last name.', 'sugar-calendar-lite' ), '#sc-event-ticketing-last-name' );
		}

		if ( empty( $_POST['email'] ) || ! is_email( $_POST['email'] ) ) {
			$this->add_error( 'missing_email', esc_html__( 'Please enter a valid email address.', 'sugar-calendar-lite' ), '#sc-event-ticketing-email' );
		}

		$qty = ! empty( $_POST['sc_et_quantity'] )
			? absint( $_POST['sc_et_quantity'] )
			: 0;

		$event_id = ! empty( $_POST['sc_et_event_id'] )
			? absint( $_POST['sc_et_event_id'] )
			: 0;

		// Check if capacity limitation is enabled.
		$limit_capacity = absint( get_event_meta( $event_id, 'ticket_limit_capacity', true ) );
		$available      = Functions\get_available_tickets( $event_id );

		// Only validate quantity if capacity limitation is enabled.
		if ( $limit_capacity && $qty > $available ) {
			/* translators: %d: number of available tickets. */
			$this->add_error( 'insufficient_quantity', sprintf( esc_html__( 'Only %d tickets are available. Please reduce your purchase quantity.', 'sugar-calendar-lite' ), $available ), '#sc-event-ticketing-modal-attendee-fieldset' );
		}

		// Validate attendees if present.
		if ( ! empty( $_POST['attendees'] ) && is_array( $_POST['attendees'] ) ) {

			// Reject any submission whose attendees[] count exceeds the
			// quantity the buyer is paying for. complete() mints one ticket
			// per attendee row, so without this check the array becomes a
			// capacity bypass.
			if ( count( $_POST['attendees'] ) > $qty ) {
				$this->add_error(
					'attendees_exceed_quantity',
					esc_html__( 'The number of attendees exceeds the selected ticket quantity.', 'sugar-calendar-lite' ),
					'#sc-event-ticketing-modal-attendee-fieldset'
				);
			}

			foreach ( $_POST['attendees'] as $index => $attendee ) {

				$fieldset_selector = '.sc-et-form-group.sc-event-ticketing-attendee[attendee-key=\'' . absint( $index ) . '\']';

				// Always: if an email was submitted, it must be a valid email address.
				if (
					isset( $attendee['email'] )
					&& $attendee['email'] !== ''
					&& ! is_email( wp_unslash( $attendee['email'] ) )
				) {
					$this->add_error(
						'invalid_attendee_email_' . $index,
						esc_html__( 'Please enter a valid attendee email address.', 'sugar-calendar-lite' ),
						$fieldset_selector
					);
				}
			}

			// Gated: required-field enforcement only runs when the admin opted in.
			if ( $this->is_attendee_validation_enabled() ) {

				foreach ( $_POST['attendees'] as $index => $attendee ) {

					$fieldset_selector = '.sc-et-form-group.sc-event-ticketing-attendee[attendee-key=\'' . absint( $index ) . '\']';

					if ( empty( $attendee['full_name'] ) || empty( $attendee['email'] ) ) {
						$this->add_error(
							'missing_attendee_info_' . $index,
							esc_html__( 'Please complete attendee\'s information.', 'sugar-calendar-lite' ),
							$fieldset_selector
						);
					}
				}
			}
		}

		/**
		 * Extra validation actions.
		 *
		 * @since 3.6.0
		 *
		 * @param Checkout $this Checkout object.
		 */
		do_action( 'sc_et_checkout_validate_data', $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Check if attendee validation is enabled.
	 *
	 * @since 3.6.0
	 *
	 * @return bool
	 */
	public function is_attendee_validation_enabled() {

		return Settings\get_setting( 'attendee_fields_is_required', false );
	}

	public function validate_gateway() {

		$gateway = ! empty( $_POST['sc_et_gateway'] )
			? sanitize_text_field( $_POST['sc_et_gateway'] )
			: false;

		if ( empty( $gateway ) || ! array_key_exists( $gateway, $this->gateways ) || ! class_exists( $this->gateways[ $gateway ] ) ) {
			$this->add_error( 'unregistered_gateway', esc_html__( 'The gateway you have selected does not exist.', 'sugar-calendar-lite' ) );
		}

		$this->gateway = $gateway;
	}

	public function validate_gateway_data() {
		// Overwritten in each gateway
	}

	/**
	 * Add an error to the errors array.
	 *
	 * @since 3.6.0
	 *
	 * @param string $error_id      The error ID.
	 * @param string $error_message The error message.
	 * @param string $selector      The CSS selector for the error.
	 */
	public function add_error( $error_id = '', $error_message = '', $selector = '' ) {

		if ( ! is_array( $this->errors ) ) {
			$this->errors = [];
		}

		// Prepare error data.
		$error = [
			'id'       => $error_id,
			'msg'      => $error_message,
			'selector' => ! empty( $selector )
				? $selector
				: '#sc-event-ticketing-modal-fieldset',
		];

		/**
		 * Filter the error data before adding it to the errors array.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $error    The error data.
		 * @param string $error_id The error ID.
		 */
		$error = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sc_et_checkout_error',
			$error,
			$error_id
		);

		// Add error to errors array.
		$this->errors[ $error_id ] = $error;
	}

	/**
	 * Remove error by key from the errors array.
	 *
	 * @since 3.8.0
	 *
	 * @param string $error_id The error ID.
	 */
	public function remove_error( $error_id = '' ) {

		unset( $this->errors[ $error_id ] );
	}

	/**
	 * Complete the purchase.
	 *
	 * @since 3.1.0
	 * @since 3.6.0
	 * @since 3.11.1 Hard-fail (wp_die) when $quantity exceeds available
	 *                  capacity or count($attendees) exceeds $quantity. Defense
	 *                  in depth for code paths that reach complete() without
	 *                  running validate_data().
	 *
	 * @param array $order_data Order data.
	 */
	public function complete( $order_data = [] ) {

		// Maybe create attendees.
		$stored_attendees = [];

		// Anonymous attendees.
		$anonymous_attendees = [];

		$attendees = ! empty( $_POST['attendees'] ) && is_array( $_POST['attendees'] )
			? array_map( [ $this, 'sanitize_attendee_input' ], wp_unslash( $_POST['attendees'] ) )
			: [];

		$event_id = ! empty( $_POST['sc_et_event_id'] )
			? absint( $_POST['sc_et_event_id'] )
			: 0;

		$quantity = ! empty( $_POST['sc_et_quantity'] )
			? max( absint( $_POST['sc_et_quantity'] ), 1 )
			: 1;

		// Defense in depth: validate_data() rejects oversold and mismatched
		// payloads, but a code path reaching complete() without running
		// validate_data() (e.g., a third-party gateway hooked via sc_et_gateways)
		// must not be allowed to mint tickets that exceed capacity or the paid
		// quantity. Fail loud rather than silently clamp — silent truncation
		// would charge the buyer for tickets they don't receive.
		$available = Functions\get_available_tickets( $event_id );

		if ( $available !== -1 && $quantity > $available ) {
			wp_die(
				esc_html__( 'Insufficient tickets available.', 'sugar-calendar-lite' ),
				400
			);
		}

		if ( count( $attendees ) > $quantity ) {
			wp_die(
				esc_html__( 'The number of attendees exceeds the selected ticket quantity.', 'sugar-calendar-lite' ),
				400
			);
		}

		$event = ! empty( $event_id )
			? sugar_calendar_get_event( $event_id )
			: false;

		$event_date = ! empty( $event )
			? $event->start
			: '0000-00-00 00:00:00';

		/**
		 * Filter the order data before saving.
		 *
		 * @since 3.6.0
		 *
		 * @param array $order_data Order data.
		 * @param Event $event      Event object.
		 */
		$order_data = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sc_et_checkout_complete_order_data_before_save',
			$order_data,
			$event
		);

		if ( ! empty( $attendees ) ) {

			foreach ( $attendees as $attendee ) {

				$attendee = (object) $attendee;

				$maybe_new = $this->maybe_create_attendee( $attendee );

				if ( empty( $maybe_new->id ) ) {
					$anonymous_attendees[] = $attendee;
				} else {
					$stored_attendees[] = $maybe_new;
				}
			}
		}

		$order_id = Functions\add_order( $order_data );

		// Create tickets.
		foreach ( $stored_attendees as $attendee ) {

			/**
			 * Filter the ticket data before saving.
			 *
			 * @since 3.6.0
			 * @since 3.8.0 Add attendee object to filter.
			 *
			 * @param array  $ticket_data Ticket data.
			 * @param array  $order_data  Order data.
			 * @param object $attendee    Attendee object.
			 */
			$ticket_data = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				'sc_et_checkout_complete_ticket_data_before_save_ticket',
				[
					'event_id'    => $event_id,
					'event_date'  => $event_date,
					'attendee_id' => $attendee->id,
					'order_id'    => $order_id,
				],
				$order_data,
				$attendee
			);

			Functions\add_ticket( $ticket_data );
		}

		if ( ! empty( $anonymous_attendees ) ) {

			// Create tickets for unnamed attendees.

			foreach ( $anonymous_attendees as $attendee ) {

				/**
				 * Filter the ticket data before saving.
				 *
				 * @since 3.6.0
				 * @since 3.8.0 Add attendee object to filter.
				 *
				 * @param array  $ticket_data Ticket data.
				 * @param array  $order_data  Order data.
				 * @param object $attendee    Attendee object.
				 */
				$ticket_data = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
					'sc_et_checkout_complete_ticket_data_before_save_ticket',
					[
						'event_id'   => $event_id,
						'event_date' => $event_date,
						'order_id'   => $order_id,
					],
					$order_data,
					$attendee
				);

				Functions\add_ticket( $ticket_data );
			}
		}

		do_action( 'sc_et_checkout_pre_redirect', $order_id, $order_data );

		$redirect = Functions\issue_new_receipt_link( Functions\get_order( $order_id ) );
		if ( '' === $redirect ) {
			// Fallback only if the order disappeared between insert and redirect.
			$redirect = get_permalink( Settings\get_setting( 'receipt_page', 0 ) );
		}
		$success_url  = apply_filters( 'sc_et_success_page_url', $redirect );

		wp_safe_redirect( $success_url );
		exit;
	}

	/**
	 * Get the sanitized ticket price of an event.
	 *
	 * @since 3.6.1
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return float
	 */
	protected function get_ticket_price( $event_id ) {

		$price = get_event_meta( $event_id, 'ticket_price', true );

		return floatval( Functions\sanitize_amount( $price ) );
	}

	/**
	 * Create an attendee if it doesn't exist.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Lint fixes and add filter to support custom attendee fields.
	 *
	 * @param object $attendee Attendee object.
	 *
	 * @return object Attendee object.
	 */
	private function maybe_create_attendee( $attendee ) {

		/**
		 * Filter the attendee object before creating an attendee.
		 *
		 * @since 3.8.0
		 *
		 * @param object $attendee Attendee object.
		 */
		$attendee = apply_filters(
			'sugar_calendar_add_on_ticketing_gateways_checkout_attendee_before_create',
			$attendee
		);

		// Bail if no email.
		if ( empty( $attendee->email ) ) {
			return $attendee;
		}

		// See if we already have an attendee created for this email.
		$found_attendee = Functions\get_attendees(
			[
				'number'     => 1,
				'email'      => $attendee->email,
				'first_name' => $attendee->first_name,
				'last_name'  => $attendee->last_name,
			]
		);

		// Attendee found so use it's ID.
		if ( ! empty( $found_attendee ) ) {

			$attendee_id = $found_attendee[0]->id;

		} else { // No attendee was found, create a new one.

			$attendee_id = Functions\add_attendee(
				[
					'email'      => $attendee->email,
					'first_name' => $attendee->first_name,
					'last_name'  => $attendee->last_name,
				]
			);
		}

		// Get attendee object.
		$attendee_object = Functions\get_attendee( $attendee_id );

		/**
		 * Filter the attendee object after retrieval.
		 *
		 * @since 3.8.0
		 *
		 * @param object $attendee     The attendee object.
		 * @param int    $attendee_id  The attendee ID.
		 */
		return apply_filters(
			'sugar_calendar_add_on_ticketing_gateways_checkout_attendee_after_create',
			$attendee_object,
			$attendee
		);
	}

	/**
	 * Normalize an attendee record from $_POST.
	 *
	 * Applied at the input boundary so every downstream consumer
	 * (maybe_create_attendee, add_attendee, filter hooks) sees safe values.
	 *
	 * @since 3.11.1
	 *
	 * @param mixed $attendee Raw $_POST['attendees'][$i] value.
	 *
	 * @return array Sanitized attendee fields.
	 */
	private function sanitize_attendee_input( $attendee ) {

		if ( ! is_array( $attendee ) ) {
			return [
				'full_name'  => '',
				'first_name' => '',
				'last_name'  => '',
				'email'      => '',
			];
		}

		return [
			'full_name'  => isset( $attendee['full_name'] )  ? sanitize_text_field( $attendee['full_name'] )  : '',
			'first_name' => isset( $attendee['first_name'] ) ? sanitize_text_field( $attendee['first_name'] ) : '',
			'last_name'  => isset( $attendee['last_name'] )  ? sanitize_text_field( $attendee['last_name'] )  : '',
			'email'      => isset( $attendee['email'] )      ? sanitize_email( $attendee['email'] )           : '',
		];
	}

	private function send_to_gateway() {
		$gateway_obj = new $this->gateways[ $this->gateway ];
		$gateway_obj->process();
	}
}
