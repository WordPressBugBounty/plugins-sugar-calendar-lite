<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use WP_Error;

/**
 * Thin $wpdb wrapper around wp_sc_oauth_connections.
 *
 * Bookings exposes this as an Eloquent-ish AbstractModel; SCE has no
 * equivalent, so we use plain prepared statements.
 *
 * @since 3.12.0
 */
class OAuthConnectionModel {

	/**
	 * Request-scoped cache for find_active_by_provider(), keyed by provider slug.
	 *
	 * The active connection is read repeatedly within a single request (the
	 * editor's provider dropdown, the create-gate, and each meeting CRUD op).
	 * Every write (insert/update/delete) flushes this, so a read-after-write in
	 * the same request still returns the fresh row.
	 *
	 * @since 3.12.0
	 *
	 * @var array<string, array|null>
	 */
	private static $active_by_provider_cache = [];

	/**
	 * Request-scoped cache for get_all_in_auth_error() (null = not yet loaded).
	 *
	 * @since 3.12.0
	 *
	 * @var array|null
	 */
	private static $auth_error_cache = null;

	/**
	 * Flush the request-scoped read caches. Called after every write so a
	 * subsequent read in the same request never sees a stale row.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	private static function flush_cache() {

		self::$active_by_provider_cache = [];
		self::$auth_error_cache         = null;
	}

	/**
	 * Fully-qualified table name.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public static function table_name() {

		global $wpdb;

		return $wpdb->prefix . 'sc_oauth_connections';
	}

	/**
	 * Insert a new connection row.
	 *
	 * @since 3.12.0
	 *
	 * @param array $data Column values keyed by column name.
	 *
	 * @return int|WP_Error Inserted ID on success.
	 */
	public static function insert( array $data ) {

		global $wpdb;

		$now = current_time( 'mysql', true );

		$row = array_merge(
			[
				'status'       => 'active',
				'token_type'   => 'bearer',
				'connected_at' => $now,
				'refreshed_at' => null,
			],
			$data
		);

		$ok = $wpdb->insert( self::table_name(), $row );

		if ( $ok === false ) {
			return new WP_Error( 'oauth_connection_insert_failed', $wpdb->last_error );
		}

		self::flush_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update columns on one row by id.
	 *
	 * Returns true on SQL success (including zero-rows-affected when the
	 * WHERE matched nothing). Only returns WP_Error on a real SQL error.
	 *
	 * @since 3.12.0
	 *
	 * @param int   $id   Row id.
	 * @param array $data Column => value.
	 *
	 * @return bool|WP_Error
	 */
	public static function update( $id, array $data ) {

		global $wpdb;

		$ok = $wpdb->update( self::table_name(), $data, [ 'id' => (int) $id ] );

		if ( $ok === false ) {
			return new WP_Error( 'oauth_connection_update_failed', $wpdb->last_error );
		}

		self::flush_cache();

		return true;
	}

	/**
	 * Delete a row by id.
	 *
	 * @since 3.12.0
	 *
	 * @param int $id Row id.
	 *
	 * @return bool
	 */
	public static function delete( $id ) {

		global $wpdb;

		$ok = (bool) $wpdb->delete( self::table_name(), [ 'id' => (int) $id ] );

		self::flush_cache();

		return $ok;
	}

	/**
	 * Delete every row for a provider (all users, any status).
	 *
	 * The connection is site-wide (one account per site), so Disconnect must
	 * clear all rows for the provider — not just the most-recent one — or a
	 * second admin's stale row is left behind and keeps the reconnect notice
	 * firing.
	 *
	 * @since 3.12.0
	 *
	 * @param string $provider Provider slug ('zoom').
	 *
	 * @return int Number of rows deleted.
	 */
	public static function delete_by_provider( $provider ) {

		global $wpdb;

		$deleted = $wpdb->delete( self::table_name(), [ 'provider' => $provider ] );

		self::flush_cache();

		return (int) $deleted;
	}

	/**
	 * Find the most recent row for (provider, user_id).
	 *
	 * @since 3.12.0
	 *
	 * @param string $provider Provider slug ('zoom').
	 * @param int    $user_id  WP user id.
	 *
	 * @return array|null
	 */
	public static function find_by_provider_and_user( $provider, $user_id ) {

		return self::find_row( 'provider = %s AND user_id = %d', [ $provider, (int) $user_id ] );
	}

	/**
	 * Find the most recent row for a provider, regardless of user or status.
	 *
	 * Site-wide (global) lookup used by the Settings UI so what an admin sees
	 * matches what ZoomIntegration::is_available() reports — the v1 connection
	 * is site-wide, not per-user.
	 *
	 * @since 3.12.0
	 *
	 * @param string $provider Provider slug ('zoom').
	 *
	 * @return array|null
	 */
	public static function find_by_provider( $provider ) {

		return self::find_row( 'provider = %s', [ $provider ] );
	}

	/**
	 * Find the first active row for a provider.
	 *
	 * @since 3.12.0
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return array|null
	 */
	public static function find_active_by_provider( $provider ) {

		if ( array_key_exists( $provider, self::$active_by_provider_cache ) ) {
			return self::$active_by_provider_cache[ $provider ];
		}

		return self::$active_by_provider_cache[ $provider ] = self::find_row(
			"provider = %s AND status = 'active'",
			[ $provider ]
		);
	}

	/**
	 * SELECT * ... ORDER BY id DESC LIMIT 1, prepared and normalized to null
	 * when no row matches.
	 *
	 * Single source of truth for the prepare()+get_row()+null-coalesce
	 * skeleton find_by_provider_and_user(), find_by_provider(), and
	 * find_active_by_provider() each repeated independently.
	 *
	 * @since 3.12.0
	 *
	 * @param string $where  WHERE clause (with %s/%d placeholders), no "WHERE " prefix.
	 * @param array  $params Values for the placeholders, in order.
	 *
	 * @return array|null
	 */
	private static function find_row( string $where, array $params ) {

		global $wpdb;

		$table = self::table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * All rows in auth_error status (for the admin notice).
	 *
	 * @since 3.12.0
	 *
	 * @return array<int, array>
	 */
	public static function get_all_in_auth_error() {

		if ( self::$auth_error_cache !== null ) {
			return self::$auth_error_cache;
		}

		global $wpdb;

		$table = self::table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s",
				'auth_error'
			),
			ARRAY_A
		);

		self::$auth_error_cache = is_array( $rows ) ? $rows : [];

		return self::$auth_error_cache;
	}

	/**
	 * All connection rows, regardless of provider or status.
	 *
	 * Used by IntegrationsDisabler to tear down every connection when the
	 * Lite kill-switch is turned on.
	 *
	 * @since 3.12.0
	 *
	 * @return array<int, array>
	 */
	public static function get_all() {

		global $wpdb;

		$table = self::table_name();

		$rows = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? $rows : [];
	}
}
