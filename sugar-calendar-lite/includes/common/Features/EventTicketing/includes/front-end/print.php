<?php
namespace Sugar_Calendar\AddOn\Ticketing\Frontend\Print_View;

/**
 * Print View.
 *
 * @since 1.0.0
 */

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Render the ticket print view
 *
 * @since 1.0.0
 */
function output() {

	// Bail if not print request
	if ( empty( $_GET['sc_et_action'] ) || ( 'print' !== $_GET['sc_et_action'] ) ) {
		return;
	}

	// Bail if no nonce
	if ( empty( $_GET[ '_wpnonce' ] ) ) {
		return;
	}

	// Default to no ticket
	$ticket = false;

	// Get ticket by code
	if ( ! empty( $_GET['ticket_code'] ) ) {
		$code   = sanitize_text_field( $_GET['ticket_code'] );
		$ticket = Functions\get_ticket_by_code( $code );

	// Get ticket by ID
	} elseif ( ! empty( $_GET['ticket_id'] ) ) {
		$id     = absint( $_GET['ticket_id'] );
		$ticket = Functions\get_ticket( $id );
	}

	// Bail if no ticket with that code
	if ( empty( $ticket ) ) {
		wp_die( esc_html__( 'That ticket could not be found.', 'sugar-calendar-lite' ) );
	}

	// Bail if nonce failed
	if ( ! wp_verify_nonce( $_GET[ '_wpnonce' ], $ticket->code ) ) {
		wp_die( esc_html__( 'This URL has expired. Please refresh and try again.', 'sugar-calendar-lite' ) );
	}

	// Get event
	$event = sugar_calendar_get_event( $ticket->event_id );

	// Bail if no event
	if ( empty( $event ) ) {
		wp_die( esc_html__( 'That event could not be found.', 'sugar-calendar-lite' ) );
	}

	$start_date = $event->format_date( sc_get_date_format(), $event->start );
	$start_time = $event->format_date( sc_get_time_format(), $event->start );

	$order = Functions\get_order( $ticket->order_id );

	$attendee = ! empty( $ticket->attendee_id )
		? Functions\get_attendee( $ticket->attendee_id )
		: false; ?>

	<head>
		<link rel="stylesheet" href="<?php echo SC_ET_PLUGIN_URL . 'includes/frontend/assets/css/print.css'; ?>" />
	</head>
	<body onload="window.print()">
		<div id="main">
			<h1><?php echo esc_html( $event->title ); ?></h1>
			<h2><?php printf( esc_html__( '%s at %s', 'sugar-calendar-lite' ), $start_date, $start_time ); ?></h2>
			<table>
				<tr>
					<th><?php esc_html_e( 'Ticket #', 'sugar-calendar-lite' ); ?></th>
					<th><?php esc_html_e( 'Purchaser', 'sugar-calendar-lite' ); ?></th>
					<th><?php esc_html_e( 'Code', 'sugar-calendar-lite' ); ?></th>
				</tr>
				<tr>
					<td><?php echo esc_html( $ticket->id ); ?></td>
					<td><?php echo esc_html( $order->first_name . ' ' . $order->last_name ); ?></td>
					<td><?php echo esc_html( $ticket->code ); ?></td>
				</tr>
				<tr>
					<th colspan="2"><?php esc_html_e( 'Location', 'sugar-calendar-lite' ); ?></th>
					<th></th>
				</tr>
				<tr>
					<td colspan="2"><?php echo get_event_meta( $event->id, 'location', true ); ?></td>
					<td></td>
				</tr>
				<?php if ( ! empty( $attendee ) ) : ?>
					<tr>
						<th colspan="2"><?php esc_html_e( 'Attendee', 'sugar-calendar-lite' ); ?></th>
					</tr>
					<tr>
						<td colspan="2"><?php echo esc_html( $attendee->first_name . ' ' . $attendee->last_name ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th colspan="3"><?php esc_html_e( 'URL', 'sugar-calendar-lite' ); ?></th>
				</tr>
				<tr>
					<td colspan="3"><?php echo get_permalink( $event->object_id ); ?></td>
				</tr>
			</table>
		</div>
	</body>

	<?php
	exit;
}
