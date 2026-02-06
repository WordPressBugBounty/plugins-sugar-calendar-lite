<?php

namespace Sugar_Calendar\Integrations\Elementor\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Event Date Widget for Elementor.
 *
 * @since 3.10.0
 */
class EventDate extends BaseEventText {

	/**
	 * Get widget icon.
	 *
	 * @since 3.10.0
	 */
	public function get_icon() {

		return 'eicon-calendar';
	}

	/**
	 * Get widget name.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget name.
	 */
	public function get_name() {

		return 'sugar-calendar-event-date';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget title.
	 */
	public function get_title() {

		return esc_html__( 'SC Event Date', 'sugar-calendar-lite' );
	}

	/**
	 * Render text editor widget output on the frontend.
	 *
	 * @since 3.10.0
	 */
	protected function render() {

		$event_date = $this->get_event_date();

		if ( ! empty( $event_date ) ) {
			// We sanitized this upstream.
			echo $event_date; // phpcs:ignore WordPress.Security.EscapeOutput

			return;
		}

		if ( $this->is_in_event_template_builder() || $this->is_in_loop_template() ) {
			// Create a datetime today.
			$test_date = sugar_calendar_get_datetime_object();

			echo esc_html( $test_date->format( sc_get_date_format() ) );
		}
	}
}
