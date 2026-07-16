<?php

namespace Sugar_Calendar\Tasks;

use Sugar_Calendar\Helpers;

/**
 * Class OrphanedEventsCleanupTask.
 *
 * Recurring cleanup of orphaned events (sc_events rows whose WordPress post was
 * removed without Sugar Calendar's deleted_post cleanup running).
 *
 * @since 3.12.0
 */
class OrphanedEventsCleanupTask extends Task {

	/**
	 * Action name for this task.
	 *
	 * @since 3.12.0
	 */
	const ACTION = 'sugar_calendar_orphaned_events_cleanup';

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 */
	public function __construct() {

		parent::__construct( self::ACTION );
	}

	/**
	 * Initialize the task.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function init() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_action( self::ACTION, [ $this, 'process' ] );

		if ( Tasks::is_scheduled( self::ACTION ) !== false ) {
			return;
		}

		$interval = static::get_interval();

		$this->recurring( time() + $interval, $interval )->register();
	}

	/**
	 * Delete a batch of orphaned events.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function process() {

		Helpers::cleanup_orphaned_events( static::get_limit() );
	}

	/**
	 * Interval between cleanup runs, in seconds.
	 *
	 * @since 3.12.0
	 *
	 * @return int
	 */
	public static function get_interval() {

		/**
		 * Filter the interval between orphaned-events cleanup runs.
		 *
		 * @since 3.12.0
		 *
		 * @param int $interval Seconds between runs. Default 12 hours.
		 */
		return (int) apply_filters( 'sugar_calendar_orphaned_events_cleanup_interval', 12 * HOUR_IN_SECONDS ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Maximum records processed per run.
	 *
	 * @since 3.12.0
	 *
	 * @return int
	 */
	public static function get_limit() {

		/**
		 * Filter the per-run limit for orphaned-record cleanup.
		 *
		 * Each orphaned event is deleted individually via sugar_calendar_delete_event()
		 * so per-event hooks fire. Keep this low enough that the Action Scheduler job
		 * completes well within the PHP time limit (default 50 is safe).
		 *
		 * @since 3.12.0
		 *
		 * @param int $limit Maximum records per run. Default 50.
		 */
		return (int) apply_filters( 'sugar_calendar_orphaned_events_cleanup_limit', 50 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
