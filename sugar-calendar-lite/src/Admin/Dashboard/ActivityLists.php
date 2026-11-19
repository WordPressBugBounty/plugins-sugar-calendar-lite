<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query;
use Sugar_Calendar\SetupChecklist\CompletionDetector;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_orders;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\ticketing_provider_available_for_admin;

/**
 * What the Activity widget reads from the site.
 *
 * Lite reads checkout tickets and orders; the Pro subclass adds WooCommerce tickets and RSVP people.
 *
 * @since 3.18.0
 */
class ActivityLists {

	use TicketingTables;

	/**
	 * Order statuses that mean the site has taken orders: what the Orders list shows, less the trash.
	 *
	 * @since 3.18.0
	 */
	const LISTED_ORDER_STATUSES = [ 'pending', 'paid', 'refunded' ];

	/**
	 * Days the free-tickets card looks back over, as the Last 30 Days preset, so a site that
	 * moves to paid tickets stops seeing the card within 30 days.
	 *
	 * @since 3.18.0
	 */
	const FREE_TICKETS_DAYS = 30;

	/**
	 * Fewest tickets in that window before the free-tickets card can show.
	 *
	 * @since 3.18.0
	 */
	const FREE_TICKETS_MIN = 10;

	/**
	 * Newest paid orders the latest lists read first. Joined to a window this small, MySQL
	 * hash-joins the tickets once; from about 20 rows it switches to a per-ticket loop.
	 *
	 * @since 3.18.0
	 */
	const LATEST_ORDERS_WINDOW = 10;

	/**
	 * Whether the site has an event with a saved status, the Setup Checklist's `create-event` rule.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function has_events(): bool {

		return CompletionDetector::has_saved_event();
	}

	/**
	 * Whether anyone has ever registered: an active ticket on a paid order.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function has_attendees(): bool {

		global $wpdb;

		$from = $this->get_checkout_tickets_from();
		$rule = $this->get_paid_attendee_rule();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from the ticketing query classes; the rule is a fixed expression.
		return (bool) $wpdb->get_var( "SELECT 1 FROM {$from} WHERE {$rule} LIMIT 1" );
	}

	/**
	 * Whether the site has ever taken an order the Orders list shows.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function has_orders(): bool {

		global $wpdb;

		[ $orders ]   = $this->get_ticketing_tables();
		$placeholders = implode( ', ', array_fill( 0, count( self::LISTED_ORDER_STATUSES ), '%s' ) );
		$sql          = "SELECT 1 FROM {$orders} WHERE status IN ( {$placeholders} ) LIMIT 1"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the ticketing query class; the placeholder run holds only %s tokens.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Every value is passed to prepare().
		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, self::LISTED_ORDER_STATUSES ) );
	}

	/**
	 * Whether the site took orders but has no payment method now. The Orders tab's Reconnect state
	 * and the Reconnect card both read this, so they cannot drift apart.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function needs_payment_reconnect(): bool {

		// The provider check first: it reads no table.
		return ! ticketing_provider_available_for_admin() && $this->has_orders();
	}

	/**
	 * Latest attendees in a range, newest first: active tickets on paid orders, dated by the order.
	 *
	 * @since 3.18.0
	 *
	 * @param int   $limit Most rows.
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array Rows: `name`, `email`, `event_id`, `occurrence_id` (0 for none), `time`
	 *               (UTC `Y-m-d H:i:s`) and `order_id`.
	 */
	public function get_latest_attendees( int $limit, array $range ): array {

		$bounds = DateRange::get_utc_window( $range );

		return $this->read_latest(
			function ( int $window ) use ( $limit, $bounds ) {
				return $this->read_ticket_attendees( $limit, $window, $bounds );
			},
			$limit,
			function () use ( $bounds ) {
				return $this->has_more_paid_orders( $bounds, $this->get_checkout_order_rule() );
			}
		);
	}

