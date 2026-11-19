<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec;

use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendarMappings;
use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendarTables;

/**
 * Finds the TEC items not migrated yet, straight from the database, since TEC is often already inactive.
 *
 * @since 3.18.0
 */
class TecReader {

	/**
	 * Whether each mapping table exists, by prefixed name, once checked.
	 *
	 * @since 3.18.0
	 *
	 * @var bool[]
	 */
	private $tables = [];

	/**
	 * The TEC post type of each post-based step entity.
	 *
	 * @since 3.18.0
	 *
	 * @var string[]
	 */
	const POST_TYPES = [
		'venues'    => 'tribe_venue',
		'tickets'   => 'tec_tc_ticket',
		'orders'    => 'tec_tc_order',
		'attendees' => 'tec_tc_attendee',
	];

	/**
	 * The TEC taxonomies that migrate, by step entity.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	const TERMS = [
		'tags'       => [
			'taxonomy' => 'post_tag',
			'alias'    => 'mtt',
			'select'   => 't.term_id, t.name, t.slug',
			'group_by' => ' GROUP BY t.term_id ',
		],
		'categories' => [
			'taxonomy' => 'tribe_events_cat',
			'alias'    => 'mtc',
			'select'   => 't.term_id, t.name, t.slug, tt.parent',
			'group_by' => '',
		],
	];

	/**
	 * The TEC terms of one taxonomy not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity     The step entity: `tags` or `categories`.
	 * @param bool   $count_only Whether to return the count only.
	 * @param int    $limit      The batch size, unused for a count.
	 *
	 * @return array
	 */
	public function get_unmigrated_terms( $entity, $count_only, $limit ) {

		global $wpdb;

		$spec = self::TERMS[ $entity ];

		list( $join, $where ) = $this->get_mapping_sql( $entity, 't.term_id', $spec['alias'] );

		$select   = $count_only ? 'SELECT COUNT(t.term_id) AS context_to_import_count' : 'SELECT ' . $spec['select'];
		$group_by = $count_only ? '' : $spec['group_by'];
		$limit    = $count_only ? '' : $wpdb->prepare( 'LIMIT %d', $limit );

		// Query is static SQL plus esc_sql()'d clause fragments (importer, TEC tables).
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $join and $where are fixed SQL from get_mapping_sql().
			esc_sql( $select ) . ' FROM ' . $wpdb->terms . ' AS t'
			. ' INNER JOIN ' . $wpdb->term_taxonomy . ' AS tt ON t.term_id = tt.term_id'
			. $join
			. ' WHERE tt.taxonomy = "' . esc_sql( $spec['taxonomy'] ) . '"'
			. $where . ' '
			. esc_sql( $group_by )
			. esc_sql( $limit )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * The TEC venues not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 * @param int  $limit      The batch size, unused for a count.
	 *
	 * @return array
	 */
	public function get_unmigrated_venues( $count_only, $limit ) {

		return $this->get_unmigrated_posts( 'venues', $count_only ? null : $limit );
	}

	/**
	 * The TEC tickets not migrated yet, oldest first, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 * @param int  $limit      The batch size, unused for a count.
	 *
	 * @return array
	 */
	public function get_unmigrated_tickets( $count_only, $limit ) {

		return $this->get_unmigrated_posts( 'tickets', $count_only ? null : $limit );
	}

	/**
	 * The TEC orders not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 * @param int  $limit      The batch size, unused for a count.
	 *
	 * @return array
	 */
	public function get_unmigrated_orders( $count_only, $limit ) {

		return $this->get_unmigrated_posts( 'orders', $count_only ? null : $limit, '', self::get_paid_order_condition() );
	}

	/**
	 * The published TEC ticket attendees or RSVP responses not migrated yet, oldest first, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 * @param int  $limit      The batch size, unused for a count.
	 * @param bool $rsvp       True for RSVP responses, false for ticket attendees.
	 *
	 * @return array
	 */
	public function get_unmigrated_attendees( $count_only, $limit, $rsvp ) {

		global $wpdb;

		return $this->get_unmigrated_posts(
			'attendees',
			$count_only ? null : $limit,
			self::get_ticket_type_join(),
			' AND ' . $wpdb->posts . '.post_status = "publish"' . self::get_rsvp_condition( $rsvp )
		);
	}

	/**
	 * The TEC posts of one entity not migrated yet, oldest first, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param string   $entity The step entity: `venues`, `tickets`, `orders` or `attendees`.
	 * @param int|null $limit  The batch size, null for a count.
	 * @param string   $join   Extra joins, after the mapping table's.
	 * @param string   $where  Extra conditions, each starting with AND.
	 *
	 * @return array
	 */
	private function get_unmigrated_posts( $entity, $limit, $join = '', $where = '' ) {

		global $wpdb;

		list( $map_join, $map_where ) = $this->get_mapping_sql( $entity, $wpdb->posts . '.ID' );

		$select = $limit === null ? 'SELECT COUNT(' . $wpdb->posts . '.ID) AS context_to_import_count' : 'SELECT ' . $wpdb->posts . '.ID';
		$limit  = $limit === null ? '' : $wpdb->prepare( 'LIMIT %d', $limit );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The fragments are fixed SQL from this class.
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			esc_sql( $select ) . ' FROM ' . $wpdb->posts
			. $map_join . $join
			. ' WHERE ' . $wpdb->posts . '.post_type = "' . esc_sql( self::POST_TYPES[ $entity ] ) . '"'
			. $map_where . $where
			. ' ORDER BY ' . $wpdb->posts . '.ID ASC ' . esc_sql( $limit )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * The join and condition that leave out the items an entity's mapping table already holds.
	 *
	 * Nothing is migrated, so nothing is left out, while the mapping table does not exist yet:
	 * `TecSource::begin()` creates it, a read never does. The existence check runs once per table.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity The step entity.
	 * @param string $key    The column or expression holding the TEC ID, or the TEC events table alias for `events`.
	 * @param string $alias  The mapping table's alias, its own name when empty.
	 *
	 * @return string[] The LEFT JOIN and the AND condition, both empty without the table.
	 */
	private function get_mapping_sql( $entity, $key, $alias = '' ) {

		global $wpdb;

		$spec = TecIdMap::spec( $entity );

		if ( $spec === null ) {
			return [ '', '' ];
		}

		$table = $wpdb->prefix . $spec['table'];

		$this->tables[ $table ] = $this->tables[ $table ] ?? TheEventCalendarTables::exists( $spec['table'] );

		if ( ! $this->tables[ $table ] ) {
			return [ '', '' ];
		}

		$name = $alias === '' ? $table : esc_sql( $alias );
		$none = ' AND ' . $name . '.id IS NULL';

		if ( $entity === 'events' ) {
			return [ TheEventCalendarMappings::get_unmigrated_join( $key ), $none ];
		}

		return [ ' LEFT JOIN ' . esc_sql( $table ) . ( $alias === '' ? '' : ' AS ' . $name ) . ' ON ' . $name . '.' . esc_sql( $spec['tec'] ) . ' = ' . $key, $none ];
	}

	/**
	 * The number of TEC ticket holders, by email, with no Sugar Calendar attendee yet; RSVPs left out.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function count_people_to_migrate() {

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The RSVP fragments are fixed SQL from this class.
		return absint(
			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
				'SELECT COUNT(*) FROM ' .
				'(SELECT DISTINCT(' . $wpdb->postmeta . '.meta_value) AS tec_email FROM ' . $wpdb->posts
				. ' LEFT JOIN ' . $wpdb->postmeta . ' ON ' . $wpdb->postmeta . '.post_id = ' . $wpdb->posts . '.ID AND '
				. $wpdb->postmeta . ".meta_key = '_tec_tickets_commerce_email'" . self::get_ticket_type_join() . ' WHERE ' . $wpdb->posts . ".post_type = 'tec_tc_attendee'" . self::get_rsvp_condition( false ) . ') tec_attendees'
				. ' LEFT JOIN ' . $wpdb->prefix . 'sc_attendees ON ' . $wpdb->prefix . 'sc_attendees.email = tec_attendees.tec_email'
				. ' WHERE ' . $wpdb->prefix . 'sc_attendees.id IS NULL'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * The TEC events not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 * @param int  $limit      The batch size, unused for a count.
	 *
	 * @return array
	 */
	public function get_unmigrated_events( $count_only, $limit ) {

		global $wpdb;

		$events = $wpdb->prefix . 'tec_events';
		$select = $count_only ? 'SELECT COUNT(' . $events . '.event_id) AS context_to_import_count' : 'SELECT ' . $events . '.event_id, ' . $events . '.post_id';
		$limit  = $count_only ? '' : $wpdb->prepare( 'LIMIT %d', $limit );

		list( $join, $where ) = $this->get_mapping_sql( 'events', $events );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The fragments are fixed SQL from this class.
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			esc_sql( $select ) . ' FROM ' . TheEventCalendarMappings::get_events_sql() . ' AS ' . $events
			. $join
			. ' LEFT JOIN ' . $wpdb->posts . ' ON ' . $wpdb->posts . '.ID = ' . esc_sql( $events ) . '.post_id'
			. ' WHERE ' . $wpdb->posts . '.ID IS NOT NULL AND ' . $wpdb->posts . '.post_type = "tribe_events"'
			. $where . ' ' . esc_sql( $limit )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get a TEC event's tickets from providers the importer doesn't migrate, for the summary.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_event_post_id TEC event post ID.
	 *
	 * @return array[] Each ticket's `id` and summary `name`.
	 */
	public function get_other_provider_tickets( $tec_event_post_id ) {

		global $wpdb;

		$providers = $this->get_other_providers();
		$keys      = array_keys( $providers );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, pm.meta_key FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE pm.meta_value = %d AND pm.meta_key IN (" . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ") AND p.post_status <> 'trash' ORDER BY p.ID", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				array_merge( [ $tec_event_post_id ], $keys )
			)
		);

		return array_map(
			static function ( $row ) use ( $providers ) {

				return [
					'id'   => (int) $row->ID,
					/* translators: %1$s: ticket name, %2$s: kind of ticket, such as WooCommerce ticket. */
					'name' => sprintf( __( '%1$s (%2$s)', 'sugar-calendar-lite' ), $row->post_title, $providers[ $row->meta_key ] ),
				];
			},
			$rows
		);
	}

	/**
	 * Get the ticket meta keys of the providers the importer doesn't migrate, with what to call them.
	 *
	 * @since 3.18.0
	 *
	 * @return string[]
	 */
	private function get_other_providers() {

		return [
			'_tribe_wooticket_for_event' => __( 'WooCommerce ticket', 'sugar-calendar-lite' ),
			'_tribe_eddticket_for_event' => __( 'Easy Digital Downloads ticket', 'sugar-calendar-lite' ),
			'_tribe_tpp_for_event'       => __( 'PayPal ticket from an older version of Event Tickets', 'sugar-calendar-lite' ),
			'_tribe_rsvp_for_event'      => __( 'RSVP from an older version of Event Tickets', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Get the TEC RSVP tickets the migration left unmigrated.
	 *
	 * @since 3.18.0
	 *
	 * @return int[]
	 */
	public function get_unmigrated_rsvp_ticket_ids() {

		global $wpdb;

		$tickets = $wpdb->prefix . TecIdMap::TICKETS_TABLE;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return array_map(
			'absint',
			$wpdb->get_col(
				"SELECT {$tickets}.tec_ticket_id FROM {$tickets}
				INNER JOIN {$wpdb->postmeta} ON {$wpdb->postmeta}.post_id = {$tickets}.tec_ticket_id
					AND {$wpdb->postmeta}.meta_key = '_type' AND {$wpdb->postmeta}.meta_value = 'tc-rsvp'
				WHERE {$tickets}.is_migrated = 0"
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Join a TEC attendee post to its ticket's `_type`, which is `tc-rsvp` for an RSVP.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	private static function get_ticket_type_join() {

		global $wpdb;

		return " LEFT JOIN {$wpdb->postmeta} AS sc_attendee_ticket ON sc_attendee_ticket.post_id = {$wpdb->posts}.ID AND sc_attendee_ticket.meta_key = '_tec_tickets_commerce_ticket'"
			. " LEFT JOIN {$wpdb->postmeta} AS sc_ticket_type ON sc_ticket_type.post_id = sc_attendee_ticket.meta_value AND sc_ticket_type.meta_key = '_type'";
	}

	/**
	 * Keep RSVP responses only, or leave them out; a ticket without `_type` is a regular ticket, as Event Tickets reads it.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $rsvp True for RSVP responses only, false to leave them out.
	 *
	 * @return string
	 */
	private static function get_rsvp_condition( $rsvp ) {

		return $rsvp
			? " AND sc_ticket_type.meta_value = 'tc-rsvp'"
			: " AND ( sc_ticket_type.meta_value IS NULL OR sc_ticket_type.meta_value <> 'tc-rsvp' )";
	}

	/**
	 * Leave out TEC orders that hold only RSVPs: nothing was paid, and their responses migrate as RSVPs.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	private static function get_paid_order_condition() {

		global $wpdb;

		$in_order = "SELECT 1 FROM {$wpdb->postmeta} AS sc_in_order";
		$is_order = "sc_in_order.post_id = {$wpdb->posts}.ID AND sc_in_order.meta_key = '_tec_tc_order_tickets_in_order'";

		return " AND ( NOT EXISTS ( {$in_order} WHERE {$is_order} )"
			. " OR EXISTS ( {$in_order} LEFT JOIN {$wpdb->postmeta} AS sc_order_type ON sc_order_type.post_id = sc_in_order.meta_value AND sc_order_type.meta_key = '_type'"
			. " WHERE {$is_order} AND ( sc_order_type.meta_value IS NULL OR sc_order_type.meta_value <> 'tc-rsvp' ) ) )";
	}
}
