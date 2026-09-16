<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Event;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\display_price;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendees;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_email_tag_receipt_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_email_tag_ticket_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_ticket_print_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_tickets;

/**
 * Build the query-complete context for an order email.
 *
 * @since 3.14.0
 */
class OrderContext {

	/**
	 * Resolve one saved order and all of its tickets.
	 *
	 * @since 3.14.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array|null
	 */
	public static function from_order( int $order_id ): ?array {

		$order = get_order( $order_id );

		if ( empty( $order ) || empty( $order->id ) ) {
			return null;
		}

		$event_values = self::event_values( $order );

		if ( $event_values === null ) {
			return null;
		}

		$values      = self::prepare_values( $order, $event_values );
		$receipt_url = get_email_tag_receipt_url( (int) $order->id );

		return self::build( $values, $receipt_url );
	}

	/**
	 * Resolve one saved order for a read-only preview.
	 *
	 * @since 3.14.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array|null
	 */
	public static function from_order_for_preview( int $order_id ): ?array {

		$order = get_order( $order_id );

		if ( empty( $order ) || empty( $order->id ) ) {
			return null;
		}

		$event_values = self::event_values( $order );

		if ( $event_values === null ) {
			return null;
		}

		$values      = self::prepare_values( $order, $event_values );
		$receipt_url = PreviewReceipt::url( (int) $order->id );

		if ( $receipt_url === '' ) {
			return null;
		}

		return self::build( $values, $receipt_url );
	}

	/**
	 * Resolve one order into administrator email values.
	 *
	 * @since 3.14.0
	 *
	 * @param int  $order_id        Order id.
	 * @param bool $preview         Whether this is a preview.
	 * @param bool $include_receipt Whether to include a receipt URL.
	 *
	 * @return array|null
	 */
	public static function from_order_for_admin( int $order_id, bool $preview = false, bool $include_receipt = false ): ?array {

		$order = get_order( $order_id );

		if ( empty( $order ) || empty( $order->id ) ) {
			return null;
		}

		$event        = sugar_calendar_get_event( (int) $order->event_id );
		$event_values = null;

		if ( empty( $event ) || empty( $event->id ) ) {
			$event_values = [
				'context' => EventContext::unavailable( (int) $order->event_id ),
				'event'   => null,
			];
		} else {
			$original_event_id = (int) $event->id;
			$resolved_event    = EventContext::resolve( $event, $order );

			if ( $resolved_event instanceof Event ) {
				$context = EventContext::from_event_for_admin(
					$resolved_event,
					! empty( $order->occurrence_id ) ? (int) $order->occurrence_id : 0
				);

				if ( $context !== null ) {
					$context['event']['sc_event_id'] = $original_event_id;
					$event_values                     = [
						'context' => $context,
						'event'   => $resolved_event,
					];
				}
			}

			if ( $event_values === null ) {
				$event_values = [
					'context' => EventContext::unavailable( $original_event_id ),
					'event'   => null,
				];
			}
		}

		$values      = self::prepare_values( $order, $event_values, true );
		$dates       = self::order_datetime( $order->date_created );
		$receipt_url = '';

		if ( $include_receipt && $event_values['event'] instanceof Event ) {
			$receipt_url = $preview
				? PreviewReceipt::url( (int) $order->id )
				: get_email_tag_receipt_url( (int) $order->id );
		}

		$values['order']['date_only'] = $dates['date'];
		$values['order']['time']      = $dates['time'];
		$values['order']['admin_url'] = add_query_arg(
			[
				'page'     => 'sc-event-ticketing',
				'order_id' => (int) $order->id,
			],
			admin_url( 'admin.php' )
		);

		return self::build( $values, (string) $receipt_url );
	}

	/**
	 * Resolve every context value before issuing a receipt URL.
	 *
	 * @since 3.14.0
	 *
	 * @param object $order        Order row.
	 * @param array  $event_values Resolved event values.
	 * @param bool   $admin        Whether to prepare administrator values.
	 *
	 * @return array
	 */
	private static function prepare_values( $order, array $event_values, bool $admin = false ): array {

		$tickets = self::tickets( $order, $event_values['event'], $admin );

		return [
			'context'   => $event_values['context'],
			'purchaser' => [
				'name'  => trim( $order->first_name . ' ' . $order->last_name ),
				'email' => (string) $order->email,
			],
			'order'     => [
				'id'             => (int) $order->id,
				'date'           => self::order_date( $order->date_created ),
				'transaction_id' => (string) $order->transaction_id,
				'amount_paid'    => [
					'label' => __( 'Amount Paid', 'sugar-calendar-lite' ),
					'value' => display_price( $order->total ),
				],
			],
			'tickets'   => $tickets,
		];
	}

