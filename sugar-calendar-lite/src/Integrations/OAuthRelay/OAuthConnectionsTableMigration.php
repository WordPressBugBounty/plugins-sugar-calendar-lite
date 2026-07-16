<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use Sugar_Calendar\Migrations\MigrationAbstract;

/**
 * Creates wp_sc_oauth_connections table.
 *
 * Schema is identical to Bookings' wp_scb_oauth_connections (prefix-only
 * change). See spec §4 for column documentation.
 *
 * @since 3.12.0
 */
class OAuthConnectionsTableMigration extends MigrationAbstract {

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
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar_oauth_connections_migration_version';

	/**
	 * Create wp_sc_oauth_connections.
	 *
	 * @since 3.12.0
	 */
	protected function migrate_to_1() {

		global $wpdb;

		$table   = $wpdb->prefix . 'sc_oauth_connections';
		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			provider VARCHAR(20) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			access_token TEXT NOT NULL,
			refresh_token TEXT NOT NULL,
			expires_at DATETIME NOT NULL,
			token_type VARCHAR(20) NOT NULL DEFAULT 'bearer',
			scope TEXT NOT NULL,
			app_id INT UNSIGNED NOT NULL,
			account_id VARCHAR(100) NOT NULL,
			account_email VARCHAR(255) NOT NULL,
			account_name VARCHAR(255) NULL,
			account_avatar VARCHAR(500) NULL,
			connected_at DATETIME NOT NULL,
			refreshed_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_provider_app_account (user_id, provider, app_id, account_id),
			INDEX provider (provider),
			INDEX status (status),
			INDEX idx_provider_status (provider, status)
		) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );

		if ( ! empty( $wpdb->last_error ) ) {
			update_option( static::ERROR_OPTION_NAME, 'OAuthConnectionsTableMigration failed: ' . $wpdb->last_error );

			return;
		}

		$this->update_db_ver( 1 );
	}
}
