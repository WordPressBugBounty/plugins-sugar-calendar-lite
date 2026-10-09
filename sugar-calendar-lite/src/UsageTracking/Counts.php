<?php

namespace Sugar_Calendar\UsageTracking;

/**
 * Cheap COUNT(*) helpers for usage-tracking snapshot keys.
 *
 * Every snapshot value must be one indexed count or an option read, so these
 * deliberately return a single integer and never rows.
 *
 * `$table` and `$where` are developer-authored SQL literals; never pass request data.
 *
 * @since 3.15.0
 */
final class Counts {

	/**
	 * Count events matching a WHERE fragment.
	 *
	 * @since 3.15.0
	 *
	 * @param string $where SQL fragment for the WHERE clause, with %s/%d placeholders when $args is given.
	 * @param array  $args  Values for the placeholders.
	 *
	 * @return int
	 */
	public static function events_where( $where, array $args = [] ) {

		return self::rows( 'sc_events', $where, $args );
	}

	/**
	 * Count distinct events carrying an exact event-meta value.
	 *
	 * @since 3.15.0
	 *
	 * @param string $meta_key   Event meta key.
	 * @param string $meta_value Exact meta value to match.
	 *
	 * @return int
	 */
	public static function events_with_meta( $meta_key, $meta_value ) {

		global $wpdb;

		$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT sc_event_id ) FROM {$wpdb->prefix}sc_eventmeta WHERE meta_key = %s AND meta_value = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_key,
				$meta_value
			)
		);

		return absint( $count );
	}

	/**
	 * Count distinct events whose event-meta value is truthy (not '' and not '0').
	 *
	 * @since 3.15.0
	 *
	 * @param string $meta_key Event meta key.
	 *
	 * @return int
	 */
	public static function events_with_truthy_meta( $meta_key ) {

		global $wpdb;

		$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT sc_event_id ) FROM {$wpdb->prefix}sc_eventmeta WHERE meta_key = %s AND meta_value NOT IN ( '', '0' )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_key
			)
		);

		return absint( $count );
	}

	/**
	 * Count rows in a plugin table.
	 *
	 * @since 3.15.0
	 *
	 * @param string $table Table name without the WP prefix, e.g. 'sc_orders'.
	 * @param string $where Optional SQL fragment for the WHERE clause.
	 * @param array  $args  Values for the placeholders in $where.
	 *
	 * @return int
	 */
	public static function rows( $table, $where = '', array $args = [] ) {

		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}{$table}";

		if ( $where !== '' ) {
			$sql .= " WHERE {$where}";
		}

		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$count = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		return absint( $count );
	}
}