	/**
	 * Latest orders in a range the Orders list shows, newest first.
	 *
	 * @since 3.18.0
	 *
	 * @param int   $limit Most rows.
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array Rows: `id`, `name`, `event_id`, `occurrence_id` (0 for none), `total`, `status`
	 *               and `time` (UTC `Y-m-d H:i:s`).
	 */
	public function get_latest_orders( int $limit, array $range ): array {

		[ $start, $end ] = DateRange::get_utc_window( $range );

		// One clause shares `inclusive` between both ends, so each end gets its own.
		$orders = get_orders(
			[
				'status'             => self::LISTED_ORDER_STATUSES,
				'date_created_query' => [
					'relation' => 'AND',
					[
						'after'     => $start,
						'inclusive' => true,
					],
					[
						'before' => $end,
					],
				],
				'orderby'            => 'date_created',
				'order'              => 'DESC',
				'number'             => $limit,
			]
		);

		return array_map( [ self::class, 'to_order_row' ], (array) $orders );
	}

	/**
	 * Whether most of the last 30 days' tickets were free: at least 10 active tickets on paid
	 * orders, more than half of them on $0 orders. WooCommerce rows carry the whole cart total, so
	 * only checkout tickets count.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function has_mostly_free_tickets(): bool {

		global $wpdb;

		$from            = $this->get_checkout_tickets_from();
		$rule            = $this->get_paid_attendee_rule();
		[ $start, $end ] = DateRange::get_utc_window( DateRange::get_preset_range( self::FREE_TICKETS_DAYS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One count for one render.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from the ticketing query classes; the rule is a fixed expression.
				"SELECT COUNT( t.id ) AS tickets, COALESCE( SUM( o.total = 0 ), 0 ) AS free FROM {$from} WHERE {$rule} AND o.date_created >= %s AND o.date_created < %s",
				$start,
				$end
			)
		);

		$count = (int) ( $row->tickets ?? 0 );

		return $count >= self::FREE_TICKETS_MIN && (int) ( $row->free ?? 0 ) * 2 > $count;
	}

	/**
	 * Rows read from the newest orders' window, or from every order when the window comes up short
	 * (its tickets trashed, or fewer attendees in the range than the list shows).
	 *
	 * A window that held every paid order in the range already has every row the full read would
	 * find, so that read (about 1 s at 200k orders, no index on the date) only runs when the window
	 * was full.
	 *
	 * @since 3.18.0
	 *
	 * @param callable $read           Reads the rows for a window of the newest orders, 0 for every order.
	 * @param int      $limit          Most rows.
	 * @param callable $is_window_full Whether the range holds more orders than the window reads.
	 *
	 * @return array
	 */
	protected function read_latest( callable $read, int $limit, callable $is_window_full ): array {

		$rows = $read( self::LATEST_ORDERS_WINDOW );

		return count( $rows ) < $limit && $is_window_full() ? $read( 0 ) : $rows;
	}

