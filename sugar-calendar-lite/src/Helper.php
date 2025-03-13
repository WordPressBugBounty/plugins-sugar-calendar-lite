<?php

namespace Sugar_Calendar;

use DateTimeInterface;
use Sugar_Calendar\Block\Calendar\CalendarView\Day\Day;

class Helper {

	public static function get_month_from_number( $number ) {

		$month = [
			1  => 'January',
			2  => 'February',
			3  => 'March',
			4  => 'April',
			5  => 'May',
			6  => 'June',
			7  => 'July',
			8  => 'August',
			9  => 'September',
			10 => 'October',
			11 => 'November',
			12 => 'December',
		];

		return isset( $month[ $number ] ) ? $month[ $number ] : '';
	}

	/**
	 * Get the month number from the month abbreviation.
	 *
	 * @since 3.0.0
	 *
	 * @param string $abbrev Month abbreviation.
	 *
	 * @return int
	 */
	public static function get_month_number_from_abbrev( $abbrev ) {

		$month = [
			'jan' => 1,
			'feb' => 2,
			'mar' => 3,
			'apr' => 4,
			'may' => 5,
			'jun' => 6,
			'jul' => 7,
			'aug' => 8,
			'sep' => 9,
			'oct' => 10,
			'nov' => 11,
			'dec' => 12,
		];

		$abbrev = strtolower( $abbrev );

		return isset( $month[ $abbrev ] ) ? $month[ $abbrev ] : 0;
	}

	/**
	 * Get the calendar ID of an event.
	 *
	 * @since 3.0.0
	 *
	 * @param Event $event Event object.
	 *
	 * @return \WP_Term[] Returns `0` if the calendar ID is not found.
	 */
	public static function get_calendars_of_event( $event ) {

		// Get the Calendar ID of the event.
		$calendars = wp_get_post_terms(
			$event->object_id,
			'sc_event_category'
		);

		if (
			empty( $calendars ) ||
			! is_array( $calendars )
		) {
			return [];
		}

		return $calendars;
	}

	/**
	 * Returns an array containing the year, month, day of a given string.
	 *
	 * @since 3.0.0
	 *
	 * @param string $yyyymmdd Date in 'YYYY-mm-dd' format.
	 *
	 * @return string[]
	 */
	public static function get_info_from_yyyymmdd( $yyyymmdd ) {

		$date_arr = explode( '-', $yyyymmdd );

		if ( ! $date_arr ) {
			return [
				'year'  => '',
				'month' => '',
				'day'   => '',
			];
		}

		return [
			'year'  => $date_arr[0],
			'month' => ltrim( $date_arr[1], '0' ),
			'day'   => ltrim( $date_arr[2], '0' ),
		];
	}

	/**
	 * Gets Events for a specific day, month, and year, from an array of Events.
	 *
	 * This return the events in this order:
	 * 1. Multi-day events.
	 * 2. All day events.
	 * 3. Simple events (rest of the events).
	 *
	 * @since 3.0.0
	 * @since 3.1.2 Added the `$timezone` parameter.
	 * @since 3.2.0 Sorted the simple events by start date.
	 *
	 * @param Event[]             $events   Events for the day.
	 * @param string              $day      Day.
	 * @param string              $month    Month.
	 * @param string              $year     Year.
	 * @param false|\DateTimeZone $timezone Timezone to convert the events' datetime.
	 *
	 * @return Event[]
	 */
	public static function filter_events_by_day( $events, $day = '01', $month = '01', $year = '1970', $timezone = false ) {

		$multi_day = [];
		$all_day   = [];
		$simple    = [];

		foreach ( $events as $event ) {

			if ( ! sc_is_event_for_day( $event, $day, $month, $year, $timezone ) ) {
				continue;
			}

			if ( $event->is_multi() ) {
				$multi_day[] = $event;
			} elseif ( $event->is_all_day() ) {
				$all_day[] = $event;
			} else {
				$simple[] = $event;
			}
		}

		if ( ! empty( $simple ) ) {
			// Sort the simple events by start date.
			usort(
				$simple,
				function ( $a, $b ) {
					return $a->start_dto <=> $b->start_dto;
				}
			);
		}

		return array_merge( $multi_day, $all_day, $simple );
	}

