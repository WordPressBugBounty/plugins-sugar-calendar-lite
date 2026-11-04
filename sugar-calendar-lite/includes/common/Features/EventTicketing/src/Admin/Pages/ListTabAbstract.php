<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\AddOn\Ticketing\Admin\ListTableAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;
use function Sugar_Calendar\AddOn\Ticketing\Common\Assets\get_url;

/**
 * Shared part of the list tabs (Tickets, Orders, Coupons) of the Tickets screen.
 *
 * @since 3.17.0
 */
abstract class ListTabAbstract extends Tickets {

	/**
	 * Screen options ID, overridden by each tab.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const SCREEN_OPTIONS_ID = '';

	/**
	 * Table name of the column chooser, overridden by each tab.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const TABLE_NAME = '';

	/**
	 * Columns the column chooser never hides, overridden by each tab.
	 *
	 * @since 3.17.0
	 *
	 * @var string[]
	 */
	const REQUIRED_COLUMNS = [];

	/**
	 * Docs URL of the help link, overridden by each tab.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const HELP_URL = '';

	/**
	 * Medium of the help link, overridden by each tab.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const HELP_MEDIUM = '';

	/**
	 * The list table.
	 *
	 * @since 3.17.0
	 *
	 * @var ListTableAbstract
	 */
	protected $wp_list_table;

	/**
	 * The performed action.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	protected $performed_action = '';

	/**
	 * The affected count.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	protected $affected_count = 0;

	/**
	 * The result of the performed action.
	 *
	 * @since 3.17.0
	 *
	 * @var mixed
	 */
	protected $performed_action_result;

	/**
	 * Create the list table of this tab.
	 *
	 * @since 3.17.0
	 *
	 * @return ListTableAbstract
	 */
	abstract protected function create_list_table();

	/**
	 * Display column options.
	 *
	 * @since 3.17.0
	 */
	public function display_column_options() {

		UI::table_screen_options(
			[
				'table_name'             => static::TABLE_NAME,
				'table_columns'          => $this->wp_list_table->get_columns(),
				'table_required_columns' => static::REQUIRED_COLUMNS,
			]
		);
	}

	/**
	 * Filter the help URL of the tab.
	 *
	 * @since 3.17.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return BaseHelpers\Helpers::get_utm_url(
			static::HELP_URL,
			[
				'content' => 'Help',
				'medium'  => static::HELP_MEDIUM,
			]
		);
	}

	/**
	 * Early init.
	 *
	 * @since 3.17.0
	 */
	public function early_init() {

		$this->setup_admin_screen_options();
	}

	/**
	 * Setup the admin screen options.
	 *
	 * @since 3.17.0
	 */
	private function setup_admin_screen_options() {

		$admin_screen_options = sugar_calendar()->get_admin_screen_options();

		if ( ! $admin_screen_options ) {
			return;
		}

		$admin_screen_options->set_screen_options_id( static::SCREEN_OPTIONS_ID );
		$admin_screen_options->add_pagination_option( esc_html__( 'Number of items per page:', 'sugar-calendar-lite' ) );

		$admin_screen_options->hooks();
	}

	/**
	 * Build and prepare the list table.
	 *
	 * @since 3.17.0
	 */
	protected function before_display_tab() {

		$this->wp_list_table = $this->create_list_table();

		$this->wp_list_table->user_saved_pref = get_user_option( static::SCREEN_OPTIONS_ID . '_screen_options' );

		$this->wp_list_table->prepare_items();
	}

	/**
	 * Display a tab's content.
	 *
	 * @since 3.17.0
	 */
	protected function display_tab() {

		$this->wp_list_table->display_search_reset();

		$this->wp_list_table->views();
		?>

        <form id="posts-filter" method="get">

			<?php $this->wp_list_table->search_box( 'Search', 'sc_event_tickets_search' ); ?>

            <input type="hidden" name="page" value="sc-event-ticketing"/>

			<?php $this->wp_list_table->display(); ?>

        </form>

		<?php
	}

	/**
	 * Print a dismissible admin notice.
	 *
	 * @since 3.17.0
	 *
	 * @param string $type    Notice type: success, warning or error.
	 * @param string $message Notice text. Nothing prints when empty.
	 */
	protected function print_notice( $type, $message ) {

		if ( empty( $message ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Enqueue the tab stylesheet and the list scripts.
	 *
	 * @since 3.17.0
	 *
	 * @param string $slug          Tab slug of the `admin-<slug>` stylesheet.
	 * @param string $events_action The AJAX action that loads the events choices.
	 */
	protected function enqueue_list_assets( $slug, $events_action ) {

		wp_enqueue_style(
			'sugar-calendar-ticketing-admin-' . $slug,
			get_url( 'css' ) . '/admin-' . $slug . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		$this->enqueue_list_scripts( $events_action );
	}

	/**
	 * Enqueue the list scripts: the events filter and the column control.
	 *
	 * @since 3.17.0
	 *
	 * @param string $events_action The AJAX action that loads the events choices.
	 */
	protected function enqueue_list_scripts( $events_action ) {

		wp_enqueue_script(
			'sugar-calendar-ticketing-admin',
			SC_PLUGIN_ASSETS_URL . 'admin/js/sc-admin-ticketing' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-choices' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_enqueue_script( 'sugar-calendar-admin-column-control' );

		wp_localize_script(
			'sugar-calendar-ticketing-admin',
			'sc_admin_ticketing',
			$this->get_script_data( $events_action )
		);
	}

	/**
	 * Data localized to the list script.
	 *
	 * @since 3.17.0
	 *
	 * @param string $events_action The AJAX action that loads the events choices.
	 *
	 * @return array
	 */
	protected function get_script_data( $events_action ) {

		return [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'sc-admin-ticketing-list' ),
			'action'  => $events_action,
			'strings' => [
				'select_event'    => esc_html__( 'Event', 'sugar-calendar-lite' ),
				'no_results_text' => esc_html__( 'No results found', 'sugar-calendar-lite' ),
				/* translators: %d: number of selected events hidden behind the "..." tag of the Events filter. */
				'more_selected'   => __( '+%d more', 'sugar-calendar-lite' ),
			],
		];
	}
}
