<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\RespondentNaming;
use Sugar_Calendar_Event_Ticketing\Features\TicketType;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\display_price;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order_tickets;

/**
 * Builds and renders the after-checkout modal's summary column.
 *
 * The design puts an Event Summary card, and for a ticketing order an order
 * card, beside the answer form. Both are read-only recaps of state the host
 * already resolved, so this class only formats — it resolves nothing itself
 * beyond the ticket type a line item names.
 *
 * The build side is host-specific (for_order() / for_event()) and the render
 * side is not, so a host that has no money to show still gets the same column.
 *
 * @since 3.13.0
 */
class AfterCheckoutSummary {

	/**
	 * The order status that earns the success heading.
	 *
	 * A pending order can reach the receipt page (HostState only withholds a
	 * refunded one), and "Order Successful!" would be a claim about money that
	 * has not arrived.
	 *
	 * @since 3.13.0
	 */
	const PAID_STATUS = 'paid';

	/**
	 * The ticketing helpers this class formats through.
	 *
	 * Checked with function_exists() before use: the add-on's functions are
	 * imported by short name above, so the check needs their real names.
	 *
	 * @since 3.13.0
	 */
	const TICKETING_NAMESPACE = 'Sugar_Calendar\AddOn\Ticketing\Common\Functions\\';

	/**
	 * The summary for a ticketing order.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $order The order.
	 * @param mixed $event The event the receipt page is rendering, so the date and
	 *                     time shown match the receipt around the modal.
	 *
	 * @return array The render() shape.
	 */
	public static function for_order( $order, $event ) {

		return [
			'event'  => self::event_rows( $event ),
			'status' => self::order_card( $order, $event ),
		];
	}

	/**
	 * The summary for a host with no order, e.g. RSVP.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $event The event.
	 *
	 * @return array The render() shape.
	 */
	public static function for_event( $event ) {

		return [
			'event'  => self::event_rows( $event ),
			'status' => null,
		];
	}

