<?php

namespace Sugar_Calendar\Integrations;

use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;
use Sugar_Calendar\Integrations\OAuthRelay\Webhooks\IncomingWebhookHandler;

/**
 * Event meeting lifecycle dispatcher (Segment 2b: full keep-in-sync).
 *
 * Meeting *creation* is now explicit: the "Create Meeting" button triggers
 * CreateMeetingAjax, which calls the public provision_meeting() method
 * directly. sync() is NOT responsible for creating meetings.
 *
 * On generic save_post:20 (AFTER Metaboxes::save() at save_post:10, which
 * writes the event row + `online_provider` via the sugar_calendar_event_to_save
 * filter), reconciles the desired provider (`online_provider`) against the
 * provisioned one (`meeting_provider` + `meeting_id`):
 *
 *   update   — same provider, meeting exists; fingerprint-gated PATCH.
 *   switch   — provider changed; delete old + create new via provision_meeting()
 *              (dormant until a 2nd provider exists).
 *   removal  — Online -> None; delete + clear meta.
 *
 * On before_delete_post / wp_trash_post, cleanup() deletes the meeting. SCE
 * deletes the event row on `deleted_post` (after before_delete_post), so the
 * meeting meta is still readable when cleanup() runs.
 *
 * A Zoom failure NEVER blocks the save/trash/delete — every path is wrapped in
 * try/catch and logs `[SC Zoom]`. Guarded by IncomingWebhookHandler::is_processing()
 * (3a) so a provider-originated change never bounces back to the provider.
 *
 * @since 3.12.0
 */
class EventMeetingManager {

	/**
	 * Event-meta key holding the "meeting deleted externally" breadcrumb.
	 *
	 * Written by the provider webhook handler when a meeting is deleted at the
	 * provider, read by the editor notice, and cleared here on a successful
	 * (re)provision. Lives on this provider-agnostic lifecycle class so neither
	 * the provider handler nor the admin notice owns the cross-cutting key.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const REMOVED_NOTICE_META_KEY = 'online_meeting_removed_notice';

	/**
	 * Capability registry.
	 *
	 * @since 3.12.0
	 *
	 * @var IntegrationCapabilityRegistry
	 */
	private $registry;

	/**
	 * Credits service (gates meeting creation when out of credits).
	 *
	 * @since 3.12.0
	 *
	 * @var CreditsService
	 */
	private $credits;

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 *
	 * @param IntegrationCapabilityRegistry $registry Capability registry.
	 * @param CreditsService                $credits  Credits service.
	 */
	public function __construct( IntegrationCapabilityRegistry $registry, CreditsService $credits ) {

		$this->registry = $registry;
		$this->credits  = $credits;
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		add_action( 'save_post', [ $this, 'sync' ], 20, 2 );
		add_action( 'before_delete_post', [ $this, 'cleanup' ], 10, 1 );
		add_action( 'wp_trash_post', [ $this, 'cleanup' ], 10, 1 );
	}

