<?php

namespace Sugar_Calendar\Admin\Dashboard;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The Dashboard's date ranges: presets, a range read from a request, and the period before one.
 *
 * A range is `[ from, to ]`, both `Y-m-d` in the site's time zone, both days included.
 *
 * @since 3.18.0
 */
class DateRange {

	/**
	 * Days in the default preset.
	 *
	 * @since 3.18.0
	 */
	const DEFAULT_RANGE_DAYS = 7;

	/**
	 * Preset day counts offered by the range control.
	 *
	 * @since 3.18.0
	 */
	const PRESETS = [ 7, 14, 30 ];

	/**
	 * Text between the two dates of a `"Y-m-d - Y-m-d"` range.
	 *
	 * @since 3.18.0
	 */
	const RANGE_SEPARATOR = ' - ';

	/**
	 * Longest custom range, in days, both ends counted.
	 *
	 * @since 3.18.0
	 */
	const MAX_RANGE_DAYS = 366;

	/**
	 * How the range control shows a date. PHP's date() and flatpickr read these tokens alike, so
	 * the server's text is the text the picker writes.
	 *
	 * @since 3.18.0
	 */
	const DISPLAY_FORMAT = 'M j, Y';

	/**
	 * The `[ from, to ]` bounds of a preset: N days ending today, site timezone.
	 *
	 * Today is included, so same-day sales show at once. The range control's JS resolves presets
	 * the same way.
	 *
	 * @since 3.18.0
	 *
	 * @param int $days Preset day count.
	 *
	 * @return array `[ from, to ]`, both `Y-m-d`.
	 */
	public static function get_preset_range( int $days ): array {

		$to   = new DateTimeImmutable( 'today', wp_timezone() );
		$from = $to->modify( '-' . ( $days - 1 ) . ' days' );

		return [ $from->format( 'Y-m-d' ), $to->format( 'Y-m-d' ) ];
	}

	/**
	 * Parse a `"Y-m-d - Y-m-d"` range, or null when it is invalid.
	 *
	 * Invalid means malformed, reversed, ending after today, or longer than MAX_RANGE_DAYS.
	 *
	 * @since 3.18.0
	 *
	 * @param string $raw Range string.
	 *
	 * @return array|null `[ from, to ]`.
	 */
	public static function parse_range( string $raw ): ?array {

		$parts = array_map( 'trim', explode( self::RANGE_SEPARATOR, $raw ) );

		if ( count( $parts ) !== 2 ) {
			return null;
		}

		[ $from, $to ] = $parts;

		if ( ! self::is_valid_date( $from ) || ! self::is_valid_date( $to ) || $from > $to ) {
			return null;
		}

		if ( $to > self::get_preset_range( 1 )[1] ) {
			return null;
		}

		if ( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS + 1 > self::MAX_RANGE_DAYS ) {
			return null;
		}

		return [ $from, $to ];
	}

	/**
	 * The period of the same length directly before a range.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array `[ from, to ]`.
	 */
	public static function get_previous_range( array $range ): array {

		// Calendar maths in UTC: a DST change can skip a local midnight, which shortens the count.
		$from = new DateTimeImmutable( $range[0], new DateTimeZone( 'UTC' ) );
		$to   = new DateTimeImmutable( $range[1], new DateTimeZone( 'UTC' ) );
		$days = (int) $from->diff( $to )->days + 1;

		return [
			$from->modify( "-{$days} days" )->format( 'Y-m-d' ),
			$from->modify( '-1 day' )->format( 'Y-m-d' ),
		];
	}

	/**
	 * The UTC instants of the local midnight opening a range and the one after it closes.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array `[ start, end ]`, UTC `Y-m-d H:i:s`, end exclusive.
	 */
	public static function get_utc_window( array $range ): array {

		// The next calendar date, then its local midnight (or its first instant, when DST skips midnight).
		$utc   = new DateTimeZone( 'UTC' );
		$next  = ( new DateTimeImmutable( $range[1], $utc ) )->modify( '+1 day' )->format( 'Y-m-d' );
		$start = new DateTimeImmutable( $range[0], wp_timezone() );
		$end   = new DateTimeImmutable( $next, wp_timezone() );

		return [
			$start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			$end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
		];
	}

	/**
	 * The title bar's range control for a range. A range that equals a preset selects that preset.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return array `options` (preset days and `custom` => label), `active` and `custom` (the range as the control shows it, when custom).
	 */
	public static function get_control_state( array $range ): array {

		$active  = 'custom';
		$options = [];

		foreach ( self::PRESETS as $days ) {
			$options[ $days ] = sprintf(
				/* translators: %d: number of days. */
				_n( 'Last %d Day', 'Last %d Days', $days, 'sugar-calendar-lite' ),
				$days
			);

			if ( $range === self::get_preset_range( $days ) ) {
				$active = (string) $days;
			}
		}

		$options['custom'] = __( 'Custom Range', 'sugar-calendar-lite' );

		return [
			'options' => $options,
			'active'  => $active,
			'custom'  => $active === 'custom' ? self::format_range( $range ) : '',
		];
	}

	/**
	 * A range in DISPLAY_FORMAT, written as flatpickr writes it: a one-day range as a single date.
	 *
	 * @since 3.18.0
	 *
	 * @param array $range Range, `[ from, to ]`.
	 *
	 * @return string
	 */
	private static function format_range( array $range ): string {

		$zone  = wp_timezone();
		$dates = array_map(
			static function ( $date ) use ( $zone ) {

				return wp_date( self::DISPLAY_FORMAT, ( new DateTimeImmutable( $date, $zone ) )->getTimestamp() );
			},
			$range
		);

		return implode( self::RANGE_SEPARATOR, array_unique( $dates ) );
	}

	/**
	 * The range picker's flatpickr locale: WordPress's translated month and weekday names, so a picked
	 * range reads `Oct 1, 2026` in the site's language, and Sugar Calendar's Start of Week, which the
	 * event editor's picker reads too.
	 *
	 * @since 3.18.0
	 *
	 * @return array `months` and `weekdays` (`longhand`, `shorthand`) and `firstDayOfWeek`.
	 */
	public static function get_picker_locale(): array {

		global $wp_locale;

		$months   = array_map( [ $wp_locale, 'get_month' ], range( 1, 12 ) );
		$weekdays = array_map( [ $wp_locale, 'get_weekday' ], range( 0, 6 ) );

		return [
			'months'         => [
				'longhand'  => $months,
				'shorthand' => array_map( [ $wp_locale, 'get_month_abbrev' ], $months ),
			],
			'weekdays'       => [
				'longhand'  => $weekdays,
				'shorthand' => array_map( [ $wp_locale, 'get_weekday_abbrev' ], $weekdays ),
			],
			'firstDayOfWeek' => (int) sugar_calendar_get_user_preference( 'start_of_week' ),
		];
	}

	/**
	 * Whether a string is a real `Y-m-d` date.
	 *
	 * @since 3.18.0
	 *
	 * @param string $date Date string.
	 *
	 * @return bool
	 */
	private static function is_valid_date( string $date ): bool {

		$parsed = DateTime::createFromFormat( 'Y-m-d', $date );

		return $parsed !== false && $parsed->format( 'Y-m-d' ) === $date;
	}
}
