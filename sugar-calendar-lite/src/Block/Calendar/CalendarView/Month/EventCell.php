<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Month;

use DateTime;
use Sugar_Calendar\Block\Common\InterfaceView;
use Sugar_Calendar\Block\Common\Template;
use Sugar_Calendar\Block\Common\TimezoneConversionHelper;
use Sugar_Calendar\Event;
use Sugar_Calendar\Helper;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Options;

/**
 * Class EventCell.
 *
 * Handles the Event Cell inside the Month view.
 *
 * @since 3.0.0
 */
class EventCell implements InterfaceView {

	/**
	 * The event.
	 *
	 * @since 3.0.0
	 *
	 * @var Event
	 */
	private $event;

	/**
	 * The date of the cell.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	private $cell_date;

	/**
	 * The calendar info.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $calendar_info;

	/**
	 * Get the calendars info of the event.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $calendar_category_info;

	/**
	 * The block instance.
	 *
	 * @since 3.9.0
	 *
	 * @var AbstractBlock|null
	 */
	private $block;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 * @since 3.9.0 Make block instance available.
	 *
	 * @param Event              $event         The event.
	 * @param string             $cell_date     The date of the cell.
	 * @param array              $calendar_info The calendar info.
	 * @param AbstractBlock|null $block         Block instance for timezone access.
	 */
	public function __construct( $event, $cell_date, $calendar_info = [], $block = null ) {

		$this->event         = $event;
		$this->cell_date     = $cell_date;
		$this->calendar_info = $calendar_info;
		$this->block         = $block;
	}

	/**
	 * Render the event cell.
	 *
	 * @since 3.0.0
	 */
	public function render() {

		Template::load( 'month.event-cell', $this );
	}

	/**
	 * Get the Event object.
	 *
	 * @since 3.0.0
	 *
	 * @return Event
	 */
	public function get_event() {

		return $this->event;
	}

	/**
	 * Get the DOM classes of the event.
	 *
	 * @since 3.0.0
	 * @since 3.9.0 Adjustments for timezone conversion.
	 *
	 * @return string[]
	 */
	public function get_event_classes() {

		$classes = [
			'sugar-calendar-block__event-cell',
			"sugar-calendar-block__calendar-month__body__day__events-container__event-id-{$this->get_event()->id}",
		];

		// We hide the events initially if it's an AJAX request and let the JS handle which ones to display.
		if ( ! empty( $this->calendar_info['from_ajax'] ) && $this->calendar_info['from_ajax'] ) {
			$classes[] = 'sugar-calendar-block__calendar-month__cell-hide';
		}

		if ( ! empty( $this->get_event()->recurrence ) ) {
			$classes[] = 'sugar-calendar-block__calendar-month__body__day__events-container__event-recur';
		}

		// Check if event is multi-day using visitor timezone when available.
		// All-day events should use original logic regardless of timezone conversion.
		$visitor_timezone = $this->block ? $this->block->get_visitor_timezone() : false;

		$is_multi_day = ( $this->get_event()->is_all_day() || ! $visitor_timezone )
			? $this->get_event()->is_multi()
			: TimezoneConversionHelper::is_multi_day_in_timezone( $this->get_event(), $visitor_timezone );

		if ( ! $is_multi_day ) {
			return $classes;
		}

		// Get event start date in visitor timezone when available.
		// All-day events should use original dates regardless of timezone conversion.
		$event_start_date = ( $this->get_event()->is_all_day() || ! $visitor_timezone )
			? $this->get_event()->start_dto
			: TimezoneConversionHelper::convert_event_start( $this->get_event(), $visitor_timezone );

		if ( $event_start_date->format( 'Y-m-d' ) === $this->cell_date ) {
			$classes[] = 'sugar-calendar-block__calendar-month__body__day__events-container__event-multi-day-start';
			$classes   = array_merge( $classes, $this->get_multi_day_duration_classes( $event_start_date ) );
		} elseif ( ! isset( $this->calendar_info['events_displayed_in_the_week'][ $this->get_event()->id ] ) ) {

			$classes[] = 'sugar-calendar-block__calendar-month__body__day__events-container__event-multi-day-start-overflow';

			$cell_date_obj = ( $this->get_event()->is_all_day() || ! $visitor_timezone )
				? DateTime::createFromFormat( 'Y-m-d|', $this->cell_date )
				: TimezoneConversionHelper::create_date_in_timezone( $this->cell_date, $visitor_timezone );

			$classes = array_merge( $classes, $this->get_multi_day_duration_classes( $cell_date_obj ) );
		} else {
			$classes[] = 'sugar-calendar-block__calendar-month__body__day__events-container__event-multi-day-overflow';
		}

		return $classes;
	}

