<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Integrations\AbstractMeetingProvider;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use WP_Error;

/**
 * Google Meet as an online meeting provider: one persistent room per event.
 *
 * @since 3.16.0
 */
class GoogleMeetIntegration extends AbstractMeetingProvider {

	/**
	 * Actions client.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleActionsClient
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param CreditsService      $credits Credits service.
	 * @param GoogleActionsClient $client  Actions client.
	 */
	public function __construct( CreditsService $credits, GoogleActionsClient $client ) {

		parent::__construct( $credits );

		$this->client = $client;
	}

	/**
	 * Register the capability.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function init() {

		IntegrationCapabilityRegistry::instance()->register( $this );
	}

	/**
	 * Provider slug. Matches the connection row so the editor can look the connection up.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_provider_slug(): string {

		return 'google';
	}

	/**
	 * Display name.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_display_name(): string {

		return __( 'Google Meet', 'sugar-calendar-lite' );
	}

	/**
	 * Create the Meet room.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event Event object.
	 *
	 * @return array|WP_Error
	 *
	 * @phpstan-ignore sugarCalendar.methodLength
	 */
	public function create_meeting( $event ) {

		$connection = $this->get_connection();

		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		/**
		 * Filter the Google Meet space config sent on create.
		 *
		 * @since 3.16.0
		 *
		 * @param array  $config Space config; `accessType` OPEN, TRUSTED or RESTRICTED.
		 * @param object $event  Event object.
		 */
		$config = (array) apply_filters( 'sugar_calendar_google_meet_space_config', [ 'accessType' => 'OPEN' ], $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$result = $this->client->create_meet_space( $connection, $config );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['meetingUri'] ) ) {
			return new WP_Error( 'google_meet_invalid_response', esc_html__( 'Google Meet returned an invalid response.', 'sugar-calendar-lite' ) );
		}

		return [
			'provider'         => 'google',
			'meeting_id'       => (string) ( $result['meetingCode'] ?? '' ),
			'join_url'         => (string) $result['meetingUri'],
			'password'         => '',
			'meeting_settings' => wp_json_encode(
				[
					'name'   => (string) ( $result['name'] ?? '' ),
					'config' => $config,
				]
			),
		];
	}

	/**
	 * A room has no title or time, so nothing ever changes: constant kind and fingerprint.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event Event object.
	 *
	 * @return array
	 */
	public function get_sync_signature( $event ): array {

		return [
			'kind'        => 'space',
			'fingerprint' => 'space',
		];
	}

	/**
	 * Nothing to update on Google; hand the stored values back so the manager rewrites them unchanged.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event Event object.
	 *
	 * @return array|WP_Error
	 */
	public function update_meeting( $event ) {

		return [
			'provider'         => 'google',
			'meeting_id'       => (string) get_event_meta( $event->id, 'meeting_id', true ),
			'join_url'         => (string) get_event_meta( $event->id, 'join_url', true ),
			'password'         => '',
			'meeting_settings' => (string) get_event_meta( $event->id, 'meeting_settings', true ),
		];
	}

	/**
	 * Meet has no delete; the room stays and Google expires its code after a year unused.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event Event object.
	 *
	 * @return true
	 */
	public function delete_meeting( $event ) {

		return true;
	}

	/**
	 * Nothing is deleted on trash, so the room and its card stay for a restore.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event Event object.
	 *
	 * @return bool
	 */
	public function trash_meeting( $event ): bool {

		return false;
	}

	/**
	 * One link serves host and guests.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	public function get_host_url( $event ): string {

		return '';
	}

	/**
	 * Active Google connection with the Meet permissions, or error. Without the Meet scopes the
	 * editor shows "(not connected)" instead of the out-of-credits state.
	 *
	 * @since 3.16.0
	 *
	 * @return GoogleOAuthConnection|WP_Error
	 */
	protected function get_connection() {

		$row = OAuthConnectionModel::find_active_by_provider( $this->get_provider_slug() );

		if ( $row === null ) {
			return new WP_Error( 'google_no_connection', esc_html__( 'No active Google connection.', 'sugar-calendar-lite' ) );
		}

		$connection = new GoogleOAuthConnection( $row );

		if ( ! $connection->has_meet_access() ) {
			return new WP_Error( 'google_no_meet_access', esc_html__( 'Google Meet permissions were not approved.', 'sugar-calendar-lite' ) );
		}

		return $connection;
	}
}
