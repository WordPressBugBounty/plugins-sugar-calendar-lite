<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Options;

/**
 * Which Google calendars sync with which Sugar Calendar calendars, and in which direction.
 *
 * One default row comes from the Integrations settings; a snippet can replace the list
 * through the `sugar_calendar_google_calendar_mapping` filter.
 *
 * @since 3.16.0
 */
class GoogleSyncMapper {

	/**
	 * Allowed row directions.
	 *
	 * @since 3.16.0
	 *
	 * @var string[]
	 */
	const MODES = [ 'both', 'sc_to_google', 'google_to_sc' ];

	/**
	 * The mapping rows, sanitised: google_calendar_id (string), sce_calendar_id (int), sync_direction (string).
	 *
	 * `sce_calendar_id` is a calendar term id. 0 means the default behaviour: every event pushes,
	 * pulled events land in the Default Event Calendar setting.
	 *
	 * Empty without an active connection that holds the calendar scopes; push, pull and the
	 * watch channel all read this list, so that one check keeps every path off, snippet included.
	 *
	 * @since 3.16.0
	 *
	 * @return array[]
	 *
	 * @phpstan-ignore sugarCalendar.methodLength
	 */
	public static function rows(): array {

		$connection = OAuthConnectionModel::find_active_by_provider( 'google' );

		if ( $connection === null || ! ( new GoogleOAuthConnection( $connection ) )->has_calendar_access() ) {
			return [];
		}

		// Options::get() caches per request; a background run must see the latest save.
		$settings = get_option( Options::OPTION_NAME, [] );
		$settings = is_array( $settings ) ? $settings : [];

		$direction = (string) ( $settings['google_sync_direction'] ?? '' );

		// A raw read skips get_defaults(); the dropdown renders the default, so the map must too.
		if ( $direction === '' ) {
			$direction = (string) ( Options::get_defaults()['google_sync_direction'] ?? 'both' );
		}

		$calendar_id = trim( (string) ( $settings['google_calendar_id'] ?? '' ) );
		$rows        = [];

		if ( $calendar_id !== '' ) {
			$rows[] = [
				'google_calendar_id' => $calendar_id,
				'sce_calendar_id'    => 0,
				'sync_direction'     => $direction,
			];
		}

		/**
		 * Filter the Google to Sugar Calendar calendar mapping.
		 *
		 * Each row: 'google_calendar_id', 'sce_calendar_id' (calendar term id; 0 = every event on
		 * push, the Default Event Calendar on pull), 'sync_direction' (optional: both |
		 * sc_to_google | google_to_sc; missing = the Sync Settings value). Rows sharing one
		 * 'sce_calendar_id' with a push direction create one Google copy per row, one credit each.
		 * A row the account cannot sync keeps an error notice on the Integrations page until the
		 * snippet is fixed.
		 *
		 * @since 3.16.0
		 *
		 * @param array[] $rows Default: one row from the Integrations settings, or none when no calendar is saved.
		 */
		$rows = apply_filters( 'sugar_calendar_google_calendar_mapping', $rows ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return self::sanitize_rows( (array) $rows, $direction );
	}

	/**
	 * Whether a snippet supplies the mapping; the snippet then wins over the Integrations settings.
	 *
	 * @since 3.16.0
	 *
	 * @return bool
	 */
	public static function is_custom(): bool {

		return has_filter( 'sugar_calendar_google_calendar_mapping' ) !== false;
	}

	/**
	 * Drop malformed, duplicate and unknown-direction rows.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $rows      Filtered rows.
	 * @param string $direction Fallback direction.
	 *
	 * @return array[]
	 */
	private static function sanitize_rows( array $rows, string $direction ): array {

		$clean = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$google = trim( (string) ( $row['google_calendar_id'] ?? '' ) );
			$mode   = (string) ( $row['sync_direction'] ?? $direction );

			if ( $google === '' || isset( $clean[ $google ] ) || ! in_array( $mode, self::MODES, true ) ) {
				continue;
			}

			$clean[ $google ] = [
				'google_calendar_id' => $google,
				'sce_calendar_id'    => max( 0, (int) ( $row['sce_calendar_id'] ?? 0 ) ),
				'sync_direction'     => $mode,
			];
		}

		return array_values( $clean );
	}

	/**
	 * Google calendars a new event in this post should be created in.
	 *
	 * @since 3.16.0
	 *
	 * @param int $post_id Event post id.
	 *
	 * @return string[]
	 */
	public static function push_targets( int $post_id ): array {

		$terms = wp_get_object_terms( $post_id, sugar_calendar_get_calendar_taxonomy_id(), [ 'fields' => 'ids' ] );
		$terms = is_wp_error( $terms ) ? [] : array_map( 'intval', (array) $terms );

		$targets = [];

		foreach ( self::rows() as $row ) {
			if ( ! self::allows_push( $row['sync_direction'] ) ) {
				continue;
			}

			if ( $row['sce_calendar_id'] === 0 || in_array( $row['sce_calendar_id'], $terms, true ) ) {
				$targets[] = $row['google_calendar_id'];
			}
		}

		return $targets;
	}

	/**
	 * The row for one Google calendar, or null when the mapping no longer names it.
	 *
	 * @since 3.16.0
	 *
	 * @param string $google_calendar_id Google calendar id.
	 *
	 * @return array|null
	 */
	public static function row_for( string $google_calendar_id ): ?array {

		foreach ( self::rows() as $row ) {
			if ( $row['google_calendar_id'] === $google_calendar_id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Google calendars whose changes are pulled in.
	 *
	 * @since 3.16.0
	 *
	 * @return string[]
	 */
	public static function pull_calendars(): array {

		$calendars = [];

		foreach ( self::rows() as $row ) {
			if ( self::allows_pull( $row['sync_direction'] ) ) {
				$calendars[] = $row['google_calendar_id'];
			}
		}

		return $calendars;
	}

	/**
	 * The calendar term a pulled event is filed under, 0 for none.
	 *
	 * @since 3.16.0
	 *
	 * @param array|null $row Mapping row.
	 *
	 * @return int
	 */
	public static function pull_calendar_term( ?array $row ): int {

		if ( $row !== null && $row['sce_calendar_id'] > 0 ) {
			return (int) $row['sce_calendar_id'];
		}

		// "No Default" is stored as -1; the option value is a string.
		$default = (int) sugar_calendar_get_default_calendar();

		return $default > 0 ? $default : 0;
	}

	/**
	 * Whether a row direction sends Sugar Calendar changes to Google.
	 *
	 * @since 3.16.0
	 *
	 * @param string $mode Row direction.
	 *
	 * @return bool
	 */
	public static function allows_push( string $mode ): bool {

		return $mode !== 'google_to_sc';
	}

	/**
	 * Whether a row direction brings Google changes into Sugar Calendar.
	 *
	 * @since 3.16.0
	 *
	 * @param string $mode Row direction.
	 *
	 * @return bool
	 */
	public static function allows_pull( string $mode ): bool {

		return $mode !== 'sc_to_google';
	}
}
