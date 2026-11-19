<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec;

use Sugar_Calendar\AddOn\Ticketing\Migrate\TicketingWriter;
use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;
use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendarSeries;
use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendarTables;
use Sugar_Calendar\Admin\Tools\Migrate\IdMap;
use Sugar_Calendar\Admin\Tools\Migrate\IssueLog;
use Sugar_Calendar\Admin\Tools\Migrate\MigrationSource;
use Sugar_Calendar\Admin\Tools\Migrate\MigrationState;
use Sugar_Calendar\Admin\Tools\Migrate\MigrationStep;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps\AttendeesStep;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps\CategoriesStep;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps\EventsStep;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps\OrdersStep;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps\RsvpsStep;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps\TagsStep;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps\TicketsStep;

/**
 * The Events Calendar as a migration source.
 *
 * @since 3.18.0
 */
class TecSource implements MigrationSource {

	/**
	 * What the steps of this request share.
	 *
	 * @since 3.18.0
	 *
	 * @var TecContext
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param TheEventCalendar $tec       The TEC importer.
	 * @param TicketingWriter  $ticketing The ticketing writer, built once and handed to the steps.
	 */
	public function __construct( TheEventCalendar $tec, TicketingWriter $ticketing ) {

		$this->context = new TecContext( $tec, $ticketing );
	}

	/**
	 * The source ID, used in option names and the importer hooks.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id() {

		return 'tec';
	}

	/**
	 * The steps, in the order they run.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_steps() {

		// Tags go before events, so events can link to them.
		$steps = [
			new TagsStep( $this->context ),
			new EventsStep( $this->context ),
			new CategoriesStep( $this->context ),
			new TicketsStep( $this->context ),
			new OrdersStep( $this->context ),
			new AttendeesStep( $this->context ),
			new RsvpsStep( $this->context ),
		];

		/**
		 * Filters the steps of a TEC migration, in the order they run.
		 *
		 * Pro puts its venue step first, so events can link to their venues.
		 *
		 * @since 3.18.0
		 *
		 * @param MigrationStep[] $steps   The steps.
		 * @param TecContext      $context What the steps of this request share.
		 */
		return (array) apply_filters( 'sugar_calendar_admin_tools_migrate_sources_tec_source_steps', $steps, $this->context );
	}

	/**
	 * The step that migrates one kind of item, null when no step does.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity The step's entity, such as `venues`.
	 *
	 * @return MigrationStep|null
	 */
	public function get_step( $entity ) {

		foreach ( $this->get_steps() as $step ) {
			if ( $step->get_entity() === $entity ) {
				return $step;
			}
		}

		return null;
	}

	/**
	 * The number of items a step has left, 0 when no step migrates the entity.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity The step's entity, such as `venues`.
	 *
	 * @return int
	 */
	public function count_pending( $entity ) {

		$step = $this->get_step( $entity );

		return $step === null ? 0 : $step->count_pending();
	}

	/**
	 * The record of which TEC item became which Sugar Calendar item.
	 *
	 * @since 3.18.0
	 *
	 * @return IdMap
	 */
	public function get_id_map() {

		return $this->context->get_id_map();
	}

	/**
	 * Where the TEC items that could not migrate are listed.
	 *
	 * @since 3.18.0
	 *
	 * @return IssueLog
	 */
	public function get_issue_log() {

		return $this->context->get_issues();
	}

	/**
	 * Create the mapping tables the steps need and note where they stand when a run starts.
	 *
	 * @since 3.18.0
	 *
	 * @param MigrationState $state The migration state.
	 */
	public function begin( MigrationState $state ) {

		// The one place a mapping table is created: reads treat a missing table as nothing migrated.
		foreach ( $this->get_steps() as $step ) {
			TheEventCalendarTables::ensure( $step->get_entity() );
		}

		// add_option(), not update_option(): a re-run keeps its date in every batch, so only its first batch may write.
		if ( ! $state->is_in_progress() ) {
			add_option( TheEventCalendar::SC_TEC_LAST_MIGRATED_IDS_OPTION_KEY, $this->context->get_lookup()->get_last_mapping_ids(), '', false );
		}
	}

	/**
	 * Run the post-migration work and mark the migration finished.
	 *
	 * @since 3.18.0
	 */
	public function complete() {

		$id_map          = $this->context->get_id_map();
		$events_imported = $this->context->get_lookup()->count_new_events();

		// After the steps' finish(): relating tags replaces an event's tags, the Series tag is added to them.
		TheEventCalendarSeries::relate_migrated_series_to_events();
		$id_map->release_rsvps( $this->context->get_reader()->get_unmigrated_rsvp_ticket_ids() );

		// The mapping tables stay: a re-run reads them to skip what is already migrated.
		update_option( TheEventCalendar::SC_TEC_MIGRATION_OPTION_KEY, gmdate( 'Y-m-d' ) );
		delete_option( TheEventCalendar::SC_TEC_LAST_MIGRATED_IDS_OPTION_KEY );

		$this->fire_completed_hooks( $events_imported );
	}

	/**
	 * Fire the hooks of a finished migration.
	 *
	 * @since 3.18.0
	 *
	 * @param int $events_imported Number of events the migration created.
	 */
	private function fire_completed_hooks( $events_imported ) {

		/**
		 * Post migration process action.
		 *
		 * @since 3.6.0
		 *
		 * @param TheEventCalendar $tec The importer object.
		 */
		do_action( 'sugar_calendar_admin_tools_importers_the_event_calendar_post_migration_process', $this->context->get_tec() ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Released hook name, kept for compatibility.

		/**
		 * Fires once when an import finishes.
		 *
		 * @since 3.15.0
		 *
		 * @param string $source          Import source: tec, json or ics.
		 * @param int    $events_imported Number of events the import created.
		 */
		do_action( 'sugar_calendar_importer_completed', 'tec', $events_imported ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