	/**
	 * Render the column, or nothing when there is nothing to recap.
	 *
	 * @since 3.13.0
	 *
	 * @param array $summary A for_order() / for_event() array.
	 *
	 * @return string
	 */
	public static function render( array $summary ) {

		$event  = isset( $summary['event'] ) ? (array) $summary['event'] : [];
		$status = isset( $summary['status'] ) && is_array( $summary['status'] ) ? $summary['status'] : null;

		if ( $event === [] && $status === null ) {
			return '';
		}

		ob_start();
		?>
		<aside class="sc-regform__summary">
			<?php
			if ( $event !== [] ) {
				self::render_card( __( 'Event Summary', 'sugar-calendar-lite' ), $event );
			}

			if ( $status !== null ) {
				self::render_card( (string) $status['label'], (array) $status['rows'], ! empty( $status['success'] ) );
			}
			?>
		</aside>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render one card: a heading and its label/value rows.
	 *
	 * A <dl> rather than a table: these are name/value pairs, and the design's
	 * two columns are a flex row per pair, not a grid the browser has to measure.
	 *
	 * @since 3.13.0
	 *
	 * @param string  $heading Card heading.
	 * @param array[] $rows    Each entry: [ 'label' => string, 'value' => string ].
	 * @param bool    $success Whether the heading carries the success check.
	 */
	private static function render_card( $heading, array $rows, $success = false ) {

		?>
		<section class="sc-regform__summary-card">
			<h4 class="sc-regform__summary-heading">
				<?php if ( $success ) : ?>
					<span class="sc-regform__summary-check" aria-hidden="true"></span>
				<?php endif; ?>
				<?php echo esc_html( $heading ); ?>
			</h4>

			<?php if ( $rows !== [] ) : ?>
				<dl class="sc-regform__summary-rows">
					<?php foreach ( $rows as $row ) : ?>
						<div class="sc-regform__summary-row">
							<dt class="sc-regform__summary-label"><?php echo esc_html( (string) $row['label'] ); ?></dt>
							<dd class="sc-regform__summary-value"><?php echo esc_html( (string) $row['value'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * The Event Summary rows: title, date, time.
	 *
	 * A row whose value cannot be resolved is dropped rather than shown empty.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $event The event.
	 *
	 * @return array[]
	 */
	private static function event_rows( $event ) {

		if ( ! is_object( $event ) || empty( $event->id ) ) {
			return [];
		}

		$values = [
			__( 'Event', 'sugar-calendar-lite' ) => isset( $event->title ) ? trim( (string) $event->title ) : '',
			__( 'Date', 'sugar-calendar-lite' )  => self::event_date( $event ),
			__( 'Time', 'sugar-calendar-lite' )  => self::event_time( $event ),
		];

		$rows = [];

		foreach ( $values as $label => $value ) {
			if ( $value !== '' ) {
				$rows[] = [
					'label' => $label,
					'value' => $value,
				];
			}
		}

		return $rows;
	}

	/**
	 * The event's start date, in the site's event date format.
	 *
	 * Formatted exactly as the receipt page around the modal does (see
	 * render_receipt_body()), so the two can't disagree by a timezone.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $event The event.
	 *
	 * @return string
	 */
	private static function event_date( $event ) {

		if ( empty( $event->start ) || $event->is_empty_date( $event->start ) ) {
			return '';
		}

		return (string) $event->format_date( sc_get_date_format(), $event->start );
	}

	/**
	 * The event's time range, or the all-day label.
	 *
	 * Built here rather than through Event::get_event_time(), which returns
	 * markup for the timezone-annotated cases; these values are escaped as text.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $event The event.
	 *
	 * @return string
	 */
	private static function event_time( $event ) {

		if ( empty( $event->start ) || $event->is_empty_date( $event->start ) ) {
			return '';
		}

		if ( $event->is_all_day() ) {
			return __( 'All-day', 'sugar-calendar-lite' );
		}

		$format = sc_get_time_format();
		$start  = (string) $event->format_date( $format, $event->start );

		if ( empty( $event->end ) || $event->is_empty_date( $event->end ) ) {
			return $start;
		}

		$end = (string) $event->format_date( $format, $event->end );

		return $end === $start ? $start : $start . ' - ' . $end;
	}

	/**
	 * The order card: one row per ticket type, then what was paid.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $order The order.
	 * @param mixed $event The event.
	 *
	 * @return array|null Null when the order carries no total to report.
	 */
	private static function order_card( $order, $event ) {

		if ( ! is_object( $order ) || ! isset( $order->total ) || ! function_exists( self::TICKETING_NAMESPACE . 'display_price' ) ) {
			return null;
		}

		$paid = isset( $order->status ) && (string) $order->status === self::PAID_STATUS;
		$rows = self::line_items( $order, $event );

		$rows[] = [
			'label' => $paid ? __( 'Paid', 'sugar-calendar-lite' ) : __( 'Total', 'sugar-calendar-lite' ),
			'value' => display_price( $order->total ),
		];

		return [
			'label'   => $paid
				? __( 'Order Successful!', 'sugar-calendar-lite' )
				: __( 'Order Summary', 'sugar-calendar-lite' ),
			'success' => $paid,
			'rows'    => $rows,
		];
	}

	/**
	 * One row per ticket type on the order: its name, unit price and quantity.
	 *
	 * The tickets table stores no price, so the unit price comes from the ticket
	 * type (event meta for general admission). A type whose price cannot be
	 * resolved shows the quantity alone rather than a wrong amount.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $order The order.
	 * @param mixed $event The event.
	 *
	 * @return array[]
	 */
	private static function line_items( $order, $event ) {

		$order_id = isset( $order->id ) ? (int) $order->id : 0;

		if ( $order_id <= 0 || ! function_exists( self::TICKETING_NAMESPACE . 'get_order_tickets' ) ) {
			return [];
		}

		$quantities = [];

		foreach ( (array) get_order_tickets( $order_id ) as $ticket ) {
			$type_id = isset( $ticket->ticket_type_id ) ? (int) $ticket->ticket_type_id : 0;

			$quantities[ $type_id ] = isset( $quantities[ $type_id ] ) ? $quantities[ $type_id ] + 1 : 1;
		}

		$event_id = isset( $event->id ) ? (int) $event->id : (int) ( $order->event_id ?? 0 );
		$rows     = [];

		foreach ( $quantities as $type_id => $quantity ) {

			$type = self::ticket_type( $event_id, $type_id );

			$rows[] = [
				'label' => $type['name'],
				'value' => self::line_item_value( $type['price'], $quantity ),
			];
		}

		return $rows;
	}

	/**
	 * One line item's right-hand side: unit price by quantity.
	 *
	 * @since 3.13.0
	 *
	 * @param float|null $price    The unit price, or null when it cannot be resolved.
	 * @param int        $quantity How many of this type are on the order.
	 *
	 * @return string
	 */
	private static function line_item_value( $price, $quantity ) {

		if ( $price === null ) {
			return sprintf(
				/* translators: %d - the number of tickets of one type on the order. */
				__( '× %d', 'sugar-calendar-lite' ),
				$quantity
			);
		}

		return sprintf(
			/* translators: %1$s - a ticket's price, %2$d - how many were bought. */
			__( '%1$s × %2$d', 'sugar-calendar-lite' ),
			display_price( $price ),
			$quantity
		);
	}

	/**
	 * A ticket type's display name and unit price.
	 *
	 * Type 0 is general admission, which lives in event meta rather than the
	 * ticket-types table. Named types are read behind class_exists(), since they
	 * are an add-on feature; without it the row still renders, priceless.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 * @param int $type_id  Ticket type id.
	 *
	 * @return array [ 'name' => string, 'price' => float|null ].
	 */
	private static function ticket_type( $event_id, $type_id ) {

		$general = self::general_admission( $event_id );

		if ( $type_id <= 0 ) {
			return $general;
		}

		if ( ! class_exists( TicketType::class ) ) {
			return $general;
		}

		$ticket_types = new TicketType();
		$ticket_type  = $ticket_types->get( $type_id );

		if ( empty( $ticket_type ) ) {
			return $general;
		}

		$name = isset( $ticket_type->ticket_name ) ? trim( (string) $ticket_type->ticket_name ) : '';

		return [
			'name'  => $name === '' ? $general['name'] : $name,
			'price' => isset( $ticket_type->ticket_price ) ? (float) $ticket_type->ticket_price : null,
		];
	}

	/**
	 * General admission's name and price, which live in event meta.
	 *
	 * Also the name fallback for a named type that no longer resolves, so the row
	 * reads as a ticket rather than as a blank.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 *
	 * @return array [ 'name' => string, 'price' => float|null ].
	 */
	private static function general_admission( $event_id ) {

		return [
			'name'  => RespondentNaming::general_admission( $event_id ),
			'price' => $event_id > 0 ? (float) get_event_meta( $event_id, 'ticket_price', true ) : null,
		];
	}
}
