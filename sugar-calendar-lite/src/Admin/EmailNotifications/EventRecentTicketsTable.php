<?php

namespace Sugar_Calendar\Admin\EmailNotifications;

use Sugar_Calendar\AddOn\Ticketing\Admin\Tickets\List_Table;
use Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query;
use Sugar_Calendar\Helpers\UI;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\count_tickets;

/**
 * Class EventRecentTicketsTable.
 *
 * @since 3.11.0
 */
class EventRecentTicketsTable extends List_Table {

	/**
	 * The WP post ID of the event being edited.
	 *
	 * Used to build return URLs for row actions so that
	 * the user is redirected back to the edit event page.
	 *
	 * @since 3.11.0
	 *
	 * @access public
	 *
	 * @var int
	 */
	public $post_id = 0;

	/**
	 * Get the columns for the recent tickets table.
	 *
	 * Removes the "Event" column since this table is displayed
	 * on the edit event page where the event context is already known.
	 *
	 * @since 3.11.0
	 *
	 * @access public
	 *
	 * @return array
	 */
	public function get_columns() {

		$columns = parent::get_columns();

		unset( $columns['event'] );

		return $columns;
	}

	/**
	 * Attendee column.
	 *
	 * Overrides the parent to include a return_url parameter
	 * in the "Send Email" row action so the user is redirected
	 * back to the edit event page after the action.
	 *
	 * @since 3.11.0
	 *
	 * @access public
	 *
	 * @param object $item The current item.
	 *
	 * @return string
	 */
	public function column_attendee( $item ) {

		$attendee = \Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendee( $item->attendee_id );

		// Bail if no attendee.
		if ( empty( $attendee ) ) {
			return '&mdash;';
		}

		$attendee_name = $attendee->first_name . ' ' . $attendee->last_name;

		$retval  = '<span>' . esc_html( $attendee_name ) . '</span><br>';
		$retval .= '<a href="mailto:' . esc_attr( $attendee->email ) . '">' . esc_html( $attendee->email ) . '</a>';

		// Check if event exists.
		$event = sugar_calendar_get_event( $item->event_id );

		// Actions.
		$actions = [];

		// Email - only add if event exists.
		if ( ! empty( $event ) ) {

			// Build the return URL pointing back to the edit event page.
			$return_url = add_query_arg(
				[
					'post'   => $this->post_id,
					'action' => 'edit',
				],
				admin_url( 'post.php' )
			);

			$actions['email'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							[
								'page'       => 'sc-event-ticketing',
								'action'     => 'email',
								'ticket'     => [ $item->id ],
								'return_url' => rawurlencode( $return_url ),
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

		$retval .= '<div class="row-actions">' . $this->row_actions( $actions ) . '</div>';

		return $retval;
	}

	public function prepare_items( $query_args = [] ) {

		// Columns and hidden columns based on user preferences (cogwheel UI).
		$columns  = $this->get_columns();
		$sortable = $this->get_sortable_columns();

		$active_columns = get_user_meta( get_current_user_id(), 'sugar_calendar_table_tickets_active_columns', true );
		$active_columns = is_array( $active_columns ) ? $active_columns : array_keys( $columns );

		// Always include required columns.
		$required_columns = [ 'cb', 'attendee', 'order' ];
		$active_columns   = array_unique( array_merge( $required_columns, $active_columns ) );

		// Compute hidden columns and ensure required are never hidden.
		$hidden = array_diff( array_keys( $columns ), $active_columns );
		$hidden = array_diff( $hidden, $required_columns );

		$this->_column_headers = [ $columns, $hidden, $sortable ];

		// Sanitize status.
		$status = ! empty( $query_args['status'] )
			? sanitize_key( $query_args['status'] )
			: 'active';

		// Sanitize search.
		$search = ! empty( $query_args['s'] )
			? sanitize_text_field( wp_unslash( $query_args['s'] ) )
			: '';

		// Set the per page limit.
		if ( ! empty( $query_args['per_page'] ) ) {
			$per_page = absint( $query_args['per_page'] );

			if ( ! empty( $per_page ) ) {
				$this->per_page = $per_page;
			}
		}

		// Sanitize orderby..
		$orderby = ! empty( $query_args['orderby'] )
			? sanitize_key( $query_args['orderby'] )
			: 'id';

		// Sanitize order.
		$order = ! empty( $query_args['order'] )
			? sanitize_key( $query_args['order'] )
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
		if ( ! empty( $query_args['event_id'] ) ) {
			$args['event_id'] = absint( $query_args['event_id'] );
		}

		// Search.
		if ( ! empty( $search ) ) {

			$tickets = $this->sc_search_tickets( $search );

			if ( ! empty( $tickets ) ) {
				$args['id__in'] = $tickets;
			}
		}

		// Cast event ID.
		if ( ! empty( $query_args['event_id'] ) && empty( $args['event_id'] ) ) {
			$args['event_id'] = absint( $query_args['event_id'] );
		}

		// Sanitize orderby.
		if ( ! empty( $query_args['orderby'] ) ) {
			$args['orderby'] = sanitize_key( $query_args['orderby'] );
		} else {
			$args['orderby'] = 'date_created';
		}

		// Sanitize order.
		if ( ! empty( $query_args['order'] ) && in_array( $query_args['order'], [ 'asc', 'desc' ], true ) ) {
			$args['order'] = sanitize_key( $_GET['order'] );
		}

		// Query.
		$this->query = new Ticket_Query( $args );

		// Set items.
		$this->items = $this->query->items;

		$total_items = count_tickets( $args );
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

	public function display() {
		?>
		<table class="sce-admin__tickets-table wp-list-table widefat fixed striped">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>

			<tbody id="the-list">
			<?php $this->display_rows_or_placeholder(); ?>
			</tbody>
		</table>
		<?php
	}

}
