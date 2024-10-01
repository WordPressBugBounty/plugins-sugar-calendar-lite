<?php

namespace Sugar_Calendar\Admin\Events\Tables;

/**
 * Grid event view.
 *
 * @since 3.0.0
 */
class Grid extends Base {

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
		$classes = implode( ' ', $this->get_table_classes() );
		?>

        <div class="<?php echo esc_attr( $classes ); ?>">
			<?php $this->print_column_headers(); ?>

			<?php $this->display_mode(); ?>

			<?php $this->print_column_headers( false ); ?>
        </div>

		<?php

		// Bottom.
		$this->display_tablenav( 'bottom' );

		// End and flush the buffer.
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Prints column headers, accounting for hidden and sortable columns.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $with_id Whether to set the ID attribute or not.
	 */
	public function print_column_headers( $with_id = true ) {

		[ $columns, $hidden ] = $this->get_column_info();
		?>

        <div class="row">

			<?php
			foreach ( $columns as $column_key => $column_display_name ) {
				$class = [ 'header', 'column', "column-{$column_key}" ];

				if ( in_array( $column_key, $hidden, true ) ) {
					$class[] = 'hidden';
				}

				$id = $with_id ? "id='$column_key'" : '';

				if ( ! empty( $class ) ) {
					$class = "class='" . implode( ' ', $class ) . "'";
				}

				echo "<div $id $class>$column_display_name</div>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

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
	public function output_grid_layout() {

		?>

        <style id="sugar-calendar-table-grid-column-layout">
            .sugar-calendar-table-events {
                --grid-template-columns: <?php echo $this->get_grid_column_layout(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
            }
        </style>

		<?php
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
			'sugar-calendar-table',
			'sugar-calendar-table-events',
			'sugar-calendar-table-events--' . $this->get_mode(),
			$this->get_mode(),
			$this->get_status(),
			$this->_args['plural'],
		];
	}

	/**
	 * Get grid layout rules.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	private function get_grid_column_layout() {

		[ $columns, $hidden ] = $this->get_column_info();

		$template = array_map(
			function ( $column ) use ( $columns, $hidden ) {

				$column    = sanitize_key( $column );
				$max       = $column === array_key_first( $columns ) ? '120px' : '1fr';
				$is_hidden = in_array( $column, $hidden, true );
				$size      = $is_hidden ? '0fr' : "minmax(0, {$max})";

				return "[{$column}] {$size}";
			},
			array_keys( $columns )
		);

		$template = implode( ' ', $template );

		return $template;
	}

	/**
	 * Get classes for event in day.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 * @param int    $cell  Cell index.
	 */
	protected function get_event_classes( $event = 0, $cell = 0 ) {

		$classes = parent::get_event_classes( $event, $cell );
		$classes = "{$classes} sugar-calendar-event-entry";

		return $classes;
	}
}
