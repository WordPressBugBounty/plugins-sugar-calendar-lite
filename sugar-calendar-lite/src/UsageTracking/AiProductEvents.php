<?php

namespace Sugar_Calendar\UsageTracking;

use Sugar_Calendar\Integrations\Ai\Availability;
use Sugar_Calendar\Integrations\Ai\IntroSeenFlag;
use Sugar_Calendar\Integrations\Ai\PluginRatedFlag;
use Sugar_Calendar\Integrations\Ai\Session;
use Sugar_Calendar\Integrations\Ai\TurnInput;
use Sugar_Calendar\Integrations\Ai\TurnState;
use Sugar_Calendar\Integrations\OAuthRelay\RelayLicenseType;
use Sugar_Calendar\Options;

/**
 * Product Events and snapshot keys for the Gia AI assistant.
 *
 * Gia fires plain actions at its named moments; this class is the only place
 * that turns them into telemetry. Nothing here is per turn or per write: the
 * catalogue is once per user, once per session, or the exceptions.
 *
 * @since 3.15.0
 */
class AiProductEvents extends AbstractProductEvents {

	/**
	 * Reasons `ai_turn_interrupted` reports; anything else never reaches the relay.
	 *
	 * @since 3.15.0
	 */
	const INTERRUPT_REASONS = [ 'error', 'refusal', 'stopped' ];

	/**
	 * Register hooks.
	 *
	 * @since 3.15.0
	 */
	protected function hooks() {

		add_action( 'sugar_calendar_ai_first_opened', [ $this, 'track_first_opened' ] );
		add_action( 'sugar_calendar_ai_session_started', [ $this, 'track_session_started' ], 10, 2 );
		add_action( 'sugar_calendar_ai_turn_interrupted', [ $this, 'track_turn_interrupted' ], 10, 3 );
		add_action( 'sugar_calendar_ai_session_converted', [ $this, 'track_session_converted' ], 10, 4 );
		add_action( 'sugar_calendar_ai_session_cleared', [ $this, 'track_session_cleared' ] );
		add_action( 'sugar_calendar_ai_plugin_rated', [ $this, 'track_plugin_rated' ] );
		add_filter( 'sugar_calendar_usage_tracking_get_data', [ $this, 'filter_usage_tracking_data' ] );
	}

	/**
	 * A user opened the chat for the first time.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 */
	public function track_first_opened( $user_id ) {

		$this->track_event( 'ai_first_opened', [ 'license_type' => RelayLicenseType::get() ] );
	}

	/**
	 * A session took its first prompt.
	 *
	 * @since 3.15.0
	 *
	 * @param Session   $session The session.
	 * @param TurnInput $input   The request that started it.
	 */
	public function track_session_started( $session, $input ) {

		if ( ! $session instanceof Session || ! $input instanceof TurnInput ) {
			return;
		}

		$this->track_event(
			'ai_session_started',
			[
				'funnel'       => (string) $session->funnel(),
				'license_type' => RelayLicenseType::get(),
				'has_mentions' => $input->mentions() !== [],
			]
		);
	}

	/**
	 * A turn ended in an error, a refusal or a Stop.
	 *
	 * @since 3.15.0
	 *
	 * @param Session $session    The session.
	 * @param string  $reason     One of `error`, `refusal` or `stopped`.
	 * @param string  $error_code WP_Error code for `error`, otherwise empty.
	 */
	public function track_turn_interrupted( $session, $reason, $error_code = '' ) {

		if ( ! $session instanceof Session || ! in_array( $reason, self::INTERRUPT_REASONS, true ) ) {
			return;
		}

		$this->track_event(
			'ai_turn_interrupted',
			[
				'funnel'     => (string) $session->funnel(),
				'reason'     => $reason,
				'error_code' => $reason === 'error' ? sanitize_key( (string) $error_code ) : '',
				'turn_seq'   => $session->state()->turn_seq(),
			]
		);
	}

	/**
	 * The session's first write landed.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState $state   The session state.
	 * @param string    $action  Either `create` or `update`.
	 * @param int       $updated Events created or successfully updated.
	 * @param int       $failed  Events the turn failed to update.
	 */
	public function track_session_converted( $state, $action, $updated, $failed ) {

		if ( ! $state instanceof TurnState || ! in_array( $action, [ 'create', 'update' ], true ) ) {
			return;
		}

		$this->track_event(
			'ai_session_converted',
			[
				'funnel'      => (string) $state->funnel(),
				'action'      => $action,
				'updated'     => (int) $updated,
				'failed'      => (int) $failed,
				'turn_seq'    => $state->turn_seq(),
				'tool_rounds' => $state->tool_rounds(),
			]
		);
	}

	/**
	 * The user cleared the session.
	 *
	 * @since 3.15.0
	 *
	 * @param Session $session The session before the clear.
	 */
	public function track_session_cleared( $session ) {

		if ( ! $session instanceof Session ) {
			return;
		}

		$state = $session->state();

		$this->track_event(
			'ai_session_cleared',
			[
				'funnel'         => (string) $session->funnel(),
				'turns'          => $state->turn_seq(),
				'writes'         => $state->writes(),
				'events_written' => $state->events_written(),
				'converted'      => $state->writes() > 0,
			]
		);
	}

	/**
	 * A user clicked Rate Plugin for the first time.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 */
	public function track_plugin_rated( $user_id ) {

		// track_event() adds `edition`, which keeps the row from being dropped as empty.
		$this->track_event( 'ai_plugin_rated', [] );
	}

	/**
	 * Add the Gia keys to the weekly snapshot.
	 *
	 * @since 3.15.0
	 *
	 * @param array $data Usage tracking data.
	 *
	 * @return array
	 */
	public function filter_usage_tracking_data( $data ) {

		$data['sugar_calendar_settings_misc_ai_assistant_enabled'] = (bool) Options::get( Availability::OPTION_KEY, true );
		$data['sugar_calendar_ai_users_opened']                    = $this->count_users_with_meta( IntroSeenFlag::META_KEY );
		$data['sugar_calendar_ai_users_rated_plugin']              = $this->count_users_with_meta( PluginRatedFlag::META_KEY );

		return $data;
	}

	/**
	 * Users carrying a meta key. One indexed COUNT(*), once per weekly ping.
	 *
	 * @since 3.15.0
	 *
	 * @param string $meta_key User meta key.
	 *
	 * @return int
	 */
	private function count_users_with_meta( $meta_key ) {

		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key )
		);
	}
}
