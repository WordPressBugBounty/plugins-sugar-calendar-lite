<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Week;

use DateTimeImmutable;
use Sugar_Calendar\Block\Common\InterfaceView;
use Sugar_Calendar\Block\Common\Template;
use Sugar_Calendar\Block\Common\TimezoneConversionHelper;
use Sugar_Calendar\Event;
use Sugar_Calendar\Helper;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\OverlapClusters;
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
	 * Right-edge gap (px) for block intra-day cards. Differs from the admin
	 * Grid's INTRA_DAY_CARD_RIGHT_GAP_PX (10) by design — block columns are
	 * narrower.
	 *
	 * @since 3.12.0
	 */
	private const CLUSTER_GAP_PX = 6;

	/**
	 * Left indent (px) applied per nesting level, mirroring the admin layout.
	 *
	 * @since 3.12.0
	 */
	private const CLUSTER_NESTING_INDENT_PX = 10;

	/**
	 * Base z-index for clustered cards; later columns and nesting stack above it.
	 *
	 * @since 3.12.0
	 */
	private const CLUSTER_Z_INDEX_BASE = 10;

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
	public function get_style() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded

		$styles = $this->get_border_styles();

		if ( ! $this->is_all_day ) {
			$styles['height'] = $this->get_height() . 'px';

			if ( $this->has_cluster() ) {

				// SCB-style proportional layout (Week and Day block): divide the
				// (cell width − gap) into N columns; each card spans its column
				// to the right edge, and nesting indents within a shared column.
				$event   = $this->get_event();
				$columns = max( 1, (int) ( $event->overlap_columns ?? 1 ) );
				$column  = max( 1, (int) ( $event->overlap_column ?? 1 ) );
				$nesting = max( 0, (int) ( $event->overlap_nesting ?? 0 ) );

				$gap      = self::CLUSTER_GAP_PX;
				$indent   = $nesting * self::CLUSTER_NESTING_INDENT_PX;
				$fraction = ( $column - 1 ) / $columns;

				if ( $fraction <= 0 ) {
					$styles['left'] = ( $indent > 0 ) ? sprintf( '%dpx', $indent ) : '0';
				} else {
					$left_expr = sprintf( '%s * (100%% - %dpx)', OverlapClusters::format_number( $fraction ), $gap );

					if ( $indent > 0 ) {
						$left_expr .= sprintf( ' + %dpx', $indent );
					}

					$styles['left'] = sprintf( 'calc(%s)', $left_expr );
				}

				$width_expr = sprintf( '%s * (100%% - %dpx)', OverlapClusters::format_number( 1 - $fraction ), $gap );

				if ( $indent > 0 ) {
					$width_expr .= sprintf( ' - %dpx', $indent );
				}

				$styles['width'] = sprintf( 'calc(%s)', $width_expr );

				if ( ( $columns > 1 ) || ( $nesting > 0 ) ) {
					// Column-dominant so later columns and deeper nesting stack on
					// top, but kept in a low band (step of 10, nesting is single
					// digit) so cards stay below the event popover's z-index.
					$styles['z-index'] = self::CLUSTER_Z_INDEX_BASE + ( $column * 10 ) + $nesting;
				}
			}
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

		// All event cards — including all-day — intentionally share this border
		// treatment: the calendar color on the left edge, the secondary color on
		// the other three sides. (All-day cards previously kept the accent color
		// on all sides.)
		$default_border_styles = [
			'border-color'        => $this->get_color(),
			'border-top-color'    => $secondary_border_color,
			'border-right-color'  => $secondary_border_color,
			'border-bottom-color' => $secondary_border_color,
		];

		return $default_border_styles;
	}

	/**
	 * Whether the event has SCB-style cluster layout (column / N / nesting).
	 *
	 * True only when the event actually shares its time with others, so single
	 * events keep their default full width.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	private function has_cluster() {

		$event = $this->get_event();

		return property_exists( $event, 'overlap_columns' )
				&& ( ( (int) $event->overlap_columns > 1 ) || ( (int) $event->overlap_nesting > 0 ) );
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

				// An event that began before this week can also continue past
				// its end. get_event_offset_width()'s overflow flag misses the
				// exact-boundary case, so compare the event's true end against
				// the week's last visible day directly. week_day_ctr is 1-based
				// (1 for the first column), so ( 7 - week_day_ctr ) reaches the
				// last day regardless of which column this cell is.
				$week_last_day = $this->day->modify( '+' . ( 7 - (int) $this->args['week_day_ctr'] ) . ' days' );

				if (
					! empty( $this->get_event()->end_dto )
					&& $this->get_event()->end_dto->format( 'Y-m-d' ) > $week_last_day->format( 'Y-m-d' )
				) {
					$classes[] = 'sugar-calendar-block__calendar-week__event-cell--multi-day--overflow-week';
				}
			} else {
				$classes[] = 'sugar-calendar-block__calendar-week__event-cell--multi-day--offset';
			}
		} elseif ( $this->has_cluster() ) {
			$classes[] = 'sugar-calendar-block__calendar-week__event-cell--has-overlap';
		}

		// In the Day view, multi-day events render in the all-day row. Give them
		// directional arrow caps: a point on the left edge when the event began
		// before this day, and a point on the right edge when it continues past
		// it. A flat edge means the event starts/ends on this day.
		if ( $this->is_day_view() && $this->get_event_multiday() ) {
			foreach ( $this->get_multiday_continuation_classes() as $continuation_class ) {
				$classes[] = $continuation_class;
			}
		}

		if ( $this->get_height() <= 50 && ! $this->is_all_day ) {
			$classes[] = 'sugar-calendar-block__calendar-week__event-cell--single-hour';
		}

		// Short events (30 minutes or less) switch to a compact single-row layout
		// that places the time beside the title (see is_compact_duration()).
		if ( ! $this->is_all_day && $this->is_compact_duration() ) {
			$classes[] = 'sugar-calendar-block__calendar-week__event-cell--compact';
		}

		return $classes;
	}

	/**
	 * Whether the event is short enough (30 minutes or less) to use the compact
	 * single-row layout.
	 *
	 * Short events lack the vertical room to stack the title and time, so they
	 * place the time beside the title instead. start_dto / end_dto can be null
	 * for events with unparseable dates, so guard before diff() to avoid a fatal
	 * on a malformed event.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	private function is_compact_duration() {

		if ( empty( $this->get_event()->start_dto ) || empty( $this->get_event()->end_dto ) ) {
			return false;
		}

		$duration      = $this->get_event()->end_dto->diff( $this->get_event()->start_dto );
		$total_minutes = ( $duration->days * 24 * 60 ) + ( $duration->h * 60 ) + $duration->i;

		return ( $total_minutes > 0 ) && ( $total_minutes <= 30 );
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
	 * Get the multi-day continuation classes for the Day view all-day row.
	 *
	 * Returns a `--continues-before` class when the event began on an earlier
	 * day and a `--continues-after` class when it runs onto a later day,
	 * relative to the day being displayed. These drive the directional arrow
	 * caps on the event bar.
	 *
	 * @since 3.12.0
	 *
	 * @return string[]
	 */
	private function get_multiday_continuation_classes() {

		$visitor_timezone = $this->block ? $this->block->get_visitor_timezone() : false;

		if ( $visitor_timezone ) {
			$event_start = TimezoneConversionHelper::convert_event_start( $this->get_event(), $visitor_timezone );
			$event_end   = TimezoneConversionHelper::convert_event_end( $this->get_event(), $visitor_timezone );
		} else {
			$event_start = $this->get_event()->start_dto;
			$event_end   = $this->get_event()->end_dto;
		}

		// start_dto / end_dto can be null for events with unparseable dates.
		if ( empty( $event_start ) || empty( $event_end ) ) {
			return [];
		}

		$current_day = $this->day->format( 'Y-m-d' );
		$classes     = [];

		if ( $event_start->format( 'Y-m-d' ) < $current_day ) {
			$classes[] = 'sugar-calendar-block__calendar-day__event-cell--continues-before';
		}

		if ( $event_end->format( 'Y-m-d' ) > $current_day ) {
			$classes[] = 'sugar-calendar-block__calendar-day__event-cell--continues-after';
		}

		return $classes;
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

		// Height is proportional to duration with no minimum, so short events
		// render as thin cards. Guard against a negative value for very short
		// events where the pixel cutoff would exceed the computed height.
		return max( 0, $height );
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
