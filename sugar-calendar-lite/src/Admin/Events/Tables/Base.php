<?php

namespace Sugar_Calendar\Admin\Events\Tables;

use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Event;
use Sugar_Calendar\Event_Query;
use Sugar_Calendar\Helper;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelper;
use Sugar_Calendar\Pro\Features\AdvancedRecurring\Occurrence;
use WP_List_Table;
use function Sugar_Calendar\Admin\Screen\Options\get_defaults;

// Include the main list table class if it's not included.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Event table.
 *
 * This list table is responsible for showing events in a traditional table,
 * even though it extends the `WP_List_Table` class. Tables & lists & tables.
 *
 * @since 3.0.0
 */
class Base extends WP_List_Table {

	/**
	 * The mode of the current view.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $mode = 'list';

	/**
	 * What day does a calendar week start on.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $start_of_week = '1';

	/**
	 * The days of the week.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $week_days = [];

	/**
	 * Number of days per week.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $day_count = 7;

	/**
	 * How should dates be formatted.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $date_format = 'F j, Y';

	/**
	 * How should times be formatted.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $time_format = 'g:i a';

	/**
	 * The beginning boundary for the current view.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $view_start = '';

	/**
	 * The end boundary for the current view.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $view_end = '';

	/**
	 * Duration of view, from start to end.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	public $view_duration = 0;

	/**
	 * The current time zone object, derived from $view_timezone.
	 *
	 * @since 2.1.0
	 *
	 * @var object
	 */
	public $view_timezone = false;

	/**
	 * The items with pointers.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $pointers = [];

	/**
	 * The start year being viewed (for list-mode).
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	protected $start_year = 2020;

	/**
	 * The year being viewed.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	protected $year = 2020;

	/**
	 * The month being viewed.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	protected $month = 1;

	/**
	 * The day being viewed.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	protected $day = 1;

	/**
	 * The exact day being viewed based on year/month/day.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	protected $today = 0;

	/**
	 * The time zone for the current view.
	 *
	 * @since 2.1.0
	 *
	 * @var object
	 */
	protected $timezone = 'UTC';

	/**
	 * The timestamp for this exact microsecond.
	 *
	 * We cache this as a reference to avoid repeated calls to time(),
	 * particularly when the accuracy of time comparisons is critical.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	protected $now = 0;

	/**
	 * Whether the week column is shown.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	protected $show_week_column = false;

	/**
	 * Current item details.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	protected $item = null;

	/**
	 * The events query for the current view items.
	 *
	 * @since 2.0.0
	 *
	 * @var object
	 */
	protected $query = null;

	/**
	 * The events query for recurring items.
	 *
	 * @since 2.0.0
	 *
	 * @var object
	 */
	protected $recurring_query = null;

	/**
	 * Unix time month start.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	public $grid_start = 0;

	/**
	 * Unix time month end.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	protected $grid_end = 0;

	/**
	 * The properties for the current cell.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	protected $current_cell = [
		'day'   => null,
		'month' => null,
		'year'  => null,
		'start' => null,
		'end'   => null,
		'index' => null,
	];

	/**
	 * The properties for all of the cells.
	 *
	 * @since 2.1.3
	 *
	 * @var array
	 */
	protected $cells = [];

	/**
	 * Array of all items to loop through.
	 *
	 * May include clones, for items that recur.
	 *
	 * @since 2.2.0
	 *
	 * @var array
	 */
	protected $all_items = [];

	/**
	 * Array of queried items, filtered, usually by status.
	 *
	 * @since 2.1.2
	 *
	 * @var array
	 */
	protected $filtered_items = [];

	/**
	 * Array of queried item IDs, filtered, usually by status.
	 *
	 * @since 2.2.0
	 *
	 * @var array
	 */
	protected $filtered_ids = [];

	/**
	 * Array of item counts, from queried items that fit into this view.
	 *
	 * @since 2.1.6
	 *
	 * @var array
	 */
	protected $item_counts = [
		'total' => 0,
	];

	/**
	 * Maximum number of events per iteration.
	 *
	 * @since 3.0.0
	 *
	 * @var int
	 */
	protected $max = 0;

	/**
	 * The main constructor method.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Constructor arguments.
	 */
	public function __construct( $args = [] ) {

		// Initialize this class.
		$r = $this->init( $args );

		// Pass arguments into parent.
		parent::__construct( $r );
	}

	/** Init ******************************************************************/

	/**
	 * Initialize this class.
	 *
	 * @since 2.1.6
	 *
	 * @param array $args Initialization arguments.
	 *
	 * @return array
	 */
	protected function init( $args = [] ) {

		// Override the list table if one was passed in.
		if ( ! empty( $args['list_table'] ) ) {
			$this->set_list_table( $args['list_table'] );
		}

		// Set class properties.
		$this->init_globals();
		$this->init_timezone();
		$this->init_boundaries();
		$this->init_week_days();
		$this->init_max();
		$this->init_modes();

		// Get post type.
		$pt = sugar_calendar_get_admin_post_type();

		// Setup arguments.
		$r = wp_parse_args(
			$args,
			[
				'plural'   => sugar_calendar_get_post_type_label( $pt, 'name', esc_html__( 'Events', 'sugar-calendar-lite' ) ),
				'singular' => sugar_calendar_get_post_type_label( $pt, 'singular_name', esc_html__( 'Event', 'sugar-calendar-lite' ) ),
			]
		);

		// Return arguments.
		return $r;
	}

	/**
	 * Force the order and orderby, so default view is correct.
	 *
	 * @since 2.0.0
	 */
	protected function init_globals() {

		$_GET['order']   = $this->get_order();
		$_GET['orderby'] = $this->get_orderby();
	}

	/**
	 * Set the time zone.
	 *
	 * @since 2.1.2
	 */
	protected function init_timezone() {

		$this->timezone = sugar_calendar_get_timezone();
	}

	/**
	 * Set the boundaries.
	 *
	 * @since 2.0.0
	 */
	protected function init_boundaries() {

		// Set now once, so everything uses the same timestamp.
		$this->now = $this->get_current_time();

		// Set formatting options.
		$this->start_of_week = $this->get_start_of_week();
		$this->date_format   = $this->get_date_format();
		$this->time_format   = $this->get_time_format();

		// Set year, month, & day.
		$this->year  = $this->get_year();
		$this->month = $this->get_month();
		$this->day   = $this->get_day();

		// Set list-mode specific year.
		$this->start_year = $this->get_start_year();

		// Set "today" based on current request.
		$this->today = strtotime( "{$this->year}/{$this->month}/{$this->day}" );
	}

