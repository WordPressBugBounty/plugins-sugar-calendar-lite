<?php

namespace Sugar_Calendar\Integrations;

use WP_Post;

/**
 * Shared checks for "is this a genuine event-editor save" and "which SC event is this post".
 *
 * Both the meeting lifecycle and the Google push react to save_post; keeping the
 * nonce/capability gate in one place means a fix lands in both.
 *
 * @since 3.16.0
 */
class EventSaveGuard {

	/**
	 * Whether this save is a genuine SCE event-editor save worth reacting to.
	 *
	 * Mirrors Metaboxes::can_save_meta_box(): not autosaves, revisions, bulk edits, or programmatic saves.
	 *
	 * @since 3.16.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return bool
	 */
	public static function is_editor_save( $post_id, $post ): bool {

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $_POST['sc_mb_nonce'] ) || ! wp_verify_nonce( $_POST['sc_mb_nonce'], 'sugar_calendar_nonce' ) ) {
			return false;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_doing_ajax() || isset( $_REQUEST['bulk_edit'] ) ) {
			return false;
		}
		// phpcs:enable

		if ( ! is_a( $post, 'WP_Post' ) || ! post_type_supports( $post->post_type, 'events' ) ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		$pto = get_post_type_object( $post->post_type );

		return ! empty( $pto ) && current_user_can( $pto->cap->edit_post, $post_id );
	}

	/**
	 * Resolve the SC event for a WP post, subtype-aware (sc_event and sc_recurring_event).
	 *
	 * @since 3.16.0
	 *
	 * @param int $post_id WP post id.
	 *
	 * @return object SCE Event object (may be empty if no row exists).
	 */
	public static function resolve_event_for_post( $post_id ) {

		return sugar_calendar_get_event_by_object(
			$post_id,
			'post',
			[ 'object_subtype' => get_post_type( $post_id ) ]
		);
	}
}
