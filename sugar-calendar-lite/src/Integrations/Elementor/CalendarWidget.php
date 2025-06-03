<?php

namespace Sugar_Calendar\Integrations\Elementor;

use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Widget_Base;
use Sugar_Calendar\Block\Calendar\CalendarView;
use Sugar_Calendar\Features\Tags\Common\Helpers;

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

		return esc_html__( 'Events Calendar', 'sugar-calendar-lite' );
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
				'label' => esc_html__( 'Events Calendar', 'sugar-calendar-lite' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'section_title_settings',
			[
				'label' => __( 'Settings', 'sugar-calendar-lite' ),
				'type'  => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'calendars',
			[
				'default'  => [],
				'label'    => esc_html__( 'Calendars', 'sugar-calendar-lite' ),
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
			'tags',
			[
				'default'  => [],
				'label'    => esc_html__( 'Tags', 'sugar-calendar-lite' ),
				'multiple' => true,
				'options'  => get_terms(
					[
						'hide_empty' => false,
						'taxonomy'   => Helpers::get_tags_taxonomy_id(),
						'fields'     => 'id=>name',
					]
				),
				'type'     => Controls_Manager::SELECT2,
			]
		);

		/**
		 * Extend the Elementor Event Calendar widget controls.
		 *
		 * @since 3.5.0
		 *
		 * @param Widget_Base $this Current widget instance.
		 */
		do_action(
			'sugar_calendar_integrations_elementor_calendar_widget_register_controls_section_settings',
			$this
		);

		$this->add_control(
			'section_title_display',
			[
				'label'     => __( 'Display', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'display_mode',
			[
				'default'    => 'month',
				'label'      => esc_html__( 'Display Type', 'sugar-calendar-lite' ),
				'options'    => [
					'month' => esc_html__( 'Month', 'sugar-calendar-lite' ),
					'week'  => esc_html__( 'Week', 'sugar-calendar-lite' ),
					'day'   => esc_html__( 'Day', 'sugar-calendar-lite' ),
				],
				'show_label' => true,
				'type'       => Controls_Manager::SELECT,
			]
		);

		$this->add_control(
			'show_block_header',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Show Block Header', 'sugar-calendar-lite' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
				'condition'    => [
					'display_mode!' => 'plain',
				],
			]
		);

		$this->add_control(
			'allow_users_to_change_display',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Allow Users to Change Display', 'sugar-calendar-lite' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
				'condition'    => [
					'display_mode!'     => 'plain',
					'show_block_header' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_filters',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Show Filters', 'sugar-calendar-lite' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
				'condition'    => [
					'display_mode!'     => 'plain',
					'show_block_header' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_search',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Show Search', 'sugar-calendar-lite' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
				'condition'    => [
					'display_mode!'     => 'plain',
					'show_block_header' => 'yes',
				],
			]
		);

		$this->add_control(
			'section_title_styles',
			[
				'label'     => __( 'Styles', 'sugar-calendar-lite' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'appearance',
			[
				'default'    => 'light',
				'label'      => esc_html__( 'Appearance', 'sugar-calendar-lite' ),
				'options'    => [
					'light' => esc_html__( 'Light', 'sugar-calendar-lite' ),
					'dark'  => esc_html__( 'Dark', 'sugar-calendar-lite' ),
				],
				'show_label' => true,
				'type'       => Controls_Manager::SELECT,
			]
		);

		$this->add_control(
			'accent_color',
			[
				'alpha'   => false,
				'default' => '#5685BD',
				'label'   => esc_html__( 'Accent Color', 'sugar-calendar-lite' ),
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
		$show_block_header             = $this->get_settings_for_display( 'show_block_header' );
		$allow_users_to_change_display = $show_block_header === 'yes' ? $this->get_settings_for_display( 'allow_users_to_change_display' ) : '';
		$show_filters                  = $show_block_header === 'yes' ? $this->get_settings_for_display( 'show_filters' ) : '';
		$show_search                   = $show_block_header === 'yes' ? $this->get_settings_for_display( 'show_search' ) : '';
		$accent_color                  = $this->get_settings_for_display( 'accent_color' );
		$calendars                     = $this->get_settings_for_display( 'calendars' );
		$tags                          = $this->get_settings_for_display( 'tags' );
		$appearance                    = $this->get_settings_for_display( 'appearance' );

		$attr = [
			'blockId'                => $this->get_id(),
			'display'                => ! empty( $display ) ? $display : 'month',
			'accentColor'            => ! empty( $accent_color ) ? $accent_color : '#5685BD',
			'calendars'              => ! empty( $calendars ) ? array_map( 'absint', $calendars ) : [],
			'tags'                   => ! empty( $tags ) ? array_map( 'absint', $tags ) : [],
			'groupEventsByWeek'      => true,
			'showBlockHeader'        => ! empty( $show_block_header ) && $show_block_header === 'yes',
			'allowUserChangeDisplay' => ! empty( $allow_users_to_change_display ) && $allow_users_to_change_display === 'yes',
			'showFilters'            => ! empty( $show_filters ) && $show_filters === 'yes',
			'showSearch'             => ! empty( $show_search ) && $show_search === 'yes',
			'appearance'             => ! empty( $appearance ) ? $appearance : 'light',
			'should_not_load_events' => false,
		];

		/**
		 * Extend the Elementor Event Calendar widget controls.
		 *
		 * @since 3.5.0
		 *
		 * @param array       $attr Event calendar block attributes.
		 * @param Widget_Base $this Current widget instance.
		 */
		$attr = apply_filters(
			'sugar_calendar_integrations_elementor_calendar_widget_render_attributes',
			$attr,
			$this
		);

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
