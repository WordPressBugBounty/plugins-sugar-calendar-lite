<?php

namespace Sugar_Calendar\Admin\Dashboard;

use DateTimeImmutable;
use DateTimeZone;
use Sugar_Calendar\Event_Query;
use Sugar_Calendar\SetupChecklist\Config;

/**
 * The numbers the stat cards, graph and Orders metrics show, per day of a `DateRange`.
 *
 * Lite counts event rows, tickets and orders; the Pro subclass adds recurring dates, WooCommerce
 * tickets and RSVP people.
 *
 * @since 3.18.0
 */
class Stats {

	use TicketingTables;

	/**
	 * Minutes in the UTC slot the order and attendee readers total by (see get_slot_sql()).
	 *
	 * @since 3.18.0
	 */
	const SLOT_MINUTES = 15;

	/**
	 * Per-day rows already read this request, keyed by range.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $daily_rows = [];

	/**
	 * Everything the range-dependent widgets render from.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array
	 */
	public function get_widget_data( array $range ): array {

		$totals = $this->get_stat_totals( $range );
		$deltas = $this->get_stat_deltas( $range );

		return [
			'date_range'     => $range,
			'stat_totals'    => $totals,
			'stat_deltas'    => $deltas,
			'series'         => $this->get_series( $range ),
			'orders_metrics' => [
				'payments' => [
					'value' => $totals['orders'],
					'delta' => $deltas['orders'],
				],
				'sales'    => [
					'value' => $totals['revenue'],
					'delta' => $deltas['revenue'],
				],
			],
		];
	}

	/**
	 * Totals for a range.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array `events`, `attendees`, `orders` (int) and `revenue` (float).
	 */
	public function get_stat_totals( array $range ): array {

		$rows = $this->get_daily_rows( $range );

		return [
			'events'    => (int) array_sum( array_column( $rows, 'events' ) ),
			'attendees' => (int) array_sum( array_column( $rows, 'attendees' ) ),
			'orders'    => (int) array_sum( array_column( $rows, 'orders' ) ),
			'revenue'   => (float) array_sum( array_column( $rows, 'revenue' ) ),
		];
	}

	/**
	 * Percent change of each total against the previous period of the same length.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array Same keys as get_stat_totals(), float percents.
	 */
	public function get_stat_deltas( array $range ): array {

		$current  = $this->get_stat_totals( $range );
		$previous = $this->get_stat_totals( DateRange::get_previous_range( $range ) );
		$deltas   = [];

		foreach ( $current as $key => $value ) {
			$deltas[ $key ] = self::get_delta( $value, $previous[ $key ] );
		}

		return $deltas;
	}

	/**
	 * Graph points, one per day.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array Rows: `label`, `tooltip` (in Sugar Calendar's date format, as the tables), `attendees`, `payments` (orders) and `sales` (revenue).
	 */
	public function get_series( array $range ): array {

		$rows   = [];
		$zone   = wp_timezone();
		$format = sc_get_date_format();

		foreach ( $this->get_daily_rows( $range ) as $row ) {
			$timestamp = ( new DateTimeImmutable( $row['date'], $zone ) )->getTimestamp();

			// The axis stays short: a long date format would crowd a 30-day range.
			$rows[] = [
				'label'     => wp_date( 'M j', $timestamp ),
				'tooltip'   => wp_date( $format, $timestamp ),
				'attendees' => $row['attendees'],
				'payments'  => $row['orders'],
				'sales'     => round( $row['revenue'], 2 ),
			];
		}

		return $rows;
	}

	/**
	 * Percent change from the previous period's total.
	 *
	 * @since 3.18.0
	 *
	 * @param int|float $current  Current period total.
	 * @param int|float $previous Previous period total.
	 *
	 * @return float +100 when only the current period has data, 0 when both are empty.
	 */
	public static function get_delta( $current, $previous ): float {

		if ( (float) $previous === 0.0 ) {
			return (float) $current > 0 ? 100.0 : 0.0;
		}

		return ( (float) $current - (float) $previous ) / (float) $previous * 100;
	}

	/**
	 * Per-day totals for a range.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array Rows: `date`, `events`, `attendees`, `orders` and `revenue`.
	 */
	protected function get_daily_rows( array $range ): array {

		$key = implode( ' - ', $range );

		if ( ! isset( $this->daily_rows[ $key ] ) ) {
			$this->daily_rows[ $key ] = $this->read_daily_rows( $range );
		}

		return $this->daily_rows[ $key ];
	}

