<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Month;

use DateTime;
use Sugar_Calendar\Block\Common\InterfaceView;
use Sugar_Calendar\Block\Common\Template;
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
	 * Constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param Event  $event         The event.
	 * @param string $cell_date     The date of the cell.
	 * @param array  $calendar_info The calendar info.
	 */
	public function __construct( $event, $cell_date, $calendar_info = [] ) {

		$this->event         = $event;
		$this->cell_date     = $cell_date;
		$this->calendar_info = $calendar_info;
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

		if ( ! $this->get_event()->is_multi() ) {
			return $classes;
		}

		if ( $this->get_event()->start_dto->format( 'Y-m-d' ) === $this->cell_date ) {
			$classes[] = 'sugar-calendar-block__calendar-month__body__day__events-container__event-multi-day-start';
			$classes   = array_merge( $classes, $this->get_multi_day_duration_classes( $this->get_event()->start_dto ) );
		} elseif ( ! isset( $this->calendar_info['events_displayed_in_the_week'][ $this->get_event()->id ] ) ) {
			$classes[] = 'sugar-calendar-block__calendar-month__body__day__events-container__event-multi-day-start-overflow';
			$classes   = array_merge( $classes, $this->get_multi_day_duration_classes( DateTime::createFromFormat( 'Y-m-d|', $this->cell_date ) ) );
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
	 *
	 * @param DateTime $start_date The start date we will display the multi-day event.
	 *
	 * @return string[]
	 */
	private function get_multi_day_duration_classes( $start_date ) {

		$classes = [];

		// Clone to avoid modifying the original object.
		$event_end  = clone $this->get_event()->end_dto;
		$start_date = clone $start_date;

		// Remove the time part.
		$start_date->setTime( 0, 0, 0 );
		$event_end->setTime( 0, 0, 0 );

		// Calculate the full-day difference.
		$date_diff = $this->get_event()->end_dto->diff( $start_date );

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

		if ( $this->get_event()->is_multi() ) {
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
	 *
	 * @return string
	 */
	public function get_event_day_duration() {

		$date_format = Options::get( 'date_format' );

		if ( ! $this->get_event()->is_multi() ) {
			return wp_json_encode(
				[
					'start_date' => Helpers::get_event_time_output(
						$this->get_event(),
						$date_format,
						'start',
						true
					),
				]
			);
		}

		// For multi-day event, we display the short day name.
		return wp_json_encode(
			[
				'start_date' => Helpers::get_event_time_output(
					$this->get_event(),
					$date_format,
					'start',
					true
				),
				'end_date'   => Helpers::get_event_time_output(
					$this->get_event(),
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
