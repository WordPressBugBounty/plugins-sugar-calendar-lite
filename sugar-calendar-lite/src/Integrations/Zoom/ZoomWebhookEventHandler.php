<?php

namespace Sugar_Calendar\Integrations\Zoom;

use Sugar_Calendar\Integrations\EventMeetingManager;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\MeetingProviderInterface;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\WebhookEventHandlerInterface;

/**
 * Zoom webhook event handler.
 *
 * SCE-fresh (not a Bookings port). Two cases; everything else is ignored —
 * SCE is the one-way source of truth for meetings (master design §10):
 *
 *   app_deauthorized               — flip the zoom connection to auth_error
 *                                    (the existing OAuthConnectionErrorNotice
 *                                    then surfaces the Reconnect CTA). LATENT:
 *                                    the AM relay currently handles deauth
 *                                    relay-side and forwards only meeting.*
 *                                    events (verified 2026-06-04 against
 *                                    wpforms-product-api), so this branch does
 *                                    NOT fire in production today — deauth is
 *                                    instead caught lazily by OAuthTokenMiddleware
 *                                    on the next failed meeting op. Kept, keyed
 *                                    on Zoom's real `app_deauthorized` string,
 *                                    for the day the relay forwards it.
 *   meeting.(permanently_)deleted  — FULL reset of the linked event: the
 *                                    5-key meeting footprint + meeting_sync_hash
 *                                    + online_provider (3a design §2: the event
 *                                    reverts to Online=None; a later save does
 *                                    NOT auto-recreate the meeting).
 *
 * @since 3.12.0
 */
class ZoomWebhookEventHandler implements WebhookEventHandlerInterface {

	/**
	 * Provider slug.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_provider_slug(): string {

		return 'zoom';
	}

	/**
	 * Display name.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_display_name(): string {

		return __( 'Zoom', 'sugar-calendar-lite' );
	}

	/**
	 * Always available when registered — the handler validates
	 * connections per-event.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_available(): bool {

		return true;
	}

	/**
	 * Process a single webhook payload from the relay.
	 *
	 * @since 3.12.0
	 *
	 * @param array $payload Decoded JSON payload.
	 *
	 * @return void
	 */
	public function handle_event( array $payload ): void {

		$event_type = (string) ( $payload['event'] ?? '' );

		switch ( $event_type ) {
			case 'app_deauthorized':
				$this->handle_deauthorized( $payload );
				break;

			case 'meeting.deleted':
			case 'meeting.permanently_deleted':
				$this->handle_meeting_deleted( $payload );
				break;
		}

		// The hook name carries the RAW provider event string (e.g.
		// "...event_meeting.deleted") from a signature-verified relay payload —
		// the shape the master design documents. WP hook names are inert array
		// keys, so an unexpected event string is harmless here.
		/**
		 * Fires after default handling of a Zoom webhook event (including
		 * ignored event types). Reserved Pro seam (master design §3).
		 *
		 * @since 3.12.0
		 *
		 * @param array $payload Full decoded webhook payload.
		 */
		do_action( "sugar_calendar_zoom_webhook_event_{$event_type}", $payload );
	}

