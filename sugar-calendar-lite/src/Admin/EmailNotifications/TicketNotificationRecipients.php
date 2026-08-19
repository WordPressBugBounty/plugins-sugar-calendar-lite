<?php

namespace Sugar_Calendar\Admin\EmailNotifications;

use Generator;
use Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query;
use Sugar_Calendar\AddOn\Ticketing\Database\Order_Query;
use Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendees;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_orders;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_tickets;

/**
 * Resolves ticket notification recipients for an event.
 *
 * @since 3.13.0
 */
class TicketNotificationRecipients {

	/**
	 * Table names captured while filtered delivery-query defaults are probed.
	 *
	 * Reusing these names prevents later query-class constructors from
	 * registering policy after the final per-type hook check.
	 *
	 * @since 3.13.0
	 *
	 * @var array{orders:string,tickets:string,attendees:string}|null
	 */
	private $batch_table_names = null;

	/**
	 * Maximum number of database rows loaded by one query.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	private const QUERY_PAGE_SIZE = 100;

	/**
	 * Global BerlinDB query-default filter.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	private const GLOBAL_QUERY_DEFAULTS_HOOK = 'sugar_calendar_database_query_default_variables';

	/**
	 * The one built-in global-default callback proven irrelevant to ticketing.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	private const ALLOWED_GLOBAL_DEFAULT_CALLBACK_CLASS = 'Sugar_Calendar\Pro\Features\Speakers\Common\Relationships';

	/**
	 * Method paired with ALLOWED_GLOBAL_DEFAULT_CALLBACK_CLASS.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	private const ALLOWED_GLOBAL_DEFAULT_CALLBACK_METHOD = 'add_speaker_ids_to_default_query_vars';

	/**
	 * Per-type BerlinDB hooks whose callbacks can change delivery-query policy.
	 *
	 * The batch availability query deliberately skips the BerlinDB query
	 * objects used by the delivery iterator. When an extension customizes any
	 * of their arguments, SQL clauses, or final results, availability
	 * must use the iterator instead so search and delivery cannot disagree.
	 * WordPress stores actions and filters in the same hook registry, so
	 * has_filter() detects both the sc_pre_get_* and sc_parse_* actions below.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	private const DELIVERY_QUERY_POLICY_HOOKS = [
		'sc_pre_get_orders',
		'sc_parse_orders_query',
		'sc_orders_query_clauses',
		'sc_the_orders',
		'sc_pre_get_tickets',
		'sc_parse_tickets_query',
		'sc_tickets_query_clauses',
		'sc_the_tickets',
		'sc_pre_get_attendees',
		'sc_parse_attendees_query',
		'sc_attendees_query_clauses',
		'sc_the_attendees',
	];

	/**
	 * Resolve the ticket notification delivery queue for an event.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return array<int,array{name:string,email:string,event_id:int,occurrence_id:int}>
	 */
	public function resolve( int $event_id ): array {

		$recipients = [];

		foreach ( $this->iterate( $event_id ) as $recipient ) {
			$recipients[] = $recipient;
		}

		return $recipients;
	}

	/**
	 * Count ticket notification recipients for an event.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return int
	 */
	public function count( int $event_id ): int {

		$count = 0;

		foreach ( $this->iterate( $event_id ) as $recipient ) {
			++$count;
		}

		return $count;
	}