	/**
	 * Set the modes.
	 *
	 * @since 2.0.0
	 */
	protected function init_modes() {

		$this->modes = [
			'month' => esc_html__( 'Month', 'sugar-calendar-lite' ),
			'week'  => esc_html__( 'Week', 'sugar-calendar-lite' ),
			'day'   => esc_html__( 'Day', 'sugar-calendar-lite' ),
			'list'  => esc_html__( 'List', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Set the maximum number of items per iteration.
	 *
	 * @since 2.0.0
	 */
	protected function init_max() {

		$this->max = sugar_calendar_get_user_preference( 'events_max_num', 100 );
	}

	/**
	 * Set the days of the week.
	 *
	 * @since 2.0.0
	 */
	protected function init_week_days() {

		// Day values.
		$days = [
			'0' => 'sunday',
			'1' => 'monday',
			'2' => 'tuesday',
			'3' => 'wednesday',
			'4' => 'thursday',
			'5' => 'friday',
			'6' => 'saturday',
		];

		// Get the day index.
		$index  = array_search( $this->start_of_week, array_keys( $days ) );
		$start  = array_slice( $days, $index, count( $days ), true );
		$finish = array_slice( $days, 0, $index, true );

		// Set days for week.
		$this->week_days = $start + $finish;
	}

	/** Setters ***************************************************************/

	/**
	 * Set the start, end, and duration of the current view.
	 *
	 * @since 2.0.0
	 *
	 * @param string $start Start of the view.
	 * @param string $end   End of the view.
	 */
	protected function set_view( $start = '', $end = '' ) {

		// Convert to timestamps.
		$start_time = strtotime( $start );
		$end_time   = strtotime( $end );

		// Set view boundaries.
		$this->view_start    = ( $end_time > $start_time ) ? $start : $end;
		$this->view_end      = ( $end_time < $start_time ) ? $start : $end;
		$this->view_duration = ( $end_time - $start_time );

		// Set view time zone.
		$this->view_timezone = sugar_calendar_get_timezone_object( $this->timezone );
	}

	/**
	 * Set the filtered items.
	 *
	 * @since 2.1.2
	 */
	protected function set_filtered_items() {

		// Get the filter.
		$filter = $this->get_items_filter();

		// No queried items.
		if ( empty( $this->query->items ) ) {
			$this->filtered_items = [];

			// No filter ("All" for view).
		} elseif ( empty( $filter ) ) {
			// Exclude trashed events.
			$this->filtered_items = array_filter(
				$this->query->items,
				fn( $event ) => in_array( $event->status, array_keys( get_post_statuses() ) )
			);

			// Filter queried items.
		} else {
			$this->filtered_items = wp_list_filter( $this->query->items, $filter );
		}

		// Set the filtered IDs.
		$this->filtered_ids = wp_list_pluck( $this->filtered_items, 'id' );

		/**
		 * Filter the filtered ids to be displayed in the admin events tables.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $filtered_ids The filtered ids to be displayed in the admin events tables.
		 * @param string $view_start   The beginning boundary for the current view.
		 * @param string $view_end     The end boundary for the current view.
		 * @param string $status       The status of the events to filter.
		 */
		$this->filtered_ids = apply_filters(
			'sugar_calendar_admin_events_tables_base_filtered_ids',
			$this->filtered_ids,
			$this->view_start,
			$this->view_end,
			$this->get_status()
		);
	}

	/**
	 * Set all of the items to loop through.
	 *
	 * @since 2.2.0
	 */
	protected function set_all_items() {

		// No items, so skip some processing.
		if ( empty( $this->query->items ) ) {
			$this->all_items = [];

			// Get sequences in this range from queried items.
		} else {

			// Environment.
			$sow = sugar_calendar_daynum_to_ical( $this->start_of_week );

			// Range.
			$after  = sugar_calendar_get_datetime_object( $this->view_start, $this->view_timezone );
			$before = sugar_calendar_get_datetime_object( $this->view_end, $this->view_timezone );

			// Get all of the items.
			$this->all_items = sugar_calendar_get_event_sequences(
				$this->query->items,
				$after,
				$before,
				$this->view_timezone, // Already an object.
				$sow
			);
		}

		/**
		 * Filter the all items to be displayed in the admin events tables.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $all_items  The items to be displayed in the admin events tables.
		 * @param string $view_start The beginning boundary for the current view.
		 * @param string $view_end   The end boundary for the current view.
		 * @param string $status     The status of the events to filter.
		 */
		$this->all_items = apply_filters(
			'sugar_calendar_admin_events_tables_base_all_items',
			$this->all_items,
			$this->view_start,
			$this->view_end,
			$this->get_status()
		);
	}

	/**
	 * Set the item counts for the current view.
	 *
	 * @since 2.1.6
	 * @since 3.6.0 Added the filter `sugar_calendar_admin_events_tables_base_item_counts`.
	 */
	protected function set_item_counts() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded

		// Reset.
		$this->item_counts = [
			'total' => 0,
		];

		// Bail if no queried items or no cells.
		if ( empty( $this->cells ) ) {
			return;
		}

		// Default counts.
		$counts = [];

		// Get all items from all cells.
		$countable_items = wp_list_pluck( $this->cells, 'countable' );

		// Bail if no cell items.
		if ( empty( $countable_items ) ) {
			return;
		}

		// Loop through cell items and flatten.
		foreach ( $countable_items as $cell_items ) {

			// Skip if no items in cell.
			if ( empty( $cell_items ) ) {
				continue;
			}

			// Remove empty.
			$counts = array_merge( $counts, $cell_items );
		}

		// Default all items.
		$countables = [];

		// Reduce counts down to countables by ID.
		foreach ( $counts as $countable ) {
			if ( ! isset( $countables[ $countable->id ] ) ) {
				$countables[ $countable->id ] = $countable;
			}
		}

		// Unique items.
		$all_items = array_unique( $countables, SORT_REGULAR );

		// Pluck all queried statuses.
		$statuses = wp_list_pluck( $all_items, 'status' );

		// Get unique statuses only.
		$statuses = array_unique( $statuses );

		$unique_events = [];

		// Loop through statuses.
		if ( ! empty( $statuses ) ) {
			foreach ( $statuses as $status ) {

				// Get items of this status.
				$status_items = wp_filter_object_list(
					$all_items,
					[
						'status' => $status,
					]
				);

				$counter = 0;

				// Loop through each of the status items.
				foreach ( $status_items as $item ) {
					if ( ! in_array( $item->object_id, $unique_events, true ) ) {
						$unique_events[] = $item->object_id;

						++$counter;
					}
				}

				// Add count to return value.
				$this->item_counts[ $status ] = $counter;
			}
		}

		// Set total to count of all items.
		$this->item_counts['total'] = count( $this->all_items );

		/**
		 * Filter the item counts for the current view.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $item_counts The item counts for the current view.
		 * @param string $view_start  The beginning boundary for the current view.
		 * @param string $view_end    The end boundary for the current view.
		 * @param string $status      The status of the events to filter.
		 */
		$this->item_counts = apply_filters(
			'sugar_calendar_admin_events_tables_base_item_counts',
			$this->item_counts,
			$this->view_start,
			$this->view_end,
			$this->get_status()
		);
	}

	/**
	 * Import object variables from another object.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item List table to import variables from.
	 */
	protected function set_list_table( $item = false ) {

		global $wp_list_table;

		// Bail if no object passed.
		if ( empty( $item ) ) {
			return;
		}

		// Set the old list table.
		$this->old_list_table = $item;

		// Loop through object vars and set the key/value.
		foreach ( get_object_vars( $item ) as $key => $value ) {
			if ( ! isset( $this->{$key} ) ) {
				$this->{$key} = $value;
			}
		}

		// Set the global list table to this class.
		$wp_list_table = $this; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/** Getters ***************************************************************/

	/**
	 * Return the post type of the current screen.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_screen_post_type() {

		return ! empty( $this->screen->post_type )
			? $this->screen->post_type
			: '';
	}

	/**
	 * Return the page of the current screen.
	 *
	 * @since 2.0.0
	 */
	protected function get_page() {

		return 'sugar-calendar';
	}

	/**
	 * Return the primary post type.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_primary_post_type() {

		return sugar_calendar_get_event_post_type_id();
	}

	/**
	 * Return the base URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_base_url() {

		return sugar_calendar_get_admin_base_url();
	}

	/**
	 * Return array of filters used on queried items.
	 *
	 * @since 2.1.2
	 *
	 * @return array
	 */
	protected function get_items_filter() {

		// Get the status.
		$status = $this->get_status();

		// Bail if viewing all.
		if ( $status === 'all' ) {
			return [];
		}

		// Return filter by status.
		return [
			'status' => $status,
		];
	}

	/**
	 * Return a properly formatted, multi-dimensional array of event counts,
	 * grouped by status.
	 *
	 * @since 2.0.0
	 * @since 2.1.6 $item_counts is populated by set_item_counts()
	 *
	 * @return array
	 */
	protected function get_item_counts() {

		return $this->item_counts;
	}

	/**
	 * Return array of intervals.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_recurrence_types() {

		return sugar_calendar_get_recurrence_types();
	}

	/**
	 * Return array of date query arguments, used for `date_query` parameter.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_date_query_args() {

		return sugar_calendar_get_date_query_args( $this->get_mode(), $this->view_start, $this->view_end );
	}

	/**
	 * Return tag IDs.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	protected function get_tags() {

		$tags_value = $this->get_request_var( 'sc_event_tags' );

		if ( empty( $tags_value ) ) {
			return [];
		}

		return TagsHelper::validate_tags_term_ids( explode( ',', $tags_value ) );
	}

	/**
	 * Return a human-readable time difference as a string.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $older_date The earlier time from which you're calculating
	 *                               the time elapsed. Enter either as an integer Unix timestamp,
	 *                               or as a date string of the format 'Y-m-d H:i:s'.
	 * @param int|bool   $newer_date Optional. Unix timestamp of date to compare older
	 *                               date to. Default: false (current time).
	 *
	 * @return string String representing the time since the older date, eg
	 *         "2 hours and 50 minutes".
	 */
	protected function get_human_diff_time( $older_date, $newer_date = false ) {

		return sugar_calendar_human_diff_time( $older_date, $newer_date );
	}

	/**
	 * Return a human-readable time difference as a string.
	 *
	 * @since 2.1.0
	 *
	 * @param string $timezone1 First Olson time zone ID.
	 * @param string $timezone2 Optional. Default: 'UTC' Second Olson time zone ID.
	 * @param mixed  $datetime  Optional. Default: 'now' Time to use for diff.
	 *
	 * @return string String representing the time difference - "2.5 hours"
	 */
	protected function get_human_diff_timezone( $timezone1 = '', $timezone2 = 'UTC', $datetime = 'now' ) {

		return sugar_calendar_human_diff_timezone( $timezone1, $timezone2, $datetime );
	}

	/**
	 * Return the color of an event.
	 *
	 * @since 2.0.0
	 *
	 * @param object $object Event object.
	 *
	 * @return string
	 */
	protected function get_item_color( $object ) {

		return sugar_calendar_get_event_color( $object->object_id, $object->type );
	}

	/**
	 * Get the current time.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_current_time() {

		return sugar_calendar_get_request_time( 'timestamp', $this->timezone );
	}

	/**
	 * Get the day each week starts on.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $start Day the week starts on.
	 *
	 * @return string
	 */
	protected function get_start_of_week( $start = '1' ) {

		return (string) sugar_calendar_get_user_preference( 'start_of_week', (string) $start );
	}

	/**
	 * Get the ISO-8601 week number for a Unix timestamp.
	 *
	 * @since 2.3.0
	 *
	 * @param int $timestamp Timestamp.
	 *
	 * @return string
	 */
	protected function get_week_for_timestamp( $timestamp = 0 ) {

		return gmdate( 'W', strtotime( 'this thursday', (int) $timestamp ) );
	}

	/**
	 * Get the date format.
	 *
	 * @since 2.0.0
	 *
	 * @param string $format Date format.
	 *
	 * @return string
	 */
	protected function get_date_format( $format = 'F j, Y' ) {

		return sugar_calendar_get_user_preference( 'date_format', $format );
	}

	/**
	 * Get the time format.
	 *
	 * @since 2.0.0
	 *
	 * @param string $format Time format.
	 *
	 * @return string
	 */
	protected function get_time_format( $format = 'g:i a' ) {

		return sugar_calendar_get_user_preference( 'time_format', $format );
	}

	/**
	 * Get the URL with persistent arguments.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Array of arguments to override.
	 *
	 * @return string
	 */
	protected function get_persistent_url( $args = [] ) {

		// Get tax terms.
		$tax_terms = $this->get_tax_terms();

		// Query arg defaults.
		$defaults = [
			'page'        => $this->get_page(),
			'cystart'     => $this->get_start_year(),
			'cy'          => $this->get_year(),
			'cm'          => $this->get_month(),
			'cd'          => $this->get_day(),
			'cz'          => $this->get_timezone(),
			'mode'        => $this->get_mode(),
			'max'         => $this->get_max(),
			'status'      => $this->get_status(),
			'object_type' => $this->get_object_type(),
			's'           => $this->get_search(),
		];

		// Parse arguments.
		$r = wp_parse_args( $args, array_merge( $defaults, $tax_terms ) );

		// Maybe unset default status.
		if ( $r['status'] === 'all' ) {
			unset( $r['status'] );
		}

		// Maybe unset default object type.
		if ( $r['object_type'] === 'post' ) {
			unset( $r['object_type'] );
		}

		// Maybe unset default search.
		if ( empty( $r['s'] ) ) {
			unset( $r['s'] );
		}

		// Maybe unset default time zone.
		if ( empty( $r['cz'] ) || ( $this->timezone === $r['cz'] ) ) {
			unset( $r['cz'] );
		}

		// Maybe unset list-years.
		if ( $r['mode'] !== 'list' ) {
			unset( $r['cystart'] );
		}

		// Use the base URL.
		$url = $this->get_base_url();

		// Add args & return.
		return add_query_arg( $r, $url );
	}

	/**
	 * Get the URL for today.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_today_url() {

		return $this->get_persistent_url(
			[
				'cy' => gmdate( 'Y', $this->now ),
				'cm' => gmdate( 'n', $this->now ),
				'cd' => gmdate( 'j', $this->now ),
			]
		);
	}

	/**
	 * Setup the list-table columns.
	 *
	 * @since 2.0.0
	 *
	 * @return array An associative array containing column information.
	 */
	public function get_columns() {

		static $retval = null;

		// Calculate if not calculated already.
		if ( $retval === null ) {

			// PHP day => day ID.
			$days = $this->get_week_days();

			// Setup return value.
			$retval = [];

			foreach ( $days as $key => $day ) {
				$retval[ $day ] = $GLOBALS['wp_locale']->get_weekday( $key );
			}
		}

		return $retval;
	}

	/**
	 * Get the days for any given week.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_week_days() {

		return $this->week_days;
	}

	/**
	 * Get the day-of-week ordinal.
	 *
	 * @since 2.2.0
	 *
	 * @param int $day Day of month.
	 *
	 * @return string
	 */
	protected function get_dow_ordinal( $day = 1 ) {

		// Default return value.
		$retval = '';

		// Possible day ordinals (no more than 5 per month).
		$ordinals = [
			1 => 'first',
			2 => 'second',
			3 => 'third',
			4 => 'fourth',
			5 => 'fifth',
		];

		// Get the ordinal of the day.
		$dow_ordinal = (int) ceil( $day / 7 );

		// Maybe set the return value.
		if ( ! empty( $ordinals[ $dow_ordinal ] ) ) {
			$retval = $ordinals[ $dow_ordinal ];
		}

		// Return.
		return $retval;
	}

	/**
	 * No columns are sortable.
	 *
	 * @since 2.0.0
	 *
	 * @return array An associative array containing the sortable columns
	 */
	protected function get_sortable_columns() {

		return [];
	}

	/**
	 * No columns are hidden.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_hidden_columns() {

		// Get hidden columns for screen.
		$screen  = get_current_screen();
		$columns = get_hidden_columns( $screen );

		// Return any hidden columns.
		return $columns;
	}

	/**
	 * No bulk actions.
	 *
	 * @since 2.0.0
	 *
	 * @return array An associative array containing all the bulk actions.
	 */
	public function get_bulk_actions() {

		return [];
	}

	/**
	 * Get the possible list table modes.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_modes() {

		return $this->modes;
	}

	/**
	 * Get the current mode.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_mode() {

		return $this->mode;
	}

	/**
	 * Get the current object type.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_object_type() {

		return $this->get_request_var( 'object_type', 'sanitize_key', 'post' );
	}

	/**
	 * Get the current month.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_month() {

		$default = gmdate( 'n', $this->now );

		return $this->get_request_var( 'cm', 'intval', $default );
	}

	/**
	 * Get the current day.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_day() {

		$default = gmdate( 'j', $this->now );

		return $this->get_request_var( 'cd', 'intval', $default );
	}

	/**
	 * Get the current year.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_year() {

		$default = gmdate( 'Y', $this->now );

		return $this->get_request_var( 'cy', 'intval', $default );
	}

	/**
	 * Get the requested start year for the list boundary.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_start_year() {

		$default = gmdate( 'Y', $this->now );

		return $this->get_request_var( 'cystart', 'intval', $default );
	}

	/**
	 * Get the current time zone.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	protected function get_timezone() {

		$default = $this->timezone;

		return $this->get_request_var( 'cz', 'urldecode', $default );
	}

	/**
	 * Get the current event status.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_status() {

		return $this->get_request_var( 'status', 'sanitize_key', 'all' );
	}

	/**
	 * Get the current term for a taxonomy.
	 *
	 * @since 2.0.0
	 *
	 * @param string $taxonomy_name Taxonomy name.
	 * @param string $default       Default name.
	 *
	 * @return mixed
	 */
	protected function get_tax_term( $taxonomy_name = '', $default = '' ) {

		return $this->get_request_var( $taxonomy_name, 'sanitize_key', $default );
	}

	/**
	 * Get taxonomy term requests.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_tax_terms() {

		// Default return value.
		$retval = [];

		// Get the taxonomies.
		$taxonomies = sugar_calendar_get_object_taxonomies(
			$this->get_primary_post_type()
		);

		// Maybe add taxonomies to tabs array.
		if ( empty( $taxonomies ) ) {
			return $retval;
		}

		// Loop through each taxonomy.
		foreach ( $taxonomies as $tax ) {

			// Look for term lookup.
			$term = $this->get_tax_term( $tax );

			// Skip if no request.
			if ( empty( $term ) ) {
				continue;
			}

			// Set.
			$retval[ $tax ] = $term;
		}

		// Return any taxonomy requests.
		return $retval;
	}

	/**
	 * Get the current page number.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_orderby() {

		return $this->get_request_var( 'orderby', 'sanitize_key', 'start' );
	}

	/**
	 * Get the current page number.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_order() {

		return $this->get_request_var( 'order', 'strtolower', 'asc' );
	}

	/**
	 * Get the current page number.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_search() {

		return $this->get_request_var( 's', 'wp_unslash' );
	}

	/**
	 * Get the maximum number of events per iteration.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	protected function get_max() {

		return $this->get_request_var( 'max', 'absint', $this->max );
	}

	/**
	 * Get a global request variable.
	 *
	 * @since 2.0.0
	 * @since 3.3.0 Update return value to mixed.
	 *
	 * @param string $var      Variable name.
	 * @param string $sanitize Sanitiazion function.
	 * @param string $default  Default value.
	 *
	 * @return mixed
	 */
	protected function get_request_var( $var = '', $sanitize = 'sanitize_text_field', $default = '' ) {

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return isset( $_REQUEST[ $var ] )
			? call_user_func( $sanitize, $_REQUEST[ $var ] )
			: $default;
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Get available statuses.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_supported_post_stati() {

		return [
			'publish',
			'future',
			'draft',
			'pending',
			'private',
			'hidden',
			'trash',
		];
	}

	/**
	 * Get removable query arguments.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_removable_args() {

		return [
			'filter_action',
			's',
			'order',
			'orderby',
			'status',
			'mode',
		];
	}

	/**
	 * Get a list of CSS classes for the list table table tag.
	 *
	 * @since 2.0.0
	 *
	 * @return array List of CSS classes for the table tag.
	 */
	protected function get_table_classes() {

		return [
			'widefat',
			'fixed',
			'striped',
			'calendar',
			'sugar-calendar-table',
			'sugar-calendar-table-events',
			'sugar-calendar-table-events--' . $this->get_mode(),
			$this->get_mode(),
			$this->get_status(),
			$this->_args['plural'],
		];
	}

	/**
	 * Get the calendar views.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_views() {

		// Output the event filter above the views.
		$this->event_search();

		// Screen.
		$base_url = $this->get_persistent_url();

		// Get the event counts for the current view.
		$event_counts = $this->get_item_counts();

		// Statuses.
		$avail_post_stati = $this->get_supported_post_stati();
		$event_statuses   = get_post_stati( [ 'show_in_admin_all_list' => false ] );

		// "All" link class.
		$all_class = ( $this->get_status() === 'all' )
			? 'current'
			: '';

		// "All" link text.
		$all_inner_html = sprintf( /* translators: %s - Item count. */
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$event_counts['total'],
				'List table: all statuses (excluding trash)',
				'sugar-calendar-lite'
			),
			number_format_i18n( $event_counts['total'] )
		);

		// "All" link URL.
		$all_url = remove_query_arg( 'status', $base_url );

		// Setup status links.
		$status_links = [
			'all' => '<a href="' . esc_url( $all_url ) . '" class="' . esc_attr( $all_class ) . '">' . $all_inner_html . '</a>',
		];

		// Other links.
		$event_statuses = get_post_stati( [ 'show_in_admin_status_list' => true ], 'objects' );

		// Loop through statuses and compile array of available ones.
		if ( ! empty( $event_statuses ) ) {
			foreach ( $event_statuses as $status ) {

				// Set variable to trick PHP.
				$status_name = $status->name;

				// Skip if not available status.
				if ( ! in_array( $status_name, $avail_post_stati, true ) ) {
					continue;
				}

				// Skip if no event count.
				if ( empty( $event_counts[ $status_name ] ) ) {
					continue;
				}

				// Set the class value.
				if ( $this->get_status() === $status_name ) {
					$class = 'current';
				} else {
					$class = '';
				}

				// Calculate the status text.
				$status_html = sprintf( translate_nooped_plural( $status->label_count, $event_counts[ $status_name ] ), number_format_i18n( $event_counts[ $status_name ] ) );
				$status_url  = add_query_arg( [ 'status' => $status_name ], $base_url );

				// Add link to array.
				$status_links[ $status_name ] = '<a href="' . esc_url( $status_url ) . '" class="' . esc_attr( $class ) . '">' . $status_html . '</a>';
			}
		}

		// Return array of HTML anchors.
		return $status_links;
	}

	/**
	 * Get the query arguments used to get events from the database.
	 *
	 * This is split up into a few separate methods to make overriding
	 * individual query arguments easier.
	 *
	 * @since 2.0.0
	 * @since 3.6.0 Add the 'object_subtype' arg.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	protected function all_query_args( $args = [] ) {

		// Maybe add `post` to to object_type's to query for.
		if ( post_type_supports( $this->get_screen_post_type(), 'events' ) ) {
			$args['object_type'] = ! empty( $args['object_type'] )
				? array_unshift( $args['object_type'], 'post' )
				: [ 'post' ];
		}

		// Setup default args.
		$defaults = [
			'number'         => $this->get_max(),
			'orderby'        => $this->get_orderby(),
			'order'          => $this->get_order(),
			'search'         => $this->get_search(),
			'date_query'     => $this->get_date_query_args(),
			'object_subtype' => sugar_calendar_get_event_post_type_id(),
		];

		// Parse the arguments.
		$r = wp_parse_args( $args, $defaults );

		// Check tags query var.
		$tags = $this->get_tags();

		// Add tags to query args.
		if ( ! empty( $tags ) ) {
			$r['sc_event_tags'] = $tags;
		}

		// Return parsed arguments.
		return $r;
	}

	/**
	 * Set a queried item in its proper array position.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $cell    Cell index.
	 * @param string $type    Cell item key.
	 * @param int    $item_id Cell item ID.
	 * @param mixed  $data    Cell item data.
	 */
	protected function set_queried_item( $cell = 1, $type = 'items', $item_id = 0, $data = [] ) {

		// Prevent debug notices if type is not set.
		if ( ! isset( $this->cells[ $cell ][ $type ] ) ) {
			$this->cells[ $cell ][ $type ] = [];
		}

		// Set the queried item.
		$this->cells[ $cell ][ $type ][ $item_id ] = $data;
	}

	/**
	 * Get the already queried items for a given day.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $cell Cell index.
	 * @param string $type Cell item type.
	 *
	 * @return array
	 */
	protected function get_queried_items( $cell = 1, $type = 'items' ) {

		// Bail if no cells or queried items.
		if ( empty( $this->cells ) || empty( $this->cells[ $cell ] ) || empty( $this->cells[ $cell ][ $type ] ) ) {
			return [];
		}

		// Return queried items.
		return $this->cells[ $cell ][ $type ];
	}

	/**
	 * Maybe skip an item in a cell.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item Cell item.
	 *
	 * @return bool
	 */
	protected function skip_item_in_cell( $item = false ) {

		return empty( $item );
	}

	/**
	 * Does an event belong inside the current cell?
	 *
	 * @since 2.0.0
	 * @since 2.1.2 Prefers Event::intersects() over Event::overlaps()
	 * @since 3.6.0 Fix issue with events extending one hour in week and day view.
	 *
	 * @param object $item Cell item.
	 *
	 * @return bool
	 */
	protected function is_item_for_cell( $item = false ) {

		// Bail if skipping.
		if ( $this->skip_item_in_cell( $item ) ) {
			return false;
		}

		// Start boundary.
		$start = $this->get_current_cell( 'start_dto' );

		if ( in_array( $this->mode, [ 'week', 'day' ], true ) && $start instanceof \DateTime ) {
			$start->modify( '+1 second' );
		}

		// End boundary.
		$end = $this->get_current_cell( 'end_dto' );

		// Get intersects.
		$retval = $item->intersects( $start, $end );

		// Return if event belongs in cell.
		return $retval;
	}

	/**
	 * Get events for a given cell.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_events_for_cell() {

		// Default return value.
		$retval = '';

		// Default items array.
		$items = $this->get_current_cell( 'items', [] );

		// Bail if no items.
		if ( empty( $items ) ) {
			return $retval;
		}

		// Loop through today's events.
		foreach ( $items as $item ) {
			$retval .= $this->get_event( $item );
		}

		// Return the output buffer.
		return $retval;
	}

	/**
	 * Get number of events in a specific cell.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $cell Cell index.
	 * @param string $type Cell item type.
	 *
	 * @return int
	 */
	protected function get_event_count_for_cell( $cell = 1, $type = 'items' ) {

		$events = $this->get_queried_items( $cell, $type );

		// Return 0 or number.
		return ! empty( $events )
			? count( $events )
			: 0;
	}

	/**
	 * Get an event link for use inside a table cell.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_event( $event = false ) {

		// Bail if event is empty.
		if ( empty( $event ) ) {
			return '';
		}

		// Get the link.
		$link = $this->get_event_link( $event );

		// Return the event link.
		return $link;
	}

	/**
	 * Return the string for the event title.
	 *
	 * Based on _draft_or_post_title() but is filtered, and not escaped.
	 *
	 * @since 2.1.7
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_event_title( $event = false ) {

		$title = ! empty( $event->title )
			? apply_filters( 'the_title', $event->title, $event->object_id ) // phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName
			: esc_html__( '(No title)', 'sugar-calendar-lite' );

		if ( $event->status === 'draft' ) {
			$title .= ' &mdash; ' . esc_html__( 'Draft', 'sugar-calendar-lite' );
		}

		return $title;
	}

	/**
	 * Return the HTML for linking to an event.
	 *
	 * @since 2.0.3
	 * @since 3.7.0 Added the `sugar_calendar_admin_events_table_event_title_url` filter.
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_event_link( $event = false ) {

		// Bail if event is empty.
		if ( empty( $event ) ) {
			return '';
		}

		// Get the cell.
		$cell = $this->get_current_cell( 'index' );

		/**
		 * Filter the event edit url.
		 *
		 * @since 3.7.0
		 *
		 * @param string $url   The event edit url.
		 * @param Event  $event The event object.
		 */
		$event_edit_url = apply_filters(
			'sugar_calendar_admin_events_table_event_title_url',
			$this->get_event_edit_url( $event ),
			$event
		);

		// Get the event title.
		$event_title = $this->get_event_title( $event );

		// Filter all event attributes.
		$attributes = [
			'href'    => esc_url( $event_edit_url ),
			'class'   => $this->get_event_classes( $event, $cell ),
			'style'   => $this->get_event_link_styling( $event ),
			'data-id' => $event->id,
		];

		// Default attribute string.
		$attr = '';

		// Loop through attributes and combine them (previously sanitized).
		foreach ( $attributes as $key => $value ) {
			$attr .= ' ' . $key . '="' . $value . '"';
		}

		// Setup the pointer for this event.
		$this->setup_pointer( $event, $cell );

		// Prepare the link HTML.
		$html = '<a %s><span>%s</span></a>';
		$link = sprintf( $html, $attr, esc_html( $event_title ) );

		// Return the event link.
		return $link;
	}

