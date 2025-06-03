<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;
use function Sugar_Calendar\AddOn\Ticketing\Common\Assets\get_url;

/**
 * Tickets page.
 *
 * @since 1.2.0
 */
class TicketsTab extends Tickets {

	/**
	 * Page tab slug.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'tickets';
	}

	/**
	 * Page title.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function get_title() {

		return esc_html__( 'Tickets', 'sugar-calendar-lite' );
	}

	/**
	 * Register page hooks.
	 *
	 * @since 1.2.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Display a tab's content.
	 *
	 * @since 1.2.0
	 */
	protected function display_tab() {

		$wp_list_table = new \Sugar_Calendar\AddOn\Ticketing\Admin\Tickets\List_Table();

		// Query for orders/tickets
		$wp_list_table->prepare_items();
		$wp_list_table->views();
		?>

        <form id="posts-filter" method="get">

			<?php $wp_list_table->search_box( 'Search', 'sc_event_tickets_search' ); ?>

            <input type="hidden" name="page" value="sc-event-ticketing"/>

			<?php $wp_list_table->display(); ?>

        </form>

		<?php
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-ticketing-admin-tickets',
			get_url( 'css' ) . '/admin-tickets' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		// Enqueue admin ticketing script.
		wp_enqueue_script(
			'sugar-calendar-ticketing-admin',
			SC_PLUGIN_ASSETS_URL . 'admin/js/sc-admin-ticketing' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-choices' ],
			BaseHelpers::get_asset_version(),
			true
		);

		// Localize script.
		wp_localize_script(
			'sugar-calendar-ticketing-admin',
			'sc_admin_ticketing',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sc-admin-ticketing-list' ),
				'strings' => [
					'select_event' => esc_html__( 'Event', 'sugar-calendar-lite' ),
				],
			]
		);
	}
}
