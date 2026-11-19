<?php

namespace Sugar_Calendar\AddOn\Ticketing\Migrate;

use Sugar_Calendar\Admin\Tools\Importers\TicketCapacity;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_ticket;

/**
 * Writes the tickets, orders and seats importers bring across.
 *
 * @since 3.18.0
 */
class TicketingWriter {

	/**
	 * Give an event its own ticket.
	 *
	 * @since 3.18.0
	 *
	 * @param int          $sc_event_id     The Sugar Calendar event ID.
	 * @param string|float $ticket_price    The price of the ticket.
	 * @param mixed        $ticket_capacity The capacity of the ticket, -1 for unlimited.
	 */
	public function set_event_ticket( $sc_event_id, $ticket_price, $ticket_capacity ) {

		$capacity = TicketCapacity::get( $ticket_capacity );

		update_event_meta( $sc_event_id, 'ticket_limit_capacity', $capacity['limit_capacity'] );
		update_event_meta( $sc_event_id, 'tickets', 1 );
		update_event_meta( $sc_event_id, 'ticket_price', $ticket_price );
		update_event_meta( $sc_event_id, 'ticket_quantity', $capacity['quantity'] );
	}

	/**
	 * Add an order.
	 *
	 * @since 3.18.0
	 *
	 * @param array $data Order data.
	 *
	 * @return int The order ID.
	 */
	public function add_order( $data ) {

		return add_order( $data );
	}

	/**
	 * Add a seat, the `sc_tickets` row of one attendee.
	 *
	 * @since 3.18.0
	 *
	 * @param array $data Ticket data.
	 *
	 * @return int|false The ticket ID, or false on failure.
	 */
	public function add_seat( $data ) {

		return add_ticket( $data );
	}
}
