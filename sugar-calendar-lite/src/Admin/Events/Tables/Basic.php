<?php

namespace Sugar_Calendar\Admin\Events\Tables;

use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;

/**
 * Event table.
 *
 * This list table is responsible for showing events in a traditional table.
 * It will look a lot like `WP_Posts_List_Table` but extends our base, and shows
 * events in a monthly way.
 *
 * @since 3.0.0
 */
class Basic extends Base {

	/**
	 * The mode of the current view.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $mode = 'list';

	/**
	 * Whether an item has an end.
	 *
	 * @since 2.0.15
	 *
	 * @var bool
	 */
	private $item_ends = false;

	/**
	 * The main constructor method.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Constructor arguments.
	 */
	public function __construct( $args = [] ) {

		parent::__construct( $args );

		// Process bulk actions.
		$this->process_bulk_action();

		// Compensate for inverted user supplied values.
		if ( $this->start_year >= $this->year ) {
			$view_start = "{$this->year}-01-01 00:00:00";
			$view_end   = "{$this->start_year}-12-31 23:59:59";
		} else {
			$view_start = "{$this->start_year}-01-01 00:00:00";
			$view_end   = "{$this->year}-12-31 23:59:59";
		}

		// Set the view.
		$this->set_view( $view_start, $view_end );

		// Filter the Date_Query arguments for this List Table.
		$this->filter_date_query_arguments();

		// Add hooks.
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 3.3.0
	 */
	public function hooks() {

		// Add admin notice.
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		// Prevent checkbox to be hidden.
		add_filter( 'hidden_columns', [ $this, 'manage_hidden_columns' ], 10, 2 );
	}

	/**
	 * Prevent checkbox to be hidden.
	 *
	 * @since 3.5.0
	 *
	 * @param array     $hidden Hidden columns.
	 * @param WP_Screen $screen Screen object.
	 *
	 * @return array
	 */
	public function manage_hidden_columns( $hidden, $screen ) {

		// Handle the first load of the list when the filters was saved.
		if (
			! empty( $_POST['_wpnonce'] ) &&
			! empty( $_POST['sugar-calendar']['columns'] ) &&
			wp_verify_nonce( $_POST['_wpnonce'], Area::SLUG )
		) {
			// Get the columns that was recently hidden.
			$hidden = array_diff( array_keys( $this->get_columns() ), $_POST['sugar-calendar']['columns'] );

			// Remove "cb" in the column since we always want to show this.
			$search_cb = array_search( 'cb', $hidden, true );

			if ( $search_cb !== false ) {
				unset( $hidden[ $search_cb ] );
			}

			return $hidden;
		}

		// If screen parent_base is not sugar-calendar, return hidden.
		if ( $screen->parent_base !== 'sugar-calendar' ) {
			return $hidden;
		}

		// Remove cb from hidden.
		$hidden = array_diff( $hidden, [ 'cb' ] );

		return $hidden;
	}

	/**
	 * Output admin notices.
	 *
	 * @since 3.3.0
	 */
	public function admin_notices() {

		// If bulk_trashed_event is set, show a notice.
		$is_trashed_event = $this->get_request_var( 'bulk_trashed_event' );

		if ( $is_trashed_event ) {

			// Use _n to print admin notice.
			WP::add_admin_notice(
				/* translators: %s: Number of events trashed. */
				sprintf( _n( '%s event moved to the Trash.', '%s events moved to the Trash.', $is_trashed_event, 'sugar-calendar-lite' ), $is_trashed_event ),
				WP::ADMIN_NOTICE_SUCCESS,
				true
			);
		}

		// If bulk_deleted_event is set, show a notice.
		$is_deleted_event = $this->get_request_var( 'bulk_deleted_event' );

		if ( $is_deleted_event ) {

			WP::add_admin_notice(
				sprintf(
					/* translators: %s: Number of events deleted. */
					_n( '%s event permanently deleted.', '%s events permanently deleted.', $is_deleted_event, 'sugar-calendar-lite' ),
					$is_deleted_event
				),
				WP::ADMIN_NOTICE_SUCCESS,
				true
			);
		}

		// If bulk_restored_event is set, show a notice.
		$is_restored_event = $this->get_request_var( 'bulk_restored_event' );

		if ( $is_restored_event ) {

			WP::add_admin_notice(
				sprintf(
					/* translators: %s: Number of events restored. */
					_n( '%s event restored.', '%s events restored.', $is_restored_event, 'sugar-calendar-lite' ),
					$is_restored_event
				),
				WP::ADMIN_NOTICE_SUCCESS,
				true
			);
		}

		WP::display_admin_notices();
	}

	/**
	 * Get the current page number.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_order() {

		return $this->get_request_var( 'order', 'strtolower', 'desc' );
	}

	/**
	 * Juggle the filters used on Date_Query arguments, so that the List Table
	 * of Events only shows current-year, including ones that may cross over
	 * between multiple years, but not their recurrences.
	 *
	 * @since 2.0.15
	 */
	protected function filter_date_query_arguments() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		/**
		 * First, we need to remove the Recurring arguments that may exist in
		 * Standard Recurring, included in non-Lite versions.
		 */
		$removed = remove_filter( 'sugar_calendar_get_recurring_date_query_args', 'Sugar_Calendar\\Standard\\Recurring\\query_args', 10, 4 );

		// Bail if not removed.
		if ( empty( $removed ) ) {
			return;
		}

		/**
		 * Last, we need to add a new filter for Recurring arguments so that
		 * they conform better to a List Table view (vs. a Calendar view).
		 */
		add_filter( 'sugar_calendar_get_recurring_date_query_args', [ $this, 'filter_recurring_query_args' ], 10 );
	}

