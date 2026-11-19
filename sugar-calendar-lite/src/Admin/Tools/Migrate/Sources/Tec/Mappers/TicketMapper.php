<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers;

use Sugar_Calendar\Admin\Tools\Importers\TicketCapacity;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecTicket;

/**
 * Turns a TEC ticket into the ticket type the Event Ticketing add-on creates.
 *
 * @since 3.18.0
 */
class TicketMapper {

	/**
	 * The ticket type fields: `name`, `price`, `limit_capacity` (0 or 1) and `quantity`.
	 *
	 * @since 3.18.0
	 *
	 * @param TecTicket $ticket The TEC ticket.
	 *
	 * @return array
	 */
	public static function to_ticket_type( TecTicket $ticket ) {

		return array_merge(
			[
				'name'  => $ticket->get_name(),
				'price' => (float) $ticket->get_price(),
			],
			TicketCapacity::get( $ticket->get_capacity() )
		);
	}
}
