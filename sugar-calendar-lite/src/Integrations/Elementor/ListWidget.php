<?php

namespace Sugar_Calendar\Integrations\Elementor;

use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Widget_Base;
use Sugar_Calendar\Block\EventList\EventListView\Block;
use Sugar_Calendar\Block\EventList\EventListView\GridView;
use Sugar_Calendar\Block\EventList\EventListView\ListView;
use Sugar_Calendar\Block\EventList\EventListView\PlainView;

/**
 * Sugar Calendar Event List widget for Elementor.
 *
 * @since 3.2.0
 */
class ListWidget extends Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_name() {

		return 'sugar-calendar-events-list';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_title() {

		return esc_html__( 'Events List', 'sugar-calendar' );
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

		return [ 'list', 'events', 'sugar calendar' ];
	}

	/**
	 * Get widget style dependencies.
	 *
	 * @since 3.2.0
	 *
	 * @return string[]
	 */
	public function get_style_depends() {

		return [ 'sugar-calendar-event-list-block-style' ];
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

		return [ 'sc-frontend-blocks-event-list-js' ];
	}

	/**
	 * Register widget controls.
	 *
	 * @since 3.2.0
	 */
	protected function register_controls() {

		$this->start_controls_section(
			'section_sugar_calendar_events_list',
			[
				'label' => esc_html__( 'Events List', 'sugar-calendar' ),
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
				'default'    => 'list',
				'label'      => esc_html__( 'Display', 'sugar-calendar' ),
				'options'    => [
					'list'  => esc_html__( 'List', 'sugar-calendar' ),
					'grid'  => esc_html__( 'Grid', 'sugar-calendar' ),
					'plain' => esc_html__( 'Plain', 'sugar-calendar' ),
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
			'show_featured_images',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Show Featured Images', 'sugar-calendar' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'show_descriptions',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Show Descriptions', 'sugar-calendar' ),
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

		$this->add_control(
			'links_color',
			[
				'alpha'   => false,
				'default' => '#000000D9',
				'label'   => esc_html__( 'Links Color', 'sugar-calendar' ),
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

		$calendars                     = $this->get_settings_for_display( 'calendars' );
		$display                       = $this->get_settings_for_display( 'display_mode' );
		$allow_users_to_change_display = $this->get_settings_for_display( 'allow_users_to_change_display' );
		$show_featured_images          = $this->get_settings_for_display( 'show_featured_images' );
		$show_descriptions             = $this->get_settings_for_display( 'show_descriptions' );
		$accent_color                  = $this->get_settings_for_display( 'accent_color' );
		$links_color                   = $this->get_settings_for_display( 'links_color' );

		$attr = [
			'blockId'                => $this->get_id(),
			'calendars'              => ! empty( $calendars ) ? array_map( 'absint', $calendars ) : [],
			'display'                => ! empty( $display ) ? $display : 'list',
			'accentColor'            => ! empty( $accent_color ) ? $accent_color : '#5685BD',
			'linksColor'             => ! empty( $links_color ) ? $links_color : '#000000D9',
			'allowUserChangeDisplay' => ! empty( $allow_users_to_change_display ) && $allow_users_to_change_display === 'yes',
			'showFeaturedImages'     => ! empty( $show_featured_images ) && $show_featured_images === 'yes',
			'showDescriptions'       => ! empty( $show_descriptions ) && $show_descriptions === 'yes',
			'should_not_load_events' => false,
		];

		$block = new Block( $attr );

		switch ( $display ) {
			case GridView::DISPLAY_MODE:
				$view = new GridView( $block );
				break;

			case PlainView::DISPLAY_MODE:
				$view = new PlainView( $block );
				break;

			default:
				$view = new ListView( $block );
		}

		$block->set_view( $view );

		// Fix issue with incorrect descriptions.
		Plugin::instance()->frontend->remove_content_filter();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $block->get_html();

		Plugin::instance()->frontend->add_content_filter();
	}
}