	/**
	 * Event dates per day: `sc_event` rows with a saved status whose start, as entered, is in the range.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array `Y-m-d` => count.
	 */
	protected function get_event_counts( array $range ): array {

		global $wpdb;

		$table        = ( new Event_Query() )->get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( Config::SAVED_POST_STATUSES ), '%s' ) );
		$sql          = "SELECT LEFT( `start`, 10 ) AS day, COUNT(*) AS n FROM {$table} WHERE `object_type` = 'post' AND `object_subtype` = 'sc_event' AND `status` IN ( {$placeholders} ) AND `start` BETWEEN %s AND %s GROUP BY day"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the events query class; the placeholder run holds only %s tokens.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Every value is passed to prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( Config::SAVED_POST_STATUSES, $this->get_local_bounds( $range ) ) ) );

		return wp_list_pluck( (array) $rows, 'n', 'day' );
	}

	/**
	 * Attendee records in a UTC window: active tickets on paid orders, dated by the order.
	 *
	 * @since 3.18.0
	 *
	 * @param string $start Window start, UTC `Y-m-d H:i:s`, inclusive.
	 * @param string $end   Window end, UTC `Y-m-d H:i:s`, exclusive.
	 *
	 * @return array Rows: `time` (a UTC slot, see get_slot_sql()) and `count`.
	 */
	protected function get_attendee_records( string $start, string $end ): array {

		global $wpdb;

		$from = $this->get_checkout_tickets_from();
		$rule = $this->get_paid_attendee_rule();
		$slot = self::get_slot_sql( 'o.date_created' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A range total, kept for the request by get_daily_rows().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from the ticketing query classes; the rule and the slot are fixed expressions.
				"SELECT {$slot} AS time, COUNT( t.id ) AS count FROM {$from} WHERE {$rule} AND o.date_created >= %s AND o.date_created < %s GROUP BY time",
				$start,
				$end
			),
			ARRAY_A
		);

		return (array) $rows;
	}

	/**
	 * Paid orders in a UTC window, $0 orders and WooCommerce rows included.
	 *
	 * @since 3.18.0
	 *
	 * @param string $start Window start, UTC `Y-m-d H:i:s`, inclusive.
	 * @param string $end   Window end, UTC `Y-m-d H:i:s`, exclusive.
	 *
	 * @return array Rows: `time` (a UTC slot, see get_slot_sql()), `count` and `total`.
	 */
	protected function get_order_records( string $start, string $end ): array {

		global $wpdb;

		[ $orders ] = $this->get_ticketing_tables();
		$slot       = self::get_slot_sql( 'date_created' );
		$paid       = $this->get_paid_order_rule();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A range total, kept for the request by get_daily_rows().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the ticketing query class; the slot and the paid rule are fixed expressions.
				"SELECT {$slot} AS time, COUNT(*) AS count, SUM( total ) AS total FROM {$orders} WHERE {$paid} AND date_created >= %s AND date_created < %s GROUP BY time",
				$start,
				$end
			),
			ARRAY_A
		);

		return (array) $rows;
	}

	/**
	 * The range's first and last second as entered, for the wall-clock event times.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array `[ start, end ]`, `Y-m-d H:i:s`, both inclusive.
	 */
	protected function get_local_bounds( array $range ): array {

		return [ $range[0] . ' 00:00:00', $range[1] . ' 23:59:59' ];
	}

	/**
	 * SQL for the UTC quarter hour (SLOT_MINUTES) a datetime column falls in, `Y-m-d H:i`.
	 *
	 * Totalling per quarter hour keeps a year's rows bounded, and every UTC offset in use is a
	 * whole number of quarter hours, so no slot straddles a local midnight.
	 *
	 * @since 3.18.0
	 *
	 * @param string $column Datetime column, e.g. `o.date_created`.
	 *
	 * @return string
	 */
	protected static function get_slot_sql( string $column ): string {

		$minutes = self::SLOT_MINUTES;

		return "CONCAT( LEFT( {$column}, 14 ), LPAD( FLOOR( MINUTE( {$column} ) / {$minutes} ) * {$minutes}, 2, '0' ) )";
	}

	/**
	 * Zero-filled rows for every day of a range, filled from each source.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array Rows: `date`, `events`, `attendees`, `orders` and `revenue`.
	 */
	private function read_daily_rows( array $range ): array {

		$rows = $this->get_empty_rows( $range );

		foreach ( $this->get_event_counts( $range ) as $date => $count ) {
			if ( isset( $rows[ $date ] ) ) {
				$rows[ $date ]['events'] += (int) $count;
			}
		}

		[ $start, $end ] = DateRange::get_utc_window( $range );

		$this->add_records( $rows, $this->get_attendee_records( $start, $end ), [ 'attendees' => 'count' ] );
		$this->add_records(
			$rows,
			$this->get_order_records( $start, $end ),
			[
				'orders'  => 'count',
				'revenue' => 'total',
			]
		);

		return array_values( $rows );
	}

	/**
	 * A zero row for every day of a range.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array Rows keyed by `Y-m-d`.
	 */
	private function get_empty_rows( array $range ): array {

		// Calendar dates, stepped in UTC: a local midnight can be missing on a DST day.
		$rows = [];
		$day  = new DateTimeImmutable( $range[0], new DateTimeZone( 'UTC' ) );
		$last = new DateTimeImmutable( $range[1], new DateTimeZone( 'UTC' ) );

		while ( $day <= $last ) {
			$rows[ $day->format( 'Y-m-d' ) ] = [
				'date'      => $day->format( 'Y-m-d' ),
				'events'    => 0,
				'attendees' => 0,
				'orders'    => 0,
				'revenue'   => 0.0,
			];

			$day = $day->modify( '+1 day' );
		}

		return $rows;
	}

	/**
	 * Add UTC-dated records to the local day each falls on.
	 *
	 * @since 3.18.0
	 *
	 * @param array $rows    Rows keyed by `Y-m-d`, changed in place.
	 * @param array $records Records with a UTC `time`.
	 * @param array $fields  Row field => record field; `total` adds money, anything else a count.
	 */
	private function add_records( array &$rows, array $records, array $fields ): void {

		$utc  = new DateTimeZone( 'UTC' );
		$zone = wp_timezone();

		foreach ( $records as $record ) {
			$date = ( new DateTimeImmutable( $record['time'], $utc ) )->setTimezone( $zone )->format( 'Y-m-d' );

			if ( ! isset( $rows[ $date ] ) ) {
				continue;
			}

			foreach ( $fields as $field => $key ) {
				$rows[ $date ][ $field ] += $key === 'total' ? (float) $record[ $key ] : (int) $record[ $key ];
			}
		}
	}
}
