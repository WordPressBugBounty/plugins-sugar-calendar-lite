<?php

namespace Sugar_Calendar\Features\RegistrationForm;

use Sugar_Calendar\Migrations\MigrationAbstract;

/**
 * Creates wp_sc_registration_responses.
 *
 * One row per respondent (order purchaser, order attendee, or RSVP attendee);
 * answers are a JSON map keyed by schema field id.
 *
 * @since 3.13.0
 */
class ResponsesTableMigration extends MigrationAbstract {

	/**
	 * Version of the latest migration.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	const VERSION = 4;

	/**
	 * Option key where we save the current migration version.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar_registration_responses_migration_version';

	/**
	 * Option key where we save a migration error.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ERROR_OPTION_NAME = 'sugar_calendar_registration_responses_migration_error';

	/**
	 * Create wp_sc_registration_responses.
	 *
	 * @since 3.13.0
	 */
	protected function migrate_to_1() {

		global $wpdb;

		$table   = $wpdb->prefix . 'sc_registration_responses';
		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT UNSIGNED NOT NULL,
			context VARCHAR(10) NOT NULL,
			context_id BIGINT UNSIGNED NOT NULL,
			attendee_key VARCHAR(20) NULL,
			attendee_id BIGINT UNSIGNED NULL,
			answers LONGTEXT NOT NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'complete',
			reminder_sent_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			INDEX event_id (event_id),
			INDEX ctx_lookup (context, context_id),
			INDEX status_created (status, created_at)
		) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );

		if ( ! empty( $wpdb->last_error ) ) {
			update_option( static::ERROR_OPTION_NAME, 'ResponsesTableMigration failed: ' . $wpdb->last_error );

			return;
		}

		$this->update_db_ver( 1 );
	}

	/**
	 * Add attendee_key.
	 *
	 * Binds a response row to the POST attendee key it came from ('main' for the
	 * purchaser, 'a{n}' for an attendee row); attendee_id alone can't tell a
	 * purchaser from an anonymous attendee.
	 *
	 * @since 3.13.0
	 */
	protected function migrate_to_2() {

		$this->maybe_run_previous_migration( 1 );

		global $wpdb;

		$table = $wpdb->prefix . 'sc_registration_responses';

		$exists = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; schema read.
			$wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'attendee_key' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is not user input.
		);

		if ( empty( $exists ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN attendee_key VARCHAR(20) NULL AFTER context_id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; no user input.

			if ( ! empty( $wpdb->last_error ) ) {
				update_option( static::ERROR_OPTION_NAME, 'ResponsesTableMigration v2 failed: ' . $wpdb->last_error );

				return;
			}
		}

		$this->update_db_ver( 2 );
	}

	/**
	 * Make one respondent's row unique per context.
	 *
	 * Prevents concurrent submissions of the same order/RSVP from each inserting
	 * a duplicate row for the same attendee key. Existing duplicates are
	 * collapsed first, keeping the highest id; NULL attendee_key rows are left
	 * unconstrained since MySQL treats every NULL as distinct in a unique index.
	 *
	 * @since 3.13.0
	 */
	protected function migrate_to_3() {

		$this->maybe_run_previous_migration( 2 );

		global $wpdb;

		$table = $wpdb->prefix . 'sc_registration_responses';

		$exists = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; schema read.
			$wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", 'ctx_attendee' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is not user input.
		);

		if ( ! empty( $exists ) ) {
			$this->update_db_ver( 3 );

			return;
		}

		/*
		 * Collapse duplicates before the constraint can reject them.
		 *
		 * Two steps rather than one multi-table DELETE: MySQL can refuse that
		 * with "Can't reopen table" depending on how the table was created.
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is not user input, and the interpolation sits inside a multi-line string where a per-line ignore cannot reach the reported line.
		$duplicated = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT context, context_id, attendee_key, MAX(id) AS keep_id
			FROM {$table}
			WHERE attendee_key IS NOT NULL
			GROUP BY context, context_id, attendee_key
			HAVING COUNT(*) > 1"
		);

		if ( ! empty( $wpdb->last_error ) ) {
			update_option( static::ERROR_OPTION_NAME, 'ResponsesTableMigration v3 duplicate scan failed: ' . $wpdb->last_error );

			return;
		}

		foreach ( (array) $duplicated as $group ) {

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"DELETE FROM {$table}
					WHERE context = %s AND context_id = %d AND attendee_key = %s AND id <> %d",
					$group->context,
					$group->context_id,
					$group->attendee_key,
					$group->keep_id
				)
			);

			if ( ! empty( $wpdb->last_error ) ) {
				update_option( static::ERROR_OPTION_NAME, 'ResponsesTableMigration v3 dedupe failed: ' . $wpdb->last_error );

				return;
			}
		}

		$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY ctx_attendee (context, context_id, attendee_key)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $wpdb->last_error ) ) {
			update_option( static::ERROR_OPTION_NAME, 'ResponsesTableMigration v3 failed: ' . $wpdb->last_error );

			return;
		}

		$this->update_db_ver( 3 );
	}

	/**
	 * Add the after-checkout credential column.
	 *
	 * One token per order/RSVP context, written to every pending row of that
	 * context at mint; deleting the context's rows destroys the credential.
	 * Indexed, not unique, since the resume link carries only the token.
	 *
	 * @since 3.13.0
	 */
	protected function migrate_to_4() {

		$this->maybe_run_previous_migration( 3 );

		// Bail if v3 didn't finish. maybe_run_previous_migration() can't report
		// failure, so re-read the option directly rather than trust the
		// constructor-cached version.
		if ( static::get_current_version() < 3 ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'sc_registration_responses';

		$exists = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; schema read.
			$wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'token' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is not user input.
		);

		if ( empty( $exists ) ) {

			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN token VARCHAR(32) NULL AFTER attendee_id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; no user input.

			if ( ! empty( $wpdb->last_error ) ) {
				update_option( static::ERROR_OPTION_NAME, 'ResponsesTableMigration v4 failed: ' . $wpdb->last_error );

				return;
			}
		}

		$indexed = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; schema read.
			$wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", 'token' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is not user input.
		);

		if ( empty( $indexed ) ) {

			$wpdb->query( "ALTER TABLE {$table} ADD INDEX token (token)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; no user input.

			if ( ! empty( $wpdb->last_error ) ) {
				update_option( static::ERROR_OPTION_NAME, 'ResponsesTableMigration v4 index failed: ' . $wpdb->last_error );

				return;
			}
		}

		$this->update_db_ver( 4 );
	}
}
