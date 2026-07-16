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

		$meeting_data = $this->build_meeting_data( $event );

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
	 * Build the Zoom meeting payload from an event (shared by create + update).
	 *
	 * Sends local time + an explicit timezone (rather than a forced UTC
	 * conversion) because SCE events can be floating-tz. Duration falls back
	 * to 60 minutes when the event has no positive span.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array|WP_Error Payload array, or WP_Error when the start can't be resolved.
	 */
	private function build_meeting_data( $event ) {

		$start_dto = ( isset( $event->start_dto ) && $event->start_dto instanceof \DateTimeInterface )
			? $event->start_dto
			: sugar_calendar_get_datetime_object( $event->start, $event->start_tz );

		// Reachable from the create-meeting AJAX path with date/time straight from
		// $_POST — a malformed start would otherwise fatal on ->format() below.
		if ( ! $start_dto instanceof \DateTimeInterface ) {
			return new WP_Error( 'zoom_invalid_start', esc_html__( 'The event has no valid start date and time for the meeting.', 'sugar-calendar-lite' ) );
		}

		$start_ts = strtotime( $event->start );
		$end_ts   = strtotime( $event->end );

		$duration = ( $start_ts && $end_ts && $end_ts > $start_ts )
			? (int) ceil( ( $end_ts - $start_ts ) / 60 )
			: 60;

		$settings = [ 'waiting_room' => false ];

		/**
		 * Filter the settings array sent to the Zoom create-meeting relay call.
		 *
		 * @since 3.12.0
		 *
		 * @param array  $settings Zoom meeting settings.
		 * @param object $event    SCE Event object.
		 */
		$settings = (array) apply_filters( 'sugar_calendar_zoom_meeting_settings', $settings, $event );

		return [
			'topic'      => $event->title,
			'type'       => 2, // Scheduled meeting.
			'start_time' => $start_dto->format( 'Y-m-d\TH:i:s' ),
			'timezone'   => $event->start_tz ? $event->start_tz : sugar_calendar_get_timezone(),
			'duration'   => $duration,
			'settings'   => $settings,
		];
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

		$meeting_data = $this->build_meeting_data( $event );

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
