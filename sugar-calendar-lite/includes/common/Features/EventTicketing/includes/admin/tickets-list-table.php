<?php
namespace Sugar_Calendar\AddOn\Ticketing\Admin\Tickets;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Database as Database;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
use Sugar_Calendar\AddOn\Ticketing\Admin\ListTableAbstract;
use Sugar_Calendar\Helper;

// Include the main list table class if it's not included
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// No list table class, so something went very wrong
if ( class_exists( '\WP_List_Table' ) ) :

/**
 * Tickets list table.
 *
 * @since 1.0.0
 */
class List_Table extends ListTableAbstract {

	public $per_page    = 30;
	public $total_count = 0;
	public $query;
	public $should_show_trashed_tickets = true;
	public $should_show_cb = true;
	public $should_enable_sorting = true;

	/**
	 * Get things started.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Bulk actions processing moved to TicketsTab class.
	 */
	public function __construct() {

		// Set parent defaults.
		parent::__construct(
			[
				'singular' => 'event-ticket',
				'plural'   => 'event-tickets',
				'ajax'     => false,
			]
		);

		// Disable this for now. Function unclear.
		// $this->get_ticket_counts();
	}

	/**
	 * Retrieve the view types.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Add trash view.
	 *
	 * @return array $views All the views available
	 */
	public function get_views() {

		$current = ! empty( $_GET['status'] )
			? sanitize_key( $_GET['status'] )
			: '';

		$total_count = '&nbsp;<span class="count">(' . number_format_i18n( $this->total_count ) . ')</span>';

		$views = [
			'all' => sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url(
					remove_query_arg(
						[
							'status',
							'paged',
						]
					)
				),
				$current === 'all' || $current === '' ? ' class="current"' : '',
				__( 'All', 'sugar-calendar-lite' ) . $total_count
			),
		];

		// Get the count of trashed tickets.
		$trash_count = Functions\count_tickets( [ 'status' => 'trash' ] );

