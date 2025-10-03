<?php

namespace Sugar_Calendar\Block\Common;

use DateTime;
use DateTimeZone;
use Sugar_Calendar\Event;

/**
 * Timezone Conversion Helper.
 *
 * Static helper class to centralize timezone conversion logic across calendar views.
 * Caller is responsible for checking if timezone conversion is enabled before using these methods.
 *
 * @since 3.9.0
 */
class TimezoneConversionHelper {

	/**
	 * Check if an event is multi-day in the given timezone.
	 *
	 * @since 3.9.0
	 *
	 * @param Event        $event    The event to check.
	 * @param DateTimeZone $timezone The timezone to check against.
	 *
	 * @return bool True if event spans multiple days in the given timezone.
	 */
	public static function is_multi_day_in_timezone( Event $event, DateTimeZone $timezone ) {

		// Return false if either DTO is null - can't determine multi-day status.
		if ( $event->start_dto === null || $event->end_dto === null ) {
			return false;
		}

		// Convert event start and end to the given timezone.
		$event_start = clone $event->start_dto;
		$event_end   = clone $event->end_dto;

		$event_start->setTimezone( $timezone );
		$event_end->setTimezone( $timezone );

		// Check if they're on different days.
		return $event_start->format( 'Y-m-d' ) !== $event_end->format( 'Y-m-d' );
	}

	/**
	 * Convert event start date to the given timezone.
	 *
	 * @since 3.9.0
	 *
	 * @param Event        $event    The event.
	 * @param DateTimeZone $timezone The target timezone.
	 *
	 * @return DateTime|null Event start date in the given timezone, or null if start_dto is null.
	 */
	public static function convert_event_start( Event $event, DateTimeZone $timezone ) {

		// Return null if start_dto is null.
		if ( $event->start_dto === null ) {
			return null;
		}

		$event_start = clone $event->start_dto;

		$event_start->setTimezone( $timezone );

		return $event_start;
	}

	/**
	 * Convert event end date to the given timezone.
	 *
	 * @since 3.9.0
	 *
	 * @param Event        $event    The event.
	 * @param DateTimeZone $timezone The target timezone.
	 *
	 * @return DateTime|null Event end date in the given timezone, or null if end_dto is null.
	 */
	public static function convert_event_end( Event $event, DateTimeZone $timezone ) {

		// Return null if end_dto is null.
		if ( $event->end_dto === null ) {
			return null;
		}

		$event_end = clone $event->end_dto;

		$event_end->setTimezone( $timezone );

		return $event_end;
	}

	/**
	 * Create a DateTime object for a date string in the given timezone.
	 *
	 * @since 3.9.0
	 *
	 * @param string       $date_string Date string in 'Y-m-d' format.
	 * @param DateTimeZone $timezone    The target timezone.
	 *
	 * @return DateTime DateTime object in the given timezone.
	 */
	public static function create_date_in_timezone( $date_string, DateTimeZone $timezone ) {

		return DateTime::createFromFormat( 'Y-m-d|', $date_string, $timezone );
	}

	/**
	 * Create a timezone-converted event object for output purposes.
	 *
	 * This method clones the original event and converts its start and end dates
	 * to the given timezone. Useful for passing to functions like
	 * Helpers::get_event_time_output() that expect Event objects.
	 *
	 * @since 3.9.0
	 *
	 * @param Event        $event    The original event to convert.
	 * @param DateTimeZone $timezone The target timezone.
	 *
	 * @return Event Event object with timezone-converted dates.
	 */
	public static function create_timezone_converted_event_for_output( Event $event, DateTimeZone $timezone ) {

		// Clone the original event to avoid modifying it.
		$event_for_output = clone $event;

		// Convert start and end dates to the target timezone.
		$event_for_output->start_dto = self::convert_event_start( $event, $timezone );
		$event_for_output->end_dto   = self::convert_event_end( $event, $timezone );

		return $event_for_output;
	}
}