	/**
	 * Flip the zoom connection to auth_error on app_deauthorized.
	 *
	 * Latent in production — see the class docblock: the relay does not forward
	 * this event today. The logic below is exercised by the 3a E2E (which posts
	 * a signed `app_deauthorized`) so it stays correct for if/when it forwards.
	 *
	 * @since 3.12.0
	 *
	 * @param array $payload Webhook payload.
	 *
	 * @return void
	 */
	private function handle_deauthorized( array $payload ): void {

		// find_by_provider (newest row) is THE site-wide v1 connection — the
		// same convention the Settings UI and reconnect UPSERT use (Segment 1).
		// A deauth matching only an older, superseded row is intentionally
		// ignored by the account guard below: that row is inert data.
		$row = OAuthConnectionModel::find_by_provider( 'zoom' );

		if ( $row === null ) {
			error_log( '[SC Zoom] app_deauthorized received but no zoom connection exists.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return;
		}

		// Replay-tolerant: the relay may deliver a deauth more than once.
		if ( (string) $row['status'] === 'auth_error' ) {
			return;
		}

		// Zoom's deauthorization payload names the deauthorizing user under
		// payload.user_id (payload.account_id is the org). When an id is
		// present and does NOT match the stored account, this is a stale
		// deauth for some other account — it must not kill the live
		// connection (3a design §2 call 5).
		$payload_user = (string) ( $payload['payload']['user_id'] ?? $payload['payload']['account_id'] ?? '' );

		if ( $payload_user !== '' && $payload_user !== (string) $row['account_id'] ) {
			error_log( '[SC Zoom] app_deauthorized account mismatch — ignoring (payload account: ' . $payload_user . ').' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return;
		}

		OAuthConnectionModel::update( (int) $row['id'], [ 'status' => 'auth_error' ] );
	}

	/**
	 * Fully reset the linked event when its meeting is deleted at Zoom.
	 *
	 * @since 3.12.0
	 *
	 * @param array $payload Webhook payload.
	 *
	 * @return void
	 */
	private function handle_meeting_deleted( array $payload ): void {

		$meeting_id = (string) ( $payload['payload']['object']['id'] ?? '' );

		if ( $meeting_id === '' ) {
			return;
		}

		$event_id = $this->find_event_by_meeting_id( $meeting_id );

		// Unknown id: an already-detached event, or the echo of our own
		// WP-side delete (cleanup() → relay DELETE → Zoom webhook). No-op.
		if ( $event_id === 0 ) {
			return;
		}

		// Cross-provider id-collision guard.
		if ( (string) get_event_meta( $event_id, 'meeting_provider', true ) !== 'zoom' ) {
			return;
		}

		foreach ( $this->get_reset_meta_keys() as $key ) {
			delete_event_meta( $event_id, $key );
		}

		// Leave a breadcrumb so the event editor can explain this otherwise-silent
		// reset (MeetingRemovedNotice reads it). A direct meta write is NOT subject
		// to the sugar_calendar_event_to_save registration gate, and the key is
		// intentionally NOT in get_reset_meta_keys(), so it survives the reset that
		// writes it. The provider slug keeps the renderer provider-agnostic.
		update_event_meta( $event_id, EventMeetingManager::REMOVED_NOTICE_META_KEY, $this->get_provider_slug() );
	}

	/**
	 * Meta keys cleared on a full event reset (meeting deleted at Zoom).
	 *
	 * The provider's meeting footprint PLUS the two keys a normal
	 * EventMeetingManager::remove() does not touch — the manager-owned
	 * meeting_sync_hash and the editor's online_provider selection. The footprint
	 * is sourced from the provider so a new meeting meta key doesn't have to be
	 * duplicated here; the fallback (registry lookup miss) reuses
	 * ZoomIntegration::META_KEYS rather than a second copy of the literal list.
	 *
	 * @since 3.12.0
	 *
	 * @return string[]
	 */
	private function get_reset_meta_keys(): array {

		$provider = IntegrationCapabilityRegistry::instance()->find( MeetingProviderInterface::class, 'zoom' );

		$footprint = $provider instanceof MeetingProviderInterface
			? $provider->get_meeting_meta_keys()
			: ZoomIntegration::META_KEYS;

		return array_merge( $footprint, [ 'meeting_sync_hash', 'online_provider' ] );
	}

	/**
	 * Reverse-lookup an SCE event id by its stored Zoom meeting id.
	 *
	 * @since 3.12.0
	 *
	 * @param string $meeting_id Zoom meeting id.
	 *
	 * @return int Event id, or 0 when not found.
	 */
	private function find_event_by_meeting_id( string $meeting_id ): int {

		global $wpdb;

		// Zoom meeting ids are globally unique; LIMIT 1 is the steady-state
		// contract. A duplicate would indicate meta corruption — the second
		// event is deliberately left as-is.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sc_event_id FROM {$wpdb->prefix}sc_eventmeta WHERE meta_key = 'meeting_id' AND meta_value = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meeting_id
			)
		);
	}
}
