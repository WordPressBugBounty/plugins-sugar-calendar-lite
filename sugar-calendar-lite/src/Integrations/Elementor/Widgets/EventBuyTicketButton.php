<?php

namespace Sugar_Calendar\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Sugar_Calendar\Integrations\Elementor\Widgets\AbstractEventWidget;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Border;
use Sugar_Calendar\AddOn\Ticketing\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Event Buy Ticket Button Widget for Elementor.
 *
 * @todo Refactor JS.
 *
 * @since 3.10.0
 */
class EventBuyTicketButton extends AbstractEventWidget {

	/**
	 * Whether to show the widget in the panel.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	public function show_in_panel() {

		if ( $this->is_in_loop_template() ) {
			return false;
		}

		if ( $this->is_in_event_template_builder() ) {
			return true;
		}

		$event = $this->get_event();

		return ! empty( $event );
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {

		return 'eicon-cart';
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
				'label' => esc_html__( 'Buy Ticket Button', 'sugar-calendar-lite' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$target_button_selector = '{{WRAPPER}} .sugar_calendar_event_ticketing_frontend_single_event__buy_now';

		$this->add_control(
			'css_fixes',
			[
				'type'      => Controls_Manager::HIDDEN,
				'default'   => 'yes',
				'selectors' => [
					$target_button_selector => 'display: flex; align-items: center; justify-content: center; gap: 10px;',
				],
			]
		);

		$default_args = [
			'section_condition'              => [],
			'alignment_default'              => '',
			'alignment_control_prefix_class' => 'elementor%s-align-',
			'content_alignment_default'      => '',
		];

		$args = wp_parse_args( [], $default_args );

		$start = is_rtl() ? 'right' : 'left';
		$end   = is_rtl() ? 'left' : 'right';

		$this->add_responsive_control(
			'content_align',
			[
				'label'     => esc_html__( 'Alignment', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'start'         => [
						'title' => esc_html__( 'Start', 'sugar-calendar-lite' ),
						'icon'  => "eicon-text-align-{$start}",
					],
					'center'        => [
						'title' => esc_html__( 'Center', 'sugar-calendar-lite' ),
						'icon'  => 'eicon-text-align-center',
					],
					'end'           => [
						'title' => esc_html__( 'End', 'sugar-calendar-lite' ),
						'icon'  => "eicon-text-align-{$end}",
					],
					'space-between' => [
						'title' => esc_html__( 'Space between', 'sugar-calendar-lite' ),
						'icon'  => 'eicon-text-align-justify',
					],
				],
				'default'   => $args['content_alignment_default'],
				'selectors' => [
					$target_button_selector => 'justify-content: {{VALUE}};',
				],
				'condition' => array_merge( $args['section_condition'], [ 'align' => 'justify' ] ),
			]
		);

		$this->add_control(
			'button_typography_font_size_default',
			[
				'type'      => Controls_Manager::HIDDEN,
				'default'   => '14',
				'selectors' => [
					$target_button_selector => 'font-size: {{VALUE}}px;',
				],
			]
		);

		$this->add_control(
			'button_typography_font_weight_default',
			[
				'type'      => Controls_Manager::HIDDEN,
				'default'   => '600',
				'selectors' => [
					$target_button_selector => 'font-weight: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'      => 'typography',
				'fields_options' => [
					'font_size' => [
						'default' => [
							'unit' => 'px',
							'size' => 14,
						],
					],
					'font_weight' => [
						'default' => '600',
					],
				],
				'selector'  => $target_button_selector,
				'condition' => $args['section_condition'],
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name'      => 'text_shadow',
				'selector'  => $target_button_selector,
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'button_cursor',
			[
				'type'      => Controls_Manager::HIDDEN,
				'default'   => 'pointer',
				'selectors' => [
					$target_button_selector => 'cursor: {{VALUE}};',
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->start_controls_tabs(
			'tabs_button_style',
			[
				'condition' => $args['section_condition'],
			]
		);

		$this->start_controls_tab(
			'tab_button_normal',
			[
				'label'     => esc_html__( 'Normal', 'sugar-calendar-lite' ),
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label'     => esc_html__( 'Text Color', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					$target_button_selector => 'fill: {{VALUE}}; color: {{VALUE}};',
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'           => 'background',
				'types'          => [ 'classic', 'gradient' ],
				'exclude'        => [ 'image' ],
				'selector'       => $target_button_selector,
				'fields_options' => [
					'background' => [
						'default' => 'classic',
					],
					'color' => [
						'default' => '#5685bd',
					],
				],
				'condition'      => $args['section_condition'],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'      => 'button_box_shadow',
				'selector'  => $target_button_selector,
				'condition' => $args['section_condition'],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			[
				'label'     => esc_html__( 'Hover', 'sugar-calendar-lite' ),
				'condition' => $args['section_condition'],
			]
		);

		$target_button_hover_selector     = '{{WRAPPER}} .sugar_calendar_event_ticketing_frontend_single_event__buy_now:hover, {{WRAPPER}} .sugar_calendar_event_ticketing_frontend_single_event__buy_now:focus';
		$target_button_hover_svg_selector = '{{WRAPPER}} .sugar_calendar_event_ticketing_frontend_single_event__buy_now:hover svg, {{WRAPPER}} .sugar_calendar_event_ticketing_frontend_single_event__buy_now:focus svg';

		$this->add_control(
			'hover_color',
			[
				'label'     => esc_html__( 'Text Color', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$target_button_hover_selector     => 'color: {{VALUE}};',
					$target_button_hover_svg_selector => 'fill: {{VALUE}};',
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'           => 'button_background_hover',
				'types'          => [ 'classic', 'gradient' ],
				'exclude'        => [ 'image' ],
				'selector'       => $target_button_hover_selector,
				'fields_options' => [
					'background' => [
						'default' => 'classic',
					],
				],
				'condition'      => $args['section_condition'],
			]
		);

		$this->add_control(
			'button_hover_border_color',
			[
				'label'     => esc_html__( 'Border Color', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$target_button_hover_selector => 'border-color: {{VALUE}};',
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'      => 'button_hover_box_shadow',
				'selector'  => $target_button_hover_selector,
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'button_hover_transition_duration',
			[
				'label'      => esc_html__( 'Transition Duration', 'sugar-calendar-lite' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'default'    => [
					'unit' => 's',
				],
				'selectors'  => [
					$target_button_selector => 'transition-duration: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'hover_animation',
			[
				'label'     => esc_html__( 'Hover Animation', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::HOVER_ANIMATION,
				'condition' => $args['section_condition'],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'           => 'border',
				'selector'       => $target_button_selector,
				'separator'      => 'before',
				'condition'      => $args['section_condition'],
				'fields_options' => [
					'border' => [
						'default' => 'none',
					],
				],
			]
		);

		$this->add_responsive_control(
			'border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'sugar-calendar-lite' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors'  => [
					$target_button_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'default'    => [
					'unit'     => 'px',
					'top'      => 4,
					'right'    => 4,
					'bottom'   => 4,
					'left'     => 4,
					'isLinked' => true,
				],
				'condition'  => $args['section_condition'],
			]
		);

		$this->add_responsive_control(
			'text_padding',
			[
				'label'      => esc_html__( 'Padding', 'sugar-calendar-lite' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors'  => [
					$target_button_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator'  => 'before',
				'condition'  => $args['section_condition'],
			]
		);

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

		return 'sugar-calendar-event-buy-ticket-button';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget title.
	 */
	public function get_title() {

		return esc_html__( 'SC Event Buy Ticket Button', 'sugar-calendar-lite' );
	}

