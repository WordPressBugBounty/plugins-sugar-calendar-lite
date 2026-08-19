<?php

namespace Sugar_Calendar\Admin\Tools\Export;

use Sugar_Calendar\Features\Tags\Common\Helpers as TagHelpers;

/**
 * Base Tools exporter.
 *
 * Owns the shared, format-independent queries; subclasses shape the data and
 * hand it to an ExportDownloader, which owns the transport. Each MUST declare a
 * `const FORMAT` so ExporterService can resolve it without branching.
 *
 * Design decisions behind the date range, the CSV table relationships and the
 * SQL construction live in the `tools-export` rule.
 *
 * @since 3.13.0
 */
abstract class AbstractExporter {

	/**
	 * The base event columns every format exports: DB column => exported key.
	 *
	 * Single source of truth for the events SELECT list and the CSV header, so the
	 * two cannot drift. Read it through `static::`, never `self::`.
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	protected const BASE_EVENT_COLUMNS = [
		'id'                  => 'id',
		'object_id'           => 'post_id',
		'title'               => 'title',
		'content'             => 'content',
		'status'              => 'status',
		'start'               => 'start_date',
		'start_tz'            => 'start_tz',
		'end'                 => 'end_date',
		'end_tz'              => 'end_tz',
		'all_day'             => 'all_day',
		'recurrence'          => 'recurrence',
		'recurrence_interval' => 'recurrence_interval',
		'recurrence_count'    => 'recurrence_count',
		'recurrence_end'      => 'recurrence_end',
		'recurrence_end_tz'   => 'recurrence_end_tz',
		'date_created'        => 'date_created',
		'date_modified'       => 'date_modified',
	];

	/**
	 * Keys to export. E.g. 'events', 'calendars', 'orders', etc.
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	protected $keys = [];

	/**
	 * Event date range filter: [ 'start' => Y-m-d|'', 'end' => Y-m-d|'' ].
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	protected $date_range = [];

	/**
	 * Working store for the shared nested build.
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	protected $export_data = [];

	/**
	 * Memoized result of collect().
	 *
	 * @since 3.13.0
	 *
	 * @var array|null
	 */
	private $data = null;

	/**
	 * Constructor.
	 *
	 * @since 3.13.0
	 *
	 * @param array $args Export args ( keys, format, date_start, date_end ).
	 */
	public function __construct( array $args ) {

		$this->keys = (array) ( $args['keys'] ?? [] );

		$this->date_range = [
			'start' => (string) ( $args['date_start'] ?? '' ),
			'end'   => (string) ( $args['date_end'] ?? '' ),
		];
	}

	/**
	 * Build the format-specific export payload.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	abstract protected function collect(): array;

	/**
	 * Whether there is anything to export for the current selection.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	abstract public function has_items(): bool;

	/**
	 * Stream the export to the browser and exit.
	 *
	 * @since 3.13.0
	 */
	abstract public function output();

	/**
	 * The format-specific payload, built once and memoized.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	protected function get_data(): array {

		if ( $this->data === null ) {
			$this->data = $this->collect();
		}

		return $this->data;
	}

	/**
	 * The base event columns as a qualified, aliased SQL SELECT list.
	 *
	 * Each column is aliased to its exported key so the row objects carry the same
	 * field names the CSV header uses; columns whose name already equals their key
	 * are emitted without a redundant alias.
	 *
	 * @since 3.13.0
	 *
	 * @return string[]
	 */
	protected function base_event_select_columns(): array {

		global $wpdb;

		$table   = $wpdb->prefix . 'sc_events';
		$columns = [];

		foreach ( static::BASE_EVENT_COLUMNS as $column => $key ) {
			$columns[] = $column === $key
				? "{$table}.`{$column}`"
				: "{$table}.`{$column}` AS `{$key}`";
		}

		return $columns;
	}

