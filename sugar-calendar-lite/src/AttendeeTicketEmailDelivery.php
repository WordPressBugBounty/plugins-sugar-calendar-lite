<?php

namespace Sugar_Calendar;

use Sugar_Calendar\Emails\Ticketing\AttendeeTicketEmail;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_tickets;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\send_ticket_email;

/**
 * Sends persisted tickets for a completed native order to their attendees.
 *
 * @since 3.14.0
 */
class AttendeeTicketEmailDelivery {

	/**
	 * Attempt delivery for every ticket belonging to an order.
	 *
	 * @since 3.14.0
	 *
	 * @param int $order_id Order ID.
	 */
	public static function send_for_order( $order_id ) {

		$order_id = absint( $order_id );

		if ( empty( $order_id ) ) {
			return;
		}

		if ( ! EmailStatus::is_enabled( AttendeeTicketEmail::ID ) ) {
			return;
		}

		$tickets = get_tickets(
			[
				'order_id' => $order_id,
				'number'   => 0,
				'orderby'  => 'id',
				'order'    => 'ASC',
			]
		);

		foreach ( $tickets as $ticket ) {
			if ( empty( $ticket->id ) ) {
				continue;
			}

			send_ticket_email( (int) $ticket->id );
		}
	}
}
