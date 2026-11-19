<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

/**
 * Builds the post data importers write an event with.
 *
 * @since 3.18.0
 */
class EventPostArgs {

	/**
	 * Get the wp_insert_post() arguments for an imported event.
	 *
	 * The arguments are slashed because wp_insert_post() unslashes what it saves, which would
	 * drop every backslash in the content. Dates, password and author apply when the source has them.
	 *
	 * @since 3.18.0
	 *
	 * @param array $data The event data.
	 *
	 * @return array
	 */
	public function get_post_args( $data ) {

		$args = [
			'post_title'   => $data['title'],
			'post_content' => $data['content'],
			'post_excerpt' => $data['excerpt'] ?? '',
			'post_status'  => $data['status'],
			'post_type'    => sugar_calendar_get_event_post_type_id(),
			'post_author'  => empty( $data['post_author'] ) ? get_current_user_id() : $data['post_author'],
		];

		foreach ( [ 'post_date', 'post_date_gmt', 'post_password' ] as $field ) {
			if ( ! empty( $data[ $field ] ) ) {
				$args[ $field ] = $data[ $field ];
			}
		}

		return wp_slash( $args );
	}

	/**
	 * Get the post fields an import changes on an existing event.
	 *
	 * @since 3.18.0
	 *
	 * @param object $old_event  Event before the update.
	 * @param array  $event_data Data used to update the event.
	 *
	 * @return array
	 */
	public function get_update_post_args( $old_event, $event_data ) {

		// Map event data fields to post data fields.
		$field_map = [
			'title'   => 'post_title',
			'content' => 'post_content',
		];

		$post_data = [];

		// Loop through field mappings and update post data if changed.
		foreach ( $field_map as $event_field => $post_field ) {

			// Check if the event field exists and has been modified:
			// 1. The event data field must not be empty.
			// 2. The event data field value must be different from the old event instance value.
			if (
				! empty( $event_data[ $event_field ] )
				&&
				$event_data[ $event_field ] !== $old_event->$event_field
			) {
				// Slashed for the same reason as get_post_args(): wp_update_post() unslashes.
				$post_data[ $post_field ] = wp_slash( $event_data[ $event_field ] );
			}
		}

		return $post_data;
	}
}
