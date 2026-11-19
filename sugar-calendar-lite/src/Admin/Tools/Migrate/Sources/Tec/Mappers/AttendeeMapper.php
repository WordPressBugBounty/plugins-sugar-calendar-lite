<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecAttendee;

/**
 * Turns a TEC attendee into a Sugar Calendar seat, or an RSVP response.
 *
 * @since 3.18.0
 */
class AttendeeMapper {

	/**
	 * The holder's first and last name.
	 *
	 * TEC keeps only the full name: the first word becomes the first name, the rest the last name.
	 *
	 * @since 3.18.0
	 *
	 * @param TecAttendee $attendee The TEC attendee.
	 *
	 * @return string[]
	 */
	public static function get_names( TecAttendee $attendee ) {

		return array_pad( explode( ' ', trim( $attendee->get_name() ), 2 ), 2, '' );
	}

	/**
	 * The event, date, order and ticket type a TEC attendee's seat goes on.
	 *
	 * One checkout can hold tickets for several events, so the seat follows the attendee's
	 * own event and falls back to the order's.
	 *
	 * @since 3.18.0
	 *
	 * @param object      $order_info     Migrated order row from TecIdMap::find_order().
	 * @param object|null $migrated_event The attendee's own event's mapping row, if it was migrated.
	 * @param int         $ticket_type_id The Sugar Calendar ticket type of the attendee's ticket, 0 for none.
	 *
	 * @return array
	 */
	public static function get_seat_args( $order_info, $migrated_event, $ticket_type_id ) {

		$sc_event   = empty( $migrated_event ) ? false : sugar_calendar_get_event( $migrated_event->sc_event_id );
		$event_date = empty( $sc_event ) ? $order_info->sc_event_start : $sc_event->start;

		return array_filter(
			[
				'event_id'       => empty( $sc_event ) ? $order_info->sc_event_id : (int) $sc_event->id,
				'event_date'     => $event_date,
				'order_id'       => $order_info->sc_order_id,
				'ticket_type_id' => $ticket_type_id,
			]
		);
	}

	/**
	 * The RSVP response an RSVP add-on saves: `name`, `email` and `going`.
	 *
	 * @since 3.18.0
	 *
	 * @param TecAttendee $attendee The TEC attendee.
	 *
	 * @return array
	 */
	public static function to_rsvp_response( TecAttendee $attendee ) {

		return [
			'name'  => $attendee->get_name(),
			'email' => $attendee->get_email(),
			'going' => $attendee->is_going(),
		];
	}
}
