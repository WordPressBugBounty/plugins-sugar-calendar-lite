<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data;

/**
 * A TEC attendee's holder, order, ticket, event and RSVP answer.
 *
 * @since 3.18.0
 */
final class TecAttendee {

	use TecRecord;

	/**
	 * The holder's email, empty when it is missing.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_email() {

		return $this->fields['holder_email'];
	}

	/**
	 * The holder's full name, empty when it is missing.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_name() {

		return $this->fields['holder_name'];
	}

	/**
	 * The TEC order post ID, 0 when the attendee has none.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_order_id() {

		return (int) $this->fields['order_id'];
	}

	/**
	 * The TEC ticket post ID.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_ticket_id() {

		return (int) $this->fields['ticket_id'];
	}

	/**
	 * The TEC event post ID.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_event_post_id() {

		return (int) $this->fields['event_post_id'];
	}

	/**
	 * Whether the RSVP answer is "going".
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function is_going() {

		return $this->fields['rsvp_status'] === 'yes';
	}
}