	/**
	 * Whether a UTC window holds more paid orders than the newest-orders window reads.
	 *
	 * @since 3.18.0
	 *
	 * @param array  $bounds        UTC window, `[ start, end ]`, end exclusive.
	 * @param string $checkout_rule The checkout's own rule, the one its window applies, so both count the same orders.
	 *
	 * @return bool
	 */
	protected function has_more_paid_orders( array $bounds, string $checkout_rule ): bool {

		global $wpdb;

		[ $orders ] = $this->get_ticketing_tables();
		$paid       = $this->get_paid_order_rule();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One count for one render.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the ticketing query class; the paid and checkout rules are fixed expressions.
				"SELECT COUNT(*) FROM ( SELECT 1 FROM {$orders} WHERE {$paid} AND {$checkout_rule} AND date_created >= %s AND date_created < %s LIMIT %d ) AS w",
				$bounds[0],
				$bounds[1],
				self::LATEST_ORDERS_WINDOW + 1
			)
		);

		return (int) $count > self::LATEST_ORDERS_WINDOW;
	}

	/**
	 * A latest-attendees row from a ticket joined to its order and attendee.
	 *
	 * @since 3.18.0
	 *
	 * @param array $row Ticket row: `order_id`, `event_id`, `occurrence_id`, `time`, the attendee's
	 *                   `attendee_id`, `first_name`, `last_name` and `email`, and the buyer's, prefixed `buyer_`.
	 *
	 * @return array
	 */
	protected static function to_ticket_attendee( array $row ): array {

		// Checkout files an attendee without an email as anonymous, so the buyer stands in.
		$prefix = $row['attendee_id'] === null ? 'buyer_' : '';

		return [
			'name'          => trim( $row[ $prefix . 'first_name' ] . ' ' . $row[ $prefix . 'last_name' ] ),
			'email'         => (string) $row[ $prefix . 'email' ],
			'event_id'      => (int) $row['event_id'],
			'occurrence_id' => (int) $row['occurrence_id'],
			'time'          => $row['time'],
			'order_id'      => (int) $row['order_id'],
		];
	}

	/**
	 * A latest-orders row from an order.
	 *
	 * @since 3.18.0
	 *
	 * @param object $order Order.
	 *
	 * @return array
	 */
	protected static function to_order_row( $order ): array {

		// The row's columns are dynamic properties, which static analysis can't see on the class.
		$row = get_object_vars( $order );

		return [
			'id'            => (int) $row['id'],
			'name'          => trim( $row['first_name'] . ' ' . $row['last_name'] ),
			'event_id'      => (int) $row['event_id'],
			'occurrence_id' => (int) $row['occurrence_id'],
			'total'         => (float) $row['total'],
			'status'        => (string) $row['status'],
			'time'          => $row['date_created'],
		];
	}

	/**
	 * The ticket attendees table.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	protected function get_attendees_table(): string {

		return ( new Attendee_Query() )->get_table_name();
	}

	/**
	 * Latest ticket attendees from a window of the newest paid checkout orders in a UTC window, or from
	 * every paid order in it.
	 *
	 * @since 3.18.0
	 *
	 * @param int   $limit  Most rows.
	 * @param int   $window Newest orders to read, 0 for every order.
	 * @param array $bounds UTC window, `[ start, end ]`, end exclusive.
	 *
	 * @return array Rows as get_latest_attendees().
	 */
	private function read_ticket_attendees( int $limit, int $window, array $bounds ): array {

		global $wpdb;

		$attendees = $this->get_attendees_table();
		$from      = $this->get_ticket_attendees_from( $window, $bounds );
		$rule      = $this->get_paid_attendee_rule();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Five rows for one render.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from the ticketing query classes; the window is prepared in get_ticket_attendees_from(); the rule is a fixed expression.
				"SELECT o.id AS order_id, t.event_id, t.occurrence_id, o.date_created AS time, a.id AS attendee_id, a.first_name, a.last_name, a.email, o.first_name AS buyer_first_name, o.last_name AS buyer_last_name, o.email AS buyer_email FROM {$from} LEFT JOIN {$attendees} AS a ON a.id = t.attendee_id WHERE {$rule} AND o.date_created >= %s AND o.date_created < %s ORDER BY o.date_created DESC, o.id DESC, t.id DESC LIMIT %d",
				$bounds[0],
				$bounds[1],
				$limit
			),
			ARRAY_A
		);

		return array_map( [ self::class, 'to_ticket_attendee' ], (array) $rows );
	}

	/**
	 * The orders and tickets a ticket-attendees read joins: a window of the newest paid checkout orders in
	 * the bounds, or every order.
	 *
	 * @since 3.18.0
	 *
	 * @param int   $window Newest orders to read, 0 for every order.
	 * @param array $bounds UTC window, `[ start, end ]`, end exclusive.
	 *
	 * @return string
	 */
	private function get_ticket_attendees_from( int $window, array $bounds ): string {

		global $wpdb;

		if ( $window === 0 ) {
			return $this->get_checkout_tickets_from();
		}

		[ $orders ] = $this->get_ticketing_tables();
		$paid       = $this->get_paid_order_rule();
		$checkout   = $this->get_checkout_order_rule();

		$newest = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the ticketing query class; the paid and checkout rules are fixed expressions.
			"( SELECT id, status, date_created, first_name, last_name, email FROM {$orders} WHERE {$paid} AND {$checkout} AND date_created >= %s AND date_created < %s ORDER BY date_created DESC, id DESC LIMIT %d )",
			$bounds[0],
			$bounds[1],
			$window
		);

		// The window joins as binary strings (`order_id` is a varchar), which MySQL hash-joins and no
		// collation setting can reject; every order joins as numbers, as the counts do.
		return $this->get_checkout_tickets_from( $newest, 'CAST( o.id AS BINARY )' );
	}
}
