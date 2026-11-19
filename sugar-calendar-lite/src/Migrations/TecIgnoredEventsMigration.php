<?php

namespace Sugar_Calendar\Migrations;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecEvent;

/**
 * Turns events an earlier TEC migration brought over as "ignored" into drafts.
 *
 * Earlier migrations copied TEC's "ignored" status as is; Sugar Calendar has no such status, so those events were hidden from every list.
 *
 * @since 3.18.0
 */
class TecIgnoredEventsMigration extends MigrationAbstract {

	/**
	 * Version of the migration.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * Option name to store the migration version.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar_tec_ignored_events_migration_version';

	/**
	 * Events changed per request.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	const BATCH_SIZE = 200;

	/**
	 * Turn a batch of ignored events into drafts; done once none are left.
	 *
	 * @since 3.18.0
	 */
	protected function migrate_to_1() {

		$events  = $this->get_ignored_events();
		$changed = 0;

		foreach ( $events as $event ) {
			$changed += (int) (bool) sugar_calendar_update_event( (int) $event->id, [ 'status' => 'draft' ] );

			$this->draft_post( (int) $event->object_id );
		}

		// A batch that changed nothing would come back on every request.
		if ( count( $events ) < self::BATCH_SIZE || $changed === 0 ) {
			$this->update_db_ver( 1 );
		}
	}

	/**
	 * Get a batch of events with TEC's "ignored" status.
	 *
	 * @since 3.18.0
	 *
	 * @return object[] Rows with the event `id` and its post's `object_id`.
	 */
	private function get_ignored_events() {

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade over the events table.
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, object_id FROM {$wpdb->prefix}sc_events WHERE status = %s AND object_type = 'post' LIMIT %d",
				TecEvent::IGNORED_STATUS,
				self::BATCH_SIZE
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $events;
	}

	/**
	 * Turn the event's post into a draft.
	 *
	 * Only our event post types: TEC's own events keep the status, which stops its importer bringing them back.
	 *
	 * @since 3.18.0
	 *
	 * @param int $post_id Event post ID.
	 */
	private function draft_post( $post_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct, so no save hook runs on the post during an upgrade.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_status = 'draft' WHERE ID = %d AND post_status = %s AND post_type IN ( %s, 'sc_recurring_event' )",
				$post_id,
				TecEvent::IGNORED_STATUS,
				sugar_calendar_get_event_post_type_id()
			)
		);

		clean_post_cache( $post_id );
	}
}
