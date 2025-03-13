<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\AddOn\Ticketing\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Admin\PageAbstract;
/**
 * Tickets page.
 *
 * @since 1.2.0
 * @since 3.6.0 Extend this class from PageAbstract.
 */
class Tickets extends PageAbstract {

	/**
	 * Page label.
	 *
	 * @since 3.6.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Tickets', 'sugar-calendar-lite' );
	}

	/**
	 * Page slug.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sc-event-ticketing';
	}

	/**
	 * Page tab slug.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		if ( ! isset( $_GET['tab'] ) ) {
			return null;
		}

		return sanitize_key( $_GET['tab'] );
	}

	/**
	 * Page URL.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function get_url() {

		return add_query_arg(
			[
				'page' => static::get_slug(),
				'tab'  => static::get_tab_slug(),
			],
			WP::admin_url( 'admin.php' )
		);
	}

	/**
	 * Whether the page appears in menus.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public static function has_menu_item() {

		return true;
	}

	/**
	 * Which menu item to highlight
	 * if the page doesn't appear in dashboard menu.
	 *
	 * @since 1.2.0
	 *
	 * @return null|string;
	 */
	public static function highlight_menu_item() {

		return null;
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

		add_action( 'admin_init', [ $this, 'init' ] );
	}

	/**
	 * Initialize page.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function init() {

		$section_id = static::get_tab_slug();
		$sections   = array_keys( $this->get_tabs() );

		if ( ! in_array( $section_id, $sections ) ) {
			wp_safe_redirect( sugar_calendar()->get_admin()->get_page_url( 'tickets_tickets' ) );
			exit;
		}
	}

	private function get_tabs() {

		// Initial tab array
		$tabs = [
			'tickets' => [
				'name' => esc_html__( 'Tickets', 'sugar-calendar-lite' ),
				'url'  => admin_url( 'admin.php?page=sc-event-ticketing' ),
			],
			'orders'  => [
				'name' => esc_html__( 'Orders', 'sugar-calendar-lite' ),
				'url'  => admin_url( 'admin.php?page=sc-event-ticketing&tab=orders' ),
			],
		];

		// Filter the tabs
		$tabs = apply_filters( 'sc_event_tickets_admin_nav', $tabs );

		return $tabs;
	}

	/**
	 * Display page.
	 *
	 * @since 1.2.0
	 */
	public function display() {

		?>

        <div id="sugar-calendar-tickets" class="wrap sugar-calendar-admin-wrap">

			<?php UI::tabs( $this->get_tabs(), static::get_tab_slug() ); ?>

            <div class="sugar-calendar-admin-content">

                <h1 class="screen-reader-text"><?php echo esc_html( static::get_title() ); ?></h1>

				<?php $this->display_tab(); ?>
            </div>
        </div>
		<?php
	}

	/**
	 * Display a tab's content.
	 *
	 * @since 1.2.0
	 */
	protected function display_tab() {}
}
