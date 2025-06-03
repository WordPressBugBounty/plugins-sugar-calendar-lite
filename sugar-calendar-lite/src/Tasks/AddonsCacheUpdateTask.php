<?php

namespace Sugar_Calendar\Tasks;

use Exception;

/**
 * Class AddonsCacheUpdateTask.
 *
 * @since 3.7.0
 */
class AddonsCacheUpdateTask extends Task {

	/**
	 * Action name for this task.
	 *
	 * @since 3.7.0
	 */
	const ACTION = 'sugar_calendar_addons_cache_update_task';

	/**
	 * Constructor.
	 *
	 * @since 3.7.0
	 */
	public function __construct() {

		parent::__construct( self::ACTION );
	}

	/**
	 * Initialize the task.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function init() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// Register the action handler.
		add_action( self::ACTION, [ $this, 'process' ] );

		if ( Tasks::is_scheduled( self::ACTION ) !== false ) {
			return;
		}

		$ttl = sugar_calendar()->get_addons()->get_cache()->get_ttl();

		$this->recurring( time() + $ttl, $ttl )->register();
	}

	/**
	 * Update the addons cache.
	 *
	 * @since 3.7.0
	 *
	 * @throws Exception Exception will be logged in the Action Scheduler logs table.
	 */
	public function process() {

		// Delete cache update task duplicates.
		try {
			$this->delete_pending();
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing.
		}

		sugar_calendar()->get_addons()->get_cache()->update();
	}
}