	/**
	 * Whether a data type was selected for this export.
	 *
	 * @since 3.13.0
	 *
	 * @param string $key Data-type key, e.g. 'events'.
	 *
	 * @return bool
	 */
	protected function wants( string $key ): bool {

		return in_array( $key, $this->keys, true );
	}

	/**
	 * Whether an export date range is active.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	protected function has_date_range(): bool {

		return ! empty( $this->date_range['start'] ) || ! empty( $this->date_range['end'] );
	}

	/**
	 * Range start as a UTC datetime for comparing against UTC-stored columns.
	 *
	 * @since 3.13.0
	 *
	 * @return string 'Y-m-d H:i:s' in UTC, or '' when no start bound is set.
	 */
	protected function range_start_utc(): string {

		return $this->range_bound_utc( (string) ( $this->date_range['start'] ?? '' ), '00:00:00' );
	}

	/**
	 * Range end as a UTC datetime for comparing against UTC-stored columns.
	 *
	 * @since 3.13.0
	 *
	 * @return string 'Y-m-d H:i:s' in UTC, or '' when no end bound is set.
	 */
	protected function range_end_utc(): string {

		return $this->range_bound_utc( (string) ( $this->date_range['end'] ?? '' ), '23:59:59' );
	}

	/**
	 * Convert one range bound from the site's timezone to UTC.
	 *
	 * For UTC-stored columns only (BerlinDB `created` columns such as
	 * `sc_orders.date_created`). Event dates must NOT be converted — see
	 * query_events() and the `tools-export` rule.
	 *
	 * @since 3.13.0
	 *
	 * @param string $ymd  Bare date from the picker ('' when unset).
	 * @param string $time Time of day to anchor the bound at.
	 *
	 * @return string
	 */
	private function range_bound_utc( string $ymd, string $time ): string {

		if ( $ymd === '' ) {
			return '';
		}

		return get_gmt_from_date( $ymd . ' ' . $time, 'Y-m-d H:i:s' );
	}

	/**
	 * Build the shared nested export data (events / calendars / attendees / tags).
	 *
	 * This is the format-independent build both exporters draw from: JSON returns
	 * it as-is (re-importable nested shape) and CSV flattens it. The per-event
	 * nested `orders` are included because the events CSV's ticketing linkage
	 * columns consume them too.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	protected function base_export_data(): array {

		$this->export_data = [];

		$this->collect_sections();

		// Established public hook name kept for BC; renaming to match this class's
		// location would break Pro consumers.
		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/**
		 * Filter for extra export support.
		 *
		 * @since 3.6.0
		 *
		 * @param array $export_data    Export data.
		 * @param array $keys_to_export Keys to export.
		 *
		 * @return array
		 */
		return (array) apply_filters(
			'sugar_calendar_admin_tools_exporter_export_data',
			$this->export_data,
			$this->keys
		);
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Build each selected data section into the export store.
	 *
	 * @since 3.13.0
	 */
	private function collect_sections() {

		foreach ( $this->keys as $key ) {

			switch ( $key ) {
				case 'events':
					$this->get_events_export_data();
					break;

				case 'calendars':
					$this->get_calendars_export_data();
					break;

				case 'orders':
					$this->get_attendees_export_data();
					$this->collect_extra_ticketing();
					break;

				case 'tags':
					$this->collect_tags();
					break;
			}
		}
	}

	/**
	 * Build the tags section (and, only when events are exported, the
	 * event-tag relationship, which is meaningless without events).
	 *
	 * @since 3.13.0
	 */
	private function collect_tags() {

		$this->get_tags_export_data();

		if ( $this->wants( 'events' ) ) {
			$this->get_events_tags_relationship_data();
		}
	}

