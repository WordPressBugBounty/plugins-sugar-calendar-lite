<?php

namespace Sugar_Calendar\Admin;

use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\TicketsTab;
use Sugar_Calendar\AddOn\Ticketing\Database\Order_Query;
use Sugar_Calendar\Admin\EmailNotifications\EventRecentTicketsTable;
use Sugar_Calendar\Event_Query;
use Sugar_Calendar\Helper;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Options;
use Sugar_Calendar\Plugin;
use Sugar_Calendar\AddOn\Ticketing\Settings as Settings;
use WP_Post;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\currency_filter;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_orders;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\count_tickets;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_ticket_data;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\ticketing_provider_available_for_admin;

/**
 * Class EmailNotifications.
 *
 * @since 3.11.0
 */
class EmailNotifications {

	/**
	 * The SMTP upsell notice ID.
	 *
	 * @since 3.11.0
	 */
	const UPSELL_SMTP = 'smtp_upsell';

	/**
	 * The dismissed upsells user meta key.
	 *
	 * @since 3.11.0
	 */
	const DISMISSED_UPSELLS_KEY = 'sugar_calendar_email_notifications_dismissed_upsells';

	/**
	 * The Action Scheduler hook for batch email sending.
	 *
	 * @since 3.11.0
	 */
	const AS_HOOK_SEND_BATCH = 'sugar_calendar_email_notification_send_batch';

	/**
	 * The option name prefix for email notification jobs.
	 *
	 * @since 3.11.0
	 */
	const JOB_OPTION_PREFIX = 'sc_email_notification_job_';

