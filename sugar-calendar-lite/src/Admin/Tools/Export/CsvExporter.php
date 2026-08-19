<?php

namespace Sugar_Calendar\Admin\Tools\Export;

use Sugar_Calendar\AddOn\Ticketing\Export\CSV_Export;
use Sugar_Calendar\AddOn\Ticketing\Export\Tickets_Export;
use Sugar_Calendar\AddOn\Ticketing\Export\Orders_Export;

/**
 * CSV exporter.
 *
 * Produces one flat table per selected data type. A single selected type
 * downloads as a `.csv`; two or more download as a `.zip`. Relationships are
 * preserved by id/count columns rather than JSON nesting.
 *
 * The events table's ticketing linkage columns reuse the shared per-event nested
 * orders; the standalone `orders` / `tickets` / `attendees` tables are queried
 * here from the Event Ticketing exporters (page parity) and scoped to the export
 * date range: orders in range, then those orders' tickets, then those tickets'
 * attendees.
 *
 * @since 3.13.0
 */
class CsvExporter extends AbstractExporter {

	/**
	 * Export format slug this class handles.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const FORMAT = 'csv';

	/**
	 * Row cap for the reused Event Ticketing exporters, mirroring the
	 * Ticketing page's own export cap.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	const TICKETING_QUERY_LIMIT = 10000;

	/**
	 * Escape character for fputcsv(): none, i.e. strict RFC 4180.
	 *
	 * PHP defaults to a backslash, which RFC 4180 has no concept of: a quote
	 * preceded by one is emitted unescaped rather than doubled, so the cell can
	 * break out. Free-form event content and post meta both reach these cells.
	 * Empty leaves quote-doubling as the only mechanism, which is what
	 * spreadsheets expect.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const CSV_ESCAPE = '';

	/**
	 * Row direction for the ticketing tables: oldest id first.
	 *
	 * The reused Ticketing exporters default to DESC, which left tickets.csv
	 * counting down while attendees.csv counted up — the same records in opposite
	 * orders in one download.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ROW_ORDER = 'ASC';

	/**
	 * Order statuses the ticketing tables export, matching the Ticketing page's
	 * own export (trashed orders are excluded).
	 *
	 * The events table's linkage columns read the same list, so an order id cited
	 * on an event row is always present in orders.csv. Note the shared prefetch
	 * behind those columns is deliberately unfiltered — it also feeds the JSON
	 * export, which has always included trashed orders and must not change.
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	const TICKETING_STATUSES = [ 'pending', 'paid', 'refunded' ];

	/**
	 * Memoized CSV parts (entity slug => CSV string).
	 *
	 * @since 3.13.0
	 *
	 * @var array|null
	 */
	private $parts = null;

	/**
	 * Build the shared data plus the flat, date-scoped ticketing tables.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	protected function collect(): array {

		$data = $this->base_export_data();

		if ( $this->wants( 'orders' ) ) {
			$data['csv_ticketing'] = $this->ticketing_tables(
				(array) ( $data['attendees'] ?? [] ),
				(array) ( $data['events'] ?? [] )
			);
		}

		return $data;
	}

	/**
	 * There is data to export when at least one CSV table is produced.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	public function has_items(): bool {

		return ! empty( $this->build_parts() );
	}

	/**
	 * Stream the CSV (single table) or ZIP (multiple tables) and exit.
	 *
	 * @since 3.13.0
	 */
	public function output() {

		$parts      = $this->build_parts();
		$downloader = $this->downloader();

		if ( count( $parts ) === 1 ) {
			$downloader->send( (string) reset( $parts ), 'text/csv', 'csv' );

			// send() exits, but do not rely on that here: without this return, any
			// future early return in it would stream the CSV and then the ZIP into
			// the same response.
			return;
		}

		$downloader->send_archive( $parts, 'csv' );
	}