	/**
	 * Get event link styles.
	 *
	 * @since 2.0.3
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_event_link_styling( $event = false ) {

		$bg_color = $this->get_item_color( $event );

		if ( ! $bg_color ) {
			return '';
		}

		$color    = sugar_calendar_get_contrast_color( $bg_color );
		$css_vars = '--sugar-calendar-background-color: ' . $bg_color . '; --sugar-calendar-foreground-color: ' . $color . ';';

		return $css_vars;
	}

	/** Cell ******************************************************************/

	/**
	 * Whether to start a new row in the table.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function start_row() {

		$i = (int) $this->get_current_cell( 'index' );

		return ( $i % $this->day_count ) === 0;
	}

	/**
	 * Whether to end the current row in the table.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function end_row() {

		$i = (int) $this->get_current_cell( 'index' );

		return ( $i % $this->day_count ) === ( $this->day_count - 1 );
	}

	/**
	 * Set the items for the current cell.
	 *
	 * @since 2.1.3
	 */
	protected function set_cell_items() {

		// Loop through all items.
		foreach ( $this->all_items as $item ) {

			// Skip if event is not for cell.
			if ( ! $this->is_item_for_cell( $item ) ) {
				continue;
			}

			// Filtered items only.
			if ( in_array( $item->id, $this->filtered_ids, true ) ) {
				array_push( $this->current_cell['items'], $item );
			}

			// Count all items (reduced later).
			array_push( $this->current_cell['countable'], $item );
		}

		// Add the current cell to the cells array.
		$this->cells[] = $this->current_cell;
	}

	/**
	 * Set the items for all of the cells.
	 *
	 * @since 2.1.3
	 */
	protected function set_cells() {

		$row   = 0;
		$index = 0;

		// Loop through days in the grid.
		for ( $d = $this->grid_start; $d < $this->grid_end; $d += DAY_IN_SECONDS ) {

			$year  = gmdate( 'Y', $d );
			$month = gmdate( 'n', $d );
			$day   = gmdate( 'j', $d );

			// Setup cell boundaries.
			$this->set_cell_boundaries(
				[
					'start' => gmmktime( 0, 0, 0, $month, $day, $year ),
					'end'   => gmmktime( 23, 59, 59, $month, $day, $year ),
					'row'   => $row,
					'index' => $index,
				]
			);

			// Setup cell items.
			$this->set_cell_items();

			// Maybe end the row?
			if ( $this->end_row() ) {
				++$row;
			}

			$index++;
		}

		// Cleanup.
		$this->current_cell = [];
	}

