<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecAttendee;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers\AttendeeMapper;
use Sugar_Calendar\Admin\Tools\Migrate\StepResult;

/**
 * TEC RSVP responses, which the RSVP add-on saves.
 *
 * @since 3.18.0
 */
class RsvpsStep extends TecStep {

	/**
	 * The kind of item, used as the progress key.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_entity() {

		return 'rsvps';
	}

	/**
	 * The number of RSVP responses one request migrates, the same as attendees.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_batch_size() {

		/** This filter is documented in AttendeesStep::get_batch_size(). */
		return apply_filters( 'sc_import_tec_attendees_limit', 10 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Established public hook; renaming would break backward compatibility.
	}

	/**
	 * The TEC RSVP responses not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	protected function query( $count_only ) {

		return $this->reader->get_unmigrated_attendees( $count_only, $count_only ? 0 : $this->get_batch_size(), true );
	}

	/**
	 * Hand a TEC RSVP response to the RSVP add-on, or list it.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return StepResult
	 */
	public function migrate( $item ) {

		$tec_attendee        = $this->items->get_attendee( $item->ID );
		$migrated_event_info = $this->lookup->find_event( 'tec_event_post_id', $tec_attendee->get_event_post_id() );
		$has_ticket_sales    = $this->items->has_ticket_sales( $tec_attendee->get_event_post_id() );

		if ( ! $has_ticket_sales && $this->migrate_response( (int) ( $migrated_event_info->sc_event_id ?? 0 ), $tec_attendee ) ) {
			return StepResult::migrated();
		}

		return StepResult::not_migrated( 'RSVPs', $this->get_rsvp_summary( $tec_attendee, $has_ticket_sales ) );
	}

	/**
	 * Ask whatever migrates RSVPs to save a TEC RSVP response.
	 *
	 * @since 3.18.0
	 *
	 * @param int         $sc_event_id  Sugar Calendar event ID, 0 when the event was not migrated.
	 * @param TecAttendee $tec_attendee The TEC attendee.
	 *
	 * @return bool Whether the response was migrated.
	 */
	private function migrate_response( $sc_event_id, TecAttendee $tec_attendee ) {

		/**
		 * Filters whether a TEC RSVP response was migrated.
		 *
		 * @since 3.18.0
		 *
		 * @param bool  $migrated    Whether the response was migrated. Default false.
		 * @param int   $sc_event_id Sugar Calendar event ID, 0 when the event was not migrated.
		 * @param array $response    `name`, `email` and `going` (true for "going", false for "not going").
		 */
		return (bool) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Named for the TEC importer, which the RSVP add-on listens to.
			'sugar_calendar_admin_tools_importers_the_event_calendar_migrate_rsvp',
			false,
			$sc_event_id,
			AttendeeMapper::to_rsvp_response( $tec_attendee )
		);
	}

	/**
	 * The summary line for a TEC RSVP response that was not migrated.
	 *
	 * @since 3.18.0
	 *
	 * @param TecAttendee $tec_attendee     The TEC attendee.
	 * @param bool        $has_ticket_sales Whether the TEC event also sells tickets.
	 *
	 * @return string
	 */
	private function get_rsvp_summary( TecAttendee $tec_attendee, $has_ticket_sales ) {

		if ( $has_ticket_sales ) {
			return sprintf(
				/* translators: %s: Attendee name. */
				__( '%s (the event also sells tickets, and an event cannot take RSVPs and sell tickets at the same time)', 'sugar-calendar-lite' ),
				$tec_attendee->get_name()
			);
		}

		if ( has_action( 'sugar_calendar_admin_tools_importers_the_event_calendar_migrate_rsvp_settings' ) ) {
			return $tec_attendee->get_name();
		}

		return sprintf(
			/* translators: %s: Attendee name. */
			__( '%s (activate the RSVP add-on before migrating to bring RSVPs across)', 'sugar-calendar-lite' ),
			$tec_attendee->get_name()
		);
	}
}
