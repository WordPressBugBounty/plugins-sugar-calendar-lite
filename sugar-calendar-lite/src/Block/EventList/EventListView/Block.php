<?php

namespace Sugar_Calendar\Block\EventList\EventListView;

use DateTimeImmutable;
use DateInterval;
use DatePeriod;
use DateTime;
use Sugar_Calendar\Block\Common\AbstractBlock;
use Sugar_Calendar\Block\Common\Template;
use Sugar_Calendar\Helper;
use Sugar_Calendar\Helpers;

class Block extends AbstractBlock {

	/**
	 * The Block key.
	 *
	 * @since 3.1.0
	 *
	 * @var string
	 */
	const KEY = 'event_list';

	/**
	 * Array containing the events.
	 *
	 * @since 3.1.0
	 *
	 * @var \Sugar_Calendar\Event[]
	 */
	private $events = null;

	/**
	 * Contains the event IDs that were already displayed.
	 *
	 * @since 3.1.0
	 *
	 * @var string[]
	 */
	private $displayed_events = [];

	/**
	 * The upcoming period.
	 *
	 * @since 3.4.0
	 * @since 3.6.0 Convert to array containing only the DateTime objects with events.
	 *
	 * @var DateTime[]
	 */
	private $upcoming_period;

	/**
	 * Whether the block has upcoming events.
	 *
	 * @since 3.4.0
	 *
	 * @var bool
	 */
	private $has_upcoming_events;

	/**
	 * Whether the block has previous events.
	 *
	 * @since 3.4.0
	 *
	 * @var bool
	 */
	private $has_previous_events;

	/**
	 * The start date of the upcoming period.
	 *
	 * @since 3.4.0
	 *
	 * @var string
	 */
	public $upcoming_start_period;

	/**
	 * The end date of the upcoming period.
	 *
	 * @since 3.4.0
	 *
	 * @var string
	 */
	public $upcoming_end_period;

	/**
	 * Returns whether the block has upcoming events or not.
	 *
	 * @since 3.4.0
	 *
	 * @return bool Whether the block has upcoming events or not.
	 */
	public function has_upcoming_events() {

		return $this->has_upcoming_events;
	}

	/**
	 * Returns whether the block has previous events or not.
	 *
	 * @since 3.4.0
	 *
	 * @return bool Whether the block has previous events or not.
	 */
	public function has_previous_events() {

		return $this->has_previous_events;
	}

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 *
	 * @param array $attributes Block attributes.
	 */
	public function __construct( $attributes ) {

		parent::__construct( $attributes );
	}

	/**
	 * Return the block HTML.
	 *
	 * @since 3.0.0
	 *
	 * @return false|string
	 */
	public function get_html() {

		ob_start();

		Template::load( 'base', $this, self::KEY );

		return ob_get_clean();
	}

	/**
	 * Get the display options.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	public function get_display_options() {

		return [
			'list'  => esc_html__( 'List', 'sugar-calendar-lite' ),
			'grid'  => esc_html__( 'Grid', 'sugar-calendar-lite' ),
			'plain' => esc_html__( 'Plain', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Get the classes for the block.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	public function get_classes() {

		return [
			'sugar-calendar-event-list-block',
			sprintf(
				'sugar-calendar-event-list-block__%s-view',
				$this->get_display_mode()
			),
		];
	}

	/**
	 * Get the data for the list view.
	 *
	 * @since 3.1.0
	 *
	 * @return \Sugar_Calendar\Event[]
	 *
	 * @throws Exception When the date for the calendar was not created.
	 */
	public function get_events() {

		if ( $this->should_not_load_events() ) {
			$this->events = [];

			return [];
		}

		if ( ! is_null( $this->events ) ) {
			return $this->events;
		}

		if ( $this->should_group_events_by_week() ) {

			$this->events = $this->get_week_events();

		} else {

			$this->events = $this->get_upcoming_events();
		}

		return $this->events;
	}

	/**
	 * Get the displayed events.
	 *
	 * @since 3.1.0
	 *
	 * @return string[]
	 */
	public function get_displayed_events() {

		return $this->displayed_events;
	}

	/**
	 * Add a displayed event.
	 *
	 * @since 3.1.0
	 *
	 * @param string $event_id Event ID.
	 *
	 * @return void
	 */
	public function add_displayed_event( $event_id ) {

		$this->displayed_events[] = $event_id;
	}

	/**
	 * Get the current pagination text.
	 *
	 * @since 3.1.0
	 *
	 * @return string
	 */
	public function get_current_pagination_display() {

		return __( 'This Week', 'sugar-calendar-lite' );
	}

	/**
	 * Get the next pagination text.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_next_pagination_display() {

		return __( 'Next Week', 'sugar-calendar-lite' );
	}

	/**
	 * Get the previous pagination text.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_previous_pagination_display() {

		return __( 'Previous Week', 'sugar-calendar-lite' );
	}

	/**
	 * Get the block styles.
	 *
	 * @since 3.1.0
	 *
	 * @return string
	 */
	public function get_styles() {

		$styles = [
			'--accent-color' => $this->get_default_accent_color(),
			'--links-color'  => $this->attributes['linksColor'],
		];

		$output = '';

		foreach ( $styles as $key => $val ) {
			$output .= sprintf( '%1$s: %2$s;', $key, $val );
		}

		return $output;
	}

	/**
	 * Get the settings/attributes for the block.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	public function get_settings_attributes() {

		return empty( $this->get_attributes()['attributes'] ) ? $this->get_attributes() : $this->get_attributes()['attributes'];
	}

	/**
	 * Get paged attribute if defined.
	 *
	 * @since 3.4.0
	 *
	 * @return int
	 */
	public function get_paged() {

		return empty( $this->get_attributes()['paged'] ) ? 1 : $this->get_attributes()['paged'];
	}

