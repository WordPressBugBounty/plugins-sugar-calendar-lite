<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\RespondentNaming;
use Sugar_Calendar_Event_Ticketing\Features\TicketType;
use Sugar_Calendar_Rsvp\Model\Rsvp;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendee;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order_tickets;

/**
 * Shapes pending response rows into render_static()'s row shape, per host.
 *
 * Extracted from TicketingReceipt and RsvpAfterCheckout so the resume host
 * (TokenResume) can reuse the same name and ticket-label resolution.
 * Host-specific logic stays split into for_order() and for_rsvp(), unchanged
 * from before the extraction.
 *
 * @since 3.13.0
 */
class RespondentRows {

	/**
	 * Build the render rows, resolving a display name and ticket label for each.
	 *
	 * @since 3.13.0
	 *
	 * @param object  $order The order.
	 * @param array[] $rows  The pending response rows.
	 *
	 * @return array[]
	 */
	public static function for_order( $order, array $rows ) {

		$ticket_labels = self::ticket_labels_by_attendee(
			isset( $order->id ) ? (int) $order->id : 0,
			isset( $order->event_id ) ? (int) $order->event_id : 0
		);
		$out           = [];

		foreach ( $rows as $position => $row ) {

			$key         = (string) $row['attendee_key'];
			$attendee_id = $row['attendee_id'] === null ? 0 : (int) $row['attendee_id'];

			$out[] = [
				'attendee_key' => $key,
				'display_name' => self::order_display_name( $order, $key, $attendee_id, (int) $position ),
				'ticket_label' => $ticket_labels[ $attendee_id ] ?? '',
				'answers'      => (array) $row['answers'],
			];
		}

		return $out;
	}

	/**
	 * Resolve one respondent's display name.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order       The order.
	 * @param string $key         Attendee key.
	 * @param int    $attendee_id Attendee id, or 0.
	 * @param int    $position    This row's zero-based position in the render list.
	 *
	 * @return string
	 */
	private static function order_display_name( $order, $key, $attendee_id, $position ) {

		if ( $key === AnswerRequest::MAIN_KEY ) {
			return RespondentNaming::purchaser( $order );
		}

		if ( $attendee_id > 0 ) {

			$attendee = get_attendee( $attendee_id );

			if ( ! empty( $attendee ) ) {
				$name = trim(
					sprintf(
						'%1$s %2$s',
						isset( $attendee->first_name ) ? (string) $attendee->first_name : '',
						isset( $attendee->last_name ) ? (string) $attendee->last_name : ''
					)
				);

				if ( $name !== '' ) {
					return $name;
				}
			}
		}

		// No sc_attendees record exists without an email, so number by position
		// (0-based, +1) to match before-checkout's attendeeFallbackName(), not by
		// the raw key ordinal (which can be non-dense across ticket types).
		return sprintf(
			/* translators: %d - the attendee's position in the order. */
			__( 'Attendee %d', 'sugar-calendar-lite' ),
			absint( $position ) + 1
		);
	}

	/**
	 * Ticket-type labels for this order, keyed by attendee id.
	 *
	 * Resolved once per distinct type id, since TicketType::get() runs an
	 * uncached query on every call.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 * @param int $event_id Sugar Calendar event id, for naming ticket type 0.
	 *
	 * @return array<int,string>
	 */
	private static function ticket_labels_by_attendee( $order_id, $event_id ) {

		if ( $order_id <= 0 ) {
			return [];
		}

		$type_id_by_attendee = self::ticket_type_ids_by_attendee( $order_id );

		if ( $type_id_by_attendee === [] ) {
			return [];
		}

		$names = self::ticket_type_names( array_unique( $type_id_by_attendee ), $event_id );
		$out   = [];

		foreach ( $type_id_by_attendee as $attendee_id => $ticket_type_id ) {
			if ( $names[ $ticket_type_id ] !== '' ) {
				$out[ $attendee_id ] = $names[ $ticket_type_id ];
			}
		}

		return $out;
	}

	/**
	 * This order's ticket_type_id per attendee id.
	 *
	 * Type 0 (the event's default ticket) is kept, not skipped: the design shows
	 * its name beside the attendee's, so "General Admission" is a label like any
	 * other. See RespondentNaming::general_admission().
	 *
	 * Split out to keep both methods under the phpcs complexity ceiling.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array<int,int>
	 */
	private static function ticket_type_ids_by_attendee( $order_id ) {

		$out = [];

		foreach ( (array) get_order_tickets( $order_id ) as $ticket ) {

			$attendee_id = isset( $ticket->attendee_id ) ? (int) $ticket->attendee_id : 0;

			if ( $attendee_id <= 0 ) {
				continue;
			}

			$out[ $attendee_id ] = isset( $ticket->ticket_type_id ) ? (int) $ticket->ticket_type_id : 0;
		}

		return $out;
	}