	/**
	 * Set the current cell properties.
	 *
	 * @since 2.1.3
	 *
	 * @param array $args Cell properties.
	 */
	protected function set_cell_boundaries( $args = [] ) {

		// Parse arguments.
		$r = wp_parse_args(
			$args,
			[
				'index'     => null,
				'start'     => null,
				'end'       => null,
				'type'      => 'normal',
				'items'     => [],
				'countable' => [],
			]
		);

		// Get the time zone.
		$timezone = $this->get_timezone();

		// Add DateTime object for start.
		if ( ! empty( $r['start'] ) ) {
			$r['start_dto'] = sugar_calendar_get_datetime_object( $r['start'], $timezone );
		}

		// Add DateTime object for end.
		if ( ! empty( $r['end'] ) ) {
			$r['end_dto'] = sugar_calendar_get_datetime_object( $r['end'], $timezone );
		}

		// Set the current cell.
		$this->current_cell = $r;
	}

	/**
	 * Set the current cell using the $cells index.
	 *
	 * @since 2.1.3
	 *
	 * @param array $args Current cell arguments.
	 */
	protected function set_current_cell( $args = [] ) {

		// Get all matching cells.
		$cells = wp_list_filter( $this->cells, $args );

		// Pick the first matching cell.
		$this->current_cell = ! empty( $cells )
			? reset( $cells )
			: [];
	}

	/**
	 * Get the current cell properties.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key     Cell item key.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	protected function get_current_cell( $key = '', $default = null ) {

		// Return a specific cell key.
		if ( ! empty( $key ) && isset( $this->current_cell[ $key ] ) ) {
			return ! is_null( $this->current_cell[ $key ] )
				? $this->current_cell[ $key ]
				: $default;
		}

		// Return the entire array, or default return value.
		return is_null( $default )
			? $this->current_cell
			: $default;
	}

	/** Formatting ************************************************************/

	/**
	 * Get the date of the event.
	 *
	 * @since 2.0.0
	 *
	 * @param string $datetime Event datetime.
	 * @param string $timezone Event timezone.
	 *
	 * @return string
	 */
	protected function get_event_date( $datetime = '', $timezone = '' ) {

		return sugar_calendar_format_date_i18n( $this->date_format, $datetime, $timezone, $this->timezone );
	}

	/**
	 * Get the time of the event.
	 *
	 * @since 2.0.0
	 *
	 * @param string $datetime Event datetime.
	 * @param string $timezone Event timezone.
	 *
	 * @return string
	 */
	protected function get_event_time( $datetime = '', $timezone = '' ) {

		return sugar_calendar_format_date_i18n( $this->time_format, $datetime, $timezone, $this->timezone );
	}

	/**
	 * Get the time zone offset.
	 *
	 * @since 2.1.0
	 *
	 * @param array $args Timezone arguments.
	 *
	 * @return string
	 */
	protected function get_time_zone_offset( $args = [] ) {

		return sugar_calendar_get_timezone_offset( $args );
	}

	/** Pointers **************************************************************/

	/**
	 * Add an event to the pointers array.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 * @param int    $cell  Cell index.
	 */
	protected function setup_pointer( $event = false, $cell = false ) {

		// Bail if no event or no cell (0 is OK for cell).
		if ( empty( $event ) || ! is_numeric( $cell ) ) {
			return;
		}

		// Get pointer content HTML.
		$title = $this->get_pointer_title( $event );
		$text  = $this->get_pointer_text( $event );
		$links = $this->get_pointer_links( $event );

		ob_start();
		?>

		<div class="sugar_calendar_event_tooltip__header">
			<h3>
				<?php echo wp_kses( $title, $this->get_allowed_pointer_tags() ); ?>
			</h3>
		</div>
		<div class="sugar_calendar_event_tooltip__body">
			<?php echo wp_kses( $text, $this->get_allowed_pointer_tags() ); ?>
		</div>
		<div class="sugar_calendar_event_tooltip__footer">
			<?php echo wp_kses( implode( ' | ', $links ), $this->get_allowed_pointer_tags() ); ?>
		</div>

		<?php

		$pointer_content = ob_get_clean();

		/**
		 * Filter the event pointer content.
		 *
		 * @since 3.0.0
		 *
		 * @param string $pointer_content Pointer content.
		 * @param object $event           Event object.
		 * @param int    $cell            Cell index.
		 */
		$pointer_content = apply_filters( 'sugar_calendar_admin_events_tables_base_pointer_content', $pointer_content, $event, $cell );

		// Add pointer to pointers array.
		$this->pointers[ $event->id ] = $pointer_content;
	}

	/**
	 * Return the pointer title text.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_pointer_title( $event = false ) {

		// Get the event title.
		$title = $this->get_event_title( $event );

		// Default return value (text only).
		$retval = esc_js( $title );

		// Only link if not trashed.
		if ( $event->status !== 'trash' ) {

			// If user can edit, link to "edit object" page.
			if ( $this->current_user_can_edit( $event ) ) {
				$retval = $this->get_event_edit_link( $event, $retval );

				// If user can view, link to permalink.
			} elseif ( $this->current_user_can_view( $event ) ) {
				$retval = $this->get_event_view_link( $event, $retval );
			}
		}

		// Return.
		return $retval;
	}

	/**
	 * Return the pointer links HTML.
	 *
	 * @since 2.1.0
	 *
	 * @param object $event Event object.
	 *
	 * @return string[]
	 */
	protected function get_pointer_links( $event = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Default no links.
		$links = [];

		// Trashed, so maybe offer to Restore or Delete.
		if ( $event->status === 'trash' ) {

			// Maybe add restore link.
			if ( $this->current_user_can_delete( $event ) ) {
				$links['restore'] = $this->get_event_restore_link( $event, esc_html__( 'Restore', 'sugar-calendar-lite' ) );
			}

			// Maybe add delete link.
			if ( $this->current_user_can_delete( $event ) ) {
				$links['delete'] = $this->get_event_delete_link( $event, esc_html__( 'Delete Permanently', 'sugar-calendar-lite' ) );
			}

			// Not trashed, so offer to Edit or View.
		} else {

			// Maybe add edit & copy links.
			if ( $this->current_user_can_edit( $event ) ) {
				$links['edit'] = $this->get_event_edit_link( $event, esc_html_x( 'Edit', 'verb', 'sugar-calendar-lite' ) );
			}

			if ( $this->current_user_can_delete( $event ) ) {
				$links['delete'] = $this->get_event_delete_link( $event, esc_html_x( 'Trash', 'verb', 'sugar-calendar-lite' ) );
			}

			// Add view link.
			if ( $this->current_user_can_view( $event ) ) {
				$links['view'] = $this->get_event_view_link( $event, esc_html_x( 'View', 'verb', 'sugar-calendar-lite' ) );
			}
		}

		// Filter & return.
		return $this->filter_pointer_links( $links, $event );
	}

