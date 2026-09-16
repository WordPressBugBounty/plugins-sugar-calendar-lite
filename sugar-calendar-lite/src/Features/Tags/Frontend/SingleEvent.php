<?php

namespace Sugar_Calendar\Features\Tags\Frontend;

use Sugar_Calendar\Features\Tags\Common\Helpers;

/**
 * Tags UI on the public single event page.
 *
 * @since 3.14.0
 */
class SingleEvent {

	/**
	 * Hooks.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		// Priority 20 keeps the tags after the Featured badge, which is at 10.
		add_filter( 'sugar_calendar_frontend_event_badges', [ $this, 'add_badges' ], 20, 2 );
	}

	/**
	 * Add a badge per tag attached to the event.
	 *
	 * Tooltip uses the tag description when set, otherwise the name.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $badges Badge markup.
	 * @param object $event  The event object.
	 *
	 * @return array
	 */
	public function add_badges( $badges, $event = null ) {

		// Terms attach to the post, not the event row.
		if ( empty( $event->object_id ) ) {
			return $badges;
		}

		$terms = get_the_terms( $event->object_id, Helpers::get_tags_taxonomy_id() );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $badges;
		}

		foreach ( $terms as $term ) {
			$badges[] = [
				'text'  => $term->name,
				'color' => 'grey',
				'title' => $term->description,
			];
		}

		return $badges;
	}
}
