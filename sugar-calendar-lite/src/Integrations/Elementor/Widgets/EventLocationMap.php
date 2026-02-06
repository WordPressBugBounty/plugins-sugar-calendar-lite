<?php

namespace Sugar_Calendar\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Sugar_Calendar\Integrations\Elementor\Widgets\AbstractEventWidget;
use Sugar_Calendar\Helpers as HelpersCommon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Event Location Map Widget for Elementor.
 *
 * @since 3.10.0
 */
class EventLocationMap extends AbstractEventWidget {

	/**
	 * Event location coordinates.
	 *
	 * @since 3.10.0
	 *
	 * @var string
	 */
	private $event_coordinates = null;

	/**
	 * Whether to show the widget in the panel.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	public function show_in_panel() {

		if (
			$this->should_display_location_widget() &&
			$this->should_display_placeholder()
		) {
			return true;
		}

		return ! empty( $this->get_event_location() );
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	public function get_icon() {

		return 'eicon-google-maps';
	}

	/**
	 * Register text editor widget controls.
	 *
	 * @since 3.10.0
	 */
	protected function register_controls() {

		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'SC Event Venue Map', 'sugar-calendar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'event_venue_map_height',
			[
				'label'      => esc_html__( 'Height', 'sugar-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'vh' ],
				'range'      => [
					'px' => [
						'min' => 200,
						'max' => 1440,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .sc_map_canvas' => 'height: {{SIZE}}{{UNIT}};',
				],
				'default'    => [
					'unit' => 'px',
					'size' => 400,
				],
			]
		);

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Get widget name.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget name.
	 */
	public function get_name() {

		return 'sugar-calendar-event-location-map';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget title.
	 */
	public function get_title() {

		return esc_html__( 'SC Event Location Map', 'sugar-calendar' );
	}

	/**
	 * Get script dependencies.
	 *
	 * @since 3.10.0
	 *
	 * @return array Script handles.
	 */
	public function get_script_depends() {

		return [ 'sugar-calendar-integrations-elementor-venue-map' ];
	}

	/**
	 * Render text editor widget output on the frontend.
	 *
	 * @since 3.10.0
	 */
	protected function render() {

		$this->render_map();
	}

	/**
	 * Render the map.
	 *
	 * @since 3.10.0
	 */
	private function render_map() {

		$event_location = $this->get_event_location();
		$coordinates    = $this->get_event_coordinates();

		$event_venue_map_height      = $this->get_settings( 'event_venue_map_height' );
		$event_venue_map_height_unit = ! empty( $event_venue_map_height['unit'] ) ? $event_venue_map_height['unit'] : 'px';
		$event_venue_map_height_size = ! empty( $event_venue_map_height['size'] ) ? $event_venue_map_height['size'] : 400;

		if (
			$this->should_display_placeholder() &&
			empty( $coordinates ) &&
			empty( $event_location )
		) {
			// Default to New York for placeholder.
			$coordinates = [
				'lat' => 40.6972846,
				'lng' => -74.14431,
			];
		}

		if (
			is_array( $coordinates )
			&&
			isset( $coordinates['lat'] )
			&&
			isset( $coordinates['lng'] )
		) {
			printf(
				'<div data-lat="%1$s" data-lng="%2$s" data-height="%3$s" class="sc_map_canvas"></div>',
				esc_attr( $coordinates['lat'] ),
				esc_attr( $coordinates['lng'] ),
				esc_attr( $event_venue_map_height_size . $event_venue_map_height_unit )
			);
		} elseif ( ! empty( $event_location ) ) {
			printf(
				'<div data-loc="%1$s" data-nonce="%2$s" data-height="%3$s" class="sc_map_canvas"></div>',
				esc_attr( $event_location ),
				esc_attr( wp_create_nonce( 'sugar_calendar_venue_save_coordinates' ) ),
				esc_attr( $event_venue_map_height_size . $event_venue_map_height_unit )
			);
		}
	}

	/**
	 * Get the event location.
	 *
	 * @since 3.10.0
	 *
	 * @return string|false
	 */
	private function get_event_location() {

		$event = $this->get_event();

		if ( empty( $event ) || empty( $event->location ) ) {
			return false;
		}

		return $event->location;
	}

	/**
	 * Get the event venue coordinates.
	 *
	 * @since 3.10.0
	 *
	 * @return array|false
	 */
	private function get_event_coordinates() {

		if ( ! is_null( $this->event_coordinates ) ) {
			return $this->event_coordinates;
		}

		$this->event_coordinates = false;
		$event_location          = $this->get_event_location();

		if ( empty( $event_location ) ) {
			return false;
		}

		$coordinates = HelpersCommon::get_coordinates_from_address( $event_location );

		if ( empty( $coordinates ) ) {
			return false;
		}

		$this->event_coordinates = $coordinates;

		return $this->event_coordinates;
	}
}
