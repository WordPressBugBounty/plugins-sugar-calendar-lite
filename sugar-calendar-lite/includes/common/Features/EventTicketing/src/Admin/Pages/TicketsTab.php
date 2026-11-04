<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\AddOn\Ticketing\Admin\Tickets\List_Table;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\trash_ticket;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\restore_ticket;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\delete_ticket;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\send_ticket_email;

/**
 * Tickets page.
 *
 * @since 1.2.0
 */
class TicketsTab extends ListTabAbstract {

	/**
	 * Screen options ID.
	 *
	 * @since 3.8.0
	 *
	 * @var string
	 */
	const SCREEN_OPTIONS_ID = 'sugar_calendar_tickets_admin_list';

	/**
	 * Table name of the column chooser.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const TABLE_NAME = 'sugar_calendar_table_tickets';

	/**
	 * Columns the column chooser never hides.
	 *
	 * @since 3.17.0
	 *
	 * @var string[]
	 */
	const REQUIRED_COLUMNS = [ 'attendee', 'order' ];

	/**
	 * Docs URL of the help link.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const HELP_URL = 'https://sugarcalendar.com/docs/events/event-ticketing-addon/#managing-ticket-orders';

	/**
	 * Medium of the help link.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const HELP_MEDIUM = 'tickets-list';

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
	 * Process bulk actions for tickets.
	 *
	 * @since 3.8.0 Process bulk actions for tickets.
	 */
	public function process_bulk_action() {

		$allowed_actions = [
			'trash',
			'restore',
			'delete',
			'email',
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

		// Processing mode - only process on our page, nonce supplied, with allowed action, and ticket IDs.
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
			empty( $_REQUEST['ticket'] )
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
				esc_html__( 'You do not have the necessary capabilities to manage tickets.', 'sugar-calendar-lite' ),
				esc_html__( 'Error', 'sugar-calendar-lite' ),
				array( 'response' => 403 )
			);
		}

		$action = sanitize_key( $_REQUEST['action'] );

		// Get ticket IDs.
		$ticket_ids = isset( $_REQUEST['ticket'] )
			? wp_parse_id_list( wp_unslash( $_REQUEST['ticket'] ) )
			: [];

		// Final check.
		if (
			empty( $action )
			||
			empty( $ticket_ids )
		) {
			return;
		}

		$affected_ticket_counter = 0;
		$failed_ticket_counter   = 0;

		// Process based on action.
		switch ( $action ) {
			case 'trash':
				foreach ( $ticket_ids as $ticket_id ) {
					$result = trash_ticket( $ticket_id );

					if ( $result ) {
						++$affected_ticket_counter;
					} else {
						++$failed_ticket_counter;
					}
				}
				break;

			case 'restore':
				foreach ( $ticket_ids as $ticket_id ) {
					$result = restore_ticket( $ticket_id );

					if ( $result ) {
						++$affected_ticket_counter;
					} else {
						++$failed_ticket_counter;
					}
				}
				break;

			case 'delete':
				foreach ( $ticket_ids as $ticket_id ) {
					$result = delete_ticket( $ticket_id );

					if ( $result ) {
						++$affected_ticket_counter;
					} else {
						++$failed_ticket_counter;
					}
				}
				break;

			case 'email':
				foreach ( $ticket_ids as $ticket_id ) {
					$result = send_ticket_email( $ticket_id );

					if ( $result ) {
						++$affected_ticket_counter;
					} else {
						++$failed_ticket_counter;
					}
				}
				break;
		}

		// If a return URL is provided, store notice in transient and redirect back.
		if ( ! empty( $_REQUEST['return_url'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$return_url = esc_url_raw( wp_unslash( $_REQUEST['return_url'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			set_transient(
				'sc_ticket_action_notice_' . get_current_user_id(),
				[
					'action'         => $action,
					'affected_count' => $affected_ticket_counter,
					'failed_count'   => $failed_ticket_counter,
				],
				60
			);

			wp_safe_redirect( $return_url );
			exit;
		}

		// Redirect with action results to display notice.
		wp_safe_redirect(
			add_query_arg(
				[
					'page'                  => 'sc-event-ticketing',
					'bulk_action_performed' => $action,
					'affected_count'        => $affected_ticket_counter,
					'failed_count'          => $failed_ticket_counter,
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
			case 'email':
				if ( $failed_count > 0 ) {
					$message = sprintf(
						/* translators: %1$d: Number of successful emails, %2$d: Number of failed emails. */
						_n(
							'%1$d ticket email sent successfully, %2$d failed.',
							'%1$d ticket emails sent successfully, %2$d failed.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count ),
						number_format_i18n( $failed_count )
					);
					$notice_type = 'warning';
				} else {
					$message = sprintf(
						/* translators: %d: Number of ticket emails sent. */
						_n(
							'%d ticket email sent successfully.',
							'%d ticket emails sent successfully.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count )
					);
					$notice_type = 'success';
				}
				break;

			case 'trash':
				if ( $failed_count > 0 ) {
					$message = sprintf(
						/* translators: %1$d: Number of successful, %2$d: Number of failed. */
						_n(
							'%1$d ticket moved to Trash successfully, %2$d failed.',
							'%1$d tickets moved to Trash successfully, %2$d failed.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count ),
						number_format_i18n( $failed_count )
					);
					$notice_type = 'warning';
				} else {
					$message = sprintf(
						/* translators: %d: Number of tickets moved to trash. */
						_n(
							'%d ticket was successfully moved to Trash.',
							'%d tickets were successfully moved to Trash.',
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
							'%1$d ticket restored successfully, %2$d failed.',
							'%1$d tickets restored successfully, %2$d failed.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count ),
						number_format_i18n( $failed_count )
					);
					$notice_type = 'warning';
				} else {
					$message = sprintf(
						/* translators: %d: Number of tickets restored. */
						_n(
							'%d ticket was successfully restored.',
							'%d tickets were successfully restored.',
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
							'%1$d ticket permanently deleted successfully, %2$d failed.',
							'%1$d tickets permanently deleted successfully, %2$d failed.',
							$this->affected_count,
							'sugar-calendar-lite'
						),
						number_format_i18n( $this->affected_count ),
						number_format_i18n( $failed_count )
					);
					$notice_type = 'warning';
				} else {
					$message = sprintf(
						/* translators: %d: Number of tickets permanently deleted. */
						_n(
							'%d ticket was successfully permanently deleted.',
							'%d tickets were successfully permanently deleted.',
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

		$this->enqueue_list_assets( 'tickets', 'fetch_ticketing_events_choices' );
	}
}
