<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

use DateTime;
use Sugar_Calendar\Helpers;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_attendee;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_ticket;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendees;

/**
 * Importer Class.
 *
 * @since 3.3.0
 */
abstract class Importer implements ImporterInterface {

	/**
	 * Contains the errors that occurred during the import process.
	 *
	 * @since 3.3.0
	 *
	 * @var array
	 */
	protected $errors = [
		'events'    => [],
		'calendars' => [],
		'tickets'   => [],
		'orders'    => [],
	];

	/**
	 * This contains the calendars that are either imported or already exists
	 * and available for use.
	 *
	 * This array uses the key as the calendar slug and the value as the calendar ID.
	 *
	 * @since 3.3.0
	 *
	 * @var array
	 */
	protected $available_calendars = [];

	/**
	 * This contains the attendees that are either imported or already exists
	 * and available for use.
	 *
	 * This array uses the key as the email and the value as the attendee ID.
	 *
	 * @since 3.3.0
	 *
	 * @var array
	 */
	protected $available_attendees = [];

	/**
	 * The total number of imported calendars.
	 *
	 * @since 3.3.0
	 *
	 * @var null|int `null` if no calendars are attempted to be imported. Otherwise, the total number of imported calendars.
	 */
	protected $imported_calendars_count = null;

	/**
	 * The total number of imported attendees.
	 *
	 * @since 3.3.0
	 *
	 * @var null|int `null` if no attendees are attempted to be imported. Otherwise, the total number of imported attendees.
	 */
	protected $imported_attendees_count = null;

	/**
	 * The total number of imported orders.
	 *
	 * @since 3.3.0
	 *
	 * @var null|int `null` if no orders are attempted to be imported. Otherwise, the total number of imported orders.
	 */
	protected $imported_orders_count = null;

	/**
	 * The total number of imported tickets.
	 *
	 * @since 3.3.0
	 *
	 * @var null|int `null` if no tickets are attempted to be imported. Otherwise, the total number of imported tickets.
	 */
	protected $imported_tickets_count = null;

