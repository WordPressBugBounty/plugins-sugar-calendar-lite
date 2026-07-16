<?php

namespace Sugar_Calendar\Migrations;

use Sugar_Calendar\Features\Tags\Common\Helpers;

/**
 * Class EventTermCountMigration.
 *
 * One-shot recount of the `sc_event_tags` term counts. The taxonomy is now
 * related to `sc_recurring_event`, but stored counts only refresh on a term
 * relationship change, so events tagged before the upgrade keep their stale
 * count until this recounts them. Idempotent; harmless for Lite-only sites and
 * already-correct counts.
 *
 * @since 3.12.0
 */
class EventTermCountMigration extends MigrationAbstract {

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
	 * default `sugar_calendar_migration_version` option or any other migration.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar_event_term_count_migration_version';

	/**
	 * Recount the `sc_event_tags` term counts.
	 *
	 * Scoped to the tags taxonomy: it is the only one newly related to
	 * `sc_recurring_event`. The calendar taxonomy was already related to every
	 * event post type, so its stored counts were never stale.
	 *
	 * @since 3.12.0
	 */
	protected function migrate_to_1() {

		global $wpdb;

		$taxonomy = Helpers::get_tags_taxonomy_id();

		$term_ids = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'fields'     => 'ids',
				'hide_empty' => false,
			]
		);

		// A failed term query leaves the version flag un-bumped so the migration
		// retries on the next admin pageview; the recount itself is idempotent.
		if ( is_wp_error( $term_ids ) ) {
			update_option( static::ERROR_OPTION_NAME, 'EventTermCountMigration get_terms failed: ' . $term_ids->get_error_message() );

			return;
		}

		if ( ! empty( $term_ids ) ) {

			wp_update_term_count_now( $term_ids, $taxonomy );

			// wp_update_term_count_now() always returns true; the real recount
			// runs raw queries inside the count callback, so surface a DB error
			// the same way other migrations do and defer for retry.
			if ( ! empty( $wpdb->last_error ) ) {
				update_option( static::ERROR_OPTION_NAME, 'EventTermCountMigration recount failed for ' . $taxonomy . ': ' . $wpdb->last_error );

				return;
			}
		}

		$this->update_db_ver( 1 );
	}
}
