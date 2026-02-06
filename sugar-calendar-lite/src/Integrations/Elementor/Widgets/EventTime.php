<?php

namespace Sugar_Calendar\Integrations\Elementor\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Event Time Widget for Elementor.
 *
 * @since 3.10.0
 */
class EventTime extends BaseEventText {

	/**
	 * Register controls.
	 *
	 * @since 3.10.0
	 */
	protected function register_controls() {

		parent::register_controls();

		$this->update_control(
			'section_style',
			[
				'label' => esc_html__( 'SC Event Time', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {

		return 'eicon-clock-o';
	}

	/**
	 * Get widget name.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget name.
	 */
	public function get_name() {

		return 'sugar-calendar-event-time';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget title.
	 */
	public function get_title() {

		return esc_html__( 'SC Event Time', 'sugar-calendar-lite' );
	}

	/**
	 * Render text editor widget output on the frontend.
	 *
	 * @since 3.10.0
	 */
	protected function render() {

		$event_time = $this->get_event_time();

		if ( ! empty( $event_time ) ) {
			// We sanitized this upstream.
			echo $event_time; // phpcs:ignore WordPress.Security.EscapeOutput

			return;
		}

		if ( $this->is_in_event_template_builder() || $this->is_in_loop_template() ) {
			// Create a datetime today.
			$test_date = sugar_calendar_get_datetime_object();

			echo esc_html( $test_date->format( sc_get_time_format() ) );
		}
	}
}
