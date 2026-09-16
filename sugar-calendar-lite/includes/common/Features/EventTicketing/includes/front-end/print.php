<?php
namespace Sugar_Calendar\AddOn\Ticketing\Frontend\Print_View;

/**
 * Print View.
 *
 * @since 1.0.0
 */

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
use Sugar_Calendar\AddOn\Ticketing\Frontend\DetailsCard;
use Sugar_Calendar\AddOn\Ticketing\Frontend\DetailsCardData;
use Sugar_Calendar\AddOn\Ticketing\Frontend\TicketCard;
use Sugar_Calendar\AddOn\Ticketing\Settings;

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

	// Bail if no ticket code
	if ( empty( $_GET['ticket_code'] ) ) {
		wp_die( esc_html__( 'That ticket could not be found.', 'sugar-calendar-lite' ) );
	}

	$code   = sanitize_text_field( wp_unslash( $_GET['ticket_code'] ) );
	$ticket = Functions\get_ticket_by_code( $code );

	// Bail if no ticket with that code
	if ( empty( $ticket ) ) {
		wp_die( esc_html__( 'That ticket could not be found.', 'sugar-calendar-lite' ) );
	}

	// Links sent before the order id was added carry only the code, so the
	// pair is checked only when an order id is present.
	if ( ! empty( $_GET['order_id'] ) && (int) $ticket->order_id !== absint( $_GET['order_id'] ) ) {
		wp_die( esc_html__( 'Invalid ticket or order.', 'sugar-calendar-lite' ) );
	}

	// Get event
	$event = sugar_calendar_get_event( $ticket->event_id );

	// Bail if no event
	if ( empty( $event ) ) {
		wp_die( esc_html__( 'That event could not be found.', 'sugar-calendar-lite' ) );
	}

	$order = Functions\get_order( $ticket->order_id );

	if ( Settings\use_legacy_template() ) {
		legacy_document( $ticket, $order, $event );
	} else {
		card_document( $ticket, $order, $event );
	}

	exit;
}

/**
 * Print the ticket as the details card.
 *
 * The card is the same one the ticket page draws, minus the two links, which
 * the `print` context drops. `wp_head()` runs so the theme's fonts load and the
 * type matches the screen.
 *
 * @since 3.14.0
 *
 * @param object $ticket The ticket.
 * @param object $order  The ticket's order.
 * @param object $event  The ticket's event.
 */
function card_document( $ticket, $order, $event ) {

	add_action(
		'wp_enqueue_scripts',
		function () {
			wp_enqueue_style( 'sugar-calendar-frontend-tailwind' );
		},
		20
	);
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php
		if ( ! current_theme_supports( 'title-tag' ) ) {
			echo '<title>' . esc_html( $event->title ) . '</title>';
		}

		wp_head();
		?>
	</head>
	<?php // The scope class must sit ABOVE the card: the print rules reach the card through it. ?>
	<body class="sugar-calendar-scope sc-ticket-print" onload="window.print()" style="<?php echo esc_attr( TicketCard::css_vars() ); ?>">
		<div class="scf:py-8 scf:px-4 scf:flex scf:justify-center scf:items-start sc-reset">
			<?php
			echo DetailsCard::render( DetailsCardData::from_ticket( $order, $ticket, $event, 'print' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built.
			?>
		</div>
		<?php wp_footer(); ?>
	</body>
	</html>
	<?php
}

/**
 * Print the ticket as the pre-3.14 table.
 *
 * Kept verbatim for sites running the legacy template.
 *
 * @since 3.14.0
 *
 * @param object $ticket The ticket.
 * @param object $order  The ticket's order.
 * @param object $event  The ticket's event.
 */
function legacy_document( $ticket, $order, $event ) {

	$start_date = $event->format_date( sc_get_date_format(), $event->start );
	$start_time = Functions\get_event_start_time( $event );

	$attendee = ! empty( $ticket->attendee_id )
		? Functions\get_attendee( $ticket->attendee_id )
		: false;
	?>

	<head>
		<link rel="stylesheet" href="<?php echo SC_PLUGIN_URL . 'assets/css/features/event-ticketing/frontend/print.css'; ?>" />
	</head>
	<body onload="window.print()">
		<div id="main">
			<h1><?php echo esc_html( $event->title ); ?></h1>
			<h2><?php echo esc_html( Functions\format_event_when( $start_date, $start_time ) ); ?></h2>
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
					<td colspan="2"><?php echo esc_html( get_event_meta( $event->id, 'location', true ) ); ?></td>
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
					<td colspan="3"><?php echo esc_url( get_permalink( $event->object_id ) ); ?></td>
				</tr>
			</table>
		</div>
	</body>
	<?php
}
