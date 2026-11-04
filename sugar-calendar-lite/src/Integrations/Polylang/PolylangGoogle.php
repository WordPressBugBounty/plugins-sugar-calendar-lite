<?php

namespace Sugar_Calendar\Integrations\Polylang;

use WP_Post;

/**
 * Push a translation group to Google from its main post only.
 *
 * The main post is the group's default-language post.
 *
 * @since 3.17.0
 */
class PolylangGoogle {

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_integrations_google_pusher_push_post', [ $this, 'get_push_post' ] );
	}

	/**
	 * Get the post whose Google copy a push updates: the group's main post when sync is on.
	 *
	 * @since 3.17.0
	 *
	 * @param WP_Post|null $push_post Post to push.
	 *
	 * @return WP_Post|null Null when the group has no main post.
	 */
	public function get_push_post( $push_post ) {

		if ( ! $push_post instanceof WP_Post || ! PolylangApi::is_custom_fields_sync_on() ) {
			return $push_post;
		}

		$translations = pll_get_post_translations( $push_post->ID );

		// A post outside any translation group pushes itself.
		if ( ! $this->is_in_translation_group( $translations ) ) {
			return $push_post;
		}

		$main_id = (int) ( $translations[ PolylangApi::get_default_language() ] ?? 0 );

		return $main_id ? get_post( $main_id ) : null;
	}

	/**
	 * Whether a post's translations make a group.
	 *
	 * The group lists the post itself, so a lone post or one with no language has at most one entry.
	 *
	 * @since 3.17.0
	 *
	 * @param int[] $translations Post IDs by language slug, the post's own included.
	 *
	 * @return bool
	 */
	private function is_in_translation_group( $translations ) {

		return count( $translations ) > 1;
	}
}
