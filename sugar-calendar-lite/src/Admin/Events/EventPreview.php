<?php

namespace Sugar_Calendar\Admin\Events;

use WP_Term;
use Sugar_Calendar\Event;

/**
 * Event "Preview Changes" engine (Lite base).
 *
 * Renders an event's front-end page with the current unsaved editor edits
 * applied, and writes nothing to the database. On the preview submit it
 * snapshots the submitted fields into a short-lived, per-user transient, then
 * overlays them onto the front-end render (title, content, date/time, location,
 * calendar). This replaces WordPress's native preview, which carries only the
 * post title/content and would otherwise show saved values for everything
 * stored in the event row.
 *
 * Booted on every request: the capture runs on admin_init, the overlays run on
 * the front-end single-event render. This is the sole engine — there is no
 * subclass. A feature supports another kind of event (e.g. recurring occurrences)
 * by resolving its own gate through the sugar_calendar_event_preview_transient_key
 * filter and capturing its fields through sugar_calendar_event_preview_payload.
 *
 * @since 3.13.0
 */
class EventPreview {

	/**
	 * Transient key prefix for the normal-event consumer.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const PREFIX = 'sc_event_preview_';

	/**
	 * Per-request payload cache. `false` = unresolved, `null` = no valid preview,
	 * array = the resolved payload.
	 *
	 * @since 3.13.0
	 *
	 * @var array|null|false
	 */
	protected $payload_cache = false;

	/**
	 * The payload store for this consumer.
	 *
	 * @since 3.13.0
	 *
	 * @var EventPreviewPayload
	 */
	protected $payload_store;

	/**
	 * Init the engine.
	 *
	 * @since 3.13.0
	 */
	public function init() {

		$this->payload_store = new EventPreviewPayload( self::PREFIX );

		$this->hooks();
	}

