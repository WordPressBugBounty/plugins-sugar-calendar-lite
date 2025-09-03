<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\AddOn\Ticketing\Admin\Orders\List_Table;
use Sugar_Calendar\Helpers\UI;
use function Sugar_Calendar\AddOn\Ticketing\Common\Assets\get_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\trash_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\delete_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\restore_order;

/**
 * Orders page.
 *
 * @since 1.2.0
 */
class OrdersTab extends Tickets {

	/**
	 * Screen options ID.
	 *
	 * @since 3.8.0
	 *
	 * @var string
	 */
	const SCREEN_OPTIONS_ID = 'sugar_calendar_orders_admin_list';

	/**
	 * The performed action.
	 *
	 * @since 3.8.0
	 *
	 * @var string
	 */
	private $performed_action = false;

	/**
	 * The affected count.
	 *
	 * @since 3.8.0
	 *
	 * @var int
	 */
	private $affected_count = 0;

	/**
	 * The result of the performed action.
	 *
	 * @since 3.8.0
	 *
	 * @var mixed
	 */
	private $performed_action_result;

	/**
	 * The list table.
	 *
	 * @since 3.8.2
	 *
	 * @var List_Table
	 */
	private $wp_list_table;

	/**
	 * Page tab slug.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'orders';
	}

	/**
	 * Page title.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function get_title() {

		return esc_html__( 'Orders', 'sugar-calendar-lite' );
	}

	/**
	 * Early init.
	 *
	 * @since 3.8.0
	 */
	public function early_init() {

		$this->setup_admin_screen_options();
	}

	/**
	 * Setup the admin screen options.
	 *
	 * @since 3.8.0
	 */
	private function setup_admin_screen_options() {

		$admin_screen_options = sugar_calendar()->get_admin_screen_options();

		if ( ! $admin_screen_options ) {
			return;
		}

		$admin_screen_options->set_screen_options_id( self::SCREEN_OPTIONS_ID );
		$admin_screen_options->add_option(
			'pagination',
			[
				'label'      => esc_html__( 'Number of items per page:', 'sugar-calendar-lite' ),
				'option'     => 'per_page',
				'default'    => 20,
				'input_type' => 'number',
				'value_type' => 'int',
				'min'        => 1,
				'max'        => 999,
			]
		);

		$admin_screen_options->hooks();
	}

