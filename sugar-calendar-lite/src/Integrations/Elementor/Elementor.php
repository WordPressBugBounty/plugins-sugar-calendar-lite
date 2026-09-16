<?php

namespace Sugar_Calendar\Integrations\Elementor;

use Elementor\Widgets_Manager;
use Elementor\Controls_Manager;
use Elementor\Elements_Manager;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Integrations\Elementor\Controls\FixedTextHeading;
use Sugar_Calendar\Integrations\Elementor\Documents\Event;
use Sugar_Calendar\Integrations\Elementor\LoopGrid\QueryArgs;
use Sugar_Calendar\Integrations\Elementor\LoopGrid\WidgetSettings;
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

		add_action( 'wp', [ $this, 'remove_sc_frontend_display_hooks' ] );

		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );

		add_action( 'elementor/elements/categories_registered', [ $this, 'add_widget_categories' ] );
		add_action( 'elementor/controls/register', [ $this, 'register_controls' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );

		( new WidgetDependencies() )->hooks();
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

		( new WidgetSettings() )->hooks();
		( new QueryArgs() )->hooks();
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
	 * Add Sugar Calendar post type to Elementor Pro.
	 *
	 * @since 3.10.0
	 *
	 * @param array $post_types Post types.
	 *
	 * @return array
	 */
	public function add_sugar_calendar_post_type( $post_types ) {

		$post_types[ sugar_calendar_get_event_post_type_id() ] = esc_html__( 'Events', 'sugar-calendar-lite' );

		return $post_types;
	}
}
