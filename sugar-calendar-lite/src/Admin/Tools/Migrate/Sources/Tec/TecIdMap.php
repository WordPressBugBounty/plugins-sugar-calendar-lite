<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec;

use Sugar_Calendar\Admin\Tools\Migrate\IdMap;

/**
 * Writes the `sc_migrate_tec_*` tables, where a `-1` Sugar Calendar ID means the item is being migrated.
 *
 * `ENTITIES` is the one spec of those tables: their names, their TEC and Sugar Calendar
 * columns, and the DDL `TheEventCalendarTables` creates. `TecIdLookup` reads them.
 *
 * @since 3.18.0
 */
class TecIdMap implements IdMap {

	/**
	 * The option that links TEC tickets to the ticket types they became.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	const TICKET_TYPES_OPTION = 'sugar_calendar_tec_migration_ticket_types';

	/**
	 * The option that holds the highest mapping row IDs from before the current run.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	const LAST_IDS_OPTION = 'sugar_calendar_tec_migration_last_ids';

	/**
	 * `sc_event_id` of an event mapping row whose migration is under way.
	 *
	 * Left behind only when the request dies, so the retry takes the event up again.
	 * Earlier versions left NULL for a failed event; that, and 0, still mean done.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	const MIGRATING_ID = -1;

	/**
	 * DB table used to keep track of the events migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const EVENTS_TABLE = 'sc_migrate_tec_events';

	/**
	 * DB table used to keep track of the categories migrated.
	 *
	 * @since 3.6.0
	 *
	 * @var string
	 */
	const CATEGORIES_TABLE = 'sc_migrate_tec_categories';

	/**
	 * DB table used to keep track of the tickets migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const TICKETS_TABLE = 'sc_migrate_tec_tickets';

	/**
	 * DB table used to keep track of the attendees migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const ATTENDEES_TABLE = 'sc_migrate_tec_attendees';

	/**
	 * DB table used to keep track of the orders migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const ORDERS_TABLE = 'sc_migrate_tec_orders';

	/**
	 * DB table used to keep track of the venues migrated.
	 *
	 * @since 3.6.0
	 *
	 * @var string
	 */
	const VENUES_TABLE = 'sc_migrate_tec_venues';

	/**
	 * DB table used to keep track of the tags migrated.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const TAGS_TABLE = 'sc_migrate_tec_tags';

	/**
	 * Each entity's mapping table.
	 *
	 * `tec` and `sc` are the TEC and Sugar Calendar ID columns (`sc` is null when the table has none),
	 * `columns` all columns after `id`, in table order, and `key` an extra index.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	const ENTITIES = [
		'events'     => [
			'table'   => self::EVENTS_TABLE,
			'tec'     => 'tec_event_id',
			'sc'      => 'sc_event_id',
			'columns' => [ 'tec_event_id', 'tec_event_post_id', 'sc_event_id', 'sc_event_post_id' ],
			'key'     => 'tec_event_id',
		],
		'categories' => [
			'table'   => self::CATEGORIES_TABLE,
			'tec'     => 'tec_category_id',
			'sc'      => 'sc_category_id',
			'columns' => [ 'tec_category_id', 'tec_category_parent_id', 'sc_category_id' ],
			'key'     => null,
		],
		'tickets'    => [
			'table'   => self::TICKETS_TABLE,
			'tec'     => 'tec_ticket_id',
			'sc'      => 'sc_event_id',
			'columns' => [ 'tec_ticket_id', 'sc_event_id', 'is_migrated' ],
			'key'     => null,
		],
		'orders'     => [
			'table'   => self::ORDERS_TABLE,
			'tec'     => 'tec_order_id',
			'sc'      => 'sc_order_id',
			'columns' => [ 'tec_order_id', 'sc_order_id' ],
			'key'     => null,
		],
		'attendees'  => [
			'table'   => self::ATTENDEES_TABLE,
			'tec'     => 'tec_attendees_id',
			'sc'      => null,
			'columns' => [ 'tec_attendees_id' ],
			'key'     => null,
		],
		'venues'     => [
			'table'   => self::VENUES_TABLE,
			'tec'     => 'tec_venue_id',
			'sc'      => 'sc_venue_id',
			'columns' => [ 'tec_venue_id', 'sc_venue_id' ],
			'key'     => null,
		],
		'tags'       => [
			'table'   => self::TAGS_TABLE,
			'tec'     => 'tec_tag_id',
			'sc'      => 'sc_tag_id',
			'columns' => [ 'tec_tag_id', 'sc_tag_id' ],
			'key'     => null,
		],
	];

	/**
	 * The spec of an entity's mapping table. RSVPs share the attendees table.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity The step entity.
	 *
	 * @return array|null Null for an entity with no mapping table, such as one an add-on step brings.
	 */
	public static function spec( $entity ) {

		return self::ENTITIES[ $entity === 'rsvps' ? 'attendees' : $entity ] ?? null;
	}

