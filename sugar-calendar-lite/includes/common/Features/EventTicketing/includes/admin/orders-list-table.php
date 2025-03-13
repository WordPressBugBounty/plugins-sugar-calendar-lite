<?php
namespace Sugar_Calendar\AddOn\Ticketing\Admin\Orders;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Database as Database;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;

// Include the main list table class if it's not included
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// No list table class, so something went very wrong
if ( class_exists( '\WP_List_Table' ) ) :

class List_Table extends \WP_List_Table {

	public $per_page       = 30;
	public $total_count    = 0;
	public $paid_count     = 0;
	public $pending_count  = 0;
	public $refunded_count = 0;
	public $query;

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

		// Filter & return.
		return apply_filters( 'sc_event_tickets_list_table_views', $views );
	}

	/**
	 * Show the search field.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Label for the search box
	 * @param string $input_id ID of the search box
	 */
	public function search_box( $text = '', $input_id = '' ) {

		// Bail if no items
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		// Setup input ID
		$input_id = $input_id . '-search-input';

		// Hidden orderby
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		}

		// Hidden order
		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		} ?>

		<p class="search-box">
			<?php do_action( 'sc_event_tickets_list_table_search_box' ); ?>
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, 'button', false, false, array('ID' => 'search-submit') ); ?><br/>
		</p><?php
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
	 *
	 * @param Order $item Order object
	 * @return string
	 */
	public function column_status( $item = null ) {
		return Functions\order_status_label( $item->status );
	}

	/**
	 * Date column.
	 *
	 * @since 1.0.0
	 *
	 * @param Order $item Order object
	 * @return string
	 */
	public function column_date( $item = null ) {

		// Get Event
		$event = sugar_calendar_get_event( $item->event_id );

		// Bail if no Event
		if ( empty( $event ) ) {
			return '&mdash;';
		}

		// Format
		$start_date = $event->format_date( sc_get_date_format(), $event->start );
		$start_time = $event->format_date( sc_get_time_format(), $event->start );

		// Return
		return esc_html( $start_date ) . '<br>' . esc_html( $start_time );
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
	 * Total column.
	 *
	 * @since 1.0.0
	 *
	 * @param Order $item Order object
	 * @return string
	 */
	public function column_total( $item = null ) {
		$retval = '<strong>' . Functions\currency_filter( $item->total ) . '</strong>';

		// Setup URL
		$url = add_query_arg(
			array(
				'page'    => 'sc-event-ticketing',
				'order_id'=> $item->id
			),
			admin_url( 'admin.php' )
		);

		// Filter URL
		$link = apply_filters( 'sc_et_tickets_list_table_order_view_link', $url, $item );

		// Setup HTML
		$retval .= $this->row_actions(
			array(
				'view' => '<a href="' . esc_url( $link ) . '" title="' . esc_attr__( 'Edit order', 'sugar-calendar-lite' ) . '">' . esc_html__( 'Edit', 'sugar-calendar-lite' ) . '</a>'
			)
		);

		// Return HTML
		return $retval;
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
			'total'    => esc_html__( 'Total',      'sugar-calendar-lite' ),
			'tickets'  => esc_html__( 'Tickets',    'sugar-calendar-lite' ),
			'status'   => esc_html__( 'Status',     'sugar-calendar-lite' ),
			'customer' => esc_html__( 'Customer',   'sugar-calendar-lite' ),
			'id'       => esc_html__( 'Order ID',   'sugar-calendar-lite' ),
			'event'    => esc_html__( 'Event',      'sugar-calendar-lite' ),
			'date'     => esc_html__( 'Order Date', 'sugar-calendar-lite' ),
		);

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

	/** Query *****************************************************************/

	/**
	 * Setup the final data for the table.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function prepare_items() {

		// Columns
		$columns               = $this->get_columns();
		$sortable              = $this->get_sortable_columns();
		$hidden                = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Sanitize status
		$status = isset( $_GET['status'] )
			? sanitize_key( $_GET['status'] )
			: 'any';

		// Sanitize search
		$search = ! empty( $_GET['s'] )
			? sanitize_text_field( $_GET['s'] )
			: '';

		// Default arguments
		$args = array(
			'number' => $this->per_page,
			'offset' => $this->per_page * ( $this->get_paged() - 1 ),
			'search' => $search
		);

		// Set status
		if ( 'any' !== $status ) {
			$args['status'] = $status;
		}

		// Sanitize orderby
		if ( ! empty( $_GET['orderby'] ) ) {
			$args['orderby'] = sanitize_key( $_GET['orderby'] );
		} else {
			$args['orderby'] = 'date_created';
		}

		// Sanitize order
		if ( ! empty( $_GET['order'] ) && in_array( $_GET['order'], array( 'asc', 'desc' ), true ) ) {
			$args['order'] = sanitize_key( $_GET['order'] );
		}

		// Query
		$this->query = new Database\Order_Query( $args );

		// Items
		$this->items = $this->query->items;

		// Set total items
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

		// Set paginations
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( $total_items / $this->per_page )
		) );
	}
}

endif;