	/**
	 * Ticket-type names, keyed by ticket type id — one query per named id.
	 *
	 * A type with no usable name is recorded as '' rather than omitted, so the
	 * caller can read every id it asked about without a second existence check.
	 * Named types are read behind class_exists(), since they are an add-on
	 * feature; type 0 needs no table at all.
	 *
	 * @since 3.13.0
	 *
	 * @param int[] $ticket_type_ids Distinct ticket type ids.
	 * @param int   $event_id        Sugar Calendar event id, for naming type 0.
	 *
	 * @return array<int,string>
	 */
	private static function ticket_type_names( array $ticket_type_ids, $event_id ) {

		$ticket_types = class_exists( TicketType::class ) ? new TicketType() : null;
		$out          = [];

		foreach ( $ticket_type_ids as $ticket_type_id ) {

			if ( $ticket_type_id <= 0 ) {
				$out[ $ticket_type_id ] = RespondentNaming::general_admission( $event_id );

				continue;
			}

			$ticket_type = $ticket_types === null ? null : $ticket_types->get( $ticket_type_id );

			$out[ $ticket_type_id ] = empty( $ticket_type->ticket_name ) ? '' : (string) $ticket_type->ticket_name;
		}

		return $out;
	}

	/**
	 * Build the render rows, resolving a display name for each.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed   $rsvp The visitor's RSVP.
	 * @param array[] $rows The pending response rows.
	 *
	 * @return array[]
	 */
	public static function for_rsvp( $rsvp, array $rows ) {

		$out = [];

		foreach ( $rows as $position => $row ) {

			$key         = (string) $row['attendee_key'];
			$attendee_id = $row['attendee_id'] === null ? 0 : (int) $row['attendee_id'];

			$out[] = [
				'attendee_key' => $key,
				'display_name' => self::rsvp_display_name( $rsvp, $key, $attendee_id, (int) $position ),
				// RSVP has no ticket types at all, matching the ticket_type => 0
				// RsvpCheckout::attendees_from_post() reports.
				'ticket_label' => '',
				'answers'      => (array) $row['answers'],
			];
		}

		return $out;
	}

	/**
	 * Resolve the pending rows for an RSVP by its post id and shape them.
	 *
	 * TokenResume has only the RSVP's context_id, not sc-rsvp's cookie-based
	 * visitor resolution, so the RSVP is resolved directly by id. Guarded by
	 * class_exists() since sc-rsvp ships independently of core.
	 *
	 * @since 3.13.0
	 *
	 * @param int     $rsvp_id RSVP post id.
	 * @param array[] $rows    The pending response rows.
	 *
	 * @return array[] Empty when the RSVP cannot be resolved.
	 */
	public static function for_rsvp_id( $rsvp_id, array $rows ) {

		if ( ! class_exists( Rsvp::class ) ) {
			return [];
		}

		$rsvp = Rsvp::get( (int) $rsvp_id );

		if ( empty( $rsvp ) ) {
			return [];
		}

		return self::for_rsvp( $rsvp, $rows );
	}

	/**
	 * Resolve one respondent's display name.
	 *
	 * Without this the modal renders blank attendee headers on a screen the
	 * visitor cannot dismiss.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed  $rsvp        The visitor's RSVP.
	 * @param string $key         Attendee key.
	 * @param int    $attendee_id Attendee id, or 0.
	 * @param int    $position    This row's zero-based position in the render list.
	 *
	 * @return string
	 */
	private static function rsvp_display_name( $rsvp, $key, $attendee_id, $position ) {

		if ( $key === AnswerRequest::MAIN_KEY ) {

			$name = isset( $rsvp->main_attendee->name ) ? trim( (string) $rsvp->main_attendee->name ) : '';

			if ( $name !== '' ) {
				return $name;
			}
		} elseif ( $attendee_id > 0 ) {

			$name = self::additional_attendee_name( $rsvp, $attendee_id );

			if ( $name !== '' ) {
				return $name;
			}
		}

		// Numbered by position (0-based, +1), matching before-checkout's own
		// attendeeFallbackName() (general.js), so the two surfaces agree.
		return sprintf(
			/* translators: %d - the attendee's position in the RSVP. */
			__( 'Attendee %d', 'sugar-calendar-lite' ),
			absint( $position ) + 1
		);
	}

	/**
	 * Look up an additional attendee's name by attendee id.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $rsvp        The visitor's RSVP.
	 * @param int   $attendee_id Attendee id.
	 *
	 * @return string Empty when the attendee no longer resolves.
	 */
	private static function additional_attendee_name( $rsvp, $attendee_id ) {

		if ( ! method_exists( $rsvp, 'get_additional_attendees' ) ) {
			return '';
		}

		foreach ( (array) $rsvp->get_additional_attendees() as $attendee ) {

			if ( isset( $attendee->id ) && (int) $attendee->id === $attendee_id ) {
				return isset( $attendee->name ) ? trim( (string) $attendee->name ) : '';
			}
		}

		return '';
	}
}