	/**
	 * Register page hooks.
	 *
	 * @since 1.2.0
	 * @since 3.8.0 Add bulk actions.
	 */
	public function hooks() {

		// Call parent hooks.
		parent::hooks();

		add_action( 'admin_init', [ $this, 'process_bulk_action' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
		add_filter( 'sugar_calendar_helpers_ui_help_url', [ $this, 'help_url' ] );
	}

	/**
	 * Process bulk actions for orders.
	 *
	 * @since 3.8.0
	 */
	public function process_bulk_action() {

		$allowed_actions = [
			'trash',
			'restore',
			'delete',
		];

		// Check if we have action results from a redirect (notice display mode).
		if (
			! empty( $_GET['page'] ) &&
			$_GET['page'] === 'sc-event-ticketing' &&
			! empty( $_GET['bulk_action_performed'] ) &&
			! empty( $_GET['affected_count'] ) &&
			isset( $_GET['failed_count'] ) &&
			in_array( $_GET['bulk_action_performed'], $allowed_actions, true )
		) {
			// Display notice mode - no nonce needed since we're just displaying results.
			$this->performed_action        = sanitize_key( $_GET['bulk_action_performed'] );
			$this->affected_count          = absint( $_GET['affected_count'] );
			$this->performed_action_result = absint( $_GET['failed_count'] );

			// Hook the notice display function.
			add_action( 'admin_notices', [ $this, 'display_performed_action_notice' ] );

			return;
		}

		// Processing mode - only process on our page, nonce supplied, with allowed action, and order IDs.
		if (
			empty( $_REQUEST['page'] )
			||
			$_REQUEST['page'] !== 'sc-event-ticketing'
			||
			empty( $_REQUEST['_wpnonce'] )
			||
			empty( $_REQUEST['action'] )
			||
			! in_array( $_REQUEST['action'], $allowed_actions, true )
			||
			empty( $_REQUEST['order'] )
		) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );

		// Verify nonce.
		if ( ! wp_verify_nonce( $nonce, 'bulk-event-tickets' ) ) {
			return;
		}

		$action = sanitize_key( $_REQUEST['action'] );

		// Get order IDs.
		$order_ids = isset( $_REQUEST['order'] )
			? wp_parse_id_list( wp_unslash( $_REQUEST['order'] ) )
			: [];

		// Final check.
		if (
			empty( $action )
			||
			empty( $order_ids )
		) {
			return;
		}

		$affected_order_counter = 0;
		$failed_order_counter   = 0;

		// Process based on action.
		switch ( $action ) {
			case 'trash':
				foreach ( $order_ids as $order_id ) {
					$result = trash_order( $order_id );

					if ( $result ) {
						++$affected_order_counter;
					} else {
						++$failed_order_counter;
					}
				}
				break;

			case 'restore':
				foreach ( $order_ids as $order_id ) {
					$result = restore_order( $order_id );

					if ( $result ) {
						++$affected_order_counter;
					} else {
						++$failed_order_counter;
					}
				}
				break;

			case 'delete':
				foreach ( $order_ids as $order_id ) {
					$result = delete_order( $order_id );

					if ( $result ) {
						++$affected_order_counter;
					} else {
						++$failed_order_counter;
					}
				}
				break;
		}

		// Redirect with action results to display notice.
		wp_safe_redirect(
			add_query_arg(
				[
					'page'                  => 'sc-event-ticketing',
					'tab'                   => 'orders',
					'bulk_action_performed' => $action,
					'affected_count'        => $affected_order_counter,
					'failed_count'          => $failed_order_counter,
				],
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 *
	 * Display a notice when an action is performed.
	 *
	 * @since 3.8.0
	 */
	public function display_performed_action_notice() {

		if ( empty( $this->performed_action ) ) {
			return;
		}

		$failed_count = $this->performed_action_result;
		$notice_type  = 'info';

		switch ( $this->performed_action ) {
			case 'trash':
				if ( $failed_count > 0 ) {
					$message = sprintf(
						/* translators: %1$d: Number of successful, %2$d: Number of failed. */
						_n(
							'%1$d order moved to Trash successfully, %2$d failed.',
							'%1$d orders moved to Trash successfully, %2$d failed.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count ),
						number_format_i18n( $failed_count )
					);
					$notice_type = 'warning';
				} else {
					$message = sprintf(
						/* translators: %d: Number of orders moved to trash. */
						_n(
							'%d order was successfully moved to Trash.',
							'%d orders were successfully moved to Trash.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count )
					);
					$notice_type = 'success';
				}
				break;

			case 'restore':
				if ( $failed_count > 0 ) {
					$message = sprintf(
						/* translators: %1$d: Number of successful, %2$d: Number of failed. */
						_n(
							'%1$d order restored successfully, %2$d failed.',
							'%1$d orders restored successfully, %2$d failed.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count ),
						number_format_i18n( $failed_count )
					);
					$notice_type = 'warning';
				} else {
					$message = sprintf(
						/* translators: %d: Number of orders restored. */
						_n(
							'%d order was successfully restored.',
							'%d orders were successfully restored.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count )
					);
					$notice_type = 'success';
				}
				break;

			case 'delete':
				if ( $failed_count > 0 ) {
					$message = sprintf(
						/* translators: %1$d: Number of successful, %2$d: Number of failed. */
						_n(
							'%1$d order permanently deleted successfully, %2$d failed.',
							'%1$d orders permanently deleted successfully, %2$d failed.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count ),
						number_format_i18n( $failed_count )
					);
					$notice_type = 'warning';
				} else {
					$message = sprintf(
						/* translators: %d: Number of orders permanently deleted. */
						_n(
							'%d order was successfully permanently deleted.',
							'%d orders were successfully permanently deleted.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count )
					);
					$notice_type = 'success';
				}
				break;

			default:
				$message = '';
				break;
		}

		if ( ! empty( $message ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice_type ),
				esc_html( $message )
			);
		}
	}

	/**
	 * Filter the help URL in the Tickets page -> Orders tab.
	 *
	 * @since 3.8.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return BaseHelpers\Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/event-ticketing-addon/#orders-overview',
			[
				'content' => 'Help',
				'medium'  => 'orders-list',
			]
		);
	}

	/**
	 * Display before tab.
	 *
	 * @since 3.8.2
	 */
	protected function before_display_tab() {

		$this->wp_list_table = new List_Table();

		$this->wp_list_table->user_saved_pref = get_user_option( self::SCREEN_OPTIONS_ID . '_screen_options' );

		$this->wp_list_table->prepare_items();
	}

	/**
	 * Display column options.
	 *
	 * @since 3.8.2
	 */
	public function display_column_options() {

		// Output the cogwheel (table column chooser) UI.
		UI::table_screen_options(
			[
				'table_name'             => 'sugar_calendar_table_orders',
				'table_columns'          => $this->wp_list_table->get_columns(),
				'table_required_columns' => [ 'total', 'status', 'date' ],
			]
		);
	}

	/**
	 * Display a tab's content.
	 *
	 * @since 1.2.0
	 */
	protected function display_tab() {

		$this->wp_list_table->display_search_reset();

		// Query for orders/tickets.
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
	 * Enqueue assets.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-ticketing-admin-orders',
			get_url( 'css' ) . '/admin-orders' . WP::asset_min() . '.css',
			[ 'sugar-calendar-admin-fontawesome' ],
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

		// Enqueue column control (cogwheel) behavior.
		wp_enqueue_script( 'sugar-calendar-admin-column-control' );

		// Localize script.
		wp_localize_script(
			'sugar-calendar-ticketing-admin',
			'sc_admin_ticketing',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sc-admin-ticketing-list' ),
				'action'  => 'fetch_ticketing_orders_events_choices',
				'strings' => [
					'select_event'    => esc_html__( 'Event', 'sugar-calendar-lite' ),
					'no_results_text' => esc_html__( 'No results found', 'sugar-calendar-lite' ),
				],
			]
		);
	}
}
