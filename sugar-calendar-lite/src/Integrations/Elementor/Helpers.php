<?php

namespace Sugar_Calendar\Integrations\Elementor;

use Elementor\Plugin as ElementorPlugin;

/**
 * Helpers for the Elementor integration.
 *
 * @since 3.10.0
 */
class Helpers {

	/**
	 * Whether the Sugar Calendar widget should be displayed or not.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	public static function should_show_widget() {

		$instance = ElementorPlugin::$instance;

		if (
			empty( $instance ) ||
			! property_exists( $instance, 'documents' ) ||
			empty( $instance->documents ) ||
			! method_exists( $instance->documents, 'get_current' ) ||
			empty( $instance->documents->get_current() )
		) {
			return false;
		}

		// Get the current document/post being edited.
		$document = $instance->documents->get_current();

		if ( in_array( $document->get_name(), [ 'loop-item', sugar_calendar_get_event_post_type_id() ], true ) ) {
			return true;
		}

		if ( $document->get_name() === 'loop-item' ) {
			return true;
		}

		if (
			! method_exists( $document, 'get_post' ) ||
			empty( $document->get_post() )
		) {
			return false;
		}

		if ( $document->get_post()->post_type === sugar_calendar_get_event_post_type_id() ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether to remove the SC frontend display hooks.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	public static function should_remove_frontend_display_hooks() {

		if ( is_admin() ) {
			return false;
		}

		$post = get_post();

		if (
			empty( $post ) ||
			$post->post_type !== sugar_calendar_get_event_post_type_id()
		) {
			return false;
		}

		$is_builder_mode = get_post_meta( $post->ID, '_elementor_edit_mode', true );

		if ( empty( $is_builder_mode ) || $is_builder_mode !== 'builder' ) {
			return false;
		}

		/**
		 * Filters whether to remove the SC frontend display hooks.
		 *
		 * @since 3.10.0
		 *
		 * @param bool     $should_remove_frontend_display_hooks Whether to remove the SC frontend display hooks.
		 * @param \WP_Post $post                                 The post object.
		 */
		$should_remove_frontend_display_hooks = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_integrations_elementor_should_remove_frontend_display_hooks',
			true,
			$post
		);

		return $should_remove_frontend_display_hooks;
	}
}
