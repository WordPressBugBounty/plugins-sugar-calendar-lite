<?php

namespace Sugar_Calendar\Helpers;

/**
 * A time printed beside its own time zone, as the Events list and the Dashboard print it.
 *
 * @since 3.18.0
 */
class TimezoneFormat {

	/**
	 * Remove every time zone character from a date or time format.
	 *
	 * For a time printed beside its own zone. Unlike `Helpers::maybe_remove_timezone_format()`, it
	 * strips them for every event.
	 *
	 * @since 3.18.0
	 *
	 * @param string $format Date or time format.
	 *
	 * @return string
	 */
	public static function strip( $format = '' ) {

		return str_replace( [ 'e', 'I', 'O', 'P', 'T', 'Z' ], '', $format );
	}

	/**
	 * A time followed by its zone's abbreviation.
	 *
	 * @since 3.18.0
	 *
	 * @param string $time     Formatted time.
	 * @param string $timezone Time zone identifier.
	 *
	 * @return string
	 */
	public static function append( $time, $timezone ) {

		/* translators: %1$s - Datetime; %2$s - Timezone. */
		return sprintf( _x( '%1$s %2$s', 'Time Time Zone', 'sugar-calendar-lite' ), $time, sugar_calendar_format_timezone( $timezone, true ) );
	}
}
