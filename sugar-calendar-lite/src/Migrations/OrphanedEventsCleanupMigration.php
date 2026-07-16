<?php

namespace Sugar_Calendar\Migrations;

use Sugar_Calendar\Helpers;
use Sugar_Calendar\Tasks\OrphanedEventsCleanupTask;

/**
 * Class OrphanedEventsCleanupMigration.
 *
 * One-shot cleanup of orphaned events (ghost events) on upgrade. Cleans one
 * limited batch; the recurring OrphanedEventsCleanupTask drains any remainder.
 * Idempotent and harmless when there are no orphans.
 *
 * @since 3.12.0
 */
class OrphanedEventsCleanupMigration extends MigrationAbstract {

	/**
	 * Version of the latest migration.
	 *
	 * @since 3.12.0
	 *
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * Option key where we save the current migration version.
	 *
	 * Unique per migration class — must not collide with the orchestrator's
	 * default option or any other migration.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar_orphaned_events_cleanup_migration_version';

	/**
	 * Delete one limited batch of orphaned events.
	 *
	 * @since 3.12.0
	 */
	protected function migrate_to_1() {

		Helpers::cleanup_orphaned_events( OrphanedEventsCleanupTask::get_limit() );

		$this->update_db_ver( 1 );
	}
}