	/**
	 * Register hooks.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function hooks() {

		// Render email preview page.
		add_action( 'admin_init', [ $this, 'render_preview' ] );

		// Output sidebar markup.
		add_action( 'sugar_calendar_admin_page_after', [ $this, 'admin_page_after' ] );
		add_action( 'in_admin_footer', [ $this, 'admin_page_after' ] );

		// Add event list table row actions.
		add_filter( 'post_row_actions', [ $this, 'post_row_actions' ], 10, 2 );

		// Ticketing addon filters.
		add_filter( 'sc_event_tickets_admin_nav', [ $this, 'event_ticketing_admin_navigation' ] );

		// Register AJAX callbacks.
		add_action( 'sugar_calendar_ajax_email_notifications_get_form_data', [ $this, 'ajax_get_form_data' ] );
		add_action( 'sugar_calendar_ajax_email_notifications_search_events', [ $this, 'ajax_search_events' ] );
		add_action( 'sugar_calendar_ajax_email_notifications_get_event_meta', [ $this, 'ajax_get_event_meta' ] );
		add_action( 'sugar_calendar_ajax_email_notifications_notify_attendees', [ $this, 'ajax_notify_attendees' ] );
		add_action( 'sugar_calendar_ajax_email_notifications_store_preview_data', [ $this, 'ajax_store_preview_data' ] );
		add_action( 'sugar_calendar_ajax_email_notifications_dismiss_upsell', [ $this, 'ajax_dismiss_upsell' ] );
		add_action( 'sugar_calendar_ajax_email_notifications_dismiss_job', [ $this, 'ajax_dismiss_job' ] );

		// Metaboxes.
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 10, 2 );

		// Display ticket action notices on the edit event page.
		add_action( 'admin_notices', [ $this, 'display_ticket_action_notice' ] );
		add_action( 'admin_notices', [ $this, 'display_job_notices' ] );

		// Enqueue assets.
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Whether the current page supports this feature.
	 *
	 * @since 3.11.0
	 *
	 * @return bool
	 */
	private function page_has_sidebar() {

		$admin = sugar_calendar()->get_admin();
		$slugs = [
			'events',
			'tickets_tickets',
			'event_edit',
			'rsvp_list',
		];

		foreach ( $slugs as $slug ) {
			if ( $admin->is_page( $slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the event post types supported by email notifications.
	 *
	 * @since 3.11.0
	 *
	 * @return string[]
	 */
	private function get_event_post_types() {

		/**
		 * Filter the event post types supported by email notifications.
		 *
		 * @since 3.11.0
		 *
		 * @param string[] $post_types Event post types.
		 */
		return apply_filters(
			'sugar_calendar_email_notifications_post_types',
			[ sugar_calendar_get_event_post_type_id() ]
		);
	}

	/**
	 * Output sidebar markup.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function admin_page_after() {

		if ( ! $this->page_has_sidebar() ) {
			return;
		}
		?>
		<div id="sugar-calendar-lite-sidebar"></div>
		<?php
	}

	/**
	 * Add event list table row actions.
	 *
	 * @since 3.11.0
	 *
	 * @param string[] $actions Row actions.
	 * @param WP_Post  $post    Current post.
	 *
	 * @return array
	 */
	public function post_row_actions( $actions, $post ) {

		if ( ! in_array( $post->post_type, $this->get_event_post_types(), true ) ) {
			return $actions;
		}

		$event = sugar_calendar_get_event_by_object(
			$post->ID,
			'post',
			[ 'object_subtype' => $post->post_type ]
		);

		if ( ! $this->event_supports_notifications( $event->id ) ) {
			return $actions;
		}

		$actions['sc_notify_attendees'] = sprintf(
			'<a href="#" data-sc-notify-event-attendees="%1$s">%2$s</a>',
			$event->id,
			__( 'Notify Attendees', 'sugar-calendar-lite' )
		);

		return $actions;
	}

	/**
	 * Filter event ticketing add-on navigation tabs;
	 *
	 * @since 3.11.0
	 *
	 * @param $tabs
	 *
	 * @return array
	 */
	public function event_ticketing_admin_navigation( $tabs ) {

		if ( ! sugar_calendar()->get_admin()->is_page( 'tickets_tickets' ) ) {
			return $tabs;
		}

		$tab = [
			'notify-attendees' => [
				'name'          => esc_html__( 'Notify Attendees', 'sugar-calendar-lite' ),
				'wrapper_class' => 'sc-et-notify-attendees-wrapper',
				'render'        => fn() => UI::button(
					[
						'text' => esc_html__( 'Notify Attendees', 'sugar-calendar-lite' ),
						'type' => 'tertiary',
						'size' => 'sm',
						'id'   => 'sugar-calendar-btn-notify-attendees',
						'data' => [],
					]
				),
			],
		];

		$tabs = array_slice( $tabs, 0, -2 ) + $tab + array_slice( $tabs, 2 );

		return $tabs;
	}

	/**
	 * Check whether an event has RSVP.
	 *
	 * @since 3.11.0
	 *
	 * @param $event_id
	 *
	 * @return bool
	 */
	private function event_has_rsvp( $event_id ) {

		return (
			function_exists( 'sc_rsvp' ) &&
			sc_rsvp()->get_event_integration()->is_rsvp_enable( $event_id )
		);
	}

	/**
	 * Check whether an event has tickets.
	 *
	 * @since 3.11.0
	 *
	 * @param $event_id
	 *
	 * @return bool
	 */
	private function event_has_ticketing( $event_id ) {

		return (
			ticketing_provider_available_for_admin() &&
			get_event_meta( $event_id, 'tickets', true )
		);
	}

	/**
	 * Check whether an event supports notifications.
	 *
	 * @since 3.11.0
	 *
	 * @param int|string $event
	 *
	 * @return bool
	 */
	private function event_supports_notifications( $event_id ) {

		return (
			$this->event_has_ticketing( $event_id ) ||
			$this->event_has_rsvp( $event_id )
		);
	}

	/**
	 * Determine the notification type from the request context.
	 *
	 * @since 3.11.0
	 *
	 * @return string 'ticketing', 'rsvp', or '' for all.
	 */
	private function determine_notification_type() {

		$type = isset( $_REQUEST['type'] ) ? sanitize_key( $_REQUEST['type'] ) : '';

		if ( in_array( $type, [ 'ticketing', 'rsvp' ], true ) ) {
			return $type;
		}

		return '';
	}

	/**
	 * Check whether an event matches the given notification type.
	 *
	 * @since 3.11.0
	 *
	 * @param int    $event_id Event ID.
	 * @param string $type     Notification type: 'ticketing', 'rsvp', or '' for all.
	 *
	 * @return bool
	 */
	private function event_matches_type( $event_id, $type ) {

		switch ( $type ) {
			case 'ticketing':
				return $this->event_has_ticketing( $event_id );

			case 'rsvp':
				return $this->event_has_rsvp( $event_id );

			default:
				return $this->event_supports_notifications( $event_id );
		}
	}

	/**
	 * Get sender email address.
	 *
	 * @since 3.11.0
	 *
	 * @return string
	 */
	private function get_from_email() {

		$from_email = Settings\get_setting( 'receipt_from_email' );

		if ( empty( $from_email ) ) {
			$from_email = get_bloginfo( 'admin_email' );
		}

		return $from_email;
	}

	/**
	 * Get sender name.
	 *
	 * @since 3.11.0
	 *
	 * @return string
	 */
	private function get_from_name() {

		$from_email = Settings\get_setting( 'receipt_from_name' );

		if ( empty( $from_email ) ) {
			$from_email = get_bloginfo( 'name' );
		}

		return $from_email;
	}

	/**
	 * Whether an SMTP plugin is active.
	 *
	 * @since 3.11.0
	 *
	 * @return bool
	 */
	private function smtp_plugin_active() {

		if ( function_exists( 'wp_mail_smtp' ) ) {
			return true;
		}

		if ( function_exists( 'easy_wp_smtp' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether an upsell has been dismissed by the current user.
	 *
	 * @since 3.11.0
	 *
	 * @param string $upsell_id Upsell ID.
	 *
	 * @return bool
	 */
	private function is_upsell_dismissed( $upsell_id ) {

		$dismissed = get_user_meta( get_current_user_id(), self::DISMISSED_UPSELLS_KEY, true );
		$dismissed = $dismissed ? $dismissed : [];

		return in_array( $upsell_id, $dismissed, true );
	}

	/**
	 * AJAX handler for dismissing upsells.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function ajax_dismiss_upsell() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		if ( ! isset( $_POST['upsell_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error();
		}

		$upsell_id = sanitize_key( $_POST['upsell_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$allowed = [ self::UPSELL_SMTP ];

		if ( ! in_array( $upsell_id, $allowed, true ) ) {
			wp_send_json_error();
		}

		$dismissed   = get_user_meta( get_current_user_id(), self::DISMISSED_UPSELLS_KEY, true );
		$dismissed   = $dismissed ? $dismissed : [];
		$dismissed[] = $upsell_id;

		update_user_meta(
			get_current_user_id(),
			self::DISMISSED_UPSELLS_KEY,
			array_unique( $dismissed )
		);

		wp_send_json_success();
	}

	/**
	 * Get the available smart tags for email notifications.
	 *
	 * @since 3.11.0
	 *
	 * @return array
	 */
	private function get_smart_tags() {

		return [
			'attendee' => [
				'label' => __( 'Attendee', 'sugar-calendar-lite' ),
				'tags'  => [
					[
						'value'       => 'name',
						'description' => __( 'Attendee name', 'sugar-calendar-lite' ),
					],
					[
						'value'       => 'email',
						'description' => __( 'Attendee email', 'sugar-calendar-lite' ),
					],
				],
			],
			'event'    => [
				'label' => __( 'Event', 'sugar-calendar-lite' ),
				'tags'  => [
					[
						'value'       => 'event_title',
						'description' => __( 'Event title', 'sugar-calendar-lite' ),
					],
					[
						'value'       => 'event_url',
						'description' => __( 'Event URL', 'sugar-calendar-lite' ),
					],
					[
						'value'       => 'event_date',
						'description' => __( 'Event date', 'sugar-calendar-lite' ),
					],
					[
						'value'       => 'event_end_date',
						'description' => __( 'Event end date', 'sugar-calendar-lite' ),
					],
					[
						'value'       => 'event_start_time',
						'description' => __( 'Event start time', 'sugar-calendar-lite' ),
					],
					[
						'value'       => 'event_end_time',
						'description' => __( 'Event end time', 'sugar-calendar-lite' ),
					],
				],
			],
			'site'     => [
				'label' => __( 'Site', 'sugar-calendar-lite' ),
				'tags'  => [
					[
						'value'       => 'site_name',
						'description' => __( 'Site name', 'sugar-calendar-lite' ),
					],
				],
			],
		];
	}

	/**
	 * Build event tag data from an event object.
	 *
	 * @since 3.11.0
	 *
	 * @param \Sugar_Calendar\Event $event Event object.
	 *
	 * @return array Associative array of event tag values.
	 */
	private function get_event_tag_data( $event, $occurrence_id = 0 ) {

		$date_format = Options::get( 'date_format' );
		$time_format = Options::get( 'time_format' );

		$start = $event->start;
		$end   = $event->end;

		// Use occurrence-specific dates when available.
		if (
			! empty( $occurrence_id ) &&
			class_exists( 'Sugar_Calendar\Pro\Features\AdvancedRecurring\Occurrence' )
		) {
			$occurrence = \Sugar_Calendar\Pro\Features\AdvancedRecurring\Occurrence::get_by_id( $occurrence_id );

			if ( $occurrence->get_id() ) {
				$start = $occurrence->get_start();
				$end   = $occurrence->get_end();
			}
		}

		$all_day_label = $event->is_all_day()
			? esc_html__( 'All-day', 'sugar-calendar-lite' )
			: '';

		return [
			'event_title'      => $event->title,
			'event_url'        => Helper::get_event_frontend_url( $event ),
			'event_date'       => sugar_calendar_format_date_i18n( $date_format, $start ),
			'event_end_date'   => sugar_calendar_format_date_i18n( $date_format, $end ),
			'event_start_time' => $all_day_label ?: sugar_calendar_format_date_i18n( $time_format, $start ),
			'event_end_time'   => $all_day_label ?: sugar_calendar_format_date_i18n( $time_format, $end ),
		];
	}

	/**
	 * Replace smart tags in text with attendee-specific values.
	 *
	 * @since 3.11.0
	 *
	 * @param string $text     Text containing smart tags.
	 * @param array  $attendee Attendee record with tag values.
	 *
	 * @return string Text with tags replaced.
	 */
	private function replace_smart_tags( $text, $attendee ) {

		$replacements = [
			'{name}'             => $attendee['name'] ?? '',
			'{email}'            => $attendee['email'] ?? '',
			'{event_title}'      => $attendee['event_title'] ?? '',
			'{event_url}'        => $attendee['event_url'] ?? '',
			'{event_date}'       => $attendee['event_date'] ?? '',
			'{event_end_date}'   => $attendee['event_end_date'] ?? '',
			'{event_start_time}' => $attendee['event_start_time'] ?? '',
			'{event_end_time}'   => $attendee['event_end_time'] ?? '',
			'{site_name}'        => get_bloginfo( 'name' ),
		];

		foreach ( $replacements as $tag => $value ) {
			// Keep the smart tag literal if no data is available.
			if ( $value === '' ) {
				continue;
			}

			$text = str_replace( $tag, $value, $text );
		}

		return wp_kses_post( $text );
	}

	private function get_event_tickets( $event_id, $count_only = false ) {

		return get_orders( [
			'event_id' => $event_id,
			'number'   => 0,
			'count'    => $count_only,
		] );
	}

	private function get_event_rsvps( $event_id, $going_only = false, $count_only = false ) {

		if ( ! class_exists( 'Sugar_Calendar_Rsvp\Model\RsvpQuery' ) ) {
			return $count_only ? 0 : [];
		}

		$items = ( new \Sugar_Calendar_Rsvp\Model\RsvpQuery( [ 'event_id' => $event_id, 'number' => -1 ] ) )->get_items();

		if ( $going_only ) {
			$items = array_filter(
				$items,
				fn( $item ) => $item->going
			);
		}

		if ( $count_only ) {
			$items = array_reduce(
				$items,
				fn( $carry, $rsvp ) => $carry + $rsvp->get_total_attendees_count(),
				0
			);
		}

		return $items;
	}

	/**
	 * Get form script data.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function ajax_get_form_data() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$event_ids = isset( $_REQUEST['event_ids'] ) ? array_map( 'intval', wp_unslash( $_REQUEST['event_ids'] ) ) : [];
		$event_ids = array_filter(
			$event_ids,
			fn( $event_id ) => $this->event_supports_notifications( $event_id )
		);

		$data = [
			'meta'       => [
				'upsell' => ! $this->smtp_plugin_active() && ! $this->is_upsell_dismissed( self::UPSELL_SMTP ),
			],
			'from_email' => $this->get_from_email(),
			'from_name'  => $this->get_from_name(),
			'events'     => array_values( $event_ids ),
		];

		wp_send_json_success( $data );
	}

	/**
	 * Search events for the Notify Attendees sidebar.
	 *
	 * Returns up to 10 notification-capable events matching the search term.
	 * When search is empty, returns the latest 10 events.
	 * When event_ids is provided, those events are also included (for pre-selected chips).
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function ajax_search_events() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$search    = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';
		$event_ids = isset( $_REQUEST['event_ids'] ) ? array_map( 'intval', wp_unslash( $_REQUEST['event_ids'] ) ) : [];

		// Fetch more than 10 so post-filter for notification capability still yields ~10 results.
		$query_args = [
			'object_subtype__in' => $this->get_event_post_types(),
			'number'             => 50,
			'orderby'            => 'date_created',
			'order'              => 'DESC',
		];

		if ( ! empty( $search ) ) {
			$query_args['search']         = $search;
			$query_args['search_columns'] = [ 'title' ];
		}

		$event_query = new Event_Query( $query_args );

		$events = array_values( array_slice(
			array_filter(
				$event_query->items,
				fn( $event ) => $this->event_supports_notifications( $event->id )
			),
			0,
			10
		) );

		// Also fetch specific event IDs (for pre-selected chips).
		if ( ! empty( $event_ids ) ) {
			$existing_ids = array_map( fn( $e ) => intval( $e->id ), $events );

			foreach ( $event_ids as $event_id ) {
				if ( in_array( $event_id, $existing_ids, true ) ) {
					continue;
				}

				$event = sugar_calendar_get_event( $event_id );

				if ( ! empty( $event ) && $this->event_supports_notifications( $event->id ) ) {
					$events[] = $event;
				}
			}
		}

		$result = array_map(
			fn( $event ) => [
				'id'    => intval( $event->id ),
				'title' => $event->title,
			],
			$events
		);

		wp_send_json_success( [ 'events' => array_values( $result ) ] );
	}

	/**
	 * Get event metadata for selected events.
	 *
	 * Returns attendee counts and RSVP flag for the given event IDs.
	 * Called when events are selected in the Notify Attendees sidebar.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function ajax_get_event_meta() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$event_ids = isset( $_REQUEST['event_ids'] ) ? array_map( 'intval', wp_unslash( $_REQUEST['event_ids'] ) ) : [];

		if ( empty( $event_ids ) ) {
			wp_send_json_success( [ 'events' => [] ] );
		}

		$result = [];

		foreach ( $event_ids as $event_id ) {
			$ticket_count      = 0;
			$rsvps_count       = 0;
			$rsvps_going_count = 0;

			if ( $this->event_has_ticketing( $event_id ) ) {
				$ticket_count = count_tickets( [ 'event_id' => $event_id ] );
			} elseif ( $this->event_has_rsvp( $event_id ) ) {
				$rsvps_count       = $this->get_event_rsvps( $event_id, false, true );
				$rsvps_going_count = $this->get_event_rsvps( $event_id, true, true );
			}

			$result[] = [
				'id'                => $event_id,
				'has_rsvp'          => $this->event_has_rsvp( $event_id ),
				'ticket_count'      => $ticket_count,
				'rsvps_count'       => $rsvps_count,
				'rsvps_going_count' => $rsvps_going_count,
			];
		}

		wp_send_json_success( [ 'events' => $result ] );
	}

	/**
	 * Notify attendees.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function ajax_notify_attendees() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$data = ! empty( $_REQUEST['data'] ) ? wp_unslash( $_REQUEST['data'] ) : [];
		$data = wp_parse_args(
			$data,
			[
				'events'                => [],
				'from_email'            => '',
				'from_name'             => '',
				'filter_rsvp'           => 'going',
				'add_custom_recipients' => false,
				'custom_recipients'     => [],
				'email_subject'         => '',
				'email_body'            => '',
			]
		);

		if (
			empty( $data['events'] ) ||
			empty( $data['from_email'] ) ||
			empty( $data['from_name'] ) ||
			empty( $data['email_subject'] ) ||
			empty( $data['email_body'] )
		) {
			wp_send_json_error();
		}

		$event_ids             = array_map( 'intval', $data['events'] );
		$add_custom_recipients = (bool) $data['add_custom_recipients'];
		$custom_recipients     = $add_custom_recipients ?
			array_map( 'sanitize_email', $data['custom_recipients'] ) :
			[];

		$from_email    = sanitize_email( $data['from_email'] );
		$from_name     = sanitize_text_field( $data['from_name'] );
		$email_subject = sanitize_text_field( $data['email_subject'] );
		$email_body    = wp_kses_post( $data['email_body'] );
		$filter_rsvp   = sanitize_key( $data['filter_rsvp'] );

		$attendees = [];

		foreach ( $event_ids as $event_id ) {
			if ( empty( sugar_calendar_get_event( $event_id ) ) ) {
				continue;
			}

			if ( $this->event_has_ticketing( $event_id ) ) {
				$use_occurrence_dates = (bool) get_event_meta( $event_id, 'tickets_sell_per_occurrence', true );
				$orders               = $this->get_event_tickets( $event_id );

				foreach ( $orders as $order ) {
					$attendees[] = [
						'name'          => trim( $order->first_name . ' ' . $order->last_name ),
						'email'         => $order->email,
						'event_id'      => $event_id,
						'occurrence_id' => ! empty( $order->occurrence_id ) && $use_occurrence_dates
							? intval( $order->occurrence_id )
							: 0,
					];
				}
			} elseif ( $this->event_has_rsvp( $event_id ) ) {
				$use_occurrence_dates = (bool) get_event_meta( $event_id, 'rsvp_unique_per_occurrence', true );
				$going_only           = $filter_rsvp === 'going';
				$not_going            = $filter_rsvp === 'not_going';

				$rsvps = $this->get_event_rsvps( $event_id, $going_only );

				if ( $not_going ) {
					$rsvps = array_filter(
						$this->get_event_rsvps( $event_id ),
						fn( $item ) => ! $item->going
					);
				}

				foreach ( $rsvps as $rsvp ) {
					$occurrence_id = ! empty( $rsvp->main_attendee->occurrence_id ) && $use_occurrence_dates
						? intval( $rsvp->main_attendee->occurrence_id )
						: 0;

					// Main attendee.
					$attendees[] = [
						'name'          => $rsvp->main_attendee->name,
						'email'         => $rsvp->main_attendee->email,
						'event_id'      => $event_id,
						'occurrence_id' => $occurrence_id,
					];

					// Additional attendees.
					foreach ( $rsvp->get_additional_attendees() as $additional ) {
						$attendees[] = [
							'name'          => $additional->name,
							'email'         => $additional->email,
							'event_id'      => $event_id,
							'occurrence_id' => $occurrence_id,
						];
					}
				}
			}
		}

		// Add custom recipients.
		// When a single event is selected, the batch processor will
		// resolve event smart tags. Otherwise, tags are kept as literals.
		$seen_custom = [];

		foreach ( $custom_recipients as $recipient_email ) {
			if ( in_array( $recipient_email, $seen_custom, true ) ) {
				continue;
			}

			$seen_custom[] = $recipient_email;

			$attendees[] = [
				'name'          => '',
				'email'         => $recipient_email,
				'event_id'      => count( $event_ids ) === 1 ? $event_ids[0] : 0,
				'occurrence_id' => 0,
			];
		}

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			"From: $from_name <$from_email>",
		];

		$total = count( $attendees );

		if ( $total === 0 ) {
			wp_send_json_error();
		}

		// Fallback to synchronous sending when Action Scheduler is not available.
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$failed           = 0;
			$event_data_cache = [];

			foreach ( $attendees as $attendee ) {
				$event_id      = $attendee['event_id'] ?? 0;
				$occurrence_id = $attendee['occurrence_id'] ?? 0;
				$cache_key     = $event_id . '-' . $occurrence_id;

				if ( $event_id > 0 && ! isset( $event_data_cache[ $cache_key ] ) ) {
					$event = sugar_calendar_get_event( $event_id );

					$event_data_cache[ $cache_key ] = ! empty( $event )
						? $this->get_event_tag_data( $event, $occurrence_id )
						: [];
				}

				$full_attendee    = array_merge( $attendee, $event_data_cache[ $cache_key ] ?? [] );
				$resolved_subject = $this->replace_smart_tags( $email_subject, $full_attendee );
				$resolved_body    = $this->replace_smart_tags( $email_body, $full_attendee );
				$resolved_email   = $this->build_email( $resolved_subject, $resolved_body );

				if ( ! wp_mail( $attendee['email'], $resolved_subject, $resolved_email, $headers ) ) {
					++$failed;
				}
			}

			wp_send_json_success(
				[
					'total'        => $total,
					'failed_count' => $failed,
				]
			);

			return;
		}

		// Create a background job.
		$job_id = uniqid( 'notify_' );

		/**
		 * Filter the number of emails sent per Action Scheduler batch.
		 *
		 * @since 3.11.0
		 *
		 * @param int $batch_size Number of emails per batch. Default 25.
		 */
		$batch_size = apply_filters( 'sugar_calendar_email_notification_batch_size', 25 );

		$job = [
			'job_id'            => $job_id,
			'status'            => 'processing',
			'attendees'         => $attendees,
			'email_subject'     => $email_subject,
			'email_body'        => $email_body,
			'headers'           => $headers,
			'batch_size'        => $batch_size,
			'total'             => $total,
			'sent'              => 0,
			'failed'            => 0,
			'failed_recipients' => [],
			'created_at'        => time(),
			'completed_at'      => null,
		];

		update_option( self::JOB_OPTION_PREFIX . $job_id, $job, false );

		// Schedule one AS action per batch.
		$num_batches = (int) ceil( $total / $batch_size );

		for ( $i = 0; $i < $num_batches; $i++ ) {

			/**
			 * Filters the schedule time.
			 *
			 * @param int $time        The time when the action is scheduled.
			 * @param int $i           Current batch number.
			 * @param int $num_batches Total number of batches.
			 * @since 3.11.0
			 */
			$schedule_time = apply_filters(
				'sce_admin_email_notfications_notify_attendees_queue_time',
				time() + ($i * 2),
				$i,
				$num_batches
			);

			as_schedule_single_action(
				$schedule_time,
				self::AS_HOOK_SEND_BATCH,
				[
					'job_id'      => $job_id,
					'batch_index' => $i,
				],
				\Sugar_Calendar\Tasks\Tasks::GROUP
			);
		}

		wp_send_json_success(
			[
				'job_id' => $job_id,
				'total'  => $total,
			]
		);
	}

	/**
	 * Process a batch of notification emails.
	 *
	 * Called by Action Scheduler for each batch. Loads the job from
	 * wp_options, sends emails for the batch slice, and updates
	 * progress counters.
	 *
	 * @since 3.11.0
	 *
	 * @access public
	 *
	 * @param string $job_id      The unique job identifier.
	 * @param int    $batch_index The zero-based batch index.
	 *
	 * @return void
	 */
	public function process_email_batch( $job_id, $batch_index ) {

		$option_key = self::JOB_OPTION_PREFIX . $job_id;
		$job        = get_option( $option_key );

		if ( empty( $job ) || $job['status'] !== 'processing' ) {
			return;
		}

		$batch_size = $job['batch_size'];
		$offset     = $batch_index * $batch_size;
		$batch      = array_slice( $job['attendees'], $offset, $batch_size );

		if ( empty( $batch ) ) {
			return;
		}

		$sent              = 0;
		$failed            = 0;
		$failed_recipients = [];

		// Cache event tag data per event/occurrence to avoid redundant DB queries.
		$event_data_cache = [];

		foreach ( $batch as $attendee ) {
			$event_id      = $attendee['event_id'] ?? 0;
			$occurrence_id = $attendee['occurrence_id'] ?? 0;
			$cache_key     = $event_id . '-' . $occurrence_id;

			// Resolve event data on first encounter, then reuse from cache.
			if ( $event_id > 0 && ! isset( $event_data_cache[ $cache_key ] ) ) {
				$event = sugar_calendar_get_event( $event_id );

				$event_data_cache[ $cache_key ] = ! empty( $event )
					? $this->get_event_tag_data( $event, $occurrence_id )
					: [];
			}

			$full_attendee    = array_merge( $attendee, $event_data_cache[ $cache_key ] ?? [] );
			$resolved_subject = $this->replace_smart_tags( $job['email_subject'], $full_attendee );
			$resolved_body    = $this->replace_smart_tags( $job['email_body'], $full_attendee );
			$resolved_email   = $this->build_email( $resolved_subject, $resolved_body );

			if ( wp_mail( $attendee['email'], $resolved_subject, $resolved_email, $job['headers'] ) ) {
				++$sent;
			} else {
				++$failed;
				$failed_recipients[] = [
					'email'       => $attendee['email'],
					'name'        => $attendee['name'],
					'event_id'    => $event_id,
					'event_title' => $full_attendee['event_title'] ?? '',
				];
			}
		}

		// Update job progress. Re-read to reduce race conditions between batches.
		$job = get_option( $option_key );

		if ( empty( $job ) ) {
			return;
		}

		$job['sent']              += $sent;
		$job['failed']            += $failed;
		$job['failed_recipients']  = array_merge( $job['failed_recipients'], $failed_recipients );

		// Mark complete when all batches have finished.
		if ( ( $job['sent'] + $job['failed'] ) >= $job['total'] ) {
			$job['status']       = 'completed';
			$job['completed_at'] = time();
		}

		update_option( $option_key, $job, false );
	}

	/**
	 * Dismiss a completed email notification job.
	 *
	 * Deletes the job option from wp_options. Only works for
	 * completed jobs.
	 *
	 * @since 3.11.0
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function ajax_dismiss_job() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$job_id = ! empty( $_REQUEST['job_id'] ) ? sanitize_key( $_REQUEST['job_id'] ) : '';

		if ( empty( $job_id ) ) {
			wp_send_json_error();
		}

		$option_key = self::JOB_OPTION_PREFIX . $job_id;
		$job        = get_option( $option_key );

		if ( empty( $job ) ) {
			wp_send_json_error();
		}

		if ( $job['status'] === 'processing' ) {
			$job['notice_dismissed'] = true;
			update_option( $option_key, $job );
		} else {
			delete_option( $option_key );
		}

		wp_send_json_success();
	}

	/**
	 * Display admin notices for email notification jobs.
	 *
	 * Shows progress for in-progress jobs and results for completed jobs.
	 * Only displays on Sugar Calendar admin pages. Cleans up jobs older
	 * than 7 days.
	 *
	 * @since 3.11.0
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function display_job_notices() {

		if ( ! $this->page_has_sidebar() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::JOB_OPTION_PREFIX ) . '%'
			)
		);

		if ( empty( $option_names ) ) {
			return;
		}

		$ajax_url = Plugin::instance()->get_admin()->ajax_url();

		foreach ( $option_names as $option_name ) {
			$job = get_option( $option_name );

			if ( empty( $job ) || ! is_array( $job ) ) {
				continue;
			}

			// Clean up jobs older than 7 days.
			if (
				$job['status'] === 'completed' &&
				! empty( $job['completed_at'] ) &&
				( time() - $job['completed_at'] ) > 7 * DAY_IN_SECONDS
			) {
				delete_option( $option_name );
				continue;
			}

			if ( $job['status'] === 'processing' && empty( $job['notice_dismissed'] ) ) {
				printf(
					'<div class="notice notice-info is-dismissible" data-sc-job-id="%1$s" data-sc-dismiss-url="%2$s"><p>%3$s</p></div>',
					esc_attr( $job['job_id'] ),
					esc_url( $ajax_url ),
					esc_html(
						sprintf(
							/* translators: %1$d: emails sent so far, %2$d: total emails. */
							__( 'Sending notifications: %1$d/%2$d sent...', 'sugar-calendar-lite' ),
							$job['sent'] + $job['failed'],
							$job['total']
						)
					)
				);
			} elseif ( $job['status'] === 'completed' ) {
				$this->render_completed_job_notice( $job, $ajax_url );
			}
		}

		static $script_output = false;

		if ( ! $script_output ) {
			$script_output = true;
			?>
			<script>
			document.addEventListener( 'click', function( e ) {
				var toggle = e.target.closest( '.sc-toggle-failed-details' );
				if ( toggle ) {
					e.preventDefault();
					var details = document.getElementById( 'sc-failed-details-' + toggle.dataset.jobId );
					if ( details ) {
						details.style.display = details.style.display === 'none' ? '' : 'none';
					}
					return;
				}

				var dismiss = e.target.closest( '.notice[data-sc-job-id] .notice-dismiss' );
				if ( dismiss ) {
					var notice = dismiss.closest( '.notice[data-sc-job-id]' );
					if ( ! notice ) return;
					var jobId = notice.dataset.scJobId;
					var url = notice.dataset.scDismissUrl;
					if ( ! jobId || ! url ) return;

					var formData = new FormData();
					formData.append( 'task', 'email_notifications_dismiss_job' );
					formData.append( 'job_id', jobId );
					fetch( url, { method: 'POST', body: formData, credentials: 'same-origin' } );
				}
			} );
			</script>
			<?php
		}
	}