	/**
	 * Get the events export data.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_events_export_data() {

		if ( isset( $this->export_data['events'] ) ) {
			return $this->export_data['events'];
		}

		$this->export_data['events'] = $this->get_events();

		return $this->export_data['events'];
	}

	/**
	 * Run the events query, optionally limited to events overlapping the range.
	 *
	 * Overlap (not "starts inside the range"), event-local dates (not UTC), and the
	 * "still running" rule for recurring series are all explained in the
	 * `tools-export` rule — including why they are that way.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $select_columns  Columns to select.
	 * @param string $left_join_query Optional LEFT JOIN clause.
	 *
	 * @return array
	 */
	private function query_events( array $select_columns, string $left_join_query ): array {

		global $wpdb;

		$clauses = [];
		$args    = [];
		$table   = $wpdb->prefix . 'sc_events';

		if ( ! empty( $this->date_range['end'] ) ) {
			$clauses[] = $table . '.`start` <= %s';
			$args[]    = $this->date_range['end'] . ' 23:59:59';
		}

		if ( ! empty( $this->date_range['start'] ) ) {
			// Still running: no repeat count AND no end date. Zero dates mean unset.
			$endless = $table . ".`recurrence` <> ''"
				. ' AND ( ' . $table . '.`recurrence_count` IS NULL OR ' . $table . '.`recurrence_count` = 0 )'
				. ' AND ( ' . $table . '.`recurrence_end` IS NULL OR '
				. $table . ".`recurrence_end` = '0000-00-00 00:00:00' )";

			$first_run = 'COALESCE( NULLIF( ' . $table . ".`end`, '0000-00-00 00:00:00' ), " . $table . '.`start` )';
			$repeats   = 'GREATEST( ' . $table . '.`recurrence_interval`, 1 ) * ( ' . $table . '.`recurrence_count` - 1 )';

			// "End after N times" bounds the series at first run + interval x (N - 1);
			// the row's own `end` describes only the first occurrence. See the
			// `tools-export` rule for why this deliberately over-reaches.
			$bounded_end = 'CASE WHEN ' . $table . ".`recurrence` <> '' AND " . $table . '.`recurrence_count` > 0'
				. ' THEN CASE ' . $table . '.`recurrence`'
				. " WHEN 'daily' THEN DATE_ADD( " . $first_run . ', INTERVAL ' . $repeats . ' DAY )'
				. " WHEN 'weekly' THEN DATE_ADD( " . $first_run . ', INTERVAL ' . $repeats . ' WEEK )'
				. " WHEN 'monthly' THEN DATE_ADD( " . $first_run . ', INTERVAL ' . $repeats . ' MONTH )'
				. " WHEN 'yearly' THEN DATE_ADD( " . $first_run . ', INTERVAL ' . $repeats . ' YEAR )'
				. ' END END';

			$last_run = 'COALESCE( NULLIF( ' . $table . ".`recurrence_end`, '0000-00-00 00:00:00' ), "
				. $bounded_end . ', ' . $first_run . ' )';

			$clauses[] = '( ( ' . $endless . ' ) OR ' . $last_run . ' >= %s )';
			$args[]    = $this->date_range['start'] . ' 00:00:00';
		}

		$where = '';

		if ( ! empty( $clauses ) ) {
			// Only the date clauses are prepared — preparing the whole statement
			// would read a `%` in a filtered column as a placeholder. See the
			// `tools-export` rule.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$where = ' WHERE ' . $wpdb->prepare( implode( ' AND ', $clauses ), $args );
		}

		$sql = 'SELECT ' . esc_sql( implode( ',', $select_columns ) )
			. ' FROM ' . $wpdb->prefix . 'sc_events'
			. esc_sql( $left_join_query )
			. $where;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Direct query for the export tool over custom Sugar Calendar tables.
		$results = $wpdb->get_results( $sql );

		return empty( $results ) ? [] : $results;
	}

