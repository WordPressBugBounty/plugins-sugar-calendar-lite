<?php

namespace Sugar_Calendar\Integrations\Wpml;

use WP_Post;

/**
 * WPML Save Integration.
 *
 * Handles data persistence for WPML duplicate events, ensuring they update
 * the original event row rather than creating new rows.
 *
 * @since 3.12.0
 */
class WpmlSave {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		// Map duplicate event saves to the original post ID.
		add_filter( 'sugar_calendar_event_pre_save_context', [ $this, 'filter_pre_save_context' ], 10 );

		// Strip title/content from duplicate event saves.
		add_filter( 'sugar_calendar_event_to_save', [ $this, 'filter_event_to_save' ], 10, 3 );
	}

	/**
	 * Remap duplicate event saves to target the original post ID.
	 *
	 * When WPML creates a duplicate of an event, we want to store event data
	 * (dates, recurrence, etc.) against the original event row, not create a new one.
	 *
	 * @since 3.12.0
	 *
	 * @param array $context Save context.
	 *
	 * @return array Modified context.
	 */
	public function filter_pre_save_context( $context ) {

		// Public filter: the object may be missing or not a WP_Post — bail before
		// dereferencing ->post_type, which would fatal in PHP 8.
		if ( ! isset( $context['object_id'] ) || empty( $context['object'] ) || ! ( $context['object'] instanceof WP_Post ) ) {
			return $context;
		}

		// If not a duplicate event, return unmodified.
		if ( ! Wpml::is_translated_post( $context['object_id'], $context['object']->post_type ) ) {
			return $context;
		}

		// Remap to the original post ID and flag as WPML save.
		$context['object_id'] = Wpml::get_original_post_id( $context['object_id'], $context['object']->post_type );
		$context['source']    = 'metabox_wpml_save';

		return $context;
	}

	/**
	 * Strip title and content from duplicate event saves.
	 *
	 * Translated posts maintain their own title/content in wp_posts.
	 * We only want to sync event metadata (dates, recurrence) to the original row.
	 *
	 * @since 3.12.0
	 *
	 * @param array  $event_to_save Event data to persist.
	 * @param object $event         Existing event object (if any).
	 * @param array  $context       Save context.
	 *
	 * @return array Modified event data.
	 */
	public function filter_event_to_save( $event_to_save, $event, $context ) {

		// Only apply to WPML duplicate saves.
		if ( $context['source'] !== 'metabox_wpml_save' ) {
			return $event_to_save;
		}

		// Remove title and content so the original event row keeps its values.
		unset( $event_to_save['title'] );
		unset( $event_to_save['content'] );

		return $event_to_save;
	}
}