	/**
	 * AJAX return status: Complete.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const AJAX_RETURN_STATUS_COMPLETE = 'complete';

	/**
	 * AJAX return status: In Progress.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const AJAX_RETURN_STATUS_IN_PROGRESS = 'in_progress';

	/**
	 * Get the page title of the importer.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public function get_title() {

		return __( 'Import', 'sugar-calendar-lite' );
	}

	/**
	 * Create a new Sugar Calendar event.
	 *
	 * @since 3.3.0
	 * @since 3.6.0 Add uuid to event data.
	 * @since 3.6.0 Added the `venue_id` to the event data.
	 *
	 * @param array $data The event data.
	 *
	 * @return false|array Returns `false` if unable to create Sugar Calendar event.
	 *                     Otherwise returns an array with the `sc_event_id` and `sc_event_post_id`.
	 */
	protected function create_sc_event( $data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.CyclomaticComplexity.TooHigh

		$old_id = 0;

		if ( ! empty( $data['post_id'] ) ) {
			$old_id = $data['post_id'];
		}

		// Insert the post.
		$post_id = wp_insert_post(
			[
				'post_title'   => $data['title'],
				'post_content' => $data['content'],
				'post_status'  => $data['status'],
				'post_type'    => sugar_calendar_get_event_post_type_id(),
				'post_author'  => get_current_user_id(),
			]
		);

		if ( empty( $post_id ) ) {

			$this->log_errors(
				'events',
				[
					'id'           => $old_id,
					'context_name' => $data['title'],
				]
			);

			return false;
		}

		if ( ! empty( $data['featured_image'] ) ) {
			$this->attach_featured_image( $post_id, $data['featured_image'] );
		} elseif ( ! empty( $data['post_thumbnail_id'] ) ) {
			// Set the post image.
			set_post_thumbnail( $post_id, $data['post_thumbnail_id'] );
		}

		$all_day = ! empty( $data['all_day'] );

		$event_data = [
			'object_id'      => absint( $post_id ),
			'object_type'    => 'post',
			'object_subtype' => sugar_calendar_get_event_post_type_id(),
			'title'          => sanitize_text_field( $data['title'] ),
			'content'        => wp_kses_post( $data['content'] ),
			'status'         => sanitize_text_field( $data['status'] ),
			'start'          => Helpers::sanitize_start( $data['start_date'], $data['end_date'], $all_day ),
			'start_tz'       => Helpers::sanitize_timezone( $data['start_tz'], $data['end_tz'], $all_day ),
			'end'            => Helpers::sanitize_end( $data['end_date'], $data['start_date'], $all_day ),
			'end_tz'         => Helpers::sanitize_timezone( $data['end_tz'], $data['start_tz'], $all_day ),
			'all_day'        => Helpers::sanitize_all_day( $all_day, $data['start_date'], $data['end_date'] ),
		];

		// If the venue ID is set, add it to the event data.
		if ( ! empty( $data['venue_id'] ) ) {
			$event_data['venue_id'] = absint( $data['venue_id'] );
		}

		if ( ! empty( $data['location'] ) ) {
			$event_data['location'] = sanitize_text_field( $data['location'] );
		}

		if ( ! empty( $data['url'] ) ) {
			$event_data['url']        = sanitize_url( $data['url'] );
			$event_data['url_target'] = absint( $data['url_target'] );
		}

		if ( ! empty( $data['uuid'] ) ) {
			$event_data['uuid'] = $data['uuid'];
		}

		if ( ! empty( $data['event_meta'] ) ) {
			// Get the event metas.
			$accept_list_meta = [
				'location'              => 1,
				'color'                 => 1,
				'url'                   => 1,
				'url_target'            => 1,
				'url_text'              => 1,
				'tickets'               => 1,
				'ticket_price'          => 1,
				'ticket_quantity'       => 1,
				'recurrence_byday'      => 1,
				'recurrence_bymonthday' => 1,
				'recurrence_bypos'      => 1,
				'recurrence_bymonth'    => 1,
				'show_map'              => 1,
			];

			foreach ( $data['event_meta'] as $meta ) {

				if (
					// If already in the `$event_data`, ignore.
					array_key_exists( $meta['meta_key'], $event_data ) ||
					! array_key_exists( $meta['meta_key'], $accept_list_meta )
				) {
					continue;
				}

				$event_data[ $meta['meta_key'] ] = $meta['meta_value'];
			}
		}

		// Handle recurrence data.
		if ( sugar_calendar()->is_pro() && ! empty( $data['recurrence'] ) ) {
			$sanitized_recurrence_data = $this->sanitize_recurrence_data( $data );

			if ( ! empty( $sanitized_recurrence_data ) ) {
				$event_data = array_merge( $event_data, $sanitized_recurrence_data );
			}
		}

		// Sanitize the color.
		$event_data['color'] = ! empty( $event_data['color'] ) ? sanitize_hex_color( $event_data['color'] ) : sugar_calendar_get_event_color( $post_id );

		$sc_event = sugar_calendar_add_event( $event_data );

		if ( empty( $sc_event ) ) {

			$this->log_errors(
				'events',
				[
					'id'           => $old_id,
					'context_name' => $data['title'],
				]
			);

			return false;
		}

		// Set the event to its calendar(s).
		if ( ! empty( $data['calendars'] ) ) {
			foreach ( $data['calendars'] as $calendar ) {
				$calendar_id = is_int( $calendar ) ? $calendar : $this->import_calendar( $calendar );

				if ( ! empty( $calendar_id ) ) {
					wp_set_object_terms( $post_id, $calendar, 'sc_event_category', true );
				}
			}
		}

		// Import custom fields.
		if ( ! empty( $data['custom_fields'] ) ) {
			$this->import_custom_fields( $post_id, $data['custom_fields'] );
		}

		// Import orders.
		if ( ! empty( $data['orders'] ) ) {
			// Loop through each of the orders.
			foreach ( $data['orders'] as $order ) {
				$this->import_order( $sc_event, $order );
			}
		}

		return [
			'sc_event_id'      => $sc_event,
			'sc_event_post_id' => $post_id,
		];
	}

