<?php

namespace Sugar_Calendar\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Sugar_Calendar\Integrations\Elementor\Widgets\AbstractEventWidget;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Event Location Widget for Elementor.
 *
 * @since 3.10.0
 */
class EventLocation extends AbstractEventWidget {

	/**
	 * Event location
	 *
	 * @since 3.10.0
	 *
	 * @var string
	 */
	private $event_location = null;

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

		$event = $this->get_event();

		if ( empty( $event ) || empty( $event->location ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	public function get_icon() {

		return 'eicon-map-pin';
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
				'label' => esc_html__( 'SC Event Location', 'sugar-calendar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label'     => esc_html__( 'Alignment', 'sugar-calendar' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'    => [
						'title' => esc_html__( 'Left', 'sugar-calendar' ),
						'icon' => 'eicon-text-align-left',
					],
					'center'  => [
						'title' => esc_html__( 'Center', 'sugar-calendar' ),
						'icon' => 'eicon-text-align-center',
					],
					'right'   => [
						'title' => esc_html__( 'Right', 'sugar-calendar' ),
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justified', 'sugar-calendar' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => 'text-align: {{VALUE}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'   => 'typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name'     => 'text_shadow',
				'selector' => '{{WRAPPER}}',
			]
		);

		$this->add_control(
			'text_color',
			[
				'label'     => esc_html__( 'Color', 'sugar-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}}, {{WRAPPER}} a' => 'color: {{VALUE}};',
				],
				'global'    => [
					'default' => Global_Colors::COLOR_TEXT,
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

		return 'sugar-calendar-event-location';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget title.
	 */
	public function get_title() {

		return esc_html__( 'SC Event Location', 'sugar-calendar' );
	}

	/**
	 * Render text editor widget output on the frontend.
	 *
	 * @since 3.10.0
	 */
	protected function render() {

		if ( $this->should_display_placeholder() ) {
			esc_html_e( '123 Test Location Address, Test City', 'sugar-calendar-lite' );
			return;
		}

		$event = $this->get_event();

		if ( empty( $event->location ) ) {
			return;
		}

		echo esc_html( $event->location );
	}
}