	/**
	 * Build one CSV part per selected data type present in the export.
	 *
	 * @since 3.13.0
	 *
	 * @return array Map of entity slug => CSV string.
	 */
	private function build_parts(): array {

		if ( $this->parts !== null ) {
			return $this->parts;
		}

		$data = $this->get_data();

		$parts = [];

		// Empty tables produce no part, which is what lets has_items() fail loud
		// instead of streaming header-only files.
		if ( $this->wants( 'events' ) && ! empty( $data['events'] ) ) {
			$parts['events'] = $this->events_csv( $data['events'] );
		}

		if ( $this->wants( 'orders' ) ) {
			$parts += $this->ticketing_parts( $data );
		}

		if ( $this->wants( 'calendars' ) && ! empty( $data['calendars'] ) ) {
			$parts['calendars'] = $this->calendars_csv( $data['calendars'] );
		}

		if ( $this->wants( 'tags' ) && ! empty( $data['tags'] ) ) {
			$parts['tags'] = $this->tags_csv( $data['tags'] );
		}

		// Named for what a consumer contributes — a table. The sniff derives the
		// required prefix from the class name, which would force the stuttering
		// `..._export_csv_exporter_tables` instead.
		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/**
		 * Filter the CSV export tables (entity slug => CSV string).
		 *
		 * Mirrors JSON's `sugar_calendar_admin_tools_exporter_export_data` seam:
		 * lets Pro (or add-ons) contribute their own CSV tables — e.g. venues,
		 * speakers — so selecting a Pro-only data type as CSV is not silently
		 * dropped.
		 *
		 * @since 3.13.0
		 *
		 * @param array $tables Map of entity slug => CSV string.
		 * @param array $data   Collected export data.
		 * @param array $keys   Selected data-type keys.
		 */
		$this->parts = (array) apply_filters( 'sugar_calendar_admin_tools_export_csv_tables', $parts, $data, $this->keys );
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		return $this->parts;
	}

	/**
	 * Build the flat, date-scoped ticketing tables.
	 *
	 * @since 3.13.0
	 *
	 * @param array $attendees Attendee object map from the shared data.
	 * @param array $events    Exported event rows (empty when events are not selected).
	 *
	 * @return array Shape: [ 'orders' => ?dataset, 'tickets' => ?dataset, 'attendees' => map ].
	 */
	private function ticketing_tables( array $attendees, array $events = [] ): array {

		// Orders: the non-trashed pending/paid/refunded set. The underlying
		// BerlinDB query returns nothing without an explicit `number`.
		$order_args = [
			'number' => self::TICKETING_QUERY_LIMIT,
			'status' => self::TICKETING_STATUSES,
			'order'  => self::ROW_ORDER,
		];

		// Which axis a range scopes depends on the selection: with events, orders
		// follow their event; without, the range means their own creation date.
		if ( $this->has_date_range() ) {
			$order_args += $this->wants( 'events' )
				? $this->orders_for_events_args( $events )
				: [ 'date_created_query' => $this->date_created_query() ];
		}

		$orders = $this->orders_rows( $order_args );

		// Tickets always follow the exported orders (scoped in SQL), so a ticket
		// never cites an order orders.csv does not contain.
		$tickets = $this->ticket_rows( $orders );

		// Attendees narrow only when a range is active; otherwise they stay complete
		// like the other reference tables. See the `tools-export` rule.
		if ( $this->has_date_range() ) {
			$ticket_rows = ( $tickets !== null ) ? $tickets['rows'] : [];
			$attendees   = $this->attendees_for_ids( $attendees, array_column( $ticket_rows, 'attendee_id' ) );
		}

		return [
			'orders'    => $orders,
			'tickets'   => $tickets,
			'attendees' => $attendees,
		];
	}

