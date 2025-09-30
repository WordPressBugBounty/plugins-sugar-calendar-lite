<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Week;

use DateTimeImmutable;
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
 * Handles the Event Cell inside the Week view.
 *
 * @since 3.0.0
 */
class EventCell implements InterfaceView {

	/**
	 * Event.
	 *
	 * @since 3.0.0
	 *
	 * @var Event
	 */
	private $event;

	/**
	 * Day of the event cell.
	 *
	 * @since 3.0.0
	 *
	 * @var DateTimeImmutable
	 */
	private $day;

	/**
	 * Event cell args.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Block instance.
	 *
	 * @since 3.9.0
	 *
	 * @var AbstractBlock|null
	 */
	private $block;

	/**
	 * Whether the event is multiday.
	 *
	 * @since 3.9.0
	 *
	 * @var bool
	 */
	private $is_multi_day = false;

	/**
	 * Whether the event is an all-day or multi-day event.
	 *
	 * @since 3.0.0
	 *
	 * @var bool
	 */
	private $is_all_day = false;

	/**
	 * Cell height.
	 *
	 * @since 3.0.0
	 *
	 * @var float
	 */
	private $height = null;

	/**
	 * Calendars info.
	 *
	 * @since 3.0.0
	 *
	 * @var null
	 */
	private $calendars_info = null;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 * @since 3.9.0 Make block instance available.
	 *
	 * @param Event              $event Event.
	 * @param DateTimeImmutable  $day   Day of the event cell.
	 * @param array              $args  Event cell args.
	 * @param AbstractBlock|null $block Block instance.
	 */
	public function __construct( $event, $day, $args = [], $block = null ) {

		$this->event = $event;
		$this->day   = $day;
		$this->args  = $args;
		$this->block = $block;

		$this->is_multi_day = $this->get_event_multiday();

		if ( isset( $this->args['is_all_day'] ) && $this->args['is_all_day'] ) {
			$this->is_all_day = (bool) $this->args['is_all_day'];
		}
	}

	/**
	 * Render the event cell.
	 *
	 * @since 3.0.0
	 */
	public function render() {

		Template::load( 'week.event-cell', $this );
	}

	/**
	 * Get the event month view styles.
	 *
	 * @since 3.0.0
	 * @since 3.9.0 Separate style handler for day view.
	 *
	 * @return string
	 */
	public function get_style() {

		$styles = $this->get_border_styles();

		if ( ! $this->is_all_day ) {
			$styles['height'] = $this->get_height() . 'px';

			if ( $this->has_overlap() ) {

				$padding_modifier = $this->is_day_view() ? 36 : 12;

				$dynamic_padding = $this->get_event()->overlap_count * $padding_modifier;

				if ( $this->is_day_view() ) {
					$left  = $dynamic_padding;
					$width = 14 + $dynamic_padding;
				} else {
					$left  = 6 + $dynamic_padding;
					$width = 12 + $dynamic_padding;
				}

				$styles['left'] = sprintf(
					'%dpx',
					$left
				);

				$styles['width'] = sprintf(
					'calc(100%% - %dpx)',
					$width
				);

				$styles['z-index'] = 10 + $this->get_event()->overlap_count;
			}
		} else {
			$styles['background-color'] = $this->get_color();
		}

		$style_string = '';

		foreach ( $styles as $key => $value ) {
			$style_string .= "{$key}: {$value};";
		}

		return $style_string;
	}

	/**
	 * Get the border styles.
	 *
	 * @since 3.9.0
	 *
	 * @return array
	 */
	private function get_border_styles() {

		$dark    = '#7F7F7F';
		$light   = '#FFFFFF';
		$is_dark = $this->block->get_appearance_mode() === 'dark';

		$secondary_border_color = $is_dark ? $dark : $light;

		$default_border_styles = [
			'border-color'        => $this->get_color(),
		];

		if ( ! $this->is_all_day ) {
			$default_border_styles['border-top-color']    = $secondary_border_color;
			$default_border_styles['border-right-color']  = $secondary_border_color;
			$default_border_styles['border-bottom-color'] = $secondary_border_color;
		}

		return $default_border_styles;
	}

