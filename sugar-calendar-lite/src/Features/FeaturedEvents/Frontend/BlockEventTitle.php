<?php

namespace Sugar_Calendar\Features\FeaturedEvents\Frontend;

use Sugar_Calendar\Features\FeaturedEvents\Common\Featured;
use Sugar_Calendar\Features\FeaturedEvents\Common\Markup;

/**
 * Featured Events UI on the block event titles.
 *
 * @since 3.14.0
 */
class BlockEventTitle {

	/**
	 * Hooks.
	 *
	 * Not admin-gated: the blocks re-render over admin-ajax.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_helper_get_event_title_html', [ $this, 'add_star' ], 10, 3 );
	}

	/**
	 * Prepend the featured star to a block event title.
	 *
	 * @since 3.14.0
	 *
	 * @param string $html  Title markup.
	 * @param object $event Event object.
	 * @param string $color Accent color of the surface, when it has one.
	 *
	 * @return string
	 */
	public function add_star( $html, $event = null, $color = '' ) {

		if ( ! Featured::is_featured( $event ) ) {
			return $html;
		}

		return Markup::get_star( 'block', $color ) . $html;
	}
}
