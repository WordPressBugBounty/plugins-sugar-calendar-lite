<?php

namespace Sugar_Calendar\Integrations\Frontend;

use Sugar_Calendar\Integrations\OnlineMeeting;
use Sugar_Calendar\Integrations\OnlineMeetingPresenter;

/**
 * Public Join Link on the single-event front-end page.
 *
 * Renders the meeting block to ALL visitors only when "Show to" is
 * "Everyone" (OnlineMeeting::is_public()). The "Only Attendees" case on the
 * event page is owned by the RSVP add-on (recognized "going" attendees) —
 * the two are mutually exclusive on the visibility value, so the link never
 * double-renders.
 *
 * @since 3.12.0
 */
class OnlineMeetingDisplay {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		// Priority 42: after Location (40), before Calendars (50).
		add_action( 'sugar_calendar_frontend_event_details', [ $this, 'render' ], 42 );
	}

	/**
	 * Render the public Join Link block.
	 *
	 * @since 3.12.0
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 *
	 * @return void
	 */
	public function render( $event ) {

		if ( ! OnlineMeeting::is_public( $event ) ) {
			return;
		}

		// Pre-escaped by the presenter (esc_url / esc_html).
		echo OnlineMeetingPresenter::front_end_html( OnlineMeeting::for_event( $event ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
