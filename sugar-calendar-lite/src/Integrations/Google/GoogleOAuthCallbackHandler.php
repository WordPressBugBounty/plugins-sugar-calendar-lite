<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Admin\Pages\Integrations\GoogleCalendar;
use Sugar_Calendar\Admin\Pages\SettingsIntegrationsTab;
use Sugar_Calendar\Integrations\OAuthRelay\AbstractOAuthCallbackHandler;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Options;

/**
 * Google OAuth callback handler.
 *
 * @since 3.16.0
 */
class GoogleOAuthCallbackHandler extends AbstractOAuthCallbackHandler {

	/**
	 * Error code when the watch channel failed to start after a successful connect, else ''.
	 *
	 * @since 3.16.0
	 *
	 * @var string
	 */
	private $sync_error_code = '';

	/**
	 * Provider slug used by the relay and the connections table.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	protected function get_provider(): string {

		return GoogleCalendar::PROVIDER;
	}

	/**
	 * Redirect target after the callback.
	 *
	 * The page slug (`google-calendar`) differs from the provider slug, so the
	 * base-class default of `get_integration_url( provider )` would miss the page.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	protected function get_default_redirect_url(): string {

		return SettingsIntegrationsTab::get_integration_url( GoogleCalendar::SLUG );
	}

	/**
	 * Store the connection, and drop the saved calendar when the account changed.
	 *
	 * @since 3.16.0
	 *
	 * @param array $tokens Token data.
	 * @param array $user   User data.
	 * @param int   $app_id OAuth application ID.
	 *
	 * @return int|\WP_Error
	 *
	 * @phpstan-ignore sugarCalendar.methodLength
	 */
	protected function store_connection( array $tokens, array $user, int $app_id ) {

		$previous = OAuthConnectionModel::find_by_provider( $this->get_provider() );
		$stored   = parent::store_connection( $tokens, $user, $app_id );

		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		$same_account = $previous !== null && (string) $previous['account_id'] === (string) $user['id'];

		if ( ! $same_account ) {
			Options::update( 'google_calendar_id', '' );
			Options::update( 'google_calendar_name', '' );

			if ( $previous !== null ) {
				delete_transient( GoogleCalendar::CALENDARS_TRANSIENT_PREFIX . (string) $previous['account_id'] );

				// A switched account must pick its calendar on purpose; a one-calendar list would preselect it.
				Options::update( 'google_calendar_sync_enabled', false );
			}
		}

		// A reconnect is the recovery path after a failed channel; the relay row needs the new refresh token.
		$manager = new GoogleWatchManager();

		foreach ( array_keys( GoogleWatchManager::get_state() ) as $calendar_id ) {
			$manager->stop( (string) $calendar_id );
		}

		$reconciled = $manager->reconcile();

		// Connect itself succeeded, so a failed channel is a notice on the page, not a failed connect.
		$this->sync_error_code = is_wp_error( $reconciled ) ? (string) $reconciled->get_error_code() : '';

		return $stored;
	}

	/**
	 * Tell the admin when the account connected but the sync channel did not start.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function redirect_with_success(): void {

		if ( $this->sync_error_code === '' ) {
			parent::redirect_with_success();

			return;
		}

		$notice = $this->sync_error_code === 'google_mapping_invalid'
			? 'google_mapping_invalid'
			: $this->get_provider() . '_sync_start_failed';

		wp_safe_redirect( add_query_arg( 'sc_notice', $notice, $this->get_redirect_url() ) );
		exit;
	}
}