		// Add Trash view if there are trashed tickets.
		if ( $trash_count > 0 ) {

			$trash_count_html = '&nbsp;<span class="count">(' . number_format_i18n( $trash_count ) . ')</span>';

			$views['trash'] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url(
					add_query_arg(
						[
							'status' => 'trash',
							'paged'  => false,
						]
					)
				),
				$current === 'trash' ? ' class="current"' : '',
				__( 'Trash', 'sugar-calendar-lite' ) . $trash_count_html
			);
		}

		/**
		 * Filter the views.
		 *
		 * @since 3.8.0
		 *
		 * @param array $views The views.
		 *
		 * @return array $views The views.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sc_event_tickets_list_table_views',
			$views
		);
	}

	/**
	 * Value of the hidden `tab` input in the list form.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	protected function get_hidden_tab() {

		return 'tickets';
	}

	/**
	 * Placeholder of the search input.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	protected function get_search_placeholder() {

		return __( 'Search Attendee...', 'sugar-calendar-lite' );
	}

	/**
	 * Display the search reset block.
	 *
	 * @since 3.8.2
	 */
	public function display_search_reset() {

		$this->display_tab_search_reset(
			esc_html__( 'ticket', 'sugar-calendar-lite' ),
			esc_html__( 'tickets', 'sugar-calendar-lite' ),
			esc_html__( 'Tickets', 'sugar-calendar-lite' )
		);
	}

	/** Columns ***************************************************************/

	/**
	 * Render most columns.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return $item->{$column_name};
	}

	/**
	 * Render most columns.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function column_id( $item ) {

		// Escape
		$retval = esc_html( $item->id );

		// Return HTML
		return $retval;
	}

	/**
	 * Code column.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function column_code( $item ) {
		$retval = '<code>' . $item->code . '</code>';

		// Return HTML
		return $retval;
	}

	/**
	 * Event column.
	 *
	 * @since 1.0.0
	 * @since 3.6.0 Added the ability to filter the event column value.
	 * @since 3.8.0 Changed the format of event value.
	 *
	 * @param object $item The current item.
	 *
	 * @return string
	 */
	public function column_event( $item ) {

		/**
		 * Filter the event column value.
		 *
		 * @since 3.6.0
		 *
		 * @param string|false $pre_column_event_val The value of the column.
		 * @param object       $item                 The current item.
		 */
		$pre_column_event_val = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sc_et_admin_tickets_list_table_event_col',
			false,
			$item
		);

		if ( $pre_column_event_val !== false ) {
			return $pre_column_event_val;
		}

		// Get Event.
		$event = sugar_calendar_get_event( $item->event_id );

		// Bail if no Event.
		if ( empty( $event ) ) {
			return '&mdash;';
		}

		// Setup URL.
		$url = add_query_arg(
			[
				'action' => 'edit',
				'post'   => $event->object_id,
			],
			admin_url( 'post.php' )
		);

		// Format date and time.
		$start_date = $event->format_date( sc_get_date_format(), $event->start );
		$start_time = $event->format_date( sc_get_time_format(), $event->start );

		// Return formatted event details.
		return sprintf(
			'<a href="%1$s">%2$s</a><br>%3$s<br>%4$s',
			esc_url( $url ),
			esc_html( $event->title ),
			esc_html( $start_date ),
			esc_html( $start_time )
		);
	}

	/**
	 * Render the order column.
	 *
	 * @since 1.0.0
	 * @since 3.3.0 Remove errors when `$order` was not found.
	 * @since 3.8.0 Changed the format of order value.
	 *
	 * @param object $item The current item.
	 *
	 * @return string
	 */
	public function column_order( $item ) {

		// Format.
		$start_date = sugar_calendar_format_date( sc_get_date_format(), $item->date_created );
		$start_time = sugar_calendar_format_date( sc_get_time_format(), $item->date_created );

		return wp_kses_post(
			wp_sprintf(
				'%1$s %2$s %3$s',
				esc_html( $start_date ),
				esc_html__( 'at', 'sugar-calendar-lite' ),
				esc_html( $start_time )
			)
		);
	}

	/**
	 * Attendee column.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Added event check to conditionally hide email action for non-existent events.
	 *
	 * @param object $item The current item.
	 *
	 * @return string
	 */
	public function column_attendee( $item ) {

		$attendee = Functions\get_attendee( $item->attendee_id );

		// Bail if no attendee.
		if ( empty( $attendee ) ) {
			return '&mdash;';
		}

		$attendee_name = $attendee->first_name . ' ' . $attendee->last_name;

		$retval  = '<span>' . esc_html( $attendee_name ) . '</span><br>';
		$retval .= '<a href="mailto:' . esc_attr( $attendee->email ) . '">' . esc_html( $attendee->email ) . '</a>';

		// Check if event exists.
		$event = sugar_calendar_get_event( $item->event_id );

		// Get status.
		$is_trashed = $item->status === 'trash';

		// Actions.
		$actions = [];

		// Email - only add if event exists.
		if ( ! empty( $event ) ) {
			$actions['email'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							[
								'page'   => 'sc-event-ticketing',
								'action' => 'email',
								'ticket' => [ $item->id ],
							],
							admin_url( 'admin.php' )
						),
						'bulk-' . $this->_args['plural']
					)
				),
				esc_html__( 'Send Email', 'sugar-calendar-lite' )
			);
		}

		// View Order.
		$actions['view_order'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					[
						'page'     => 'sc-event-ticketing',
						'view'     => 'order',
						'order_id' => $item->order_id,
					],
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'View Order Details', 'sugar-calendar-lite' )
		);

		if ( $this->should_show_trashed_tickets ) {
			// Different actions based on trash status.
			if ( $is_trashed ) {
				// Restore.
				$actions['restore'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						wp_nonce_url(
							add_query_arg(
								[
									'page'   => 'sc-event-ticketing',
									'action' => 'restore',
									'ticket' => [ $item->id ],
								],
								admin_url( 'admin.php' )
							),
							'bulk-' . $this->_args['plural']
						)
					),
					esc_html__( 'Restore', 'sugar-calendar-lite' )
				);

				// Delete Permanently.
				$actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
					esc_url(
						wp_nonce_url(
							add_query_arg(
								[
									'page'   => 'sc-event-ticketing',
									'action' => 'delete',
									'ticket' => [ $item->id ],
								],
								admin_url( 'admin.php' )
							),
							'bulk-' . $this->_args['plural']
						)
					),
					esc_attr__( 'Are you sure you want to permanently delete this ticket? This action cannot be undone.', 'sugar-calendar-lite' ),
					esc_html__( 'Delete Permanently', 'sugar-calendar-lite' )
				);
			} else {
				// Trash.
				$actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete" title="%s">%s</a>',
					esc_url(
						wp_nonce_url(
							add_query_arg(
								[
									'page'   => 'sc-event-ticketing',
									'action' => 'trash',
									'ticket' => [ $item->id ],
								],
								admin_url( 'admin.php' )
							),
							'bulk-' . $this->_args['plural']
						)
					),
					esc_attr__( 'Move this ticket to the Trash', 'sugar-calendar-lite' ),
					esc_html__( 'Trash', 'sugar-calendar-lite' )
				);
			}
		}

		$retval .= '<div class="row-actions">' . $this->row_actions( $actions ) . '</div>';

		return $retval;
	}

	/**
	 * Retrieve the table columns.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Added the bulk action checkbox column.
	 *
	 * @return array
	 */
	public function get_columns() {

		// Columns.
		$columns = [
			'cb'       => '<input type="checkbox" />',
			'attendee' => esc_html__( 'Attendee',   'sugar-calendar-lite' ),
			'code'     => esc_html__( 'Code',       'sugar-calendar-lite' ),
			'id'       => esc_html__( 'Ticket ID',  'sugar-calendar-lite' ),
			'event'    => esc_html__( 'Event',      'sugar-calendar-lite' ),
			'order'    => esc_html__( 'Order Date', 'sugar-calendar-lite' ),
		];

		if ( ! $this->should_show_cb ) {
			unset( $columns['cb'] );
		}

		// Filter & return.
		return apply_filters( 'sc_event_tickets_list_table_columns', $columns );
	}

	/**
	 * Checkbox column.
	 *
	 * @since 3.8.0
	 *
	 * @param object $item The current item.
	 *
	 * @return string
	 */
	public function column_cb( $item ) {

		return sprintf(
			'<input type="checkbox" name="ticket[]" value="%d" />',
			absint( $item->id )
		);
	}

	/**
	 * Retrieve the sortable table columns.
	 *
	 * @since 1.1.4
	 *
	 * @return array
	 */
	public function get_sortable_columns() {

		if ( ! $this->should_enable_sorting ) {
			return [];
		}

		// Columns
		$columns = array(
			'id'       => array( 'id',           'asc' ),
			'order'    => array( 'date_created', 'asc' ),
			'attendee' => array( 'attendee_id',  'asc' ),
		);

		// Return
		return $columns;
	}

	/** Pagination ************************************************************/

	/**
	 * Retrieve the current page number.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_paged() {
		return ! empty( $_GET['paged'] )
			? absint( $_GET['paged'] )
			: 1;
	}

	/**
	 * Retrieve the ticket counts.
	 *
	 * @since 1.0
	 */
	public function get_ticket_counts() {

		$args = array();

		$search = ! empty( $_GET['s'] )
			? sanitize_text_field( $_GET['s'] )
			: '';

		if ( false !== strpos( $search, 'event:' ) ) {

			$search = str_replace( 'event:', '', $search );

			if ( is_numeric( $search ) ) {

				$event = sugar_calendar_get_event( $search );

				if ( empty( $event ) ) {
					// See if an event with a matching post ID exists
					$event = sugar_calendar_get_event_by_object( $search );
				}

			} else {

				// Search for an event by the title
				$event = sugar_calendar_get_event_by( 'title', $search );
			}

			if ( ! empty( $event ) ) {
				$args['event_id'] = $event->id;
				$search = '';
			}
		}

		$args['search'] = $search;

		if ( ! empty( $_GET['event_id'] ) && empty( $args['event_id'] ) ) {
			$args['event_id'] = absint( $_GET['event_id'] );
		}

		// Include all tickets (including trashed) in the total count.
		$count_args = $args;
		$count_args['status'] = '';

		$this->total_count = Functions\count_tickets( $count_args );
	}

	/** Query *****************************************************************/

	/**
	 * Setup the final data for the table.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Respect the user preference for per page items.
	 * @since 3.8.0 Fix the search for tickets by attendee name.
	 * @since 3.8.0 Add support for trash status filtering.
	 * @since 3.8.2 Separate prepare_items method.
	 */
	public function prepare_items() {

		// Columns and hidden columns based on user preferences (cogwheel UI).
		$this->set_column_headers( 'tickets', [ 'cb', 'attendee', 'order' ] );

		// Sanitize status.
		$status = ! empty( $_GET['status'] )
			? sanitize_key( $_GET['status'] )
			: 'active';

		// Sanitize search.
		$search = ! empty( $_GET['s'] )
			? sanitize_text_field( wp_unslash( $_GET['s'] ) )
			: '';

		// Set the per page limit.
		$this->apply_saved_per_page();

		// Sanitize orderby..
		$orderby = ! empty( $_GET['orderby'] )
			? sanitize_key( $_GET['orderby'] )
			: 'id';

		// Sanitize order.
		$order = ! empty( $_GET['order'] )
			? sanitize_key( $_GET['order'] )
			: 'desc';

		// Sanitize page.
		$page = $this->get_paged();

		// Args.
		$args = [
			'number'  => $this->per_page,
			'offset'  => $this->per_page * ( $page - 1 ),
			'orderby' => $orderby,
			'order'   => $order,
		];

		// Set status based on view.
		$args['status'] = $status === 'trash' ? 'trash' : 'active';

		// Event ID.
		if ( ! empty( $_GET['event_id'] ) ) {
			$args['event_id'] = absint( $_GET['event_id'] );
		}

		// Search.
		if ( ! empty( $search ) ) {

			$tickets = $this->sc_search_tickets( $search );

			if ( ! empty( $tickets ) ) {
				$args['id__in'] = $tickets;
			}
		}

		// Cast event ID.
		if ( ! empty( $_GET['event_id'] ) && empty( $args['event_id'] ) ) {
			$args['event_id'] = absint( $_GET['event_id'] );
		}

		// Sanitize orderby.
		if ( ! empty( $_GET['orderby'] ) ) {
			$args['orderby'] = sanitize_key( $_GET['orderby'] );
		} else {
			$args['orderby'] = 'date_created';
		}

		// Sanitize order.
		if ( ! empty( $_GET['order'] ) && in_array( $_GET['order'], [ 'asc', 'desc' ], true ) ) {
			$args['order'] = sanitize_key( $_GET['order'] );
		}

		// Query.
		$this->query = new Database\Ticket_Query( $args );

		// Set items.
		$this->items = $this->query->items;

		$total_items = Functions\count_tickets( $args );
		$total_pages = ceil( $total_items / $this->per_page );

		// Set total count.
		$this->total_count = $total_items;

		// Set pagination args.
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => $total_pages,
			]
		);
	}

	/**
	 * Search for tickets by attendee name or event title.
	 *
	 * @since 3.8.0
	 *
	 * @param string $search Search string.
	 *
	 * @return array Results.
	 */
	public static function sc_search_tickets( $search ) {

		global $wpdb;

		$like_attendee_first_name = $wpdb->esc_like( $search ) . '%';
		$like_attendee_last_name  = '%' . $wpdb->esc_like( $search ) . '%';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = "
			SELECT t.id
			FROM {$wpdb->prefix}sc_tickets t
			LEFT JOIN {$wpdb->prefix}sc_attendees a ON t.attendee_id = a.id
			WHERE
				a.first_name LIKE %s OR
				a.last_name LIKE %s
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare(
			$query,
			[
				$like_attendee_first_name,
				$like_attendee_last_name,
			]
		);

		// Get IDs as a flat array and ensure they're all integers.
		$ids = wp_list_pluck( $wpdb->get_results( $prepared ), 'id' );

		return array_map( 'absint', $ids );
	}

	/**
	 * Get bulk actions.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Add trash, restore, and delete actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {

		// Get current view.
		$current_view = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		// Default actions.
		$actions = [];

		// Different actions based on view.
		if ( $current_view === 'trash' ) {
			$actions['restore'] = __( 'Restore', 'sugar-calendar-lite' );
			$actions['delete']  = __( 'Delete Permanently', 'sugar-calendar-lite' );
		} else {
			$actions['trash'] = __( 'Move to Trash', 'sugar-calendar-lite' );
			$actions['email'] = __( 'Resend Email', 'sugar-calendar-lite' );
		}

		return $actions;
	}

	/**
	 * Message to be displayed when there are no items.
	 *
	 * @since 3.8.0
	 */
	public function no_items() {

		Helper::display_placeholder_row(
			$this->get_column_count(),
			'sc-icon-ticket',
			esc_html__( 'No Tickets detected!', 'sugar-calendar-lite' ),
			'',
			'',
			'tickets'
		);
	}
}

endif;
