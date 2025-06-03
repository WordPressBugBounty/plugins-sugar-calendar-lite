<?php

namespace Sugar_Calendar\Integrations\Elementor;

use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Widget_Base;
use Sugar_Calendar\Block\EventList\EventListView\Block;
use Sugar_Calendar\Block\EventList\EventListView\GridView;
use Sugar_Calendar\Block\EventList\EventListView\ListView;
use Sugar_Calendar\Block\EventList\EventListView\PlainView;
use Sugar_Calendar\Features\Tags\Common\Helpers;

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

		return esc_html__( 'Events List', 'sugar-calendar-lite' );
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
				'label' => esc_html__( 'Events List', 'sugar-calendar-lite' ),
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
		 * Extend the Elementor Event List widget controls.
		 *
		 * @since 3.5.0
		 *
		 * @param Widget_Base $this Current widget instance.
		 */
		do_action(
			'sugar_calendar_integrations_elementor_list_widget_register_controls_section_settings',
			$this
		);

		$this->add_control(
			'group_events_by_week',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Group events by week', 'sugar-calendar-lite' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'events_per_page',
			[
				'default'    => 10,
				'label'      => esc_html__( 'Events per page', 'sugar-calendar-lite' ),
				'show_label' => true,
				'type'       => Controls_Manager::NUMBER,
				'min'        => 1,
				'max'        => 30,
				'step'       => 1,
				'condition'  => [
					'group_events_by_week!' => 'yes',
				],
			]
		);

		$this->add_control(
			'maximum_events_to_show',
			[
				'default'    => 10,
				'label'      => esc_html__( 'Max events to show', 'sugar-calendar-lite' ),
				'show_label' => true,
				'type'       => Controls_Manager::NUMBER,
				'min'        => 1,
				'max'        => 30,
				'step'       => 1,
				'condition'  => [
					'group_events_by_week!' => 'yes',
				],
			]
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
				'default'    => 'list',
				'label'      => esc_html__( 'Display Type', 'sugar-calendar-lite' ),
				'options'    => [
					'list'  => esc_html__( 'List', 'sugar-calendar-lite' ),
					'grid'  => esc_html__( 'Grid', 'sugar-calendar-lite' ),
					'plain' => esc_html__( 'Plain', 'sugar-calendar-lite' ),
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
			'show_date_cards',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Show Date Cards', 'sugar-calendar-lite' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
				'condition'    => [
					'display_mode' => 'list',
				],
			]
		);

		$this->add_control(
			'show_descriptions',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Show Descriptions', 'sugar-calendar-lite' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'show_featured_images',
			[
				'default'      => 'yes',
				'label'        => esc_html__( 'Show Featured Images', 'sugar-calendar-lite' ),
				'return_value' => 'yes',
				'type'         => Controls_Manager::SWITCHER,
				'condition'    => [
					'display_mode!' => 'plain',
				],
			]
		);

		$this->add_control(
			'image_position',
			[
				'default'    => 'right',
				'label'      => esc_html__( 'Image position', 'sugar-calendar-lite' ),
				'options'    => [
					'left'  => esc_html__( 'Left', 'sugar-calendar-lite' ),
					'right' => esc_html__( 'Right', 'sugar-calendar-lite' ),
				],
				'show_label' => true,
				'type'       => Controls_Manager::SELECT,
				'condition'  => [
					'display_mode'         => 'list',
					'show_featured_images' => 'yes',
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

		$this->add_control(
			'links_color',
			[
				'alpha'   => false,
				'default' => '#000000D9',
				'label'   => esc_html__( 'Links Color', 'sugar-calendar-lite' ),
				'type'    => Controls_Manager::COLOR,
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output.
	 *
	 * @since 3.2.0
	 * @since 3.4.0 Additional options for event list block.
	 */
	protected function render() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$calendars                     = $this->get_settings_for_display( 'calendars' );
		$tags                          = $this->get_settings_for_display( 'tags' );
		$group_events_by_week          = $this->get_settings_for_display( 'group_events_by_week' );
		$events_per_page               = $this->get_settings_for_display( 'events_per_page' );
		$maximum_events_to_show        = $this->get_settings_for_maximum_events_to_show( $events_per_page );
		$display                       = $this->get_settings_for_display( 'display_mode' );
		$show_block_header             = $this->get_settings_for_display( 'show_block_header' );
		$allow_users_to_change_display = $show_block_header === 'yes' ? $this->get_settings_for_display( 'allow_users_to_change_display' ) : '';
		$show_filters                  = $show_block_header === 'yes' ? $this->get_settings_for_display( 'show_filters' ) : '';
		$show_search                   = $show_block_header === 'yes' ? $this->get_settings_for_display( 'show_search' ) : '';
		$show_date_cards               = $this->get_settings_for_display( 'show_date_cards' );
		$show_descriptions             = $this->get_settings_for_display( 'show_descriptions' );
		$show_featured_images          = $this->get_settings_for_display( 'show_featured_images' );
		$image_position                = $this->get_settings_for_display( 'image_position' );
		$appearance                    = $this->get_settings_for_display( 'appearance' );
		$accent_color                  = $this->get_settings_for_display( 'accent_color' );
		$links_color                   = $this->get_settings_for_links_color( $appearance );

		$attr = [
			'blockId'                => $this->get_id(),
			'calendars'              => ! empty( $calendars ) ? array_map( 'absint', $calendars ) : [],
			'tags'                   => ! empty( $tags ) ? array_map( 'absint', $tags ) : [],
			'groupEventsByWeek'      => ! empty( $group_events_by_week ) && $group_events_by_week === 'yes',
			'eventsPerPage'          => ! empty( $events_per_page ) ? absint( $events_per_page ) : 10,
			'maximumEventsToShow'    => ! empty( $maximum_events_to_show ) ? absint( $maximum_events_to_show ) : 10,
			'display'                => ! empty( $display ) ? $display : 'list',
			'showBlockHeader'        => ! empty( $show_block_header ) && $show_block_header === 'yes',
			'allowUserChangeDisplay' => ! empty( $allow_users_to_change_display ) && $allow_users_to_change_display === 'yes',
			'showFilters'            => ! empty( $show_filters ) && $show_filters === 'yes',
			'showSearch'             => ! empty( $show_search ) && $show_search === 'yes',
			'showDateCards'          => ! empty( $show_date_cards ) && $show_date_cards === 'yes',
			'showDescriptions'       => ! empty( $show_descriptions ) && $show_descriptions === 'yes',
			'showFeaturedImages'     => ! empty( $show_featured_images ) && $show_featured_images === 'yes',
			'imagePosition'          => ! empty( $image_position ) ? $image_position : 'right',
			'appearance'             => ! empty( $appearance ) ? $appearance : 'light',
			'accentColor'            => ! empty( $accent_color ) ? $accent_color : '#5685BD',
			'linksColor'             => ! empty( $links_color ) ? $links_color : '#000000D9',
			'should_not_load_events' => false,
		];

		/**
		 * Extend the Elementor Event List widget controls.
		 *
		 * @since 3.5.0
		 *
		 * @param array       $attr Event list block attributes.
		 * @param Widget_Base $this Current widget instance.
		 */
		$attr = apply_filters(
			'sugar_calendar_integrations_elementor_list_widget_render_attributes',
			$attr,
			$this
		);

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

	/**
	 * Get settings for Links Color.
	 *
	 * @since 3.4.0
	 *
	 * @param string $appearance Appearance.
	 *
	 * @return string
	 */
	private function get_settings_for_links_color( $appearance ) {

		$links_color = $this->get_settings_for_display( 'links_color' );

		// Default links color.
		$default_links_color = [
			'light' => '#000000D9',
			'dark'  => '#ffffff',
		];

		// Update default links color with default color based on appearance.
		if ( $appearance === 'dark' && $links_color === $default_links_color['light'] ) {

			$links_color = $default_links_color['dark'];

			$this->update_control(
				'links_color',
				[
					'default' => $default_links_color['dark'],
				]
			);
		} elseif ( $appearance === 'light' && $links_color === $default_links_color['dark'] ) {

			$links_color = $default_links_color['light'];

			$this->update_control(
				'links_color',
				[
					'default' => $default_links_color['light'],
				]
			);
		}

		return $links_color;
	}

	/**
	 * Get settings for Maximum Events to Show.
	 *
	 * @since 3.4.0
	 *
	 * @param int $events_per_page Events per page.
	 *
	 * @return int
	 */
	private function get_settings_for_maximum_events_to_show( $events_per_page ) {

		// Default maximum events to show.
		$maximum_events_to_show = $this->get_settings_for_display( 'maximum_events_to_show' );

		return $events_per_page > $maximum_events_to_show
			? $events_per_page
			: $maximum_events_to_show;
	}
}
