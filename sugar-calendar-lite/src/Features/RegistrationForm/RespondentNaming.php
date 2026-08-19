<?php

namespace Sugar_Calendar\Features\RegistrationForm;

/**
 * Display names for registration respondents.
 *
 * Holds only what more than one surface needs. The purchaser's name is built
 * from the order row by both the receipt and the admin order page, and the two
 * must agree; the attendee-name fallback has a single caller and deliberately
 * stays there.
 *
 * @since 3.13.0
 */
class RespondentNaming {

	/**
	 * The purchaser's name, from the order itself.
	 *
	 * The purchaser is not an attendee and has no attendee record, so the order
	 * row is the only source.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order The order.
	 *
	 * @return string Possibly empty.
	 */
	public static function purchaser( $order ) {

		return trim(
			sprintf(
				'%1$s %2$s',
				isset( $order->first_name ) ? (string) $order->first_name : '',
				isset( $order->last_name ) ? (string) $order->last_name : ''
			)
		);
	}

	/**
	 * What to call ticket type 0 — the event's unnamed, default ticket.
	 *
	 * Type 0 has no row in the ticket-types table; its name is event meta, with
	 * the same default the ticketing add-on's own EventTicket uses. Shared by the
	 * attendee headers and the modal's order summary, which have to agree on what
	 * a general-admission ticket is called.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 *
	 * @return string
	 */
	public static function general_admission( $event_id ) {

		$event_id = (int) $event_id;
		$name     = $event_id > 0 ? trim( (string) get_event_meta( $event_id, 'ticket_name', true ) ) : '';

		return $name === '' ? __( 'General Admission', 'sugar-calendar-lite' ) : $name;
	}
}
