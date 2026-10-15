<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\WebhookEventHandlerInterface;

/**
 * Handles the relay's forwarded Google notifications: "calendar changed" and "connection failed".
 *
 * @since 3.16.0
 */
class GoogleWebhookEventHandler implements WebhookEventHandlerInterface {

	/**
	 * Provider slug.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_provider_slug(): string {

		return 'google';
	}

	/**
	 * Human-readable name.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_display_name(): string {

		return esc_html__( 'Google Calendar', 'sugar-calendar-lite' );
	}

	/**
	 * Always available when registered: each event checks the connection itself, and the
	 * "connection failed" event must run on a row that is already in error.
	 *
	 * @since 3.16.0
	 *
	 * @return bool
	 */
	public function is_available(): bool {

		return true;
	}

	/**
	 * Dispatch one forwarded event.
	 *
	 * @since 3.16.0
	 *
	 * @param array $payload Decoded, signature-verified body.
	 *
	 * @return void
	 */
	public function handle_event( array $payload ): void {

		$event       = (string) ( $payload['event'] ?? '' );
		$calendar_id = (string) ( $payload['calendar_id'] ?? '' );

		if ( $event === 'calendar.changed' && $calendar_id !== '' ) {
			// Google pings once per change and once when a channel opens; the queue coalesces.
			GooglePullScheduler::enqueue( $calendar_id );
		}

		if ( $event === 'connection_failed' ) {
			$this->handle_connection_failed( $calendar_id, (string) ( $payload['reason'] ?? '' ) );
		}

		/**
		 * Fires after default handling of a Google webhook event.
		 *
		 * @since 3.16.0
		 *
		 * @param array $payload Full decoded webhook payload.
		 */
		do_action( 'sugar_calendar_google_webhook_event_' . $event, $payload ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * The relay could not renew a channel: forget it, and surface Reconnect when the token is the cause.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id from the payload.
	 * @param string $reason      Relay failure reason.
	 *
	 * @return void
	 */
	private function handle_connection_failed( string $calendar_id, string $reason ) {

		if ( $calendar_id !== '' ) {
			GoogleWatchManager::clear_channel( $calendar_id );
		}

		// One calendar deleted in Google must not stop the sync for every other mapped calendar.
		if ( $reason === 'calendar_gone' ) {
			return;
		}

		$row = OAuthConnectionModel::find_by_provider( 'google' );

		if ( $row !== null && (string) $row['status'] !== 'auth_error' ) {
			OAuthConnectionModel::update( (int) $row['id'], [ 'status' => 'auth_error' ] );
		}
	}
}
