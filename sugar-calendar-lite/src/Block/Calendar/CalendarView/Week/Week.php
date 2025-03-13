<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Week;

use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use Sugar_Calendar\Block\Calendar\CalendarView\Block;
use Sugar_Calendar\Block\Common\InterfaceBaseView;
use Sugar_Calendar\Block\Common\Template;
use Sugar_Calendar\Helper;

/**
 * Class Week.
 *
 * Base class of the "Week" view.
 *
 * @since 3.0.0
 */
class Week implements InterfaceBaseView {

	/**
	 * Block object.
	 *
	 * @since 3.0.0
	 *
	 * @var Block
	 */
	private $block;

	/**
	 * Array containing the date as the key and
	 * the events as the value.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $events = null;

	/**
	 * The calendar period.
	 *
	 * @since 3.0.0
	 *
	 * @var DatePeriod
	 */
	private $calendar_period;

	/**
	 * Contains the all-day events for the week.
	 *
	 * @since 3.0.0
	 *
	 * @var \Sugar_Calendar\Event[]
	 */
	private $all_day_events = null;

	/**
	 * Contains the multi-events for the week.
	 *
	 * @since 3.0.0
	 *
	 * @var \Sugar_Calendar\Event[]
	 */
	private $multi_day_events = null;

	/**
	 * Contains the multi-day events with the ID as the key.
	 *
	 * @since 3.0.0
	 *
	 * @var \Sugar_Calendar\Event[]
	 */
	private $multi_day_events_by_id = null;

	/**
	 * Contains the formatted events.
	 *
	 * @since 3.0.0
	 *
	 * @var \Sugar_Calendar\Event[]
	 */
	private $formatted_events = null;

	/**
	 * Whether the current day is within the week.
	 *
	 * @since 3.0.0
	 *
	 * @var bool
	 */
	private $is_current_day_within_the_week = null;

	/**
	 * Array containing the events of the previous day.
	 *
	 * We used this as a holder when formatting the events.
	 *
	 * @since 3.0.0
	 *
	 * @var false|array
	 */
	private $previous_day_events = false;

	/**
	 * Multi-day events with spacers.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $formatted_multi_day_events = null;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param Block $block Block object.
	 */
	public function __construct( $block ) {

		$this->block = $block;

		$this->setup_formatted_events();
	}

	/**
	 * Get the data for the calendar.
	 *
	 * @since 3.0.0
	 *
	 * @return \Sugar_Calendar\Event[]
	 *
	 * @throws Exception When the date for the calendar was not created.
	 */
	private function get_events() {

		if ( ! is_null( $this->events ) ) {
			return $this->events;
		}

		$this->events = $this->block->get_week_events();

		return $this->events;
	}

	/**
	 * Get the events for the day by type.
	 *
	 * @since 3.0.0
	 *
	 * @param DateTimeImmutable $day  Day to get the events for.
	 * @param string            $type Type of events to get.
	 *
	 * @return \Sugar_Calendar\Event[]
	 */
	public function get_day_events_by_type( $day, $type ) {

		$day_string = $day->format( 'Y-m-d' );

		switch ( $type ) {
			case 'all_day':
				$events = $this->all_day_events;
				break;

			case 'multi_day':
				$events = $this->multi_day_events;
				break;

			default:
				$events = [];
				break;
		}

		return array_key_exists( $day_string, $events ) ?
			$events[ $day_string ] : [];
	}

	/**
	 * Setup the formatted events.
	 *
	 * @since 3.0.0
	 */
	private function setup_formatted_events() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		if ( $this->get_block()->should_not_load_events() ) {
			/*
			 * Do not load events.
			 */
			$this->events           = [];
			$this->formatted_events = [];
			$this->all_day_events   = [];
			$this->multi_day_events = [];

			return;
		}

		if ( ! is_null( $this->formatted_events ) ) {
			return;
		}

		$formatted_events = [];
		$all_day_events   = [];
		$multi_day_events = [];

