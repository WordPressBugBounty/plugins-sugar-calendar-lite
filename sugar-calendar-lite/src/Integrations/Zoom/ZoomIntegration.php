<?php

namespace Sugar_Calendar\Integrations\Zoom;

use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\MeetingProviderInterface;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthRelayClient;
use WP_Error;

/**
 * Zoom integration glue.
 *
 * Registers as a MeetingProviderInterface and wires the callback
 * handler. create_meeting() was added in Segment 2a;
 * update_meeting()/delete_meeting() and the keep-in-sync lifecycle
 * landed in Segment 2b.
 *
 * @since 3.12.0
 */
class ZoomIntegration implements MeetingProviderInterface {

	/**
	 * Meta keys this provider stores in wp_sc_eventmeta (the provider's "meeting footprint").
	 *
	 * Single source of truth for get_meeting_meta_keys() — ZoomWebhookEventHandler's
	 * reset-on-delete fallback references this instead of duplicating the literal list.
	 *
	 * @since 3.12.0
	 *
	 * @var string[]
	 */
	public const META_KEYS = [ 'meeting_provider', 'meeting_id', 'join_url', 'meeting_password', 'meeting_settings' ];

	/**
	 * Relay client.
	 *
	 * @since 3.12.0
	 *
	 * @var OAuthRelayClient
	 */
	private $relay;

	/**
	 * Credits service (gates availability on remaining credits).
	 *
	 * @since 3.12.0
	 *
	 * @var CreditsService
	 */
	private $credits;

	/**
	 * Lazily-instantiated Zoom actions client.
	 *
	 * @since 3.12.0
	 *
	 * @var ZoomActionsClient|null
	 */
	private $actions_client = null;

	/**
	 * Lazily-instantiated meeting-data builder.
	 *
	 * @since 3.13.0
	 *
	 * @var ZoomMeetingBuilder|null
	 */
	private $builder = null;

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 *
	 * @param OAuthRelayClient $relay   Relay client.
	 * @param CreditsService   $credits Credits service.
	 */
	public function __construct( OAuthRelayClient $relay, CreditsService $credits ) {

		$this->relay   = $relay;
		$this->credits = $credits;
	}

	/**
	 * Boot the integration.
	 *
	 * @since 3.12.0
	 */
	public function init() {

		// Register the callback handler so admin_init can pick up the OAuth return URL.
		( new ZoomOAuthCallbackHandler( $this->relay ) )->init();

		// Register the capability so future consumers (EventMeetingManager) find this provider.
		IntegrationCapabilityRegistry::instance()->register( $this );
	}

	// --- IntegrationCapabilityInterface ---

	/**
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_provider_slug(): string {

		return 'zoom';
	}

	/**
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_display_name(): string {

		return __( 'Zoom', 'sugar-calendar-lite' );
	}

	/**
	 * Available when an active connection exists AND credits remain.
	 *
	 * Credits are read from cache only (no HTTP on this path) and fail open:
	 * a cold cache does not block, since the relay is the ultimate enforcer.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_available(): bool {

		return ! is_wp_error( $this->get_connection() ) && ! $this->credits->is_out_of_credits();
	}

	// --- MeetingProviderInterface ---

	/**
	 * Create the Zoom meeting for an event.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array|WP_Error
	 */
	public function create_meeting( $event ) {

		$connection = $this->get_connection();

		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		$meeting_data = $this->get_builder()->build( $event );

		if ( is_wp_error( $meeting_data ) ) {
			return $meeting_data;
		}

		$result = $this->get_actions_client()->create_meeting( $connection, $meeting_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['id'] ) ) {
			return new WP_Error( 'zoom_invalid_response', esc_html__( 'Zoom API returned an invalid response.', 'sugar-calendar-lite' ) );
		}

