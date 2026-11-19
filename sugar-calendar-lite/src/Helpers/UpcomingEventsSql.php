<?php

namespace Sugar_Calendar\Helpers;

/**
 * The upcoming events queries' current time and opt-in SQL, shared by Lite's query and Pro's recurring one.
 *
 * @since 3.18.0
 */
class UpcomingEventsSql {

	/**
	 * The queries' current time, `Y-m-d H:i:s`: in Sugar Calendar's time zone, or UTC on the site clock.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public static function get_now(): string {

		return (string) sugar_calendar_get_request_time( 'mysql', self::is_site_clock() ? 'UTC' : sugar_calendar_get_timezone() );
	}

	/**
	 * `AND <column> >= <start of today>` when the caller passed `'starting_from_today' => true`,
	 * else nothing. The end filter alone keeps an event that started on an earlier day while it runs.
	 *
	 * @since 3.18.0
	 *
	 * @param array  $args   The query args.
	 * @param string $now    The query's current time, from `get_now()`.
	 * @param string $column The start column, table-qualified.
	 *
	 * @return string
	 */
	public static function starting_from_today( array $args, string $now, string $column ): string {

		global $wpdb;

		if ( ( $args['starting_from_today'] ?? false ) !== true ) {
			return '';
		}

		// On the site clock `$now` is UTC, so today is the site's date.
		$today = self::is_site_clock() ? wp_date( 'Y-m-d' ) : substr( $now, 0, 10 );

		return ' AND ' . $column . $wpdb->prepare( ' >= %s', $today . ' 00:00:00' );
	}

	/**
	 * Whether event times are the site's wall clock: Sugar Calendar's time zones are off, or on with no
	 * default zone. Not `sugar_calendar_is_timezone_floating()`, which reads the saved type, not the filtered one.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	private static function is_site_clock(): bool {

		return sugar_calendar_get_timezone_type() === 'off' || empty( sugar_calendar_get_timezone() );
	}
}
