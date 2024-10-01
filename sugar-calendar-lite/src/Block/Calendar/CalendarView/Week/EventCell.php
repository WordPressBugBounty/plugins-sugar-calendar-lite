<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Week;

use DateTimeImmutable;
use Sugar_Calendar\Block\Common\InterfaceView;
use Sugar_Calendar\Block\Common\Template;
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
	 *
	 * @param Event             $event Event.
	 * @param DateTimeImmutable $day   Day of the event cell.
	 * @param array             $args  Event cell args.
	 */
	public function __construct( $event, $day, $args = [] ) {

		$this->event = $event;
		$this->day   = $day;
		$this->args  = $args;

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
	 * Get the event styles.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_style() {

		$styles = [
			'border-color' => $this->get_color(),
		];

		if ( ! $this->is_all_day ) {
			$styles['height'] = $this->get_height() . 'px';

			if ( $this->has_overlap() ) {
				$dynamic_padding = $this->get_event()->overlap_count * 12;

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

		if ( ! $this->is_day_view() && $this->get_event()->is_multi() ) {

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

		$diff = $this->get_event()->end_dto->diff( $this->get_event()->start_dto );

		/*
		 * Calculate the height of the event block.
		 * The time slot is 51px per hour.
		 * We substract 1 to avoid the event block to hit the bottom border
		 * for events that ends in the top of the hour.
		 */
		$height = ( ( $diff->h * 51 ) + ( $diff->i * 0.9 ) ) - 1;

		$this->height = $height;

		return $this->height;
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
}
