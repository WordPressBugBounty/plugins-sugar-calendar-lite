<?php

namespace Sugar_Calendar\Admin\Tools;

use Sugar_Calendar\Features\Tags\Common\Helpers as TagHelpers;

class Exporter {

	/**
	 * Keys to export. E.g. 'events', 'calendars', 'orders', etc.
	 *
	 * @since 3.3.0
	 *
	 * @var array
	 */
	private $keys_to_import = [];

	/**
	 * Data to export.
	 *
	 * @since 3.3.0
	 *
	 * @var array
	 */
	private $export_data = [];

	/**
	 * Constructor.
	 *
	 * @since 3.3.0
	 *
	 * @param array $keys_to_export Array containing the data to export.
	 */
	public function __construct( $keys_to_export ) {

		$this->keys_to_import = $keys_to_export;
	}

	/**
	 * Export.
	 *
	 * @since 3.3.0
	 *
	 * @return array
	 */
	public function export() {

		if ( in_array( 'events', $this->keys_to_import, true ) ) {
			$this->export_with_events();
		} else {
			$this->export_without_events();
		}

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
		return apply_filters(
			'sugar_calendar_admin_tools_exporter_export_data',
			$this->export_data,
			$this->keys_to_import
		);
	}

	/**
	 * Export data with events.
	 *
	 * @since 3.3.0
	 */
	private function export_with_events() {

		foreach ( $this->keys_to_import as $key ) {

			switch ( $key ) {
				case 'events':
					$this->get_events_export_data();
					break;

				case 'calendars':
					$this->get_calendars_export_data();
					break;

				case 'orders':
					$this->get_attendees_export_data();
					$this->get_orders_without_event_export_data();
					$this->get_extra_tickets_export_data();
					break;

				case 'tags':
					$this->get_tags_export_data();
					$this->get_events_tags_relationship_data();
					break;
			}
		}
	}

	/**
	 * Export the data without events.
	 *
	 * @since 3.3.0
	 */
	private function export_without_events() {

		foreach ( $this->keys_to_import as $key ) {

			switch ( $key ) {
				case 'calendars':
					$this->get_calendars_export_data();
					break;

				case 'orders':
					$this->get_attendees_export_data();
					$this->get_all_orders_export_data();
					$this->get_extra_tickets_export_data( [ 'order' ] );
					break;

				case 'tags':
					$this->get_tags_export_data();
					break;
			}
		}
	}

	/**
	 * Get the events export data.
	 *
	 * @since 3.3.0
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
	 * Get the events to export from DB.
	 *
	 * @since 3.3.0
	 *
	 * @return array
	 */
	private function get_events() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.NestingLevel.MaxExceeded

