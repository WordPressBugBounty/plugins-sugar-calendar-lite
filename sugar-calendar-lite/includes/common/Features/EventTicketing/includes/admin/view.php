<?php
namespace Sugar_Calendar\AddOn\Ticketing\Admin;
/**
 * Order View.
 *
 * @since 1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Settings as Settings;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
use Sugar_Calendar\AddOn\Ticketing\Gateways as Gateways;

/**
 * Render the order details page
 *
 * @since 1.0.0
 *
 * @param int $order_id ID of the Order
 */
function view( $order_id = 0 ) {

	// Order
	$order = Functions\get_order( $order_id );

	// Bail if no order
	if ( empty( $order ) ) {
		wp_die( esc_html__( 'This URL has expired. Please refresh and try again.', 'sugar-calendar-lite' ) );
	}

	// Transactions may be empty
	$transaction = ! empty( $order->transaction_id )
		? $order->transaction_id
		: '&mdash;';

	// Stripe URL
	$sandbox     = (bool) Settings\get_setting( 'sandbox' );
	$test        = $sandbox ? 'test/' : '';
	$payment_url = 'https://dashboard.stripe.com/' . $test . 'payments/' . $order->transaction_id;

	// Form action
	$form_action = add_query_arg(
		array(
			'page'     => 'sc-event-ticketing',
			'order_id' => $order_id
		),
		admin_url( 'admin.php' )
	);

	// Event
	$event  = sugar_calendar_get_event( $order->event_id );
	$format = sc_get_date_format() . ' @ ' . sc_get_time_format();

	// Tickets
	$tickets = Functions\get_order_tickets( $order->id ); ?>

	<div class="wrap">
		<h2><?php esc_html_e( 'Event Ticket Order Details', 'sugar-calendar-lite' ); ?></h2>

		<div id="sc-item-card-wrapper">

			<?php do_action( 'sc_et_admin_order_top', $order ); ?>

			<div class="info-wrapper item-section">

				<form id="edit-item-info" method="post" action="<?php echo esc_url( $form_action ); ?>">

					<div class="item-info">

						<table class="widefat striped">
							<tbody>
								<tr>
									<td class="row-title">
										<label><?php esc_html_e( 'Order ID:', 'sugar-calendar-lite' ); ?></label>
									</td>
									<td>
										<?php echo esc_html( $order->id ); ?>
									</td>
								</tr>
								<tr>
									<td class="row-title">
										<label><?php esc_html_e( 'Transaction ID:', 'sugar-calendar-lite' ); ?></label>
									</td>
									<td>
										<a href="<?php echo esc_url( $payment_url ); ?>" target="_blank"><?php echo esc_html( $transaction ); ?></a>
									</td>
								</tr>
								<tr>
									<td class="row-title">
										<label><?php esc_html_e( 'Purchase Date:', 'sugar-calendar-lite' ); ?></label>
									</td>
									<td>
										<?php echo esc_html( $order->date_created ); ?>
									</td>
								</tr>
								<tr>
									<td class="row-title">
										<label><?php esc_html_e( 'Customer:', 'sugar-calendar-lite' ); ?></label>
									</td>
									<td>
										<?php echo esc_html( $order->first_name . ' ' . $order->last_name ) . ' (' . make_clickable( $order->email ) . ')'; ?>
									</td>
								</tr>
								<tr>
									<td class="row-title">
										<label><?php esc_html_e( 'Total:', 'sugar-calendar-lite' ); ?></label>
									</td>
									<td>
										<?php echo Functions\currency_filter( $order->total ); ?>
									</td>
								</tr>
								<tr>
									<td class="row-title">
										<label><?php esc_html_e( 'Status:', 'sugar-calendar-lite' ); ?></label>
									</td>
									<td>
										<select name="status" id="sc-et-status">
											<option value="pending"<?php selected( $order->status, 'pending' ); ?>><?php echo Functions\order_status_label( 'pending' ); ?></option>
											<option value="paid"<?php selected( $order->status, 'paid' ); ?>><?php echo Functions\order_status_label( 'paid' ); ?></option>
											<option value="refunded"<?php selected( $order->status, 'refunded' ); ?>><?php echo Functions\order_status_label( 'refunded' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td class="row-title">
										<label><?php esc_html_e( 'Event:', 'sugar-calendar-lite' ); ?></label>
									</td>
									<td>
										<?php if ( ! empty( $event ) ) : ?>

											<a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $event->object_id ) ); ?>">
												<?php echo esc_html( $event->title ); ?>
											</a>

											&mdash;

											<?php echo esc_html( $event->format_date( $format, strtotime( $order->event_date ) ) ); ?>

										<?php else : ?>

											&mdash;

										<?php endif; ?>
									</td>
								</tr>

							</tbody>
						</table>

					</div>

					<div id="item-edit-actions" class="edit-item">
						<?php wp_nonce_field( 'sc_event_tickets', 'sc_event_tickets_nonce', false, true ); ?>

						<input type="hidden" name="order_id" value="<?php echo absint( $order->id ); ?>" />

						<input type="submit" name="sc_et_resend_receipt" class="sc-resend-order-receipt button" value="<?php esc_attr_e( 'Resend Email Receipt', 'sugar-calendar-lite' ); ?>" />

						<?php if ( current_user_can( 'manage_options' ) ) : ?>

							<input type="submit" name="sc_et_delete_order" class="sc-delete-order button" value="<?php esc_attr_e( 'Delete Order', 'sugar-calendar-lite' ); ?>" />
							<input type="submit" name="sc_et_update_order" class="button-primary button" value="<?php esc_attr_e( 'Update Order', 'sugar-calendar-lite' ); ?>" />

						<?php endif; ?>

					</div>
				</form>
			</div>

			<div id="item-tables-wrapper" class="item-section">

				<h3><?php esc_html_e( 'Tickets', 'sugar-calendar-lite' ); ?></h3>

				<?php do_action( 'sc_et_admin_order_before_tickets', $order ); ?>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'sugar-calendar-lite' ); ?></th>
							<th><?php esc_html_e( 'Code', 'sugar-calendar-lite' ); ?></th>
							<th><?php esc_html_e( 'Attendee', 'sugar-calendar-lite' ); ?></th>
						</tr>
					</thead>

					<tbody>

						<?php

						foreach ( $tickets as $ticket ) :

							// Get the attendee
							$attendee = Functions\get_attendee( $ticket->attendee_id );

							// Try to put the name together
							$fname    = ! empty( $attendee->first_name )
								? $attendee->first_name
								: '';
							$lname    = ! empty( $attendee->last_name )
								? $attendee->last_name
								: '';
							$name     = ! empty( $fname . $lname )
								? $fname . ' ' . $lname
								: '&mdash;';

							$print_url = wp_nonce_url(
								add_query_arg(
									array(
										'sc_et_action' => 'print',
										'ticket_code'  => $ticket->code
									),
									home_url()
								),
								$ticket->code
							);

							$email_url = wp_nonce_url(
								add_query_arg(
									array(
										'sc_et_action' => 'email_ticket',
										'ticket_code'  => $ticket->code
									)
								),
								$ticket->code
							);

							?>

							<tr>
								<td>
									<span class="row-title"><?php echo absint( $ticket->id ); ?></span>

									<div class="row-actions">
										<span class="print">
											<a href="<?php echo esc_url( $print_url ); ?>" target="_blank"><?php esc_html_e( 'Print', 'sugar-calendar-lite' ); ?></a>
										</span>

										<?php if ( ! empty( $attendee->email ) ) : ?>

											|

											<span class="email">
												<a href="<?php echo esc_url( $email_url ); ?>"><?php esc_html_e( 'Resend Email', 'sugar-calendar-lite' ); ?></a>
											</span>

										<?php endif; ?>

									</div>
								</td>

								<td>
									<code><?php echo esc_html( $ticket->code ); ?></code>
								</td>

								<td>
									<?php echo esc_html( $name ); ?>

									<?php

									if ( ! empty( $attendee->email ) ) :
										echo '<br>' . make_clickable( $attendee->email );
									endif;

									?>
								</td>
							</tr>

					<?php endforeach; ?>

					<?php do_action( 'sc_et_admin_order_ticket_list', $order ); ?>

					</tbody>
				</table>

				<?php do_action( 'sc_et_admin_order_after_tickets', $order ); ?>

			</div>

			<?php do_action( 'sc_et_admin_order_bottom', $order ); ?>

		</div>
	</div>

	<?php
}

