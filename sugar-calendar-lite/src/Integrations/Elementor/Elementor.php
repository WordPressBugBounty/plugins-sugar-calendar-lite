<?php

namespace Sugar_Calendar\Integrations\Elementor;

use Elementor\Plugin;
use Elementor\Widgets_Manager;
use Elementor\Controls_Manager;
use Elementor\Elements_Manager;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Integrations\Elementor\Controls\FixedTextHeading;
use Sugar_Calendar\Integrations\Elementor\Documents\Event;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventTitle;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventDetails;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventDate;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventTime;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventImage;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventBuyTicketButton;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventBuyTicketBox;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventLocation;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventLocationMap;

/**
 * Elementor integration.
 *
 * @since 3.2.0
 */
class Elementor {

	/**
	 * Initialize the Elementor integration.
	 *
	 * @since 3.2.0
	 */
	public function init() {

		$this->hooks();
		$this->elementor_pro_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.2.0
	 */
	private function hooks() {

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		add_action( 'elementor/init', [ $this, 'enqueue_common_scripts' ] );

		add_action( 'elementor/widget/before_render_content', [ $this, 'include_widget_files' ] );

		add_action( 'wp', [ $this, 'remove_sc_frontend_display_hooks' ] );

		add_filter( 'sugar_calendar_block_list_should_load_assets', [ $this, 'should_load_sc_block_list_assets' ] );
		add_filter( 'sugar_calendar_block_calendar_should_load_assets', [ $this, 'should_load_sc_block_calendar_assets' ] );

		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );

		add_action( 'elementor/elements/categories_registered', [ $this, 'add_widget_categories' ] );
		add_action( 'elementor/controls/register', [ $this, 'register_controls' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );
	}

	/**
	 * Register hooks for Elementor Pro.
	 *
	 * @since 3.10.0
	 */
	private function elementor_pro_hooks() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if (
			! class_exists( '\Elementor\Plugin' ) ||
			! defined( 'ELEMENTOR_PRO_VERSION' )
		) {
			return;
		}

		add_action( 'elementor_pro/utils/get_public_post_types', [ $this, 'add_sugar_calendar_post_type' ] );
		add_action( 'elementor/documents/register', [ $this, 'register_documents' ] );
		add_action( 'elementor/element/loop-grid/section_query/before_section_end', [ $this, 'add_filter_controls_in_loop_grid' ], 10, 1 );
		add_action( 'elementor/element/loop-grid/section_query/after_section_end', [ $this, 'hide_loop_grid_controls_on_events' ] );

		add_filter( 'elementor/query/query_args', [ $this, 'filter_loop_grid_query_args' ], 900, 2 );
	}