	/**
	 * Get the events to export from DB.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_events() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.NestingLevel.MaxExceeded

		global $wpdb;

		// Established public hook name kept for BC; renaming to match this class's
		// location would break Pro consumers.
		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/**
		 * Filter support for extra columns.
		 *
		 * @since 3.6.0
		 *
		 * @param array $select_columns Array of columns to select.
		 * @param array $keys_to_import Keys to import.
		 *
		 * @return array
		 */
		$select_columns = apply_filters(
			'sugar_calendar_admin_tools_exporter_events_select_columns',
			$this->base_event_select_columns(),
			$this->keys
		);
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		$left_join_query        = '';
		$should_export_calendar = false;

		if ( $this->wants( 'calendars' ) ) {
			// If we are exporting calendars, we need to get the calendar IDs.
			$should_export_calendar = true;
			$select_columns[]       = 'wp_tr.`calendar_ids`';
			$left_join_query        = ' LEFT JOIN ( '
				. 'SELECT ' . $wpdb->term_relationships . '.`object_id`, GROUP_CONCAT( ' . $wpdb->term_relationships . '.`term_taxonomy_id`) AS calendar_ids '
				. 'FROM ' . $wpdb->term_relationships . ' GROUP BY ' . $wpdb->term_relationships . '.`object_id` ) wp_tr '
				. 'ON wp_tr.`object_id` = ' . $wpdb->prefix . 'sc_events.`object_id`';
		}

		// Get the events (optionally filtered by the stored event start date).
		$results = $this->query_events( $select_columns, $left_join_query );

		if ( empty( $results ) ) {
			return [];
		}

		// Check if we should export ticket orders.
		$should_export_orders = $this->wants( 'orders' );

		$results_count = count( $results );

		// Bulk-load per-event data up front so the loop below stays free of
		// per-event queries (avoids an N+1 that times out large exports).
		$prefetched = $this->prefetch_event_relations( $results );

		$meta_by_event         = $prefetched['meta'];
		$orders_by_event       = $prefetched['orders'];
		$custom_fields_by_post = $prefetched['custom_fields'];

		for ( $ctr = 0; $ctr < $results_count; $ctr++ ) {

			$event_id = $results[ $ctr ]->id;

			// Get featured image.
			$featured_image = get_the_post_thumbnail_url( $results[ $ctr ]->post_id, 'full' );

			if ( ! empty( $featured_image ) ) {
				$results[ $ctr ]->featured_image = esc_url( $featured_image );
			}

			if ( ! empty( $meta_by_event[ $event_id ] ) ) {
				$results[ $ctr ]->event_meta = $meta_by_event[ $event_id ];
			}

			// Check if we are exporting custom fields.
			if ( $this->wants( 'custom_fields' ) ) {
				$results[ $ctr ]->custom_fields = $custom_fields_by_post[ $results[ $ctr ]->post_id ] ?? [];
			}

			if ( $should_export_calendar ) {
				// If we are exporting calendars, let's convert the calendar IDs to slugs.
				$results[ $ctr ]->calendars = $this->convert_calendar_ids_to_slugs( $results[ $ctr ]->calendar_ids );

				unset( $results[ $ctr ]->calendar_ids );
			}

			if ( $should_export_orders && ! empty( $orders_by_event[ $event_id ] ) ) {
				$results[ $ctr ]->orders = $orders_by_event[ $event_id ];
			}
		}