	/**
	 * Update the event.
	 *
	 * @since 3.6.0
	 *
	 * @param int   $event_id  The event ID.
	 * @param array $event     The event data.
	 * @param Event $old_event The old event instance.
	 *
	 * @return array Returns an array with the `sc_event_id` and `sc_event_post_id`.
	 */
	protected function update_event( $event_id, $event, $old_event = null ) {

		$retval = [
			'sc_event_id'      => 0,
			'sc_event_post_id' => 0,
		];

		if ( is_null( $old_event ) ) {
			$old_event = sugar_calendar_get_event( $event_id );
		}

		$all_day = ! empty( $event['all_day'] );

		// Prepare the event data.
		$event_data = [
			'title'    => sanitize_text_field( $event['title'] ),
			'content'  => wp_kses_post( $event['content'] ),
			'status'   => sanitize_text_field( $event['status'] ),
			'start'    => Helpers::sanitize_start( $event['start_date'], $event['end_date'], $all_day ),
			'start_tz' => Helpers::sanitize_timezone( $event['start_tz'], $event['end_tz'], $all_day ),
			'end'      => Helpers::sanitize_end( $event['end_date'], $event['start_date'], $all_day ),
			'end_tz'   => Helpers::sanitize_timezone( $event['end_tz'], $event['start_tz'], $all_day ),
			'all_day'  => Helpers::sanitize_all_day( $all_day, $event['start_date'], $event['end_date'] ),
		];

		// Update the event.
		$updated_event = sugar_calendar_update_event( $event_id, $event_data, $old_event );

		if ( $updated_event ) {
			$retval['sc_event_id'] = $event_id;
		}

		// Update the WordPress post if old event instance is not empty.
		if ( ! empty( $old_event ) ) {

			$updated_post = $this->update_event_wp_post( $old_event, $event_data );

			if ( $updated_post ) {
				$retval['sc_event_post_id'] = $event_id;
			}
		}

		return $retval;
	}

	/**
	 * Update WordPress post of event.
	 *
	 * @since 3.6.0
	 *
	 * @param Event $old_event_instance Event instance before the update.
	 * @param array $event_data         Data used to update the event.
	 *
	 * @return mixed Returns the post ID if the post was updated, false otherwise, and WP_Error if there was an error.
	 */
	protected function update_event_wp_post( $old_event_instance, $event_data ) {

		// Map event data fields to post data fields.
		$field_map = [
			'title'   => 'post_title',
			'content' => 'post_content',
		];

		$post_data = [];

		// Loop through field mappings and update post data if changed.
		foreach ( $field_map as $event_field => $post_field ) {

			// Check if the event field exists and has been modified:
			// 1. The event data field must not be empty.
			// 2. The event data field value must be different from the old event instance value.
			if (
				! empty( $event_data[ $event_field ] )
				&&
				$event_data[ $event_field ] !== $old_event_instance->$event_field
			) {
				$post_data[ $post_field ] = $event_data[ $event_field ];
			}
		}

		// Update post if we have changes.
		if ( empty( $post_data ) ) {
			return false;
		}

		$post_data['ID'] = $old_event_instance->object_id;

		return wp_update_post( $post_data );
	}

	/**
	 * Attach a featured image to a post.
	 *
	 * @since 3.3.0
	 *
	 * @param int    $post_id   The post ID to attach the image to.
	 * @param string $image_url The URL of the image to attach.
	 *
	 * @return bool|int
	 */
	private function attach_featured_image( $post_id, $image_url ) {

		$attach_id = media_sideload_image(
			$image_url,
			$post_id,
			'',
			'id'
		);

		if ( ! is_int( $attach_id ) ) {
			return false;
		}

		return set_post_thumbnail( $post_id, $attach_id );
	}

