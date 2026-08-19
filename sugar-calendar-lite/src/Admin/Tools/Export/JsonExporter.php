<?php

namespace Sugar_Calendar\Admin\Tools\Export;

/**
 * JSON exporter.
 *
 * Serializes the shared nested build (already re-importable) as a single JSON
 * file, and adds the JSON-only ticketing sections the shared build omits:
 * orphan orders, extra tickets, and — with no events selected — all orders.
 *
 * @since 3.13.0
 */
class JsonExporter extends AbstractExporter {

	/**
	 * Export format slug this class handles.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const FORMAT = 'json';

	/**
	 * Build the nested export payload.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	protected function collect(): array {

		$data = $this->base_export_data();

		// Keep the attendee map consistent with the date-range scope.
		if ( $this->has_date_range() && isset( $data['attendees'] ) ) {
			if ( $this->wants( 'events' ) ) {
				// Events selected: only the in-range events' ticket attendees belong.
				$data['attendees'] = $this->scope_attendees_to_events(
					(array) $data['attendees'],
					(array) ( $data['events'] ?? [] )
				);
			} else {
				// Events not selected: only the in-range orders' ticket attendees belong.
				$data['attendees'] = $this->scope_attendees_to_orders(
					(array) $data['attendees'],
					(array) ( $data['orders'] ?? [] )
				);
			}
		}

		return $data;
	}

	/**
	 * Keep only the attendees referenced by the given events' nested tickets.
	 *
	 * An event's attendees are exactly its orders' attendees, so this collects the
	 * orders and defers to scope_attendees_to_orders() rather than walking the same
	 * orders -> tickets -> attendee_id path a second time.
	 *
	 * @since 3.13.0
	 *
	 * @param array $attendees Attendee object map (keyed by attendee id).
	 * @param array $events    Event row objects (with nested orders -> tickets).
	 *
	 * @return array
	 */
	private function scope_attendees_to_events( array $attendees, array $events ): array {

		$orders = [];

		foreach ( $events as $event ) {
			foreach ( (array) ( $event->orders ?? [] ) as $order ) {
				$orders[] = $order;
			}
		}

		return $this->scope_attendees_to_orders( $attendees, $orders );
	}

	/**
	 * Keep only the attendees referenced by the given orders' nested tickets.
	 *
	 * @since 3.13.0
	 *
	 * @param array $attendees Attendee object map (keyed by attendee id).
	 * @param array $orders    Order row objects (with nested tickets).
	 *
	 * @return array
	 */
	private function scope_attendees_to_orders( array $attendees, array $orders ): array {

		$ids = [];

		foreach ( $orders as $order ) {
			foreach ( (array) ( $order->tickets ?? [] ) as $ticket ) {
				if ( ! empty( $ticket->attendee_id ) ) {
					$ids[ (string) $ticket->attendee_id ] = true;
				}
			}
		}

		return array_intersect_key( $attendees, $ids );
	}

	/**
	 * There is data to export when at least one section holds rows.
	 *
	 * A section key can be present but empty (e.g. an attendee map scoped down to
	 * nothing), so key presence alone is not enough — require that some section
	 * actually has data, otherwise the export fails loud instead of streaming an
	 * empty payload.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	public function has_items(): bool {

		foreach ( $this->get_data() as $section ) {
			if ( ! empty( $section ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Stream the JSON file and exit.
	 *
	 * @since 3.13.0
	 */
	public function output() {

		$this->downloader()->send( wp_json_encode( $this->get_data() ), 'application/json', 'json' );
	}

	/**
	 * Add the JSON-only ticketing sections the shared build leaves out.
	 *
	 * With events selected we add the orphan orders (not tied to any event) and
	 * the extra tickets; without events we add all orders plus order-orphan
	 * tickets. CSV never needs these — it queries its own page-parity tables.
	 *
	 * @since 3.13.0
	 */
	protected function collect_extra_ticketing() {

		if ( $this->wants( 'events' ) ) {
			// Events selected: orders ride nested under their in-range events. A
			// date range therefore scopes ticketing to those events, so the orphan
			// / standalone sections (which belong to no in-range event) are omitted.
			if ( $this->has_date_range() ) {
				return;
			}

			$this->get_orders_without_event_export_data();
			$this->get_extra_tickets_export_data();

			return;
		}

		// Events not selected: orders live in a flat top-level section. Emit it,
		// scoped by order date_created when a range is active (matching the CSV
		// export). Orphan tickets have no order to date-scope against, so a range
		// omits them.
		$this->get_all_orders_export_data();

		if ( ! $this->has_date_range() ) {
			$this->get_extra_tickets_export_data( [ 'order' ] );
		}
	}

	/**
	 * Get the orders not associated to any events export data.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_orders_without_event_export_data() {

		if ( isset( $this->export_data['extra_orders'] ) ) {
			return $this->export_data['extra_orders'];
		}

		$extra_orders = $this->get_orders_without_event();

		if ( empty( $extra_orders ) ) {
			$this->export_data['extra_orders'] = [];

			return $this->export_data['extra_orders'];
		}

		$this->export_data['extra_orders'] = $this->populate_orders_with_tickets_and_attendees( $extra_orders );

		return $this->export_data['extra_orders'];
	}

	/**
	 * Populate orders with tickets and attendees data.
	 *
	 * @since 3.13.0
	 *
	 * @param array $orders Orders.
	 *
	 * @return array
	 */
	private function populate_orders_with_tickets_and_attendees( $orders ) {

		$orders_count = count( $orders );

		// Get the tickets for each order.
		for ( $ctr = 0; $ctr < $orders_count; $ctr++ ) {
			$tickets = $this->get_tickets( 'order_id', $orders[ $ctr ]->order_id );

			if ( ! empty( $tickets ) ) {
				$orders[ $ctr ]->tickets = $tickets;
			}
		}

		return $orders;
	}

