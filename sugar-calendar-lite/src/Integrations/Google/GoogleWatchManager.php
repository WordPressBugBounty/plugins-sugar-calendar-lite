<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\OAuthRelay\Webhooks\IncomingWebhookHandler;
use Sugar_Calendar\Options;
use WP_Error;

/**
 * Owns the Google push channels for the calendars mapped for pulling: start, stop, reconcile.
 *
 * The relay creates, renews and stops the channel; this class only decides when.
 *
 * @since 3.16.0
 */
class GoogleWatchManager {

	/**
	 * Standalone option holding per-calendar channel and sync state.
	 *
	 * Not a key inside the plugin settings array: a background run writing a sync token
	 * through Options::update() would rewrite every setting from a stale cache.
	 *
	 * @since 3.16.0
	 *
	 * @var string
	 */
	const OPTION = 'sugar_calendar_google_pull_state';

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
	 * @param GoogleActionsClient|null $client Actions client. Built here when the caller has none.
	 */
	public function __construct( ?GoogleActionsClient $client = null ) {

		$this->client = $client ?? new GoogleActionsClient();
	}

	/**
	 * Make what is watched match what the settings ask for.
	 *
	 * @since 3.16.0
	 *
	 * @return true|WP_Error First error met, or true.
	 */
	public function reconcile() {

		$saved   = (string) Options::get( 'google_calendar_id' );
		$desired = GoogleSyncMapper::pull_calendars();
		$current = $this->watched_calendars();
		$result  = true;

		foreach ( array_diff( $current, $desired ) as $id ) {
			$this->stop( $id );
		}

		foreach ( array_diff( $desired, $current ) as $id ) {
			$started = $this->start( $id );

			if ( ! is_wp_error( $started ) || $result !== true ) {
				continue;
			}

			// A snippet row that cannot be watched gets its own notice, so the admin looks at the snippet.
			$result = $id === $saved
				? $started
				: new WP_Error( 'google_mapping_invalid', $started->get_error_message() );
		}

		return $result;
	}

	/**
	 * Calendars with an open channel.
	 *
	 * @since 3.16.0
	 *
	 * @return string[]
	 */
	private function watched_calendars(): array {

		$current = [];

		foreach ( self::get_state() as $id => $entry ) {
			if ( ! empty( $entry['channel_id'] ) ) {
				$current[] = (string) $id;
			}
		}

		return $current;
	}

	/**
	 * Open the channel and queue the first pull.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return true|WP_Error
	 */
	private function start( string $calendar_id ) {

		$row = OAuthConnectionModel::find_active_by_provider( 'google' );

		if ( $row === null ) {
			return new WP_Error( 'google_no_connection', 'No active Google connection.' );
		}

		$result = $this->client->watch_calendar( new GoogleOAuthConnection( $row ), $calendar_id, IncomingWebhookHandler::get_webhook_url( 'google' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$stored = $this->store_channel( $calendar_id, (array) $result );

		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		GooglePullScheduler::enqueue( $calendar_id );

		return true;
	}

	/**
	 * Store the channel the relay opened.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id.
	 * @param array  $result      Relay answer: channel_id, resource_id, expiration.
	 *
	 * @return true|WP_Error
	 */
	private function store_channel( string $calendar_id, array $result ) {

		$channel_id = (string) ( $result['channel_id'] ?? '' );

		// Storing an empty id would read as "not watched" and open a second channel on the next save.
		if ( $channel_id === '' ) {
			return new WP_Error( 'google_watch_no_channel', 'The relay returned no channel id.' );
		}

		self::update_state(
			$calendar_id,
			[
				'channel_id'  => $channel_id,
				'resource_id' => (string) ( $result['resource_id'] ?? '' ),
				'expires_at'  => (int) ( $result['expiration'] ?? 0 ),
			]
		);

		return true;
	}

	/**
	 * Close the channel. The sync token stays so re-enabling resumes incrementally.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return void
	 */
	public function stop( string $calendar_id ) {

		// Any status: a stop has to work on an auth_error row too.
		$row = OAuthConnectionModel::find_by_provider( 'google' );

		if ( $row !== null ) {
			$this->client->stop_watch( new GoogleOAuthConnection( $row ), $calendar_id );
		}

		self::clear_channel( $calendar_id );
	}

	/**
	 * Stop every channel and forget the calendar before "Disable Integrations" deletes the token the stop needs.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function disable() {

		foreach ( self::get_state() as $calendar_id => $fields ) {
			if ( ! empty( $fields['channel_id'] ) ) {
				$this->stop( (string) $calendar_id );
			}
		}

		Options::update( 'google_calendar_id', '' );
		Options::update( 'google_calendar_name', '' );
		Options::update( 'google_calendar_sync_enabled', true );
	}

	/**
	 * Per-calendar channel and sync state.
	 *
	 * @since 3.16.0
	 *
	 * @return array
	 */
	public static function get_state(): array {

		$state = get_option( self::OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	/**
	 * Merge fields into one calendar's entry.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id.
	 * @param array  $fields      Fields to set.
	 *
	 * @return void
	 */
	public static function update_state( string $calendar_id, array $fields ) {

		$state                 = self::get_state();
		$state[ $calendar_id ] = array_merge(
			[
				'channel_id'  => '',
				'resource_id' => '',
				'expires_at'  => 0,
				'sync_token'  => '',
				'page_token'  => '',
			],
			$state[ $calendar_id ] ?? [],
			$fields
		);

		update_option( self::OPTION, $state, false );
	}

	/**
	 * Blank the channel fields, keep the sync token.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return void
	 */
	public static function clear_channel( string $calendar_id ) {

		self::update_state(
			$calendar_id,
			[
				'channel_id'  => '',
				'resource_id' => '',
				'expires_at'  => 0,
				'page_token'  => '',
			]
		);
	}
}
