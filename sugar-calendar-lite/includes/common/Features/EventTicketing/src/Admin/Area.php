<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin;

use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\OrderEdit;
use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\OrdersTab;
use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\Tickets;
use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\TicketsTab;

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

}
