<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

/**
 * The one rule turning an imported ticket capacity into Sugar Calendar's limit and quantity.
 *
 * @since 3.18.0
 */
class TicketCapacity {

	/**
	 * Get the limit flag and quantity for a capacity.
	 *
	 * Checkout enforces the quantity only with the limit on, so -1 (unlimited) and 0 leave it off.
	 *
	 * @since 3.18.0
	 *
	 * @param mixed $capacity Ticket capacity, -1 for unlimited.
	 *
	 * @return array `limit_capacity` (0 or 1) and `quantity`.
	 */
	public static function get( $capacity ) {

		$capacity = (int) $capacity;

		if ( $capacity === -1 ) {
			$quantity = 9999;
		} else {
			$quantity = max( 0, $capacity );
		}

		return [
			'limit_capacity' => $capacity > 0 ? 1 : 0,
			'quantity'       => $quantity,
		];
	}
}