	/**
	 * Enqueue the common scripts.
	 *
	 * @since 3.10.0
	 */
	public function enqueue_common_scripts() {

		wp_register_style(
			'sugar-calendar-pro-elementor-common',
			SC_PLUGIN_ASSETS_URL . 'pro/css/integrations/elementor/common' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		// Register Google maps-related assets.
		$google_maps_api_key = BaseHelpers::get_google_maps_api_key();

		if ( empty( $google_maps_api_key ) ) {
			return;
		}

		wp_register_script(
			'sugar-calendar-integrations-elementor-venue-map',
			SC_PLUGIN_ASSETS_URL . 'integrations/elementor/js/venue-map' . WP::asset_min() . '.js',
			[ 'jquery', 'sc-google-maps-api' ],
			BaseHelpers::get_asset_version(),
			true
		);
	}

	/**
	 * Load the needed files for specific widget.
	 *
	 * @since 3.10.0
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 */
	public function include_widget_files( $widget ) {

		/**
		 * Fire to include widget files.
		 *
		 * @since 3.10.0
		 *
		 * @param \Elementor\Widget_Base $widget The widget.
		 */
		do_action( 'sugar_calendar_integrations_elementor_include_widget_files', $widget );

		if (
			! $widget instanceof EventBuyTicketBox &&
			! $widget instanceof EventBuyTicketButton
		) {
			return;
		}

		$common_features = sugar_calendar()->get_common_features();

		if ( empty( $common_features ) ) {
			return;
		}

		$event_ticketing = $common_features->get_feature( 'EventTicketing' );

		if ( empty( $event_ticketing ) ) {
			return;
		}

		if ( method_exists( $event_ticketing, 'get_frontend' ) ) {
			$event_ticketing->get_frontend();
		}

		if ( method_exists( $event_ticketing, 'include_frontend' ) ) {
			$event_ticketing->include_frontend();
		}
	}

	/**
	 * Remove the SC frontend display hooks.
	 *
	 * @since 3.10.0
	 */
	public function remove_sc_frontend_display_hooks() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if ( ! Helpers::should_remove_frontend_display_hooks() ) {
			return;
		}

		remove_action( 'sc_event_details', [ sugar_calendar()->get_frontend(), 'event_details' ] );

		// Remove the event ticketing.
		remove_action( 'sc_after_event_content', 'Sugar_Calendar\\AddOn\\Ticketing\\Frontend\\Single\\display' );

		// Remove the google map.
		remove_action( 'sc_after_event_content', [ sugar_calendar()->get_common_features()->get_feature( 'GoogleMaps' ), 'show_map' ] );
	}

	/**
	 * Check if the Sugar Calendar block list assets should be loaded.
	 *
	 * @since 3.2.1
	 *
	 * @param bool $should_load Whether the assets should be loaded.
	 *
	 * @return bool
	 */
	public function should_load_sc_block_list_assets( $should_load ) {

		if ( ! $this->check_for_widget( 'sugar-calendar-events-list' ) ) {
			return $should_load;
		}

		return true;
	}

	/**
	 * Check if the Sugar Calendar block calendar assets should be loaded.
	 *
	 * @since 3.2.1
	 *
	 * @param bool $should_load Whether the assets should be loaded.
	 *
	 * @return bool
	 */
	public function should_load_sc_block_calendar_assets( $should_load ) {

		if ( ! $this->check_for_widget( 'sugar-calendar-events-calendar' ) ) {
			return $should_load;
		}

		return true;
	}

