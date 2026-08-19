<?php

namespace Sugar_Calendar\Admin\Events;

use WP_Post;

/**
 * Event preview — the WordPress post fields the engine's own buckets don't carry:
 * the featured image and the excerpt.
 *
 * Both are captured from the preview submit into the payload's `post` bucket. The
 * excerpt is overlaid on `get_the_excerpt`. The featured image is overlaid on
 * `post_thumbnail_id`, which is what makes an occurrence preview work — that URL
 * carries `sc_preview_occ` rather than `preview_id`, so WordPress's own
 * `_wp_preview_post_thumbnail_filter` never fires for it. Normal events get the
 * captured id appended to their preview URL instead, so core applies it there too
 * (including raw `_thumbnail_id` meta reads this filter cannot see).
 *
 * @since 3.13.0
 */
class EventPreviewPostFields {

	/**
	 * Capture the submitted featured image and excerpt into the preview payload.
	 *
	 * The capture caller (EventPreviewPayload::store) has already verified the
	 * update-post nonce and the edit_post capability before this fires.
	 *
	 * @since 3.13.0
	 *
	 * @param array $payload The event preview payload.
	 *
	 * @return array
	 */
	public static function capture( $payload ) {

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by the capture caller; the int cast is the sanitizer here.
		// Core's featured-image metabox submits -1 for "no image", which absint() would read as attachment 1.
		$thumbnail_id = isset( $_POST['_thumbnail_id'] ) ? (int) wp_unslash( $_POST['_thumbnail_id'] ) : 0;

		$payload['post']['thumbnail_id'] = max( 0, $thumbnail_id );
		$payload['post']['excerpt']      = isset( $_POST['excerpt'] ) ? wp_kses_post( wp_unslash( $_POST['excerpt'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return $payload;
	}

	/**
	 * Overlay the previewed featured image.
	 *
	 * @since 3.13.0
	 *
	 * @param int|false        $thumbnail_id The saved thumbnail id.
	 * @param int|WP_Post|null $post         The post the thumbnail was read for.
	 *
	 * @return int|false
	 */
	public static function overlay_thumbnail_id( $thumbnail_id, $post = null ) {

		$post = get_post( $post );

		if ( ! $post instanceof WP_Post ) {
			return $thumbnail_id;
		}

		$payload = self::payload();

		if (
			empty( $payload['overlay_post_id'] )
			|| (int) $post->ID !== (int) $payload['overlay_post_id']
			|| ! isset( $payload['post']['thumbnail_id'] )
		) {
			return $thumbnail_id;
		}

		// A cleared image is authoritative — the payload carries the full editor state.
		return (int) $payload['post']['thumbnail_id'];
	}

	/**
	 * Overlay the previewed excerpt.
	 *
	 * An empty captured excerpt returns the incoming value untouched: core's
	 * wp_trim_excerpt has already derived one from the content, and blanking that
	 * would make the preview differ from the real page for every event without a
	 * manual excerpt.
	 *
	 * @since 3.13.0
	 *
	 * @param string       $excerpt The post excerpt.
	 * @param WP_Post|null $post    The post object.
	 *
	 * @return string
	 */
	public static function overlay_excerpt( $excerpt, $post = null ) {

		if ( ! $post instanceof WP_Post ) {
			return $excerpt;
		}

		$payload = self::payload();

		if (
			empty( $payload['overlay_post_id'] )
			|| (int) $post->ID !== (int) $payload['overlay_post_id']
		) {
			return $excerpt;
		}

		$overlaid = isset( $payload['post']['excerpt'] ) ? (string) $payload['post']['excerpt'] : '';

		return $overlaid === '' ? $excerpt : $overlaid;
	}

	/**
	 * The active preview payload, or an empty array.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private static function payload() {

		$payload = sugar_calendar()->get_event_preview()->get_payload();

		return empty( $payload ) ? [] : $payload;
	}
}
