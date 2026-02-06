<?php
namespace Sugar_Calendar\Integrations\Elementor\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;

/**
 * Event Details Widget for Elementor.
 *
 * @since 3.10.0
 */
class EventDetails extends AbstractEventWidget {

	/**
	 * Get widget name.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget name.
	 */
	public function get_name() {

		return 'sugar-calendar-event-details';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget title.
	 */
	public function get_title() {

		return esc_html__( 'SC Event Details', 'sugar-calendar-lite' );
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {

		return 'eicon-text';
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 3.10.0
	 *
	 * @return array Widget keywords.
	 */
	public function get_keywords() {

		return [ 'event', 'details' ];
	}

	/**
	 * Register text editor widget controls.
	 *
	 * @since 3.10.0
	 */
	protected function register_controls() {

		$this->start_controls_section(
			'section_editor',
			[
				'label' => esc_html__( 'SC Event Details', 'sugar-calendar-lite' ),
			]
		);

		$this->add_control(
			'event-details',
			[
				'label' => esc_html__( 'Event Details', 'sugar-calendar-lite' ),
				'type'  => Controls_Manager::HIDDEN,
			]
		);

		$this->add_responsive_control(
			'text_columns',
			[
				'label'     => esc_html__( 'Columns', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::SELECT,
				'separator' => 'before',
				'options'   => [
					''   => esc_html__( 'Default', 'sugar-calendar-lite' ),
					'1'  => 1,
					'2'  => 2,
					'3'  => 3,
					'4'  => 4,
					'5'  => 5,
					'6'  => 6,
					'7'  => 7,
					'8'  => 8,
					'9'  => 9,
					'10' => 10,
				],
				'selectors' => [
					'{{WRAPPER}}' => 'columns: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'column_gap',
			[
				'label'      => esc_html__( 'Columns Gap', 'sugar-calendar-lite' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range'      => [
					'px'  => [
						'max' => 100,
					],
					'%'   => [
						'max'  => 10,
						'step' => 0.1,
					],
					'vw'  => [
						'max'  => 10,
						'step' => 0.1,
					],
					'em'  => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors'  => [
					'{{WRAPPER}}' => 'column-gap: {{SIZE}}{{UNIT}};',
				],
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'name'     => 'text_columns',
							'operator' => '>',
							'value'    => 1,
						],
						[
							'name'     => 'text_columns',
							'operator' => '===',
							'value'    => '',
						],
					],
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'SC Event Details', 'sugar-calendar-lite' ),
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
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justified', 'sugar-calendar-lite' ),
						'icon'  => 'eicon-text-align-justify',
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

		$this->add_responsive_control(
			'paragraph_spacing',
			[
				'label'      => esc_html__( 'Paragraph Spacing', 'sugar-calendar-lite' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'vh', 'custom' ],
				'range'      => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'min' => 0.1,
						'max' => 20,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} p' => 'margin-block-end: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'separator',
			[
				'type' => Controls_Manager::DIVIDER,
			]
		);

		$this->start_controls_tabs( 'link_colors' );

		$this->start_controls_tab(
			'colors_normal',
			[
				'label' => esc_html__( 'Normal', 'sugar-calendar-lite' ),
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

		$this->add_control(
			'link_color',
			[
				'label'     => esc_html__( 'Link Color', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'colors_hover',
			[
				'label' => esc_html__( 'Hover', 'sugar-calendar-lite' ),
			]
		);

		$this->add_control(
			'link_hover_color',
			[
				'label'     => esc_html__( 'Link Color', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} a:hover, {{WRAPPER}} a:focus' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'link_hover_color_transition_duration',
			[
				'label'      => esc_html__( 'Transition Duration', 'sugar-calendar-lite' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'default'    => [
					'unit' => 's',
				],
				'selectors'  => [
					'{{WRAPPER}} a' => 'transition-duration: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Render text editor widget output on the frontend.
	 *
	 * @since 3.10.0
	 */
	protected function render() {

		$event         = $this->get_event();
		$event_details = '';

		if ( ! empty( $event ) && ! empty( $event->content ) ) {
			$event_details = $this->parse_text_editor( $event->content );
		}

		if ( ! empty( $event_details ) ) {
			echo $event_details; // phpcs:ignore WordPress.Security.EscapeOutput

			return;
		}

		if ( $this->is_in_event_template_builder() || $this->is_in_loop_template() ) {

			esc_html_e(
				'This is a test event details.',
				'sugar-calendar-lite'
			);
		}
	}
}
