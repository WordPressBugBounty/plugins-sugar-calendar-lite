<?php

namespace Sugar_Calendar\Integrations\Elementor;

use Elementor\Plugin;
use Elementor\Widgets_Manager;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;

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

		add_filter( 'sugar_calendar_block_list_should_load_assets', [ $this, 'should_load_sc_block_list_assets' ] );
		add_filter( 'sugar_calendar_block_calendar_should_load_assets', [ $this, 'should_load_sc_block_calendar_assets' ] );

		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );

		add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );
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
	 *
	 * @param Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widget( $widgets_manager ) {

		$widgets_manager->register( new CalendarWidget() );
		$widgets_manager->register( new ListWidget() );
	}
}
