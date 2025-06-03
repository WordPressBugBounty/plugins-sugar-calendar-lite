<?php
namespace Sugar_Calendar\AddOn\Ticketing\Admin\Tickets;

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

	public $per_page    = 30;
	public $total_count = 0;
	public $query;

	/**
	 * Get things started.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Set parent defaults
		parent::__construct( array(
			'singular' => 'event-ticket',
			'plural'   => 'event-tickets',
			'ajax'     => false
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

		$current = ! empty( $_GET['status'] )
			? sanitize_key( $_GET['status'] )
			: '';

		$total_count = '&nbsp;<span class="count">(' . number_format_i18n( $this->total_count ) . ')</span>';

		$views = array(
			'all' => sprintf( '<a href="%s"%s>%s</a>', remove_query_arg( array( 'status', 'paged' ) ), $current === 'all' || $current === '' ? ' class="current"' : '', esc_html__( 'All', 'sugar-calendar-lite' ) . $total_count )
		);

		return apply_filters( 'sc_event_tickets_list_table_views', $views );
	}

	/**
	 * Display the bulk actions dropdown.
	 *
	 * @since 1.0.0
	 * @since 3.7.0 Moved the event_id field to the extra_tablenav() method.
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 *                      This is designated as optional for backward compatibility.
	 */
	protected function bulk_actions( $which = '' ) {

		$event_id = ! empty( $_GET['event_id'] )
			? absint( $_GET['event_id'] )
			: '';

		echo '<input type="submit" name="sc_et_export_tickets" class="button-secondary" id="sc-et-export-tickets" value="' . esc_html__( 'Export to CSV', 'sugar-calendar-lite' ) . '"/>';
		echo wp_nonce_field( 'sc_et_export_nonce', 'sc_et_export_nonce' );
	}

	/**
	 * Displays extra controls between bulk actions and pagination.
	 *
	 * @since 3.7.0
	 *
	 * @param string $which Whether top or bottom.
	 */
	protected function extra_tablenav( $which ) {

		if ( $which !== 'top' ) {
			return;
		}

		$option = '';

		if ( ! empty( $_GET['event_id'] ) ) {
			$event = sugar_calendar_get_event( absint( $_GET['event_id'] ) );

			if ( ! empty( $event ) ) {
				$option = sprintf(
					'<option selected value="%1$d">%2$s</option>',
					absint( $_GET['event_id'] ),
					esc_html( $event->title )
				);
			}
		}
		?>
		<div class="sugar-calendar-ticketing__admin__list__actions alignleft actions">
			<span class="sugar-calendar-ticketing__admin__list__actions__choices-events choicesjs-select-wrap">
				<select id="sugar-calendar-ticketing-event" name="event_id" class="choicesjs-select">
					<?php
						echo wp_kses(
							$option,
							[
								'option' => [
									'selected' => [],
									'value'    => [],
								],
							]
						);
					?>
				</select>
			</span>
			<?php
			printf(
				'<input type="submit" class="button" value="%1$s">',
				esc_attr__( 'Filter', 'sugar-calendar-lite' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * Show the search field.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Label for the search box
	 * @param string $input_id ID of the search box
	 */
	public function search_box( $text, $input_id ) {

		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		}

		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		} ?>

		<p class="search-box">
			<?php do_action( 'sc_event_tickets_list_table_search_box' ); ?>
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<input type="hidden" name="tab" value="tickets" />
			<?php submit_button( $text, 'button', false, false, array('ID' => 'search-submit') ); ?><br/>
		</p><?php
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
	 * Render the order column.
	 *
	 * @since 1.0.0
	 * @since 3.3.0 Remove errors when `$order` was not found.
	 *
	 * @return string
	 */
	public function column_order( $item ) {

		if ( false !== strpos( $item->order_id, 'WOO' ) ) {
			$order  = wc_get_order( str_replace( 'WOO-', '', $item->order_id ) );
			$status = $order->get_status();

		} else {
			$order  = Functions\get_order( $item->order_id );
			$status = empty( $order ) ? null : $order->status;
		}

		if ( ! empty( $order ) ) {
			// Setup URL.
			$url = add_query_arg(
				[
					'page'     => 'sc-event-ticketing',
					'order_id' => $item->order_id,
				],
				admin_url( 'admin.php' )
			);

			// Filter URL.
			$link = apply_filters( 'sc_et_tickets_list_table_order_link', $url, $item );

			// Setup HTML.
			$link  = '<a href="' . esc_url( $link ) . '" title="' . esc_attr__( 'View or edit ticket', 'sugar-calendar-lite' ) . '">';
			$link .= esc_html( $item->order_id );

			// Refunded.
			if ( $status === 'refunded' ) {
				$link .= ' ' . esc_html__( '(Refunded)', 'sugar-calendar-lite' );
			}

			// Close HTML.
			$link .= '</a><br>';
		} else {
			$link = '';
		}

		// Format.
		$start_date = sugar_calendar_format_date( sc_get_date_format(), $item->date_created );
		$start_time = sugar_calendar_format_date( sc_get_time_format(), $item->date_created );

		// Return HTML.
		return $link . esc_html( $start_date ) . '<br>' . esc_html( $start_time );
	}

	/**
	 * Attendees column.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function column_attendee( $item ) {
		$attendee = Functions\get_attendee( $item->attendee_id );

		// Bail if no attendee
		if ( empty( $attendee ) ) {
			return '&mdash;';
		}

		// Setup name
		$retval = $attendee->first_name . ' ' . $attendee->last_name . '<br>' . make_clickable( $attendee->email );

		// Get the nonce URL
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'sc_et_action' => 'email_ticket',
					'ticket_code'  => $item->code
				)
			),
			$item->code
		);

		// Add row actions
		$retval .= $this->row_actions(
			array(
				'email' => '<a href="' . esc_url( $url ) . '" title="' . esc_attr__( 'Email Ticket to attendee', 'sugar-calendar-lite' ) . '">' . esc_html__( 'Send Email', 'sugar-calendar-lite' ) . '</a>'
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
			'attendee' => esc_html__( 'Attendee',   'sugar-calendar-lite' ),
			'code'     => esc_html__( 'Code',       'sugar-calendar-lite' ),
			'id'       => esc_html__( 'Ticket ID',  'sugar-calendar-lite' ),
			'event'    => esc_html__( 'Event',      'sugar-calendar-lite' ),
			'order'    => esc_html__( 'Order Date', 'sugar-calendar-lite' )
		);

		// Filter & return
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

		$this->total_count = Functions\count_tickets( $args );
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
		$status   = ! empty( $_GET['status'] )
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
		);

		// "event:" type searches
		if ( false !== strpos( $search, 'event:' ) ) {

			$search = str_replace( 'event:', '', $search );

			// Searching by event ID
			if ( is_numeric( $search ) ) {

				// Get event
				$event = sugar_calendar_get_event( $search );

				// See if an event with a matching post ID exists
				if ( empty( $event ) ) {
					$event = sugar_calendar_get_event_by_object( $search );
				}

			// Search for an event by the title
			} else {
				$event = sugar_calendar_get_event_by( 'title', $search );
			}

			// Query by event ID
			if ( ! empty( $event ) ) {
				$args['event_id'] = $event->id;
				$search = '';
			}
		}

		// Set the search
		$args['search'] = $search;

		// Set the status
		if ( 'any' !== $status ) {
			$args['status'] = $status;
		}

		// Cast event ID
		if ( ! empty( $_GET['event_id'] ) && empty( $args['event_id'] ) ) {
			$args['event_id'] = absint( $_GET['event_id'] );
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
		$this->query = new Database\Ticket_Query( $args );

		// Set items
		$this->items = $this->query->items;

		// Set paginations
		$this->set_pagination_args( array(
			'total_items' => $this->total_count,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( $this->total_count / $this->per_page )
		) );
	}
}

endif;
