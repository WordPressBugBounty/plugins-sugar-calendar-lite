<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecTicket;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers\TicketMapper;
use Sugar_Calendar\Admin\Tools\Migrate\StepResult;

/**
 * TEC tickets, which become the ticket settings of their Sugar Calendar event.
 *
 * @since 3.18.0
 */
class TicketsStep extends TecStep {

	/**
	 * The kind of item, used as the progress key.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_entity() {

		return 'tickets';
	}

	/**
	 * The number of tickets one request migrates.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_batch_size() {

		/**
		 * Filter the number of TEC tickets to import per iteration.
		 *
		 * @since 3.3.0
		 *
		 * @param int $limit The number of TEC tickets to import per iteration.
		 */
		return apply_filters( 'sc_import_tec_tickets_limit', 10 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Established public hook; renaming would break backward compatibility.
	}

	/**
	 * The TEC tickets not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	protected function query( $count_only ) {

		return $this->reader->get_unmigrated_tickets( $count_only, $count_only ? 0 : $this->get_batch_size() );
	}

	/**
	 * Give a TEC ticket's event its ticket, ticket type or RSVP settings.
	 *
	 * Counts as migrated once the event's ticket meta was written, which does not prove the write took.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return StepResult
	 */
	public function migrate( $item ) {

		$tec_ticket_id = (int) $item->ID;
		$ticket        = $this->items->get_ticket( $tec_ticket_id );
		$event         = empty( $ticket ) ? null : $this->get_migrated_event( $tec_ticket_id );

		if ( empty( $event ) ) {
			$this->id_map->save_ticket( $tec_ticket_id );

			return StepResult::not_migrated();
		}

		if ( $this->items->is_rsvp_ticket( $tec_ticket_id ) ) {
			$migrated = $this->migrate_rsvp_settings( $ticket, (int) $event->sc_event_id, (int) $event->tec_event_post_id );

			$this->id_map->save_ticket( $tec_ticket_id, $event->sc_event_id, $migrated );

			return $migrated ? StepResult::migrated() : StepResult::not_migrated();
		}

		return $this->migrate_ticket( $tec_ticket_id, (int) $event->sc_event_id, $ticket );
	}

	/**
	 * The mapping row of the migrated event a TEC ticket belongs to.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_ticket_id The TEC ticket post ID.
	 *
	 * @return object|null|false
	 */
	private function get_migrated_event( $tec_ticket_id ) {

		$tec_event_post_id = get_post_meta( $tec_ticket_id, '_tec_tickets_commerce_event', true );

		return empty( $tec_event_post_id ) ? null : $this->lookup->find_event( 'tec_event_post_id', $tec_event_post_id );
	}

	/**
	 * Make a paid or free TEC ticket the event's ticket, or a ticket type when the event has one.
	 *
	 * @since 3.18.0
	 *
	 * @param int       $tec_ticket_id The TEC ticket post ID.
	 * @param int       $sc_event_id   The Sugar Calendar event ID.
	 * @param TecTicket $ticket        The TEC ticket.
	 *
	 * @return StepResult
	 */
	private function migrate_ticket( $tec_ticket_id, $sc_event_id, TecTicket $ticket ) {

		// The first ticket, even a free one, is the event's own ticket; only the others become ticket types.
		if ( get_event_meta( $sc_event_id, 'ticket_price', true ) === '' ) {
			$this->ticketing->set_event_ticket( $sc_event_id, $ticket->get_price(), $ticket->get_capacity() );
			update_event_meta( $sc_event_id, 'ticket_name', $ticket->get_name() );
		} elseif ( empty( $this->migrate_ticket_type( $ticket, $sc_event_id ) ) ) {
			return $this->list_unmigrated_ticket_type( $tec_ticket_id, $sc_event_id );
		}

		$this->id_map->save_ticket( $tec_ticket_id, $sc_event_id, true );

		return StepResult::migrated();
	}

