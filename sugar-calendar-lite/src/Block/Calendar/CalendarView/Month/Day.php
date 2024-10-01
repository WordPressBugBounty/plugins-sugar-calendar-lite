<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Month;

use Sugar_Calendar\Block\Common\InterfaceView;
use Sugar_Calendar\Block\Common\Template;
use Sugar_Calendar\Helper;

/**
 * Class Day.
 *
 * Handles the Day inside the Month view.
 *
 * @since 3.0.0
 */
class Day implements InterfaceView {

	/**
	 * The day date.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	private $date;

	/**
	 * Events on the day.
	 *
	 * @since 3.0.0
	 *
	 * @var \Sugar_Calendar\Event[]
	 */
	private $events;

	/**
	 * Date Info.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $date_info;

	/**
	 * Calendar Info.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $calendar_info;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param string                $date          The day date.
	 * @param \Sugar_Calendar\Event $events        Events on the day.
	 * @param array                 $calendar_info Calendar Info.
	 */
	public function __construct( $date, $events, $calendar_info ) {

		$this->date          = $date;
		$this->events        = $events;
		$this->calendar_info = $calendar_info;
	}

	/**
	 * Display the day cell.
	 *
	 * @since 3.0.0
	 */
	public function render() {

		Template::load( 'month.day', $this );
	}

	/**
	 * Get the classes.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public function get_classes() {

		$classes = [
			'sugar-calendar-block__calendar-month__cell',
			'sugar-calendar-block__calendar-month__body__day',
		];

		if ( absint( $this->get_date_info()['month'] ) !== absint( $this->calendar_info['month'] ) ) {
			$classes[] = 'sugar-calendar-block__calendar-month__body__day-offset';
		}

		return $classes;
	}

	/**
	 * Get the container classes.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_container_classes() {

		return [
			'sugar-calendar-block__calendar-month__body__day__events-container',
		];
	}

	/**
	 * Get the date info.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_date_info() {

		if ( empty( $this->date_info ) ) {
			$this->date_info = Helper::get_info_from_yyyymmdd( $this->date );
		}

		return $this->date_info;
	}

	/**
	 * Get the calendar info.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_calendar_info() {

		return $this->calendar_info;
	}

	/**
	 * Return the events.
	 *
	 * @since 3.0.0
	 *
	 * @return \Sugar_Calendar\Event[]
	 */
	public function get_events() {

		return $this->events;
	}

	/**
	 * Get the date.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_date() {

		return $this->date;
	}
}