		foreach ( $this->get_events() as $date => $events ) {

			$normal_events = [];

			// Separate the events by type.
			foreach ( $events as $event ) {

				if ( $event->is_multi() ) {
					$multi_day_events[ $date ][] = $event;

					continue;
				}

				if ( $event->is_all_day() ) {
					$all_day_events[ $date ][] = $event;

					continue;
				}

				$normal_events[] = $event;
			}

			$formatted_events[ $date ] = Helper::get_formatted_events_with_overlap( $normal_events );
		}

		$this->formatted_events = $formatted_events;
		$this->all_day_events   = $all_day_events;
		$this->multi_day_events = $multi_day_events;

		// Fix formatting for multi-day events.
		foreach ( $multi_day_events as $multi_day_date => $multi_day_evs ) {
			$pluck = [];

			foreach ( $multi_day_evs as $multi_day_ev ) {
				$this->multi_day_events_by_id[ $multi_day_ev->id ] = $multi_day_ev;
				$pluck[] = absint( $multi_day_ev->id );
			}

			// Loop through each of the $day_events.
			$format_events = $this->format_events( $pluck );

			if ( $format_events ) {
				$this->formatted_multi_day_events[ $multi_day_date ] = $format_events;
			}
		}

		$this->replace_data();
	}

	/**
	 * Format events.
	 *
	 * @since 3.0.0
	 *
	 * @param array $events Events to be formatted.
	 *
	 * @return false|array Returns `false` if events doesn't need formatting.
	 *                     Otherwise, returns the formatted events.
	 */
	private function format_events( $events ) {

		// Skip if there's no event in the day.
		if ( empty( $events ) ) {
			$this->previous_day_events = false;

			return false;
		}

		if ( $this->previous_day_events ) {
			$diff = array_diff( $this->previous_day_events, $events );

			// If there's no difference, then try to swap the order.
			if ( empty( $diff ) ) {
				$diff = array_diff( $events, $this->previous_day_events );
			}

			$inter = array_intersect( $this->previous_day_events, $events );

			if ( ! empty( $diff ) && ! empty( $inter ) ) {
				$formatted_events          = $this->fill_with_spacers( $events, $inter );
				$this->previous_day_events = $formatted_events;

				return $formatted_events;
			}
		}

		$this->previous_day_events = $events;

		return false;
	}

	/**
	 * Fill the events with spacers.
	 *
	 * This method accepts `$original_events` which contains event IDs.
	 * We assume that the events are sorted by the following logic:
	 * 1. Multi-day events.
	 * 2. All-day events.
	 * 3. Regular/Same-day events.
	 *
	 * The `$intersect` contains the multi-event IDs that was displayed from the
	 * previous cell. Its key is the position of the event in the previous cell.
	 *
	 * $original_events = [
	 *     0 => 32,
	 *     1 => 35,
	 *     2 => 40,
	 * ];
	 *
	 * $intersect = [
	 *     2 => 32,
	 * ];
	 *
	 * Then the return value will be:
	 *
	 * $return_value = [
	 *     0 => `$spacer_arr`,
	 *     1 => `$spacer_arr`,
	 *     2 => 32,
	 *     3 => 35,
	 *     4 => 40,
	 * ];
	 *
	 * @since 3.0.0
	 *
	 * @param array $original_events Current form of the events.
	 * @param array $intersect       Contains the events that needs repositioning
	 *                               and it's new position.
	 *
	 * @return array
	 */
	private function fill_with_spacers( $original_events, $intersect ) {

		$output = [];

		foreach ( $original_events as $k => $v ) {
			// Get the new position of the event.
			$new_position = array_search( $v, $intersect, true );

			if ( ! $new_position ) {
				$output[] = $v;

				continue;
			}

			// Let's fill the old positions with false.
			foreach ( range( $k, $new_position ) as $j ) {
				if ( $j === $new_position ) {
					$output[ $j ] = $v;
				} elseif ( ! array_key_exists( $j, $output ) ) {
					$output[ $j ] = $this->get_spacer_based_on_previous_day_event( $j );
				}
			}
		}

		return $output;
	}

	/**
	 * Replace the data with the formatted events.
	 *
	 * @since 3.0.0
	 */
	private function replace_data() {

		if ( empty( $this->formatted_multi_day_events ) ) {
			return;
		}

		foreach ( $this->formatted_multi_day_events as $day => $events ) {

			if ( ! array_key_exists( $day, $this->multi_day_events ) ) {
				continue;
			}

			$new_ev = [];

			foreach ( $events as $event_id ) {

				if ( is_numeric( $event_id ) && array_key_exists( $event_id, $this->multi_day_events_by_id ) ) {
					$new_ev[] = $this->multi_day_events_by_id[ $event_id ];
				} else {
					$new_ev[] = $event_id;
				}
			}

			$this->multi_day_events[ $day ] = $new_ev;
		}
	}

	/**
	 * Returns the spacer.
	 *
	 * @since 3.0.0
	 *
	 * @param int $position The position of the event in the previous day.
	 *
	 * @return string
	 */
	private function get_spacer_based_on_previous_day_event( $position ) {

		$previous_day_event = $this->previous_day_events[ $position ];

		if ( ! is_int( $previous_day_event ) ) {
			return $previous_day_event;
		}

		$spacer = 'full';

		if (
			isset( $this->multi_day_events_by_id[ $previous_day_event ] ) &&
			$this->multi_day_events_by_id[ $previous_day_event ]->is_multi()
		) {
			return 'small-' . $this->previous_day_events[ $position ];
		}

		return $spacer . '-' . $this->previous_day_events[ $position ];
	}

	/**
	 * Get the events of a day.
	 *
	 * @since 3.0.0
	 *
	 * @param DateTimeInterface $day Day of the events we want to get.
	 *
	 * @return array|\Sugar_Calendar\Event
	 */
	public function get_events_by_day( $day ) {

		$this->setup_formatted_events();

		$day_string = $day->format( 'Y-m-d' );

		return ! ( empty( $this->formatted_events[ $day_string ] ) ) ?
			$this->formatted_events[ $day_string ] : [];
	}

	/**
	 * Render the base template of the Weekly view.
	 *
	 * @since 3.0.0
	 */
	public function render_base() {

		Template::load( 'week.base', $this );
	}

	/**
	 * Render the Weekly view.
	 *
	 * @since 3.0.0
	 */
	public function render() {

		$this->render_base();
	}

	/**
	 * Get the heading of the Weekly view.
	 *
	 * @since 3.0.0
	 * @since 3.4.0
	 *
	 * @param bool $use_abbreviated_month Whether to use abbreviated month or not.
	 *
	 * @return string
	 */
	public function get_heading( $use_abbreviated_month = false ) {

		global $wp_locale;

		$start_date = $wp_locale->get_month( $this->get_block()->get_week_period()->start->format( 'm' ) );
		$end_date   = $wp_locale->get_month( $this->get_block()->get_week_period()->end->format( 'm' ) );

		if ( $use_abbreviated_month ) {
			$start_date = $wp_locale->get_month_abbrev( $start_date );
			$end_date   = $wp_locale->get_month_abbrev( $end_date );
		}

		return sprintf(
			'%1$s %2$d - %3$s %4$d',
			$start_date,
			$this->get_block()->get_week_period()->getStartDate()->format( 'd' ),
			$end_date,
			$this->get_block()->get_week_period()->getEndDate()->format( 'd' )
		);
	}

	/**
	 * Get the Block object.
	 *
	 * @since 3.0.0
	 *
	 * @return Block
	 */
	public function get_block() {

		return $this->block;
	}

	/**
	 * Whether the current day is within the week.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 *
	 * @throws \Exception
	 */
	public function is_current_day_within_the_week() {

		if ( ! is_null( $this->is_current_day_within_the_week ) ) {
			return $this->is_current_day_within_the_week;
		}

		$this->is_current_day_within_the_week = false;

		$today = new DateTimeImmutable( gmdate( 'Y-m-d' ) );

		if (
			$today >= $this->get_block()->get_week_period()->getStartDate() &&
			$today <= $this->get_block()->get_week_period()->getEndDate()
		) {
			$this->is_current_day_within_the_week = true;
		}

		return $this->is_current_day_within_the_week;
	}
}
