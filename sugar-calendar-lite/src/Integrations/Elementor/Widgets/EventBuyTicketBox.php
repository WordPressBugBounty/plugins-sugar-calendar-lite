<?php

namespace Sugar_Calendar\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Sugar_Calendar\Integrations\Elementor\Widgets\AbstractEventWidget;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Border;
use Sugar_Calendar\AddOn\Ticketing\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Event Buy Ticket Box Widget for Elementor.
 *
 * @since 3.10.0
 */
class EventBuyTicketBox extends AbstractEventWidget {

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

		return 'eicon-product-description';
	}

	/**
	 * Get widget name.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget name.
	 */
	public function get_name() {

		return 'sugar-calendar-event-buy-ticket-box';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget title.
	 */
	public function get_title() {

		return esc_html__( 'SC Event Buy Ticket Box', 'sugar-calendar-lite' );
	}

	/**
	 * Register text editor widget controls.
	 *
	 * @since 3.10.0
	 */
	protected function register_controls() {

		$this->title_controls();
		$this->text_controls();
		$this->checkout_button_controls();
	}

	/**
	 * Add title controls.
	 *
	 * @since 3.10.0
	 */
	private function title_controls() {

		$this->start_controls_section(
			'section_title_style',
			[
				'label' => esc_html__( 'Title', 'sugar-calendar-lite' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'event_tickets_title_typography',
				'label'    => __( 'Title Typography', 'sugar-calendar-lite' ),
				'selector' => '{{WRAPPER}} #sc-event-ticketing-wrap .sc-et-card-header',
				'global'   => [
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				],
			]
		);

		$this->add_control(
			'event_tickets_title_color',
			[
				'default'   => 'rgba(0,0,0,.85)',
				'label'     => esc_html__( 'Title Color', 'sugar-calendar-lite' ),
				'selectors' => [
					'{{WRAPPER}} #sc-event-ticketing-wrap .sc-et-card-header' => 'color: {{VALUE}}',
				],
				'type'      => Controls_Manager::COLOR,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_separator_style',
			[
				'label' => esc_html__( 'Separator', 'sugar-calendar-lite' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'event_tickets_title_separator_color',
			[
				'label'     => esc_html__( 'Color', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} #sc-event-ticketing-wrap .sc-et-card-header' => 'border-bottom-color: {{VALUE}}',
				],
				'default'   => 'rgba(0,0,0,.1)',
			]
		);

		$this->add_control(
			'event_tickets_title_separator_size',
			[
				'label'     => esc_html__( 'Size (px)', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => [
					'{{WRAPPER}} #sc-event-ticketing-wrap .sc-et-card-header' => 'border-bottom-width: {{SIZE}}px;border-bottom-style: solid;',
				],
				'default'   => [
					'size' => 1,
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Add text controls.
	 *
	 * @since 3.10.0
	 */
	private function text_controls() {

		$this->start_controls_section(
			'section_text_style',
			[
				'label' => esc_html__( 'Text', 'sugar-calendar-lite' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'event_tickets_text_color',
			[
				'default'   => 'rgba(0,0,0,.4)',
				'label'     => esc_html__( 'Text Color', 'sugar-calendar-lite' ),
				'selectors' => [
					'{{WRAPPER}} #sc-event-ticketing-wrap #sc-event-ticketing-price-wrap .sc-event-ticketing-price' => 'color: {{VALUE}}',
				],
				'type'      => Controls_Manager::COLOR,
			]
		);

		$this->add_control(
			'event_tickets_text_typography_font_size_default',
			[
				'type'      => Controls_Manager::HIDDEN,
				'default'   => '14',
				'selectors' => [
					'{{WRAPPER}} #sc-event-ticketing-wrap #sc-event-ticketing-price-wrap .sc-event-ticketing-price' => 'font-size: {{VALUE}}px;',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'           => 'event_tickets_text_typography',
				'label'          => __( 'Text Typography', 'sugar-calendar-lite' ),
				'selector'       => '{{WRAPPER}} #sc-event-ticketing-wrap #sc-event-ticketing-price-wrap .sc-event-ticketing-price',
				'fields_options' => [
					'font_size' => [
						'default' => [
							'unit' => 'px',
							'size' => 14,
						],
					],
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Add checkout button controls.
	 *
	 * @since 3.10.0
	 */
	private function checkout_button_controls() {

		$this->start_controls_section(
			'section_checkout_button_style',
			[
				'label' => esc_html__( 'Checkout Button', 'sugar-calendar-lite' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'event_checkout_btn_text_color',
			[
				'default'   => '#FFF',
				'label'     => esc_html__( 'Text Color', 'sugar-calendar-lite' ),
				'selectors' => [
					'{{WRAPPER}} #sc-event-ticketing-wrap #sc-event-ticketing-price-wrap .sc-event-ticketing-price-wrap__add-to-cart-section .sc-event-ticketing-price-wrap__add-to-cart-section__btn-container #sc-event-ticketing-buy-button' => 'color: {{VALUE}}',
					'#sc-event-ticketing-modal #sc-event-ticketing-checkout .modal-footer #sc-event-ticketing-purchase' => 'color: {{VALUE}}',
				],
				'type'      => Controls_Manager::COLOR,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'           => 'event_checkout_btn_text_typography',
				'label'          => __( 'Text Typography', 'sugar-calendar-lite' ),
				'selector'       => '{{WRAPPER}} #sc-event-ticketing-wrap #sc-event-ticketing-price-wrap .sc-event-ticketing-price-wrap__add-to-cart-section .sc-event-ticketing-price-wrap__add-to-cart-section__btn-container #sc-event-ticketing-buy-button, #sc-event-ticketing-modal #sc-event-ticketing-checkout .modal-footer #sc-event-ticketing-purchase',
				'fields_options' => [
					'font_size'  => [
						'default' => [
							'unit' => 'px',
							'size' => 16,
						],
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'           => 'event_checkout_btn_background',
				'types'          => [ 'classic', 'gradient' ],
				'exclude'        => [ 'image' ],
				'selector'       => '{{WRAPPER}} #sc-event-ticketing-wrap #sc-event-ticketing-price-wrap .sc-event-ticketing-price-wrap__add-to-cart-section .sc-event-ticketing-price-wrap__add-to-cart-section__btn-container #sc-event-ticketing-buy-button, #sc-event-ticketing-modal #sc-event-ticketing-checkout .modal-footer #sc-event-ticketing-purchase',
				'fields_options' => [
					'background' => [
						'default' => 'classic',
					],
					'color'      => [
						'default' => '#5685bd',
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'event_checkout_btn_box_shadow',
				'selector' => '{{WRAPPER}} #sc-event-ticketing-wrap #sc-event-ticketing-price-wrap .sc-event-ticketing-price-wrap__add-to-cart-section .sc-event-ticketing-price-wrap__add-to-cart-section__btn-container #sc-event-ticketing-buy-button, #sc-event-ticketing-modal #sc-event-ticketing-checkout .modal-footer #sc-event-ticketing-purchase, #sc-event-ticketing-modal #sc-event-ticketing-checkout .modal-footer #sc-event-ticketing-purchase, #sc-event-ticketing-modal #sc-event-ticketing-checkout .modal-footer #sc-event-ticketing-purchase',
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'      => 'border',
				'selector'  => '{{WRAPPER}} #sc-event-ticketing-wrap #sc-event-ticketing-price-wrap .sc-event-ticketing-price-wrap__add-to-cart-section .sc-event-ticketing-price-wrap__add-to-cart-section__btn-container #sc-event-ticketing-buy-button, #sc-event-ticketing-modal #sc-event-ticketing-checkout .modal-footer #sc-event-ticketing-purchase',
				'separator' => 'before',
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
					'{{WRAPPER}} #sc-event-ticketing-wrap #sc-event-ticketing-price-wrap .sc-event-ticketing-price-wrap__add-to-cart-section .sc-event-ticketing-price-wrap__add-to-cart-section__btn-container #sc-event-ticketing-buy-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'#sc-event-ticketing-modal #sc-event-ticketing-checkout .modal-footer #sc-event-ticketing-purchase' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'default'    => [
					'unit'     => 'px',
					'top'      => 4,
					'right'    => 4,
					'bottom'   => 4,
					'left'     => 4,
					'isLinked' => true,
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render text editor widget output on the frontend.
	 *
	 * @since 3.10.0
	 */
	protected function render() {

		// @todo Handle multiple tickets.
		$event    = $this->get_event();
		$renderer = new Renderer( $event );

		if ( $this->should_display_placeholder() ) {
			$renderer->render_ticket_box_placeholder();
		} else {

			if ( $this->is_in_editor_mode() ) {
				$renderer->should_enable_modal = false;
			}

			$renderer->maybe_render_ticket_box();
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