	/**
	 * Render the admin notice for a completed email notification job.
	 *
	 * @since 3.11.0
	 *
	 * @access private
	 *
	 * @param array  $job      The job data array.
	 * @param string $ajax_url The AJAX URL for dismiss requests.
	 *
	 * @return void
	 */
	private function render_completed_job_notice( $job, $ajax_url ) {

		$job_id = $job['job_id'];

		if ( $job['failed'] > 0 ) {
			$notice_type = 'warning';
			$message     = sprintf(
				/* translators: %1$d: delivered count, %2$d: total count, %3$d: failed count. */
				__( 'Notifications sent: %1$d/%2$d delivered, %3$d failed.', 'sugar-calendar-lite' ),
				$job['sent'],
				$job['total'],
				$job['failed']
			);
		} else {
			$notice_type = 'success';
			$message     = sprintf(
				/* translators: %d: total recipients. */
				__( 'Notifications sent successfully to %d recipients.', 'sugar-calendar-lite' ),
				$job['total']
			);
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible" data-sc-job-id="%2$s" data-sc-dismiss-url="%3$s">',
			esc_attr( $notice_type ),
			esc_attr( $job_id ),
			esc_url( $ajax_url )
		);

		echo '<p>' . esc_html( $message );

		if ( $job['failed'] > 0 && ! empty( $job['failed_recipients'] ) ) {
			printf(
				' <a href="#" class="sc-toggle-failed-details" data-job-id="%s">%s</a>',
				esc_attr( $job_id ),
				esc_html__( 'View details', 'sugar-calendar-lite' )
			);
		}

		echo '</p>';

		// Render hidden failed recipients details.
		if ( $job['failed'] > 0 && ! empty( $job['failed_recipients'] ) ) {
			printf(
				'<div id="sc-failed-details-%s" style="display:none;"><ul>',
				esc_attr( $job_id )
			);

			foreach ( $job['failed_recipients'] as $recipient ) {
				printf(
					'<li>%s (%s) — %s</li>',
					esc_html( $recipient['name'] ?: __( 'No name', 'sugar-calendar-lite' ) ),
					esc_html( $recipient['email'] ),
					esc_html( $recipient['event_title'] ?: __( 'Custom recipient', 'sugar-calendar-lite' ) )
				);
			}

			echo '</ul></div>';
		}

		echo '</div>';
	}

