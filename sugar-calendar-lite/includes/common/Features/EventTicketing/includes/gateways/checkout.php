<?php
/**
 * Purchase API handlers
 */
namespace Sugar_Calendar\AddOn\Ticketing\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\CapacityLock;
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

	/**
	 * The built-in gateway map used as the `sc_et_gateways` filter default.
	 *
	 * Single source of truth so the constructor and any other caller (e.g.
	 * refund dispatch) share one default and stay rename-safe via ::class.
	 *
	 * @since 3.12.0
	 *
	 * @return array Map of gateway slug => gateway class FQCN.
	 */
	public static function default_gateways() {
		return [
			'stripe' => Stripe::class,
		];
	}

	public function __construct() {

		$this->gateways = apply_filters( 'sc_et_gateways', self::default_gateways() );

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
			'price'     => Functions\display_price( $price ),
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

			/**
			 * Filter the AJAX validation failure payload.
			 *
			 * Lets a feature attach structured, per-field error data under its own
			 * key alongside the flat `errors` list the modal renders. The existing
			 * front-end controllers iterate `errors` and ignore other keys.
			 *
			 * @since 3.13.0
			 *
			 * @param array    $response The wp_send_json_error payload.
			 * @param Checkout $checkout Checkout object.
			 */
			$response = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				'sc_et_checkout_ajax_validation_response',
				[ 'errors' => $this->errors ],
				$this
			);

			wp_send_json_error( $response );
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
	 * Resolve an event and report whether it is a multi-ticket event.
	 *
	 * Single source of truth for the multi-ticket check shared by validate_data()
	 * and complete(): both exempt multi-ticket events from the single-ticket
	 * count(attendees) > quantity guard, so the detection must stay identical.
	 * Defaults to false (single-ticket) when the event can't be resolved or the
	 * add-on isn't filtering.
	 *
	 * @since 3.12.0
	 *
	 * @param int $event_id The event ID.
	 *
	 * @return bool
	 */
	private function is_multiple_tickets_event( $event_id ) {

		$event = ! empty( $event_id )
			? sugar_calendar_get_event( $event_id )
			: false;

		return ! empty( $event )
			? (bool) apply_filters( 'sc_et_is_multiple_tickets', false, $event, $event->object_id )
			: false;
	}

	/**
	 * Validate the checkout form data.
	 *
	 * @since 1.0.0
	 * @since 3.6.0 Add required condition for attendee fields.
	 * @since 3.11.0 Fixed the condition for limit capacity.
	 * @since 3.11.1 Reject submissions where count(attendees) > sc_et_quantity.
	 * @since 3.12.0 Skip the count(attendees) > sc_et_quantity guard for
	 *                  multi-ticket events, where attendees[] legitimately spans
	 *                  all ticket types and the add-on's per-type cart/attendee
	 *                  parity gate validates the composition instead.
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

		// Multi-ticket orders submit one attendees[] row per ticket across every
		// selected type, so the single-ticket count <= quantity guard below does
		// not apply; the add-on's per-type cart/attendee parity gate (hooked on
		// sc_et_checkout_validate_data) validates that case instead.
		$is_multiple_tickets = $this->is_multiple_tickets_event( $event_id );

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

			// Reject any single-ticket submission whose attendees[] count exceeds
			// the quantity the buyer is paying for. complete() mints one ticket
			// per attendee row, so without this check the array becomes a
			// capacity bypass. Multi-ticket events are exempt: attendees[] spans
			// all ticket types there, and the add-on's cart/attendee parity gate
			// enforces the per-type composition instead.
			if ( ! $is_multiple_tickets && count( $_POST['attendees'] ) > $qty ) {
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
	 * @since 3.12.0 Exempt multi-ticket events from the count($attendees) >
	 *                  $quantity hard-fail (attendees[] spans all ticket types;
	 *                  the add-on's parity gate validates the composition).
	 * @since 3.12.0 Fire the sc_et_checkout_complete action with the full
	 *                  order snapshot once the order is persisted.
	 * @since 3.12.0 Stamp the processing gateway on the order.
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

		// Resolve the event up front; used for the event date further down.
		$event = ! empty( $event_id )
			? sugar_calendar_get_event( $event_id )
			: false;

		$is_multiple_tickets = $this->is_multiple_tickets_event( $event_id );

		// Single-ticket only: attendees[] spans all types in multi-ticket mode,
		// where the add-on's cart/attendee parity gate enforces composition.
		if ( ! $is_multiple_tickets && count( $attendees ) > $quantity ) {
			wp_die(
				esc_html__( 'The number of attendees exceeds the selected ticket quantity.', 'sugar-calendar-lite' ),
				400
			);
		}

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

		// A named lock serializes the capacity re-check and the ticket inserts, so
		// concurrent buyers for the last seat(s) cannot all pass the check before any
		// of them inserts. The lock is skipped for events that cannot oversell: an
		// unlimited single-ticket event ( get_available_tickets() === -1 ). Multi-ticket
		// events always lock — a capped ticket type can oversell even when the
		// event-wide total reads "unlimited" because another type is uncapped.
		$needs_lock = $is_multiple_tickets || ( Functions\get_available_tickets( $event_id ) !== -1 );

		if ( $needs_lock && ! CapacityLock::acquire( $event_id ) ) {
			// Fail safe: never run the capacity check unlocked. A terminal wp_die keeps
			// the paid path identical to today (a charged race-loser hits a dead-end,
			// exactly as today's over-capacity wp_die) rather than a retry-friendly
			// redirect that could invite a double charge — the money path is a
			// separate, later PR.
			wp_die(
				esc_html__( 'This event is currently busy. Please try again in a moment.', 'sugar-calendar-lite' ),
				503
			);
		}

		// FOOTGUN: any early wp_die() / exit / wp_safe_redirect()+exit added inside
		// this try MUST call CapacityLock::release( $event_id ) first — PHP does not
		// run the finally block on wp_die()/exit, so the lock would otherwise be held
		// until connection close and time out a concurrent buyer into a false "busy".
		try {

			if ( $needs_lock ) {

				// Force a database-fresh count under the lock. BerlinDB caches the
				// per-request ticket count, so without this the re-check would read
				// its own pre-lock (stale) count and the lock would not stop the
				// oversell.
				CapacityLock::flush_ticket_cache();

				/**
				 * Re-check per-ticket-type / per-occurrence capacity against fresh counts.
				 *
				 * Fired inside the capacity lock, immediately before minting. Handlers
				 * return a WP_Error when a ticket type or occurrence would be oversold,
				 * or the incoming value (default null) otherwise. Because it runs under
				 * a per-event lock, handlers must do only fast, local work.
				 *
				 * @since 3.13.0
				 *
				 * @param WP_Error|null $capacity_error Error when capacity is exceeded, else null.
				 * @param int           $event_id       Event ID.
				 * @param int           $quantity       Quantity being purchased.
				 * @param array         $order_data     Order data.
				 */
				$capacity_error = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
					'sc_et_checkout_capacity_recheck',
					null,
					$event_id,
					$quantity,
					$order_data
				);

				if ( is_wp_error( $capacity_error ) ) {
					// Release before terminating: wp_die() does not run the finally block.
					CapacityLock::release( $event_id );
					wp_die( esc_html( $capacity_error->get_error_message() ), 400 );
				}

				// Event-wide re-check (defense in depth, now serialized and fresh). A
				// code path reaching complete() without validate_data() (e.g. a
				// third-party gateway) still cannot mint beyond capacity. Fail loud
				// rather than silently clamp — silent truncation would charge the buyer
				// for tickets they don't receive.
				$available = Functions\get_available_tickets( $event_id );

				if ( $available !== -1 && $quantity > $available ) {
					CapacityLock::release( $event_id );
					wp_die(
						esc_html__( 'Insufficient tickets available.', 'sugar-calendar-lite' ),
						400
					);
				}
			}

			// Test-only: widen the check -> insert window so the concurrency E2E can
			// reliably observe the race. Inert in production (constant undefined).
			if ( defined( 'SC_ET_TEST_CAP_DELAY' ) && SC_ET_TEST_CAP_DELAY ) {
				usleep( (int) SC_ET_TEST_CAP_DELAY );
			}

			if ( ! empty( $attendees ) ) {
				$prepared            = $this->prepare_attendees( $attendees );
				$stored_attendees    = $prepared['stored'];
				$anonymous_attendees = $prepared['anonymous'];
			}

			// Record the processing gateway on the order (its key in the
			// sc_et_gateways map). Refunds dispatch back to this gateway.
			$order_data['gateway'] = ! empty( $this->gateway )
				? $this->gateway
				: 'stripe';

			$order_id = Functions\add_order( $order_data );

			// The UNIQUE constraint on transaction_id rejects a second order
			// that reuses a paid PaymentIntent — the losing side of a replay race.
			// Bail before minting any tickets so we never create tickets bound to a
			// non-existent order. The app-level replay SELECT catches the common
			// case earlier; this guard covers the concurrent race.
			if ( empty( $order_id ) ) {
				// Release before terminating: exit does not run the finally block.
				if ( $needs_lock ) {
					CapacityLock::release( $event_id );
				}
				wp_safe_redirect(
					add_query_arg(
						[ 'error_code' => 'sc_et_order_not_created' ],
						Helper::get_event_frontend_url( $event )
					)
				);
				exit;
			}

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
		} finally {
			// Backstop for the normal fall-through and any thrown exception. The
			// wp_die / exit branches above already released explicitly (they do not
			// run finally); a double release is harmless.
			if ( $needs_lock ) {
				CapacityLock::release( $event_id );
			}
		}

		do_action( 'sc_et_checkout_pre_redirect', $order_id, $order_data );

		/**
		 * Fires once after a ticket order is fully persisted (order, tickets,
		 * and attendees saved), for every gateway and the free-ticket path.
		 *
		 * Provides a single, complete snapshot of the purchase so integrations
		 * don't have to reassemble it from the individual checkout hooks.
		 *
		 * @since 3.12.0
		 *
		 * @param array $payload  Order snapshot. See get_checkout_complete_payload().
		 * @param int   $order_id Order ID.
		 */
		do_action( 'sc_et_checkout_complete', Functions\get_checkout_complete_payload( $order_id ), $order_id );

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
	 * Prepare submitted attendees: create/resolve each attendee record, then split
	 * them into stored (has a persisted attendee id) and anonymous (no email / not
	 * persisted) buckets. "Prepare" — not "sort" — because it performs the
	 * maybe_create_attendee() writes, it does not reorder.
	 *
	 * @since 3.13.0
	 *
	 * @param array $attendees Sanitized attendee records.
	 *
	 * @return array {
	 *     @type object[] $stored    Attendees with a persisted id.
	 *     @type object[] $anonymous Attendees without a persisted id.
	 * }
	 */
	private function prepare_attendees( $attendees ) {

		$stored    = [];
		$anonymous = [];

		foreach ( $attendees as $attendee ) {

			$attendee  = (object) $attendee;
			$maybe_new = $this->maybe_create_attendee( $attendee );

			if ( empty( $maybe_new->id ) ) {
				$anonymous[] = $attendee;
			} else {
				$stored[] = $maybe_new;
			}
		}

		return [
			'stored'    => $stored,
			'anonymous' => $anonymous,
		];
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
				'ticket_type' => 0,
			];
		}

		return [
			'full_name'  => isset( $attendee['full_name'] )  ? sanitize_text_field( $attendee['full_name'] )  : '',
			'first_name' => isset( $attendee['first_name'] ) ? sanitize_text_field( $attendee['first_name'] ) : '',
			'last_name'  => isset( $attendee['last_name'] )  ? sanitize_text_field( $attendee['last_name'] )  : '',
			'email'      => isset( $attendee['email'] )      ? sanitize_email( $attendee['email'] )           : '',
			// Preserve the multi-ticket type id so the ticketing add-on can bind
			// each minted ticket to its purchased type. The add-on validates the
			// id against the event before use; core keeps it as an opaque absint.
			'ticket_type' => isset( $attendee['ticket_type'] ) ? absint( $attendee['ticket_type'] ) : 0,
		];
	}

	private function send_to_gateway() {
		$gateway_obj = new $this->gateways[ $this->gateway ];

		// Record which gateway is processing so complete() can stamp the order.
		$gateway_obj->gateway = $this->gateway;

		$gateway_obj->process();
	}
}
