<?php

namespace Sugar_Calendar\Admin\Events;

use WP_Post;
use Sugar_Calendar\Admin\Events\Metaboxes\EventDateTimeRequest;

/**
 * The event preview payload's whole lifecycle: how it is built from a request,
 * how it is keyed, how it is persisted, and how it is read back.
 *
 * One instance per consumer — the transient prefix is constructor state, so the
 * normal-event store and the recurring-occurrence store can never collide on the
 * same target id.
 *
 * @since 3.13.0
 */
final class EventPreviewPayload {

	/**
	 * Snapshot lifetime. Short on purpose — a snapshot outliving the editing
	 * session would replay stale edits on a reloaded preview URL.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	const TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Consumer-scoped transient prefix.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Class constructor.
	 *
	 * @since 3.13.0
	 *
	 * @param string $prefix Consumer-scoped transient prefix.
	 */
	public function __construct( $prefix ) {

		$this->prefix = (string) $prefix;
	}

	/**
	 * Transient key for a target id + user.
	 *
	 * @since 3.13.0
	 *
	 * @param int      $target_id Target id (post id or occurrence id).
	 * @param int|null $user_id   WP user id. Defaults to the current user.
	 *
	 * @return string
	 */
	public function key_for( $target_id, $user_id = null ) {

		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		return $this->prefix . (int) $target_id . '_' . (int) $user_id;
	}

	/**
	 * Build the payload from the submitted editor fields and persist it, then
	 * clear the orphaned classic autosave backup.
	 *
	 * Verifies the edit nonce itself: the contract is structural, not a docblock.
	 * The caller must still have checked current_user_can( 'edit_post', … ).
	 *
	 * @since 3.13.0
	 *
	 * @param int     $target_id    Target id for the transient key.
	 * @param WP_Post $post         The post whose front-end page renders this preview.
	 * @param string  $nonce_action The edit nonce action to verify before writing.
	 * @param array   $overrides    Payload keys to override (e.g. overlay_event_id).
	 */
	public function store( $target_id, WP_Post $post, $nonce_action, array $overrides = [] ) {

		check_admin_referer( $nonce_action );

		$payload = $this->build_from_request( $post );

		if ( ! empty( $overrides ) ) {
			$payload = array_merge( $payload, $overrides );
		}

		set_transient( $this->key_for( $target_id ), $payload, self::TTL );

		$this->clear_local_autosave_backup( $post->ID );
	}

	/**
	 * Read the snapshot for a target id, or null when there is none.
	 *
	 * @since 3.13.0
	 *
	 * @param int $target_id Target id for the transient key.
	 *
	 * @return array|null
	 */
	public function read( $target_id ) {

		return $this->read_by_key( $this->key_for( $target_id ) );
	}

	/**
	 * Read the snapshot for an already-resolved transient key.
	 *
	 * The gate seam (sugar_calendar_event_preview_transient_key) hands back a key
	 * rather than a target id, so the engine reads through this entry point.
	 *
	 * @since 3.13.0
	 *
	 * @param string $key The transient key.
	 *
	 * @return array|null
	 */
	public function read_by_key( $key ) {

		$data = get_transient( $key );

		return empty( $data ) || ! is_array( $data ) ? null : $data;
	}

	/**
	 * Discard the snapshot for a target id.
	 *
	 * @since 3.13.0
	 *
	 * @param int $target_id Target id for the transient key.
	 */
	public function delete( $target_id ) {

		delete_transient( $this->key_for( $target_id ) );
	}

	/**
	 * Compose the typed buckets and apply the feature capture seam.
	 *
	 * @since 3.13.0
	 *
	 * @param WP_Post $post The post whose front-end page renders this preview.
	 *
	 * @return array
	 */
	private function build_from_request( WP_Post $post ) {

		$event = sugar_calendar_get_event_by_object( $post->ID );

		$payload = [
			'overlay_post_id'  => (int) $post->ID,
			'overlay_event_id' => ! empty( $event->id ) ? (int) $event->id : 0,
			'post'             => $this->post_bucket( $post ),
			'event'            => EventDateTimeRequest::from_request(),
			'terms'            => $this->terms_bucket(),
			'meta'             => [],
		];

		/**
		 * Filters the event preview payload so features can capture their own
		 * editor fields (e.g. a feature adds its meta key to the `meta` bucket).
		 *
		 * @since 3.13.0
		 *
		 * @param array   $payload The preview payload (buckets: post, event, terms, meta).
		 * @param WP_Post $post    The event post being previewed.
		 */
		$payload = apply_filters( 'sugar_calendar_event_preview_payload', $payload, $post ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return is_array( $payload ) ? $payload : [];
	}

	/**
	 * The `post` bucket — WordPress post fields.
	 *
	 * @since 3.13.0
	 *
	 * @param WP_Post $post The post being previewed (title fallback).
	 *
	 * @return array
	 */
	private function post_bucket( WP_Post $post ) {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in store().
		$title = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : $post->post_title;

		// SCE's classic Details editor posts the description as `post_content`;
		// fall back to core's `content` for safety.
		if ( isset( $_POST['post_content'] ) ) {
			$content = wp_kses_post( wp_unslash( $_POST['post_content'] ) );
		} elseif ( isset( $_POST['content'] ) ) {
			$content = wp_kses_post( wp_unslash( $_POST['content'] ) );
		} else {
			$content = '';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return [
			'title'   => $title,
			'content' => $content,
		];
	}

	/**
	 * The `terms` bucket — event taxonomy terms (calendar).
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function terms_bucket() {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in store().
		$term_ids = isset( $_POST['tax_input']['sc_event_category'] )
			? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['tax_input']['sc_event_category'] ) ) ) )
			: [];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return [
			'sc_event_category' => $term_ids,
		];
	}

	/**
	 * Discard WordPress's client-side local autosave backup for a post.
	 *
	 * A non-destructive preview never saves, so core's sessionStorage backup would
	 * otherwise be left orphaned and surface a "Restore the backup" notice. Setting
	 * the same "saved" cookie core sets after a real save makes WordPress's own
	 * checkPost() delete that backup.
	 *
	 * @since 3.13.0
	 *
	 * @param int $post_id The post id the classic editor keyed the backup on.
	 */
	private function clear_local_autosave_backup( $post_id ) {

		$saving_post = isset( $_COOKIE['wp-saving-post'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['wp-saving-post'] ) ) : '';

		if ( $saving_post === $post_id . '-check' ) {
			setcookie( 'wp-saving-post', $post_id . '-saved', time() + DAY_IN_SECONDS, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, is_ssl() );
		}
	}
}