	/**
	 * Whether the event has overlap.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	private function has_overlap() {

		return property_exists( $this->get_event(), 'overlap_count' )
				&& $this->get_event()->overlap_count > 0;
	}

	/**
	 * Get the event classes.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public function get_classes() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$classes   = [];
		$classes[] = 'sugar-calendar-block__event-cell';
		$classes[] = sprintf(
			'sugar-calendar-block__calendar-week__event-cell--id-%d',
			$this->get_event()->id
		);

		if ( ! empty( $this->args['is_ajax'] ) && $this->args['is_ajax'] ) {
			$classes[] = 'sugar-calendar-block__calendar-month__cell-hide';
		}

		if ( $this->is_all_day ) {
			$classes[] = 'sugar-calendar-block__calendar-week__event-cell--all-day';
		} else {
			$classes[] = 'sugar-calendar-block__calendar-week__event-cell';
		}

		if ( ! $this->is_day_view() && $this->get_event_multiday() ) {

			if ( $this->day->format( 'Y-m-d' ) === $this->get_event()->start_dto->format( 'Y-m-d' ) ) {
				$get_event_offset_width = Helper::get_event_offset_width(
					$this->get_event()->start_dto,
					$this->get_event()->end_dto,
					$this->args['week_day_ctr']
				);

				$classes[] = 'sugar-calendar-block__calendar-week__event-cell--multi-day--start';
				$classes[] = sprintf(
					'sugar-calendar-block__calendar-week__event-cell--multi-day--%d',
					$get_event_offset_width['width']
				);

				if ( $get_event_offset_width['is_week_overflow'] ) {
					$classes[] = 'sugar-calendar-block__calendar-week__event-cell--multi-day--overflow-week';
				}
			} elseif ( ! isset( $this->get_displayed_events()[ $this->get_event()->id ] ) ) {
				$get_event_offset_width = Helper::get_event_offset_width(
					$this->day,
					$this->get_event()->end_dto,
					$this->args['week_day_ctr']
				);

				$classes[] = 'sugar-calendar-block__calendar-week__event-cell--multi-day--start-overflow';
				$classes[] = sprintf(
					'sugar-calendar-block__calendar-week__event-cell--multi-day--%d',
					$get_event_offset_width['width']
				);
			} else {
				$classes[] = 'sugar-calendar-block__calendar-week__event-cell--multi-day--offset';
			}
		} elseif ( $this->has_overlap() ) {
			$classes[] = 'sugar-calendar-block__calendar-week__event-cell--has-overlap';
		}

		if ( $this->get_height() <= 50 && ! $this->is_all_day ) {
			$classes[] = 'sugar-calendar-block__calendar-week__event-cell--single-hour';
		}

		return $classes;
	}

	/**
	 * Whether the view is a day view.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	private function is_day_view() {

		return ! empty( $this->args['block_attributes']['display'] )
			&& $this->args['block_attributes']['display'] === 'day';
	}

	/**
	 * Returns the height of the event block in px.
	 *
	 * @since 3.0.0
	 * @since 3.9.0 Updated Day view multi-day height calculation to use timezone-aware event times.
	 *
	 * @return float
	 */
	private function get_height() {

		if ( ! is_null( $this->height ) ) {
			return $this->height;
		}

		if ( $this->is_all_day ) {
			$this->height = 20;

			return $this->height;
		}

		$this->height = $this->is_day_view() && $this->get_event_multiday()
			? $this->get_render_heights_day_view_multiday_events()
			: $this->get_render_heights();

		return $this->height;
	}

	/**
	 * Get the height of normal event block.
	 *
	 * @since 3.9.0
	 *
	 * @return int
	 */
	public function get_render_heights() {

		// Original logic for single-day events and Week view.
		$duration = $this->calculate_durations( $this->get_event()->start_dto, $this->get_event()->end_dto );

		return $this->calculate_height( $duration['hours'], $duration['minutes'] );
	}

	/**
	 * Get the height of the event block for multiday events in the day view.
	 *
	 * @since 3.9.0
	 *
	 * @return int
	 */
	public function get_render_heights_day_view_multiday_events() {

		// Renderers.
		$render_hours   = 0;
		$render_minutes = 0;

		$current_day_date = $this->day->format( 'Y-m-d' );

		// Use timezone-aware event dates and times when visitor timezone is available.
		$visitor_timezone = $this->block ? $this->block->get_visitor_timezone() : false;

		if ( $visitor_timezone ) {
			$event_start_dto = TimezoneConversionHelper::convert_event_start( $this->get_event(), $visitor_timezone );
			$event_end_dto   = TimezoneConversionHelper::convert_event_end( $this->get_event(), $visitor_timezone );
		} else {
			$event_start_dto = $this->get_event()->start_dto;
			$event_end_dto   = $this->get_event()->end_dto;
		}

		$event_start_date = $event_start_dto->format( 'Y-m-d' );
		$event_end_date   = $event_end_dto->format( 'Y-m-d' );

		// Handling for multiday events.
		if ( $current_day_date === $event_start_date ) { // Start of multiday event.

			// Start day: use original logic (same as single-day events).
			$duration = $this->calculate_durations( $event_start_dto, $event_end_dto );

			// Multiday event does not need minutes on start day.
			$render_hours = $duration['hours'];

		} elseif ( $current_day_date === $event_end_date ) { // End of multiday event.

			// Setup a midnight marker.
			$event_end_midnight = clone $event_end_dto;
			$event_end_midnight = $event_end_midnight->setTime( 0, 0, 0 );

			// Compare the midnight marker to the event end time.
			$diff = $event_end_midnight->diff( $event_end_dto );

			$render_hours   = $diff->h;
			$render_minutes = $diff->i;

		} else { // Middle of multiday event.

			// Render full day (24 hours).
			$render_hours = 24;
		}

		return $this->calculate_height( $render_hours, $render_minutes );
	}