	/**
	 * Get the orders not associated to any events.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_orders_without_event() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the export tool over custom Sugar Calendar tables.
		return $wpdb->get_results(
			'SELECT ' . esc_sql( implode( ',', $this->get_orders_select_columns() ) )
			. ' FROM ' . $wpdb->prefix . 'sc_orders LEFT JOIN '
			. $wpdb->prefix . 'sc_events ON ' . $wpdb->prefix . 'sc_events.id = ' . $wpdb->prefix . 'sc_orders.event_id WHERE '
			. $wpdb->prefix . 'sc_events.id IS NULL'
		);
	}

	/**
	 * Get the extra tickets export data.
	 *
	 * @since 3.13.0
	 *
	 * @param array $context An array containing the context. E.g. ['event'], ['order'], etc.
	 *
	 * @return array
	 */
	private function get_extra_tickets_export_data( $context = [] ) {

		if ( ! isset( $this->export_data['extra_tickets'] ) ) {
			$this->export_data['extra_tickets'] = $this->get_extra_tickets( $context );
		}

		return $this->export_data['extra_tickets'];
	}

	/**
	 * Get tickets not associated to any events or orders.
	 *
	 * `$context` is an array containing the context. E.g. ['event'], ['order'], etc.
	 * If `$context` is empty, it will return tickets not associated to any events or orders.
	 * If `$context` is not empty, it will return tickets not associated to the context.
	 *
	 * @since 3.13.0
	 *
	 * @param array $context An array containing the context. E.g. ['event'], ['order'], etc.
	 *
	 * @return array
	 */
	private function get_extra_tickets( $context = [] ) {

		global $wpdb;

		$left_joins = [];
		$where      = [];

		$event_join  = ' LEFT JOIN ' . $wpdb->prefix . 'sc_events ON '
				. $wpdb->prefix . 'sc_events.`id` = ' . $wpdb->prefix . 'sc_tickets.`event_id`';
		$event_where = $wpdb->prefix . 'sc_events.`id` IS NULL';

		if ( in_array( 'event', $context, true ) ) {
			$left_joins[] = $event_join;
			$where[]      = $event_where;
		}

		$order_join  = ' LEFT JOIN ' . $wpdb->prefix . 'sc_orders ON '
				. $wpdb->prefix . 'sc_orders.`id` = ' . $wpdb->prefix . 'sc_tickets.`order_id`';
		$order_where = $wpdb->prefix . 'sc_orders.`id` IS NULL';

		if ( in_array( 'order', $context, true ) ) {
			$left_joins[] = $order_join;
			$where[]      = $order_where;
		}

		// Default.
		if ( empty( $left_joins ) || empty( $where ) ) {
			$left_joins = [ $event_join, $order_join ];
			$where      = [ $event_where, $order_where ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the export tool over custom Sugar Calendar tables.
		$results = $wpdb->get_results(
			'SELECT ' . esc_sql( implode( ',', $this->get_tickets_select_columns() ) )
			. ' FROM ' . $wpdb->prefix . 'sc_tickets ' . esc_sql( implode( ' ', $left_joins ) ) . ' LEFT JOIN '
			. $wpdb->prefix . 'sc_attendees ON ' . $wpdb->prefix . 'sc_attendees.`id` = ' . $wpdb->prefix . 'sc_tickets.`attendee_id` WHERE '
			. esc_sql( implode( ' OR ', $where ) )
		);

		if ( empty( $results ) ) {
			return [];
		}

		return $results;
	}

	/**
	 * Get all orders export data.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_all_orders_export_data() {

		if ( isset( $this->export_data['orders'] ) ) {
			return $this->export_data['orders'];
		}

		$orders = $this->get_all_orders();

		if ( empty( $orders ) ) {
			$this->export_data['orders'] = [];
		} else {
			$this->export_data['orders'] = $this->populate_orders_with_tickets_and_attendees( $orders );
		}

		return $this->export_data['orders'];
	}

	/**
	 * Get all orders.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_all_orders() {

		global $wpdb;

		$sql = 'SELECT ' . esc_sql( implode( ',', $this->get_orders_select_columns() ) )
			. ' FROM ' . $wpdb->prefix . 'sc_orders';

		// Scope by order creation date when a range is active, matching the CSV
		// export (which date-scopes its orders table on date_created the same way).
		// date_created is stored in UTC, so the bounds are converted from the
		// site's timezone rather than compared as submitted.
		$clauses = [];
		$args    = [];

		$start = $this->range_start_utc();
		$end   = $this->range_end_utc();

		if ( $start !== '' ) {
			$clauses[] = $wpdb->prefix . 'sc_orders.`date_created` >= %s';
			$args[]    = $start;
		}

		if ( $end !== '' ) {
			$clauses[] = $wpdb->prefix . 'sc_orders.`date_created` <= %s';
			$args[]    = $end;
		}

		if ( ! empty( $clauses ) ) {
			// Prepare only the date clauses, not the assembled statement: the same
			// reason query_events() does it this way — a `%` anywhere in the column
			// list would be read as a placeholder and silently empty the query.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql .= ' WHERE ' . $wpdb->prepare( implode( ' AND ', $clauses ), $args );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Direct query for the export tool over custom Sugar Calendar tables.
		return $wpdb->get_results( $sql );
	}
}