/**
 * Update an order update request
 *
 * @since 1.0.0
 */
function update() {

	// Bail if not updating order
	if ( empty( $_POST['sc_et_update_order'] ) || empty( $_POST['sc_event_tickets_nonce'] ) ) {
		return;
	}

	// Bail if user cannot manage options
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have the necessary capabilities to modify orders.', 'sugar-calendar-lite' ), esc_html__( 'Error', 'sugar-calendar-lite' ), array( 'response' => 403 )  );
	}

	// Bail if nonce fails
	if ( ! wp_verify_nonce( $_POST['sc_event_tickets_nonce'], 'sc_event_tickets' ) ) {
		wp_die( esc_html__( 'This URL has expired. Please refresh and try again.', 'sugar-calendar-lite' ) );
	}

	// Order ID
	$order_id = ! empty( $_POST['order_id'] )
		? absint( $_POST['order_id'] )
		: 0;

	// Status
	$status = ! empty( $_POST['status'] )
		? sanitize_text_field( $_POST['status'] )
		: '';

	// Get the order
	$order  = Functions\get_order( $order_id );

	// Default notice ID
	$notice_id = 'order-update';

	// Order exists
	if ( ! empty( $order->id ) ) {

		// Do we need to refund in Stripe?
		if ( ( 'refunded' === $status ) && ( 'paid' === $order->status ) && ( false !== strpos( $order->transaction_id, 'pi_' ) ) ) {

			// Setup stripe
			$stripe = new Gateways\Stripe;

			// Load the SDK
			$stripe->load_sdk();

			// Refund payment in Stripe
			\Stripe\Refund::create(
				array(
					'payment_intent' => $order->transaction_id,
				)
			);

			// Update notice ID
			$notice_id = 'order-refund';
		}

		// New status
		$to_update = array(
			'status' => $status
		);

		// Update the order
		$notice_type = Functions\update_order( $order_id, $to_update )
			? 'updated'
			: 'error';
	}

	// Setup URL
	$url = add_query_arg(
		array(
			'page'           => 'sc-event-ticketing',
			'order_id'       => $order_id,
			'sc-notice-id'   => $notice_id,
			'sc-notice-type' => $notice_type
		),
		admin_url( 'admin.php' )
	);

	// Redirect
	wp_safe_redirect( $url );
	exit;
}