	/**
	 * Check if set to group events by week.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public function should_group_events_by_week() {

		return $this->get_settings_attributes()['groupEventsByWeek'];
	}

	/**
	 * Get events per page attribute setting if defined.
	 *
	 * @since 3.4.0
	 *
	 * @return int
	 */
	public function get_settings_attribute_events_per_page() {

		$attributes = $this->get_settings_attributes();

		// If isset eventsPerPage attribute, return it.
		if ( isset( $attributes['eventsPerPage'] ) ) {
			return $attributes['eventsPerPage'];
		}

		return sc_get_number_of_events();
	}

	/**
	 * Whether or not we should display the block footer.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public function should_render_block_footer() {

		if ( $this->should_group_events_by_week() ) {
			return true;
		}

		if ( ! $this->has_upcoming_events() ) {
			return false;
		}

		$events_per_page        = $this->get_settings_attribute_events_per_page();
		$maximum_events_to_show = $this->get_settings_attributes()['maximumEventsToShow'];

		return ! ( $events_per_page === $maximum_events_to_show );
	}

	/**
	 * Get the no events message.
	 *
	 * @since 3.1.0
	 * @since 3.4.0 Added the no events message for the upcoming event mode.
	 *
	 * @return string
	 */
	public function get_no_events_msg() {

		if (
			! empty( $this->get_search_term() )
			||
			! empty( $this->get_calendars() )
			||
			! $this->should_group_events_by_week()
		) {
			return __( 'There are no events scheduled that match your criteria.', 'sugar-calendar-lite' );
		}

		return __( 'There are no events scheduled this week.', 'sugar-calendar-lite' );
	}

	/**
	 * Get appearance mode.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public function get_appearance_mode() {

		return $this->get_settings_attributes()['appearance'];
	}

	/**
	 * Get upcoming events with pagination inside the loop.
	 *
	 * @since 3.4.0
	 * @since 3.6.0 Optimize the method to get the upcoming events.
	 *
	 * @return Event[]
	 */
	public function get_upcoming_events() { // phpcs:ignore Generic.Metrics.NestingLevel.MaxExceeded, Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.CyclomaticComplexity.TooHigh

		$settings_attrs = $this->get_settings_attributes();

		// Limits.
		$events_per_page = $settings_attrs['eventsPerPage'];
		$page            = $this->get_paged();

		// Search term if any.
		$search_term = $this->get_search_term();

		/**
		 * Filter the maximum number of events to show in the upcoming events block.
		 *
		 * @since 3.4.0
		 * @since 3.6.0 Changed the value to use `$settings_attrs['maximumEventsToShow']`.
		 *
		 * @param int   $quantity       The quantity of different events to fetch.
		 * @param array $settings_attrs The block attributes.
		 */
		$max_number_of_events = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_list_block_upcoming_events_max_number_of_events',
			max( 30, $settings_attrs['maximumEventsToShow'] ),
			$settings_attrs
		);

		$args = [
			'number'       => $events_per_page,
			'calendar_ids' => $this->get_calendars(),
			'search'       => $search_term,
			'offset'       => ( $page - 1 ) * $events_per_page,
		];

		$upcoming_events = Helpers::get_upcoming_events_list_with_recurring(
			$args,
			$this->get_attributes()
		);

		// If we found no events by this point, return an empty array.
		if ( empty( $upcoming_events ) ) {
			return [];
		}

		/*
		 * We set `$upcoming_events` to get an additional 1 entry to check if there are more events
		 */
		if ( count( $upcoming_events ) > $events_per_page ) {
			$upcoming_events           = array_slice( $upcoming_events, 0, $events_per_page );
			$this->has_upcoming_events = true;
		}

		$upcoming_dates       = [];
		$upcoming_events_list = [];

		foreach ( $upcoming_events as $upcoming_event ) {
			$event_date = $upcoming_event->start_date( 'Y-m-d' );

			if ( ! isset( $upcoming_events_list[ $event_date ] ) ) {
				$upcoming_events_list[ $event_date ] = [];
				$upcoming_dates[]                    = $upcoming_event->start_dto;
			}

			$upcoming_events_list[ $event_date ][] = $upcoming_event;
		}

		$this->upcoming_period = $upcoming_dates;

		// Set pagination flags based on displayed events count.
		$this->has_previous_events = ( $page > 1 );

		if ( ( $page * $events_per_page ) >= $max_number_of_events ) {
			$this->has_upcoming_events = false;
		}

		return $upcoming_events_list;
	}

	/**
	 * The upcoming period based on the start and end events.
	 *
	 * @since 3.4.0
	 * @since 3.6.0 Convert to array containing only the DateTime objects with events.
	 *
	 * @return DateTime[]
	 */
	public function get_upcoming_period() {

		if ( ! is_null( $this->upcoming_period ) ) {
			return $this->upcoming_period;
		}

		if ( is_null( $this->events ) ) {
			$this->events = $this->get_upcoming_events();
		}

		return $this->upcoming_period;
	}

	/**
	 * Get the display mode string.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public function get_display_mode_string() {

		$string          = '';
		$display_options = $this->get_display_options();
		$display_mode    = $this->get_display_mode();

		if ( array_key_exists( $display_mode, $display_options ) ) {
			$string = ucwords( $display_options[ $display_mode ] );
		}

		/**
		 * Filters display mode string.
		 *
		 * @since 3.7.0
		 *
		 * @param string $string       The display mode string.
		 * @param string $display_mode The display mode.
		 */
		return apply_filters(
			'sugar_calendar_block_event_list_event_list_view_block_display_mode_string',
			$string,
			$display_mode
		);
	}
}