	/**
	 * Add the `-1` row of an item before its step runs.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity    The step's entity.
	 * @param int    $source_id The item's TEC ID.
	 */
	public function start( $entity, $source_id ) {

		global $wpdb;

		// Tags have no `-1` row: the tag step writes its row once the tag exists.
		if ( $entity === 'tags' ) {
			return;
		}

		$spec = self::spec( $entity );

		if ( $spec === null ) {
			return;
		}

		$row = [ $spec['tec'] => $source_id ];

		// Only an event's row is marked `-1`; the others stay NULL until saved, as they always have.
		if ( $entity === 'events' ) {
			$row[ $spec['sc'] ] = self::MIGRATING_ID;

			// The row an interrupted attempt left, so the retry doesn't add a second.
			$wpdb->delete( $wpdb->prefix . $spec['table'], $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
		}

		$this->insert_row( $spec['table'], $row );
	}

	/**
	 * Mark an item as not migrated.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity    The step's entity.
	 * @param int    $source_id The item's TEC ID.
	 */
	public function fail( $entity, $source_id ) {

		global $wpdb;

		// Only an event's row records the failure, so no later batch picks it up again; the other rows keep `-1`, as they always have.
		if ( $entity !== 'events' ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $wpdb->prefix . esc_sql( self::EVENTS_TABLE ) . ' SET sc_event_id = 0 WHERE tec_event_id = %d AND sc_event_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$source_id,
				self::MIGRATING_ID
			)
		);
	}

	/**
	 * Record the tag a TEC tag became.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_tag_id The TEC tag ID.
	 * @param int $sc_tag_id  The Sugar Calendar tag ID.
	 *
	 * @return int|false
	 */
	public function save_tag( $tec_tag_id, $sc_tag_id ) {

		return $this->insert_row(
			self::TAGS_TABLE,
			[
				'tec_tag_id' => $tec_tag_id,
				'sc_tag_id'  => $sc_tag_id,
			]
		);
	}