	/**
	 * Get the link used to edit an event.
	 *
	 * @since 2.0.0
	 * @since 3.6.0 Added the `sugar_calendar_admin_events_table_event_edit_link` filter.
	 *
	 * @param object $event     Event object.
	 * @param string $link_text Text of the link.
	 *
	 * @return string
	 */
	public function get_event_edit_link( $event = false, $link_text = '' ) {

		/**
		 * Filter the event edit link.
		 *
		 * @since 3.6.0
		 *
		 * @param string $link  The event edit link.
		 * @param Event  $event The event object.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_admin_events_table_event_edit_link',
			'<a href="' . esc_url( $this->get_event_edit_url( $event ) ) . '">' . $link_text . '</a>',
			$event
		);
	}

	/**
	 * Get the link used to copy an event.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event     Event object.
	 * @param string $link_text Text of the link.
	 *
	 * @return string
	 */
	public function get_event_copy_link( $event = false, $link_text = '' ) {

		return '<a href="' . esc_url( $this->get_event_copy_url( $event ) ) . '">' . $link_text . '</a>';
	}

	/**
	 * Get the link used to delete an event.
	 *
	 * @since 2.0.21
	 * @since 3.6.0 Added the `sugar_calendar_admin_events_tables_base_event_delete_link` filter.
	 *
	 * @param object $event     Event object.
	 * @param string $link_text Text of the link.
	 *
	 * @return string
	 */
	public function get_event_delete_link( $event = false, $link_text = '' ) {

		/**
		 * Filter the event delete link.
		 *
		 * @since 3.6.0
		 *
		 * @param string $link  The event delete link.
		 * @param Event  $event The event object.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_admin_events_table_event_delete_link',
			'<a href="' . esc_url( $this->get_event_delete_url( $event ) ) . '" class="delete">' . $link_text . '</a>',
			$event
		);
	}

	/**
	 * Get the link used to restore an event.
	 *
	 * @since 2.0.21
	 * @since 3.6.0 Added the `sugar_calendar_admin_events_table_event_restore_link` filter.
	 *
	 * @param object $event     Event object.
	 * @param string $link_text Text of the link.
	 *
	 * @return string
	 */
	public function get_event_restore_link( $event = false, $link_text = '' ) {

		/**
		 * Filter the event delete link.
		 *
		 * @since 3.6.0
		 *
		 * @param string $link  The event restore link.
		 * @param Event  $event The event object.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_admin_events_table_event_restore_link',
			'<a href="' . esc_url( $this->get_event_restore_url( $event ) ) . '">' . $link_text . '</a>',
			$event
		);
	}

	/**
	 * Get the link used to view an event.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event     Event object.
	 * @param string $link_text Text of the link.
	 *
	 * @return string
	 */
	public function get_event_view_link( $event = false, $link_text = '' ) {

		return '<a href="' . esc_url( Helper::get_event_frontend_url( $event ) ) . '">' . $link_text . '</a>';
	}

	/**
	 * Get the URL used to edit an event.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_event_edit_url( $event = false ) {

		// Default return value.
		$retval = '';

		// Type of object.
		switch ( $event->object_type ) {
			case 'post':
				$retval = get_edit_post_link( $event->object_id );
				break;

			case 'user':
				$retval = get_edit_user_link( $event->object_id );
				break;

			case 'comment':
				$retval = get_edit_comment_link( $event->object_id );
				break;
		}

		// Return the HTML.
		return $retval;
	}

	/**
	 * Get the URL used to copy an event.
	 *
	 * @since 2.1.7
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_event_copy_url( $event = false ) {

		// Default return value.
		$retval = $this->get_event_edit_url( $event );

		// Arguments.
		$action      = 'sc_copy';
		$request_uri = $_SERVER['REQUEST_URI'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$args        = [
			'action'          => $action,
			'wp_http_referer' => urlencode( wp_unslash( $request_uri ) ),
		];

		// URL.
		$url   = add_query_arg( $args, $retval );
		$nonce = "{$action}-{$event->object_type}_{$event->object_id}";

		// Return the URL.
		return wp_nonce_url( $url, $nonce );
	}

	/**
	 * Get the URL used to restore an event.
	 *
	 * @since 2.0.0
	 *
	 * @param Event $event Event object.
	 *
	 * @return string
	 */
	protected function get_event_delete_url( $event = false ) {

		// Default return value.
		$retval = $this->get_event_edit_url( $event );

		// Action.
		$action = ( $event->status !== 'trash' ) && EMPTY_TRASH_DAYS
			? 'trash'
			: 'delete';

		// Arguments.
		$request_uri = $_SERVER['REQUEST_URI'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$args        = [
			'action'          => $action,
			'wp_http_referer' => urlencode( wp_unslash( $request_uri ) ),
		];

		// URL.
		$url   = add_query_arg( $args, $retval );
		$nonce = "{$action}-{$event->object_type}_{$event->object_id}";

		// Return the URL.
		return wp_nonce_url( $url, $nonce );
	}

	/**
	 * Get the URL used to restore an event.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_event_restore_url( $event = false ) {

		// Default return value.
		$retval = $this->get_event_edit_url( $event );

		// Arguments.
		$request_uri = $_SERVER['REQUEST_URI'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$action      = 'untrash';
		$args        = [
			'action'          => $action,
			'wp_http_referer' => urlencode( wp_unslash( $request_uri ) ),
		];

		// URL.
		$url   = add_query_arg( $args, $retval );
		$nonce = "{$action}-{$event->object_type}_{$event->object_id}";

		// Return the URL.
		return wp_nonce_url( $url, $nonce );
	}

	/**
	 * Get all of the pointer text.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_pointer_text( $event = false ) {

		// Get all pointer info.
		$pointer = [
			$this->get_pointer_dates( $event ),
			$this->get_pointer_meta( $event ),
			$this->get_pointer_details( $event ),
		];

		// Filter out empties and merge.
		$pointer_text = array_merge( array_filter( $pointer ) );

		// Remove HTML tags that are not allowed.
		foreach ( $pointer_text as $key => $value ) {
			$pointer_text[ $key ] = wp_kses( $value, $this->get_allowed_pointer_tags() );
		}

		/**
		 * Filter the event pointer text.
		 *
		 * @since 3.0.0
		 *
		 * @param string $pointer_text Pointer text.
		 * @param object $event        Event object.
		 */
		$retval = (array) apply_filters( 'sugar_calendar_admin_events_tables_base_get_pointer_text', $pointer_text, $event );

		// Combine with line breaks.
		return implode( '', $retval );
	}

	/**
	 * Get the pointer details.
	 *
	 * @since 2.0.3
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_pointer_details( $event = false ) {

		$pointer_text = [];

		// Special case for password protected events.
		if ( ! empty( $event->post_password ) ) {
			$pointer_text['details_title'] = '<strong>' . esc_html__( 'Details', 'sugar-calendar-lite' ) . '</strong>';
			$pointer_text['details']       = '<span>' . esc_html__( 'Password protected', 'sugar-calendar-lite' ) . '</span>';

			// Post is not protected.
		} elseif ( ! empty( $event->content ) ) {

			// Trim content down to 25 words or less - no HTML, to be safe.
			$content = wp_trim_words( $event->content, 25 );

			// Title.
			$pointer_text['details_title'] = '<strong>' . esc_html__( 'Details', 'sugar-calendar-lite' ) . '</strong>';

			// Texturize.
			$pointer_text['details'] = '<span>' . esc_html( $content ) . '</span>';
		}

		/**
		 * Filter the event pointer details.
		 *
		 * @since 3.0.0
		 *
		 * @param string $pointer_text Pointer details.
		 * @param object $event        Event object.
		 */
		$retval = (array) apply_filters( 'sugar_calendar_admin_events_tables_base_get_pointer_details', $pointer_text, $event );

		// Separate with line breaks.
		return implode( '', $retval );
	}

	/**
	 * Get event dates for display in a pointer.
	 *
	 * @since 2.0.0
	 * @since 3.2.0 Corrected the start and end time labels for recurring events.
	 *
	 * @param Event $event Event object.
	 *
	 * @return array
	 */
	protected function get_pointer_dates( $event = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded,Generic.Metrics.NestingLevel.MaxExceeded

		$pointer_dates = [];

		// Default time zone offset strings.
		$stz = '';
		$etz = '';

		// Strip time zone formats from date & time formats.
		$df = $this->strip_timezone_format( $this->date_format );
		$tf = $this->strip_timezone_format( $this->time_format );

		// Start time zone.
		if ( ! empty( $event->start_tz ) ) {

			// Maybe show the original date, time, and zone.
			if ( ! empty( $this->timezone ) && ( $this->timezone !== $event->start_tz ) ) {
				$to = sprintf( /* translators: %1$s - Datetime; %2$s - Timezone. */
					esc_html_x( '%1$s %2$s', 'Time Time Zone', 'sugar-calendar-lite' ),
					sugar_calendar_format_date_i18n( $tf, $event->start, $event->start_tz ),
					sugar_calendar_format_timezone( $event->start_tz, true )
				);

				// Single time zone.
			} else {
				$to = sugar_calendar_format_timezone( $event->start_tz, true );
			}

			// Wrap in span.
			$stz = '<span class="sc-timezone">' . esc_html( $to ) . '</span>';
		}

		// End time zone.
		if ( ! empty( $event->end_tz ) ) {

			// Maybe show the original date, time, and zone.
			if ( ! empty( $this->timezone ) && ( $this->timezone !== $event->end_tz ) ) {
				$to = sprintf( /* translators: %1$s - Datetime; %2$s - Timezone. */
					esc_html_x( '%1$s %2$s', 'Time Time Zone', 'sugar-calendar-lite' ),
					sugar_calendar_format_date_i18n( $tf, $event->end, $event->end_tz ),
					sugar_calendar_format_timezone( $event->end_tz, true )
				);

				// Single time zone.
			} else {
				$to = sugar_calendar_format_timezone( $event->end_tz, true );
			}

			// Wrap in span.
			$etz = '<span class="sc-timezone">' . esc_html( $to ) . '</span>';

			// Use the start time zone string.
		} elseif ( ! empty( $stz ) ) {
			$etz = $stz;
		}

		// All day, single-day event.
		if ( $event->is_all_day() ) {

			// Start & end.
			$start = sugar_calendar_format_date_i18n( $this->date_format, $event->start, $event->start_tz );
			$end   = sugar_calendar_format_date_i18n( $this->date_format, $event->end, $event->end_tz );

			// Multi-day.
			if ( $event->is_multi( 'j' ) ) {

				// Yearly.
				if ( $event->recurrence === 'yearly' ) {
					$pointer_dates['start_title'] = '<strong>' . esc_html__( 'Start', 'sugar-calendar-lite' ) . '</strong>';
					$pointer_dates['start']       = '<span>' . esc_html( $start ) . '</span>';
					$pointer_dates['end_title']   = '<strong>' . esc_html__( 'End', 'sugar-calendar-lite' ) . '</strong>';
					$pointer_dates['end']         = '<span>' . esc_html( $end ) . '</span>';

					// Monthly.
				} elseif ( $event->recurrence === 'monthly' ) {
					$pointer_dates['start_title'] = '<strong>' . esc_html__( 'Start', 'sugar-calendar-lite' ) . '</strong>';
					$pointer_dates['start']       = '<span>' . esc_html( $start ) . '</span>';
					$pointer_dates['end_title']   = '<strong>' . esc_html__( 'End', 'sugar-calendar-lite' ) . '</strong>';
					$pointer_dates['end']         = '<span>' . esc_html( $end ) . '</span>';

					// No recurrence.
				} else {
					$pointer_dates['start_title'] = '<strong>' . esc_html__( 'Start', 'sugar-calendar-lite' ) . '</strong>';
					$pointer_dates['start']       = '<span>' . esc_html( $start ) . '</span>';
					$pointer_dates['end_title']   = '<strong>' . esc_html__( 'End', 'sugar-calendar-lite' ) . '</strong>';
					$pointer_dates['end']         = '<span>' . esc_html( $end ) . '</span>';
				}

				// Single all-day.
			} else {
				$pointer_dates['all_day_title'] = '<strong>' . esc_html__( 'All Day', 'sugar-calendar-lite' ) . '</strong>';
				$pointer_dates['all_day']       = '<span>' . esc_html( $start ) . '</span>';
			}

			// All other events.
		} elseif ( $event->is_multi( 'j' ) ) {

			// Start & end.
			$start = sugar_calendar_format_date_i18n( $this->date_format, $event->start, $event->start_tz );
			$end   = sugar_calendar_format_date_i18n( $this->date_format, $event->end, $event->end_tz );

			// Yearly.
			if ( $event->recurrence === 'yearly' ) {
				$pointer_dates['start_title'] = '<strong>' . esc_html__( 'Start', 'sugar-calendar-lite' ) . '</strong>';
				$pointer_dates['start']       = '<span>' . esc_html( $start ) . '</span>';
				$pointer_dates['end_title']   = '<strong>' . esc_html__( 'End', 'sugar-calendar-lite' ) . '</strong>';
				$pointer_dates['end']         = '<span>' . esc_html( $end ) . '</span>';

				// Monthly.
			} elseif ( $event->recurrence === 'monthly' ) {
				$pointer_dates['start_title'] = '<strong>' . esc_html__( 'Start', 'sugar-calendar-lite' ) . '</strong>';
				$pointer_dates['start']       = '<span>' . esc_html( $start ) . '</span>';
				$pointer_dates['end_title']   = '<strong>' . esc_html__( 'End', 'sugar-calendar-lite' ) . '</strong>';
				$pointer_dates['end']         = '<span>' . esc_html( $end ) . '</span>';

				// No recurrence.
			} else {

				// Display the start and end time if available.
				if ( ! $event->is_all_day() ) {
					$start .= ' - ' . esc_html( $this->get_event_time( $event->start, $event->start_tz ) );

					// Maybe append time zone.
					if ( ! empty( $stz ) ) {
						$start .= $stz;
					}

					if ( ! empty( $event->end ) ) {
						$end .= ' - ' . esc_html( $this->get_event_time( $event->end, $event->end_tz ) );

						// Maybe append time zone.
						if ( ! empty( $etz ) ) {
							$end .= $etz;
						}
					}
				}

				$pointer_dates['start_title'] = '<strong>' . esc_html__( 'Start', 'sugar-calendar-lite' ) . '</strong>';
				$pointer_dates['start']       = '<span>' . wp_kses( $start, [ 'span' => [ 'class' ] ] ) . '</span>';
				$pointer_dates['end_title']   = '<strong>' . esc_html__( 'End', 'sugar-calendar-lite' ) . '</strong>';
				$pointer_dates['end']         = '<span>' . wp_kses( $end, [ 'span' => [ 'class' ] ] ) . '</span>';
			}

			// Single day.
		} else {

			// Date & Time.
			if ( ! $event->is_empty_date( $event->start ) ) {

				$start = esc_html( $this->get_event_time( $event->start, $event->start_tz ) );

				// Maybe append time zone.
				if ( ! empty( $stz ) ) {
					$start .= $stz;
				}

				$pointer_dates['start_title'] = '<strong>' . esc_html__( 'Start', 'sugar-calendar-lite' ) . '</strong>';
				$pointer_dates['start']       = '<span>' . $start . '</span>';
			}

			// Date & Time.
			if ( ! $event->is_empty_date( $event->end ) && ( $event->start !== $event->end ) ) {
				$end = esc_html( $this->get_event_time( $event->end, $event->end_tz ) );

				// Maybe append time zone.
				if ( ! empty( $etz ) ) {
					$end .= $etz;
				}

				$pointer_dates['end_title'] = '<strong>' . esc_html__( 'End', 'sugar-calendar-lite' ) . '</strong>';
				$pointer_dates['end']       = '<span>' . $end . '</span>';
			}
		}

		/**
		 * Filter the event pointer date.
		 *
		 * @since 3.0.0
		 *
		 * @param array  $pointer_dates Pointer dates.
		 * @param object $event         Event object.
		 */
		$retval = (array) apply_filters( 'sugar_calendar_admin_events_tables_base_get_pointer_dates', $pointer_dates, $event );

		// Separate with line breaks.
		return implode( '', $retval );
	}

	/**
	 * Get event dates for display in a pointer.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 *
	 * @return array
	 */
	protected function get_pointer_meta( $event = false ) {

		$pointer_meta = [];

		// Location.
		if ( ! empty( $event->location ) ) {

			// Turn new lines into line breaks.
			$location = preg_replace( '/[\r\n]+/', '<br>', $event->location );

			// Title.
			$pointer_meta['location_title'] = '<strong>' . esc_html__( 'Location', 'sugar-calendar-lite' ) . '</strong>';

			// Location though kses, only allow breaks.
			$pointer_meta['location'] = '<span>' . $location . '</span>';
		}

		/**
		 * Filter the event pointer meta.
		 *
		 * @since 3.0.0
		 *
		 * @param array  $pointer_meta Pointer meta.
		 * @param object $event        Event object.
		 */
		$retval = (array) apply_filters( 'sugar_calendar_admin_events_tables_base_get_pointer_meta', $pointer_meta, $event );

		// Separate with line breaks.
		return implode( '', $retval );
	}

	/**
	 * Return array of allowed HTML tags to use in admin pointers.
	 *
	 * @since 2.0.0
	 *
	 * @return array Allowed HTML tags.
	 */
	public function get_allowed_pointer_tags() {

		return [
			'a'      => [
				'href'               => [],
				'class'              => [],
				'data-action'        => [],
				'data-occurrence-id' => [],
			],
			'strong' => [],
			'span'   => [ 'class' => [] ],
		];
	}

	/**
	 * Can the current user delete an event?
	 *
	 * @since 2.0.21
	 *
	 * @param object $event Event object.
	 *
	 * @return bool
	 */
	protected function current_user_can_delete( $event = false ) {

		return $this->user_can_delete( get_current_user_id(), $event );
	}

	/**
	 * Can a user ID delete an event?
	 *
	 * This method uses the object_type for the event to determine if the user
	 * can delete the related object_id.
	 *
	 * @since 2.0.21
	 *
	 * @param int    $user_id User ID.
	 * @param object $event   Event object.
	 *
	 * @return bool Default false. True if user can delete event.
	 */
	protected function user_can_delete( $user_id = 0, $event = false ) {

		// Bail if no user was passed.
		if ( empty( $user_id ) ) {
			return false;
		}

		// Get the cap, based on the object_type.
		switch ( $event->object_type ) {
			case 'post':
				$type = get_post_type( $event->object_id );
				$obj  = get_post_type_object( $type );
				$cap  = 'do_not_allow';

				// Map to delete_post if exists.
				if ( ! empty( $obj ) ) {
					$cap = $obj->cap->delete_post;
				}
				break;

			case 'user':
				$cap = 'delete_user';
				break;

			case 'comment':
				$cap = 'delete_comment';
				break;

			default:
				$cap = 'delete_event';
				break;
		}

		// Cast and return.
		return (bool) user_can( $user_id, $cap, $event->object_id );
	}

	/**
	 * Can the current user edit an event?
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 *
	 * @return bool
	 */
	public function current_user_can_edit( $event = false ) {

		return $this->user_can_edit( get_current_user_id(), $event );
	}

	/**
	 * Can a user ID edit an event?
	 *
	 * This method uses the object_type for the event to determine if the user
	 * can edit the related object_id.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id User ID.
	 * @param object $event   Event object.
	 *
	 * @return bool Default false. True if user can edit event.
	 */
	protected function user_can_edit( $user_id = 0, $event = false ) {

		// Bail if no user was passed.
		if ( empty( $user_id ) ) {
			return false;
		}

		// Get the cap, based on the object_type.
		switch ( $event->object_type ) {
			case 'post':
				$type = get_post_type( $event->object_id );
				$obj  = get_post_type_object( $type );
				$cap  = 'do_not_allow';

				// Map to edit_post if exists.
				if ( ! empty( $obj ) ) {
					$cap = $obj->cap->edit_post;
				}
				break;

			case 'user':
				$cap = 'edit_user';
				break;

			case 'comment':
				$cap = 'edit_comment';
				break;

			default:
				$cap = 'edit_event';
				break;
		}

		// Cast and return.
		return (bool) user_can( $user_id, $cap, $event->object_id );
	}

	/**
	 * Can the current user view an event?
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 *
	 * @return bool
	 */
	protected function current_user_can_view( $event = false ) {

		return $this->user_can_view( get_current_user_id(), $event );
	}

	/**
	 * Can a user ID view an event?
	 *
	 * This method uses the object_type for the event to determine if the user
	 * can view the related object_id.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id User ID.
	 * @param object $event   Event object.
	 *
	 * @return bool Default false. True if user can view event.
	 */
	protected function user_can_view( $user_id = 0, $event = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.NestingLevel.MaxExceeded

		// Bail if no user was passed.
		if ( empty( $user_id ) ) {
			return false;
		}

		// Get the cap, based on the object_type.
		switch ( $event->object_type ) {
			case 'post':
				$post = get_post( $event->object_id );
				$type = get_post_type( $event->object_id );
				$obj  = get_post_type_object( $type );
				$cap  = 'do_not_allow';

				// Must be viewable by WordPress standards.
				if ( is_post_type_viewable( $obj ) ) {

					// Some statuses require ability to edit.
					if ( in_array( $post->post_status, [ 'pending', 'draft', 'future' ], true ) ) {
						$cap = 'edit_post';

						// Map to view_post if exists.
					} elseif ( ! empty( $obj ) ) {
						$cap = $obj->cap->read_post;
					}
				}
				break;

			case 'user':
				$cap = 'view_user';
				break;

			case 'comment':
				$cap = 'view_comment';
				break;

			default:
				$cap = 'read_event';
				break;
		}

		// Cast and return.
		return (bool) user_can( $user_id, $cap, $event->object_id );
	}

	/** Output & Markup *******************************************************/

	/**
	 * Output default content for columns without explicit handlers.
	 *
	 * @since 2.0.15
	 *
	 * @param object $item        Current item.
	 * @param string $column_name Column name.
	 */
	public function column_default( $item, $column_name ) {

		// Default content.
		$default = '&mdash;';

		/**
		 * Filter column contents.
		 *
		 * @since 3.0.0
		 *
		 * @param string $default Column contents.
		 * @param object $item    Current item.
		 */
		$to_echo = apply_filters( "sugar_calendar_admin_events_tables_base_{$column_name}_contents", $default, $item );

		// Output the content.
		echo $to_echo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Prepare items for list-table display.
	 *
	 * @since 2.0.0
	 */
	public function prepare_items() {

		// Get query arguments.
		$args = $this->all_query_args();

		// Query for events in the view.
		$this->query = new Event_Query( $args );

		// Set filtered items.
		$this->set_filtered_items();

		// Set all items.
		$this->set_all_items();

		// Set cells.
		$this->set_cells();

		// Set item counts.
		$this->set_item_counts();
	}

	/**
	 * Output an event filter form.
	 *
	 * @since 3.0.0
	 *
	 * @param string $content Form contents.
	 *
	 * @return void
	 */
	protected function event_filter_form( $content = '' ) {

		// Look for post type screen.
		$post_type = $this->get_primary_post_type();

		// Get taxonomies for this post type.
		$taxonomies = sugar_calendar_get_object_taxonomies(
			$post_type,
			'objects'
		);

		$taxonomies = array_filter( $taxonomies, fn( $taxonomy ) => ! empty( $taxonomy->public ) );
		?>
        <form class="sugar-calendar-item-filters" method="get">
            <input type="hidden" name="object_type" value="<?php echo esc_attr( $this->get_object_type() ); ?>"/>
            <input type="hidden" name="status" value="<?php echo esc_attr( $this->get_status() ); ?>"/>
            <input type="hidden" name="mode" value="<?php echo esc_attr( $this->get_mode() ); ?>"/>
            <input type="hidden" name="page" value="<?php echo esc_attr( $this->get_page() ); ?>"/>
            <input type="hidden" name="cd" value="<?php echo esc_attr( $this->get_day() ); ?>"/>
            <input type="hidden" name="cm" value="<?php echo esc_attr( $this->get_month() ); ?>"/>

			<?php if ( $this->get_mode() === 'list' ) : ?>

                <input type="hidden" name="cy" value="<?php echo esc_attr( $this->get_year() ); ?>"/>
                <input type="hidden" name="cystart" value="<?php echo esc_attr( $this->get_start_year() ); ?>"/>

			<?php else : ?>

                <input type="hidden" name="cy" value="<?php echo esc_attr( $this->get_year() ); ?>"/>

			<?php endif; ?>

            <input type="hidden" name="cz" value="<?php echo esc_attr( $this->get_timezone() ); ?>"/>
            <input type="hidden" name="order" value="<?php echo esc_attr( $this->get_order() ); ?>"/>
            <input type="hidden" name="orderby" value="<?php echo esc_attr( $this->get_orderby() ); ?>"/>
            <input type="hidden" name="max" value="<?php echo esc_attr( $this->get_max() ); ?>"/>
            <input type="hidden" name="s" value="<?php _admin_search_query(); ?>">

			<?php foreach ( $taxonomies as $tax ) : ?>

                <input type="hidden" name="<?php echo esc_attr( $tax->name ); ?>" value="<?php echo esc_attr( $this->get_tax_term( $tax->name ) ); ?>">

			<?php endforeach; ?>

			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </form>
		<?php
	}

	/**
	 * Event filter to match the styling of the Media Filter.
	 *
	 * This methods outputs the HTML used to switch modes, search events, filter
	 * with taxonomies.
	 *
	 * @since 2.0.0
	 */
	public function event_filters() {

		// Picker.
		echo $this->mode_picker(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		ob_start();

		echo $this->extra_tablenav( 'bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->event_filter_form( ob_get_clean() );
	}

	/**
	 * Output the event search form.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function event_search() {

		ob_start()
		?>

        <p class="search-box">
            <label for="event-search-input" class="screen-reader-text"><?php esc_html_e( 'Search', 'sugar-calendar-lite' ); ?></label>
            <input type="search" id="event-search-input" class="search" name="s" value="<?php _admin_search_query(); ?>">
            <input type="submit" id="search-submit" class="button" value="<?php esc_html_e( 'Search', 'sugar-calendar-lite' ); ?>">
        </p>

		<?php

		$this->event_filter_form( ob_get_clean() );
	}

	/**
	 * Output the options menu.
	 *
	 * @since 3.0.0
	 * @since 3.7.0 Title is now mandatory in screen options.
	 *
	 * @return void
	 */
	public function options_menu() {

		global $wp_locale;

		// Get the default preferences.
		$preferences    = get_defaults();
		$start_of_week  = sugar_calendar_get_user_preference( 'start_of_week', $preferences['start_of_week'] );
		$events_max_num = sugar_calendar_get_user_preference( 'events_max_num', $preferences['events_max_num'] );

		?>
        <div class="sugar-calendar-screen-options">
            <button id="sugar-calendar-screen-options-toggle" class="sugar-calendar-screen-options-toggle button" type="button">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 19 19">
                    <path d="M18,11l-2.18,0c-0.17,0.7 -0.44,1.35 -0.81,1.93l1.54,1.54l-2.1,2.1l-1.54,-1.54c-0.58,0.36 -1.23,0.63 -1.91,0.79l0,2.18l-3,0l0,-2.18c-0.68,-0.16 -1.33,-0.43 -1.91,-0.79l-1.54,1.54l-2.12,-2.12l1.54,-1.54c-0.36,-0.58 -0.63,-1.23 -0.79,-1.91l-2.18,0l0,-2.97l2.17,0c0.16,-0.7 0.44,-1.35 0.8,-1.94l-1.54,-1.54l2.1,-2.1l1.54,1.54c0.58,-0.37 1.24,-0.64 1.93,-0.81l0,-2.18l3,0l0,2.18c0.68,0.16 1.33,0.43 1.91,0.79l1.54,-1.54l2.12,2.12l-1.54,1.54c0.36,0.59 0.64,1.24 0.8,1.94l2.17,0l0,2.97Zm-8.5,1.5c1.66,0 3,-1.34 3,-3c0,-1.66 -1.34,-3 -3,-3c-1.66,0 -3,1.34 -3,3c0,1.66 1.34,3 3,3Z"></path>
                </svg>
            </button>

            <div class="sugar-calendar-screen-options-menu" style="display: none;">
                <form action="" method="post">
					<?php wp_nonce_field( Area::SLUG ); ?>
                    <input type="hidden" name="mode" value="<?php echo esc_attr( $this->get_mode() ); ?>"/>
                    <input type="hidden" name="cd" value="<?php echo esc_attr( $this->get_day() ); ?>"/>
                    <input type="hidden" name="cm" value="<?php echo esc_attr( $this->get_month() ); ?>"/>
                    <input type="hidden" name="cy" value="<?php echo esc_attr( $this->get_year() ); ?>"/>

                    <fieldset>
                        <legend><?php esc_html_e( 'Columns', 'sugar-calendar-lite' ); ?></legend>
						<?php
						[ $columns, $hidden ] = $this->get_column_info();

						foreach ( $columns as $column_key => $column_display_name ) :

							// Skip if column is cb (checkbox).
							if ( $column_key === 'cb' ) {
								continue;
							}
							?>

                            <label>
								<?php if ( $column_key === 'title' ) : ?>

									<input
										type="checkbox"
										value="<?php echo esc_attr( $column_key ); ?>"
										checked
										disabled
									>
									<input type="hidden" name="sugar-calendar[columns][]" value="<?php echo esc_attr( $column_key ); ?>">

								<?php else : ?>

									<input
										type="checkbox"
										name="sugar-calendar[columns][]"
										value="<?php echo esc_attr( $column_key ); ?>"
										<?php checked( ! in_array( $column_key, $hidden, true ) ); ?>>
								<?php endif; ?>

								<?php
								echo wp_strip_all_tags( $column_display_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
                            </label>

						<?php endforeach; ?>

                    </fieldset>
                    <fieldset>
                        <legend><?php esc_html_e( 'Start of Week', 'sugar-calendar-lite' ); ?></legend>
                        <select name="sugar-calendar[start_of_week]">

							<?php foreach ( range( 0, 6 ) as $week_day ) : ?>

                                <option value="<?php echo absint( $week_day ); ?>" <?php selected( $start_of_week, $week_day ); ?>><?php echo esc_html( $wp_locale->get_weekday( $week_day ) ); ?></option>

							<?php endforeach; ?>

                        </select>
                    </fieldset>
                    <fieldset>
                        <legend><?php esc_html_e( 'Maximum Events', 'sugar-calendar-lite' ); ?></legend>
                        <input type="number" step="1" min="1" max="999" class="code" name="sugar-calendar[events_max_num]" maxlength="3" value="<?php echo absint( $events_max_num ); ?>">
                    </fieldset>
                    <p class="submit">
                        <button type="submit" name="sugar-calendar-submit" class="button"><?php esc_html_e( 'Save Options', 'sugar-calendar-lite' ); ?></button>
                    </p>
                </form>
            </div>
        </div>
		<?php
	}


	/**
	 * Display the table.
	 *
	 * @since 2.0.0
	 */
	public function display() {

		// Start an output buffer.
		ob_start();

		// Top.
		$this->display_tablenav( 'top' );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

        <table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
            <thead>
            <tr>
				<?php $this->print_column_headers(); ?>
            </tr>
            </thead>

            <tbody id="the-list" data-wp-lists='list:<?php echo $this->_args['singular']; ?>'>
			<?php $this->display_mode(); ?>
            </tbody>

            <tfoot>
            <tr>
				<?php $this->print_column_headers( false ); ?>
            </tr>
            </tfoot>
        </table>

		<?php
		// Bottom.
		$this->display_tablenav( 'bottom' );

		// End and flush the buffer.
		echo ob_get_clean();

		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Display a calendar by month and year.
	 *
	 * @since 2.0.0
	 */
	protected function display_mode() {
		// Performed by subclass.
	}

	/**
	 * Generate the table navigation above or below the table.
	 *
	 * @since 2.0.0
	 *
	 * @param string $which Table navigation area.
	 */
	protected function display_tablenav( $which = 'top' ) {

		?>

        <div class="tablenav <?php echo esc_attr( $which ); ?> sugar-calendar-tablenav sugar-calendar-tablenav-<?php echo esc_attr( $which ); ?>">
			<?php

			// Output Month, Year tablenav.
			echo $this->extra_tablenav( $which ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// Top only output.
			if ( $which === 'top' ) :

				// Pagination.
				echo $this->extra_tablenav( 'pagination' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				// Tools.
				echo $this->extra_tablenav( 'tools' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			endif;

			?>
            <br class="clear">
        </div>

		<?php
	}

	/**
	 * Output grid layout rules.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function output_grid_layout() {}

	/**
	 * Method to avoid putting out the default search box.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text     Search box content.
	 * @param string $input_id Search box HTML ID.
	 *
	 * @return string
	 */
	public function search_box( $text = '', $input_id = '' ) {

		return '';
	}

	/**
	 * Message to be displayed when there are no items.
	 *
	 * @since 2.0.0
	 */
	public function no_items() {
		// Do nothing; calendars always have rows.
	}

	/**
	 * Handle bulk action requests.
	 *
	 * @since 2.0.0
	 */
	public function process_bulk_action() {
		// No bulk actions.
	}

	/**
	 * Always have items.
	 *
	 * This method forces WordPress to always show the calendar rows, and never
	 * to trigger the `no_items()` method.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function has_items() {

		return true;
	}

	/**
	 * Get classes for event in day.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 * @param int    $cell  Cell index.
	 */
	protected function get_event_classes( $event = 0, $cell = 0 ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		// Empty classes array.
		$classes = [];

		// All day.
		$classes[] = ! empty( $event->all_day )
			? 'all-day'
			: '';

		// Multiple days.
		$start_day = gmdate( 'Y-m-d', strtotime( $event->start ) );
		$end_day   = gmdate( 'Y-m-d', strtotime( $event->end ) );

		if ( $start_day !== $end_day ) {
			$classes[] = 'multiple-days';
		}

		// Recurring.
		$classes[] = ! empty( $event->recurrence )
			? 'recur-' . sanitize_key( $event->recurrence )
			: '';

		// Location.
		$classes[] = get_event_meta( $event->id, 'location', true )
			? 'has-location'
			: '';

		// Color.
		$classes[] = get_event_meta( $event->id, 'color', true )
			? 'has-color'
			: '';

		// Get taxonomies.
		$taxos = sugar_calendar_get_object_taxonomies(
			$this->get_primary_post_type()
		);

		// Maybe loop through taxonomies, and add terms to.
		if ( ! empty( $taxos ) && is_array( $taxos ) ) {

			// Loop through taxonomies.
			foreach ( $taxos as $tax ) {

				// Check term cache first.
				$terms = get_object_term_cache( $event->object_id, $tax );

				// No cache, so query for terms.
				if ( $terms === false ) {
					$terms = wp_get_object_terms( $event->object_id, $tax );
				}

				// Bail if no terms in this taxonomy.
				if ( empty( $terms ) ) {
					continue;
				}

				// Add taxonomy to classes.
				$classes[] = "tax-{$tax}";

				// Loop through terms and add them, too.
				foreach ( $terms as $term ) {
					$classes[] = "term-{$term->slug}";
				}
			}
		}

		// Filter the event classes.
		$classes = array_unique( get_post_class( $classes, $event->object_id ) );

		// Join & return.
		return trim( implode( ' ', $classes ) );
	}

	/**
	 * Is a year/month/day today.
	 *
	 * @since 2.0.0
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @param int $day   Day.
	 *
	 * @return bool
	 */
	protected function is_today( $year = 0, $month = 0, $day = 0 ) {

		$_year  = (bool) ( $year == gmdate( 'Y', $this->now ) );
		$_month = (bool) ( $month == gmdate( 'n', $this->now ) );
		$_day   = (bool) ( $day == gmdate( 'j', $this->now ) );

		return (bool) ( $_year === true && $_month === true && $_day === true );
	}

	/**
	 * Is a year/month/day a weekend?
	 *
	 * @since 2.2.0
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @param int $day   Day.
	 *
	 * @return bool
	 */
	protected function is_weekend( $year = 0, $month = 0, $day = 0 ) {

		// Get the day.
		$j = (int) gmdate( 'w', strtotime( "{$year}-{$month}-{$day}" ) );

		// Is Sunday or Saturday.
		$retval = in_array( $j, [ 0, 6 ], true );

		// Return.
		return $retval;
	}

	/**
	 * Is a year/month/day the last of its day-of-week.
	 *
	 * @since 2.2.0
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @param int $day   Day.
	 *
	 * @return bool
	 */
	protected function is_dow_last( $year = 0, $month = 0, $day = 0 ) {

		// Get number of days in this month.
		$days = (int) gmdate( 't', strtotime( "{$year}-{$month}-{$day}" ) );

		// Is day-of-week.
		$retval = ( $day > ( $days - 7 ) );

		// Return.
		return $retval;
	}

	/**
	 * Get classes for table cell.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_cell_classes() {

		// Get the grid positions.
		$cell = $this->get_current_cell( 'index' );

		// Get the start.
		$start = $this->get_current_cell( 'start_dto' );

		$day   = $start->format( 'd' );
		$month = $start->format( 'n' );
		$year  = $start->format( 'Y' );

		// Get day of week, and day key.
		$dow        = ( $cell % $this->day_count );
		$day_key    = strtolower( $start->format( 'l' ) );
		$day_column = "column-{$day_key}";

		// Position & day info.
		$column_number = "column-{$dow}";
		$cell_number   = "cell-{$cell}";
		$day_number    = "day-{$day}";
		$month_number  = "month-{$month}";
		$year_number   = "year-{$year}";

		// Count.
		$count        = 0;
		$count_number = '';
		$has_events   = ! empty( $count )
			? 'not-empty'
			: '';

		// Day specific classes.
		$weekend            = '';
		$is_today           = '';
		$dow_last           = '';
		$dow_ordinal        = '';
		$is_different_month = '';

		// Day.
		if ( ! empty( $day ) ) {

			// Weekend.
			$weekend = $this->is_weekend( $year, $month, $day )
				? 'weekend'
				: 'weekday';

			// Today.
			$is_today = $this->is_today( $year, $month, $day )
				? 'today'
				: '';

			// Day-of-week last.
			$dow_last = $this->is_dow_last( $year, $month, $day )
				? 'last'
				: '';

			// Day-of-week ordinal.
			$dow_ordinal = 'dow-' . $this->get_dow_ordinal( $day );

			// Different month.
			$is_different_month = $this->month != $month
				? 'different-month'
				: '';
		}

		// Hidden?
		$hidden = in_array( $day_key, $this->get_hidden_columns(), true )
			? 'hidden'
			: '';

		// Assemble classes.
		$classes = array_filter(
			[
				'column',
				$is_today,
				$hidden,
				$dow_ordinal,
				$dow_last,
				$day_key,
				$weekend,
				$day_column,
				$has_events,
				$count_number,
				$column_number,
				$cell_number,
				$day_number,
				$month_number,
				$year_number,
				$is_different_month,
			]
		);

		// Trim spaces and return.
		return trim( implode( ' ', $classes ) );
	}

	/**
	 * Displays a taxonomy drop-downs for filtering in the bar table navigation.
	 *
	 * @since 3.0.0
	 * @since 3.7.0 Added filter for registered taxonomies.
	 *
	 * @return false|string|void
	 */
	protected function dropdown_taxonomies() {

		// Look for post type screen.
		$post_type = $this->get_primary_post_type();

		// Bail if no post types.
		if ( empty( $post_type ) ) {
			return;
		}

		/**
		 * Filter taxonomies for this post type.
		 *
		 * @since 3.7.0
		 *
		 * @param array $taxonomies Taxonomies.
		 */
		$taxonomies = apply_filters(
			'sugar_calendar_admin_events_tables_base_dropdown_taxonomies',
			sugar_calendar_get_object_taxonomies(
				$post_type,
				'objects'
			)
		);

		// Bail if no taxonomies.
		if ( empty( $taxonomies ) ) {
			return;
		}

		// Start an output buffer.
		ob_start();

		// Loop through taxonomies and setup the dropdowns.
		foreach ( $taxonomies as $tax ) {

			// Skip if private.
			if ( empty( $tax->public ) ) {
				continue;
			}

			$current = $this->get_tax_term( $tax->name );
			?>

            <label class="screen-reader-text" for="<?php echo esc_attr( $tax->name ); ?>">
				<?php
				sprintf( /* translators: %s taxonomy label. */
					esc_html__( 'Filter by %s', 'sugar-calendar-lite' ),
					$tax->labels->name
				);
				?>
            </label>

			<?php
			// Dropdown.
			wp_dropdown_categories(
				[
					'taxonomy'          => $tax->name,
					'name'              => $tax->name,
					'show_option_all'   => $tax->labels->all_items,
					'show_option_none'  => $tax->labels->no_terms,
					'option_none_value' => '__sc_none__',
					'hierarchical'      => $tax->hierarchical,
					'hide_empty'        => false,
					'show_count'        => false,
					'orderby'           => 'name',
					'value_field'       => 'slug',
					'selected'          => $current,
				]
			);
		}

		return ob_get_clean();
	}

	/**
	 * Output month & year inputs, for viewing relevant events.
	 *
	 * @since 2.0.0
	 *
	 * @param string $which Table navigation area.
	 */
	protected function extra_tablenav( $which = '' ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		// Start an output buffer.
		ob_start();

		/**
		 * Fires before extra table navigation.
		 *
		 * @since 3.0.0
		 *
		 * @param Base $table Table instance.
		 */
		do_action( "sugar_calendar_admin_events_tables_base_before_extra_tablenav_{$which}", $this );

		// Bar.
		if ( $which === 'bar' ) :

			// Get the taxonomies.
			$drop = $this->dropdown_taxonomies();

			// Output taxonomies and "Filter" button.
			if ( ! empty( $drop ) ) :
				echo $drop; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>

			<?php
			endif;

		// Top.
        elseif ( $which === 'top' ) :

			// Hide the month picker UI in List mode.
			if ( $this->get_mode() !== 'list' ) :
				?>

                <label for="cm" class="screen-reader-text"><?php esc_html_e( 'Switch to this month', 'sugar-calendar-lite' ); ?></label>
                <select name="cm" id="cm">

					<?php for ( $month_index = 1; $month_index <= 12; $month_index++ ) : ?>

                        <option value="<?php echo esc_attr( $month_index ); ?>" <?php selected( $month_index, $this->month ); ?>>
							<?php echo $GLOBALS['wp_locale']->get_month( $month_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </option>

					<?php endfor; ?>

                </select>

			<?php
			endif;

			// Show the day input UI for day mode only.
			if ( $this->get_mode() === 'day' ) :
				?>

                <label for="cd" class="screen-reader-text"><?php esc_html_e( 'Set the day', 'sugar-calendar-lite' ); ?></label>
                <input type="number" name="cd" id="cd" value="<?php echo (int) $this->day; ?>" size="2">

			<?php

			// Hide the day input UI for week mode.
            elseif ( $this->get_mode() === 'week' ) :
				?>

                <input type="hidden" name="cd" id="cd" value="<?php echo (int) $this->day; ?>">

			<?php
			endif;

			// Show start & end years for list mode.
			if ( $this->get_mode() === 'list' ) :
				?>

                <label for="cystart" class="screen-reader-text"><?php esc_html_e( 'Set the first year', 'sugar-calendar-lite' ); ?></label>
                <input type="number" name="cystart" id="cystart" value="<?php echo (int) $this->get_start_year(); ?>">

                <span><?php esc_html_e( 'to', 'sugar-calendar-lite' ); ?></span>

                <label for="cy" class="screen-reader-text"><?php esc_html_e( 'Set the last year', 'sugar-calendar-lite' ); ?></label>
                <input type="number" name="cy" id="cy" value="<?php echo (int) $this->get_year(); ?>">

			<?php

			// Show single year for non-list modes.
			else :
				?>

                <label for="cy" class="screen-reader-text"><?php esc_html_e( 'Set the year', 'sugar-calendar-lite' ); ?></label>
                <input type="number" name="cy" id="cy" value="<?php echo (int) $this->year; ?>">

			<?php endif; ?>

            <input type="hidden" name="mode" value="<?php echo esc_attr( $this->get_mode() ); ?>"/>

            <input type="hidden" name="order" value="<?php echo esc_attr( $this->get_order() ); ?>"/>
            <input type="hidden" name="orderby" value="<?php echo esc_attr( $this->get_orderby() ); ?>"/>
            <input type="hidden" name="s" value="<?php echo esc_attr( $this->get_search() ); ?>"/>

			<?php

			// Taxonomies.
			$tax_terms = $this->get_tax_terms();

			if ( ! empty( $tax_terms ) ) :
				foreach ( $tax_terms as $tax => $term ) :
					?>
                    <input type="hidden" name="<?php echo esc_attr( $tax ); ?>" value="<?php echo esc_attr( $term ); ?>"/>
				<?php
				endforeach;
			endif;

			// Output the "View" button.
			submit_button( esc_html_x( 'View', 'verb', 'sugar-calendar-lite' ), 'action', '', false, [ 'id' => 'doaction' ] );

			// Maybe output an "Empty Trash" button.
			if ( ( $this->get_status() === 'trash' ) && current_user_can( get_post_type_object( $this->get_primary_post_type() )->cap->edit_others_posts ) && $this->has_items() ) :
				submit_button( esc_html__( 'Empty Trash', 'sugar-calendar-lite' ), 'apply', 'delete_all_trashed_events', false );
			endif;

			// Nonce for event actions.
			wp_nonce_field( 'event-actions' );

		// Output pagination.
        elseif ( $which === 'pagination' ) :
			echo $this->pagination(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Output tools.
        elseif ( $which === 'tools' ) :
			echo $this->tools(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		endif;

		/**
		 * Fires after extra table navigation.
		 *
		 * @since 3.0.0
		 *
		 * @param Base $table Table instance.
		 */
		do_action( "sugar_calendar_admin_events_tables_base_after_extra_tablenav_{$which}", $this );

		// Return.
		return ob_get_clean();
	}

	/**
	 * Paginate through months & years.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Pagination arguments.
	 *
	 * @return string
	 */
	protected function pagination( $args = [] ) {

		// Parse args.
		$r = wp_parse_args(
			$args,
			[
				'small'  => '1 month',
				'large'  => '1 year',
				'labels' => [
					'today'      => esc_html__( 'Today', 'sugar-calendar-lite' ),
					'next_small' => esc_html__( 'Next month', 'sugar-calendar-lite' ),
					'next_large' => esc_html__( 'Next year', 'sugar-calendar-lite' ),
					'prev_small' => esc_html__( 'Previous month', 'sugar-calendar-lite' ),
					'prev_large' => esc_html__( 'Previous year', 'sugar-calendar-lite' ),
				],
			]
		);

		// Base URLs.
		$today = $this->get_today_url();

		// Today's small & large timestamps.
		$ts = $this->today;
		$tl = $this->today;

		// Adjust small for month.
		if ( strstr( $r['small'], 'month' ) ) {
			$ts = strtotime( gmdate( 'Y-m-01', $ts ) );
		}

		// Adjust large for month.
		if ( strstr( $r['large'], 'month' ) ) {
			$tl = strtotime( gmdate( 'Y-m-01', $tl ) );
		}

		// Calculate previous & next weeks & months.
		$prev_small = strtotime( "-{$r['small']}", $ts );
		$next_small = strtotime( "+{$r['small']}", $ts );
		$prev_large = strtotime( "-{$r['large']}", $tl );
		$next_large = strtotime( "+{$r['large']}", $tl );

		// Week.
		$prev_small_d = gmdate( 'j', $prev_small );
		$prev_small_m = gmdate( 'n', $prev_small );
		$prev_small_y = gmdate( 'Y', $prev_small );
		$next_small_d = gmdate( 'j', $next_small );
		$next_small_m = gmdate( 'n', $next_small );
		$next_small_y = gmdate( 'Y', $next_small );

		// Month.
		$prev_large_d = gmdate( 'j', $prev_large );
		$prev_large_m = gmdate( 'n', $prev_large );
		$prev_large_y = gmdate( 'Y', $prev_large );
		$next_large_d = gmdate( 'j', $next_large );
		$next_large_m = gmdate( 'n', $next_large );
		$next_large_y = gmdate( 'Y', $next_large );

		// Setup month args.
		$prev_small_args = [
			'cy' => $prev_small_y,
			'cm' => $prev_small_m,
			'cd' => $prev_small_d,
		];
		$prev_large_args = [
			'cy' => $prev_large_y,
			'cm' => $prev_large_m,
			'cd' => $prev_large_d,
		];
		$next_small_args = [
			'cy' => $next_small_y,
			'cm' => $next_small_m,
			'cd' => $next_small_d,
		];
		$next_large_args = [
			'cy' => $next_large_y,
			'cm' => $next_large_m,
			'cd' => $next_large_d,
		];

		// Setup links.
		$prev_small_link = add_query_arg( $prev_small_args, $today );
		$next_small_link = add_query_arg( $next_small_args, $today );
		$prev_large_link = add_query_arg( $prev_large_args, $today );
		$next_large_link = add_query_arg( $next_large_args, $today );

		// Start an output buffer.
		ob_start();
		?>

        <div class="tablenav-pages sugar-calendar-tablenav sugar-calendar-tablenav-pages">

			<?php

			/**
			 * Fires before table pagination.
			 *
			 * @since 3.0.0
			 *
			 * @param Base $table Table instance.
			 */
			do_action( 'sugar_calendar_admin_events_tables_base_before_pagination', $this );
			?>

            <a class="previous-page button" href="<?php echo esc_url( $prev_large_link ); ?>">
                <span class="screen-reader-text"><?php echo esc_html( $r['labels']['prev_large'] ); ?></span>
                <span aria-hidden="true">&laquo;</span>
            </a>

            <a class="previous-page button" href="<?php echo esc_url( $prev_small_link ); ?>">
                <span class="screen-reader-text"><?php echo esc_html( $r['labels']['prev_small'] ); ?></span>
                <span aria-hidden="true">&lsaquo;</span>
            </a>

            <a href="<?php echo esc_url( $today ); ?>" class="previous-page today button">
                <span class="screen-reader-text"><?php echo esc_html( $r['labels']['today'] ); ?></span>
                <span aria-hidden="true"><?php echo esc_html( $r['labels']['today'] ); ?></span>
            </a>

            <a class="next-page button" href="<?php echo esc_url( $next_small_link ); ?>">
                <span class="screen-reader-text"><?php echo esc_html( $r['labels']['next_small'] ); ?></span>
                <span aria-hidden="true">&rsaquo;</span>
            </a>

            <a class="next-page button" href="<?php echo esc_url( $next_large_link ); ?>">
                <span class="screen-reader-text"><?php echo esc_html( $r['labels']['next_large'] ); ?></span>
                <span aria-hidden="true">&raquo;</span>
            </a>

			<?php

			/**
			 * Fires after table pagination.
			 *
			 * @since 3.0.0
			 *
			 * @param Base $table Table instance.
			 */
			do_action( 'sugar_calendar_admin_events_tables_base_after_pagination', $this );
			?>

        </div>

		<?php

		// Return.
		return ob_get_clean();
	}

	/**
	 * Additional tools.
	 *
	 * @since 2.1.1
	 *
	 * @return string
	 */
	private function tools() {

		// Time zone.
		$tztype   = sugar_calendar_get_timezone_type();
		$floating = sugar_calendar_is_timezone_floating();
		$timezone = sugar_calendar_format_timezone( $this->timezone );

		// Start an output buffer.
		ob_start();
		?>

        <div class="tablenav-tools sugar-calendar-tablenav sugar-calendar-tablenav-tools">

			<?php

			/**
			 * Fires before table tools.
			 *
			 * @since 3.0.0
			 *
			 * @param Base $table Table instance.
			 */
			do_action( 'sugar_calendar_admin_events_tables_base_before_tools', $this );

			// Output time zone if not floating or support is enabled.
			if ( ( $floating === false ) || ( $tztype !== 'off' ) ) :
				?>

                <span class="sc-timezone"><?php echo esc_html( $timezone ); ?></span>

			<?php
			endif;

			/**
			 * Fires after table tools.
			 *
			 * @since 3.0.0
			 *
			 * @param Base $table Table instance.
			 */
			do_action( 'sugar_calendar_admin_events_tables_base_after_tools', $this );
			?>

        </div>

		<?php

		// Return.
		return ob_get_clean();
	}

	/**
	 * Display the mode switcher.
	 *
	 * @since 2.0.0
	 *
	 * @param string $which Mode picker area.
	 */
	public function mode_picker( $which = 'top' ) {

		// Only switch on top.
		if ( $which !== 'top' ) {
			return;
		}

		// Get these ahead of the foreach loop.
		$base_url  = $this->get_persistent_url();
		$modes     = $this->get_modes();
		$removable = $this->get_removable_args();
		$mode_url  = remove_query_arg( $removable, $base_url );

		// Start an output buffer.
		ob_start();
		?>

        <div class="sugar-calendar-view-modes">
            <input type="hidden" name="mode" value="<?php echo esc_attr( $this->get_mode() ); ?>"/>

			<?php

			// Loop through modes.
			foreach ( $modes as $mode => $title ) :

				// Setup the URL by adding & removing args.
				$url = add_query_arg(
					[ 'mode' => $mode ],
					$mode_url
				);

				// Setup classes.
				$classes = [
					'sugar-calendar-view-mode',
					'sugar-calendar-view-mode-' . $mode,
				];

				if ( $this->get_mode() === $mode ) {
					$classes[] = 'current';
				}

				$classes = implode( ' ', $classes );
				?>

                <a href="<?php echo esc_url( $url ); ?>"
                   class="<?php echo esc_attr( $classes ); ?>"
                   title="<?php echo esc_attr( $title ); ?>">
                    <span class='screen-reader-text'><?php echo esc_html( $title ); ?></span>
                </a>

			<?php endforeach; ?>

        </div>

		<?php

		// Return the output buffer.
		return ob_get_clean();
	}

	/**
	 * Strip timezone formatting from a DateTime format string.
	 *
	 * Used to avoid duplicate time zone output in the specific places where
	 * we manually always output a formatted time zone string.
	 *
	 * @since 2.1.2
	 *
	 * @param string $format Timezone format.
	 *
	 * @return string
	 */
	private function strip_timezone_format( $format = '' ) {

		// Time zone formats to remove.
		$tz_formats = [ 'e', 'I', 'O', 'P', 'T', 'Z' ];

		return str_replace( $tz_formats, '', $format );
	}

	/**
	 * Filters pointer links according to the Event::object_type property.
	 *
	 * This method fires WordPress hooks for third-party plugin support, and
	 * also adds fallback support for unknown object types.
	 *
	 * @since 2.1.8
	 *
	 * @param array  $links Array of pointer links.
	 * @param object $event Event object.
	 *
	 * @return array
	 */
	private function filter_pointer_links( $links = [], $event = false ) {

		// Remove any empty links.
		$links = array_filter( $links );

		/**
		 * Filter pointer links.
		 *
		 * @since 3.0.0
		 *
		 * @param array  $links Array of links.
		 * @param object $event Event object.
		 * @param Base   $table Table instance.
		 */
		$links = (array) apply_filters( 'sugar_calendar_admin_events_tables_base_get_pointer_links', $links, $event, $this );

		// Type of object.
		switch ( $event->object_type ) {
			case 'post':
				$object = get_post( $event->object_id );
				$type   = is_post_type_hierarchical( $object->post_type ) ? 'page' : 'post';
				$filter = "{$type}_row_actions";
				break;

			case 'user':
				$object = get_userdata( $event->object_id );
				$filter = 'user_row_actions';
				break;

			case 'comment':
				$object = get_comment( $event->object_id );
				$filter = 'comment_row_actions';
				break;

			case 'sc_event':
				$object = $event;
				$filter = 'sugar_calendar_admin_event_row_actions';
				break;

			default:
				$object = $event;
				$filter = 'sugar_calendar_admin_default_row_actions';
				break;
		}

		/**
		 * Filter pointer action links.
		 *
		 * @since 3.0.0
		 *
		 * @param array  $links Array of links.
		 * @param object $event Event object.
		 */
		return (array) apply_filters( $filter, $links, $object ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Get all items.
	 *
	 * @since 3.6.0
	 *
	 * @return Event[]
	 */
	public function get_all_items() {

		return $this->all_items;
	}
}
