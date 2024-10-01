<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Week;

use DateTimeInterface;
use Sugar_Calendar\Block\Common\InterfaceView;
use Sugar_Calendar\Block\Common\Template;
use Sugar_Calendar\Event;

/**
 * Class Day.
 *
 * Handles the Day inside the Week view.
 *
 * @since 3.0.0
 */
class Day implements InterfaceView {

	/**
	 * Events for the day.
	 *
	 * @since 3.0.0
	 *
	 * @var Event[]
	 */
	private $events;

	/**
	 * Date in context.
	 *
	 * @since 3.0.0
	 *
	 * @var DateTimeInterface
	 */
	private $date;

	/**
	 * Args.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param Event[]           $events Events of the day.
	 * @param DateTimeInterface $date   Date in context.
	 * @param array             $args   More data.
	 */
	public function __construct( $events, $date, $args = [] ) {

		$this->events = $events;
		$this->date   = $date;
		$this->args   = $args;
	}

	/**
	 * Render the day.
	 *
	 * @since 3.0.0
	 */
	public function render() {

		Template::load( 'week.day', $this );
	}

	/**
	 * Get the events of the day.
	 *
	 * @since 3.0.0
	 *
	 * @return Event[]
	 */
	public function get_events() {

		return $this->events;
	}

	/**
	 * Get the date in context.
	 *
	 * @since 3.0.0
	 *
	 * @return DateTimeInterface
	 */
	public function get_date() {

		return $this->date;
	}

	/**
	 * Get the args.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_args() {

		return $this->args;
	}
}
