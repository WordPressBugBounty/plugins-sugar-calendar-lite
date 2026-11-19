<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec;

use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendarTables;

/**
 * Reads the `sc_migrate_tec_*` rows: what each TEC item became, and what this run added.
 *
 * @since 3.18.0
 */
class TecIdLookup {

	/**
	 * Get the highest mapping row ID from before the current run.
	 *
	 * @since 3.18.0
	 *
	 * @param string $context Either `events` or `categories`.
	 *
	 * @return int
	 */
	public static function get_last_mapping_id( $context ) {

		$ids = get_option( TecIdMap::LAST_IDS_OPTION, [] );

		return absint( $ids[ $context ] ?? 0 );
	}

	/**
	 * The Sugar Calendar term a TEC term became.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity      The step entity: `tags` or `categories`.
	 * @param int    $tec_term_id The TEC term ID.
	 *
	 * @return string|null
	 */
	public function get_sc_term_id( $entity, $tec_term_id ) {

		global $wpdb;

		$spec = TecIdMap::spec( $entity );

		return $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prepare( 'SELECT ' . esc_sql( $spec['sc'] ) . ' FROM ' . $wpdb->prefix . esc_sql( $spec['table'] ) . ' WHERE ' . esc_sql( $spec['tec'] ) . ' = %d', $tec_term_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * The category rows this run added.
	 *
	 * @since 3.18.0
	 *
	 * @return object[]
	 */
	public function get_new_categories() {

		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . esc_sql( TecIdMap::CATEGORIES_TABLE ) . ' WHERE id > %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::get_last_mapping_id( 'categories' )
			),
			OBJECT
		);

		foreach ( $results as $row ) {
			$row->id                     = (int) $row->id;
			$row->tec_category_id        = (int) $row->tec_category_id;
			$row->tec_category_parent_id = (int) $row->tec_category_parent_id;
			$row->sc_category_id         = (int) $row->sc_category_id;
		}

		return $results;
	}

	/**
	 * The event rows this run added.
	 *
	 * @since 3.18.0
	 *
	 * @return object[]
	 */
	public function get_new_events() {

		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prepare(
				'SELECT
					tec_event_id, sc_event_id, tec_event_post_id, sc_event_post_id
				FROM
					' . esc_sql( $wpdb->prefix . TecIdMap::EVENTS_TABLE ) . '
				WHERE
					sc_event_id IS NOT NULL AND id > %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::get_last_mapping_id( 'events' )
			)
		);
	}

	/**
	 * The mapping row of a migrated event whose Sugar Calendar event still exists.
	 *
	 * @since 3.18.0
	 *
	 * @param string $by    The column to search by: `tec_event_id`, `tec_event_post_id` or `sc_event_id`.
	 * @param int    $value The value to search for.
	 *
	 * @return object|null|false False for a column it does not search by.
	 */
	public function find_event( $by, $value ) {

		global $wpdb;

		if ( ! in_array( $by, [ 'tec_event_id', 'tec_event_post_id', 'sc_event_id' ], true ) ) {
			return false;
		}

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . esc_sql( TecIdMap::EVENTS_TABLE ) . ' WHERE ' . esc_sql( $by ) . ' = %d' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				. ' AND sc_event_id IN ( SELECT id FROM ' . $wpdb->prefix . 'sc_events ) ORDER BY id ASC',
				$value
			)
		);
	}

	/**
	 * The mapping row of a TEC order, with its Sugar Calendar order's event, status and event start.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_order_id The TEC order ID.
	 *
	 * @return object[]
	 */
	public function find_order( $tec_order_id ) {

		global $wpdb;

		$table = $wpdb->prefix . TecIdMap::ORDERS_TABLE;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prepare(
				'SELECT ' . esc_sql( $table ) . '.*, ' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				. $wpdb->prefix . 'sc_orders.event_id AS sc_event_id, '
				. $wpdb->prefix . 'sc_orders.status AS sc_order_status, '
				. $wpdb->prefix . 'sc_events.start AS sc_event_start FROM '
				. esc_sql( $table )
				. ' LEFT JOIN ' . $wpdb->prefix . 'sc_orders ON '
				. $wpdb->prefix . 'sc_orders.id = ' . esc_sql( $table ) . '.sc_order_id LEFT JOIN '
				. $wpdb->prefix . 'sc_events ON ' . $wpdb->prefix . 'sc_events.id = ' . $wpdb->prefix . 'sc_orders.event_id WHERE tec_order_id = %d',
				$tec_order_id
			)
		);
	}

	/**
	 * The venue a TEC venue became.
	 *
	 * @since 3.18.0
	 *
	 * @param int|string $tec_venue_id The TEC venue ID, empty when the event has none.
	 *
	 * @return int|false False when the TEC venue was not migrated, or no venue ever was.
	 */
	public function get_sc_venue_id( $tec_venue_id ) {

		global $wpdb;

		// The table only exists once a venue step has run, which only Pro adds.
		if ( empty( $tec_venue_id ) || ! TheEventCalendarTables::exists( TecIdMap::VENUES_TABLE ) ) {
			return false;
		}

		$sc_venue_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prepare(
				'SELECT sc_venue_id FROM ' . esc_sql( $wpdb->prefix . TecIdMap::VENUES_TABLE ) . ' WHERE tec_venue_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$tec_venue_id
			)
		);

		return empty( $sc_venue_id ) ? false : absint( $sc_venue_id );
	}

	/**
	 * The number of events this run created.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function count_new_events() {

		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $wpdb->prefix . esc_sql( TecIdMap::EVENTS_TABLE ) . ' WHERE sc_event_id > 0 AND id > %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::get_last_mapping_id( 'events' )
			)
		);
	}

	/**
	 * Get the highest event and category mapping row IDs.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_last_mapping_ids() {

		global $wpdb;

		return [
			'events'     => (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . $wpdb->prefix . esc_sql( TecIdMap::EVENTS_TABLE ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			'categories' => (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . $wpdb->prefix . esc_sql( TecIdMap::CATEGORIES_TABLE ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		];
	}
}