	/**
	 * Query args scoping the orders to the exported events.
	 *
	 * @since 3.13.0
	 *
	 * @param array $events Exported event rows.
	 *
	 * @return array
	 */
	private function orders_for_events_args( array $events ): array {

		$ids = [];

		foreach ( $events as $event ) {

			$event = (array) $event;
			$id    = (int) ( $event['id'] ?? 0 );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return [ 'event_id__in' => array_values( array_unique( $ids ) ) ];
	}

	/**
	 * Fetch the orders table.
	 *
	 * @since 3.13.0
	 *
	 * @param array $args Query args forwarded to the exporter.
	 *
	 * @return array|null Shape: [ 'cols' => [ key => label ], 'rows' => [ … ] ].
	 */
	private function orders_rows( array $args ) {

		if ( ! class_exists( Orders_Export::class ) ) {
			return null;
		}

		$exporter = new Orders_Export();

		// No exported events means no orders to follow, and an empty `event_id__in`
		// is not a reliable "match nothing" — skip the query.
		if ( isset( $args['event_id__in'] ) && empty( $args['event_id__in'] ) ) {
			return [
				'cols' => $exporter->get_csv_cols(),
				'rows' => [],
			];
		}

		return $this->provider_rows( $exporter, $args );
	}

	/**
	 * Fetch the tickets table, bounded in SQL to the exported order set.
	 *
	 * Always scoped to the orders orders.csv contains, on every export — not only
	 * ranged ones — or a ticket cites an order the file omits. The scoping must
	 * happen in SQL, not in PHP afterwards: `number` alone returns the newest N
	 * tickets site-wide, and narrowing that page could empty the table.
	 *
	 * @since 3.13.0
	 *
	 * @param array|null $orders Orders dataset (or null when unavailable).
	 *
	 * @return array|null Shape: [ 'cols' => [ key => label ], 'rows' => [ … ] ].
	 */
	private function ticket_rows( $orders ) {

		if ( ! class_exists( Tickets_Export::class ) ) {
			return null;
		}

		$exporter  = new Tickets_Export();
		$order_ids = ( $orders !== null ) ? array_column( $orders['rows'], 'id' ) : [];

		// No exported orders means no tickets to list, and an empty
		// `order_id__in` is not a reliable "match nothing" — skip the query.
		if ( empty( $order_ids ) ) {
			return [
				'cols' => $exporter->get_csv_cols(),
				'rows' => [],
			];
		}

		return $this->provider_rows(
			$exporter,
			[
				'number'       => self::TICKETING_QUERY_LIMIT,
				'order'        => self::ROW_ORDER,
				'order_id__in' => $order_ids,
			]
		);
	}

	/**
	 * Build the date_created Date_Query clause from the active range.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function date_created_query(): array {

		$clause = [ 'inclusive' => true ];

		// date_created is stored in UTC and BerlinDB compares these bounds
		// literally, so they are converted from the site's timezone first.
		$start = $this->range_start_utc();
		$end   = $this->range_end_utc();

		if ( $start !== '' ) {
			$clause['after'] = $start;
		}

		if ( $end !== '' ) {
			$clause['before'] = $end;
		}

		return [ $clause ];
	}

	/**
	 * Fetch a ticketing exporter's column map and formatted rows.
	 *
	 * Uses get_csv_cols() + prepare_data() (not the raw csv_cols()/get_data())
	 * so the output matches the Ticketing page exports exactly, including their
	 * `sc_et_export_csv_cols_*` / `sc_et_export_get_data_*` filters.
	 *
	 * @since 3.13.0
	 *
	 * Typed against the shared parent so a wrong argument fails here rather than as
	 * a mid-export fatal. Both call sites guard on class_exists() first, so the hint
	 * is never resolved on a build without Event Ticketing.
	 *
	 * @param CSV_Export $exporter Ticketing CSV exporter (Tickets_Export / Orders_Export).
	 * @param array      $args     Query args forwarded to the exporter's get_data().
	 *
	 * @return array Shape: [ 'cols' => [ key => label ], 'rows' => [ assoc row, ... ] ].
	 */
	private function provider_rows( CSV_Export $exporter, array $args = [] ): array {

		return [
			'cols' => $exporter->get_csv_cols(),
			'rows' => (array) $exporter->prepare_data( $exporter->get_data( $args ) ),
		];
	}

	/**
	 * Keep only attendees whose id is in $ids (preserves the map keys).
	 *
	 * @since 3.13.0
	 *
	 * @param array $attendees Attendee object map.
	 * @param array $ids       Allowed attendee ids.
	 *
	 * @return array
	 */
	private function attendees_for_ids( array $attendees, array $ids ): array {

		// Flip the wanted ids into keys so each attendee is one hash lookup rather
		// than a fresh scan of the id list, matching how the JSON export scopes
		// attendees — see JsonExporter::scope_attendees_to_orders().
		$wanted = array_flip( array_map( 'strval', $ids ) );
		$kept   = [];

		foreach ( $attendees as $key => $attendee ) {
			if ( isset( $wanted[ (string) ( $attendee->attendee_id ?? '' ) ] ) ) {
				$kept[ $key ] = $attendee;
			}
		}

		return $kept;
	}

	/**
	 * Render the pre-built, date-scoped ticketing tables into CSV parts.
	 *
	 * @since 3.13.0
	 *
	 * @param array $data Collected export data.
	 *
	 * @return array
	 */
	private function ticketing_parts( array $data ): array {

		$ticketing = (array) ( $data['csv_ticketing'] ?? [] );

		// tickets / orders carry a non-empty `cols` map even with zero rows, so
		// gate on the rows themselves — a table with no data is skipped, not
		// rendered header-only (keeps has_items()'s fail-loud honest).
		$parts = $this->rows_parts( $ticketing );

		$attendees = $ticketing['attendees'] ?? ( $data['attendees'] ?? [] );

		if ( ! empty( $attendees ) ) {
			$parts['attendees'] = $this->attendees_csv( $attendees );
		}

		return $parts;
	}

	/**
	 * Render the tickets / orders tables that actually have rows into CSV parts.
	 *
	 * @since 3.13.0
	 *
	 * @param array $ticketing The csv_ticketing data ( tickets / orders / attendees ).
	 *
	 * @return array Map of slug => CSV string for each non-empty table.
	 */
	private function rows_parts( array $ticketing ): array {

		$parts = [];

		foreach ( [ 'tickets', 'orders' ] as $slug ) {

			$table = (array) ( $ticketing[ $slug ] ?? [] );

			if ( ! empty( $table['rows'] ) ) {
				// The exporters' `cols` map is key => label, and the CSV header is
				// the keys — which are also the row field names.
				$parts[ $slug ] = self::records_csv( array_keys( $table['cols'] ), $table['rows'] );
			}
		}

		return $parts;
	}

	/**
	 * Build the events CSV.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $events Event row objects.
	 *
	 * @return string
	 */
	private function events_csv( $events ): string {

		$events  = array_values( (array) $events );
		$columns = $this->discover_event_columns( $events );

		// Resolved once and passed in, so the header and rows cannot see different
		// maps — and so those methods stay database-free and unit-testable.
		$tag_slugs = $this->build_tag_slug_map();
		$extra     = $this->build_extra_event_columns();

		$header = $this->event_header( $columns, $extra );

		$rows = [];

		foreach ( $events as $event ) {
			$rows[] = $this->event_row( $event, $columns, $extra, $tag_slugs );
		}

		return self::render_csv( $header, $rows );
	}

	/**
	 * Build the tag id => slug map from the collected tags data.
	 *
	 * CSV-only: the events CSV shows tag slugs (unique + portable, matching the
	 * calendars column) while the JSON export keeps the raw tag ids.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function build_tag_slug_map(): array {

		$map = [];

		foreach ( (array) ( $this->get_data()['tags'] ?? [] ) as $tag ) {

			$tag = (array) $tag;

			if ( isset( $tag['id'], $tag['slug'] ) ) {
				$map[ (int) $tag['id'] ] = $tag['slug'];
			}
		}

		return $map;
	}

	/**
	 * Resolve add-on event columns for the events CSV.
	 *
	 * @since 3.13.0
	 *
	 * @return array Map of column key => value-extractor callable( $event ).
	 */
	private function build_extra_event_columns(): array {

		// `csv_event_columns` rather than the class-derived
		// `..._export_csv_exporter_event_columns`: the `csv_` segment is what
		// separates this from the SQL-side `..._exporter_events_select_columns`.
		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/**
		 * Filter add-on columns for the events CSV.
		 *
		 * Lets Pro / add-ons append columns to the events table (e.g. venue_id,
		 * speaker_ids) without core knowing about them. CSV-only — the JSON export
		 * is unaffected. Return a map of column key => callable( $event ) that
		 * yields the cell value.
		 *
		 * Each callable is invoked once per exported event, so resolve any lookups
		 * before returning rather than querying inside the callable.
		 *
		 * @since 3.13.0
		 *
		 * @param array $columns Map of column key => value-extractor callable.
		 * @param array $data    Collected export data.
		 * @param array $keys    Selected data-type keys.
		 */
		$columns = (array) apply_filters(
			'sugar_calendar_admin_tools_export_csv_event_columns',
			[],
			$this->get_data(),
			$this->keys
		);
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		// The values are invoked to produce cells, so a non-callable would fatal
		// the whole export. Drop them here, where the header and the rows both
		// read the result, so the two cannot disagree about the column count.
		$callable = array_filter( $columns, 'is_callable' );

		// Log rather than drop in silence: the column just goes missing from a file
		// whose whole contract is a stable set of columns. Written to the log, not
		// raised as a notice — this request streams a file, and a displayed notice
		// would land in the response before the headers and corrupt the download.
		$dropped = array_keys( array_diff_key( $columns, $callable ) );

		if ( ! empty( $dropped ) ) {
			error_log( '[SC Export] events CSV columns skipped, value extractor not callable: ' . implode( ', ', $dropped ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $callable;
	}

	/**
	 * The fixed base columns present on every event row.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function event_base_columns(): array {

		// Derived from the same ordered map the events query aliases its SELECT
		// list to, so the header can never list a different set, or a different
		// order, than the query returns.
		return array_values( static::BASE_EVENT_COLUMNS );
	}

	/**
	 * Discover the dynamic event columns (featured image, meta and custom-field keys).
	 *
	 * @since 3.13.0
	 *
	 * @param array $events Event row objects.
	 *
	 * @return array Shape: [ 'featured' => bool, 'meta' => string[], 'cf' => string[] ].
	 */
	private function discover_event_columns( array $events ): array {

		$want_cf = $this->wants( 'custom_fields' );

		$featured  = false;
		$meta_keys = [];
		$cf_keys   = [];

		foreach ( $events as $event ) {
			if ( ! empty( $event->featured_image ) ) {
				$featured = true;
			}

			foreach ( $this->meta_map( $event->event_meta ?? [] ) as $key => $value ) {
				$meta_keys[ $key ] = true;
			}

			if ( $want_cf ) {
				foreach ( $this->meta_map( $event->custom_fields ?? [] ) as $key => $value ) {
					$cf_keys[ $key ] = true;
				}
			}
		}

		return [
			'featured' => $featured,
			'meta'     => array_keys( $meta_keys ),
			'cf'       => array_keys( $cf_keys ),
		];
	}

	/**
	 * Build the events CSV header row.
	 *
	 * MUST stay in lockstep with event_row() — same column groups, same order,
	 * same conditions.
	 *
	 * @since 3.13.0
	 *
	 * @param array $columns Discovered columns from discover_event_columns().
	 * @param array $extra   Add-on columns: column key => extractor callable.
	 *
	 * @return array
	 */
	private function event_header( array $columns, array $extra ): array {

		$header = $this->event_base_columns();

		if ( $columns['featured'] ) {
			$header[] = 'featured_image';
		}

		if ( $this->wants( 'calendars' ) ) {
			$header[] = 'calendars';
		}

		if ( $this->wants( 'tags' ) ) {
			$header[] = 'tags';
		}

		if ( $this->wants( 'orders' ) ) {
			$header = array_merge( $header, $this->linkage_header() );
		}

		// Add-on columns (e.g. Pro venue / speaker), before the meta / cf tail.
		$header = array_merge( $header, array_keys( $extra ) );

		// Meta and custom fields are always the last columns, meta then cf.
		foreach ( $columns['meta'] as $key ) {
			$header[] = 'meta:' . $key;
		}

		foreach ( $columns['cf'] as $key ) {
			$header[] = 'cf:' . $key;
		}

		return $header;
	}

	/**
	 * The six ticketing linkage column keys for the events CSV.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function linkage_header(): array {

		return [
			'ticket_count',
			'tickets',
			'order_count',
			'orders',
			'attendee_count',
			'attendees',
		];
	}

	/**
	 * Build a single event row aligned to the discovered columns.
	 *
	 * MUST stay in lockstep with event_header(): the column groups here
	 * (base, featured, meta, cf, calendars, tags, ticketing linkage) are emitted
	 * in the same order and under the same conditions as the header. Change one,
	 * change the other, or the cells shift under the wrong headers.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event     Event row object.
	 * @param array  $columns   Discovered columns.
	 * @param array  $extra     Add-on columns: column key => extractor callable.
	 *                          MUST be the same map event_header() received.
	 * @param array  $tag_slugs Tag id => slug map.
	 *
	 * @return array
	 */
	private function event_row( $event, array $columns, array $extra, array $tag_slugs ): array {

		$row = $this->base_cells( $event );

		if ( $columns['featured'] ) {
			$row[] = $event->featured_image ?? '';
		}

		if ( $this->wants( 'calendars' ) ) {
			$row[] = $this->list_cell( $event->calendars ?? [] );
		}

		if ( $this->wants( 'tags' ) ) {
			$row[] = $this->tags_cell( $event, $tag_slugs );
		}

		if ( $this->wants( 'orders' ) ) {
			$row = array_merge( $row, $this->linkage_cells( $event ) );
		}

		// Add-on columns, aligned to the same keys the header emitted.
		foreach ( $extra as $extractor ) {
			$row[] = (string) $extractor( $event );
		}

		// Meta and custom fields last, meta then cf.
		$row = array_merge( $row, $this->map_cells( $this->meta_map( $event->event_meta ?? [] ), $columns['meta'] ) );
		$row = array_merge( $row, $this->map_cells( $this->meta_map( $event->custom_fields ?? [] ), $columns['cf'] ) );

		return $row;
	}

	/**
	 * The tags cell for an event, mapped from tag ids to slugs (CSV-only).
	 *
	 * Falls back to the raw id when a slug is unknown (e.g. the map is empty).
	 *
	 * @since 3.13.0
	 *
	 * @param object $event     Event row object.
	 * @param array  $tag_slugs Tag id => slug map.
	 *
	 * @return string
	 */
	private function tags_cell( $event, array $tag_slugs ): string {

		$slugs = [];

		foreach ( (array) ( $event->tags ?? [] ) as $id ) {
			$slugs[] = $tag_slugs[ (int) $id ] ?? $id;
		}

		return $this->list_cell( $slugs );
	}

	/**
	 * Build the fixed base-column cells for an event.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event Event row object.
	 *
	 * @return array
	 */
	private function base_cells( $event ): array {

		$cells = [];

		foreach ( $this->event_base_columns() as $col ) {
			$cells[] = isset( $event->$col ) ? $event->$col : '';
		}

		return $cells;
	}

	/**
	 * Pull an ordered list of values from a map for the given keys.
	 *
	 * @since 3.13.0
	 *
	 * @param array $map  Key => value map.
	 * @param array $keys Ordered keys to pull.
	 *
	 * @return array
	 */
	private function map_cells( array $map, array $keys ): array {

		$cells = [];

		foreach ( $keys as $key ) {
			$cells[] = $map[ $key ] ?? '';
		}

		return $cells;
	}

	/**
	 * Render a list value as a single pipe-joined cell.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $value List value.
	 *
	 * @return string
	 */
	private function list_cell( $value ): string {

		return empty( $value ) ? '' : implode( '|', (array) $value );
	}

	/**
	 * An event's orders, narrowed to the ones orders.csv actually contains.
	 *
	 * Without this an event row would cite order ids missing from orders.csv —
	 * a trashed order is the everyday case. The prefetch behind $event->orders is
	 * shared with the JSON export (which has always carried every status), so the
	 * narrowing happens here rather than in the query.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event Event row object.
	 *
	 * @return array
	 */
	private function exported_orders( $event ): array {

		return array_filter(
			(array) ( $event->orders ?? [] ),
			static function ( $order ) {

				return in_array( $order->status ?? '', self::TICKETING_STATUSES, true );
			}
		);
	}

	/**
	 * Compute the six linkage cell values for an event.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event Event row object.
	 *
	 * @return array
	 */
	private function linkage_cells( $event ): array {

		$order_ids    = [];
		$ticket_ids   = [];
		$attendee_ids = [];

		foreach ( $this->exported_orders( $event ) as $order ) {
			$order_ids[] = $order->order_id;

			foreach ( (array) ( $order->tickets ?? [] ) as $ticket ) {
				$ticket_ids[] = $ticket->ticket_id;

				if ( ! empty( $ticket->attendee_id ) ) {
					$attendee_ids[] = $ticket->attendee_id;
				}
			}
		}

		$attendee_ids = array_values( array_unique( $attendee_ids ) );

		return [
			count( $ticket_ids ),
			implode( '|', $ticket_ids ),
			count( $order_ids ),
			implode( '|', $order_ids ),
			count( $attendee_ids ),
			implode( '|', $attendee_ids ),
		];
	}

	/**
	 * Turn a meta_key/meta_value row list into a key => value map.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $items Array of objects with meta_key/meta_value.
	 *
	 * @return array
	 */
	private function meta_map( $items ): array {

		$map = [];

		foreach ( (array) $items as $item ) {
			// Post meta and event meta both allow a key to repeat, and the flat CSV
			// has one cell per key — so join instead of letting the last row win and
			// drop the rest. Same separator the other list cells use.
			$map[ $item->meta_key ] = isset( $map[ $item->meta_key ] )
				? $map[ $item->meta_key ] . '|' . $item->meta_value
				: $item->meta_value;
		}

		return $map;
	}

	/**
	 * Render a list of records to a CSV string against a column map.
	 *
	 * `$map` is `header => source field`. A numeric key means the header and the
	 * source field share a name, so a table that renames nothing can be written
	 * as a plain list of columns. Records may be objects or associative arrays —
	 * both are read the same way.
	 *
	 * Public and static for the same reason render_csv() is: add-ons contributing
	 * tables through `sugar_calendar_admin_tools_export_csv_tables` (Pro's
	 * venues / speakers) render through this rather than re-deriving cells, so
	 * every table shares one quoting and injection guard.
	 *
	 * @since 3.13.0
	 *
	 * @param array $map     Map of header => source field, or a list of columns.
	 * @param mixed $records Records to render (objects or associative arrays).
	 *
	 * @return string
	 */
	public static function records_csv( array $map, $records ): string {

		$header = [];
		$fields = [];

		foreach ( $map as $column => $field ) {
			$header[] = is_int( $column ) ? $field : $column;
			$fields[] = $field;
		}

		$rows = [];

		foreach ( (array) $records as $record ) {

			$record = (array) $record;
			$cells  = [];

			foreach ( $fields as $field ) {
				$value = $record[ $field ] ?? '';

				// A non-scalar would reach fputcsv() as an array and warn; encode
				// it so the cell carries the value instead of breaking the row.
				$cells[] = is_scalar( $value ) ? $value : wp_json_encode( $value );
			}

			$rows[] = $cells;
		}

		return self::render_csv( $header, $rows );
	}

	/**
	 * Build the attendees CSV.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $attendees Attendee row objects (keyed by id).
	 *
	 * @return string
	 */
	private function attendees_csv( $attendees ): string {

		return self::records_csv(
			[
				'attendee_id',
				'first_name',
				'last_name',
				'email',
				'date_created',
			],
			$attendees
		);
	}

	/**
	 * Build the calendars CSV.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $calendars Calendar row objects (keyed by term id).
	 *
	 * @return string
	 */
	private function calendars_csv( $calendars ): string {

		return self::records_csv(
			[
				// The exported column is `id`; the row carries it as `term_id`.
				'id'          => 'term_id',
				'name'        => 'name',
				'slug'        => 'slug',
				'description' => 'description',
				'parent_slug' => 'parent_slug',
			],
			$calendars
		);
	}

	/**
	 * Build the tags CSV.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $tags Tag rows (assoc arrays).
	 *
	 * @return string
	 */
	private function tags_csv( $tags ): string {

		return self::records_csv(
			[
				'id',
				'name',
				'slug',
				'description',
			],
			$tags
		);
	}

	/**
	 * Render header + rows to a CSV string with RFC-4180 quoting and a
	 * CSV-injection guard.
	 *
	 * Public and static so add-ons contributing tables via the
	 * `sugar_calendar_admin_tools_export_csv_tables` filter (e.g. Pro's
	 * venues / speakers) reuse the exact same escaping and injection guard as the
	 * core tables, rather than re-implementing CSV encoding.
	 *
	 * @since 3.13.0
	 *
	 * @param array $header Column labels.
	 * @param array $rows   Rows (arrays of scalars).
	 *
	 * @return string
	 */
	public static function render_csv( array $header, array $rows ): string {

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		fputcsv( $handle, array_map( [ self::class, 'guard_cell' ], $header ), ',', '"', self::CSV_ESCAPE );

		foreach ( $rows as $row ) {
			fputcsv( $handle, array_map( [ self::class, 'guard_cell' ], $row ), ',', '"', self::CSV_ESCAPE );
		}

		rewind( $handle );

		$csv = stream_get_contents( $handle );

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $csv;
	}

	/**
	 * Neutralize CSV-injection vectors in a cell.
	 *
	 * Numeric values (incl. negatives like a refund's `-12.50`) are left as-is so
	 * spreadsheets and re-imports read them as numbers; only non-numeric values
	 * beginning with a formula trigger are prefixed with an apostrophe.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $value Cell value.
	 *
	 * @return string
	 */
	private static function guard_cell( $value ): string {

		$value = (string) $value;

		if ( $value === '' || is_numeric( $value ) ) {
			return $value;
		}

		if ( in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			$value = "'" . $value;
		}

		return $value;
	}
}
