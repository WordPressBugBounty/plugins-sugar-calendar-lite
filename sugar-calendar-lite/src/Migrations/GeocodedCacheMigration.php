<?php

namespace Sugar_Calendar\Migrations;

/**
 * Class GeocodedCacheMigration.
 *
 * One-shot port of legacy `_transient_scgm_*` options into the new
 * `wp_sc_geocoded` table. Runs once at the next admin pageview after the
 * plugin is upgraded; subsequent invocations are no-ops thanks to the
 * version-check in the abstract.
 *
 * @since 3.11.1
 */
class GeocodedCacheMigration extends MigrationAbstract {

	/**
	 * Version of the latest migration.
	 *
	 * @since 3.11.1
	 *
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * Option key where we save the current migration version.
	 *
	 * Unique per migration class — must not collide with the orchestrator's
	 * default `sugar_calendar_migration_version` option used by
	 * `Migration` (the plugin-options migration).
	 *
	 * @since 3.11.1
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar_geocoded_migration_version';

	/**
	 * Port legacy `_transient_scgm_*` options into wp_sc_geocoded, then
	 * delete the legacy option rows.
	 *
	 * Defensive: skips rows whose decoded value isn't a well-shaped array
	 * or whose lat/lng are out of geographic range. Pre-existing poisoned
	 * values that happen to be in-range will be ported as-is (this is no
	 * worse than the status quo — the same data is currently being read
	 * back from the transient).
	 *
	 * @since 3.11.1
	 */
	protected function migrate_to_1() {

		global $wpdb;

		$table = $wpdb->prefix . 'sc_geocoded';

		// Pull legacy transient rows (the value rows, not the timeout rows).
		// The double-backslash escapes are MySQL LIKE-escape syntax.
		$rows = $wpdb->get_results(
			"SELECT option_value
			   FROM {$wpdb->options}
			  WHERE option_name LIKE '\\_transient\\_scgm\\_%'
			    AND option_name NOT LIKE '\\_transient\\_timeout\\_%'"
		);

		// Fail loud if the SELECT errored — leave the version flag untouched
		// so the migration framework retries on the next admin pageview.
		if ( ! empty( $wpdb->last_error ) ) {
			update_option( static::ERROR_OPTION_NAME, 'GeocodedCacheMigration SELECT failed: ' . $wpdb->last_error );

			return;
		}

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {

				$value = maybe_unserialize( $row->option_value );

				if (
					! is_array( $value )
					|| empty( $value['address'] )
					|| ! isset( $value['lat'], $value['lng'] )
					|| ! is_numeric( $value['lat'] )
					|| ! is_numeric( $value['lng'] )
					|| (float) $value['lat'] < -90
					|| (float) $value['lat'] > 90
					|| (float) $value['lng'] < -180
					|| (float) $value['lng'] > 180
				) {
					continue;
				}

				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table}
							( address_hash, address, lat, lng )
						 VALUES ( %s, %s, %f, %f )",
						md5( $value['address'] ),
						(string) $value['address'],
						(float) $value['lat'],
						(float) $value['lng']
					)
				);

				// Fail loud on any INSERT error — do not DELETE legacy rows
				// and do not bump the version flag. Partial migration is
				// recoverable; a flag-bump after partial insert is not.
				if ( ! empty( $wpdb->last_error ) ) {
					update_option( static::ERROR_OPTION_NAME, 'GeocodedCacheMigration INSERT failed: ' . $wpdb->last_error );

					return;
				}
			}
		}

		// Delete all legacy transient rows — value and timeout — whether or
		// not we successfully migrated them.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			  WHERE option_name LIKE '\\_transient\\_scgm\\_%'
			     OR option_name LIKE '\\_transient\\_timeout\\_scgm\\_%'"
		);

		// Fail loud if the DELETE errored — leave the version flag untouched
		// so the migration framework retries on the next admin pageview.
		if ( ! empty( $wpdb->last_error ) ) {
			update_option( static::ERROR_OPTION_NAME, 'GeocodedCacheMigration DELETE failed: ' . $wpdb->last_error );

			return;
		}

		$this->update_db_ver( 1 );
	}
}