	/**
	 * Get the multi-day duration classes.
	 *
	 * @since 3.0.0
	 * @since 3.5.1 Fixed issue with multi-day events overflow.
	 * @since 3.9.0 Adjustments for timezone conversion.
	 *
	 * @param DateTime $start_date The start date we will display the multi-day event.
	 *
	 * @return string[]
	 */
	private function get_multi_day_duration_classes( $start_date ) {

		$classes = [];

		// All-day events should not be affected by timezone conversion for duration calculation.
		// They represent calendar days, not specific times.
		$visitor_timezone = $this->block ? $this->block->get_visitor_timezone() : false;

		if ( $this->get_event()->is_all_day() || ! $visitor_timezone ) {
			// Use original dates for all-day events or when no timezone conversion.
			$event_end  = clone $this->get_event()->end_dto;
			$start_date = clone $start_date;
		} else {
			// Use timezone-converted dates for timed events only.
			$event_end  = TimezoneConversionHelper::convert_event_end( $this->get_event(), $visitor_timezone );
			$start_date = clone $start_date;

			$start_date->setTimezone( $visitor_timezone );
		}

		// Remove the time part.
		$start_date->setTime( 0, 0, 0 );
		$event_end->setTime( 0, 0, 0 );

		// Calculate the full-day difference.
		$date_diff = $event_end->diff( $start_date );

		$duration = absint( $date_diff->format( '%a' ) );

		if (
			$duration === 7 &&
			( $date_diff->h > 0 || $date_diff->i > 0 )
		) {
			++$duration;
		}

		// Calculate the remaining days in the current week.
		$remaining = 7 - $this->calendar_info['days_of_week_ctr'];

		if ( ( $remaining - $duration ) < 0 ) {
			/*
			 * If we are here then it means that the multi-event overflows to next week.
			 * We don't want to have it to overflow outside of the week calendar.
			 * So we will only span it to the rest of the week.
			 */
			$duration = $remaining;

			$classes[] = 'sugar-calendar-block__calendar-month__body__day__events-container__event-multi-day-overflow-week';
		}

		// Since we are displaying the calendar by week, we only need to know a max of 7 days duration.
		// We also add 1 today current day.
		$classes[] = sprintf(
			'sugar-calendar-block__calendar-month__body__day__events-container__event-multi-day-%d',
			( $duration > 7 ) ? 7 : $duration + 1
		);

		return $classes;
	}

	/**
	 * Get the event styles.
	 *
	 * @since 3.0.0
	 * @since 3.9.0 Adjustments for timezone conversion.
	 *
	 * @return string
	 */
	public function get_event_style() {

		$primary_event_color = $this->get_calendars_category_info()['primary_event_color'];

		if ( empty( $primary_event_color ) ) {
			$primary_event_color = $this->get_accent_color();
		}

		$styles                 = [];
		$styles['border-color'] = $primary_event_color;

		// Check if event is multi-day using visitor timezone when available.
		// All-day events should use original logic regardless of timezone conversion.
		$visitor_timezone = $this->block ? $this->block->get_visitor_timezone() : false;

		$is_multi_day = ( $this->get_event()->is_all_day() || ! $visitor_timezone )
			? $this->get_event()->is_multi()
			: TimezoneConversionHelper::is_multi_day_in_timezone( $this->get_event(), $visitor_timezone );

		if ( $is_multi_day ) {
			$styles['background'] = $primary_event_color;
		}

		$style_string = '';

		foreach ( $styles as $key => $value ) {
			$style_string .= "{$key}: {$value};";
		}

		return $style_string;
	}

	/**
	 * Get the calendars category info of the event.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_calendars_category_info() {

		if ( ! empty( $this->calendar_category_info ) ) {
			return $this->calendar_category_info;
		}

		// Get the calendars associated with the event.
		$calendars = Helper::get_calendars_of_event( $this->get_event() );

		if ( empty( $calendars ) ) {
			return [
				'primary_event_color' => $this->get_accent_color(),
			];
		}

		$calendars_info = [];

		foreach ( $calendars as $cal ) {
			$calendars_info['calendars'][] = [
				'name'  => $cal->name,
				'color' => sugar_calendar_get_calendar_color( $cal->term_id ),
			];
		}

		$calendars_info['primary_event_color'] = ! empty( $calendars_info['calendars'][0]['color'] ) ? $calendars_info['calendars'][0]['color'] : $this->get_accent_color();

		return $calendars_info;
	}

	/**
	 * Get the accent color.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	private function get_accent_color() {

		if ( ! empty( $this->calendar_info['accentColor'] ) ) {
			return $this->calendar_info['accentColor'];
		}

		return '';
	}

	/**
	 * Get the event day duration.
	 *
	 * @since 3.0.0
	 * @since 3.1.2 Return the wp_json_encoded string.
	 * @since 3.9.0 Adjustments for timezone conversion.
	 *
	 * @return string
	 */
	public function get_event_day_duration() {

		$date_format = Options::get( 'date_format' );

		// Check if we should use timezone conversion for this event.
		// All-day events should use original logic regardless of timezone conversion.
		$visitor_timezone = $this->block ? $this->block->get_visitor_timezone() : false;

		$use_timezone_conversion = ( ! $this->get_event()->is_all_day() && $visitor_timezone );

		// Determine if event is multi-day (timezone-aware for timed events).
		$is_multi_day = ( $this->get_event()->is_all_day() || ! $visitor_timezone )
			? $this->get_event()->is_multi()
			: TimezoneConversionHelper::is_multi_day_in_timezone( $this->get_event(), $visitor_timezone );

		if ( ! $is_multi_day ) {

			// Single day event - get start date in appropriate timezone.
			$event_for_output = $use_timezone_conversion
				? TimezoneConversionHelper::create_timezone_converted_event_for_output( $this->get_event(), $visitor_timezone )
				: $this->get_event();

			return wp_json_encode(
				[
					'start_date' => Helpers::get_event_time_output(
						$event_for_output,
						$date_format,
						'start',
						true
					),
				]
			);
		}

		// Multi-day event - get both start and end dates in appropriate timezone.
		$event_for_output = $use_timezone_conversion
			? TimezoneConversionHelper::create_timezone_converted_event_for_output( $this->get_event(), $visitor_timezone )
			: $this->get_event();

		return wp_json_encode(
			[
				'start_date' => Helpers::get_event_time_output(
					$event_for_output,
					$date_format,
					'start',
					true
				),
				'end_date'   => Helpers::get_event_time_output(
					$event_for_output,
					$date_format,
					'end',
					true
				),
			]
		);
	}

	/**
	 * Get the event title.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_event_title() {

		return $this->get_event()->title;
	}
}
