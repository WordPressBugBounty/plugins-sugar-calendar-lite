<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Month;

use Sugar_Calendar\Block\Common\InterfaceView;
use Sugar_Calendar\Block\Common\Template;

/**
 * Class Month.
 *
 * Handles the Week inside the Month view.
 *
 * @since 3.0.0
 */
class Week implements InterfaceView {

	/**
	 * Week data.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Array containing the events for the week with `id`
	 * as the key.
	 *
	 * @since 3.0.0
	 *
	 * @var Event[]
	 */
	private $events;

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
	 * Contains the formatted events for the week.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $formatted_events = [];

	/**
	 * Flag to check if the events are already formatted.
	 *
	 * @since 3.0.0
	 *
	 * @var bool
	 */
	private $is_formatted = false;

	/**
	 * Calendar Info.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $calendar_info = [];

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param array $data          Array containing the events for a week.
	 * @param array $calendar_info Calendar Info.
	 */
	public function __construct( $data, $calendar_info ) {

		$this->data          = $data;
		$this->calendar_info = $calendar_info;
	}

	/**
	 * Display the week.
	 *
	 * @since 3.0.0
	 */
	public function render() {

		Template::load( 'month.week', $this );
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
	 * Setup the events for the week.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_formatted_events() {

		if ( $this->is_formatted ) {
			return $this->data;
		}

		// Loop through each of the days in the week.
		foreach ( $this->data as $cal_date => $day_events ) {

			$pluck = [];

			foreach ( $day_events as $day_event ) {
				$this->events[ $day_event->id ] = $day_event;
				$pluck[]                        = absint( $day_event->id );
			}

			// Loop through each of the $day_events.
			$format_events = $this->format_events( $pluck );

			if ( $format_events ) {
				$this->formatted_events[ $cal_date ] = $format_events;
			}
		}

		$this->replace_data();

		$this->is_formatted = true;

		return $this->data;
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
			$diff  = array_diff( $this->previous_day_events, $events );
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
	 * @since 3.2.0 Fix the issue with positioning and recurring events.
	 *
	 * @param array $original_events Current form of the events.
	 * @param array $intersect       Contains the events that needs repositioning
	 *                               and it's new position.
	 *
	 * @return array
	 */
	private function fill_with_spacers( $original_events, $intersect ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$output = [];

		foreach ( $original_events as $position => $event_id ) {
			// Get the new position of the event.
			$new_position = array_search( $event_id, $intersect, true );

			if ( ! $new_position ) {
				$output[] = $event_id;

				continue;
			}

			// If the event is not multi-day, then we don't need to reposition it.
			if (
				isset( $this->previous_day_events[ $new_position ] ) &&
				! $this->events[ $event_id ]->is_multi()
			) {
				$output[] = $event_id;

				continue;
			}

			foreach ( range( $position, $new_position ) as $j ) {
				if ( $j === $new_position ) {
					$output[ $j ] = $event_id;
				} elseif ( ! array_key_exists( $j, $output ) ) {
					$output[ $j ] = $this->get_spacer_based_on_previous_day_event( $j );
				}
			}
		}

		return $output;
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
			isset( $this->events[ $previous_day_event ] ) &&
			$this->events[ $previous_day_event ]->is_multi()
		) {
			return 'small-' . $this->previous_day_events[ $position ];
		}

		return $spacer . '-' . $this->previous_day_events[ $position ];
	}

	/**
	 * Replace the data with the formatted events.
	 *
	 * @since 3.0.0
	 */
	private function replace_data() {

		if ( empty( $this->formatted_events ) ) {
			return;
		}

		foreach ( $this->formatted_events as $day => $events ) {

			if ( ! array_key_exists( $day, $this->data ) ) {
				continue;
			}

			$new_ev = [];

			foreach ( $events as $event_id ) {

				if ( is_numeric( $event_id ) && array_key_exists( $event_id, $this->events ) ) {
					$new_ev[] = $this->events[ $event_id ];
				} else {
					$new_ev[] = $event_id;
				}
			}

			$this->data[ $day ] = $new_ev;
		}
	}
}