	/**
	 * Get the weekday abbreviation by the weekday number.
	 *
	 * @since 3.0.0
	 *
	 * @param int $weekday_num Weekday number.
	 *
	 * @return string
	 */
	public static function get_weekday_abbrev_by_number( $weekday_num ) {

		global $wp_locale;

		return $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $weekday_num ) );
	}

	/**
	 * Get the event offset width.
	 *
	 * This method returns the `width` in terms of days in the
	 * calendar the event should span and if the event overflows
	 * to next week.
	 *
	 * @since 3.0.0
	 *
	 * @param DateTimeInterface $start_date          Start date of the event.
	 * @param DateTimeInterface $end_date            End date of the event.
	 * @param int               $day_of_the_week_num Day of the week the event started.
	 *
	 * @return array
	 */
	public static function get_event_offset_width( $start_date, $end_date, $day_of_the_week_num ) {

		$is_week_overflow      = false;
		$duration_of_the_event = $start_date->diff( $end_date )->format( '%a' );
		$remaining_days        = 7 - $day_of_the_week_num;

		if ( ( $remaining_days - $duration_of_the_event ) < 0 ) {
			/*
			 * If we are here then it means that the multi-event overflows to next week.
			 * We don't want to have it to overflow outside of the week calendar.
			 * So we will only span it to the rest of the week.
			 */
			$duration_of_the_event = $remaining_days;
			$is_week_overflow      = true;
		}

		/*
		 * We add `1` to include the start date.
		 */
		return [
			'width'            => ( $duration_of_the_event > 7 ) ? 7 : $duration_of_the_event + 1,
			'is_week_overflow' => $is_week_overflow,
		];
	}

	/**
	 * Get the information of the calendars associated with an event.
	 *
	 * The returned array contains the name and color of the calendars.
	 *
	 * @since 3.0.0
	 *
	 * @param Event $event Event object.
	 *
	 * @return string[]
	 */
	public static function get_calendars_info_of_event( $event ) {

		$calendars = self::get_calendars_of_event( $event );

		if ( empty( $calendars ) ) {
			return [];
		}

		$calendars_info = [];

		foreach ( $calendars as $cal ) {
			$calendars_info['calendars'][] = [
				'name'  => $cal->name,
				'color' => sugar_calendar_get_calendar_color( $cal->term_id ),
			];
		}

		return $calendars_info;
	}

	/**
	 * Returns the morning, afternoon, evening, night, hour division range.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public static function get_day_time_division() {

		static $day_time_div;

		if ( $day_time_div ) {
			return $day_time_div;
		}

		$day_time_div = [
			'early_night' => range( 0, 5 ),
			'morning'     => range( 6, 11 ),
			'afternoon'   => range( 12, 17 ),
			'evening'     => range( 18, 23 ),
			'late_night'  => range( 21, 23 ),
		];

		return $day_time_div;
	}

	/**
	 * Get the event's day division.
	 *
	 *  "Morning" - 6:01 AM to 12:00 noon
	 *  "Afternoon" - 12:01 PM to 6:00 PM
	 *  "Evening" - 6:01 PM to 00:00 PM midnight
	 *  "Night" - 9:01 PM to 6:00 AM.
	 *
	 * @since 3.0.0
	 *
	 * @param Event $event Event object.
	 *
	 * @return string[]
	 */
	public static function get_time_day_division_of_event( $event ) {

		if ( $event->is_all_day() ) {
			return [
				'all_day',
			];
		}

		// Let's get the meridian of the start and end time of the event.
		$start_hour = absint( $event->start_dto->format( 'G' ) );
		$end_hour   = absint( $event->end_dto->format( 'G' ) );

		$event_range_in_hours = range( $start_hour, $end_hour );
		$event_day_division   = [];

		foreach ( self::get_day_time_division() as $div_key => $div_range ) {

			$inter = array_intersect( $event_range_in_hours, $div_range );

			if ( ! empty( $inter ) ) {

				if ( $div_key === 'early_night' || $div_key === 'late_night' ) {
					$div_key = 'night';
				}

				$event_day_division[] = $div_key;
			}
		}

		return $event_day_division;
	}

	/**
	 * Get the event excerpt.
	 *
	 * @since 3.0.0
	 *
	 * @param Event  $event        Event object.
	 * @param int    $num_words    Number of words to return.
	 * @param string $excerpt_more More text to append to the excerpt.
	 *
	 * @return string
	 */
	public static function get_event_excerpt( $event, $num_words = 20, $excerpt_more = '...' ) {

		if ( empty( $event->content ) ) {
			return '';
		}

		$text = strip_shortcodes( $event->content );
		$text = excerpt_remove_blocks( $text );
		$text = excerpt_remove_footnotes( $text );
		$text = str_replace( ']]>', ']]&gt;', $text );

		return wp_trim_words( $text, $num_words, $excerpt_more );
	}

	/**
	 * Get the event time recurrence class.
	 *
	 * @since 3.0.0
	 *
	 * @param Event $event Event object.
	 *
	 * @return string
	 */
	public static function get_event_time_recur_class( $event ) {

		return ! empty( $event->recurrence ) ? 'sugar-calendar-block__event-cell__time--recur' : '';
	}

	/**
	 * Get the formatted events with overlap.
	 *
	 * This method accepts an array of events for a Day then returns the events
	 * with the overlap count for each of the event.
	 *
	 * @since 3.0.0
	 *
	 * @param Event[] $events Events to format.
	 *
	 * @return Event[]
	 */
	public static function get_formatted_events_with_overlap( $events ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$formatted_events = [];

		// Holds the start and end times of each of the events for the day.
		$events_start_and_end_times = [];

		// Sort the events by start hour.
		uasort(
			$events,
			function ( $a, $b ) {
				return ( 1 * $a->start_date( 'G' ) ) < ( 1 * $b->start_date( 'G' ) ) ? -1 : 1;
			}
		);

		foreach ( $events as $event ) {
			// Number of times the event overlaps with other events.
			$overlap_count = 0;

			$start_hour = 1 * $event->start_date_dto( 'G' );
			$start_min  = 1 * $event->start_date_dto( 'i' );
			$end_hour   = 1 * $event->end_date_dto( 'G' );
			$end_min    = 1 * $event->end_date_dto( 'i' );

			foreach ( $events_start_and_end_times as $event_time ) {
				if ( $start_hour >= $event_time['start_hour'] && $end_hour <= $event_time['end_hour'] ) {
					++$overlap_count;
				} elseif ( $start_hour < $event_time['end_hour'] ) {
					++$overlap_count;
				}
			}

			$events_start_and_end_times[] = [
				'start_hour' => $start_hour,
				'start_min'  => $start_min,
				'end_hour'   => $end_hour,
				'end_min'    => $end_min,
			];

			$event->overlap_count = $overlap_count;

			foreach ( Day::get_division_by_hour() as $hour_int => $hour_name ) {

				if ( $hour_int === $start_hour ) {
					$nearest_five_mins = absint( round( $start_min / 5 ) * 5 );

					$formatted_events[ $hour_int ][ $nearest_five_mins ][] = $event;
				}
			}
		}

		return $formatted_events;
	}

	/**
	 * Get the event frontend URL.
	 *
	 * @since 3.6.0
	 *
	 * @param Event $event Event object.
	 *
	 * @return string|false Returns the event URL or `false` if the event does not exists.
	 */
	public static function get_event_frontend_url( $event ) {

		if ( empty( $event ) ) {
			return false;
		}

		/**
		 * Filters the frontend URL of a non-standard event.
		 *
		 * @since 3.6.0
		 *
		 * @param string|false $url   URL of the event.
		 * @param Event        $event Event object.
		 */
		$url = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_get_non_standard_event_frontend_url',
			false,
			$event
		);

		// Early return if already set by the filter.
		if ( ! empty( $url ) ) {
			return $url;
		}

		return get_permalink( $event->object_id );
	}
}
