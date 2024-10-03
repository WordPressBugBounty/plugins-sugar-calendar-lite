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
	 *
	 * @return string
	 */
	public function get_heading() {

		return $this->get_view()->get_heading();
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
			'month' => esc_html__( 'Month', 'sugar-calendar' ),
			'week'  => esc_html__( 'Week', 'sugar-calendar' ),
			'day'   => esc_html__( 'Day', 'sugar-calendar' ),
		];
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
				$label = __( 'Today', 'sugar-calendar' );
				break;

			case 'week':
				$label = __( 'This Week', 'sugar-calendar' );
				break;

			default:
				$label = __( 'This Month', 'sugar-calendar' );
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
}