	/**
	 * Register the frontend overlay filters.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_frontend_loader_event_object', [ $this, 'overlay_event_object' ], 20 );
		add_filter( 'the_title', [ $this, 'overlay_title' ], 10, 2 );
		add_filter( 'the_content', [ $this, 'overlay_content' ], 9 );
		add_filter( 'sugar_calendar_helper_get_calendars_of_event', [ $this, 'overlay_calendars' ], 10, 2 );
		add_filter( 'get_sc_event_metadata', [ $this, 'overlay_meta' ], 10, 4 );

		// WordPress post fields with no bucket of their own — featured image, excerpt.
		add_filter( 'sugar_calendar_event_preview_payload', [ EventPreviewPostFields::class, 'capture' ] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		add_filter( 'post_thumbnail_id', [ EventPreviewPostFields::class, 'overlay_thumbnail_id' ], 10, 2 );

		// After core's wp_trim_excerpt (priority 10), so a content-derived excerpt survives an empty capture.
		add_filter( 'get_the_excerpt', [ EventPreviewPostFields::class, 'overlay_excerpt' ], 20, 2 );

		// Stash the submitted edits on a classic "Preview Changes" submit.
		add_action( 'admin_init', [ $this, 'maybe_stash_preview' ] );

		// A re-opened editor supersedes any snapshot from earlier in the session.
		add_action( 'load-post.php', [ $this, 'clear_stale_snapshot' ] );

		// The block editor saves over REST and never reloads post.php.
		add_action( 'save_post', [ $this, 'clear_snapshot_on_save' ] );
	}

	/**
	 * Discard this post's preview snapshot when its edit screen loads.
	 *
	 * Re-entering the editor means any snapshot taken earlier in the session is
	 * superseded; because WordPress redirects back to the edit screen after a
	 * classic save, this also covers "the edits were saved, so the snapshot is
	 * stale".
	 *
	 * @since 3.13.0
	 */
	public function clear_stale_snapshot() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen routing; the key embeds the current user, so this can only discard the user's own throwaway snapshot.
		$action  = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $action !== 'edit' || empty( $post_id ) ) {
			return;
		}

		if ( ! post_type_supports( get_post_type( $post_id ), 'events' ) ) {
			return;
		}

		$this->payload_store->delete( $post_id );
	}

	/**
	 * Discard this post's preview snapshot when it is saved.
	 *
	 * The block editor saves over the REST API and never reloads post.php, so the
	 * load-post.php handler alone would leave a saved-over snapshot in place. This
	 * cannot discard the snapshot it just wrote — maybe_stash_preview() exits before
	 * WordPress reaches edit_post().
	 *
	 * @since 3.13.0
	 *
	 * @param int $post_id The post being saved.
	 */
	public function clear_snapshot_on_save( $post_id ) {

		if ( ! post_type_supports( get_post_type( $post_id ), 'events' ) ) {
			return;
		}

		$this->payload_store->delete( $post_id );
	}

	/**
	 * On a classic "Preview Changes" submit for a real event post, stash the
	 * submitted editor fields, then redirect to the front-end preview URL and exit,
	 * preempting WordPress's native preview.
	 *
	 * Non-destructive for every post status: the redirect fires before WP's
	 * edit_post()/post_preview() runs, so nothing is written (no draft persist, no
	 * autosave revision).
	 *
	 * @since 3.13.0
	 */
	public function maybe_stash_preview() {

		// Cheap discriminators first, before any nonce work.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST['wp-preview'] ) || $_POST['wp-preview'] !== 'dopreview' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;

		// Only a real sc_event post is stashed here. A submit whose post id has
		// no real post row belongs to another consumer's write path.
		if ( empty( $post_id ) || get_post_type( $post_id ) !== 'sc_event' ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return;
		}

		// store() verifies the edit nonce itself (admin_init fires before post.php does).
		$this->payload_store->store( $post_id, $post, 'update-post_' . $post_id );

		// Own the whole preview: redirect to the event's front-end preview URL carrying a
		// self-minted WP preview nonce, then exit before WP's edit_post()/post_preview()
		// runs. Renders published AND unpublished events, and persists nothing.
		wp_safe_redirect( $this->get_preview_redirect_url( $post_id ) );
		exit;
	}

	/**
	 * Front-end preview URL for a normal event, carrying a self-minted WP preview
	 * nonce so _show_post_preview() authorizes the render (published or draft).
	 *
	 * The captured featured image rides the URL as `_thumbnail_id`, which is what
	 * core's _wp_preview_post_thumbnail_filter reads — it treats any value <= 0 as
	 * "no image", so a cleared image is sent as -1 exactly as core's own
	 * post_preview() sends it.
	 *
	 * @since 3.13.0
	 *
	 * @param int $post_id The event post id.
	 *
	 * @return string
	 */
	protected function get_preview_redirect_url( $post_id ) {

		$payload      = $this->payload_store->read( $post_id );
		$thumbnail_id = isset( $payload['post']['thumbnail_id'] ) ? (int) $payload['post']['thumbnail_id'] : 0;

		return get_preview_post_link(
			$post_id,
			[
				'preview_id'    => $post_id,
				'preview_nonce' => wp_create_nonce( 'post_preview_' . $post_id ),
				'_thumbnail_id' => $thumbnail_id > 0 ? $thumbnail_id : -1,
			]
		);
	}

	/**
	 * Overlay the edited date/time onto the Event object (row columns).
	 *
	 * @since 3.13.0
	 *
	 * @param Event $event Event object.
	 *
	 * @return Event
	 */
	public function overlay_event_object( $event ) {

		$payload = $this->get_payload();

		if ( empty( $payload ) || empty( $event ) || (int) $event->object_id !== (int) $payload['overlay_post_id'] ) {
			return $event;
		}

		if ( empty( $payload['event'] ) || ! is_array( $payload['event'] ) ) {
			return $event;
		}

		foreach ( $payload['event'] as $key => $value ) {

			if ( $key === 'all_day' ) {
				$event->all_day = ! empty( $value );

				continue;
			}

			$event->{$key} = $value;
		}

		// Refresh the cached datetime objects the renderers read from.
		if ( method_exists( $event, 'set_datetime_objects' ) ) {
			$event->set_datetime_objects();
		}

		return $event;
	}

	/**
	 * Overlay the edited post title.
	 *
	 * @since 3.13.0
	 *
	 * @param string $title   The post title.
	 * @param int    $post_id The post id.
	 *
	 * @return string
	 */
	public function overlay_title( $title, $post_id = 0 ) {

		$payload = $this->get_payload();

		if ( empty( $payload ) || (int) $post_id !== (int) $payload['overlay_post_id'] ) {
			return $title;
		}

		return isset( $payload['post']['title'] ) ? $payload['post']['title'] : $title;
	}

	/**
	 * Overlay the edited post content on the main event post.
	 *
	 * @since 3.13.0
	 *
	 * @param string $content The post content.
	 *
	 * @return string
	 */
	public function overlay_content( $content ) {

		$payload = $this->get_payload();

		if ( empty( $payload ) || (int) get_the_ID() !== (int) $payload['overlay_post_id'] ) {
			return $content;
		}

		return isset( $payload['post']['content'] ) ? $payload['post']['content'] : $content;
	}

	/**
	 * Overlay the edited calendar terms (sc_event_category) for the previewed event.
	 *
	 * Filters Helper::get_calendars_of_event(); returns the selected terms resolved
	 * to WP_Term objects (an empty array honors a cleared selection).
	 *
	 * @since 3.13.0
	 *
	 * @param WP_Term[] $calendars The calendars of the event.
	 * @param Event     $event     The event object.
	 *
	 * @return WP_Term[]
	 */
	public function overlay_calendars( $calendars, $event ) {

		$payload = $this->get_payload();

		if ( empty( $payload ) || empty( $event ) || (int) $event->object_id !== (int) $payload['overlay_post_id'] ) {
			return $calendars;
		}

		if ( ! isset( $payload['terms']['sc_event_category'] ) ) {
			return $calendars;
		}

		$term_ids = array_map( 'absint', $payload['terms']['sc_event_category'] );

		// Empty selection is authoritative — the editor cleared every calendar.
		if ( empty( $term_ids ) ) {
			return [];
		}

		// One query, not one per id. `orderby => include` preserves the editor's
		// selection order; `hide_empty => false` keeps calendars with no events.
		$overlaid = get_terms(
			[
				'taxonomy'   => 'sc_event_category',
				'include'    => $term_ids,
				'orderby'    => 'include',
				'hide_empty' => false,
			]
		);

		return is_array( $overlaid ) ? $overlaid : [];
	}

	/**
	 * Overlay event-meta reads (get_event_meta) for the previewed event.
	 *
	 * Short-circuits WordPress's get_sc_event_metadata for keys in the `meta`
	 * bucket, matched to the previewed event id. Every other key/event returns the
	 * incoming $value (null) so the normal DB read proceeds. The value comes from
	 * the payload array — never from get_event_meta — so there is no recursion.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed  $value     Short-circuit value (null = read normally).
	 * @param int    $object_id The SC event id being read.
	 * @param string $meta_key  The meta key being read.
	 * @param bool   $single    Whether a single value was requested.
	 *
	 * @return mixed
	 */
	public function overlay_meta( $value, $object_id, $meta_key, $single ) {

		$payload = $this->get_payload();

		if (
			empty( $payload['meta'] )
			|| empty( $payload['overlay_event_id'] )
			|| (int) $object_id !== (int) $payload['overlay_event_id']
			|| ! array_key_exists( $meta_key, $payload['meta'] )
		) {
			return $value;
		}

		$overlaid = $payload['meta'][ $meta_key ];

		return $single ? $overlaid : [ $overlaid ];
	}

	/**
	 * Resolve + validate the preview payload for the current request.
	 *
	 * Shared gate: logged in (cheap) → resolve_preview_context() (each consumer's
	 * param + nonce check) → transient read → edit_post capability. Memoized.
	 *
	 * @since 3.13.0
	 *
	 * @return array|null
	 */
	public function get_payload() {

		if ( $this->payload_cache !== false ) {
			return $this->payload_cache;
		}

		$this->payload_cache = null;

		if ( ! is_user_logged_in() ) {
			return null;
		}

		$transient_key = $this->resolve_preview_context();

		if ( empty( $transient_key ) ) {
			return null;
		}

		$data = $this->payload_store->read_by_key( $transient_key );

		if ( empty( $data['overlay_post_id'] ) ) {
			return null;
		}

		if ( ! current_user_can( 'edit_post', $data['overlay_post_id'] ) ) {
			return null;
		}

		$this->payload_cache = $data;

		return $this->payload_cache;
	}

	/**
	 * Resolve the current request's preview transient key, or null when this is
	 * not a valid preview request for this consumer.
	 *
	 * Base (normal-event) consumer: the WP-native preview URL carrying ?preview_id
	 * + ?preview_nonce. Because maybe_stash_preview() mints that nonce and redirects
	 * on every "Preview Changes" submit, this is the only entry point — there is no
	 * nonce-less bare path.
	 *
	 * @since 3.13.0
	 *
	 * @return string|null
	 */
	private function resolve_preview_context() {

		/**
		 * Filters the preview transient key so a feature can resolve an alternate
		 * gate (e.g. recurring occurrences) before the base normal-event gate.
		 *
		 * A feature MUST verify its own nonce before returning a key; return the
		 * incoming null to fall through to the normal-event gate below. Either way,
		 * get_payload() still applies the edit_post capability check after the read.
		 *
		 * @since 3.13.0
		 *
		 * @param string|null $transient_key The resolved transient key, or null.
		 */
		$transient_key = apply_filters( 'sugar_calendar_event_preview_transient_key', null ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( is_string( $transient_key ) && $transient_key !== '' ) {
			return $transient_key;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce is verified below.
		$preview_id = isset( $_GET['preview_id'] ) ? absint( $_GET['preview_id'] ) : 0;
		$nonce      = isset( $_GET['preview_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['preview_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( empty( $preview_id ) || empty( $nonce ) ) {
			return null;
		}

		return wp_verify_nonce( $nonce, 'post_preview_' . $preview_id )
			? $this->payload_store->key_for( $preview_id )
			: null;
	}
}
