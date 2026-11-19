<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

/**
 * A plugin whose data migrates into Sugar Calendar.
 *
 * @since 3.18.0
 */
interface MigrationSource {

	/**
	 * The source ID, used in option names and the importer hooks.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * The steps, in the order they run.
	 *
	 * @since 3.18.0
	 *
	 * @return MigrationStep[]
	 */
	public function get_steps();

	/**
	 * The record of which source item became which Sugar Calendar item.
	 *
	 * @since 3.18.0
	 *
	 * @return IdMap
	 */
	public function get_id_map();

	/**
	 * Where the items that could not migrate are listed.
	 *
	 * @since 3.18.0
	 *
	 * @return IssueLog
	 */
	public function get_issue_log();

	/**
	 * Run at the start of each request, before the state marks the run in progress.
	 *
	 * @since 3.18.0
	 *
	 * @param MigrationState $state The migration state.
	 */
	public function begin( MigrationState $state );

	/**
	 * Run the source's own work once every step is done.
	 *
	 * @since 3.18.0
	 */
	public function complete();
}
