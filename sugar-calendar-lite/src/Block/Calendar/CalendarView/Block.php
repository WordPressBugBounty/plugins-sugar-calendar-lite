<?php

namespace Sugar_Calendar\Block\Calendar\CalendarView;

use Sugar_Calendar\Block\Common\AbstractBlock;
use Sugar_Calendar\Block\Common\Template;

/**
 * Block Class.
 *
 * @since 3.0.0
 */
class Block extends AbstractBlock {

	/**
	 * Return the block HTML.
	 *
	 * @since 3.0.0
	 *
	 * @return false|string
	 */
	public function get_html() {

		ob_start();

		Template::load( 'base', $this );

		return ob_get_clean();
	}

	/**
	 * Get the heading.
	 *
	 * @since 3.0.0
	 * @since 3.4.0
	 *
	 * @param bool $use_abbreviated_month Whether to use abbreviated month or not.
	 *
	 * @return string
	 */
	public function get_heading( $use_abbreviated_month = false ) {

		return $this->get_view()->get_heading( $use_abbreviated_month );
	}

	/**
	 * Get the additional heading.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_additional_heading() {

		if ( $this->get_display_mode() === 'month' ) {
			return $this->get_year();
		}

		return '';
	}

	/**
	 * Get the classes for the block.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_classes() {

		return [
			'sugar-calendar-block',
			sprintf(
				'sugar-calendar-block__%s-view',
				$this->get_display_mode()
			),
		];
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
			'month' => esc_html__( 'Month', 'sugar-calendar-lite' ),
			'week'  => esc_html__( 'Week', 'sugar-calendar-lite' ),
			'day'   => esc_html__( 'Day', 'sugar-calendar-lite' ),
		];
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
			'sugar_calendar_block_calendar_view_block_display_mode_string',
			$string,
			$display_mode
		);
	}

	/**
	 * Get the current pagination text.
	 *
	 * @since 3.1.0
	 *
	 * @return string
	 */
	public function get_current_pagination_display() {

		switch ( $this->get_display_mode() ) {
			case 'day':
				$label = __( 'Today', 'sugar-calendar-lite' );
				break;

			case 'week':
				$label = __( 'This Week', 'sugar-calendar-lite' );
				break;

			default:
				$label = __( 'This Month', 'sugar-calendar-lite' );
				break;
		}

		return $label;
	}

	/**
	 * Get the next pagination text.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_next_pagination_display() {

		switch ( $this->get_display_mode() ) {
			case 'day':
				$label = __( 'Next Day', 'sugar-calendar-lite' );
				break;

			case 'week':
				$label = __( 'Next Week', 'sugar-calendar-lite' );
				break;

			default:
				$label = __( 'Next Month', 'sugar-calendar-lite' );
				break;
		}

		return $label;
	}

	/**
	 * Get the previous pagination text.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_previous_pagination_display() {

		switch ( $this->get_display_mode() ) {
			case 'day':
				$label = __( 'Previous Day', 'sugar-calendar-lite' );
				break;

			case 'week':
				$label = __( 'Previous Week', 'sugar-calendar-lite' );
				break;

			default:
				$label = __( 'Previous Month', 'sugar-calendar-lite' );
				break;
		}

		return $label;
	}

	/**
	 * Get appearance mode.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public function get_appearance_mode() {

		return $this->attributes['appearance'];
	}

	/**
	 * Always show the block left controls.
	 *
	 * @since 3.4.0
	 *
	 * @return boolean
	 */
	public function should_render_block_left_controls() {

		return true;
	}
}