	/**
	 * Hand a TEC RSVP's settings to whatever migrates RSVPs.
	 *
	 * Core has no RSVPs: the RSVP add-on answers the hook. An event can't take RSVPs and
	 * sell tickets at once, so a TEC event that also sells tickets gets no RSVPs.
	 *
	 * @since 3.18.0
	 *
	 * @param TecTicket $ticket            The TEC RSVP ticket.
	 * @param int       $sc_event_id       Sugar Calendar event ID.
	 * @param int       $tec_event_post_id TEC event post ID.
	 *
	 * @return bool Whether anything took the settings.
	 */
	private function migrate_rsvp_settings( TecTicket $ticket, $sc_event_id, $tec_event_post_id ) {

		if ( ! has_action( 'sugar_calendar_admin_tools_importers_the_event_calendar_migrate_rsvp_settings' ) || $this->items->has_ticket_sales( $tec_event_post_id ) ) {
			return false;
		}

		/**
		 * Fires for each TEC RSVP, so an RSVP add-on can turn RSVPs on for the migrated event.
		 *
		 * @since 3.18.0
		 *
		 * @param int   $sc_event_id Sugar Calendar event ID.
		 * @param array $settings    `capacity` (int, zero or less is unlimited) and `allow_not_going` (bool).
		 */
		do_action( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Named for the TEC importer, which the RSVP add-on listens to.
			'sugar_calendar_admin_tools_importers_the_event_calendar_migrate_rsvp_settings',
			$sc_event_id,
			[
				'capacity'        => (int) $ticket->get_capacity(),
				'allow_not_going' => ! empty( get_post_meta( $ticket->get_id(), '_tribe_ticket_show_not_going', true ) ),
			]
		);

		return true;
	}

	/**
	 * Hand an event's further TEC ticket to whatever migrates ticket types, and record the type.
	 *
	 * Ticket types belong to the Event Ticketing add-on, which answers the hook.
	 *
	 * @since 3.18.0
	 *
	 * @param TecTicket $ticket      The TEC ticket.
	 * @param int       $sc_event_id Sugar Calendar event ID.
	 *
	 * @return int The Sugar Calendar ticket type ID, or 0 when nothing took the ticket.
	 */
	private function migrate_ticket_type( TecTicket $ticket, $sc_event_id ) {

		/**
		 * Filters the ticket type a TEC ticket becomes, so a ticketing add-on can create it.
		 *
		 * @since 3.18.0
		 *
		 * @param int   $ticket_type_id Ticket type ID; 0 until something creates it.
		 * @param int   $sc_event_id    Sugar Calendar event ID.
		 * @param array $ticket         `name`, `price`, `limit_capacity` (0 or 1) and `quantity`, decided as for the event's own ticket.
		 */
		$ticket_type_id = (int) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Named for the TEC importer, which the Event Ticketing add-on listens to.
			'sugar_calendar_admin_tools_importers_the_event_calendar_migrate_ticket_type',
			0,
			$sc_event_id,
			TicketMapper::to_ticket_type( $ticket )
		);

		if ( $ticket_type_id > 0 ) {
			$this->id_map->save_ticket_type( $ticket->get_id(), $ticket_type_id );
		}

		return $ticket_type_id;
	}

	/**
	 * List a further TEC ticket that nothing turned into a ticket type.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_ticket_id TEC ticket post ID.
	 * @param int $sc_event_id   Sugar Calendar event ID.
	 *
	 * @return StepResult
	 */
	private function list_unmigrated_ticket_type( $tec_ticket_id, $sc_event_id ) {

		$this->id_map->save_ticket( $tec_ticket_id, $sc_event_id );

		return StepResult::not_migrated(
			'tickets',
			/* translators: %s: ticket name. */
			sprintf( __( '%s (activate the Event Ticketing add-on before migrating to bring every ticket type across)', 'sugar-calendar-lite' ), get_the_title( $tec_ticket_id ) )
		);
	}

	/**
	 * Hide the tickets from the Migrate tab's counts.
	 *
	 * @since 3.18.0
	 *
	 * @param array $response The batch response.
	 *
	 * @return array
	 */
	public function get_response( $response ) {

		// A TEC ticket only adds ticket data to its Sugar Calendar event, so the tab shows no count for it.
		$response['process'] = 'hidden';

		return $response;
	}
}
