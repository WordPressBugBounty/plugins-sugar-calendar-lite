<?php

namespace Sugar_Calendar\Integrations\Wpml;

use WP_Post;

/**
 * WPML Frontend Integration.
 *
 * Handles frontend display concerns: ensuring translated event pages
 * display the original event data when the translation has no event row.
 *
 * @since 3.12.0
 */
class WpmlFrontend {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		// Legacy event display (the_content injector).
		add_filter( 'sc_event_content_hooks_event', [ $this, 'ensure_event_from_original_post' ], 5 );

		// New frontend loader wrapper.
		add_filter( 'sugar_calendar_frontend_loader_event_object', [ $this, 'ensure_event_from_original_post' ], 5 );
	}

	/**
	 * Ensure the frontend event object resolves to the original (default-language) post
	 * when the current translated post does not have its own event row.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $event Event object (or falsy when not found yet).
	 *
	 * @return mixed Event object, possibly re-fetched using the original post ID.
	 */
	public function ensure_event_from_original_post( $event ) {

		// If we already have a valid event with a real event ID, use it as-is.
		if ( is_object( $event ) && ! empty( $event->id ) && absint( $event->id ) > 0 ) {
			return $event;
		}

		$current_post_id = get_the_ID();

		if ( empty( $current_post_id ) ) {
			return $event;
		}

		// Map to default-language counterpart if needed.
		$original_post_id = Wpml::get_original_post_id( $current_post_id, sugar_calendar_get_event_post_type_id() );

		// If mapping succeeded and differs, try to fetch the canonical event.
		if ( ! empty( $original_post_id ) && (int) $original_post_id !== (int) $current_post_id ) {
			$resolved = sugar_calendar_get_event_by_object( $original_post_id );

			if ( is_object( $resolved ) && ! empty( $resolved->id ) && absint( $resolved->id ) > 0 ) {
				return $resolved;
			}
		}

		// Fall back to the original value.
		return $event;
	}

	/**
	 * Filter posts to keep only originals with translated titles.
	 *
	 * 1. Identifies translation groups using wpml_element_trid.
	 * 2. Keeps only the original (default language) post from each group.
	 * 3. Replaces the original post's title with the translated title if available.
	 *
	 * @since 3.12.0
	 *
	 * @param WP_Post[] $posts     Array of post objects.
	 * @param string    $post_type The post type being filtered.
	 *
	 * @return WP_Post[]
	 */
	public static function filter_posts_keep_originals_with_translated_titles( $posts, $post_type ) {

		// If WPML is not active, return posts as-is.
		if ( ! Wpml::is_wpml_active() ) {
			return $posts;
		}

		global $sitepress;

		if ( empty( $sitepress ) ) {
			return $posts;
		}

		$filtered_posts = [];
		$seen_trids     = [];

		foreach ( $posts as $post ) {

			// Get the translation ID (trid) for this post.
			$trid = apply_filters( 'wpml_element_trid', null, $post->ID, 'post_' . $post_type );

			if ( empty( $trid ) ) {
				// Not a translatable post or WPML filter not available.
				$filtered_posts[] = $post;

				continue;
			}

			// If we've already seen this translation group, skip this duplicate.
			if ( isset( $seen_trids[ $trid ] ) ) {
				continue;
			}

			// Get the original (default language) post ID for this translation group.
			$original_id = Wpml::get_original_post_id( $post->ID, $post_type );

			// Get the translated title for the current language.
			$current_lang_id = apply_filters( 'wpml_object_id', $original_id, $post_type, true );

			// If we have a current language version different from the original, get its translated title.
			if ( ! empty( $current_lang_id ) && (int) $current_lang_id !== (int) $original_id ) {
				$translated_post = get_post( $current_lang_id );

				if (
					$translated_post
					&&
					$translated_post instanceof WP_Post
					&&
					$original_id
				) {
					$original_post = get_post( $original_id );

					if ( $original_post instanceof WP_Post ) {
						// Clone the original post and replace the title with the translation.
						// Cloning avoids mutating the shared object held in WordPress's post cache.
						$original_post             = clone $original_post;
						$original_post->post_title = $translated_post->post_title;
						$filtered_posts[]          = $original_post;
						$seen_trids[ $trid ]       = true;

						continue;
					}
				}
			}

			// Use the original post as-is (either no translation exists or it's already the original).
			if ( $post->ID === $original_id ) {
				$filtered_posts[] = $post;
			} else {
				$original_post = get_post( $original_id );

				if ( $original_post instanceof WP_Post ) {
					$filtered_posts[] = $original_post;
				}
			}

			$seen_trids[ $trid ] = true;
		}

		return $filtered_posts;
	}
}
