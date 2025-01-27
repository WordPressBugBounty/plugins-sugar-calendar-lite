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
	 *
	 * @var DatePeriod
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
	 *
	 * @return Event[]
	 */
	public function get_upcoming_events() { // phpcs:ignore Generic.Metrics.NestingLevel.MaxExceeded, Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.CyclomaticComplexity.TooHigh

		$events = [];

		$upcoming_events_transient = [
			'events'              => [],
			'has_previous_events' => false,
			'has_upcoming_events' => false,
		];

		// Block attributes.
		$attributes = $this->get_settings_attributes();

		// Limits.
		$events_per_page  = $attributes['eventsPerPage'];
		$max_events_count = $attributes['maximumEventsToShow'];
		$page             = $this->get_paged();

		// Current time.
		$now      = sugar_calendar_get_request_time( 'mysql' );
		$date_now = new DateTime( $now );

		/**
		 * End time modify filter.
		 *
		 * @since 3.4.0
		 *
		 * @param string $upcoming_events_limit The upcoming events limit time modification.
		 * @param array  $attributes            The block attributes.
		 */
		$upcoming_events_limit = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_list_block_upcoming_events_limit',
			'+3 month',
			$attributes
		);

		// End time.
		$end = $date_now->modify( $upcoming_events_limit )->format( 'Y-m-d H:i:s' );

		// Set period.
		$this->upcoming_start_period = $now;
		$this->upcoming_end_period   = $end;

		// Set the period.
		$calendar_period = $this->get_upcoming_period();

		$start_period_range = $calendar_period->getStartDate();
		$end_period_range   = $calendar_period->getEndDate();

		if ( $this->get_visitor_timezone() ) {
			$start_period_range = $start_period_range->modify( '-1 day' );
			$end_period_range   = $end_period_range->modify( '+1 day' );
		}

		// List of calendar slugs.
		$calendar_slugs = '';

		if ( ! empty( $this->get_calendars() ) ) {

			// Get the calendar ids.
			$calendars = $this->get_calendars();

			// Get the calendar slugs.
			$calendar_slugs_array = array_map(
				function ( $calendar_id ) {
					// Get the calendar.
					$calendar = get_term_by(
						'id',
						$calendar_id,
						sugar_calendar_get_calendar_taxonomy_id()
					);

					return $calendar->slug;
				},
				$calendars
			);

			// Implode the calendar slugs.
			$calendar_slugs = implode( ',', $calendar_slugs_array );
		}

		// List of venues.
		$venues    = $this->get_venues();
		$venue_ids = ! empty( $venues ) ? implode( ',', $venues ) : '';

		// Search term if any.
		$search_term = $this->get_search_term();

		/**
		 * Event variety filter.
		 *
		 * @since 3.4.0
		 *
		 * @param int   $quantity   The quantity of different events to fetch.
		 * @param array $attributes The block attributes.
		 */
		$max_number_of_events = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_list_block_upcoming_events_max_number_of_events',
			max( 30, $events_per_page ),
			$attributes
		);

		/**
		 * Event sequences limit to fetch.
		 *
		 * @since 3.4.0
		 *
		 * @param int $quantity The quantity of event sequences to fetch.
		 */
		$events_sequences_limit = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_list_block_upcoming_events_sequences_limit',
			max( 60, $max_events_count )
		);

		/**
		 * Whether to use transient for upcoming events.
		 *
		 * @since 3.4.0
		 * @since 3.5.0 Add multiday grouping.
		 *
		 * @param bool  $use_transient Whether to use transient for upcoming events.
		 * @param array $attributes    The block attributes.
		 */
		$use_transient = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_list_block_upcoming_events_use_transient',
			false,
			$attributes
		);

		$transient_key = wp_sprintf(
			'sugar_calendar_upcoming_events_%1$s%2$s',
			$attributes['blockId'],
			md5(
				':'
				. $page
				. ':'
				. $events_per_page
				. ':'
				. $max_events_count
				. ':'
				. $max_number_of_events
				. ':'
				. $events_sequences_limit
				. ':'
				. $calendar_slugs
				. ':'
				. $search_term
			)
		);

		if ( $use_transient ) {

			$upcoming_events_transient = get_transient( $transient_key );

			if ( $upcoming_events_transient ) {

				$events = ! empty( $upcoming_events_transient['events'] ) ? $upcoming_events_transient['events'] : [];

				// Set the pagination flags.
				$this->has_previous_events = $upcoming_events_transient['has_previous_events'];
				$this->has_upcoming_events = $upcoming_events_transient['has_upcoming_events'];

				return $events;
			}
		}

		// Fetch events as an "infinite" list.
		$events_list = Helpers::get_upcoming_events_list_with_recurring(
			$max_number_of_events,
			$calendar_slugs,
			$venue_ids,
			$search_term
		);

		// If we found no events by this point, return an empty array.
		if ( empty( $events_list ) ) {
			return $events;
		}

		// Build event sequences within the given timeframe.
		$events_sequences = sugar_calendar_get_event_sequences(
			$events_list,
			$start_period_range,
			$end_period_range,
			'',
			'',
			$events_sequences_limit
		);

		// Update max events count based on the fetched events if search term is set.
		if ( ! empty( $search_term ) ) {
			$max_events_count = count( $events_sequences );
		}

		// Event counters.
		$queried_events        = [];
		$event_count           = 0;
		$displayed_event_count = 0;
		$last_event            = null;
		$is_last_page          = false;
		$multi_day_event_ids   = [];

		$offset = ( $page - 1 ) * $events_per_page;

		// Loop through each day in the calendar period.
		foreach ( $calendar_period as $d ) {

			// Filter events for the current day.
			$filtered_events = Helper::filter_events_by_day(
				$events_sequences,
				$d->format( 'd' ),
				$d->format( 'm' ),
				$d->format( 'Y' ),
				$this->get_visitor_timezone()
			);

			// Group the filtered events by day.
			$queried_events[ $d->format( 'Y-m-d' ) ] = $filtered_events;

			if ( ! empty( $filtered_events ) ) {
				$last_event = end( $filtered_events );
			}
		}

		// Loop through each day in the calendar period.
		foreach ( $queried_events as $date => $event_singles ) {

			if ( empty( $event_singles ) ) {
				continue;
			}

			// Loop event singles.
			foreach ( $event_singles as $event_single ) {

				// Check if it's a multi-day event and has already been added.
				if (
					$event_single->is_multi()
					&&
					in_array(
						$event_single->id,
						$multi_day_event_ids,
						true
					)
				) {
					continue;
				}

				// Mark multi-day event as added.
				if ( $event_single->is_multi() ) {
					$multi_day_event_ids[] = $event_single->id;
				}

				if ( $event_count < $offset ) {

					++$event_count;

					continue;
				}

				// Add event if still below the events per page and total events count below max events count.
				if (
					$displayed_event_count < $events_per_page
					&&
					$event_count < $max_events_count
				) {

					$events[ $date ][] = $event_single;

					if (
						$event_single->id === $last_event->id
						&&
						$event_single->start === $last_event->start
					) {
						$is_last_page = true;
					}

					++$event_count;
					++$displayed_event_count;
				}

				// If we reached the events per page, break the loop.
				if ( $displayed_event_count === $events_per_page ) {
					break;
				}
			}

			// If we reached the events per page, break the loop.
			if ( $displayed_event_count === $events_per_page ) {
				break;
			}
		}

		// Set pagination flags based on displayed events count.
		$this->has_previous_events = ( $page > 1 );
		$this->has_upcoming_events = $event_count < $max_events_count && ! $is_last_page;

		// If events to display is less than the events per page, there are no more events.
		if ( count( $events_sequences ) <= $events_per_page ) {
			$this->has_upcoming_events = false;
		}

		if (
			$use_transient
			&&
			! empty( $events )
			&&
			empty( $upcoming_events_transient )
		) {

			/**
			 * Transient expiration filter.
			 *
			 * @since 3.4.0
			 *
			 * @param int   $transient_expiration The transient expiration time.
			 * @param array $attributes           The block attributes.
			 */
			$transient_expiration = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				'sugar_calendar_event_list_block_upcoming_events_transient_expiration',
				24 * HOUR_IN_SECONDS,
				$attributes
			);

			// Set the transient value.
			$upcoming_events_transient                        = [];
			$upcoming_events_transient['events']              = $events;
			$upcoming_events_transient['has_previous_events'] = $this->has_previous_events;
			$upcoming_events_transient['has_upcoming_events'] = $this->has_upcoming_events;

			set_transient( $transient_key, $upcoming_events_transient, $transient_expiration );
		}

		return $events;
	}

	/**
	 * The upcoming period based on the start and end events.
	 *
	 * @since 3.4.0
	 *
	 * @return DatePeriod
	 */
	public function get_upcoming_period() {

		if ( ! is_null( $this->upcoming_period ) ) {
			return $this->upcoming_period;
		}

		// Build the period-based events array.
		$this->upcoming_period = new DatePeriod(
			new DateTimeImmutable( $this->upcoming_start_period ),
			new DateInterval( 'P1D' ),
			( new DateTimeImmutable( $this->upcoming_end_period ) )->setTime( 23, 59, 59 )
		);

		return $this->upcoming_period;
	}
}
