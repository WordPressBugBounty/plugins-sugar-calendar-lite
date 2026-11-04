<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\AddOn\Ticketing\Admin\Orders\List_Table;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\trash_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\delete_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\restore_order;

/**
 * Orders page.
 *
 * @since 1.2.0
 */
class OrdersTab extends ListTabAbstract {

	/**
	 * Screen options ID.
	 *
	 * @since 3.8.0
	 *
	 * @var string
	 */
	const SCREEN_OPTIONS_ID = 'sugar_calendar_orders_admin_list';

	/**
	 * Table name of the column chooser.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const TABLE_NAME = 'sugar_calendar_table_orders';

	/**
	 * Columns the column chooser never hides.
	 *
	 * @since 3.17.0
	 *
	 * @var string[]
	 */
	const REQUIRED_COLUMNS = [ 'total', 'status', 'date' ];

	/**
	 * Docs URL of the help link.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const HELP_URL = 'https://sugarcalendar.com/docs/events/event-ticketing-addon/#orders-overview';

	/**
	 * Medium of the help link.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const HELP_MEDIUM = 'orders-list';

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

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have the necessary capabilities to manage orders.', 'sugar-calendar-lite' ),
				esc_html__( 'Error', 'sugar-calendar-lite' ),
				array( 'response' => 403 )
			);
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

		$this->print_notice( $notice_type, $message );
	}

	/**
	 * Create the list table.
	 *
	 * @since 3.17.0
	 *
	 * @return List_Table
	 */
	protected function create_list_table() {

		return new List_Table();
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		$this->enqueue_list_assets( 'orders', 'fetch_ticketing_orders_events_choices' );
	}
}