	/**
	 * Reconcile the event's meeting against the selected provider on save.
	 *
	 * @since 3.12.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function sync( $post_id, $post ) {

		// A change that originated at the provider (webhook ingest) must not
		// bounce back to the provider as another API call.
		if ( IncomingWebhookHandler::is_processing() ) {
			return;
		}

		if ( ! $this->can_sync( $post_id, $post ) ) {
			return;
		}

		try {
			$event = sugar_calendar_get_event_by_object( $post_id );

			if ( empty( $event->id ) ) {
				return;
			}

			// Recurring events do not carry a single-meeting lifecycle yet (deferred).
			if ( ! empty( $event->recurrence ) ) {
				return;
			}

			$desired  = (string) get_event_meta( $event->id, 'online_provider', true );
			$existing = $this->get_provisioned_provider( $event );
			$current  = ( $desired !== '' )
				? $this->registry->find( MeetingProviderInterface::class, $desired )
				: null;

			// removal: Online -> None.
			if ( $existing && ! $current ) {
				$this->remove( $existing, $event );

				return;
			}

			// switch: provider changed (dormant until a 2nd provider exists).
			if ( $existing && $current && $existing !== $current ) {
				$this->remove( $existing, $event );
				$this->create( $current, $event );

				return;
			}

			// update: same provider, meeting exists — fingerprint-gated PATCH.
			if ( $existing && $existing === $current ) {
				$this->update( $current, $event );
			}
		} catch ( \Throwable $e ) {
			error_log( '[SC Zoom] EventMeetingManager::sync failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Delete the provider meeting when the event is trashed or deleted.
	 *
	 * @since 3.12.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function cleanup( $post_id ) {

		if ( IncomingWebhookHandler::is_processing() ) {
			return;
		}

		if ( get_post_type( $post_id ) !== sugar_calendar_get_event_post_type_id() ) {
			return;
		}

		try {
			$event = sugar_calendar_get_event_by_object( $post_id );

			if ( empty( $event->id ) ) {
				return;
			}

			$provider = $this->get_provisioned_provider( $event );

			if ( $provider ) {
				$this->remove( $provider, $event );
			}
		} catch ( \Throwable $e ) {
			error_log( '[SC Zoom] EventMeetingManager::cleanup failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Resolve the provider currently provisioned for an event: meeting_provider
	 * + meeting_id are both set and the slug resolves via the capability
	 * registry.
	 *
	 * Shared by sync() (the "existing" side of the reconcile) and cleanup() —
	 * both independently re-read the same two meta keys before this extraction.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return MeetingProviderInterface|null
	 */
	private function get_provisioned_provider( $event ): ?MeetingProviderInterface {

		$provisioned = (string) get_event_meta( $event->id, 'meeting_provider', true );
		$meeting_id  = (string) get_event_meta( $event->id, 'meeting_id', true );

		if ( $provisioned === '' || $meeting_id === '' ) {
			return null;
		}

		return $this->registry->find( MeetingProviderInterface::class, $provisioned );
	}

	/**
	 * Provision a NEW meeting and persist its meta + fingerprint.
	 *
	 * Public so the explicit Create-Meeting AJAX endpoint can drive the exact
	 * same path the lifecycle dispatcher uses. Returns true on success, or a
	 * WP_Error (out-of-credits, or the provider's create failure) so the caller
	 * can decide whether to log it (sync) or surface it to the user (AJAX).
	 *
	 * @since 3.12.0
	 *
	 * @param MeetingProviderInterface $provider Resolved provider.
	 * @param object                   $event    SCE Event object.
	 *
	 * @return true|\WP_Error
	 */
	public function provision_meeting( MeetingProviderInterface $provider, $event ) {

		// Create-only credits gate (updates/deletes are never gated).
		if ( $this->credits->is_out_of_credits() ) {
			return new \WP_Error( 'out_of_credits', esc_html__( "You're out of integration credits.", 'sugar-calendar-lite' ) );
		}

		$result = $provider->create_meeting( $event );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->write_meeting_meta( $event->id, $result );
		update_event_meta( $event->id, 'meeting_sync_hash', $this->fingerprint( $event ) );

		// A successful (re)provision clears any "meeting deleted externally"
		// breadcrumb so a stale editor notice does not linger.
		delete_event_meta( $event->id, self::REMOVED_NOTICE_META_KEY );

		return true;
	}

	/**
	 * Explicitly detach the meeting from an event: delete it at the provider,
	 * clear the meeting meta, and revert `online_provider` to None.
	 *
	 * Public so the "Remove" AJAX endpoint drives the exact same removal path the
	 * on-save `sync()` reconcile uses (private `remove()`), plus the
	 * `online_provider` clear that an on-save removal gets from the user setting
	 * the dropdown to None. Resolves the provisioned provider itself, and stays
	 * orphan-tolerant on both axes: an unresolvable provider (deregistered) still
	 * gets the local meta cleared, and a provider delete failure still clears the
	 * local meta (matches `remove()` / the disconnect philosophy). The event
	 * always ends in "no meeting here."
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return void
	 */
	public function detach_meeting( $event ) {

		$provider = $this->get_provisioned_provider( $event );

		if ( $provider ) {
			// Deletes the remote meeting + clears the provider meta keys + sync hash.
			$this->remove( $provider, $event );
		} else {
			// Orphaned meeting (provider deregistered): still honor the intent by
			// clearing the raw meeting meta so no stale card renders.
			foreach ( [ 'meeting_provider', 'meeting_id', 'join_url', 'meeting_password', 'meeting_settings', 'meeting_sync_hash' ] as $key ) {
				delete_event_meta( $event->id, $key );
			}
		}

		delete_event_meta( $event->id, 'online_provider' );
	}