		global $wpdb;

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
			[
				"{$wpdb->prefix}sc_events.`id`",
				"{$wpdb->prefix}sc_events.`object_id` AS `post_id`",
				"{$wpdb->prefix}sc_events.`title`",
				"{$wpdb->prefix}sc_events.`content`",
				"{$wpdb->prefix}sc_events.`status`",
				"{$wpdb->prefix}sc_events.`start` AS `start_date`",
				"{$wpdb->prefix}sc_events.`start_tz`",
				"{$wpdb->prefix}sc_events.`end` AS `end_date`",
				"{$wpdb->prefix}sc_events.`end_tz`",
				"{$wpdb->prefix}sc_events.`all_day`",
				"{$wpdb->prefix}sc_events.`recurrence`",
				"{$wpdb->prefix}sc_events.`recurrence_interval`",
				"{$wpdb->prefix}sc_events.`recurrence_count`",
				"{$wpdb->prefix}sc_events.`recurrence_end`",
				"{$wpdb->prefix}sc_events.`recurrence_end_tz`",
				"{$wpdb->prefix}sc_events.`date_created`",
				"{$wpdb->prefix}sc_events.`date_modified`",
			],
			$this->keys_to_import
		);

		$left_join_query        = '';
		$should_export_calendar = false;

		if ( in_array( 'calendars', $this->keys_to_import, true ) ) {
			// If we are exporting calendars, we need to get the calendar IDs.
			$should_export_calendar = true;
			$select_columns[]       = 'wp_tr.`calendar_ids`';
			$left_join_query        = ' LEFT JOIN ( '
				. 'SELECT ' . $wpdb->term_relationships . '.`object_id`, GROUP_CONCAT( ' . $wpdb->term_relationships . '.`term_taxonomy_id`) AS calendar_ids '
				. 'FROM ' . $wpdb->term_relationships . ' GROUP BY ' . $wpdb->term_relationships . '.`object_id` ) wp_tr '
				. 'ON wp_tr.`object_id` = ' . $wpdb->prefix . 'sc_events.`object_id`';
		}

		// Get the events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			'SELECT ' . esc_sql( implode( ',', $select_columns ) ) . ' FROM ' . $wpdb->prefix . 'sc_events'
			. esc_sql( $left_join_query )
		);

		if ( empty( $results ) ) {
			return [];
		}

		// Check if we should export ticket orders.
		$should_export_orders = in_array( 'orders', $this->keys_to_import, true );

		$results_count = count( $results );

		for ( $ctr = 0; $ctr < $results_count; $ctr++ ) {

			// Get featured image.
			$featured_image = get_the_post_thumbnail_url( $results[ $ctr ]->post_id, 'full' );

			if ( ! empty( $featured_image ) ) {
				$results[ $ctr ]->featured_image = esc_url( $featured_image );
			}

			$event_meta = $this->get_event_meta( $results[ $ctr ]->id );

			if ( ! empty( $event_meta ) ) {
				$results[ $ctr ]->event_meta = $event_meta;
			}

			// Check if we are exporting custom fields.
			if ( in_array( 'custom_fields', $this->keys_to_import, true ) ) {
				$results[ $ctr ]->custom_fields = $this->get_custom_fields( $results[ $ctr ]->post_id );
			}

			if ( $should_export_calendar ) {
				// If we are exporting calendars, let's convert the calendar IDs to slugs.
				$results[ $ctr ]->calendars = $this->convert_calendar_ids_to_slugs( $results[ $ctr ]->calendar_ids );

				unset( $results[ $ctr ]->calendar_ids );
			}

			if ( $should_export_orders ) {
				$orders = $this->get_orders_with_tickets_and_attendees( $results[ $ctr ]->id );

				if ( ! empty( $orders ) ) {
					$results[ $ctr ]->orders = $orders;
				}
			}
		}

		return $results;
	}

	/**
	 * Get the orders of an event with their tickets and attendees data.
	 *
	 * @since 3.3.0
	 *
	 * @param int $event_id Sugar Calendar event ID.
	 *
	 * @return array
	 */
	private function get_orders_with_tickets_and_attendees( $event_id ) {

		$orders = $this->get_orders( $event_id );

		if ( empty( $orders ) ) {
			return [];
		}

		return $this->populate_orders_by_event_id_with_tickets_and_attendees( $orders, $event_id );
	}

	/**
	 * Populate the provided `$orders` array, in the context of event IDs,
	 * with tickets and attendees data.
	 *
	 * @since 3.3.0
	 *
	 * @param array $orders   Array containing orders.
	 * @param int   $event_id Event ID.
	 *
	 * @return array
	 */
	private function populate_orders_by_event_id_with_tickets_and_attendees( $orders, $event_id ) {

		$tickets = $this->get_tickets( 'event_id', $event_id );

		$orders_count = count( $orders );

		for ( $orders_ctr = 0; $orders_ctr < $orders_count; $orders_ctr++ ) {
			$order_tickets = [];

			// Loop through the tickets of the event and assign them to their respective orders.
			foreach ( $tickets as $ticket_key => $ticket ) {
				if ( $ticket->order_id === $orders[ $orders_ctr ]->order_id ) {
					$order_tickets[] = $ticket;

					// Unset to make the next looping faster.
					unset( $tickets[ $ticket_key ] );
				}
			}

			if ( ! empty( $order_tickets ) ) {
				$orders[ $orders_ctr ]->tickets = $order_tickets;
			}
		}

		return $orders;
	}

	/**
	 * Get the event meta.
	 *
	 * @since 3.3.0
	 *
	 * @param int $event_id Sugar Calendar event ID.
	 *
	 * @return array
	 */
	private function get_event_meta( $event_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT meta_key, meta_value FROM ' . $wpdb->prefix . 'sc_eventmeta WHERE sc_event_id = %d',
				$event_id
			)
		);
	}

	/**
	 * Get the custom fields/post meta associated with the event.
	 *
	 * @since 3.3.0
	 *
	 * @param int $event_post_id Event post ID.
	 *
	 * @return array
	 */
	private function get_custom_fields( $event_post_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT meta_key, meta_value FROM ' . $wpdb->postmeta . ' WHERE post_id = %d',
				$event_post_id
			)
		);
	}

	/**
	 * Convert calendar IDs to slugs.
	 *
	 * @since 3.3.0
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
	 * @since 3.3.0
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
	 * @since 3.3.0
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
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
	 * @since 3.3.0
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
	 * @since 3.3.0
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
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
	 * Get the orders of an event.
	 *
	 * @since 3.3.0
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return array
	 */
	private function get_orders( $event_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ' . esc_sql( implode( ',', $this->get_orders_select_columns() ) )
				. ' FROM ' . $wpdb->prefix . 'sc_orders WHERE `event_id` = %d',
				$event_id
			)
		);
	}

	/**
	 * Get the select columns for orders.
	 *
	 * @since 3.3.0
	 *
	 * @return string[]
	 */
	private function get_orders_select_columns() {

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
	 * @since 3.3.0
	 *
	 * @param string $by      Either 'order_id' or 'event_id'.
	 * @param int    $context Context.
	 *
	 * @return array
	 */
	private function get_tickets( $by, $context ) {

		$by = strtolower( $by );

		if ( ! in_array( $by, [ 'order_id', 'event_id' ], true ) ) {
			return [];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
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
	 * @since 3.3.0
	 *
	 * @return string[]
	 */
	private function get_tickets_select_columns() {

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
	 * Get the orders not associated to any events export data.
	 *
	 * @since 3.3.0
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
	 * @since 3.3.0
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
	 * @since 3.3.0
	 *
	 * @return array
	 */
	private function get_orders_without_event() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
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
	 * @since 3.3.0
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
	 * @since 3.3.0
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
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
	 * @since 3.3.0
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
	 * @since 3.3.0
	 *
	 * @return array
	 */
	private function get_all_orders() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			'SELECT ' . esc_sql( implode( ',', $this->get_orders_select_columns() ) )
			. ' FROM ' . $wpdb->prefix . 'sc_orders'
		);
	}

	/**
	 * Get tags for export.
	 *
	 * @since 3.7.0
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
	 * @since 3.7.0
	 */
	private function get_events_tags_relationship_data() {

		global $wpdb;

		$taxonomy_id = TagHelpers::get_tags_taxonomy_id();

		// Get all events with their tags.
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
}