		return $results;
	}

	/**
	 * Bulk-load everything the event loop needs, so it issues no queries per row.
	 *
	 * Anything the loop touches per event multiplies by the size of the calendar,
	 * which is why each source is loaded in one pass here. That includes priming
	 * WP's post and meta caches: the featured-image lookup reads the post and its
	 * meta, which is two queries per event even for events with no thumbnail.
	 *
	 * @since 3.13.0
	 *
	 * @param array $results Event rows from the events query.
	 *
	 * @return array Keys: `meta`, `orders`, `custom_fields`.
	 */
	private function prefetch_event_relations( array $results ): array {

		$event_ids = wp_list_pluck( $results, 'id' );
		$post_ids  = array_filter( array_map( 'intval', wp_list_pluck( $results, 'post_id' ) ) );

		if ( ! empty( $post_ids ) ) {
			_prime_post_caches( $post_ids, false, true );
		}

		return [
			'meta'          => $this->prefetch_event_meta( $event_ids ),
			'orders'        => $this->wants( 'orders' )
				? $this->prefetch_orders_with_tickets( $event_ids )
				: [],
			'custom_fields' => $this->wants( 'custom_fields' )
				? $this->prefetch_custom_fields( $post_ids )
				: [],
		];
	}

	/**
	 * Bulk-load orders (with their tickets and attendees) for a set of events.
	 *
	 * Returns a map of event ID => array of order rows, each order carrying a
	 * `tickets` property when it has any. Two queries total, whatever the event count.
	 *
	 * @since 3.13.0
	 *
	 * @param int[] $event_ids Sugar Calendar event IDs.
	 *
	 * @return array
	 */
	private function prefetch_orders_with_tickets( array $event_ids ): array {

		global $wpdb;

		if ( empty( $event_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );

		// All orders for the exported events, in one query. $placeholders is a
		// generated run of %d that the sniff cannot see through.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Direct query for the export tool over custom Sugar Calendar tables.
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ' . esc_sql( implode( ',', $this->get_orders_select_columns() ) )
				. ' FROM ' . $wpdb->prefix . 'sc_orders WHERE `event_id` IN ( ' . $placeholders . ' )',
				$event_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $orders ) ) {
			return [];
		}

		// All tickets (with attendee data) for the exported events, in one query.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Direct query for the export tool over custom Sugar Calendar tables; $placeholders is a generated %d run bound by prepare().
		$tickets = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ' . esc_sql( implode( ',', $this->get_tickets_select_columns() ) )
				. ' FROM ' . $wpdb->prefix . 'sc_tickets'
				. ' LEFT JOIN ' . $wpdb->prefix . 'sc_attendees ON '
				. $wpdb->prefix . 'sc_attendees.`id` = ' . $wpdb->prefix . 'sc_tickets.`attendee_id`'
				. ' WHERE ' . $wpdb->prefix . 'sc_tickets.`event_id` IN ( ' . $placeholders . ' )',
				$event_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		// Group tickets by their order so each order can claim its own. An
		// order id is unique per event, so grouping globally is equivalent to
		// the previous per-event match.
		$tickets_by_order = [];

		foreach ( $tickets as $ticket ) {
			$tickets_by_order[ $ticket->order_id ][] = $ticket;
		}

		$orders_by_event = [];

		foreach ( $orders as $order ) {
			if ( ! empty( $tickets_by_order[ $order->order_id ] ) ) {
				$order->tickets = $tickets_by_order[ $order->order_id ];
			}

			$orders_by_event[ $order->event_id ][] = $order;
		}

		return $orders_by_event;
	}

	/**
	 * Bulk-load event meta for a set of events, keyed by event ID.
	 *
	 * Runs one query for all events instead of one query per event. Each meta
	 * row keeps the exact `{ meta_key, meta_value }` shape callers expect — the
	 * grouping column is stripped after grouping.
	 *
	 * @since 3.13.0
	 *
	 * @param int[] $event_ids Sugar Calendar event IDs.
	 *
	 * @return array
	 */
	private function prefetch_event_meta( array $event_ids ): array {

		global $wpdb;

		if ( empty( $event_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Direct query for the export tool over custom Sugar Calendar tables; $placeholders is a generated %d run bound by prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT sc_event_id, meta_key, meta_value FROM ' . $wpdb->prefix . 'sc_eventmeta WHERE sc_event_id IN ( ' . $placeholders . ' )',
				$event_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		$meta_by_event = [];

		foreach ( $rows as $row ) {
			$event_id = $row->sc_event_id;

			// Drop the grouping key so each row matches the previous shape.
			unset( $row->sc_event_id );

			$meta_by_event[ $event_id ][] = $row;
		}

		return $meta_by_event;
	}

	/**
	 * Bulk-load post meta (the Custom Fields payload) for a set of event posts.
	 *
	 * Mirrors prefetch_event_meta(): one query for all posts, each row keeping the
	 * `{ meta_key, meta_value }` shape callers expect. WP's own meta cache cannot
	 * serve this — the rows are read straight from postmeta rather than through
	 * get_post_meta() — so priming the post caches does not cover it.
	 *
	 * @since 3.13.0
	 *
	 * @param int[] $event_post_ids Event post IDs.
	 *
	 * @return array Map of post ID => meta rows.
	 */
	private function prefetch_custom_fields( array $event_post_ids ): array {

		global $wpdb;

		if ( empty( $event_post_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $event_post_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Direct query so the whole set loads at once; $placeholders is a generated %d run bound by prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT post_id, meta_key, meta_value FROM ' . $wpdb->postmeta . ' WHERE post_id IN ( ' . $placeholders . ' )',
				$event_post_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		$meta_by_post = [];

		foreach ( $rows as $row ) {
			$post_id = $row->post_id;

			// Drop the grouping key so each row matches the previous shape.
			unset( $row->post_id );

			$meta_by_post[ $post_id ][] = $row;
		}

		return $meta_by_post;
	}

	/**
	 * Convert calendar IDs to slugs.
	 *
	 * @since 3.13.0
	 *
	 * @param string $calendar_ids_string Comma-separated calendar IDs.
	 *
	 * @return array
	 */
	private function convert_calendar_ids_to_slugs( $calendar_ids_string ) {

		if ( empty( $calendar_ids_string ) ) {
			return [];
		}

		// Lazy load the calendars data.
		$this->get_calendars_export_data();

		$calendars    = [];
		$calendar_ids = explode( ',', $calendar_ids_string );

		foreach ( $calendar_ids as $cal_id ) {
			if ( ! empty( $this->export_data['calendars'][ $cal_id ] ) ) {
				$calendars[] = $this->export_data['calendars'][ $cal_id ]->slug;
			}
		}

		return $calendars;
	}

	/**
	 * Get the calendars export data.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_calendars_export_data() {

		if ( ! isset( $this->export_data['calendars'] ) ) {
			$this->export_data['calendars'] = $this->get_calendars();
		}

		return $this->export_data['calendars'];
	}

	/**
	 * Get the calendars to export from DB.
	 *
	 * Returns an array of calendars with the `key` being the calendar/term ID.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_calendars() {

		global $wpdb;

		$select_columns = [
			"{$wpdb->terms}.`term_id`",
			"{$wpdb->terms}.`name`",
			"{$wpdb->terms}.`slug`",
			"{$wpdb->term_taxonomy}.`description`",
			'wp_t.`slug` AS `parent_slug`',
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the export tool over custom Sugar Calendar tables.
		$results = $wpdb->get_results(
			'SELECT ' . esc_sql( implode( ',', $select_columns ) )
			. ' FROM ' . $wpdb->term_taxonomy
			. ' LEFT JOIN ' . $wpdb->terms . ' ON ' . $wpdb->terms . '.`term_id` = ' . $wpdb->term_taxonomy . '.`term_id`'
			. ' LEFT JOIN ' . $wpdb->terms . ' wp_t ON wp_t.`term_id` = ' . $wpdb->term_taxonomy . '.`parent`'
			. ' WHERE ' . $wpdb->term_taxonomy . '.`taxonomy` = "sc_event_category"',
			OBJECT_K
		);

		if ( empty( $results ) ) {
			return [];
		}

		foreach ( $results as $cal_id => $cal ) {
			$calendar_meta = get_term_meta( $cal_id );

			if ( ! empty( $calendar_meta ) ) {
				$results[ $cal_id ]->meta = $calendar_meta;
			}
		}

		return $results;
	}

	/**
	 * Get the attendees export data.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_attendees_export_data() {

		if ( ! isset( $this->export_data['attendees'] ) ) {
			$this->export_data['attendees'] = $this->get_attendees();
		}

		return $this->export_data['attendees'];
	}

	/**
	 * Get the attendees to export from DB.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_attendees() {

		global $wpdb;

		$select_columns = [
			"{$wpdb->prefix}sc_attendees.`id` AS attendee_id",
			"{$wpdb->prefix}sc_attendees.`email`",
			"{$wpdb->prefix}sc_attendees.`first_name`",
			"{$wpdb->prefix}sc_attendees.`last_name`",
			"{$wpdb->prefix}sc_attendees.`date_created`",
			"{$wpdb->prefix}sc_attendees.`date_modified`",
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the export tool over custom Sugar Calendar tables.
		$results = $wpdb->get_results(
			'SELECT ' . esc_sql( implode( ',', $select_columns ) )
			. ' FROM ' . $wpdb->prefix . 'sc_attendees',
			OBJECT_K
		);

		if ( empty( $results ) ) {
			return [];
		}

		return $results;
	}

	/**
	 * Get the select columns for orders.
	 *
	 * @since 3.13.0
	 *
	 * @return string[]
	 */
	protected function get_orders_select_columns() {

		global $wpdb;

		return [
			"{$wpdb->prefix}sc_orders.`id` AS order_id",
			"{$wpdb->prefix}sc_orders.`transaction_id`",
			"{$wpdb->prefix}sc_orders.`status`",
			"{$wpdb->prefix}sc_orders.`currency`",
			"{$wpdb->prefix}sc_orders.`discount_id`", // @todo - Check where this is used.
			"{$wpdb->prefix}sc_orders.`email`",
			"{$wpdb->prefix}sc_orders.`first_name`",
			"{$wpdb->prefix}sc_orders.`last_name`",
			"{$wpdb->prefix}sc_orders.`subtotal`",
			"{$wpdb->prefix}sc_orders.`discount`", // @todo - Check where this is used.
			"{$wpdb->prefix}sc_orders.`tax`", // @todo - Check where this is used.
			"{$wpdb->prefix}sc_orders.`total`",
			"{$wpdb->prefix}sc_orders.`event_id`",
			"{$wpdb->prefix}sc_orders.`event_date`",
			"{$wpdb->prefix}sc_orders.`checkout_type`", // @todo - Check where this is used.
			"{$wpdb->prefix}sc_orders.`checkout_id`", // @todo - Check where this is used.
			"{$wpdb->prefix}sc_orders.`date_created`",
		];
	}

	/**
	 * Get the tickets of an event.
	 *
	 * @since 3.13.0
	 *
	 * @param string $by      Either 'order_id' or 'event_id'.
	 * @param int    $context Context.
	 *
	 * @return array
	 */
	protected function get_tickets( $by, $context ) {

		$by = strtolower( $by );

		if ( ! in_array( $by, [ 'order_id', 'event_id' ], true ) ) {
			return [];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the export tool over custom Sugar Calendar tables.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ' . esc_sql( implode( ',', $this->get_tickets_select_columns() ) )
				. ' FROM ' . $wpdb->prefix . 'sc_tickets'
				. ' LEFT JOIN ' . $wpdb->prefix . 'sc_attendees ON '
				. $wpdb->prefix . 'sc_attendees.`id` = ' . $wpdb->prefix . 'sc_tickets.`attendee_id`'
				. ' WHERE ' . $wpdb->prefix . 'sc_tickets.`' . esc_sql( $by ) . '` = %d',
				$context
			)
		);
	}

	/**
	 * Get the select columns for tickets.
	 *
	 * @since 3.13.0
	 *
	 * @return string[]
	 */
	protected function get_tickets_select_columns() {

		global $wpdb;

		return [
			"{$wpdb->prefix}sc_tickets.`id` AS ticket_id",
			"{$wpdb->prefix}sc_tickets.`order_id`",
			"{$wpdb->prefix}sc_tickets.`event_id`",
			"{$wpdb->prefix}sc_tickets.`attendee_id`",
			"{$wpdb->prefix}sc_tickets.`code`",
			"{$wpdb->prefix}sc_tickets.`event_date`",
			"{$wpdb->prefix}sc_tickets.`date_created`",
			"{$wpdb->prefix}sc_tickets.`date_modified`",
			"{$wpdb->prefix}sc_attendees.`email`",
			"{$wpdb->prefix}sc_attendees.`first_name`",
			"{$wpdb->prefix}sc_attendees.`last_name`",
			"{$wpdb->prefix}sc_attendees.`date_created` AS attendee_date_created",
			"{$wpdb->prefix}sc_attendees.`date_modified` AS attendee_date_modified",
		];
	}

	/**
	 * Hook for format-specific extra ticketing sections — orphan orders, tickets
	 * not tied to an event/order, or all orders when events are not exported.
	 *
	 * The base build produces none: CSV queries its own page-parity ticketing
	 * tables and never consumes these. JsonExporter overrides this to add them.
	 *
	 * @since 3.13.0
	 */
	protected function collect_extra_ticketing() {

		// No extra ticketing sections in the base build.
	}

	/**
	 * Get tags for export.
	 *
	 * @since 3.13.0
	 */
	private function get_tags_export_data() {

		$tags = get_terms(
			[
				'taxonomy'   => TagHelpers::get_tags_taxonomy_id(),
				'hide_empty' => false,
			]
		);

		if ( empty( $tags ) || is_wp_error( $tags ) ) {
			return [];
		}

		$tags_data = [];

		foreach ( $tags as $tag ) {
			$tags_data[] = [
				'id'          => $tag->term_id,
				'name'        => $tag->name,
				'slug'        => $tag->slug,
				'description' => $tag->description,
			];
		}

		$this->export_data['tags'] = $tags_data;
	}

	/**
	 * Get event-tag relationships for export.
	 *
	 * @since 3.13.0
	 */
	private function get_events_tags_relationship_data() {

		global $wpdb;

		$taxonomy_id = TagHelpers::get_tags_taxonomy_id();

		// Get all events with their tags.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the export tool over custom Sugar Calendar tables.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tr.object_id as event_id, tt.term_id as tag_id
				 FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 INNER JOIN {$wpdb->prefix}sc_events sce ON sce.object_id = tr.object_id
				 WHERE tt.taxonomy = %s",
				$taxonomy_id
			),
			ARRAY_A
		);

		// If there are no results or events data is empty, return.
		if (
			empty( $results )
			||
			empty( $this->export_data['events'] )
		) {
			return;
		}

		// Get the post terms relationship and relate it to event data.
		$post_terms = [];

		foreach ( $results as $result ) {
			$post_terms[ $result['event_id'] ][] = intval( $result['tag_id'] );
		}

		foreach ( $this->export_data['events'] as $index => $event ) {

			if ( isset( $post_terms[ $event->post_id ] ) ) {
				$this->export_data['events'][ $index ]->tags = $post_terms[ $event->post_id ];
			}
		}
	}

	/**
	 * Base download filename (no extension).
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	protected function base_filename(): string {

		return 'sugar-calendar-export-' . current_time( 'm-d-Y' );
	}

	/**
	 * The collaborator that turns a built payload into the HTTP response.
	 *
	 * A method rather than a constructor-injected property so a test can
	 * substitute a double: everything below this seam ends in exit().
	 *
	 * @since 3.13.0
	 *
	 * @return ExportDownloader
	 */
	protected function downloader(): ExportDownloader {

		return new ExportDownloader( $this->base_filename() );
	}
}