		return [
			'provider'         => 'zoom',
			'meeting_id'       => (string) $result['id'],
			'join_url'         => $result['join_url'] ?? '',
			'password'         => $result['password'] ?? '',
			'meeting_settings' => wp_json_encode( $meeting_data['settings'] ?? [] ),
		];
	}

	/**
	 * Lazily build the meeting-data builder.
	 *
	 * @since 3.13.0
	 *
	 * @return ZoomMeetingBuilder
	 */
	private function get_builder(): ZoomMeetingBuilder {

		if ( $this->builder === null ) {
			$this->builder = new ZoomMeetingBuilder();
		}

		return $this->builder;
	}

	/**
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array{kind:string,fingerprint:string}
	 */
	public function get_sync_signature( $event ): array {

		return $this->get_builder()->signature( $event );
	}

	/**
	 * Lazily build the Zoom actions client.
	 *
	 * @since 3.12.0
	 *
	 * @return ZoomActionsClient
	 */
	private function get_actions_client(): ZoomActionsClient {

		if ( $this->actions_client === null ) {
			$this->actions_client = new ZoomActionsClient();
		}

		return $this->actions_client;
	}

	/**
	 * Update the Zoom meeting for an event (PATCH the recomputed payload).
	 *
	 * SCE sends no invitees, so Bookings' "can't update invitees → recreate"
	 * nuance does not apply — this is a straight PATCH. Zoom keeps meeting_id /
	 * join_url across a PATCH, so those are re-affirmed from existing meta
	 * rather than read from a possibly-thin PATCH response.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array|WP_Error
	 */
	public function update_meeting( $event ) {

		$connection = $this->get_connection();

		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		$meeting_id = (string) get_event_meta( $event->id, 'meeting_id', true );

		if ( $meeting_id === '' ) {
			return new WP_Error( 'zoom_no_meeting', esc_html__( 'No Zoom meeting to update.', 'sugar-calendar-lite' ) );
		}

		$meeting_data = $this->get_builder()->build( $event );

		if ( is_wp_error( $meeting_data ) ) {
			return $meeting_data;
		}

		$result = $this->get_actions_client()->update_meeting( $connection, $meeting_id, $meeting_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'provider'         => 'zoom',
			'meeting_id'       => $meeting_id,
			'join_url'         => (string) get_event_meta( $event->id, 'join_url', true ),
			'password'         => (string) get_event_meta( $event->id, 'meeting_password', true ),
			'meeting_settings' => wp_json_encode( $meeting_data['settings'] ?? [] ),
		];
	}

	/**
	 * Delete the Zoom meeting for an event. Idempotent.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return true|WP_Error
	 */
	public function delete_meeting( $event ) {

		$meeting_id = (string) get_event_meta( $event->id, 'meeting_id', true );

		if ( $meeting_id === '' ) {
			return true; // Nothing to delete.
		}

		$connection = $this->get_connection();

		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		return $this->get_actions_client()->delete_meeting( $connection, $meeting_id );
	}

	/**
	 * Resolve the active Zoom connection.
	 *
	 * Single source of truth for the connection lookup that availability,
	 * create/update/delete all need — avoids re-deriving the same null-check
	 * + WP_Error shape at every call site.
	 *
	 * @since 3.12.0
	 *
	 * @return ZoomOAuthConnection|WP_Error
	 */
	private function get_connection() {

		$row = OAuthConnectionModel::find_active_by_provider( $this->get_provider_slug() );

		if ( $row === null ) {
			return new WP_Error( 'zoom_no_connection', esc_html__( 'No active Zoom connection.', 'sugar-calendar-lite' ) );
		}

		return new ZoomOAuthConnection( $row );
	}

	/**
	 * Zoom "start as host" URL, derived from the stored meeting id.
	 *
	 * Constructed (not stored): Zoom's real start_url embeds a host token that
	 * expires (~2h) and is sensitive. The canonical .../s/{id} URL is stable and
	 * starts the meeting as host when the account owner is logged into Zoom.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string
	 */
	public function get_host_url( $event ): string {

		$meeting_id = ! empty( $event->id )
			? (string) get_event_meta( $event->id, 'meeting_id', true )
			: '';

		return $meeting_id !== ''
			? 'https://zoom.us/s/' . rawurlencode( $meeting_id )
			: '';
	}

	/**
	 * @since 3.12.0
	 *
	 * @return string[]
	 */
	public function get_meeting_meta_keys(): array {

		return self::META_KEYS;
	}
}
