<?php

namespace Sugar_Calendar\Features\FeaturedEvents\Frontend;

use Sugar_Calendar\Features\FeaturedEvents\Common\Featured;
use Sugar_Calendar\Features\FeaturedEvents\Common\Markup;

/**
 * Featured Events UI on the public single event page.
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

		add_filter( 'sugar_calendar_frontend_event_badges', [ $this, 'add_badge' ], 10, 2 );
	}

	/**
	 * Add the "Featured" badge to the single event badges.
	 *
	 * Blue, per the single-event design.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $badges Badge markup.
	 * @param object $event  The event object.
	 *
	 * @return array
	 */
	public function add_badge( $badges, $event = null ) {

		if ( ! Featured::is_featured( $event ) ) {
			return $badges;
		}

		$badges[] = [
			'text'  => Markup::get_label(),
			'color' => 'blue',
		];

		return $badges;
	}
}