	/**
	 * Calculate duration hours and minutes from start/end DTOs.
	 *
	 * @since 3.9.0
	 *
	 * @param DateTimeImmutable $start_dto Event start datetime.
	 * @param DateTimeImmutable $end_dto   Event end datetime.
	 *
	 * @return array Array with 'hours' and 'minutes' keys.
	 */
	private function calculate_durations( $start_dto, $end_dto ) {

		$diff = $end_dto->diff( $start_dto );

		// Get start hour in 24-hour format.
		$start_hour_pointer = intval( $start_dto->format( 'H' ) );
		$event_duration     = $diff->h + $diff->d * 24;
		$total_height       = $start_hour_pointer + $event_duration;

		$is_over_bound = $total_height > 24;

		$render_hours = $is_over_bound ? 24 - $start_hour_pointer : $event_duration;

		return [
			'hours'   => $render_hours,
			'minutes' => $diff->i,
		];
	}

	/**
	 * Calculate the height of the event block.
	 *
	 * @since 3.9.0
	 *
	 * @param int $render_hours   The number of hours to render.
	 * @param int $render_minutes The number of minutes to render.
	 *
	 * @return int
	 */
	public function calculate_height( $render_hours, $render_minutes ) {

		$pixel_cutoff = 3;

		/*
		 * Calculate the height of the event block.
		 * The time slot is 51px per hour.
		 * We substract 1 to avoid the event block to hit the bottom border
		 * for events that ends in the top of the hour.
		 */
		$height = ( ( $render_hours * 51 ) + ( $render_minutes * 0.9 ) ) - $pixel_cutoff;

		return $height;
	}

	/**
	 * Get the event.
	 *
	 * @since 3.0.0
	 *
	 * @return Event
	 */
	public function get_event() {

		return $this->event;
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

	/**
	 * Check if the event is multiday.
	 *
	 * @since 3.9.0
	 * @since 3.9.0 Updated to use timezone-aware multi-day detection for proper timezone conversion support.
	 *
	 * @return bool
	 */
	public function get_event_multiday() {

		// Use timezone-aware multi-day detection when visitor timezone is available.
		$visitor_timezone = $this->block ? $this->block->get_visitor_timezone() : false;

		return $visitor_timezone
			? TimezoneConversionHelper::is_multi_day_in_timezone( $this->get_event(), $visitor_timezone )
			: $this->get_event()->is_multi();
	}

	/**
	 * Get the accent color.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_accent_color() {

		if ( ! empty( $this->args['block_attributes']['accentColor'] ) ) {
			return $this->args['block_attributes']['accentColor'];
		}

		return '';
	}

	/**
	 * Get the calendars info of an event.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public function get_calendars_info() {

		if ( ! is_null( $this->calendars_info ) ) {
			return $this->calendars_info;
		}

		$this->calendars_info = Helper::get_calendars_info_of_event( $this->get_event() );

		if ( empty( $this->calendars_info ) ) {
			return [
				'primary_event_color' => $this->get_accent_color(),
			];
		}

		$this->calendars_info['primary_event_color'] = ! empty( $this->calendars_info['calendars'][0]['color'] ) ? $this->calendars_info['calendars'][0]['color'] : $this->get_accent_color();

		return $this->calendars_info;
	}

	/**
	 * Get the color of the event.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_color() {

		if ( empty( $this->get_calendars_info() ) ) {
			return $this->get_accent_color();
		}

		return empty( $this->get_calendars_info()['calendars'][0]['color'] ) ?
			$this->get_accent_color()
			:
			$this->get_calendars_info()['calendars'][0]['color'];
	}

	/**
	 * Whether the event is an all-day or multi-day event.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function is_all_day() {

		return $this->is_all_day;
	}

	/**
	 * Get the displayed events.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_displayed_events() {

		return ! empty( $this->args['events_displayed_in_the_week'] ) ?
			$this->args['events_displayed_in_the_week']
			:
			[];
	}

	/**
	 * Get the block instance.
	 *
	 * @since 3.9.0
	 *
	 * @return AbstractBlock|null
	 */
	public function get_block() {

		return $this->block;
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

		if ( ! $this->get_event_multiday() ) {
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
}
