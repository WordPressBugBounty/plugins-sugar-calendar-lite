<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecAttendee;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers\AttendeeMapper;
use Sugar_Calendar\Admin\Tools\Migrate\StepResult;

/**
 * TEC ticket attendees, which become Sugar Calendar tickets.
 *
 * @since 3.18.0
 */
class AttendeesStep extends TecStep {

	/**
	 * The number of people left to migrate, counted before the batch runs.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	private $people_to_migrate = 0;

	/**
	 * The kind of item, used as the progress key.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_entity() {

		return 'attendees';
	}

	/**
	 * The number of attendees one request migrates.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_batch_size() {

		/**
		 * Filter the number of TEC attendees to import per iteration.
		 *
		 * @since 3.3.0
		 *
		 * @param int $limit The number of TEC attendees to import per iteration.
		 */
		return apply_filters( 'sc_import_tec_attendees_limit', 10 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Established public hook; renaming would break backward compatibility.
	}

	/**
	 * The TEC ticket attendees not migrated yet, RSVPs left out, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	protected function query( $count_only ) {

		return $this->reader->get_unmigrated_attendees( $count_only, $count_only ? 0 : $this->get_batch_size(), false );
	}

	/**
	 * The next attendees to migrate, counting the people left first.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_batch() {

		$batch = parent::get_batch();

		if ( ! empty( $batch ) ) {
			$this->people_to_migrate = $this->reader->count_people_to_migrate();
		}

		return $batch;
	}

	/**
	 * Give a TEC attendee a seat on their migrated order.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return StepResult
	 */
	public function migrate( $item ) {

		$tec_attendee = $this->items->get_attendee( $item->ID );
		$order        = $this->get_seat_order( $tec_attendee );

		if ( empty( $order ) ) {
			return StepResult::not_migrated();
		}

		[ $first_name, $last_name ] = AttendeeMapper::get_names( $tec_attendee );

		$sc_attendee_id = $this->tec->get_or_create_sc_attendee( $tec_attendee->get_email(), $first_name, $last_name );

		if ( empty( $sc_attendee_id ) ) {
			return StepResult::not_migrated();
		}

		return $this->add_seat( $tec_attendee, $order, $sc_attendee_id );
	}

	/**
	 * The migrated order an attendee's seat goes on, if it can hold seats.
	 *
	 * @since 3.18.0
	 *
	 * @param TecAttendee $tec_attendee The TEC attendee.
	 *
	 * @return object|null
	 */
	private function get_seat_order( TecAttendee $tec_attendee ) {

		$orders = $this->lookup->find_order( absint( $tec_attendee->get_order_id() ) );
		$order  = $orders[0] ?? null;

		// An order left out, or refunded (a reversed one keeps its attendees in TEC), holds no seats.
		if ( empty( $order ) || empty( $order->sc_order_id ) || $order->sc_order_status === 'refunded' ) {
			return null;
		}

		return $order;
	}

	/**
	 * Add the attendee's seat, or list it when the write fails.
	 *
	 * @since 3.18.0
	 *
	 * @param TecAttendee $tec_attendee   The TEC attendee.
	 * @param object      $order          The migrated order row.
	 * @param int         $sc_attendee_id The Sugar Calendar attendee ID.
	 *
	 * @return StepResult
	 */
	private function add_seat( TecAttendee $tec_attendee, $order, $sc_attendee_id ) {

		$migrated_event = $this->lookup->find_event( 'tec_event_post_id', $tec_attendee->get_event_post_id() );
		$seat_args      = AttendeeMapper::get_seat_args( $order, $migrated_event, $this->id_map->get_ticket_type_id( $tec_attendee->get_ticket_id() ) );
		$sc_ticket      = $this->ticketing->add_seat( [ 'attendee_id' => $sc_attendee_id ] + $seat_args );

		return empty( $sc_ticket ) ? StepResult::not_migrated( 'tickets', $tec_attendee->get_name() ) : StepResult::migrated();
	}

	/**
	 * Add the people counts the Migrate tab shows.
	 *
	 * @since 3.18.0
	 *
	 * @param array $response The batch response.
	 *
	 * @return array
	 */
	public function get_response( $response ) {

		return [
			'attendees_total_count' => $this->people_to_migrate,
			// The TEC attendees are Sugar Calendar's tickets.
			'process'               => 'tickets',
			'attendees_count'       => $this->tec->get_imported_attendees_count(),
		] + $response;
	}
}
