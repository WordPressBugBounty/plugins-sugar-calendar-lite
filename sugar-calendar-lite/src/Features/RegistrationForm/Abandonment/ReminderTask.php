<?php

namespace Sugar_Calendar\Features\RegistrationForm\Abandonment;

use Sugar_Calendar\Tasks\Task;
use Sugar_Calendar\Tasks\Tasks;

/**
 * Schedules the hourly abandonment sweep.
 *
 * A structural copy of Tasks\OrphanedEventsCleanupTask. process() is one line so
 * the sweep is testable without Action Scheduler.
 *
 * @since 3.13.0
 */
class ReminderTask extends Task {

	/**
	 * Action name for this task.
	 *
	 * @since 3.13.0
	 */
	const ACTION = 'sugar_calendar_registration_reminder';

	/**
	 * Constructor.
	 *
	 * @since 3.13.0
	 */
	public function __construct() {

		parent::__construct( self::ACTION );
	}

	/**
	 * Initialize the task.
	 *
	 * @since 3.13.0
	 */
	public function init() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_action( self::ACTION, [ $this, 'process' ] );

		// `!== false`, not `=== true`: is_scheduled() returns null when Action
		// Scheduler isn't loaded, which must not be treated as unscheduled.
		if ( Tasks::is_scheduled( self::ACTION ) !== false ) {
			return;
		}

		$interval = static::get_interval();

		$this->recurring( time() + $interval, $interval )->register();
	}

	/**
	 * Run one sweep.
	 *
	 * @since 3.13.0
	 */
	public function process() {

		ReminderSweep::run( static::get_limit() );
	}

	/**
	 * Seconds between sweeps.
	 *
	 * @since 3.13.0
	 *
	 * @return int
	 */
	public static function get_interval() {

		/**
		 * Filters the interval between abandonment sweeps.
		 *
		 * @since 3.13.0
		 *
		 * @param int $interval Seconds between runs. Default 1 hour.
		 */
		return (int) apply_filters( 'sugar_calendar_registration_reminder_interval', HOUR_IN_SECONDS ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Maximum contexts reminded per run.
	 *
	 * @since 3.13.0
	 *
	 * @return int
	 */
	public static function get_limit() {

		/**
		 * Filters the per-run cap on reminders.
		 *
		 * Keep low enough that the job finishes within the PHP time limit;
		 * each context costs a wp_mail() call, which may block on SMTP.
		 *
		 * @since 3.13.0
		 *
		 * @param int $limit Maximum contexts per run. Default 50.
		 */
		return (int) apply_filters( 'sugar_calendar_registration_reminder_limit', 50 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