	/**
	 * Render text editor widget output on the frontend.
	 *
	 * @since 3.10.0
	 */
	protected function render() {

		$event    = $this->get_event();
		$renderer = new Renderer( $event );

		if ( $this->is_in_editor_mode() ) {
			$renderer->should_enable_modal = false;

			$renderer->render_buy_now_button_placeholder();
		} elseif ( ! empty( $event ) ) {
			$renderer->maybe_render_buy_now_button();
		}
	}

	/**
	 * Render the button.
	 *
	 * @param string $buy_now_label    The "Buy Now" label.
	 * @param string $woocommerce_link The WooCommerce event ticket link.
	 *
	 * @since 3.10.0
	 */
	private function render_button( $buy_now_label, $woocommerce_link ) {

		$settings = $this->get_settings_for_display();

		$this->add_render_attribute( 'button', 'class', 'elementor-button' );
		$this->add_render_attribute( 'button', 'class', 'sugar-calendar__display__inline-flex' );
		$this->add_render_attribute( 'button', 'class', 'sugar-calendar__align-items__center' );
		$this->add_render_attribute( 'button', 'class', 'sugar-calendar__gap-10' );

		if ( ! empty( $settings['size'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-size-' . $settings['size'] );
		} else {
			$this->add_render_attribute( 'button', 'class', 'elementor-size-sm' ); // BC, to make sure the class is always present
		}

		if ( ! empty( $settings['hover_animation'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-animation-' . $settings['hover_animation'] );
		}

		$svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">' .
		'<path d="M9.09375 7.75C10 8.03125 10.625 8.9375 10.625 9.90625C10.625 11.125 9.65625 12.125 8.5 12.125V12.625C8.5 12.9062 8.25 13.125 8 13.125H7.5C7.21875 13.125 7 12.9062 7 12.625V12.125C6.46875 12.125 5.96875 11.9375 5.53125 11.6562C5.28125 11.4688 5.21875 11.0938 5.46875 10.875L5.84375 10.5312C6 10.375 6.25 10.3438 6.46875 10.4688C6.65625 10.5938 6.84375 10.625 7.0625 10.625H8.46875C8.84375 10.625 9.125 10.3125 9.125 9.90625C9.125 9.5625 8.9375 9.28125 8.65625 9.1875L6.40625 8.5C5.5 8.25 4.875 7.34375 4.875 6.375C4.875 5.15625 5.8125 4.15625 7 4.125V3.625C7 3.375 7.21875 3.125 7.5 3.125H8C8.28125 3.125 8.5 3.375 8.5 3.625V4.15625C9 4.15625 9.5 4.3125 9.9375 4.625C10.2188 4.8125 10.25 5.1875 10 5.40625L9.625 5.75C9.46875 5.90625 9.21875 5.9375 9.03125 5.8125C8.84375 5.6875 8.625 5.625 8.40625 5.625H7C6.65625 5.625 6.34375 5.96875 6.34375 6.375C6.34375 6.6875 6.5625 7 6.84375 7.09375L9.09375 7.75ZM7.75 0.375C12.0312 0.375 15.5 3.84375 15.5 8.125C15.5 12.4062 12.0312 15.875 7.75 15.875C3.46875 15.875 0 12.4062 0 8.125C0 3.84375 3.46875 0.375 7.75 0.375ZM7.75 14.375C11.1875 14.375 14 11.5938 14 8.125C14 4.6875 11.1875 1.875 7.75 1.875C4.28125 1.875 1.5 4.6875 1.5 8.125C1.5 11.5938 4.28125 14.375 7.75 14.375Z" fill="currentColor"/>' .
		'</svg>';

		if ( ! empty( $woocommerce_link ) ) {
			$this->add_render_attribute( 'button', 'href', $woocommerce_link );
			?>
			<a <?php $this->print_render_attribute_string( 'button' ); ?>>
				<?php
				echo wp_kses(
					sprintf(
						'%1$s %2$s',
						$svg,
						$buy_now_label
					),
					[
						'svg'  => [
							'width'   => [],
							'height'  => [],
							'viewBox' => [],
							'fill'    => [],
							'xmlns'   => [],
						],
						'path' => [
							'd'    => [],
							'fill' => [],
						],
					]
				);
				?>
			</a>
			<?php
		} else {
			if ( ! $this->is_in_editor_mode() ) {
				$this->add_render_attribute( 'button', 'data-toggle', 'modal' );
				$this->add_render_attribute( 'button', 'data-target', '#sc-event-ticketing-modal' );
			}

			$this->add_render_attribute( 'button', 'class', 'sugar_calendar_event_ticketing_frontend_single_event__buy_now' );
			?>
			<button <?php $this->print_render_attribute_string( 'button' ); ?>>
				<?php
				echo wp_kses(
					sprintf(
						'%1$s %2$s',
						$svg,
						$buy_now_label
					),
					[
						'svg'  => [
							'width'   => [],
							'height'  => [],
							'viewBox' => [],
							'fill'    => [],
							'xmlns'   => [],
						],
						'path' => [
							'd'    => [],
							'fill' => [],
						],
					]
				);
				?>
			</button>
			<?php
		}
	}

	/**
	 * Get widget style dependencies.
	 *
	 * @since 3.10.0
	 *
	 * @return array Widget style dependencies.
	 */
	public function get_style_depends() {

		return [ 'sc-event-ticketing-frontend-single-event' ];
	}
}