	/**
	 * Check if a widget is present in the current post.
	 *
	 * @since 3.2.1
	 *
	 * @param string $widget_name The widget name.
	 *
	 * @return bool
	 */
	private function check_for_widget( $widget_name ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Get the post ID.
		$post_id = get_the_ID();

		if ( empty( $post_id ) ) {
			return false;
		}

		$document = Plugin::instance()->documents->get( $post_id );

		if ( ! $document ) {
			return false;
		}

		$elements_data = $document->get_elements_data();

		if ( empty( $elements_data ) ) {
			return false;
		}

		foreach ( $elements_data as $element_data ) {

			if ( empty( $element_data['elements'] ) ) {
				continue;
			}

			foreach ( $element_data['elements'] as $element ) {

				if (
					$element['elType'] === 'widget' &&
					$element['widgetType'] === $widget_name

				) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Enqueue editor scripts.
	 *
	 * @since 3.2.0
	 */
	public function enqueue_editor_scripts() {

		wp_enqueue_style(
			'sugar-calendar-elementor-editor',
			SC_PLUGIN_ASSETS_URL . 'css/integrations/elementor/editor' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);
	}

	/**
	 * Register the Sugar Calendar widget.
	 *
	 * @since 3.2.0
	 * @since 3.10.0 Made the widget classes to register filterable.
	 *
	 * @param Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widget( $widgets_manager ) {

		$widget_classes = [
			CalendarWidget::class,
			ListWidget::class,
			EventTitle::class,
			EventDetails::class,
			EventDate::class,
			EventTime::class,
			EventImage::class,
			EventBuyTicketButton::class,
			EventBuyTicketBox::class,
			EventLocation::class,
			EventLocationMap::class,
		];

		/**
		 * Filters the Sugar Calendar Elementor widget classes.
		 *
		 * @since 3.10.0
		 *
		 * @param string[] $widget_classes Array of widget classes.
		 */
		$widget_classes = apply_filters(
			'sugar_calendar_integrations_elementor_widget_classes',
			$widget_classes
		);

		foreach ( $widget_classes as $widget_class ) {

			if ( ! class_exists( $widget_class ) ) {
				continue;
			}

			$widgets_manager->register( new $widget_class() );
		}
	}

	/**
	 * Add Sugar Calendar-specific widget categories.
	 *
	 * @since 3.10.0
	 * @since 3.10.0 Added the sugar-calendar-event-elements-single category.
	 *
	 * @param Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function add_widget_categories( $elements_manager ) {

		$elements_manager->add_category(
			'sugar-calendar',
			[
				'title' => esc_html__( 'Sugar Calendar', 'sugar-calendar-lite' ),
				'icon'  => 'icon-sugar-calendar',
			]
		);

		$elements_manager->add_category(
			'sugar-calendar-event-elements-single',
			[
				'title' => esc_html__( 'Sugar Calendar Events', 'sugar-calendar-lite' ),
				'icon'  => 'icon-sugar-calendar',
			]
		);
	}

	/**
	 * Register custom Sugar Calendar-specific Elementor controls.
	 *
	 * @since 3.10.0
	 *
	 * @param Controls_Manager $controls_manager Elementor controls manager.
	 *
	 * @return void
	 */
	public function register_controls( $controls_manager ) {

		$controls_manager->register( new FixedTextHeading() );
	}

	/**
	 * Register the Sugar Calendar event post document.
	 *
	 * @since 3.10.0
	 *
	 * @param \Elementor\Core\Documents_Manager\Documents_Manager $documents_manager The documents manager.
	 */
	public function register_documents( $documents_manager ) {

		$documents_manager->register_document_type( sugar_calendar_get_event_post_type_id(), Event::get_class_full_name() );
	}

	/**
	 * Hide specific controls for Sugar Calendar events in Loop Grid.
	 *
	 * @since 3.10.0
	 *
	 * @param \ElementorPro\Modules\Posts\Widgets\Base_Widget $widget Widget object.
	 */
	public function hide_loop_grid_controls_on_events( $widget ) {

		if ( $widget->get_name() !== 'loop-grid' ) {
			return;
		}

		/**
		 * Filters the flag to hide the controls on the Loop Grid widget.
		 *
		 * @since 3.10.0
		 *
		 * @param bool                                            $flag_to_hide_controls Flag to hide the controls.
		 * @param \ElementorPro\Modules\Posts\Widgets\Base_Widget $widget                Widget object.
		 */
		$flag_to_hide_controls = apply_filters( 'sugar_calendar_integrations_elementor_loop_grid_hide_controls_on_events', true, $widget );

		if ( ! $flag_to_hide_controls ) {
			return;
		}

		$controls         = $widget->get_controls();
		$controls_to_hide = [
			// Layout section controls.
			'masonry',
			'alternate_template',
			// Query section controls.
			'post_query_select_date',
			'post_query_date_before',
			'post_query_date_after',
			'post_query_orderby',
			'post_query_order',
			// Include section controls.
			'post_query_query_include',
			'post_query_include',
			'post_query_include_term_ids',
			'post_query_include_authors',
			// Exclude section controls.
			'post_query_query_exclude',
			'post_query_exclude',
			'post_query_exclude_ids',
			'post_query_exclude_term_ids',
			'post_query_exclude_authors',
		];

		foreach ( $controls_to_hide as $control_id ) {
			if ( ! isset( $controls[ $control_id ] ) ) {
				continue;
			}

			// Get existing condition.
			$control_config     = $controls[ $control_id ];
			$existing_condition = isset( $control_config['condition'] ) ? $control_config['condition'] : [];

			// Add our condition - hide when sc_event is selected.
			$existing_condition['post_query_post_type!'] = sugar_calendar_get_event_post_type_id();

			$widget->update_control(
				$control_id,
				[
					'condition' => $existing_condition,
				]
			);
		}
	}

	/**
	 * Filter the query arguments for the Loop Grid widget.
	 *
	 * @since 3.10.0
	 *
	 * @param array                                           $query_args Array containing the query to be used.
	 * @param \ElementorPro\Modules\Posts\Widgets\Base_Widget $widget     Widget object.
	 *
	 * @return array
	 */
	public function filter_loop_grid_query_args( $query_args, $widget ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded

		if ( $widget->get_name() !== 'loop-grid' ) {
			return $query_args;
		}

		$settings = $widget->get_settings_for_display();

		// Only apply to events.
		if ( empty( $settings['post_query_post_type'] ) || $settings['post_query_post_type'] !== sugar_calendar_get_event_post_type_id() ) {
			return $query_args;
		}

		$custom_query_args = [
			'post_status' => 'publish',
			'post_type'   => sugar_calendar_get_event_post_type_id(),
			'paged'       => get_query_var( 'page', 1 ),
			'orderby'     => 'post__in',
		];

		if ( ! empty( $settings['sce_include_by_post_id'] ) ) {
			// Convert the comma-separated string to an array.
			$sce_include_post_ids = explode( ',', $settings['sce_include_by_post_id'] );

			if ( ! empty( $sce_include_post_ids ) ) {
				// Sanitize.
				$sce_include_post_ids = array_map( 'absint', $sce_include_post_ids );

				// In the case where included post ID is included, we will only display them.
				if ( ! empty( $sce_include_post_ids ) ) {
					$custom_query_args['post__in'] = $sce_include_post_ids;

					return $custom_query_args;
				}
			}
		}

		$sce_args = [];

		if ( ! empty( $settings['calendars'] ) && is_array( $settings['calendars'] ) ) {
			$sce_args['calendar_ids'] = $settings['calendars'];
		}

		/**
		 * Filters the arguments passed to get the Sugar Calendar events for the Loop Grid widget.
		 *
		 * @since 3.10.0
		 *
		 * @param array                                               $sce_args   Array containing the arguments to get the Sugar Calendar events.
		 * @param array                                               $settings   Array containing the settings of the Loop Grid widget.
		 * @param array                                               $query_args Array containing the query arguments for the Loop Grid widget.
		 * @param \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Grid $widget     Widget object.
		 */
		$sce_args = apply_filters( 'sugar_calendar_integrations_elementor_loop_grid_query_args', $sce_args, $settings, $query_args, $widget );

		/**
		 * Filters the attributes passed to get the Sugar Calendar events for the Loop Grid widget.
		 *
		 * @since 3.10.0
		 *
		 * @param array                                               $sce_atts   Array containing the arguments to get the Sugar Calendar events.
		 * @param array                                               $settings   Array containing the settings of the Loop Grid widget.
		 * @param array                                               $query_args Array containing the query arguments for the Loop Grid widget.
		 * @param \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Grid $widget     Widget object.
		 */
		$sce_atts = apply_filters( 'sugar_calendar_integrations_elementor_loop_grid_attributes', [], $settings, $query_args, $widget );

		$events = BaseHelpers::get_upcoming_events_list_with_recurring( $sce_args, $sce_atts );

		if ( empty( $events ) ) {
			return [];
		}

		$post_ids = wp_list_pluck( $events, 'object_id' );

		if ( empty( $post_ids ) ) {
			return [];
		}

		$custom_query_args['post__in'] = $post_ids;

		if ( ! empty( $settings['posts_per_page'] ) && is_numeric( $settings['posts_per_page'] ) ) {
			$custom_query_args['posts_per_page'] = absint( $settings['posts_per_page'] );
		}

		if ( ! empty( $settings['sce_exclude_by_post_id'] ) ) {
			// Convert the comma-separated string to an array.
			$sce_exclude_post_ids = explode( ',', $settings['sce_exclude_by_post_id'] );

			if ( ! empty( $sce_exclude_post_ids ) ) {
				// Sanitize.
				$sce_exclude_post_ids = array_map( 'absint', $sce_exclude_post_ids );

				if ( ! empty( $sce_exclude_post_ids ) ) {
					// In the case where excluded post ID is excluded, we will not display them.
					$custom_query_args['post__not_in'] = $sce_exclude_post_ids;

					// Remove the post IDs from the post__in array.
					if ( ! empty( $custom_query_args['post__in'] ) ) {
						$custom_query_args['post__in'] = array_diff( $custom_query_args['post__in'], $sce_exclude_post_ids );
					}
				}
			}
		}

		return $custom_query_args;
	}

	/**
	 * Add custom controls in the loop grid widget.
	 *
	 * @since 3.10.0
	 *
	 * @param \ElementorPro\Modules\Posts\Widgets\Base_Widget $widget Widget object.
	 */
	public function add_filter_controls_in_loop_grid( $widget ) {

		if ( $widget->get_name() !== 'loop-grid' ) {
			return;
		}

		// Start injection to position the control right after the Source control.
		$widget->start_injection(
			[
				'type' => 'control',
				'at'   => 'after',
				'of'   => 'post_query_post_type',
			]
		);

		$widget->add_control(
			'calendars',
			[
				'label'       => esc_html__( 'Filter by Calendars', 'sugar-calendar-lite' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => get_terms(
					[
						'hide_empty' => false,
						'taxonomy'   => 'sc_event_category',
						'fields'     => 'id=>name',
					]
				),
				'default'     => [],
				'label_block' => true,
				'condition'   => [
					'post_query_post_type' => sugar_calendar_get_event_post_type_id(),
				],
				'description' => esc_html__( 'Leave empty to show events from all calendars.', 'sugar-calendar-lite' ),
			]
		);

		/**
		 * Fires after adding the SCE filters controls to the Loop Grid widget.
		 *
		 * @since 3.10.0
		 *
		 * @param \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Grid $widget Loop Grid Widget object.
		 */
		do_action( 'sugar_calendar_integrations_elementor_loop_grid_add_filter_controls', $widget );

		$widget->add_control(
			'sce_include_by_post_id',
			[
				'label'       => esc_html__( 'Include by Post ID', 'sugar-calendar-lite' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => esc_html__( '1, 2, 3', 'sugar-calendar-lite' ),
				'condition'   => [
					'post_query_post_type' => sugar_calendar_get_event_post_type_id(),
				],
				'description' => esc_html__( 'Enter comma-separated post IDs to include specific events.', 'sugar-calendar-lite' ),
				'ai'          => [
					'active' => false,
				],
			]
		);

		$widget->add_control(
			'sce_exclude_by_post_id',
			[
				'label'       => esc_html__( 'Exclude by Post ID', 'sugar-calendar-lite' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => esc_html__( '21, 22', 'sugar-calendar-lite' ),
				'condition'   => [
					'post_query_post_type' => sugar_calendar_get_event_post_type_id(),
				],
				'description' => esc_html__( 'Enter comma-separated post IDs to exclude specific events.', 'sugar-calendar-lite' ),
				'ai'          => [
					'active' => false,
				],
			]
		);

		$widget->end_injection();
	}

	/**
	 * Add Sugar Calendar post type to Elementor Pro.
	 *
	 * @since 3.10.0
	 *
	 * @param array $post_types Post types.
	 *
	 * @return array
	 */
	public function add_sugar_calendar_post_type( $post_types ) {

		$post_types[ sugar_calendar_get_event_post_type_id() ] = esc_html__( 'Events', 'sugar-calendar' );

		return $post_types;
	}
}
