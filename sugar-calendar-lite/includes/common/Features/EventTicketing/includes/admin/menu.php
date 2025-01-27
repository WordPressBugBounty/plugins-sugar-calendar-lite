<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Menu;

/**
 * Menu Functions.
 *
 * @since 1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Output the admin tickets page
 *
 * @since 1.0.0
 */
function tickets_page() {

	// Viewing an order ID
	$order_id = ! empty( $_GET['order_id'] )
		? absint( $_GET['order_id'] )
		: 0;

	// Do the view instead
	if ( ! empty( $order_id ) ) {
		\Sugar_Calendar\AddOn\Ticketing\Admin\View( $order_id );

		return;
	}

	// Which list table?
	$wp_list_table = ! empty( $_GET['tab'] ) && ( 'orders' === $_GET['tab'] )
		? new \Sugar_Calendar\AddOn\Ticketing\Admin\Orders\List_Table()
		: new \Sugar_Calendar\AddOn\Ticketing\Admin\Tickets\List_Table();

	// Query for orders/tickets
	$wp_list_table->prepare_items();

	// Set the help tabs
	$wp_list_table->set_help_tabs(); ?>

    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Event Ticket Orders', 'sugar-calendar-lite' ); ?></h1>

		<?php \Sugar_Calendar\AddOn\Ticketing\Admin\Nav\display(); ?>

        <hr class="wp-header-end">

        <div id="sc-event-ticketing-admin-calendar-wrapper">

			<?php $wp_list_table->views(); ?>

            <form id="posts-filter" method="get">

				<?php $wp_list_table->search_box( 'Search', 'sc_event_tickets_search' ); ?>

                <input type="hidden" name="page" value="sc-event-ticketing"/>

				<?php $wp_list_table->display(); ?>

            </form>
        </div>
    </div>
	<?php
}