	/**
	 * Record what a TEC item became, on its row.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity  The step entity.
	 * @param int    $tec_id  The TEC item ID.
	 * @param array  $columns The columns to set, all integers.
	 *
	 * @return int|false
	 */
	public function save( $entity, $tec_id, $columns ) {

		global $wpdb;

		$spec = self::spec( $entity );

		if ( $spec === null ) {
			return false;
		}

		return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prefix . $spec['table'],
			$columns,
			[ $spec['tec'] => $tec_id ],
			array_fill( 0, count( $columns ), '%d' ),
			[ '%d' ]
		);
	}

	/**
	 * Record what a TEC ticket became.
	 *
	 * SC can only have 1 ticket per event, so the TEC tickets that never become one stay at event 0.
	 *
	 * @since 3.18.0
	 *
	 * @param int|null $tec_ticket_id The TEC ticket ID.
	 * @param int      $sc_event_id   The Sugar Calendar event ID. Default: `0`.
	 * @param bool     $is_migrated   Whether the ticket is migrated or not. Default: `false`.
	 *
	 * @return int|false
	 */
	public function save_ticket( $tec_ticket_id, $sc_event_id = 0, $is_migrated = false ) {

		return $this->save(
			'tickets',
			$tec_ticket_id,
			[
				'sc_event_id' => empty( $sc_event_id ) ? 0 : $sc_event_id,
				'is_migrated' => (int) $is_migrated,
			]
		);
	}

	/**
	 * Record an extra-date event against its TEC event, so calendars and tags reach it too.
	 *
	 * @since 3.18.0
	 *
	 * @param array $extra_event       The created event's `sc_event_id` and `sc_event_post_id`.
	 * @param int   $tec_event_id      TEC event ID.
	 * @param int   $tec_event_post_id TEC event post ID.
	 */
	public function save_extra_event( $extra_event, $tec_event_id, $tec_event_post_id ) {

		$this->insert_row(
			self::EVENTS_TABLE,
			[
				'tec_event_id'      => $tec_event_id,
				'tec_event_post_id' => $tec_event_post_id,
				'sc_event_id'       => $extra_event['sc_event_id'],
				'sc_event_post_id'  => $extra_event['sc_event_post_id'],
			]
		);
	}

	/**
	 * Insert a mapping row of integer columns.
	 *
	 * @since 3.18.0
	 *
	 * @param string $table The mapping table, without the prefix.
	 * @param array  $row   The row's columns and values.
	 *
	 * @return int|false
	 */
	private function insert_row( $table, $row ) {

		global $wpdb;

		return $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prefix . $table,
			$row,
			array_fill( 0, count( $row ), '%d' )
		);
	}

	/**
	 * Record the ticket type a TEC ticket became, for its attendees' seats.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_ticket_id  TEC ticket post ID.
	 * @param int $ticket_type_id Sugar Calendar ticket type ID.
	 */
	public function save_ticket_type( $tec_ticket_id, $ticket_type_id ) {

		$ticket_types = get_option( self::TICKET_TYPES_OPTION, [] );

		$ticket_types[ (int) $tec_ticket_id ] = $ticket_type_id;

		update_option( self::TICKET_TYPES_OPTION, $ticket_types, false );
	}

	/**
	 * The Sugar Calendar ticket type a TEC ticket became.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_ticket_id TEC ticket post ID.
	 *
	 * @return int 0 for the event's own ticket, or a ticket nothing took.
	 */
	public function get_ticket_type_id( $tec_ticket_id ) {

		return (int) ( get_option( self::TICKET_TYPES_OPTION, [] )[ (int) $tec_ticket_id ] ?? 0 );
	}

	/**
	 * Free the RSVPs this run could not migrate, so a later run tries them again.
	 *
	 * Each mapping row is written before its attempt, and a run skips mapped rows. They are
	 * freed only once the run is done: freed mid-run, the next batch would pick them again.
	 *
	 * @since 3.18.0
	 *
	 * @param int[] $ticket_ids The TEC RSVP tickets the run left unmigrated.
	 */
	public function release_rsvps( $ticket_ids ) {

		global $wpdb;

		if ( empty( $ticket_ids ) ) {
			return;
		}

		$tickets   = $wpdb->prefix . self::TICKETS_TABLE;
		$attendees = $wpdb->prefix . self::ATTENDEES_TABLE;

		// Quoted: `meta_value` is text, and the IDs are integers already.
		$in = "'" . implode( "','", $ticket_ids ) . "'";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE {$attendees} FROM {$attendees}
			INNER JOIN {$wpdb->postmeta} ON {$wpdb->postmeta}.post_id = {$attendees}.tec_attendees_id
				AND {$wpdb->postmeta}.meta_key = '_tec_tickets_commerce_ticket' AND {$wpdb->postmeta}.meta_value IN ({$in})"
		);
		$wpdb->query( "DELETE FROM {$tickets} WHERE tec_ticket_id IN ({$in})" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