	/**
	 * Sanitize the recurrence data.
	 *
	 * @since 3.3.0
	 *
	 * @param array $data An array containing the recurrence data.
	 *
	 * @return array|false Returns an array of sanitized recurrence data. Otherwise returns `false`.
	 */
	private function sanitize_recurrence_data( $data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$recurrence = $this->sanitize_recurrence( $data['recurrence'] );

		if ( empty( $recurrence ) ) {
			return false;
		}

		$recurrence_data = [];

		// We only add recurrence data if it's type is valid.
		$recurrence_data['recurrence']          = $recurrence;
		$recurrence_data['recurrence_count']    = ! empty( $data['recurrence_count'] ) ? absint( $data['recurrence_count'] ) : 0;
		$recurrence_data['recurrence_interval'] = ! empty( $data['recurrence_interval'] ) ? absint( $data['recurrence_interval'] ) : 0;

		if ( ! empty( $data['recurrence_end'] ) ) {
			// @todo - Check if proper datetime format.
			$recurrence_end = DateTime::createFromFormat( 'Y-m-d', $data['recurrence_end'] );

			if ( ! empty( $recurrence_end ) ) {
				// The end date should be the last second of the day.
				$recurrence_data['recurrence_end'] = $recurrence_end->format( 'Y-m-d 23:59:59' );
			} else {
				$recurrence_data['recurrence_end'] = $data['recurrence_end'];
			}
		}

		if ( ! empty( $data['recurrence_byday'] ) ) {
			$recurrence_data['recurrence_byday'] = $data['recurrence_byday'];
		}

		if ( ! empty( $data['recurrence_bymonthday'] ) ) {
			$recurrence_data['recurrence_bymonthday'] = $data['recurrence_bymonthday'];
		}

		if ( ! empty( $data['recurrence_bypos'] ) ) {
			$recurrence_data['recurrence_bypos'] = $data['recurrence_bypos'];
		}

		if ( ! empty( $data['recurrence_bymonth'] ) ) {
			$recurrence_data['recurrence_bymonth'] = $data['recurrence_bymonth'];
		}

		return $recurrence_data;
	}

	/**
	 * Update the ticket meta data of a Sugar Calendar event.
	 *
	 * @since 3.3.0
	 *
	 * @param int          $sc_event_id     The Sugar Calendar event ID.
	 * @param string|float $ticket_price    The price of the ticket.
	 * @param mixed        $ticket_capacity The capacity of the ticket.
	 *
	 * @return void
	 */
	protected function update_sc_event_ticket_meta( $sc_event_id, $ticket_price, $ticket_capacity ) {

		$ticket_capacity = (int) $ticket_capacity;

		if ( $ticket_capacity === -1 ) {
			$ticket_capacity = 9999;
		} elseif ( $ticket_capacity < 0 ) {
			$ticket_capacity = 0;
		}

		update_event_meta( $sc_event_id, 'tickets', 1 );
		update_event_meta( $sc_event_id, 'ticket_price', $ticket_price );
		update_event_meta( $sc_event_id, 'ticket_quantity', $ticket_capacity );
	}

