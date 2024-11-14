<?php

namespace Sugar_Calendar\Integrations\Elementor;

use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Widget_Base;
use Sugar_Calendar\Block\Calendar\CalendarView;

/**
 * Sugar Calendar widget for Elementor.
 *
 * @since 3.2.0
 */
class CalendarWidget extends Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_name() {

		return 'sugar-calendar-events-calendar';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_title() {

		return esc_html__( 'Events Calendar', 'sugar-calendar' );
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_icon() {

		return 'icon-sugar-calendar';
	}

	/**
	 * Get widget categories.
	 *
	 * @since 3.2.0
	 *
	 * @return string[]
	 */
	public function get_categories() {

		return [ 'basic' ];
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 3.2.0
	 *
	 * @return string[]
	 */
	public function get_keywords() {

		return [ 'calendar', 'events', 'sugar calendar' ];
	}

	/**
	 * Get widget style dependencies.
	 *
	 * @since 3.2.0
	 *
	 * @return string[]
	 */
	public function get_style_depends() {

		return [ 'sugar-calendar-block-style' ];
	}

	/**
	 * Get widget script dependencies.
	 *
	 * @since 3.2.0
	 *
	 * @return string[]
	 */
	public function get_script_depends() {

		if ( Plugin::instance()->preview->is_preview_mode() ) {
			return [];
		}

		return [ 'sugar-calendar-js' ];
	}

	/**
	 * Register widget controls.
	 *
	 * @since 3.2.0
	 */
	protected function register_controls() {

		$this->start_controls_section(
			'section_sugar_calendar_events_calendar',
			[
				'label' => esc_html__( 'Events Calendar', 'sugar-calendar' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'calendars',
			[
				'default'  => [],
				'label'    => esc_html__( 'Calendars', 'sugar-calendar' ),
				'multiple' => true,
				'options'  => get_terms(
					[
						'hide_empty' => false,
						'taxonomy'   => 'sc_event_category',
						'fields'     => 'id=>name',
					]
				),
				'type'     => Controls_Manager::SELECT2,
			]
		);

		$this->add_control(
			'display_mode',
			[
				'default'    => 'month',
				'label'      => esc_html__( 'Display', 'sugar-calendar' ),
				'options'    => [
					'month' => esc_html__( 'Month', 'sugar-calendar' ),
					'week'  => esc_html__( 'Week', 'sugar-calendar' ),
					'day'   => esc_html__( 'Day', 'sugar-calendar' ),
				],
				'show_label' => true,
				'type'       => Controls_Manager::SELECT,
			]
		);

		$this->add_control(
			'appearance',
			[
				'default'    => 'light',
				'label'      => esc_html__( 'Appearance', 'sugar-calendar' ),
				'options'    => [
					'light' => esc_html__( 'Light', 'sugar-calendar' ),
					'dark'  => esc_html__( 'Dark', 'sugar-calendar' ),
				],
				'show_label' => true,
				'type'       => Controls_Manager::SELECT,
			]
		);

		$this->add_control(
			'allow_users_to_change_display',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Allow Users to Change Display', 'sugar-calendar' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'accent_color',
			[
				'alpha'   => false,
				'default' => '#5685BD',
				'label'   => esc_html__( 'Accent Color', 'sugar-calendar' ),
				'type'    => Controls_Manager::COLOR,
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output.
	 *
	 * @since 3.2.0
	 */
	protected function render() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$display                       = $this->get_settings_for_display( 'display_mode' );
		$allow_users_to_change_display = $this->get_settings_for_display( 'allow_users_to_change_display' );
		$accent_color                  = $this->get_settings_for_display( 'accent_color' );
		$calendars                     = $this->get_settings_for_display( 'calendars' );
		$appearance                    = $this->get_settings_for_display( 'appearance' );

		$attr = [
			'blockId'                => $this->get_id(),
			'display'                => ! empty( $display ) ? $display : 'month',
			'accentColor'            => ! empty( $accent_color ) ? $accent_color : '#5685BD',
			'calendars'              => ! empty( $calendars ) ? array_map( 'absint', $calendars ) : [],
			'groupEventsByWeek'      => true,
			'showBlockHeader'        => true,
			'showFilters'            => true,
			'showSearch'             => true,
			'allowUserChangeDisplay' => ! empty( $allow_users_to_change_display ) && $allow_users_to_change_display === 'yes',
			'appearance'             => ! empty( $appearance ) ? $appearance : 'light',
			'should_not_load_events' => false,
		];

		$block = new CalendarView\Block( $attr );

		switch ( $display ) {
			case 'week':
				$view = new CalendarView\Week\Week( $block );
				break;

			case 'day':
				$view = new CalendarView\Day\Day( $block );
				break;

			default:
				$view = new CalendarView\Month\Month( $block );
				break;
		}

		$block->set_view( $view );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $block->get_html();
	}
}
