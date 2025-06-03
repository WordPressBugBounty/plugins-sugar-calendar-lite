<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView\Day;

use Sugar_Calendar\Block\Calendar\CalendarView\Block;
use Sugar_Calendar\Block\Common\InterfaceBaseView;
use Sugar_Calendar\Block\Common\Template;
use Sugar_Calendar\Helper;

/**
 * Class Day.
 *
 * Base class of the "Day" view.
 *
 * @since 3.0.0
 */
class Day implements InterfaceBaseView {

	/**
	 * The block object.
	 *
	 * @since 3.0.0
	 *
	 * @var Block
	 */
	private $block;

	/**
	 * Contains the formatted events.
	 *
	 * @since 3.0.0
	 *
	 * @var \Sugar_Calendar\Event[]
	 */
	private $formatted_events = null;

	/**
	 * Contains all-day events.
	 *
	 * @since 3.0.0
	 *
	 * @var \Sugar_Calendar\Event[]
	 */
	private $all_day_events = null;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param Block $block The block object.
	 */
	public function __construct( $block ) {

		$this->block = $block;
	}

	/**
	 * Get the events for the day.
	 *
	 * @since 3.0.0
	 * @since 3.1.2 Added support for visitor timezone conversion.
	 * @since 3.5.0 Added support for filtering by venues.
	 * @since 3.6.0 Added filter hook `sugar_calendar_block_calendar_view_day_events`.
	 * @since 3.7.0 Added support for filtering by tags.
	 * @since 3.7.0 Filter the number of events to load.
	 *
	 * @return \Sugar_Calendar\Event[]
	 */
	public function get_events() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		if ( $this->get_block()->should_not_load_events() ) {
			/*
			 * Do not load events.
			 */
			$this->formatted_events = [];
			$this->all_day_events   = [];

			return [];
		}

		if ( ! is_null( $this->formatted_events ) ) {
			return $this->formatted_events;
		}

		$this->all_day_events = [];
		$normal_events        = [];

		$start_range = $this->get_block()->get_datetime();
		$end_range   = $this->get_block()->get_datetime();

		/*
		 * If Visitor Timezone Conversion is enabled, we get the events the day before and after
		 * today's date. This is to consider timezone differences.
		 */
		if ( $this->get_block()->get_visitor_timezone() ) {
			$start_range = $this->get_block()->get_datetime()->modify( '-1 day' );
			$end_range   = $this->get_block()->get_datetime()->modify( '+1 day' );
		}

		$block_categories = ! empty( $this->block->get_calendars() ) ? array_map( 'absint', $this->block->get_calendars() ) : [];
		$search_term      = $this->block->get_search_term();
		$block_venues   = $this->block->get_venues();
		$block_tags     = $this->block->get_tags();
		$block_speakers = $this->block->get_speakers();

		$events = sugar_calendar_get_events_within_range(
			[
				'start_range' => $start_range,
				'end_range'   => $end_range,
				'category'    => $block_categories,
				'search'      => $search_term,
				'number'      => $this->block->get_max_events_count(),
				'venues'      => $block_venues,
				'tags'        => $block_tags,
				'speakers'    => $block_speakers,
			]
		);

		/**
		 * Filter the events for the calendar block - day view.
		 *
		 * @since 3.6.0
		 * @since 3.7.0 Added support for filtering by tags.
		 *
		 * @param \Sugar_Calendar\Event[] $events           The calendar events.
		 * @param \DateTimeImmutable      $start_range      The start period range.
		 * @param \DateTimeImmutable      $end_range        The end period range.
		 * @param int[]                   $block_categories The calendars to filter the occurrences.
		 * @param string                  $search_term      The search term.
		 * @param int[]                   $venues           The venues to filter the occurrences.
		 * @param int[]                   $tags             The tags to filter the occurrences.
		 * @param int[]                   $speakers         The speakers to filter the occurrences.
		 */
		$events = apply_filters(
			'sugar_calendar_block_calendar_view_day_events',
			$events,
			$start_range,
			$end_range,
			$block_categories,
			$search_term,
			$block_venues,
			$block_tags,
			$block_speakers
		);

		if ( ! empty( $events ) && $this->get_block()->get_visitor_timezone() ) {
			/*
			 * For the case where visitor timezone conversion is enabled, we get the
			 * events for yesterday and tomorrow to account for timezone difference.
			 * But ultimately, we only want to show the current day's events.
			 */
			$events = Helper::filter_events_by_day(
				$events,
				$this->get_block()->get_datetime()->format( 'd' ),
				$this->get_block()->get_datetime()->format( 'm' ),
				$this->get_block()->get_datetime()->format( 'Y' ),
				$this->get_block()->get_visitor_timezone()
			);
		}

		foreach ( $events as $event ) {

			if ( $event->is_all_day() ) {
				$this->all_day_events[] = $event;

				continue;
			}

			$normal_events[] = $event;
		}

		$this->formatted_events = Helper::get_formatted_events_with_overlap( $normal_events );

		return $this->formatted_events;
	}

	/**
	 * Get all-day events.
	 *
	 * @since 3.0.0
	 *
	 * @return \Sugar_Calendar\Event[]
	 */
	public function get_all_day_events() {

		if ( ! is_null( $this->all_day_events ) ) {
			return $this->all_day_events;
		}

		$this->get_events();

		return $this->all_day_events;
	}

	/**
	 * Render the initial/base view.
	 *
	 * @since 3.0.0
	 */
	public function render_base() {

		Template::load( 'day.base', $this );
	}

	/**
	 * Render the Day view with events.
	 *
	 * This is also used on rendering updates via AJAX.
	 *
	 * @since 3.0.0
	 */
	public function render() {

		$this->render_base();
	}

	/**
	 * Get the heading of the Day view.
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

		$month = $wp_locale->get_month( $this->get_block()->get_month_num_without_zero() );

		if ( $use_abbreviated_month ) {
			$month = $wp_locale->get_month_abbrev( $month );
		}

		return sprintf(
			'%1$s %2$s, %3$d',
			$month,
			$this->get_block()->get_day_num_without_zero(),
			$this->get_block()->get_year()
		);
	}

	/**
	 * Returns an array with keys as hours and values as the 12-hour format with meridian.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public static function get_division_by_hour() {

		return [
			0  => '12:01 AM',
			1  => '1:00 AM',
			2  => '2:00 AM',
			3  => '3:00 AM',
			4  => '4:00 AM',
			5  => '5:00 AM',
			6  => '6:00 AM',
			7  => '7:00 AM',
			8  => '8:00 AM',
			9  => '9:00 AM',
			10 => '10:00 AM',
			11 => '11:00 AM',
			12 => '12:00 PM',
			13 => '1:00 PM',
			14 => '2:00 PM',
			15 => '3:00 PM',
			16 => '4:00 PM',
			17 => '5:00 PM',
			18 => '6:00 PM',
			19 => '7:00 PM',
			20 => '8:00 PM',
			21 => '9:00 PM',
			22 => '10:00 PM',
			23 => '11:00 PM',
			24 => '12:00 AM',
		];
	}

	/**
	 * Get the block object.
	 *
	 * @since 3.0.0
	 *
	 * @return Block
	 */
	public function get_block() {

		return $this->block;
	}
}