	/**
	 * Assemble prepared values with the selected receipt URL.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $values      Prepared context values.
	 * @param string $receipt_url Receipt URL.
	 *
	 * @return array
	 */
	private static function build( array $values, string $receipt_url ): array {

		$values['order']['receipt_url'] = $receipt_url;

		return array_merge(
			$values['context'],
			[
				'purchaser' => $values['purchaser'],
				'order'     => $values['order'],
				'tickets'   => $values['tickets'],
			]
		);
	}

	/**
	 * Format a valid stored MySQL datetime as date and time without normalizing
	 * legacy bad data.
	 *
	 * The stored clock is shown as-is, which is what the admin Orders list does.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $date Stored order datetime.
	 *
	 * @return string
	 */
	private static function order_date( $date ): string {

		return self::order_datetime( $date )['combined'];
	}

	/**
	 * Format a valid stored MySQL datetime as separate date and time values.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $date Stored order datetime.
	 *
	 * @return array
	 */
	private static function order_datetime( $date ): array {

		$empty = [
			'date'     => '',
			'time'     => '',
			'combined' => '',
		];

		if ( ! is_scalar( $date ) ) {
			return $empty;
		}

		$date = (string) $date;

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/D', $date, $parts ) ) {
			return $empty;
		}

		if (
			! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] )
			|| (int) $parts[4] > 23
			|| (int) $parts[5] > 59
			|| (int) $parts[6] > 59
		) {
			return $empty;
		}

		$formatted_date = mysql2date( sc_get_date_format(), $date );
		$formatted_time = mysql2date( sc_get_time_format(), $date );

		if ( ! is_string( $formatted_date ) || ! is_string( $formatted_time ) ) {
			return $empty;
		}

		return [
			'date'     => $formatted_date,
			'time'     => $formatted_time,
			/* translators: 1: Purchase date. 2: Purchase time. */
			'combined' => sprintf( __( '%1$s at %2$s', 'sugar-calendar-lite' ), $formatted_date, $formatted_time ),
		];
	}

	/**
	 * Resolve recurrence-aware event context once.
	 *
	 * @since 3.14.0
	 *
	 * @param object $order Order row.
	 *
	 * @return array|null
	 */
	private static function event_values( $order ): ?array {

		$event = sugar_calendar_get_event( (int) $order->event_id );

		if ( empty( $event ) || empty( $event->id ) ) {
			return null;
		}

		$resolved_event = EventContext::resolve( $event, $order );

		if ( $resolved_event === null ) {
			return null;
		}

		$context = EventContext::from_event(
			$resolved_event,
			! empty( $order->occurrence_id ) ? (int) $order->occurrence_id : 0
		);

		if ( $context === null ) {
			return null;
		}

		$context['event']['sc_event_id'] = (int) $event->id;

		return [
			'context' => $context,
			'event'   => $resolved_event,
		];
	}

	/**
	 * Resolve all order tickets from one bulk query.
	 *
	 * @since 3.14.0
	 *
	 * @param object     $order Order row.
	 * @param Event|null $event Resolved event.
	 * @param bool       $admin Whether to omit ticket credentials and costs.
	 *
	 * @return array
	 */
	private static function tickets( $order, ?Event $event, bool $admin = false ): array {

		$tickets   = get_tickets(
			[
				'order_id' => (int) $order->id,
				'number'   => PHP_INT_MAX,
				'order'    => 'ASC',
			]
		);
		$attendees = self::attendees_by_id( $tickets );
		$values    = [];

		foreach ( $tickets as $ticket ) {
			$attendee_id = ! empty( $ticket->attendee_id ) ? (int) $ticket->attendee_id : 0;
			$attendee    = $attendees[ $attendee_id ] ?? null;

			$values[] = [
				'id'        => (int) $ticket->id,
				'attendee'  => empty( $attendee ) ? '' : trim( $attendee->first_name . ' ' . $attendee->last_name ),
				'email'     => empty( $attendee ) ? '' : (string) $attendee->email,
				'type'      => self::ticket_type( $ticket, $event ),
				'code'      => (string) $ticket->code,
				'cost'      => $admin || ! ( $event instanceof Event ) ? '' : TicketContext::current_cost( $ticket, $event ),
				'url'       => $admin ? '' : get_email_tag_ticket_url( (int) $ticket->id ),
				'print_url' => $admin ? '' : get_ticket_print_url( $ticket ),
			];
		}

		return $values;
	}

	/**
	 * Deterministic multi-ticket sample for previews.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function fictional(): array {

		return array_merge(
			EventContext::fictional(),
			[
				'purchaser' => [
					'name'  => __( 'Grace Hopper', 'sugar-calendar-lite' ),
					'email' => 'grace@example.com',
				],
				'order'     => [
					'id'             => 517,
					'date'           => self::order_date( '2026-08-21 04:30:00' ),
					'transaction_id' => 'pi_3TJXIDCPzfP3USTq11hKfTzu',
					'amount_paid'    => [
						'label' => __( 'Amount Paid', 'sugar-calendar-lite' ),
						'value' => display_price( 42.17 ),
					],
					'receipt_url'    => home_url( '/receipt/?order=sample-order' ),
				],
				'tickets'   => [
					[
						'id'        => 101,
						'attendee'  => __( 'Ada Lovelace', 'sugar-calendar-lite' ),
						'email'     => 'ada@example.com',
						'type'      => __( 'General Admission', 'sugar-calendar-lite' ),
						'code'      => 'TKT-GENERAL-101',
						'cost'      => display_price( 25 ),
						'url'       => home_url( '/ticket-details/?order_id=517&ticket_code=TKT-GENERAL-101' ),
						'print_url' => home_url( '/?sc_et_action=print&order_id=517&ticket_code=TKT-GENERAL-101' ),
					],
					[
						'id'        => 102,
						'attendee'  => __( 'Katherine Johnson', 'sugar-calendar-lite' ),
						'email'     => 'katherine@example.com',
						'type'      => __( 'VIP Balcony', 'sugar-calendar-lite' ),
						'code'      => 'TKT-VIP-102',
						'cost'      => display_price( 35 ),
						'url'       => home_url( '/ticket-details/?order_id=517&ticket_code=TKT-VIP-102' ),
						'print_url' => home_url( '/?sc_et_action=print&order_id=517&ticket_code=TKT-VIP-102' ),
					],
				],
			]
		);
	}

	/**
	 * Deterministic administrator order values for previews.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function fictional_for_admin(): array {

		$values = self::fictional();
		$dates  = self::order_datetime( '2026-08-21 04:30:00' );

		$values['event']              = EventContext::fictional_for_admin()['event'];
		$values['order']['date_only'] = $dates['date'];
		$values['order']['time']      = $dates['time'];
		$values['order']['admin_url'] = admin_url( 'admin.php?page=sc-event-ticketing&order_id=517' );

		return $values;
	}

	/**
	 * Load attendees once and key them by id.
	 *
	 * @since 3.14.0
	 *
	 * @param array $tickets Ticket rows.
	 *
	 * @return array
	 */
	private static function attendees_by_id( array $tickets ): array {

		$ids = array_values( array_unique( array_filter( array_map( 'intval', wp_list_pluck( $tickets, 'attendee_id' ) ) ) ) );

		if ( empty( $ids ) ) {
			return [];
		}

		$attendees = get_attendees(
			[
				'id__in' => $ids,
				'number' => PHP_INT_MAX,
			]
		);
		$by_id     = [];

		foreach ( $attendees as $attendee ) {
			$by_id[ (int) $attendee->id ] = $attendee;
		}

		return $by_id;
	}

	/**
	 * Resolve a Lite-safe ticket type label.
	 *
	 * @since 3.14.0
	 *
	 * @param object $ticket Ticket row.
	 * @param Event|null $event Resolved event.
	 *
	 * @return string
	 */
	private static function ticket_type( $ticket, ?Event $event ): string {

		$default = __( 'General Admission', 'sugar-calendar-lite' );

		if ( ! ( $event instanceof Event ) ) {
			return $default;
		}

		/**
		 * Filters the ticket type label.
		 *
		 * @since 3.14.0
		 *
		 * @param string $type   Ticket type label.
		 * @param object $ticket Ticket row.
		 * @param Event  $event  Resolved event.
		 */
		$type = apply_filters( 'sugar_calendar_emails_ticket_type', $default, $ticket, $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return is_string( $type ) && $type !== '' ? $type : $default;
	}
}
