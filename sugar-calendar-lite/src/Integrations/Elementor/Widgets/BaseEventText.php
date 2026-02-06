<?php

namespace Sugar_Calendar\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Sugar_Calendar\Helpers as SugarCalendarBaseHelpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base Event Text widget for Elementor.
 *
 * @since 3.10.0
 */
abstract class BaseEventText extends AbstractEventWidget {

	/**
	 * Event date.
	 *
	 * @since 3.10.0
	 *
	 * @var string
	 */
	private $event_date = null;

	/**
	 * Event time.
	 *
	 * @since 3.10.0
	 *
	 * @var string
	 */
	private $event_time = null;

	/**
	 * Get widget icon.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {

		return 'eicon-post-title';
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 3.10.0
	 *
	 * @return string[] Widget keywords.
	 */
	public function get_keywords() {

		return [ 'sugar calendar', 'event', 'date', 'time' ];
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
				'label' => esc_html__( 'Event Date', 'sugar-calendar-lite' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label'     => esc_html__( 'Alignment', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'    => [
						'title' => esc_html__( 'Left', 'sugar-calendar-lite' ),
						'icon' => 'eicon-text-align-left',
					],
					'center'  => [
						'title' => esc_html__( 'Center', 'sugar-calendar-lite' ),
						'icon' => 'eicon-text-align-center',
					],
					'right'   => [
						'title' => esc_html__( 'Right', 'sugar-calendar-lite' ),
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justified', 'sugar-calendar-lite' ),
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
				'label'     => esc_html__( 'Text Color', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}}' => 'color: {{VALUE}};',
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
	 * Get HTML wrapper class.
	 *
	 * @since 3.10.0
	 *
	 * @return string HTML wrapper class.
	 */
	protected function get_html_wrapper_class() {

		return parent::get_html_wrapper_class() . ' elementor-page-title elementor-widget-' . $this->get_name();
	}

	/**
	 * Render plain content.
	 *
	 * @since 3.10.0
	 */
	public function render_plain_content() {}

	/**
	 * Get widget group name.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget group name.
	 */
	public function get_group_name() {

		return 'sugar-calendar';
	}

	/**
	 * Get event date.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	protected function get_event_date() {

		if ( ! is_null( $this->event_date ) ) {
			return $this->event_date;
		}

		$this->event_date = false;
		$event            = $this->get_event();

		if ( empty( $event ) ) {
			return '';
		}

		$this->event_date = wp_kses(
			SugarCalendarBaseHelpers::get_event_datetime( $event ),
			[
				'time' => [
					'datetime'      => true,
					'title'         => true,
					'data-timezone' => true,
				],
			]
		);

		return $this->event_date;
	}

	/**
	 * Get event time.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	protected function get_event_time() {

		if ( ! is_null( $this->event_time ) ) {
			return $this->event_time;
		}

		$this->event_time = false;
		$event            = $this->get_event();

		if ( empty( $event ) ) {
			return '';
		}

		$this->event_time = wp_kses(
			SugarCalendarBaseHelpers::get_event_datetime( $event, 'time' ),
			[
				'time' => [
					'datetime'      => true,
					'title'         => true,
					'data-timezone' => true,
				],
			]
		);

		return $this->event_time;
	}
}