	/**
	 * Return array of recurring query arguments, used in Date_Query.
	 *
	 * This method is only made public so that it can use WordPress hooks. Do
	 * not rely on calling this method directly. Consider it private.
	 *
	 * Recurring events
	 * - recurrence starts before the view ends
	 * - recurrence ends after the view starts
	 * - start and end do not matter
	 *
	 * @since  2.0.15
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public function filter_recurring_query_args( $args = [] ) {

		// Override filtered query arguments completely.
		$args = [
			'relation' => 'AND',

			// Recurring Ends.
			[
				'relation' => 'OR',

				// No end (recurs forever) - exploits math in Date_Query
				// This might break someday. Works great now though!
				[
					'column'    => 'recurrence_end',
					'inclusive' => true,
					'before'    => '0000-01-01 00:00:00',
				],

				// Ends after the beginning of this view.
				[
					'column'    => 'recurrence_end',
					'inclusive' => true,
					'after'     => $this->view_start,
				],
			],

			// Make sure events are only for this year.
			[
				'relation' => 'AND',
				[
					'column'  => 'start',
					'compare' => '>=',
					'year'    => $this->year,
				],
				[
					'column'  => 'end',
					'compare' => '<=',
					'year'    => $this->year,
				],
			],
		];

		// Return the newly filtered query arguments.
		return $args;
	}

	/**
	 * Prevent base class from setting cells.
	 *
	 * @since 2.1.6
	 */
	protected function set_cells() {}

