<?php

namespace Sugar_Calendar\Admin\Events\Tables;

use Sugar_Calendar\Plugin;

/**
 * Calendar Month List Table.
 *
 * This list table is responsible for showing events in a traditional table,
 * even though it extends the `WP_List_Table` class. Tables & lists & tables.
 *
 * @since 2.0.0
 */
class Month extends Grid {

	/**
	 * The mode of the current view.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $mode = 'month';

	/**
	 * The main constructor method.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Constructor arguments.
	 */
	public function __construct( $args = [] ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		parent::__construct( $args );

		// View start.
		$start_of_month        = strtotime( "{$this->year}-{$this->month}-01 00:00:00" );
		$starting_day          = reset( $this->week_days );
		$starting_day_of_month = strtolower( gmdate( 'l', $start_of_month ) );
		$view_start_format     = "Last {$starting_day}";

		// If the first day of the month falls exactly on the starting day,
		// pick it as the grid start.
		if ( $starting_day_of_month === $starting_day ) {
			$view_start_format = "{$starting_day} this week";
		}

		$view_start = strtotime( $view_start_format, $start_of_month );

		// View end.
		$view_end = strtotime( '+6 weeks', $view_start );

		// Grid boundaries.
		$this->grid_start = $view_start;
		$this->grid_end   = $view_end;

		// Set the view.
		$view_start = gmdate( 'Y-m-d H:i:s', $view_start );
		$view_end   = gmdate( 'Y-m-d H:i:s', $view_end );

		$this->set_view( $view_start, $view_end );

		// Filter the default hidden columns.
		add_filter( 'default_hidden_columns', [ $this, 'hide_week_column' ], 10, 2 );
	}

	/**
	 * Maybe hide the week column in Month view.
	 *
	 * It's hidden by default in Month view, but could be made optionally
	 * visible at a later date.
	 *
	 * @since 2.0.15
	 *
	 * @param array  $columns The columns that are hidden by default.
	 * @param object $screen  The current screen object.
	 *
	 * @return array
	 */
	public function hide_week_column( $columns = [], $screen = false ) {

		// Bail if not on the correct screen.
		if ( ! Plugin::instance()->get_admin()->is_page( 'events' ) ) {
			return $columns;
		}

		// Add Week column to default hidden columns.
		array_push( $columns, 'week' );

		// Return merged columns.
		return $columns;
	}

	/**
	 * Setup the list-table columns.
	 *
	 * Overrides base class to add the hidden "week" column.
	 *
	 * @since 2.0.0
	 *
	 * @return array An associative array containing column information
	 */
	public function get_columns() {

		static $retval = null;

		// Calculate if not calculated already.
		if ( $retval === null ) {

			// Setup return value.
			$retval = [
				'week' => esc_html__( 'Week', 'sugar-calendar-lite' ),
			];

			// PHP day => day ID.
			$days = $this->get_week_days();

			// Loop through days and add them to the return value.
			foreach ( $days as $key => $day ) {
				$retval[ $day ] = $GLOBALS['wp_locale']->get_weekday( $key );
			}
		}

		// Return columns.
		return $retval;
	}

	/**
	 * Start the week with a table row, and a th to show the time.
	 *
	 * @since 2.0.0
	 */
	protected function get_row_start() {

		// Get the start.
		$start = $this->get_current_cell( 'start_dto' );

		// Get cells.
		$day   = $start->format( 'd' );
		$month = $start->format( 'm' );
		$year  = $start->format( 'Y' );

		// Week for row.
		$week = $this->get_week_for_timestamp( $start->format( 'U' ) );

		// Calculate link to week view.
		$link_to_day = add_query_arg(
			[
				'mode' => 'week',
				'cy'   => $year,
				'cm'   => $month,
				'cd'   => $day,
			],
			$this->get_base_url()
		);

		// Week column.
		$columns = $this->get_hidden_columns();
		$hidden  = in_array( 'week', $columns, true )
			? 'hidden'
			: '';

		// Start an output buffer.
		ob_start(); ?>

        <div class="row">
        <div class="week column column-week <?php echo esc_attr( $hidden ); ?>">
            <h4>
                <a href="<?php echo esc_url( $link_to_day ); ?>" class="week-number"><?php echo esc_html( $week ); ?></a>
            </h4>
        </div>

		<?php

		// Return the output buffer.
		return ob_get_clean();
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

	/**
	 * Start the week with a table row.
	 *
	 * @since 2.0.0
	 */
	protected function get_row_cell() {

		// Get the start.
		$start = $this->get_current_cell( 'start_dto' );

		// Calculate the day of the month.
		$day = $start->format( 'd' );

		// Arguments.
		$args = [
			'mode' => 'day',
			'cy'   => $this->year,
			'cm'   => $this->month,
			'cd'   => $day,
		];

		// Calculate link to day view.
		$link_to_day = add_query_arg( $args, $this->get_base_url() );

		$day_format = intval( $day ) === 1 ? 'M j' : 'j';

		// Start an output buffer.
		ob_start();

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

        <div class="<?php echo $this->get_cell_classes(); ?>">
            <h4>
                <a href="<?php echo esc_url( $link_to_day ); ?>" class="day-number"><?php echo $start->format( $day_format ); ?></a>
            </h4>

			<?php echo $this->get_events_for_cell(); ?>
        </div>

		<?php
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		// Return the output buffer.
		return ob_get_clean();
	}

	/**
	 * Display a calendar by month and year.
	 *
	 * @since 2.0.0
	 */
	protected function display_mode() {

		// Loop through days of the month.
		foreach ( $this->cells as $cell ) {

			// Set the current cell.
			$this->current_cell = $cell;

			// Maybe start a new row.
			if ( $this->start_row() ) {
				echo $this->get_row_start(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			echo $this->get_row_cell(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// Maybe end the row.
			if ( $this->end_row() ) {
				echo $this->get_row_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
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
				'small'  => '1 month',
				'large'  => '1 year',
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
}
