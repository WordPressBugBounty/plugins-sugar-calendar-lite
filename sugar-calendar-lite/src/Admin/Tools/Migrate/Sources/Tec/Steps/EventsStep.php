<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps;

use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendarSeries;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecEvent;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers\EventMapper;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers\RecurrenceMapper;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecCustomFields;
use Sugar_Calendar\Admin\Tools\Migrate\StepResult;

/**
 * TEC events, with their repeat, extra dates and additional fields.
 *
 * @since 3.18.0
 */
class EventsStep extends TecStep {

	/**
	 * Copies TEC's additional fields.
	 *
	 * @since 3.18.0
	 *
	 * @var TecCustomFields|null
	 */
	private $custom_fields = null;

	/**
	 * The kind of item, used as the progress key.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_entity() {

		return 'events';
	}

	/**
	 * The number of events one request migrates.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_batch_size() {

		/**
		 * Filter the number of TEC events to import per iteration.
		 *
		 * @since 3.3.0
		 *
		 * @param int $limit The number of TEC events to import per iteration.
		 */
		return apply_filters( 'sc_import_tec_events_limit', 10 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Established public hook; renaming would break backward compatibility.
	}

	/**
	 * The TEC events not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	protected function query( $count_only ) {

		return $this->reader->get_unmigrated_events( $count_only, $count_only ? 0 : $this->get_batch_size() );
	}

	/**
	 * The source ID of one batch item.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return int
	 */
	public function get_item_id( $item ) {

		return (int) $item->event_id;
	}

	/**
	 * Create the Sugar Calendar event of a TEC event, then its extra dates and fields.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return StepResult
	 */
	public function migrate( $item ) {

		$tec_event = $this->items->get_event( $item->post_id );

		if ( empty( $tec_event ) || $tec_event->get_post()->post_type !== 'tribe_events' ) {
			return StepResult::not_migrated();
		}

		$data            = EventMapper::map( $tec_event, $this->items->get_location( $tec_event->get_post()->ID ), $this->get_sc_venue_id( $tec_event->get_post()->ID ) );
		$recurrence      = $tec_event->get_recurrence();
		$recurrence_data = empty( $recurrence ) ? false : RecurrenceMapper::map( $recurrence );
		$created         = $this->tec->create_sc_event( is_array( $recurrence_data ) ? array_merge( $data, $recurrence_data ) : $data );

		if ( empty( $created ) ) {
			return StepResult::not_migrated();
		}

		$this->record( $item, $tec_event, $created );

		if ( is_array( $recurrence_data ) ) {
			$this->fire_recurring_hook( (int) $created['sc_event_id'], RecurrenceMapper::get_excluded_dates( $recurrence ) );
		}

		$this->migrate_extra_rules( $item->event_id, $tec_event->get_post()->ID, TheEventCalendarSeries::get_extra_rules( $recurrence, $recurrence_data ), $data );
		$this->list_other_provider_tickets( $tec_event->get_post()->ID );

		return StepResult::migrated();
	}

	/**
	 * Record the new event, then copy its additional fields.
	 *
	 * @since 3.18.0
	 *
	 * @param object   $item      The batch item.
	 * @param TecEvent $tec_event The TEC event.
	 * @param array    $created   The new event's `sc_event_id` and `sc_event_post_id`.
	 */
	private function record( $item, TecEvent $tec_event, $created ) {

		// Mapped first: a request that dies after this point must not create the event again.
		$this->id_map->save(
			'events',
			$item->event_id,
			[
				'tec_event_post_id' => $tec_event->get_post()->ID,
				'sc_event_id'       => $created['sc_event_id'],
				'sc_event_post_id'  => $created['sc_event_post_id'],
			]
		);

		$this->get_custom_fields()->import( $created['sc_event_post_id'], $tec_event );
	}

	/**
	 * The migrated venue of a TEC event, 0 when it has none or no venues were migrated.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_event_post_id The TEC event post ID.
	 *
	 * @return int
	 */
	private function get_sc_venue_id( $tec_event_post_id ) {

		return (int) $this->lookup->get_sc_venue_id( get_post_meta( $tec_event_post_id, '_EventVenueID', true ) );
	}

	/**
	 * Copies TEC's additional fields, built once per step.
	 *
	 * @since 3.18.0
	 *
	 * @return TecCustomFields
	 */
	private function get_custom_fields() {

		if ( $this->custom_fields === null ) {
			$this->custom_fields = new TecCustomFields();
		}

		return $this->custom_fields;
	}

	/**
	 * Let Pro build the occurrences of a migrated repeat.
	 *
	 * @since 3.18.0
	 *
	 * @param int      $sc_event_id    The Sugar Calendar event ID.
	 * @param string[] $excluded_dates The dates TEC excluded, as `Y-m-d`.
	 */
	private function fire_recurring_hook( $sc_event_id, $excluded_dates ) {

		/**
		 * Fires after a recurring TEC event is migrated, so Pro can build its occurrences.
		 *
		 * @since 3.18.0
		 *
		 * @param int      $sc_event_id    Sugar Calendar event ID.
		 * @param string[] $excluded_dates The dates TEC excluded from the repeat, as `Y-m-d`.
		 */
		do_action( 'sugar_calendar_admin_tools_importers_the_event_calendar_migrated_recurring_event', $sc_event_id, $excluded_dates ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Released with stack 951's TEC fixes, kept for compatibility.
	}

	/**
	 * Migrate the TEC rules that did not become the event's repeat.
	 *
	 * An extra date becomes its own event, the way "This event only" detaches a date in
	 * the editor. A second repeat pattern can't be kept, so the import summary lists it.
	 *
	 * @since 3.18.0
	 *
	 * @param int   $tec_event_id      TEC event ID.
	 * @param int   $tec_event_post_id TEC event post ID.
	 * @param array $rules             TEC `_EventRecurrence` rules, minus the one that became the repeat.
	 * @param array $data              The migrated event's data, without its recurrence.
	 */
	private function migrate_extra_rules( $tec_event_id, $tec_event_post_id, $rules, $data ) {

		foreach ( $rules as $rule ) {

			if ( strtolower( $rule['custom']['type'] ?? '' ) !== 'date' ) {
				$this->issues->add( 'Repeat rules', $tec_event_post_id, $data['title'] );

				continue;
			}

			$extra_event = $this->tec->create_sc_event( array_merge( $data, TheEventCalendarSeries::get_extra_date_times( $rule['custom'], $data ) ) );

			if ( ! empty( $extra_event ) ) {
				$this->id_map->save_extra_event( $extra_event, $tec_event_id, $tec_event_post_id );
			}
		}
	}

	/**
	 * List a TEC event's tickets from providers the importer doesn't migrate, such as WooCommerce.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_event_post_id TEC event post ID.
	 */
	private function list_other_provider_tickets( $tec_event_post_id ) {

		foreach ( $this->reader->get_other_provider_tickets( $tec_event_post_id ) as $ticket ) {
			$this->issues->add( 'tickets', $ticket['id'], $ticket['name'] );
		}
	}
}