	/**
	 * Check if a DB table exists.
	 *
	 * @since 3.3.0
	 *
	 * @param string $db_table_name DB table name.
	 *
	 * @return mixed
	 */
	protected function check_if_db_table_exists( $db_table_name ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				esc_sql( $wpdb->prefix . $db_table_name )
			)
		);
	}

	/**
	 * Get the SC attendee.
	 *
	 * @since 3.3.0
	 *
	 * @param string $email      Email address.
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 *
	 * @return false|int Returns the SC attendee ID when found, otherwise returns `false`.
	 */
	protected function get_sc_attendee( $email, $first_name, $last_name ) {

		if ( array_key_exists( $email, $this->available_attendees ) ) {
			return (int) $this->available_attendees[ $email ];
		}

		$found_attendee = get_attendees(
			[
				'number'     => 1,
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
			]
		);

		if ( ! empty( $found_attendee ) ) {
			return $found_attendee[0]->id;
		}

		return false;
	}

	/**
	 * Get or create an attendee.
	 *
	 * @since 3.3.0
	 *
	 * @param string $email      The email of the attendee.
	 * @param string $first_name The first name of the attendee.
	 * @param string $last_name  The last name of the attendee.
	 *
	 * @return int
	 */
	protected function get_or_create_sc_attendee( $email, $first_name, $last_name ) {

		$found_attendee = $this->get_sc_attendee( $email, $first_name, $last_name );

		if ( ! empty( $found_attendee ) ) {
			return $found_attendee;
		}

		// Create a new attendee.
		$add_attendee = add_attendee(
			[
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
			]
		);

		if ( ! empty( $add_attendee ) ) {

			if ( is_null( $this->imported_attendees_count ) ) {
				$this->imported_attendees_count = 0;
			}

			$this->imported_attendees_count++;
		}

		return $add_attendee;
	}

	/**
	 * Helper function to get the specific metadata by key from the result
	 * of `get_post_meta()`.
	 *
	 * @since 3.3.0
	 *
	 * @param string $meta_key  The metadata key we want to value.
	 * @param array  $meta_data The metadata from `get_post_meta()`.
	 *
	 * @return mixed|false
	 */
	protected function get_data_from_meta( $meta_key, $meta_data ) {

		if ( isset( $meta_data[ $meta_key ][0] ) ) {
			return $meta_data[ $meta_key ][0];
		}

		return false;
	}

	/**
	 * Sanitize a recurrence string.
	 *
	 * @since 3.3.0
	 *
	 * @param string $recurrence The recurrence string to sanitize.
	 *
	 * @return string
	 */
	private function sanitize_recurrence( $recurrence ) {

		$recurrence = strtolower( $recurrence );

		if ( in_array( $recurrence, [ 'daily', 'weekly', 'monthly', 'yearly' ], true ) ) {
			return $recurrence;
		}

		return '';
	}

	/**
	 * Import a calendar.
	 *
	 * @since 3.3.0
	 *
	 * @param string|array $calendar_data The calendar slug if `string` is passed.
	 *                                    Otherwise, an array containing the calendar data to import.
	 *
	 * @return false|int Returns `false` if unable to import the calendar. Otherwise, returns the calendar ID/term_taxonomy_id.
	 */
	protected function import_calendar( $calendar_data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.CyclomaticComplexity.MaxExceeded

		// Convert to array.
		if ( is_string( $calendar_data ) ) {
			$calendar_data = [
				'name'        => $calendar_data,
				'slug'        => $calendar_data,
				'description' => '',
			];
		}

		// Check the cache if the calendar already exists.
		if ( ! empty( $this->available_calendars[ $calendar_data['slug'] ] ) ) {
			return $this->available_calendars[ $calendar_data['slug'] ];
		}

		$term_exists = term_exists( $calendar_data['slug'], 'sc_event_category' );

		if ( ! empty( $term_exists ) ) {

			$this->available_calendars[ $calendar_data['slug'] ] = (int) $term_exists['term_taxonomy_id'];

			return $this->available_calendars[ $calendar_data['slug'] ];
		}

		// Check if the calendar has a parent.
		$parent = 0;

		if ( ! empty( $calendar_data['parent_slug'] ) ) {
			// First check the cache.
			if ( ! empty( $this->available_calendars[ $calendar_data['parent_slug'] ] ) ) {
				$parent = $this->available_calendars[ $calendar_data['parent_slug'] ];
			} else {
				$parent_term_exists = term_exists( $calendar_data['parent_slug'], 'sc_event_category' );

				if ( ! empty( $parent_term_exists ) ) {
					$parent = (int) $parent_term_exists['term_taxonomy_id'];
				}
			}
		}

		// Let's create the new calendar.
		$new_calendar = wp_insert_term(
			$calendar_data['name'],
			'sc_event_category',
			[
				'description' => $calendar_data['description'],
				'parent'      => $parent,
				'slug'        => $calendar_data['slug'],
			]
		);

		if ( is_wp_error( $new_calendar ) ) {

			$this->log_errors(
				'calendars',
				[
					'id'           => $calendar_data['term_id'],
					'context_name' => $calendar_data['name'],
				]
			);

			return false;
		}

		++$this->imported_calendars_count;

		// Insert meta if exists.
		if ( ! empty( $calendar_data['meta'] ) ) {
			foreach ( $calendar_data['meta'] as $meta_key => $meta_value ) {
				add_term_meta(
					$new_calendar['term_id'],
					$meta_key,
					is_array( $meta_value ) ? $meta_value[0] : $meta_value,
					true
				);
			}
		}

		$this->available_calendars[ $calendar_data['slug'] ] = $new_calendar['term_taxonomy_id'];

		return $this->available_calendars[ $calendar_data['slug'] ];
	}

	/**
	 * Import an attendee.
	 *
	 * @since 3.3.0
	 *
	 * @param array $attendee_data Contains the attendee data to import.
	 *
	 * @return false|int
	 */
	protected function import_attendee( $attendee_data ) {

		$attendee_id = $this->get_or_create_sc_attendee(
			$attendee_data['email'],
			$attendee_data['first_name'],
			$attendee_data['last_name']
		);

		if ( empty( $attendee_id ) ) {
			return false;
		}

		$this->available_attendees[ $attendee_data['email'] ] = $attendee_id;

		return $attendee_id;
	}

	/**
	 * Import an order.
	 *
	 * @since 3.3.0
	 *
	 * @param int   $sc_event   Sugar Calendar event ID associated with the order.
	 * @param array $order_data Order data.
	 *
	 * @return false|int Returns `false` if unable to import the order. Otherwise, returns the order ID.
	 */
	protected function import_order( $sc_event, $order_data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$old_order_id = 0;

		if ( ! empty( $order_data['order_id'] ) ) {
			$old_order_id = $order_data['order_id'];
		}

		if ( is_null( $this->imported_orders_count ) ) {
			$this->imported_orders_count = 0;
		}

		$new_order_data = [
			'transaction_id' => $order_data['transaction_id'],
			'currency'       => $order_data['currency'],
			'status'         => $order_data['status'],
			'discount_id'    => '',
			'subtotal'       => $order_data['subtotal'],
			'tax'            => $order_data['tax'],
			'discount'       => $order_data['discount'],
			'total'          => $order_data['total'],
			'event_id'       => empty( $sc_event ) ? 0 : $sc_event,
			'event_date'     => $order_data['event_date'],
			'email'          => $order_data['email'],
			'first_name'     => $order_data['first_name'],
			'last_name'      => $order_data['last_name'],
		];

		if ( $order_data['status'] === 'paid' ) {
			$new_order_data['date_paid'] = $order_data['date_created'];
		}

		$sc_order_id = add_order( $new_order_data );

		if ( empty( $sc_order_id ) ) {

			$this->log_errors(
				'orders',
				[
					'id'           => $old_order_id,
					'context_name' => $order_data['first_name'] . ' ' . $order_data['last_name'],
				]
			);

			return false;
		}

		// Increment the imported orders count.
		++$this->imported_orders_count;

		if ( ! empty( $order_data['tickets'] ) ) {
			foreach ( $order_data['tickets'] as $ticket ) {
				$this->import_ticket(
					$ticket,
					$sc_order_id,
					$sc_event,
					$order_data['event_date']
				);
			}
		}

		return $sc_order_id;
	}

	/**
	 * Import a ticket.
	 *
	 * @since 3.3.0
	 *
	 * @param array  $ticket_data      Ticket data to import.
	 * @param int    $order_id         Order ID of the ticket.
	 * @param int    $event_id         Event ID of the ticket.
	 * @param string $event_start_date Start date of the event.
	 *
	 * @return bool|int Returns `false` if unable to import the ticket. Otherwise, returns the ticket ID.
	 */
	protected function import_ticket( $ticket_data, $order_id, $event_id, $event_start_date ) {

		if ( is_null( $this->imported_tickets_count ) ) {
			$this->imported_tickets_count = 0;
		}

		$attendee_id = $this->get_or_create_sc_attendee(
			$ticket_data['email'],
			$ticket_data['first_name'],
			$ticket_data['last_name']
		);

		$ticket = add_ticket(
			[
				'attendee_id' => (int) $attendee_id,
				'event_date'  => $event_start_date,
				'event_id'    => $event_id,
				'order_id'    => $order_id,
			]
		);

		if ( $ticket ) {
			++$this->imported_tickets_count;

			return $ticket;
		}

		$this->log_errors(
			'tickets',
			[
				'id'           => $ticket_data['ticket_id'],
				'context_name' => $ticket_data['first_name'] . ' ' . $ticket_data['last_name'],
			]
		);

		return false;
	}

	/**
	 * Import custom fields.
	 *
	 * @since 3.3.0
	 *
	 * @param int   $post_id       Post ID.
	 * @param array $custom_fields Custom fields.
	 */
	protected function import_custom_fields( $post_id, $custom_fields ) {

		// Block list.
		$block_list = [
			'_edit_lock'    => 1,
			'_edit_last'    => 1,
			'_thumbnail_id' => 1, // We don't want to import old thumbnail ID.
		];

		foreach ( $custom_fields as $custom_field ) {

			if ( array_key_exists( $custom_field['meta_key'], $block_list ) ) {
				continue;
			}

			update_post_meta( $post_id, $custom_field['meta_key'], $custom_field['meta_value'] );
		}
	}

	/**
	 * Log errors.
	 *
	 * @since 3.3.0
	 *
	 * @param string $context The context of the error.
	 * @param mixed  $data    The data of the error.
	 */
	protected function log_errors( $context, $data ) {

		if ( ! array_key_exists( $context, $this->errors ) ) {
			return;
		}

		if ( $this->is_ajax() ) {
			// If we're on ajax, then we need to save the errors in transient.
			$errors = $this->get_errors();

			$errors[ $context ][] = $data;

			set_transient( $this->get_errors_transient_key(), wp_json_encode( $errors ), DAY_IN_SECONDS );

		} else {
			$this->errors[ $context ][] = $data;
		}
	}

	/**
	 * Get the HTML display of the errors.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	protected function get_error_html_display() {

		$context_errors = $this->get_errors();

		ob_start();

		foreach ( $context_errors as $context => $errors ) {

			if ( empty( $errors ) ) {
				continue;
			}

			$label = $this->get_error_label_by_context( $context );
			?>
			<div class="sc-admin-tools-import-errors">
				<p class="sc-admin-tools-import-errors__context">
					<strong>
						<?php echo esc_html( ucfirst( $context ) ); ?>
					</strong>
				</p>
				<ul>
					<?php
					foreach ( $errors as $error ) {
						?>
						<li>
							<?php
							printf(
								/* translators: 1: Context ID, 2: Label, 3: Name. */
								esc_html__( 'ID: %1$d, %2$s: %3$s' ),
								absint( $error['id'] ),
								esc_html( $label ),
								esc_html( $error['context_name'] )
							);
							?>
						</li>
						<?php
					}
					?>
				</ul>
			</div>
			<?php
		}

		$html = ob_get_clean();

		if ( empty( $html ) ) {
			return '';
		}

		return '<div id="sc-admin-tools-import-errors-wrapper" class="sc-admin-tools-import-notice sc-admin-tools-import-notice__warning"><p><strong>'
			. esc_html__( 'Some items could not be imported...' ) . '</strong></p>'
			. $html .
			'</div>';
	}

	/**
	 * Get the label of the error by context.
	 *
	 * @since 3.3.0
	 *
	 * @param string $context The context of the error.
	 *
	 * @return string
	 */
	private function get_error_label_by_context( $context ) {

		switch ( $context ) {
			case 'calendars':
				$label = __( 'Name', 'sugar-calendar-lite' );
				break;

			case 'orders':
				$label = __( 'Payer', 'sugar-calendar-lite' );
				break;

			case 'tickets':
				$label = __( 'Attendee', 'sugar-calendar-lite' );
				break;

			default:
				$label = __( 'Title', 'sugar-calendar-lite' );
				break;
		}

		return $label;
	}

	/**
	 * @{inheritdoc}
	 *
	 * @since 3.3.0
	 */
	public function get_errors() {

		if ( ! $this->is_ajax() ) {
			return $this->errors;
		}

		$errors = get_transient( $this->get_errors_transient_key() );

		if ( ! empty( $errors ) ) {
			$errors = json_decode( $errors, true );
		}

		// If we can't get the errors from transient, then return the default.
		if ( empty( $errors ) ) {
			return $this->errors;
		}

		return $errors;
	}

	/**
	 * Get the errors transient key.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	protected function get_errors_transient_key() {

		return 'sc_admin_tools_import_' . $this->get_slug() . '_errors';
	}
}
