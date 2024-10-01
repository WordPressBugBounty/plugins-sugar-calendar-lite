<?php

namespace Sugar_Calendar\Admin\Events;

/**
 * Events class.
 *
 * Handles anything event-related in the admin-side.
 *
 * @since 3.2.0
 */
class Events {

	/**
	 * Hooks.
	 *
	 * @since 3.2.0
	 */
	public function hooks() {

		add_action( 'save_post_sc_event', [ $this, 'save' ], 10, 2 );
	}

	/**
	 * Fires once an event has been saved/updated.
	 *
	 * @since 3.2.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save( $post_id, $post ) {

		if ( $post->post_type !== 'sc_event' || $post->post_status !== 'publish' ) {
			return;
		}

		$calendars = wp_get_post_terms(
			$post_id,
			'sc_event_category',
			[
				'number' => 1,
				'fields' => 'ids',
			]
		);

		if ( ! empty( $calendars ) ) {
			return;
		}

		// Get the default calendar.
		$default_calendar = absint( sugar_calendar_get_default_calendar() );

		if ( empty( $default_calendar ) ) {
			return;
		}

		wp_set_post_terms(
			$post_id,
			[ $default_calendar ],
			'sc_event_category'
		);
	}
}
