<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Event;
use Sugar_Calendar\Ics\EventData;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\display_price;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendee;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_email_tag_ticket_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_ticket;

/**
 * Turn a ticket into the stable single-item Modern context.
 *
 * @since 3.14.0
 */
class TicketContext {

	/**
	 * Resolve a ticket into renderable values.
	 *
	 * @since 3.14.0
	 *
	 * @param int $ticket_id Ticket id.
	 *
	 * @return array|null
	 */
	public static function from_ticket( $ticket_id ) {

		$ticket = get_ticket( (int) $ticket_id );

		if ( empty( $ticket ) || empty( $ticket->id ) ) {
			return null;
		}

		$event = sugar_calendar_get_event( $ticket->event_id );

		if ( empty( $event ) || empty( $event->id ) ) {
			return null;
		}

		$attendee = get_attendee( $ticket->attendee_id );

		if ( empty( $attendee ) || empty( $attendee->id ) ) {
			return null;
		}

		$resolved_event = EventContext::resolve( $event, $ticket );

		if ( $resolved_event === null ) {
			return null;
		}

		$context = EventContext::from_event(
			$resolved_event,
			! empty( $ticket->occurrence_id ) ? (int) $ticket->occurrence_id : 0
		);

		if ( $context === null ) {
			return null;
		}

		$context['calendar']             = EventData::calendar( $resolved_event );
		$context['event']['sc_event_id'] = (int) $event->id;

		$type       = self::ticket_type( $ticket, $resolved_event );
		$cost       = self::current_cost( $ticket, $resolved_event );
		$ticket_url = get_email_tag_ticket_url( $ticket->id );

		return array_merge(
			$context,
			[
				'details'   => [
					self::detail( 'icon-attendee.png', __( 'Attendee', 'sugar-calendar-lite' ), trim( $attendee->first_name . ' ' . $attendee->last_name ) ),
					self::detail( 'icon-ticket-type.png', __( 'Ticket Type', 'sugar-calendar-lite' ), $type ),
					self::detail( 'icon-ticket-code.png', __( 'Ticket Code', 'sugar-calendar-lite' ), (string) $ticket->code ),
					self::detail( 'icon-ticket-cost.png', __( 'Ticket Cost', 'sugar-calendar-lite' ), $cost ),
				],
				'item_link' => [
					'url'   => $ticket_url,
					'label' => __( 'View Ticket Online', 'sugar-calendar-lite' ),
				],
				'footer'    => [
					'before_link' => __( "You're receiving this because a ticket was purchased for you at ", 'sugar-calendar-lite' ),
					'after_link'  => '',
				],
			]
		);
	}

	/**
	 * Deterministic sample values for previews.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function fictional(): array {

		$context = EventContext::fictional();

		return array_merge(
			$context,
			[
				'details'   => [
					self::detail( 'icon-attendee.png', __( 'Attendee', 'sugar-calendar-lite' ), __( 'Jane Doe', 'sugar-calendar-lite' ) ),
					self::detail( 'icon-ticket-type.png', __( 'Ticket Type', 'sugar-calendar-lite' ), __( 'General Admission', 'sugar-calendar-lite' ) ),
					self::detail( 'icon-ticket-code.png', __( 'Ticket Code', 'sugar-calendar-lite' ), 'TKT-ABCDE-12345' ),
					self::detail( 'icon-ticket-cost.png', __( 'Ticket Cost', 'sugar-calendar-lite' ), display_price( 25 ) ),
				],
				'item_link' => [
					'url'   => home_url( '/ticket-details/?order_id=1&ticket_code=TKT-ABCDE-12345' ),
					'label' => __( 'View Ticket Online', 'sugar-calendar-lite' ),
				],
				'footer'    => [
					'before_link' => __( "You're receiving this because a ticket was purchased for you at ", 'sugar-calendar-lite' ),
					'after_link'  => '',
				],
			]
		);
	}

	/**
	 * Get the currently configured display price for a ticket.
	 *
	 * @since 3.14.0
	 *
	 * @param object $ticket Ticket row.
	 * @param Event  $event  Resolved event.
	 *
	 * @return string
	 */
	public static function current_cost( $ticket, $event ): string {

		$ticket_type_id = ! empty( $ticket->ticket_type_id ) ? (int) $ticket->ticket_type_id : 0;
		$price          = $ticket_type_id > 0
			? null
			: get_event_meta( $ticket->event_id, 'ticket_price', true );

		if ( $ticket_type_id > 0 ) {
			/**
			 * Filters the current configured price for a named ticket type.
			 *
			 * @since 3.14.0
			 *
			 * @param mixed  $price  Unresolved current price.
			 * @param object $ticket Ticket row.
			 * @param Event  $event  Resolved event.
			 */
			$price = apply_filters( 'sugar_calendar_emails_ticket_price', $price, $ticket, $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		}

		return is_numeric( $price ) ? display_price( $price ) : '';
	}

	/**
	 * Resolve the ticket type label.
	 *
	 * @since 3.14.0
	 *
	 * @param object $ticket Ticket row.
	 * @param Event  $event  Event.
	 *
	 * @return string
	 */
	private static function ticket_type( $ticket, $event ) {

		$default = __( 'General Admission', 'sugar-calendar-lite' );

		/**
		 * Filters the ticket type label.
		 *
		 * @since 3.14.0
		 *
		 * @param string $type   Ticket type label.
		 * @param object $ticket Ticket row.
		 * @param Event  $event  Event.
		 */
		$type = apply_filters( 'sugar_calendar_emails_ticket_type', $default, $ticket, $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return is_string( $type ) && $type !== '' ? $type : $default;
	}

	/**
	 * Build one generic detail entry.
	 *
	 * @since 3.14.0
	 *
	 * @param string $icon  Icon filename.
	 * @param string $label Label.
	 * @param string $value Value.
	 *
	 * @return array
	 */
	private static function detail( $icon, $label, $value ) {

		return [
			'icon'  => $icon,
			'label' => $label,
			'value' => $value,
		];
	}
}