	/**
	 * Determine whether an event has at least one ticket notification recipient.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return bool
	 */
	public function has_recipients( int $event_id ): bool {

		foreach ( $this->iterate( $event_id ) as $recipient ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine recipient availability for multiple events.
	 *
	 * @since 3.13.0
	 *
	 * @param int[] $event_ids Event IDs.
	 *
	 * @return array<int,bool>
	 */
	public function has_recipients_for_events( array $event_ids ): array {

		$availability = $this->initialize_availability( $event_ids );

		if ( empty( $availability ) ) {
			return [];
		}

		if ( $this->delivery_query_policy_is_filtered() ) {
			return $this->get_lazy_availability( $availability );
		}

		$remaining = array_fill_keys( array_keys( $availability ), true );
		$cursor    = 0;

		do {
			$rows = $this->query_recipient_availability_page( array_keys( $remaining ), $cursor );

			if ( $rows === null ) {
				return $this->get_lazy_availability( $availability );
			}

			$row_count = count( $rows );

			if ( $row_count === 0 ) {
				break;
			}

			$cursor = (int) $rows[ $row_count - 1 ]->ticket_id;

			$this->update_availability_from_rows( $rows, $availability, $remaining );

			if ( empty( $remaining ) ) {
				break;
			}
		} while ( $row_count === self::QUERY_PAGE_SIZE );

		return $availability;
	}

	/**
	 * Check whether an extension can change any delivery query.
	 *
	 * Raw SQL cannot safely reproduce callbacks on BerlinDB's generic defaults,
	 * per-type pre-get/parse actions, SQL-clause filters, or final-result
	 * filters. Detect the complete relevant hook surface for the order, ticket,
	 * and attendee queries and use the exact delivery iterator when customized.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	private function delivery_query_policy_is_filtered(): bool {

		if ( $this->per_type_query_policy_is_filtered() ) {
			return true;
		}

		if ( ! $this->global_query_defaults_are_safe() ) {
			return true;
		}

		$this->capture_batch_table_names();

		return (
			$this->per_type_query_policy_is_filtered() ||
			! $this->global_query_defaults_are_safe()
		);
	}

	/**
	 * Check the per-type delivery-query hook surface.
	 *
	 * This check runs both before and after global defaults are evaluated,
	 * because a global callback can register a per-type policy callback while
	 * the filtered query objects are constructed.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	private function per_type_query_policy_is_filtered(): bool {

		foreach ( self::DELIVERY_QUERY_POLICY_HOOKS as $hook_name ) {
			if ( false !== has_filter( $hook_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the global-default hook contains only the known safe callback.
	 *
	 * Pro Speakers always registers the allowlisted callback. Its current body
	 * only adds the event-only speaker_ids variable, which the order, ticket,
	 * and attendee query classes do not interpret. This allowlist is deliberately
	 * identity-based: arbitrary callbacks are never executed in a dummy probe,
	 * because a stateful callback could remove itself or mutate policy before the
	 * real lazy delivery query gets a chance to apply it.
	 *
	 * Re-audit this identity gate if the Speakers callback body/registration
	 * changes or if ticketing query classes begin interpreting speaker_ids.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	private function global_query_defaults_are_safe(): bool {

		global $wp_filter;

		if ( ! isset( $wp_filter[ self::GLOBAL_QUERY_DEFAULTS_HOOK ] ) ) {
			return true;
		}

		$hook = $wp_filter[ self::GLOBAL_QUERY_DEFAULTS_HOOK ];

		if ( ! $hook instanceof \WP_Hook ) {
			return false;
		}

		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'] ?? null;

				if ( ! $this->is_allowed_global_default_callback( $function, $callback ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check one global-default callback against the exact built-in identity.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $function Callback callable.
	 * @param array $callback Registered callback metadata.
	 *
	 * @return bool
	 */
	private function is_allowed_global_default_callback( $function, array $callback ): bool {

		return (
			is_array( $function ) &&
			count( $function ) === 2 &&
			is_object( $function[0] ) &&
			get_class( $function[0] ) === self::ALLOWED_GLOBAL_DEFAULT_CALLBACK_CLASS &&
			$function[1] === self::ALLOWED_GLOBAL_DEFAULT_CALLBACK_METHOD &&
			( $callback['accepted_args'] ?? null ) === 1
		);
	}

	/**
	 * Capture raw-query table names from one instance of each query class.
	 *
	 * This runs only after the global registry contains no callbacks or only the
	 * audited Speakers callback. No query objects are constructed after the
	 * subsequent per-type/global hook recheck.
	 *
	 * @since 3.13.0
	 *
	 * @return void
	 */
	private function capture_batch_table_names(): void {

		$order_query    = new Order_Query();
		$ticket_query   = new Ticket_Query();
		$attendee_query = new Attendee_Query();

		$this->batch_table_names = [
			'orders'    => $order_query->get_table_name(),
			'tickets'   => $ticket_query->get_table_name(),
			'attendees' => $attendee_query->get_table_name(),
		];
	}

	/**
	 * Resolve availability through the extension-aware delivery iterator.
	 *
	 * @since 3.13.0
	 *
	 * @param array<int,bool> $availability Normalized availability map.
	 *
	 * @return array<int,bool>
	 */
	private function get_lazy_availability( array $availability ): array {

		foreach ( array_keys( $availability ) as $event_id ) {
			$availability[ $event_id ] = $this->has_recipients( $event_id );
		}

		return $availability;
	}

	/**
	 * Update availability state from one keyset page.
	 *
	 * @since 3.13.0
	 *
	 * @param object[]        $rows         Ticket recipient candidates.
	 * @param array<int,bool> $availability Availability keyed by event ID.
	 * @param array<int,bool> $remaining    Unresolved event IDs.
	 *
	 * @return void
	 */
	private function update_availability_from_rows( array $rows, array &$availability, array &$remaining ): void {

		foreach ( $rows as $row ) {
			$event_id = (int) $row->event_id;

			if ( ! isset( $remaining[ $event_id ] ) ) {
				continue;
			}

			$email = $this->normalize_email( $row->attendee_email );

			if ( $email === '' ) {
				$email = $this->normalize_email( $row->purchaser_email );
			}

			if ( $email !== '' ) {
				$availability[ $event_id ] = true;

				unset( $remaining[ $event_id ] );
			}
		}
	}

	/**
	 * Initialize recipient availability for unique positive event IDs.
	 *
	 * @since 3.13.0
	 *
	 * @param int[] $event_ids Event IDs.
	 *
	 * @return array<int,bool>
	 */
	private function initialize_availability( array $event_ids ): array {

		$availability = [];

		foreach ( $event_ids as $event_id ) {
			$event_id = absint( $event_id );

			if ( $event_id > 0 && ! array_key_exists( $event_id, $availability ) ) {
				$availability[ $event_id ] = false;
			}
		}

		return $availability;
	}

	/**
	 * Query one keyset page of ticket recipient candidates.
	 *
	 * @since 3.13.0
	 *
	 * @param int[] $event_ids Remaining event IDs.
	 * @param int   $cursor    Last inspected ticket ID.
	 *
	 * @return object[]|null Null when the database query fails.
	 */
	private function query_recipient_availability_page( array $event_ids, int $cursor ): ?array {

		global $wpdb;

		if ( $this->batch_table_names === null ) {
			return null;
		}

		$ticket_table   = $this->batch_table_names['tickets'];
		$order_table    = $this->batch_table_names['orders'];
		$attendee_table = $this->batch_table_names['attendees'];
		$placeholders   = implode( ', ', array_fill( 0, count( $event_ids ), '%d' ) );
		$args           = array_merge( [ $cursor ], $event_ids, [ 'active', 'paid', self::QUERY_PAGE_SIZE ] );

		$sql = "SELECT tickets.id AS ticket_id,
				orders.event_id AS event_id,
				attendees.email AS attendee_email,
				orders.email AS purchaser_email
			FROM {$ticket_table} AS tickets
			LEFT JOIN {$attendee_table} AS attendees ON attendees.id = tickets.attendee_id
			INNER JOIN {$order_table} AS orders ON orders.id = tickets.order_id
			WHERE tickets.id > %d
				AND orders.event_id IN ( {$placeholders} )
				AND tickets.status = %s
				AND orders.status = %s
			ORDER BY tickets.id ASC
			LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the ticketing query classes; the placeholder run contains only generated %d tokens.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Bounded keyset query against ticketing custom tables; every value is passed to prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		return $wpdb->last_error === '' && is_array( $rows ) ? $rows : null;
	}

	/**
	 * Iterate over ticket notification recipients in bounded query pages.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return Generator<int,array{name:string,email:string,event_id:int,occurrence_id:int}>
	 */
	private function iterate( int $event_id ): Generator {

		if ( $event_id <= 0 ) {
			return;
		}

		$order_offset = 0;

		do {
			$orders      = $this->query_order_page( $event_id, $order_offset );
			$order_count = count( $orders );

			if ( $order_count === 0 ) {
				break;
			}

			yield from $this->iterate_order_page( $event_id, $orders );

			$order_offset += self::QUERY_PAGE_SIZE;
		} while ( $order_count === self::QUERY_PAGE_SIZE );
	}

	/**
	 * Query one bounded page of paid event orders.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Event ID.
	 * @param int $offset   Query offset.
	 *
	 * @return array
	 */
	private function query_order_page( int $event_id, int $offset ): array {

		return get_orders(
			[
				'event_id' => $event_id,
				'status'   => 'paid',
				'number'   => self::QUERY_PAGE_SIZE,
				'offset'   => $offset,
				'orderby'  => 'id',
				'order'    => 'ASC',
			]
		);
	}

	/**
	 * Iterate over recipients from one bounded order page.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $event_id Event ID.
	 * @param array $orders   Order rows.
	 *
	 * @return Generator<int,array{name:string,email:string,event_id:int,occurrence_id:int}>
	 */
	private function iterate_order_page( int $event_id, array $orders ): Generator {

		$orders_by_id  = $this->index_orders( $orders );
		$state         = $this->order_state();
		$ticket_offset = 0;

		do {
			$tickets      = $this->query_ticket_page( array_keys( $orders_by_id ), $ticket_offset );
			$ticket_count = count( $tickets );

			if ( $ticket_count === 0 ) {
				break;
			}

			$attendees_by_id = $this->index_ticket_page_attendees( $tickets );

			yield from $this->iterate_ticket_page(
				$event_id,
				$tickets,
				$attendees_by_id,
				$orders_by_id,
				$state
			);

			$ticket_offset += self::QUERY_PAGE_SIZE;
		} while ( $ticket_count === self::QUERY_PAGE_SIZE );

		$fallback = $this->fallback_recipient( $state, $event_id );

		if ( $fallback !== null ) {
			yield $fallback;
		}
	}

	/**
	 * Index an order page by order ID.
	 *
	 * @since 3.13.0
	 *
	 * @param array $orders Order rows.
	 *
	 * @return array<int,object>
	 */
	private function index_orders( array $orders ): array {

		$orders_by_id = [];

		foreach ( $orders as $order ) {
			$orders_by_id[ (int) $order->id ] = $order;
		}

		return $orders_by_id;
	}

	/**
	 * Query one bounded page of active tickets.
	 *
	 * @since 3.13.0
	 *
	 * @param int[] $order_ids Order IDs.
	 * @param int   $offset    Query offset.
	 *
	 * @return array
	 */
	private function query_ticket_page( array $order_ids, int $offset ): array {

		return get_tickets(
			[
				'order_id__in' => $order_ids,
				'status'       => 'active',
				'number'       => self::QUERY_PAGE_SIZE,
				'offset'       => $offset,
				'orderby'      => [ 'order_id', 'id' ],
				'order'        => 'ASC',
			]
		);
	}

	/**
	 * Index attendees referenced by the current ticket page.
	 *
	 * @since 3.13.0
	 *
	 * @param array $tickets Ticket rows.
	 *
	 * @return array<int,object>
	 */
	private function index_ticket_page_attendees( array $tickets ): array {

		$attendee_ids = [];

		foreach ( $tickets as $ticket ) {
			$attendee_id = (int) $ticket->attendee_id;

			if ( $attendee_id > 0 ) {
				$attendee_ids[ $attendee_id ] = $attendee_id;
			}
		}

		if ( empty( $attendee_ids ) ) {
			return [];
		}

		$attendees = get_attendees(
			[
				'id__in' => array_values( $attendee_ids ),
				'number' => self::QUERY_PAGE_SIZE,
			]
		);

		$attendees_by_id = [];

		foreach ( $attendees as $attendee ) {
			$attendees_by_id[ (int) $attendee->id ] = $attendee;
		}

		return $attendees_by_id;
	}

	/**
	 * Iterate over one ticket page while preserving the current order state.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $event_id        Event ID.
	 * @param array $tickets         Ticket rows.
	 * @param array $attendees_by_id Attendees keyed by ID.
	 * @param array $orders_by_id    Orders keyed by ID.
	 * @param array $state           Current order state, passed by reference.
	 *
	 * @return Generator<int,array{name:string,email:string,event_id:int,occurrence_id:int}>
	 */
	private function iterate_ticket_page( int $event_id, array $tickets, array $attendees_by_id, array $orders_by_id, array &$state ): Generator {

		foreach ( $tickets as $ticket ) {
			$order_id = (int) $ticket->order_id;

			if ( $order_id !== $state['order_id'] ) {
				$fallback = $this->fallback_recipient( $state, $event_id );

				if ( $fallback !== null ) {
					yield $fallback;
				}

				$state = $this->order_state( $orders_by_id[ $order_id ] );
			}

			$recipient = $this->named_recipient( $ticket, $attendees_by_id, $state, $event_id );

			if ( $recipient !== null ) {
				yield $recipient;
			}
		}
	}

	/**
	 * Create an empty or initialized current-order state.
	 *
	 * @since 3.13.0
	 *
	 * @param object|null $order Order row.
	 *
	 * @return array{order:object|null,order_id:int,named_emails:array<string,bool>,fallback_needed:bool,fallback_occurrence_id:int}
	 */
	private function order_state( $order = null ): array {

		$order_id = $order === null ? 0 : (int) $order->id;

		return [
			'order'                  => $order,
			'order_id'               => $order_id,
			'named_emails'           => [],
			'fallback_needed'        => false,
			'fallback_occurrence_id' => 0,
		];
	}

	/**
	 * Build one named recipient or mark the current order for fallback.
	 *
	 * @since 3.13.0
	 *
	 * @param object $ticket          Ticket row.
	 * @param array  $attendees_by_id Attendees keyed by ID.
	 * @param array  $state           Current order state, passed by reference.
	 * @param int    $event_id        Event ID.
	 *
	 * @return array{name:string,email:string,event_id:int,occurrence_id:int}|null
	 */
	private function named_recipient( $ticket, array $attendees_by_id, array &$state, int $event_id ): ?array {

		$attendee_id = (int) $ticket->attendee_id;
		$attendee    = $attendees_by_id[ $attendee_id ] ?? null;
		$email       = $attendee === null ? '' : $this->normalize_email( $attendee->email );

		if ( $email === '' ) {
			$this->mark_fallback_needed( $state, (int) $ticket->occurrence_id );

			return null;
		}

		if ( isset( $state['named_emails'][ $email ] ) ) {
			return null;
		}

		$state['named_emails'][ $email ] = true;

		$ticket_occurrence_id = (int) $ticket->occurrence_id;
		$order_occurrence_id  = (int) $state['order']->occurrence_id;
		$occurrence_id        = $ticket_occurrence_id > 0 ? $ticket_occurrence_id : $order_occurrence_id;

		return [
			'name'          => $this->name( $attendee ),
			'email'         => $email,
			'event_id'      => $event_id,
			'occurrence_id' => $occurrence_id,
		];
	}

	/**
	 * Mark the current order for at most one purchaser fallback.
	 *
	 * @since 3.13.0
	 *
	 * @param array $state         Current order state, passed by reference.
	 * @param int   $occurrence_id First fallback-needing ticket occurrence ID.
	 *
	 * @return void
	 */
	private function mark_fallback_needed( array &$state, int $occurrence_id ): void {

		if ( $state['fallback_needed'] ) {
			return;
		}

		$state['fallback_needed']        = true;
		$state['fallback_occurrence_id'] = $occurrence_id;
	}

	/**
	 * Build the purchaser fallback for one completed order.
	 *
	 * @since 3.13.0
	 *
	 * @param array $state    Current order state.
	 * @param int   $event_id Event ID.
	 *
	 * @return array{name:string,email:string,event_id:int,occurrence_id:int}|null
	 */
	private function fallback_recipient( array $state, int $event_id ): ?array {

		if ( $state['order'] === null || ! $state['fallback_needed'] ) {
			return null;
		}

		$order = $state['order'];
		$email = $this->normalize_email( $order->email );

		if ( $email === '' || isset( $state['named_emails'][ $email ] ) ) {
			return null;
		}

		$order_occurrence_id = (int) $order->occurrence_id;
		$occurrence_id       = $order_occurrence_id > 0 ? $order_occurrence_id : $state['fallback_occurrence_id'];

		return [
			'name'          => $this->name( $order ),
			'email'         => $email,
			'event_id'      => $event_id,
			'occurrence_id' => $occurrence_id,
		];
	}

	/**
	 * Normalize and validate an email address.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $email Email address.
	 *
	 * @return string
	 */
	private function normalize_email( $email ): string {

		$email = sanitize_email( (string) $email );

		return is_email( $email ) ? strtolower( $email ) : '';
	}

	/**
	 * Build a full name from an attendee or order row.
	 *
	 * @since 3.13.0
	 *
	 * @param object $row Attendee or order row.
	 *
	 * @return string
	 */
	private function name( $row ): string {

		return trim( (string) $row->first_name . ' ' . (string) $row->last_name );
	}
}
