<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

/**
 * Whether a host's state still permits collecting registration answers.
 *
 * Applied only at the after-checkout render seams (TicketingReceipt,
 * RsvpAfterCheckout), never at SubmitEndpoint: refusing to print cannot
 * strand anyone, but refusing to accept could strand a visitor inside a
 * modal with no close button, backdrop click, or Escape.
 *
 * @since 3.13.0
 */
class HostState {

	/**
	 * The only post status that withdraws an event from collection.
	 *
	 * Deliberately narrow: a private or draft event may still legitimately have
	 * tickets out. Trash is the one status that unambiguously means "pulled".
	 *
	 * @since 3.13.0
	 */
	const WITHDRAWN_EVENT_STATUS = 'trash';

	/**
	 * The only order status that withdraws an order from collection.
	 *
	 * @since 3.13.0
	 */
	const WITHDRAWN_ORDER_STATUS = 'refunded';

	/**
	 * Whether an event's post state still permits printing.
	 *
	 * Fails open on an unresolvable post id, since this gate only exists to
	 * honour a status the organizer actually chose.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_post_id The event's WordPress post id (Event::$object_id),
	 *                           NOT the Sugar Calendar event id.
	 *
	 * @return bool
	 */
	public static function event_permits_printing( $event_post_id ) {

		$event_post_id = (int) $event_post_id;

		if ( $event_post_id <= 0 ) {
			return true;
		}

		$status = get_post_status( $event_post_id );

		if ( $status === false ) {
			return true;
		}

		return $status !== self::WITHDRAWN_EVENT_STATUS;
	}

	/**
	 * Whether an order's state still permits printing.
	 *
	 * A refunded buyer keeps their existing answers but is not asked for new
	 * ones, and is never forced to clear a modal to read their receipt. Fails
	 * open on an order with no status, same reasoning as event_permits_printing().
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $order The order object.
	 *
	 * @return bool
	 */
	public static function order_permits_printing( $order ) {

		if ( ! is_object( $order ) || ! isset( $order->status ) ) {
			return true;
		}

		return (string) $order->status !== self::WITHDRAWN_ORDER_STATUS;
	}
}