/**
 * Handle an order delete request
 *
 * @since 1.0.0
 */
function delete() {

	// Bail if not deleting order
	if ( empty( $_POST['sc_et_delete_order'] ) || empty( $_POST['sc_event_tickets_nonce'] ) ) {
		return;
	}

	// Bail if user cannot manage options
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have the necessary capabilities to delete orders.', 'sugar-calendar-lite' ), esc_html__( 'Error', 'sugar-calendar-lite' ), array( 'response' => 403 )  );
	}

	// Bail if nonce fails
	if ( ! wp_verify_nonce( $_POST['sc_event_tickets_nonce'], 'sc_event_tickets' ) ) {
		wp_die( esc_html__( 'This URL has expired. Please refresh and try again.', 'sugar-calendar-lite' ) );
	}

	// Get the order ID
	$order_id = ! empty( $_POST['order_id'] )
		? absint( $_POST['order_id'] )
		: 0;

	// Order ID
	if ( ! empty( $order_id ) ) {

		// Get tickets
		$tickets = Functions\get_order_tickets( $order_id );

		// Delete tickets
		if ( ! empty( $tickets ) ) {
			foreach ( $tickets as $ticket ) {
				Functions\delete_ticket( $ticket->id );
			}
		}

		// Delete the order
		$notice_type = Functions\delete_order( $order_id )
			? 'updated'
			: 'error';
	}

	// Setup URL
	$url = add_query_arg(
		array(
			'page'        => 'sc-event-ticketing',
			'tab'            => 'orders',
			'sc-notice-id'   => 'order-delete',
			'sc-notice-type' => $notice_type
		),
		admin_url( 'admin.php' )
	);

	// Redirect
	wp_safe_redirect( $url );
	exit;
}

/**
 * Handle a request to resent an email receipt
 *
 * @since 1.0.0
 */
function resend_receipt() {

	// Bail if not resending
	if ( empty( $_POST['sc_et_resend_receipt'] ) || empty( $_POST['sc_event_tickets_nonce'] ) ) {
		return;
	}

	// Bail if nonce fails
	if ( ! wp_verify_nonce( $_POST['sc_event_tickets_nonce'], 'sc_event_tickets' ) ) {
		wp_die( esc_html__( 'This URL has expired. Please refresh and try again.', 'sugar-calendar-lite' ) );
	}

	// Get order ID
	$order_id = ! empty( $_POST['order_id'] )
		? absint( $_POST['order_id'] )
		: 0;

	// Send receipt email
	if ( ! empty( $order_id ) ) {
		$notice_type = Functions\send_order_receipt_email( $order_id )
			? 'updated'
			: 'error';
	}

	// Setup URL
	$url = add_query_arg(
		array(
			'sc-notice-id'   => 'email-resend',
			'sc-notice-type' => $notice_type
		),
		wp_get_referer()
	);

	// Redirect
	wp_safe_redirect( $url );
	exit;
}
