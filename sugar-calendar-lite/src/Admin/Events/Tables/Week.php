<?php

namespace Sugar_Calendar\Admin\Events\Tables;

/**
 * Calendar Week List Table.
 *
 * This list table is responsible for showing events in a traditional table,
 * even though it extends the `WP_List_Table` class. Tables & lists & tables.
 *
 * @since 2.0.0
 */
class Week extends Grid {

	/**
	 * The mode of the current view.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $mode = 'week';

	/**
	 * The main constructor method.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Constructor arguments.
	 */
	public function __construct( $args = [] ) {

		parent::__construct( $args );

		// Get days.
		$days       = array_values( $this->get_week_days() );
		$first_day  = $days[0];
		$last_day   = $days[ count( $days ) - 1 ];
		$week_start = ( $this->start_of_week === gmdate( 'w', $this->today ) );

		// Reset the week.
		$where_is_thumbkin = ( $week_start === true )
			? "this {$first_day} midnight"
			: "last {$first_day} midnight";

		// Get fresh for the weekend.
		$here_i_am = ( $week_start === true )
			? "this {$last_day} midnight -1 second"
			: "next {$first_day} midnight -1 second";

		// Setup the week ranges.
		$this->grid_start = strtotime( $where_is_thumbkin, $this->today );
		$this->grid_end   = strtotime( $here_i_am, $this->today );

		// Setup the week ranges.
		$view_start = gmdate( 'Y-m-d H:i:s', $this->grid_start );
		$view_end   = gmdate( 'Y-m-d H:i:s', $this->grid_end );

		// Setup views.
		$this->set_view( $view_start, $view_end );
	}

