<?php
/**
 * Paid-order totals for the `tickets` signup mode's stats.
 *
 * @since 3.17.0
 */

namespace Sugar_Calendar\AddOn\Ticketing\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Database\Order_Query;

/**
 * TicketOrderTotals — counts paid orders and their revenue straight from the orders table.
 *
 * Core reads the table itself so the numbers do not depend on the Event Ticketing add-on.
 *
 * @since 3.17.0
 */
class TicketOrderTotals {

	/**
	 * Paid-order count and revenue for one window.
	 *
	 * `date_created` is written with gmdate(), so it is UTC and compares directly against the
	 * UTC bounds passed in. The window is half-open so two adjacent windows cannot both claim
	 * one order.
	 *
	 * @since 3.17.0
	 *
	 * @param string $start Window start, UTC 'Y-m-d H:i:s', inclusive.
	 * @param string $end   Window end, UTC 'Y-m-d H:i:s', exclusive.
	 *
	 * @return array { count: int, revenue: float }
	 */
	public function window_totals( string $start, string $end ): array {

		global $wpdb;

		$orders_table = ( new Order_Query() )->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS order_count, SUM(`total`) AS revenue FROM {$orders_table} WHERE `status` = %s AND `date_created` >= %s AND `date_created` < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'paid',
				$start,
				$end
			),
			ARRAY_A
		);

		return $this->totals( $row );
	}

	/**
	 * Paid-order count and revenue for one window, counting only the given events' orders.
	 *
	 * @since 3.17.0
	 *
	 * @param string $start     Window start, UTC 'Y-m-d H:i:s', inclusive.
	 * @param string $end       Window end, UTC 'Y-m-d H:i:s', exclusive.
	 * @param int[]  $event_ids Sugar Calendar event ids. A series' parent id covers all its dates.
	 *
	 * @return array { count: int, revenue: float }
	 */
	public function window_totals_for_events( string $start, string $end, array $event_ids ): array {

		global $wpdb;

		$event_ids = array_values( array_unique( array_filter( array_map( 'absint', $event_ids ) ) ) );

		// An empty IN () is invalid SQL, and no events means nothing to count.
		if ( empty( $event_ids ) ) {
			return $this->totals( null );
		}

		$orders_table = ( new Order_Query() )->get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $event_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS order_count, SUM(`total`) AS revenue FROM {$orders_table} WHERE `status` = %s AND `date_created` >= %s AND `date_created` < %s AND `event_id` IN ( {$placeholders} )",
				array_merge( [ 'paid', $start, $end ], $event_ids )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return $this->totals( $row );
	}

	/**
	 * Map an aggregate row to the totals shape.
	 *
	 * @since 3.17.0
	 *
	 * @param mixed $row Row from `$wpdb->get_row( ..., ARRAY_A )`, or null.
	 *
	 * @return array { count: int, revenue: float }
	 */
	private function totals( $row ): array {

		return [
			'count'   => isset( $row['order_count'] ) ? absint( $row['order_count'] ) : 0,
			'revenue' => isset( $row['revenue'] ) ? round( (float) $row['revenue'], 2 ) : 0.0,
		];
	}
}
