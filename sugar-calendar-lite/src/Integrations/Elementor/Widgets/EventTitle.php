<?php

namespace Sugar_Calendar\Integrations\Elementor\Widgets;

use Sugar_Calendar\Integrations\Elementor\Helpers;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Utils;
use Elementor\Plugin as ElementorPlugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Event Title Widget for Elementor.
 *
 * @since 3.10.0
 */
class EventTitle extends AbstractEventWidget {

	/**
	 * Get widget name.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget name.
	 */
	public function get_name() {

		return 'sugar-calendar-event-title';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget title.
	 */
	public function get_title() {

		return esc_html__( 'SC Event Title', 'sugar-calendar-lite' );
	}

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

		return [ 'sugar calendar', 'event', 'title' ];
	}

	/**
	 * Register event title widget controls.
	 *
	 * @since 3.10.0
	 */
	protected function register_controls() {

		$this->start_controls_section(
			'section_title',
			[
				'label' => esc_html__( 'SC Event Title', 'sugar-calendar-lite' ),
			]
		);

		$this->add_control(
			'title',
			[
				'label' => esc_html__( 'Title', 'sugar-calendar-lite' ),
				'type'  => Controls_Manager::HIDDEN,
			]
		);

		$this->add_control(
			'header_size',
			[
				'label'   => esc_html__( 'HTML Tag', 'sugar-calendar-lite' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
					'p'    => 'p',
				],
				'default' => 'h2',
			]
		);

		$hyper_link_type    = Controls_Manager::HIDDEN;
		$hyper_link_default = 'no';

		if ( $this->is_in_loop_template() ) {
			$hyper_link_type    = Controls_Manager::SWITCHER;
			$hyper_link_default = 'yes';
		}

		$this->add_control(
			'hyperlink_title',
			[
				'label'        => esc_html__( 'Link Title to Event', 'sugar-calendar-lite' ),
				'type'         => $hyper_link_type,
				'label_on'     => esc_html__( 'Yes', 'sugar-calendar-lite' ),
				'label_off'    => esc_html__( 'No', 'sugar-calendar-lite' ),
				'return_value' => $hyper_link_default,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_title_style',
			[
				'label' => esc_html__( 'SC Event Title', 'sugar-calendar-lite' ),
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
						'icon'  => 'eicon-text-align-left',
					],
					'center'  => [
						'title' => esc_html__( 'Center', 'sugar-calendar-lite' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'   => [
						'title' => esc_html__( 'Right', 'sugar-calendar-lite' ),
						'icon'  => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justified', 'sugar-calendar-lite' ),
						'icon'  => 'eicon-text-align-justify',
					],
				],
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}}' => 'text-align: {{VALUE}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'typography',
				'global'   => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} .elementor-heading-title',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			[
				'name'     => 'text_stroke',
				'selector' => '{{WRAPPER}} .elementor-heading-title',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name'     => 'text_shadow',
				'selector' => '{{WRAPPER}} .elementor-heading-title',
			]
		);

		$this->add_control(
			'separator',
			[
				'type' => Controls_Manager::DIVIDER,
			]
		);

		$this->add_control(
			'title_color',
			[
				'label'     => esc_html__( 'Text Color', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => [
					'default' => Global_Colors::COLOR_PRIMARY,
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-heading-title' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-heading-title a' => 'color: {{VALUE}};',
				],
			]
		);

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

		return parent::get_html_wrapper_class() . ' elementor-page-title elementor-widget-' . self::get_name();
	}

	/**
	 * Render event title output on the frontend.
	 *
	 * @since 3.10.0
	 */
	protected function render() {

		$settings = $this->get_settings_for_display();

		$this->add_render_attribute( 'title', 'class', 'elementor-heading-title' );

		$title = wp_kses_post( $this->get_event_title() );

		if ( empty( $title ) && $this->should_display_placeholder() ) {
			$title = esc_html__( 'Event Title', 'sugar-calendar-lite' );
		}

		if ( ! empty( $settings['hyperlink_title'] ) && $settings['hyperlink_title'] === 'yes' ) {
			$url = '#';

			if ( ! empty( $this->get_event() ) && ! empty( $this->get_event()->object_id ) ) {
				$url = get_permalink( $this->get_event()->object_id );
			}

			$title = sprintf( '<a href="%1$s">%2$s</a>', $url, $title );
		}

		$title_html = sprintf( '<%1$s %2$s>%3$s</%1$s>', Utils::validate_html_tag( $settings['header_size'] ), $this->get_render_attribute_string( 'title' ), $title );

		// PHPCS - the variable $title_html holds safe data.
		echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

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
}
