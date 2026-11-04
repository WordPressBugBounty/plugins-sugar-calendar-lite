<?php
namespace Sugar_Calendar\AddOn\Ticketing\Admin\Orders;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Database as Database;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
use Sugar_Calendar\AddOn\Ticketing\Admin\ListTableAbstract;
use Sugar_Calendar\AddOn\Ticketing\Admin\OrdersDiscountColumn;
use Sugar_Calendar\Helper;

// Include the main list table class if it's not included
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// No list table class, so something went very wrong
if ( class_exists( '\WP_List_Table' ) ) :

/**
 * Orders list table.
 *
 * @since 1.0.0
 */
class List_Table extends ListTableAbstract {

	public $per_page       = 30;
	public $total_count    = 0;
	public $paid_count     = 0;
	public $pending_count  = 0;
	public $refunded_count = 0;
	public $query;

	/**
	 * Discount column.
	 *
	 * @since 3.17.0
	 *
	 * @var OrdersDiscountColumn
	 */
	private $discount_column;

	/**
	 * Get things started.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Set parent defaults
		parent::__construct( array(
			'singular'  => 'event-ticket',
			'plural'    => 'event-tickets',
			'ajax'      => false
		) );

		$this->discount_column = new OrdersDiscountColumn();

		$this->get_ticket_counts();
	}

	/**
	 * Retrieve the view types.
	 *
	 * @since 1.0.0
	 * @return array $views All the views available
	 */
	public function get_views() {

		// Status
		$current = ! empty( $_GET['status'] )
			? sanitize_key( $_GET['status'] )
			: '';

		// Counts
		$total_count     = '&nbsp;<span class="count">(' . number_format_i18n( $this->total_count    ) . ')</span>';
		$paid_count      = '&nbsp;<span class="count">(' . number_format_i18n( $this->paid_count     ) . ')</span>';
		$pending_count   = '&nbsp;<span class="count">(' . number_format_i18n( $this->pending_count  ) . ')</span>';
		$refunded_count  = '&nbsp;<span class="count">(' . number_format_i18n( $this->refunded_count ) . ')</span>';

		// Views.
		$views = [
			'all'      => sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( remove_query_arg( [ 'status', 'paged' ] ) ),
				$current === 'all' || $current === '' ? ' class="current"' : '',
				wp_kses(
					Functions\order_status_label( 'all' ) . $total_count,
					[
						'span' => [
							'class' => [],
						],
					]
				)
			),
			'pending'  => sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url(
					add_query_arg(
						[
							'status' => 'pending',
							'paged'  => false,
						]
					)
				),
				$current === 'pending' ? ' class="current"' : '',
				wp_kses(
					Functions\order_status_label( 'pending' ) . $pending_count,
					[
						'span' => [
							'class' => [],
						],
					]
				)
			),
			'paid'     => sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url(
					add_query_arg(
						[
							'status' => 'paid',
							'paged'  => false,
						]
					)
				),
				$current === 'paid' ? ' class="current"' : '',
				wp_kses(
					Functions\order_status_label( 'paid' ) . $paid_count,
					[
						'span' => [
							'class' => [],
						],
					]
				)
			),
			'refunded' => sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url(
					add_query_arg(
						[
							'status' => 'refunded',
							'paged'  => false,
						]
					)
				),
				$current === 'refunded' ? ' class="current"' : '',
				wp_kses(
					Functions\order_status_label( 'refunded' ) . $refunded_count,
					[
						'span' => [
							'class' => [],
						],
					]
				)
			),
		];

		// Get the count of trashed orders.
		$trash_count = Functions\count_orders( [ 'status' => 'trash' ] );

		// Add Trash view if there are trashed orders.
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
				wp_kses(
					Functions\order_status_label( 'trash' ) . $trash_count_html,
					[
						'span' => [
							'class' => [],
						],
					]
				)
			);
		}

		// Filter & return.
		return apply_filters( 'sc_event_tickets_list_table_views', $views );
	}

	/**
	 * Value of the hidden `tab` input in the list form.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	protected function get_hidden_tab() {

		return 'orders';
	}

	/**
	 * Placeholder of the search input.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	protected function get_search_placeholder() {

		return __( 'Search Customer...', 'sugar-calendar-lite' );
	}

	/**
	 * Display the search reset block.
	 *
	 * @since 3.8.2
	 */
	public function display_search_reset() {

		$this->display_tab_search_reset(
			esc_html__( 'order', 'sugar-calendar-lite' ),
			esc_html__( 'orders', 'sugar-calendar-lite' ),
			esc_html__( 'Orders', 'sugar-calendar-lite' )
		);
	}

	/** Columns ***************************************************************/

	/**
	 * Render most columns.
	 *
	 * @since 1.0.0
	 *
	 * @param Order  $item        Order object
	 * @param string $column_name Column name
	 * @return string
	 */
	public function column_default( $item = null, $column_name = '' ) {
		return $item->{$column_name};
	}

	/**
	 * Status column
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Wrap status text.
	 *
	 * @param Order $item Order object
	 * @return string
	 */
	public function column_status( $item = null ) {

		// Get status label
		$status_label = Functions\order_status_label( $item->status );

		// Return status with span wrapper
		return wp_kses_post(
			sprintf(
				'<span class="column-status__%s">%s</span>',
				esc_attr( $item->status ),
				$status_label
			)
		);
	}

	/**
	 * Date column.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Update date and time display.
	 * @since 3.13.0 Show the order's own date instead of the event's start date.
	 *
	 * @param Order $item Order object.
	 *
	 * @return string
	 */
	public function column_date( $item = null ) {

		$order_date = sugar_calendar_format_date_i18n( sc_get_date_format(), $item->date_created );
		$order_time = sugar_calendar_format_date_i18n( sc_get_time_format(), $item->date_created );

		return wp_kses_post(
			wp_sprintf(
				'%1$s %2$s %3$s',
				esc_html( $order_date ),
				esc_html__( 'at', 'sugar-calendar-lite' ),
				esc_html( $order_time )
			)
		);
	}

	/**
	 * Customer column.
	 *
	 * @since 1.0.0
	 *
	 * @param Order $item Order object
	 * @return string
	 */
	public function column_customer( $item = null ) {

		// Customer
		$retval = $item->first_name . ' ' . $item->last_name . '<br>' . make_clickable( $item->email );

		// Return HTML
		return $retval;
	}

	/**
	 * Event column.
	 *
	 * @since 1.0.0
	 * @since 3.6.0 Added the ability to filter the event column value.
	 *
	 * @param Order $item Order object.
	 *
	 * @return string
	 */
	public function column_event( $item = null ) {

		/**
		 * Filter the event column value.
		 *
		 * @since 3.6.0
		 *
		 * @param string|false $pre_column_event_val The value of the column.
		 * @param object       $item                 The current item.
		 */
		$pre_column_event_val = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sc_et_admin_orders_list_table_event_col',
			false,
			$item
		);

		if ( $pre_column_event_val !== false ) {
			return $pre_column_event_val;
		}

		// Get Event.
		$event = sugar_calendar_get_event( $item->event_id );

		// Bail if no Event
		if ( empty( $event ) ) {
			return '&mdash;';
		}

		// Setup URL
		$url = add_query_arg(
			array(
				'action' => 'edit',
				'post'   => $event->object_id
			),
			admin_url( 'post.php' )
		);

		// Make sure Event is not missing
		$retval = '<a href="' . esc_url( $url ) . '">' . esc_html( $event->title ) . '</a>';

		// Format
		$start_date = $event->format_date( sc_get_date_format(), $event->start );
		$start_time = $event->format_date( sc_get_time_format(), $event->start );

		// Return
		return $retval . '<br>' . esc_html( $start_date ) . '<br>' . esc_html( $start_time );
	}

	/**
	 * Attendees column.
	 *
	 * @since 1.0.0
	 *
	 * @param Order $item Order object
	 * @return string
	 */
	public function column_tickets( $item = null ) {
		return max( 1, count( Functions\get_order_tickets( $item->id ) ) );
	}

	/**
	 * Discount column.
	 *
	 * @since 3.17.0
	 *
	 * @param Order $item Order object.
	 *
	 * @return string
	 */
	public function column_discount( $item = null ) {

		return $this->discount_column->cell( $item );
	}

	/**
	 * Total column.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Add additional support actions.
	 *
	 * @param Order $item Order object.
	 *
	 * @return string
	 */
	public function column_total( $item = null ) {

		$retval = '<strong>' . Functions\display_price( $item->total ) . '</strong>';

		// Setup URL.
		$url = add_query_arg(
			[
				'page'     => 'sc-event-ticketing',
				'order_id' => $item->id,
			],
			admin_url( 'admin.php' )
		);

		/**
		 * Filter URL.
		 *
		 * @since 3.8.0
		 *
		 * @param string $url  The URL.
		 * @param object $item The current item.
		 */
		$link = apply_filters( 'sugar_calendar_add_on_ticketing_admin_orders_list_table_order_view_link', $url, $item );

		// Get status.
		$is_trashed = $item->status === 'trash';

		// Actions.
		$actions = [
			'view' => '<a href="' . esc_url( $link ) . '" title="' . esc_attr__( 'Edit order', 'sugar-calendar-lite' ) . '">' . esc_html__( 'Edit', 'sugar-calendar-lite' ) . '</a>',
		];

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
								'order'  => [ $item->id ],
								'tab'    => 'orders',
							],
							admin_url( 'admin.php' )
						),
						'bulk-event-tickets'
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
								'order'  => [ $item->id ],
								'tab'    => 'orders',
							],
							admin_url( 'admin.php' )
						),
						'bulk-event-tickets'
					)
				),
				esc_attr__( 'Are you sure you want to permanently delete this order? This action cannot be undone.', 'sugar-calendar-lite' ),
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
								'order'  => [ $item->id ],
								'tab'    => 'orders',
							],
							admin_url( 'admin.php' )
						),
						'bulk-event-tickets'
					)
				),
				esc_attr__( 'Move this order to the Trash', 'sugar-calendar-lite' ),
				esc_html__( 'Trash', 'sugar-calendar-lite' )
			);
		}

		// Setup HTML.
		$retval .= $this->row_actions( $actions );

		// Return HTML.
		return $retval;
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
			'<input type="checkbox" name="order[]" value="%d" />',
			absint( $item->id )
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @since 3.8.0
	 *
	 * @return array
	 */
	public function get_bulk_actions() {

		// Get current view.
		$current_view = isset( $_GET['status'] )
			? sanitize_key( $_GET['status'] )
			: '';

		// Default actions.
		$actions = [];

		// Different actions based on view.
		if ( $current_view === 'trash' ) {
			$actions['restore'] = __( 'Restore', 'sugar-calendar-lite' );
			$actions['delete']  = __( 'Delete Permanently', 'sugar-calendar-lite' );
		} else {
			$actions['trash'] = __( 'Move to Trash', 'sugar-calendar-lite' );
		}

		return $actions;
	}

	/**
	 * Retrieve the table columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_columns() {

		// Columns
		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'total'    => esc_html__( 'Total',      'sugar-calendar-lite' ),
			'tickets'  => esc_html__( 'Tickets',    'sugar-calendar-lite' ),
			'status'   => esc_html__( 'Status',     'sugar-calendar-lite' ),
			'customer' => esc_html__( 'Customer',   'sugar-calendar-lite' ),
			'id'       => esc_html__( 'Order ID',   'sugar-calendar-lite' ),
			'event'    => esc_html__( 'Event',      'sugar-calendar-lite' ),
			'date'     => esc_html__( 'Order Date', 'sugar-calendar-lite' ),
		);

		$columns = $this->discount_column->insert( $columns );

		// Filter & Return
		return apply_filters( 'sc_event_tickets_list_table_columns', $columns );
	}

	/**
	 * Retrieve the sortable table columns.
	 *
	 * @since 1.1.4
	 *
	 * @return array
	 */
	public function get_sortable_columns() {

		// Columns
		$columns = array(
			'id'     => array( 'id',           'asc' ),
			'date'   => array( 'date_created', 'asc' ),
			'total'  => array( 'total',        'asc' ),
			'status' => array( 'status',       'asc' ),
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
	 * @since 1.0.0
	 */
	public function get_ticket_counts() {

		// Search
		$search = ! empty( $_GET['s'] )
			? sanitize_text_field( $_GET['s'] )
			: '';

		// Setup counts
		$this->paid_count     = Functions\count_orders( array( 'status' => 'paid',     'search' => $search ) );
		$this->pending_count  = Functions\count_orders( array( 'status' => 'pending',  'search' => $search ) );
		$this->refunded_count = Functions\count_orders( array( 'status' => 'refunded', 'search' => $search ) );

		// Setup total
		$this->total_count    = $this->paid_count + $this->pending_count + $this->refunded_count;
	}

	/**
	 * Search for orders by customer name.
	 *
	 * @since 3.8.0
	 *
	 * @param string $search Search string.
	 *
	 * @return array Results.
	 */
	private function sc_search_orders( $search ) {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $search ) . '%';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = "
			SELECT id
			FROM {$wpdb->prefix}sc_orders
			WHERE
				first_name LIKE %s OR
				last_name LIKE %s OR
				CONCAT(first_name, ' ', last_name) LIKE %s
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare(
			$query,
			[
				$like,
				$like,
				$like,
			]
		);

		// Get IDs as a flat array and ensure they're all integers.
		$ids = wp_list_pluck( $wpdb->get_results( $prepared ), 'id' );

		return array_map( 'absint', $ids );
	}

	/** Query *****************************************************************/

	/**
	 * Setup the final data for the table.
	 *
	 * @since 1.0.0
	 * @since 3.8.0 Respect the user preference for per page items.
	 * @since 3.8.0 Revise usage of status.
	 * @since 3.8.0 Add support for searching by customer name.
	 * @since 3.8.2 Separate prepare_items method.
	 */
	public function prepare_items() {

		$this->discount_column->apply_user_default( get_current_user_id() );

		// Columns and hidden columns based on user preferences (cogwheel UI).
		$this->set_column_headers( 'orders', [ 'cb', 'total', 'status', 'date' ] );

		// Sanitize status.
		$status = isset( $_GET['status'] )
			? sanitize_key( $_GET['status'] )
			: 'any';

		// Sanitize search.
		$search = ! empty( $_GET['s'] )
			? sanitize_text_field( wp_unslash( $_GET['s'] ) )
			: '';

		// Set the per page limit.
		$this->apply_saved_per_page();

		// Default arguments..
		$args = [
			'number' => $this->per_page,
			'offset' => $this->per_page * ( $this->get_paged() - 1 ),
		];

		// Set status.
		if ( in_array( $status, [ 'any', 'pending', 'paid', 'refunded' ], true ) ) {
			$args['not_in'] = [ 'trash' ];
		}

		if ( $status === 'any' ) {
			$args['status'] = [
				'pending',
				'paid',
				'refunded',
			];
		} else {
			$args['status'] = $status;
		}

		// Search.
		if ( ! empty( $search ) ) {
			$orders = $this->sc_search_orders( $search );

			if ( ! empty( $orders ) ) {
				$args['id__in'] = $orders;
			}
		}

		// Event filtering
		if ( ! empty( $_GET['event_id'] ) ) {
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
		$this->query = new Database\Order_Query( $args );

		// Items.
		$this->items = $this->query->items;

		// Set total items.
		switch ( $status ) {
			case 'paid':
				$total_items = $this->paid_count;
				break;

			case 'pending':
				$total_items = $this->pending_count;
				break;

			case 'refunded':
				$total_items = $this->refunded_count;
				break;

			case 'any':
			default:
				$total_items = $this->total_count;
				break;
		}

		// Set paginations.
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			]
		);
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
			esc_html__( 'No Orders detected!', 'sugar-calendar-lite' ),
			'',
			'',
			'orders'
		);
	}
}

endif;
