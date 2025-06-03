<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin;

use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\OrderEdit;
use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\OrdersTab;
use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\Tickets;
use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\TicketsTab;
use Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query;


/**
 * Admin area.
 *
 * @since 1.2.0
 */
class Area {

	public function hooks() {

		add_action( 'admin_menu', [ $this, 'admin_menu' ], 30 );
		add_filter( 'sugar_calendar_admin_area_current_page_id', [ $this, 'admin_area_current_page_id' ] );
		add_filter( 'sugar_calendar_admin_area_pages', [ $this, 'admin_area_pages' ] );
		add_action( 'wp_ajax_fetch_ticketing_events_choices', [ $this, 'ajax_fetch_events_choices' ] );

	}

	/**
	 * Add admin area menu items.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function admin_menu() {

		// Get the main post type object
		$post_type = sugar_calendar_get_event_post_type_id();
		$pt_object = get_post_type_object( $post_type );

		add_submenu_page(
			'sugar-calendar',
			esc_html__( 'Tickets', 'sugar-calendar-lite' ),
			esc_html__( 'Tickets', 'sugar-calendar-lite' ),
			$pt_object->cap->create_posts,
			'sc-event-ticketing',
			[ sugar_calendar()->get_admin(), 'display' ],
			5
		);
	}

	/**
	 * Register page ids.
	 *
	 * @since 1.2.0
	 *
	 * @param string|null $page_id Current page id.
	 */
	public function admin_area_current_page_id( $page_id ) {

		if ( isset( $_GET['page'] ) && $_GET['page'] === 'sc-event-ticketing' ) {
			$page_id = 'tickets';
		}

		if ( $page_id === 'tickets' && isset( $_GET['order_id'] ) ) {

			// Order edit screen.
			$page_id = 'tickets_order_edit';
		} elseif ( $page_id === 'tickets' ) {

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$section = $_GET['tab'] ?? 'tickets';

			switch ( $section ) {
				case 'tickets':
					$page_id = 'tickets_tickets';
					break;

				case 'orders':
					$page_id = 'tickets_orders';
					break;
			}
		}

		return $page_id;
	}

	/**
	 * Register page classes.
	 *
	 * @since 1.2.0
	 *
	 * @return PageInterface[]
	 */
	public function admin_area_pages( $pages ) {

		$pages['tickets']            = Tickets::class;
		$pages['tickets_tickets']    = TicketsTab::class;
		$pages['tickets_orders']     = OrdersTab::class;
		$pages['tickets_order_edit'] = OrderEdit::class;

		return $pages;
	}

	/**
	 * AJAX handler for fetching events for the dropdown.
	 *
	 * @since 3.7.0
	 */
	public function ajax_fetch_events_choices() {

		check_ajax_referer( 'sc-admin-ticketing-list', 'nonce' );

		$search_term = '';

		if ( ! empty( $_POST['searchTerm'] ) ) {
			$search_term = sanitize_text_field( wp_unslash( $_POST['searchTerm'] ) );
		}

		$choices = [];

		// Get search title for events with tickets.
		global $wpdb;

		$like_search_term = '%' . $wpdb->esc_like( $search_term ) . '%';

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT e.id, e.title
				FROM {$wpdb->prefix}sc_events e
				INNER JOIN {$wpdb->prefix}sc_tickets t ON e.id = t.event_id
				WHERE e.status = 'publish' AND e.title LIKE %s
				ORDER BY e.title ASC",
				$like_search_term
			)
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$choices[] = [
					'value' => $result->id,
					'label' => $result->title,
				];
			}
		}

		wp_send_json_success( $choices );
	}
}