	/**
	 * Store email preview data in a transient.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function ajax_store_preview_data() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$data = ! empty( $_REQUEST['data'] ) ? wp_unslash( $_REQUEST['data'] ) : [];

		$email_subject = ! empty( $data['email_subject'] ) ? sanitize_text_field( $data['email_subject'] ) : '';
		$email_body    = ! empty( $data['email_body'] ) ? wp_kses_post( $data['email_body'] ) : '';
		$event_ids     = ! empty( $data['events'] ) ? array_map( 'intval', $data['events'] ) : [];

		if ( empty( $email_subject ) && empty( $email_body ) ) {
			wp_send_json_error();
		}

		$preview_key = 'sc_email_preview_' . get_current_user_id() . '_' . wp_rand();

		set_transient(
			$preview_key,
			[
				'email_subject' => $email_subject,
				'email_body'    => $email_body,
				'event_ids'     => $event_ids,
			],
			5 * MINUTE_IN_SECONDS
		);

		$preview_url = add_query_arg(
			[
				'sc_email_preview' => '1',
				'preview_key'      => $preview_key,
				'_wpnonce'         => wp_create_nonce( 'sc_email_preview' ),
			],
			admin_url()
		);

		wp_send_json_success( [ 'preview_url' => $preview_url ] );
	}

	/**
	 * Render email preview as a standalone page.
	 *
	 * Intercepts requests with `sc_email_preview` query param,
	 * verifies security, retrieves stored preview data from transient,
	 * and outputs a standalone HTML page.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function render_preview() {

		if ( ! isset( $_GET['sc_email_preview'] ) ) {
			return;
		}

		if (
			! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'sc_email_preview' )
		) {
			wp_die( esc_html__( 'Invalid preview link.', 'sugar-calendar-lite' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to preview emails.', 'sugar-calendar-lite' ) );
		}

		$preview_key = isset( $_GET['preview_key'] ) ? sanitize_key( $_GET['preview_key'] ) : '';
		$preview     = get_transient( $preview_key );

		if ( empty( $preview ) ) {
			wp_die( esc_html__( 'Preview has expired. Please try again.', 'sugar-calendar-lite' ) );
		}

		// Delete the transient after use.
		delete_transient( $preview_key );

		$email_subject = $preview['email_subject'] ?? '';
		$email_body    = $preview['email_body'] ?? '';
		$event_ids     = $preview['event_ids'] ?? [];

		// Build sample attendee data for preview.
		$sample_attendee = [
			'name'  => 'John Doe',
			'email' => 'john@example.com',
		];

		$preview_event_title = '';

		if ( ! empty( $event_ids ) ) {
			$first_event = sugar_calendar_get_event( $event_ids[0] );

			if ( ! empty( $first_event ) ) {
				$sample_attendee     = array_merge( $sample_attendee, $this->get_event_tag_data( $first_event ) );
				$preview_event_title = $first_event->title;
			}
		}

		$email_subject = $this->replace_smart_tags( $email_subject, $sample_attendee );
		$email_body    = $this->replace_smart_tags( wp_kses_post( $email_body ), $sample_attendee );

		$output = $this->build_email( $email_subject, $email_body );

		// Inject preview banner inside the HTML body if we have event context.
		if ( ! empty( $preview_event_title ) ) {
			$banner = sprintf(
				'<div style="background:#f0f6fc;border:1px solid #c3d9ed;padding:10px 15px;margin:10px auto;max-width:520px;font-family:sans-serif;font-size:13px;color:#1d2327;text-align:center;">%s</div>',
				esc_html(
					sprintf(
						/* translators: %s: Event title */
						__( 'Preview shown with sample data from: %s', 'sugar-calendar-lite' ),
						$preview_event_title
					)
				)
			);

			$output = preg_replace( '/(<body[^>]*>)/i', '$1' . $banner, $output, 1 );
		}

		echo $output;

		exit;
	}

	/**
	 * Wrap email content with the ticketing email template.
	 *
	 * Uses the same HTML template as ticket purchase emails,
	 * replacing {heading} with the subject and {email} with the body.
	 *
	 * @since 3.11.0
	 *
	 * @param string $subject Email subject (used as heading).
	 * @param string $content Email body content.
	 *
	 * @return string Fully wrapped email HTML.
	 */
	private function build_email( $subject, $content ) {

		ob_start();
		include SC_CORE_ET_PLUGIN_DIR . 'includes/templates/email.php';
		$template = ob_get_clean();

		$message = str_replace( '{email}', $content, $template );
		$message = str_replace( '{heading}', $subject, $message );

		return $message;
	}

	/**
	 * Register metaboxes.
	 *
	 * @since 3.11.0
	 *
	 * @param string  $post_type Current post type.
	 * @param WP_POST $post      Current post.
	 *
	 * @return void
	 */
	public function add_meta_boxes( $post_type, $post ) {

		if ( ! in_array( $post_type, $this->get_event_post_types(), true ) ) {
			return;
		}

		// For virtual occurrence edit pages, $post->ID is a virtual ID (8000000 + occurrence_id).
		// Look up the parent event via the occurrence instead.
		if (
			! empty( $post->sc_occurrence_id ) &&
			class_exists( 'Sugar_Calendar\Pro\Features\AdvancedRecurring\Occurrence' )
		) {
			$occurrence = \Sugar_Calendar\Pro\Features\AdvancedRecurring\Occurrence::get_by_id( $post->sc_occurrence_id );
			$event      = sugar_calendar_get_event( $occurrence->get_parent_sc_event_id() );
		} else {
			$event = sugar_calendar_get_event_by_object(
				$post->ID,
				'post',
				[ 'object_subtype' => $post_type ]
			);
		}

		if ( $this->event_has_ticketing( $event->id ) ) {
			add_meta_box(
				'sugar_calendar_editor_event_recent_tickets',
				esc_html__( 'Recent Tickets', 'sugar-calendar-lite' ),
				[ $this, 'display_recent_tickets' ],
				get_post_types_by_support( [ 'events' ] ),
				'normal',
				'high',
				[
					'event' => $event,
				],
			);

			add_meta_box(
				'sugar_calendar_editor_event_tickets_overview',
				esc_html__( 'Tickets Overview', 'sugar-calendar-lite' ),
				[ $this, 'display_tickets_overview' ],
				get_post_types_by_support( [ 'events' ] ),
				'side',
				'default',
				[
					'event' => $event,
				],
			);
		}
	}

	/**
	 * Display the recent tickets metabox.
	 *
	 * @param WP_POST $post   Current post.
	 * @param mixed  $metabox
	 * @return void
	 */
	public function display_recent_tickets( $post, $metabox ) {

		$event      = $metabox['args']['event'];
		$list_table = new EventRecentTicketsTable();

		// Set the post ID so row actions can build return URLs.
		$list_table->post_id = $post->ID;

		// Disable certain features for the recent tickets list table.
		$list_table->should_show_trashed_tickets = false;
		$list_table->should_show_cb              = false;
		$list_table->should_enable_sorting       = false;

		$list_table->prepare_items(
			[
				'event_id' => $event->id,
				'per_page' => 5,
			]
		);
		$list_table->display();
		?>
		<div class="sugar-calendar-metabox-footer">
			<a href="<?php echo esc_url( add_query_arg( 'event_id', $event->id, TicketsTab::get_url() ) ); ?>"
				class="button"><?php esc_html_e( 'View All', 'sugar-calendar-lite' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Display a ticket action notice on the edit event page.
	 *
	 * Reads a transient stored by TicketsTab::process_bulk_action()
	 * when a return_url was provided, and displays the appropriate notice.
	 *
	 * @since 3.11.0
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function display_ticket_action_notice() {

		$screen = get_current_screen();

		if ( empty( $screen ) || $screen->id !== 'sc_event' ) {
			return;
		}

		$transient_key = 'sc_ticket_action_notice_' . get_current_user_id();
		$notice_data   = get_transient( $transient_key );

		if ( empty( $notice_data ) ) {
			return;
		}

		delete_transient( $transient_key );

		$action         = $notice_data['action'];
		$affected_count = ! empty( $notice_data['affected_count'] ) ? absint( $notice_data['affected_count'] ) : 0;
		$failed_count   = ! empty( $notice_data['failed_count'] ) ? absint( $notice_data['failed_count'] ) : 0;

		switch ( $action ) {
			case 'email':
				if ( $failed_count > 0 ) {
					$message = sprintf(
						/* translators: %1$d: Number of successful emails, %2$d: Number of failed emails. */
						_n(
							'%1$d ticket email sent successfully, %2$d failed.',
							'%1$d ticket emails sent successfully, %2$d failed.',
							$affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $affected_count ),
						number_format_i18n( $failed_count )
					);
					$notice_type = 'warning';
				} else {
					$message = sprintf(
						/* translators: %d: Number of ticket emails sent. */
						_n(
							'%d ticket email sent successfully.',
							'%d ticket emails sent successfully.',
							$affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $affected_count )
					);
					$notice_type = 'success';
				}
				break;

			default:
				return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notice_type ),
			esc_html( $message )
		);
	}

	public function display_tickets_overview( $post, $metabox ) {

		$event           = $metabox['args']['event'];
		$ticket_data     = get_ticket_data( $event );
		$limit_capacity  = $ticket_data['ticket_limit_capacity'];
		$ticket_quantity = $ticket_data['ticket_quantity'];
		$ticket_count    = count_tickets( [ 'event_id' => $event->id ] );
		$order_query     = new Order_Query( [
			'event_id' => $event->id,
			'status'   => 'paid',
			'number'   => 0,
		] );
		$total_revenue   = array_reduce(
			$order_query->items,
			fn( $carry, $order ) => $carry + $order->total,
			0.00
		);

		?>
		<div class="sugar-calendar-ticket-overview__content">
			<div class="sugar-calendar-ticket-overview sugar-calendar-ticket-overview--count">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php
				echo wp_kses(
					sprintf(
						'%1$s: <span>%2$s</span>',
						esc_html__( 'Tickets Sold', 'sugar-calendar-lite' ),
						$limit_capacity ?
							sprintf( '%1$s/%2$s', $ticket_count, $ticket_quantity ) :
							$ticket_count
					),
					[ 'span' => [] ]
				);
				?>
			</div>
			<div class="sugar-calendar-ticket-overview sugar-calendar-ticket-overview--revenue">
				<span class="dashicons dashicons-money-alt"></span>
				<?php
				echo wp_kses(
					sprintf(
						'%1$s: <span>%2$s</span>',
						esc_html__( 'Total Revenue', 'sugar-calendar-lite' ),
						currency_filter( $total_revenue )
					),
					[ 'span' => [] ]
				);
				?>
			</div>
		</div>
		<div class="sugar-calendar-metabox-footer" data-event-id="<?php echo esc_attr( $event->id ); ?>" data-type="ticketing">
			<a href="#"
				data-sc-notify-event-attendees
				class="button"><?php esc_html_e( 'Notify Attendees', 'sugar-calendar-lite' ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( 'event_id', $event->id, TicketsTab::get_url() ) ); ?>"
				class="button"><?php esc_html_e( 'View Tickets', 'sugar-calendar-lite' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Enqueue admin-side scripts and styles.
	 *
	 * @since 3.11.0
	 *
	 * @param PageInterface $page Current page.
	 */
	public function enqueue_assets( $page ) {

		if ( sugar_calendar()->get_admin()->is_page( 'event_edit' ) ) {
			wp_enqueue_style(
				'sugar-calendar-admin-email-notifications',
				SC_PLUGIN_ASSETS_URL . 'css/admin-email-notifications' . WP::asset_min() . '.css',
				[ 'dashicons' ],
				Helpers::get_asset_version()
			);
		}

		if ( $this->page_has_sidebar() ) {
			$asset_file = include( SC_PLUGIN_DIR . 'assets/jsx/build/admin/email-notifications.asset.php' );

			// The 'react-jsx-runtime' script handle was added in WP 6.6.
			// On WP 6.2–6.5, React 18 is available but the handle isn't registered.
			// Register a shim that provides the ReactJSXRuntime global via React.createElement.
			if ( ! wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
				wp_register_script( 'react-jsx-runtime', false, [ 'react' ] );
				wp_add_inline_script(
					'react-jsx-runtime',
					'(function(R){' .
						'function jsx(t,c,k){' .
							'var p={},ch,i;' .
							'for(i in c){i==="children"?ch=c[i]:p[i]=c[i]}' .
							'if(k!==void 0)p.key=k;' .
							'var a=[t,p];' .
							'if(Array.isArray(ch))for(i=0;i<ch.length;i++)a.push(ch[i]);' .
							'else if(ch!==void 0)a.push(ch);' .
							'return R.createElement.apply(null,a)' .
						'}' .
						'window.ReactJSXRuntime={jsx:jsx,jsxs:jsx,Fragment:R.Fragment}' .
					'})(window.React);'
				);
			}

			// Required for WYSIWYG editor.
			wp_enqueue_editor();
			wp_enqueue_media();

			wp_enqueue_script(
				'sugar-calendar-admin-email-notifications-sidebar',
				SC_PLUGIN_ASSETS_URL . 'jsx/build/admin/email-notifications.js',
				$asset_file['dependencies'],
				$asset_file['version']
			);

			wp_localize_script(
				'sugar-calendar-admin-email-notifications-sidebar',
				'sugar_calendar_admin_email_notifications',
				[
					'ajax_url'            => Plugin::instance()->get_admin()->ajax_url(),
					'smart_tags'          => $this->get_smart_tags(),
					'is_event_edit_page'  => sugar_calendar()->get_admin()->is_page( 'event_edit' ),
				]
			);

			wp_enqueue_style(
				'sugar-calendar-admin-email-notifications-sidebar',
				SC_PLUGIN_ASSETS_URL . 'jsx/build/admin/email-notifications.css',
				[],
				$asset_file['version']
			);
		}
	}
}