	/**
	 * Setup the list-table columns.
	 *
	 * Overrides base class to add the "week" column.
	 *
	 * @since 2.0.0
	 *
	 * @return array An associative array containing column information
	 */
	public function get_columns() {

		static $retval = null;

		// Calculate if not calculated already.
		if ( $retval === null ) {

			// Link text.
			/* translators: %s - week number. */
			$string = esc_html_x(
				'Week %s',
				'Week number',
				'sugar-calendar-lite'
			);
			$week   = $this->get_week_for_timestamp( $this->today );
			$text   = sprintf( $string, $week );

			// Setup return value.
			$retval = [
				'hour' => '<span>' . esc_html( $text ) . '</span>',
			];

			// PHP day => day ID.
			$days = $this->get_week_days();

			// Set initial time.
			$time = $this->grid_start;

			// Loop through days and add them to the return value.
			foreach ( $days as $day ) {

				// Arguments.
				$args = [
					'mode' => 'day',
					'cy'   => gmdate( 'Y', $time ),
					'cm'   => gmdate( 'm', $time ),
					'cd'   => gmdate( 'd', $time ),
				];

				// Link text.
				$text = gmdate( 'M j, D', $time );

				// Bump time.
				$time = $time + ( DAY_IN_SECONDS * 1 );

				// Calculate link to day view.
				$link_to_day = add_query_arg( $args, $this->get_persistent_url() );

				// Column.
				$retval[ $day ] = '<a href="' . esc_url( $link_to_day ) . '" class="day-number">' . esc_html( $text ) . '</a>';
			}
		}

		// Return columns.
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
				'small'  => '1 week',
				'large'  => '1 month',
				'labels' => [
					'today'      => esc_html__( 'Today', 'sugar-calendar-lite' ),
					'next_small' => esc_html__( 'Next Week', 'sugar-calendar-lite' ),
					'next_large' => esc_html__( 'Next Month', 'sugar-calendar-lite' ),
					'prev_small' => esc_html__( 'Previous Week', 'sugar-calendar-lite' ),
					'prev_large' => esc_html__( 'Previous Month', 'sugar-calendar-lite' ),
				],
			]
		);

		// Return pagination.
		return parent::pagination( $r );
	}

	/**
	 * Skip items based on their all-day or multi-day duration.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item Current item.
	 *
	 * @return bool
	 */
	protected function skip_item_in_cell( $item = false ) {

		// Default return value.
		$retval = false;

		// Get the current cell type.
		$cell_type = $this->get_current_cell( 'type' );

		// Maybe skip an event in a cell.
		switch ( $cell_type ) {
			// Only allow all-day events in all-day cells.
			case 'all_day':
				if ( ! $item->is_all_day() ) {
					$retval = true;
				}
				break;

			// Only allow multi-day events in multi-day cells.
			case 'multi_day':
				if ( $item->is_all_day() || ! $item->is_multi( 'j' ) ) {
					$retval = true;
				}
				break;

			// Prevent all-day and multi-day in regular cells.
			default:
				if ( $item->is_all_day() || $item->is_multi( 'j' ) ) {
					$retval = true;
				}
				break;
		}

		// Return if skipping.
		return (bool) $retval;
	}

	/**
	 * Return the class for the "hour" column.
	 *
	 * @since 2.0.15
	 *
	 * @return string
	 */
	protected function get_hour_class() {

		// Hour column.
		$columns = $this->get_hidden_columns();

		// Return.
		return in_array( 'hour', $columns, true )
			? ' hidden'
			: '';
	}

	/**
	 * Start the week with a table row, and a th to show the time.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Spans row type (either 'all_day' or 'multi_day').
	 */
	protected function get_event_spans_row( $type ) {

		if ( ! in_array( $type, [ 'all_day', 'multi_day' ] ) ) {
			return;
		}

		$items = [];
		$cells = [];
		$days  = $this->get_week_days();

		for ( $i = 0; $i <= $this->day_count - 1; $i++ ) {
			// Setup the row and offset.
			$offset = ceil( DAY_IN_SECONDS * $i );
			$dt     = $this->grid_start + $offset;

			// Calc the day/month/year.
			$day   = gmdate( 'd', $dt );
			$month = gmdate( 'm', $dt );
			$year  = gmdate( 'Y', $dt );

			// Set the boundaries.
			$start = gmmktime( 0, 0, 0, $month, $day, $year );
			$end   = gmmktime( 23, 59, 59, $month, $day, $year );

			// Arguments.
			$args = [
				'start' => $start,
				'end'   => $end,
				'type'  => $type,
				'index' => $i,
			];

			// Cache the cell.
			$cells[] = $args;

			// Set the current cell.
			$this->set_current_cell( $args );

			// Get day key.
			$dow     = ( $i % $this->day_count );
			$day_key = array_values( $days )[ $dow ];

			foreach ( $this->get_current_cell( 'items', [] ) as $item ) {
				if ( ! isset( $items[ $item->id ] ) ) {
					$items[ $item->id ] = [
						'event' => $item,
						'start' => $i + 2,
						'end'   => $i + 3,
						'days'  => [ $day_key ],
					];
				} else {
					$items[ $item->id ]['end']    = $i + 3;
					$items[ $item->id ]['days'][] = $day_key;
				}
			}
		}

		// Start an output buffer.
		ob_start();
		?>

        <div class="event-spans-group event-spans-group--<?php echo sanitize_html_class( $type ); ?>">
            <div class="row">
                <div class="column column-hour<?php echo $this->get_hour_class(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">

					<?php if ( $type === 'all_day' ) : ?>

						<?php esc_html_e( 'All Day', 'sugar-calendar-lite' ); ?>

					<?php elseif ( $type === 'multi_day' ) : ?>

						<?php esc_html_e( 'Multiple Days', 'sugar-calendar-lite' ); ?>

					<?php endif; ?>

                </div>

				<?php foreach ( $cells as $cell ) : ?>

					<?php $this->set_current_cell( $cell ); ?>

                    <div class="<?php echo $this->get_cell_classes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"></div>

				<?php endforeach; ?>
            </div>

            <div class="row event-spans">

				<?php foreach ( $items as $item ) : ?>

					<?php
					[
						'event' => $event,
						'start' => $start,
						'end'   => $end,
						'days'  => $days,
					] = $item;

					$start     = (int) $start;
					$end       = (int) $end;
					$style     = "grid-column: {$start} / {$end};";
					$data_days = array_map( 'sanitize_key', $days );
					$data_days = implode( ',', $data_days );
					$classes   = [ 'event-span' ];

					$is_hidden = array_diff( $days, $this->get_hidden_columns() ) === [];

					if ( $is_hidden ) {
						$classes[] = 'hidden';
					}

					$classes = implode( ' ', $classes );
					?>

                    <div class="<?php echo esc_attr( $classes ); ?>" style="<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" data-days="<?php echo esc_attr( $data_days ); ?>">
						<?php echo $this->get_event( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>

				<?php endforeach; ?>

            </div>
        </div>

		<?php
		// Return the output buffer.
		return ob_get_clean();
	}

	/**
	 * Start the week with a table row.
	 *
	 * @since 2.0.0
	 */
	protected function get_day_row_cell() {

		// Start an output buffer.
		ob_start();
		?>

		<?php echo $this->get_events_for_cell(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php

		// Return the output buffer.
		return ob_get_clean();
	}

	/**
	 * Start the week with a table row, and a th to show the time.
	 *
	 * @since 2.0.0
	 */
	protected function get_row_start() {

		// Get the start time.
		$start = $this->get_current_cell( 'start_dto' );

		// Hour for row.
		$hour = $start->format( 'H' );

		// No row classes.
		$classes = [
			'hour',
			"hour-{$hour}",
		];

		// Is this the current hour?
		if ( gmdate( 'H', $this->now ) === $hour ) {
			$classes[] = 'this-hour';
		}

		// Format based on clock type.
		$format = ( '12' === sugar_calendar_get_clock_type() )
			? 'g:i a'
			: 'H:i';

		// Start an output buffer.
		ob_start();
		?>

        <div class="row">
        <div class="column column-hour<?php echo $this->get_hour_class(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<?php echo $start->format( $format ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

		<?php

		// Return the output buffer.
		return ob_get_clean();
	}

	/**
	 * Start the week with a table row.
	 *
	 * @since 2.0.0
	 */
	protected function get_row_cell() {

		// Start an output buffer.
		ob_start();
		?>

        <div class="<?php echo $this->get_cell_classes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<?php echo $this->get_events_for_cell(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

		<?php

		// Return the output buffer.
		return ob_get_clean();
	}

	/**
	 * Set the items for all of the cells.
	 *
	 * @since 2.1.3
	 */
	protected function set_cells() {

		// All Day, Multi Day cells.
		for ( $i = 0; $i <= $this->day_count - 1; $i++ ) {

			// Setup the row and offset.
			$offset = ceil( DAY_IN_SECONDS * $i );
			$dt     = $this->grid_start + $offset;

			// Calc the day/month/year.
			$day   = gmdate( 'd', $dt );
			$month = gmdate( 'm', $dt );
			$year  = gmdate( 'Y', $dt );

			// Set the boundaries.
			$start = gmmktime( 0, 0, 0, $month, $day, $year );
			$end   = gmmktime( 23, 59, 59, $month, $day, $year );

			// Arguments.
			$args = [
				'start' => $start,
				'end'   => $end,
				'type'  => 'all_day',
				'index' => $i,
			];

			// Setup all-daycell boundaries.
			$this->set_cell_boundaries( $args );

			// Setup all-day cell items.
			$this->set_cell_items();

			// Set type to multi-day.
			$args['type'] = 'multi_day';

			// Setup multi-day cell boundaries.
			$this->set_cell_boundaries( $args );

			// Setup multi-day cell items.
			$this->set_cell_items();

			// Bump the day.
			++$day;
		}

		// Default column.
		$column = 0;

		// Loop through hours in days of week.
		for ( $i = 0; $i < ( $this->day_count * 24 ); $i++ ) {

			// Setup the row and offset.
			$row    = floor( $i / $this->day_count );
			$offset = ceil( DAY_IN_SECONDS * $column );
			$dt     = $this->grid_start + $offset;

			// Calc the day/month/year.
			$day   = gmdate( 'd', $dt );
			$month = gmdate( 'm', $dt );
			$year  = gmdate( 'Y', $dt );

			// Set the boundaries.
			$start = gmmktime( $row, 0, 0, $month, $day, $year );
			$end   = gmmktime( $row, 59, 59, $month, $day, $year );

			// Arguments.
			$args = [
				'start'  => $start,
				'end'    => $end,
				'row'    => $row,
				'index'  => $i,
				'offset' => $column,
			];

			// Setup cell boundaries.
			$this->set_cell_boundaries( $args );

			// Setup cell items.
			$this->set_cell_items();

			// Bump offset.
			++$column;

			// Close row.
			if ( $this->end_row() ) {
				$column = 0;
			}
		}

		// Cleanup.
		$this->current_cell = [];
	}

	/**
	 * Display a calendar by mode & range.
	 *
	 * @since 2.0.0
	 */
	protected function display_mode() {

		// All day events.
		echo $this->get_event_spans_row( 'all_day' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Multi day events.
		echo $this->get_event_spans_row( 'multi_day' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Default column.
		$column = 0;

		// Loop through hours in days of week.
		for ( $i = 0; $i < ( $this->day_count * 24 ); $i++ ) {

			// Setup the row and offset.
			$row    = floor( $i / $this->day_count );
			$offset = ceil( DAY_IN_SECONDS * $column );
			$dt     = $this->grid_start + $offset;

			// Calc the day/month/year.
			$day   = gmdate( 'd', $dt );
			$month = gmdate( 'm', $dt );
			$year  = gmdate( 'Y', $dt );

			// Set the boundaries.
			$start = gmmktime( $row, 0, 0, $month, $day, $year );
			$end   = gmmktime( $row, 59, 59, $month, $day, $year );

			// Arguments.
			$args = [
				'start'  => $start,
				'end'    => $end,
				'row'    => $row,
				'index'  => $i,
				'offset' => $column,
			];

			// Setup cell boundaries.
			$this->set_current_cell( $args );

			// New row.
			if ( $this->start_row() ) {
				echo $this->get_row_start(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			// Get this table cell.
			echo $this->get_row_cell(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// Bump offset.
			++$column;

			// Close row.
			if ( $this->end_row() ) {
				echo $this->get_row_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$column = 0;
			}
		}
	}

	/**
	 * End the week with a closed table row.
	 *
	 * @since 2.0.0
	 */
	protected function get_row_end() {

		// Start an output buffer.
		ob_start();
		?>

        </div>

		<?php

		// Return the output buffer.
		return ob_get_clean();
	}
}