	/**
	 * Internal create used by the switch branch — provisions and logs on failure.
	 *
	 * @since 3.12.0
	 *
	 * @param MeetingProviderInterface $provider Resolved provider.
	 * @param object                   $event    SCE Event object.
	 *
	 * @return void
	 */
	private function create( MeetingProviderInterface $provider, $event ) {

		$result = $this->provision_meeting( $provider, $event );

		if ( is_wp_error( $result ) ) {
			error_log( '[SC Zoom] create_meeting skipped/failed: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Fingerprint-gated update: PATCH only when meeting-relevant fields changed.
	 *
	 * @since 3.12.0
	 *
	 * @param MeetingProviderInterface $provider Resolved provider.
	 * @param object                   $event    SCE Event object.
	 *
	 * @return void
	 */
	private function update( MeetingProviderInterface $provider, $event ) {

		$current_hash = $this->fingerprint( $event );
		$stored_hash  = (string) get_event_meta( $event->id, 'meeting_sync_hash', true );

		// No meeting-relevant change — skip the relay call entirely.
		if ( $current_hash === $stored_hash ) {
			return;
		}

		$result = $provider->update_meeting( $event );

		if ( is_wp_error( $result ) ) {
			error_log( '[SC Zoom] update_meeting failed: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// Leave the stored hash untouched so the next save retries.
			return;
		}

		$this->write_meeting_meta( $event->id, $result );
		update_event_meta( $event->id, 'meeting_sync_hash', $current_hash );
	}

	/**
	 * Delete the provider meeting and clear local meta (orphan-tolerant).
	 *
	 * @since 3.12.0
	 *
	 * @param MeetingProviderInterface $provider Resolved provider.
	 * @param object                   $event    SCE Event object.
	 *
	 * @return void
	 */
	private function remove( MeetingProviderInterface $provider, $event ) {

		$result = $provider->delete_meeting( $event );

		if ( is_wp_error( $result ) ) {
			error_log( '[SC Zoom] delete_meeting failed: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Clear local meta regardless of the delete result: the user's intent is
		// "no meeting here"; an orphaned remote meeting is harmless (matches the
		// disconnect orphan-meta philosophy).
		foreach ( $provider->get_meeting_meta_keys() as $key ) {
			delete_event_meta( $event->id, $key );
		}

		delete_event_meta( $event->id, 'meeting_sync_hash' );
	}

	/**
	 * Persist the meeting metadata returned by a provider create/update.
	 *
	 * @since 3.12.0
	 *
	 * @param int   $event_id Event ID.
	 * @param array $result   Provider result array.
	 *
	 * @return void
	 */
	private function write_meeting_meta( $event_id, array $result ) {

		// Guard every key: this manager is provider-agnostic, so a provider that
		// returns a partial array must not raise an undefined-index notice here.
		update_event_meta( $event_id, 'meeting_provider', $result['provider'] ?? '' );
		update_event_meta( $event_id, 'meeting_id', $result['meeting_id'] ?? '' );
		update_event_meta( $event_id, 'join_url', $result['join_url'] ?? '' );
		update_event_meta( $event_id, 'meeting_password', $result['password'] ?? '' );
		update_event_meta( $event_id, 'meeting_settings', $result['meeting_settings'] ?? '' );
	}

	/**
	 * Provider-agnostic fingerprint of the meeting-relevant event fields.
	 *
	 * Mirrors the inputs build_meeting_data() consumes (minus the static
	 * settings). A change here is what gates the update PATCH.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string
	 */
	private function fingerprint( $event ): string {

		return md5(
			implode(
				'|',
				[
					(string) $event->title,
					(string) $event->start,
					(string) $event->end,
					(string) $event->start_tz,
				]
			)
		);
	}

	/**
	 * Whether this save is a genuine SCE event-editor save we should react to.
	 *
	 * Mirrors Metaboxes::can_save_meta_box() so the manager only fires for the
	 * same editor saves that wrote the online_provider meta — not autosaves,
	 * revisions, bulk edits, or programmatic saves.
	 *
	 * @since 3.12.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return bool
	 */
	private function can_sync( $post_id, $post ): bool {

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $_POST['sc_mb_nonce'] ) || ! wp_verify_nonce( $_POST['sc_mb_nonce'], 'sugar_calendar_nonce' ) ) {
			return false;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || isset( $_REQUEST['bulk_edit'] ) ) {
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
}