	/**
	 * Set item counts for the List mode list-table.
	 *
	 * @since 2.1.6
	 */
	protected function set_item_counts() {

		// Default return value.
		$this->item_counts = [
			'total' => 0,
		];

		// Items to count.
		if ( ! empty( $this->query->items ) ) {

			// Pluck all queried statuses.
			$statuses = wp_list_pluck( $this->query->items, 'status' );

			// Get unique statuses only.
			$statuses = array_unique( $statuses );

			// Set total to count of all items.
			$this->item_counts['total'] = count( $this->query->items );

			// Loop through statuses.
			foreach ( $statuses as $status ) {

				// Get items of this status.
				$items = wp_filter_object_list(
					$this->query->items,
					[
						'status' => $status,
					]
				);

				// Add count to return value.
				$this->item_counts[ $status ] = count( $items );
			}
		}

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
			'sugar_calendar_admin_events_tables_basic_item_counts',
			$this->item_counts,
			$this->view_start,
			$this->view_end,
			$this->get_status()
		);
	}

	/**
	 * Mock function for custom list table columns.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_columns() {

		// Default columns.
		$columns = [
			'cb'       => '<input type="checkbox">',
			'title'    => esc_html_x( 'Title', 'Noun', 'sugar-calendar-lite' ),
			'start'    => esc_html_x( 'Start', 'Noun', 'sugar-calendar-lite' ),
			'end'      => esc_html_x( 'End', 'Noun', 'sugar-calendar-lite' ),
			'tags'     => TagsHelpers::get_tags_taxonomy_labels( 'name' ),
			'duration' => esc_html_x( 'Duration', 'Noun', 'sugar-calendar-lite' ),
		];

		// Repeat column.
		if ( has_filter( 'sugar_calendar_get_recurring_date_query_args' ) ) {
			$columns['repeat'] = esc_html_x( 'Repeats', 'Noun', 'sugar-calendar-lite' );
		}

		// Return columns.
		return $columns;
	}

	/**
	 * Allow columns to be sortable.
	 *
	 * @since 2.0.0
	 *
	 * @return array An associative array containing the sortable columns.
	 */
	protected function get_sortable_columns() {

		return [
			'title' => [ 'title', true ],
			'start' => [ 'start', true ],
			'end'   => [ 'end', true ],
		];
	}

	/**
	 * Return the "title" column as the primary column name.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {

		return 'title';
	}

	/**
	 * Return the checkbox column.
	 *
	 * @since 3.3.0
	 *
	 * @param object $item Current item.
	 *
	 * @return string
	 */
	public function column_cb( $item = null ) {

		// Bail if no item.
		if ( empty( $item ) ) {
			return;
		}

		return sprintf(
			'<input type="checkbox" name="post[]" value="%s"/>',
			esc_attr( $item->object_id )
		);
	}

	/**
	 * No bulk actions.
	 *
	 * @since 3.3.0
	 *
	 * @return array An associative array containing all the bulk actions.
	 */
	public function get_bulk_actions() {

		$actions = [];

		// Actions in trash status.
		if ( $this->get_request_var( 'status' ) === 'trash' ) {

			$actions['restore'] = esc_html__( 'Restore', 'sugar-calendar-lite' );
			$actions['delete']  = esc_html__( 'Delete Permanently', 'sugar-calendar-lite' );

		} else {

			// Default actions.
			$actions['trash'] = esc_html__( 'Move to Trash', 'sugar-calendar-lite' );

			// Edit tags action. Handled via JS.
			$actions['edit_tags'] = esc_html__( 'Edit Tags', 'sugar-calendar-lite' );
		}

		return $actions;
	}

	/**
	 * Generate the table navigation above or below the table.
	 *
	 * @since 3.3.0
	 *
	 * @param string $which Table navigation area.
	 */
	protected function display_tablenav( $which = 'top' ) {

		if ( $which === 'top' ) {
			wp_nonce_field( 'bulk-actions-' . $this->_args['plural'], 'bulk_actions_wpnonce', false );
		}
		?>

        <div class="tablenav <?php echo esc_attr( $which ); ?> sugar-calendar-tablenav sugar-calendar-tablenav-<?php echo esc_attr( $which ); ?>">

			<?php if ( $this->has_items() ) : ?>
				<div class="alignleft actions bulkactions">
					<?php $this->bulk_actions( $which ); ?>
				</div>
			<?php endif; ?>

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
	 * Handle bulk action requests.
	 *
	 * @since 3.3.0
	 */
	public function process_bulk_action() {

		// Validate nonce, by default it's already sanitized.
		$nonce       = $this->get_request_var( 'bulk_actions_wpnonce' );
		$bulk_action = $this->get_request_var( 'action2' );

		// Validate nonce.
		if ( ! wp_verify_nonce( $nonce, 'bulk-actions-' . $this->_args['plural'] ) ) {
			return;
		}

		// Define action map.
		$actions_map = [
			'trash'   => [
				'callback' => 'wp_trash_post',
				'event'    => 'bulk_trashed_event',
			],
			'delete'  => [
				'callback' => 'wp_delete_post',
				'event'    => 'bulk_deleted_event',
			],
			'restore' => [
				'callback' => 'wp_untrash_post',
				'event'    => 'bulk_restored_event',
			],
		];

		// If the bulk action is not supported or no items, do nothing.
		if ( ! isset( $actions_map[ $bulk_action ] ) ) {
			return;
		}

		// Get the items for the action.
		$items = $this->get_request_var( 'post', [ $this, 'sanitize_array_key_values' ] );

		// Bail if no items.
		if ( empty( $items ) ) {
			return;
		}

		// Get redirect URL.
		$redirect = remove_query_arg( [ 'action', 'action2', 'bulk_actions_wpnonce' ] );

		// Process the items.
		foreach ( $items as $post_id ) {
			call_user_func( $actions_map[ $bulk_action ]['callback'], (int) $post_id ?? false );
		}

		// Add success message to the redirect URL.
		$redirect = add_query_arg( $actions_map[ $bulk_action ]['event'], count( $items ), $redirect );

		// Redirect to remove query string.
		wp_safe_redirect( $redirect );
		exit;
	}


	/**
	 * Sanitize array key and value.
	 *
	 * @since 3.3.0
	 *
	 * @param array $unsanitized_array Array to sanitize.
	 *
	 * @return array
	 */
	public function sanitize_array_key_values( $unsanitized_array = [] ) {

		// Default return value.
		$sanitized_array = [];

		// Loop through unsanitized_array.
		foreach ( $unsanitized_array as $key => $value ) {

			// Sanitize key.
			$key = sanitize_key( $key );

			// Sanitize value.
			$value = sanitize_text_field( $value );

			// Add to return value.
			$sanitized_array[ $key ] = $value;
		}

		// Return sanitized array.
		return $sanitized_array;
	}

	/**
	 * Return the contents for the "Title" column.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item Current item.
	 *
	 * @return string
	 */
	public function column_title( $item = null ) {

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

		// Start an output buffer to make syntax easier to read.
		ob_start();

		// Items in trash are not editable.
		if ( $item->status === 'trash' ) {
			?>

            <strong class="status-trash"><?php echo $this->get_event_title( $item ); ?></strong>

			<?php
			// Items not in trash get linked.
		} else {

			?>

            <strong><?php echo $this->get_event_link( $item ); ?></strong>

			<?php
		}

		// Output the row actions.
		echo $this->row_actions(
			$this->get_pointer_links( $item )
		);

		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		// Return the buffer.
		return ob_get_clean();
	}

	/**
	 * Return the contents for the "Start" column.
	 *
	 * @since 2.0.15
	 *
	 * @param object $item Current item.
	 *
	 * @return string
	 */
	public function column_start( $item = null ) {

		// Default return value.
		$retval = '&mdash;';

		// Bail if empty date.
		if ( $item->is_empty_date( $item->start ) ) {
			return $retval;
		}

		// Floating.
		$format = 'Y-m-d\TH:i:s';
		$tz     = 'floating';

		// Non-floating.
		if ( ! empty( $item->start_tz ) ) {

			// Get the offset.
			$offset = sugar_calendar_get_timezone_offset(
				[
					'time'     => $item->start,
					'timezone' => $item->start_tz,
				]
			);

			// Add timezone to format.
			$format = "Y-m-d\TH:i:s{$offset}";
		}

		// Format the date/time.
		$dt = $item->start_date( $format );

		// All-day Events have floating time zones.
		if ( ! empty( $item->start_tz ) && ! $item->is_all_day() ) {
			$tz = $item->start_tz;
		}

		// Start the <time> tag, with timezone data.
		$retval = '<time datetime="' . esc_attr( $dt ) . '" title="' . esc_attr( $dt ) . '" data-timezone="' . esc_attr( $tz ) . '">';
		$retval .= '<span class="sc-date">' . $this->get_event_date( $item->start, $item->start_tz ) . '</span>';

		// Maybe add time if not all-day.
		if ( ! $item->is_all_day() ) {
			$retval .= '<br><span class="sc-time">' . $this->get_event_time( $item->start, $item->start_tz ) . '</span>';

			// Maybe add timezone.
			if ( ! empty( $item->start_tz ) ) {
				$retval .= '<br><span class="sc-timezone">' . sugar_calendar_format_timezone( $tz ) . '</span>';
			}
		}

		// Close the <time> tag.
		$retval .= '</time>';

		// Return the <time> tag.
		return $retval;
	}

	/**
	 * Return the contents for the "End" column.
	 *
	 * @since 2.0.15
	 *
	 * @param object $item Current item.
	 *
	 * @return string
	 */
	public function column_end( $item = null ) {

		// Default return value.
		$retval = '&mdash;';

		// Bail if empty date.
		if ( $item->is_empty_date( $item->end ) ) {
			return $retval;
		}

		// Bail if start & end are exactly the same.
		if ( $item->start === $item->end ) {
			return $retval;
		}

		// Bail if all-day and only 1 day.
		if ( $item->is_all_day() && ( $item->start_date( 'Y-m-d' ) === $item->end_date( 'Y-m-d' ) ) ) {
			return $retval;
		}

		// Floating.
		$format = 'Y-m-d\TH:i:s';
		$tz     = 'floating';

		// Non-floating.
		if ( ! empty( $item->end_tz ) ) {

			// Get the offset.
			$offset = sugar_calendar_get_timezone_offset(
				[
					'time'     => $item->end,
					'timezone' => $item->end_tz,
				]
			);

			// Add timezone to format.
			$format = "Y-m-d\TH:i:s{$offset}";
		}

		// Format the date/time.
		$dt = $item->end_date( $format );

		// All-day Events have floating time zones.
		if ( ! empty( $item->end_tz ) && ! $item->is_all_day() ) {
			$tz = $item->end_tz;

			// Maybe fallback to the start time zone.
		} elseif ( ! empty( $item->start_tz ) ) {
			$tz = $item->start_tz;
		}

		// Start the <time> tag, with timezone data.
		$retval = '<time datetime="' . esc_attr( $dt ) . '" title="' . esc_attr( $dt ) . '" data-timezone="' . esc_attr( $tz ) . '">';
		$retval .= '<span class="sc-date">' . $this->get_event_date( $item->end, $item->end_tz ) . '</span>';

		// Maybe add time if not all-day.
		if ( ! $item->is_all_day() ) {
			$retval .= '<br><span class="sc-time">' . $this->get_event_time( $item->end, $item->end_tz ) . '</span>';

			// Maybe add timezone.
			if ( ! empty( $item->end_tz ) ) {
				$retval .= '<br><span class="sc-timezone">' . sugar_calendar_format_timezone( $tz ) . '</span>';
			}
		}

		// Close the <time> tag.
		$retval .= '</time>';

		// Return the <time> tag.
		return $retval;
	}

	/**
	 * Return the contents for the "Duration" column.
	 *
	 * @since 2.0.15
	 *
	 * @param object $item Current item.
	 *
	 * @return string
	 */
	public function column_duration( $item = null ) {

		// Default return value.
		$retval = '&mdash;';

		// Duration.
		if ( $item->is_all_day() ) {
			$retval = esc_html__( 'All Day', 'sugar-calendar-lite' );

			// Maybe add duration if multiple all-day days.
			if ( $item->is_multi() ) {
				$retval .= '<br>' . $this->get_human_diff_time( $item->start, $item->end );
			}

			// Get diff only if end exists.
		} elseif ( ( $item->start !== $item->end ) && ! $item->is_empty_date( $item->end ) ) {

			// Default date times.
			$start = strtotime( $item->start );
			$end   = strtotime( $item->end );

			// Adjust start by time zone.
			if ( ! empty( $item->start_tz ) ) {
				$str   = sprintf( '%s %s', $item->start, $item->start_tz );
				$start = strtotime( $str );
			}

			// Adjust end by time zone.
			if ( ! empty( $item->end_tz ) ) {
				$str = sprintf( '%s %s', $item->end, $item->end_tz );
				$end = strtotime( $str );
			}

			// Get human readible date time difference.
			$retval = $this->get_human_diff_time( $start, $end );

			// Look for a time zone difference.
			$difference = $this->get_human_diff_timezone( $item->start_tz, $item->end_tz );

			// Wrap difference in a decorative span.
			if ( ! empty( $difference ) ) {
				$retval .= '<br><span class="sc-timechange">' . esc_html( $difference ) . '</span>';
			}
		}

		// Return the duration.
		return $retval;
	}

	/**
	 * Return the contents for the "Repeats" column.
	 *
	 * @since 2.0.15
	 *
	 * @param object $item Current item.
	 *
	 * @return string
	 */
	public function column_repeat( $item = null ) {

		// Default return value.
		$retval = '&mdash;';

		// Get recurrence type.
		if ( ! empty( $item->recurrence ) ) {
			$intervals = $this->get_recurrence_types();

			// Interval is known.
			if ( isset( $intervals[ $item->recurrence ] ) ) {
				$retval = $intervals[ $item->recurrence ];
			}
		}

		// Return the repeat.
		return $retval;
	}

	/**
	 * Paginate through months & years.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Pagination arguments.
	 */
	protected function pagination( $args = [] ) {

		// Parse args.
		$r = wp_parse_args(
			$args,
			[
				'small'  => '1 year',
				'large'  => '10 years',
				'labels' => [
					'today'      => esc_html__( 'Today', 'sugar-calendar-lite' ),
					'next_small' => esc_html__( 'Next Month', 'sugar-calendar-lite' ),
					'next_large' => esc_html__( 'Next Year', 'sugar-calendar-lite' ),
					'prev_small' => esc_html__( 'Previous Month', 'sugar-calendar-lite' ),
					'prev_large' => esc_html__( 'Previous Year', 'sugar-calendar-lite' ),
				],
			]
		);

		// Return pagination.
		return parent::pagination( $r );
	}

	/**
	 * Output all rows.
	 *
	 * @since 2.0.0
	 */
	public function display_mode() {

		/**
		 * Filter the list table items before they are displayed.
		 *
		 * @since 3.6.0
		 *
		 * @param Event[] $list_items The list of items to display.
		 * @param Basic   $this       The current instance of the list table.
		 */
		$list_items = apply_filters(
			'sugar_calendar_admin_events_tables_basic_items_display_before',
			$this->filtered_items,
			$this
		);

		// Attempt to display rows.
		if ( ! empty( $list_items ) ) {

			/**
			 * Fires before displaying the table rows.
			 *
			 * @since 3.7.0
			 *
			 * @param Basic $this ListTable instance.
			 */
			do_action( 'sugar_calendar_admin_events_tables_basic_before_rows', $this );

			// Loop through items and show them.
			foreach ( $list_items as $item ) {
				$this->single_row( $item );
			}

			/**
			 * Fires after displaying the table rows.
			 *
			 * @since 3.7.0
			 *
			 * @param Basic $this ListTable instance.
			 */
			do_action( 'sugar_calendar_admin_events_tables_basic_after_rows', $this );

			// No rows to display.
		} else {
			$this->no_items();
		}
	}

	/**
	 * Message to be displayed when there are no items.
	 *
	 * @since 2.0.0
	 */
	public function no_items() {

		// Get the column count.
		$count = $this->get_column_count();
		?>
        <tr>
            <td colspan="<?php echo absint( $count ); ?>">

				<?php esc_html_e( 'No events found.', 'sugar-calendar-lite' ); ?>

            </td>
        </tr>
		<?php
	}

	/**
	 * Output a single row.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item Current item.
	 */
	public function single_row( $item ) {

		// Default item end back to false, for "Duration" column.
		$this->item_ends = false;
		?>

        <tr id="event-<?php echo esc_attr( $item->id ); ?>">
			<?php $this->single_row_columns( $item ); ?>
        </tr>

		<?php
	}
}
