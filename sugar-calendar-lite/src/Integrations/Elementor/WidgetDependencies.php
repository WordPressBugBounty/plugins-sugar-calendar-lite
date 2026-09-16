<?php

namespace Sugar_Calendar\Integrations\Elementor;

use Elementor\Plugin;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventBuyTicketBox;
use Sugar_Calendar\Integrations\Elementor\Widgets\EventBuyTicketButton;

/**
 * Resolves what a page's Elementor widgets need loaded: ticketing includes for
 * the widgets that require them, and the Sugar Calendar block assets.
 *
 * @since 3.14.0
 */
class WidgetDependencies {

	/**
	 * Register hooks.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		add_action( 'elementor/widget/before_render_content', [ $this, 'include_widget_files' ] );

		add_filter( 'sugar_calendar_block_list_should_load_assets', [ $this, 'should_load_sc_block_list_assets' ] );
		add_filter( 'sugar_calendar_block_calendar_should_load_assets', [ $this, 'should_load_sc_block_calendar_assets' ] );
	}

	/**
	 * Load the needed files for specific widget.
	 *
	 * @since 3.10.0
	 * @since 3.14.0 Moved here from Elementor::include_widget_files().
	 *
	 * @param \Elementor\Widget_Base $widget The widget.
	 */
	public function include_widget_files( $widget ) {

		/**
		 * Fire to include widget files.
		 *
		 * Name predates this class; renaming it to match would break Pro and any
		 * third party already hooking it.
		 *
		 * @since 3.10.0
		 *
		 * @param \Elementor\Widget_Base $widget The widget.
		 */
		do_action( 'sugar_calendar_integrations_elementor_include_widget_files', $widget ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

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
	 * Check if the Sugar Calendar block list assets should be loaded.
	 *
	 * @since 3.2.1
	 * @since 3.14.0 Moved here from Elementor::should_load_sc_block_list_assets().
	 *
	 * @param bool $should_load Whether the assets should be loaded.
	 *
	 * @return bool
	 */
	public function should_load_sc_block_list_assets( $should_load ) {

		if ( ! $this->has_widget( 'sugar-calendar-events-list' ) ) {
			return $should_load;
		}

		return true;
	}

	/**
	 * Check if the Sugar Calendar block calendar assets should be loaded.
	 *
	 * @since 3.2.1
	 * @since 3.14.0 Moved here from Elementor::should_load_sc_block_calendar_assets().
	 *
	 * @param bool $should_load Whether the assets should be loaded.
	 *
	 * @return bool
	 */
	public function should_load_sc_block_calendar_assets( $should_load ) {

		if ( ! $this->has_widget( 'sugar-calendar-events-calendar' ) ) {
			return $should_load;
		}

		return true;
	}

	/**
	 * Check if a widget is present in the current post.
	 *
	 * @since 3.2.1
	 * @since 3.14.0 Renamed from Elementor::check_for_widget() and moved here.
	 *
	 * @param string $widget_name The widget name.
	 *
	 * @return bool
	 */
	private function has_widget( $widget_name ) {

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

		return $this->elements_contain_widget( $elements_data, $widget_name );
	}

	/**
	 * Whether any element in the tree is the named widget.
	 *
	 * Recursive because Elementor nests to arbitrary depth: a widget can sit at
	 * the top level, inside a container, inside nested containers, or inside a
	 * classic section/column pair. A fixed-depth walk matches exactly one of
	 * those shapes and misses the rest, leaving the block assets unloaded.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $elements    Elementor element definitions.
	 * @param string $widget_name The widget name.
	 *
	 * @return bool
	 */
	private function elements_contain_widget( $elements, $widget_name ) {

		foreach ( $elements as $element ) {

			$el_type   = $element['elType'] ?? '';
			$el_widget = $element['widgetType'] ?? '';

			if ( $el_type === 'widget' && $el_widget === $widget_name ) {
				return true;
			}

			if (
				! empty( $element['elements'] ) &&
				$this->elements_contain_widget( $element['elements'], $widget_name )
			) {
				return true;
			}
		}

		return false;
	}
}
