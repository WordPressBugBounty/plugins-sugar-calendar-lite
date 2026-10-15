<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Integrations\Google\GoogleOAuthConnection;
use Sugar_Calendar\Integrations\Google\GoogleWatchManager;
use Sugar_Calendar\Options;

/**
 * Google Calendar / Meets Integration page.
 *
 * The page slug (`google-calendar`) differs from the relay provider slug
 * (`google`); every provider-facing call uses PROVIDER, every URL uses SLUG.
 *
 * @since 3.16.0
 */
class GoogleCalendar extends AbstractOAuthIntegrationPage {

	/**
	 * Page slug (URL key).
	 *
	 * @since 3.16.0
	 */
	const SLUG = 'google-calendar';

	/**
	 * Relay provider slug (connections table + relay app row).
	 *
	 * @since 3.16.0
	 */
	const PROVIDER = 'google';

	/**
	 * Base documentation URL. Each entry point adds its own UTM params.
	 *
	 * @since 3.16.0
	 */
	const DOCS_URL = 'https://sugarcalendar.com/docs/events/using-the-google-calendar-integration/';

	/**
	 * Transient key prefix for the cached calendar list; the account id is appended.
	 *
	 * @since 3.16.0
	 */
	const CALENDARS_TRANSIENT_PREFIX = 'sugar_calendar_google_calendars_';

	/**
	 * Calendar dropdown value for "Don't Sync Events"; Google calendar ids always contain an @.
	 *
	 * @since 3.16.0
	 */
	const SYNC_OFF_VALUE = 'none';



	/**
	 * Integration slug (URL key).
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_slug() {

		return self::SLUG;
	}

	/**
	 * Display name shown in the sidebar and content title bar.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_name() {

		return esc_html__( 'Google Calendar / Meets', 'sugar-calendar-lite' );
	}

	/**
	 * Sidebar icon URL.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_icon_url() {

		return SC_PLUGIN_ASSETS_URL . 'images/integrations/integration-google.png';
	}

	/**
	 * Relay provider slug.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_provider() {

		return self::PROVIDER;
	}

	/**
	 * The sentence under the title.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	protected function get_description_text() {

		/* translators: %s - Documentation link URL. */
		return __( 'Connect your Google Calendar account to automatically sync new events there and host online events via Meets. Events will be synced to your selected calendar. If you need help, please refer to <a href="%s" target="_blank" rel="noopener">our documentation</a>.', 'sugar-calendar-lite' );
	}

	/**
	 * Label of the Connect button.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	protected function get_connect_label() {

		return __( 'Connect with Google', 'sugar-calendar-lite' );
	}

	/**
	 * Inline notices this page shows.
	 *
	 * @since 3.16.0
	 *
	 * @return array
	 */
	protected function get_notice_messages() {

		return [
			'google_connected'         => [ 'success', __( 'Google connected successfully.', 'sugar-calendar-lite' ) ],
			'google_disconnected'      => [ 'success', __( 'Google disconnected.', 'sugar-calendar-lite' ) ],
			'google_relay_unavailable' => [ 'error', __( 'Could not connect to Google. Please try again.', 'sugar-calendar-lite' ) ],
			'google_scopes_missing'    => [ 'error', __( 'Some permissions were not approved. Connect again and accept all of them.', 'sugar-calendar-lite' ) ],
			'google_sync_start_failed' => [ 'error', __( 'Could not start the Google sync. Please try again.', 'sugar-calendar-lite' ) ],
			'google_mapping_invalid'   => [ 'error', __( 'The Google Calendar mapping snippet points at a calendar that could not be synced. Check the snippet.', 'sugar-calendar-lite' ) ],
		];
	}

	/**
	 * The scope warnings follow the inline notice.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function maybe_render_notice() {

		parent::maybe_render_notice();

		$this->maybe_render_scope_warning();
	}

	/**
	 * The calendar and sync rows, or their locked stand-ins.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function render_settings_rows() {

		( new GoogleCalendarSettingsFields( new GoogleCalendarSettingsForm( $this->get_connection_row() ) ) )->render( $this->get_connection() );
	}

	/**
	 * Forget the account's calendars and stop every watch channel; the next account picks its own.
	 *
	 * @since 3.16.0
	 *
	 * @param array|null $row The connection row being removed, or null when none existed.
	 *
	 * @return void
	 */
	protected function on_disconnect( $row ) {

		if ( $row !== null ) {
			delete_transient( self::CALENDARS_TRANSIENT_PREFIX . (string) $row['account_id'] );
		}

		// Snippet-mapped calendars have channels too; the relay would renew them forever.
		$manager = new GoogleWatchManager();

		foreach ( GoogleWatchManager::get_state() as $calendar_id => $entry ) {
			if ( ! empty( $entry['channel_id'] ) ) {
				$manager->stop( (string) $calendar_id );
			}
		}

		Options::update( 'google_calendar_id', '' );
		Options::update( 'google_calendar_name', '' );
		Options::update( 'google_calendar_sync_enabled', true );
	}

	/**
	 * Enqueue the page's script: the Remove dialog and the sync-row lock.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-confirm' );

		wp_enqueue_script(
			'sugar-calendar-admin-integration-google-calendar',
			SC_PLUGIN_ASSETS_URL . 'js/admin-integration-google-calendar' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-jquery-confirm' ],
			Sugar_Calendar_Helpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-integration-google-calendar',
			'sugar_calendar_admin_integration_google_calendar',
			[
				'icon_url'       => SC_PLUGIN_ASSETS_URL . 'images/icons/exclamation-circle.svg',
				'sync_off_value' => self::SYNC_OFF_VALUE,
				'text'           => [
					'title'       => esc_html__( 'Remove Google connection?', 'sugar-calendar-lite' ),
					'message'     => esc_html__( "Links on existing events stay active. New online events won't get Google Meet links until you reconnect.", 'sugar-calendar-lite' ),
					'confirm'     => esc_html__( 'Remove', 'sugar-calendar-lite' ),
					'cancel'      => esc_html__( 'Cancel', 'sugar-calendar-lite' ),
					'sync_locked' => esc_html__( 'Pick a calendar to enable', 'sugar-calendar-lite' ),
				],
			]
		);
	}

	/**
	 * Render the Google icon inside the Connect button.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function render_connect_button_icon() {

		?>
		<span class="sugar-calendar-google-calendar__connect-button-icon">
			<img
				src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/icons/google.svg' ); ?>"
				alt=""
				width="30"
				height="30"
			/>
		</span>
		<?php
	}

	/**
	 * Save, then make the watch channel match what was saved.
	 *
	 * Called by SettingsIntegrationsTab::handle_post().
	 *
	 * @since 3.16.0
	 *
	 * @param array $post_data Posted `sugar-calendar` settings array.
	 *
	 * @return void
	 */
	public function handle_post( $post_data = [] ) {

		// The shared settings nonce is also issued on screens lower roles can reach.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved = ( new GoogleCalendarSettingsForm( $this->get_connection_row() ) )->save( $post_data );

		// Idempotent: starts or stops the Google channel to match the saved direction and calendar.
		$reconciled = ( new GoogleWatchManager() )->reconcile();

		if ( is_wp_error( $reconciled ) ) {
			$message = $reconciled->get_error_code() === 'google_mapping_invalid'
				? esc_html__( 'The Google Calendar mapping snippet points at a calendar that could not be synced. Check the snippet.', 'sugar-calendar-lite' )
				: esc_html__( 'Could not start the Google sync. Please try again.', 'sugar-calendar-lite' );

			WP::add_admin_notice( $message, WP::ADMIN_NOTICE_ERROR );
		}

		// The save itself worked even when a mapped calendar did not; say both.
		if ( $saved ) {
			WP::add_admin_notice( esc_html__( 'Settings saved.', 'sugar-calendar-lite' ), WP::ADMIN_NOTICE_SUCCESS );
		}
	}

	/**
	 * The connected account, wrapped; only meaningful while the status is active.
	 *
	 * @since 3.16.0
	 *
	 * @return GoogleOAuthConnection
	 */
	private function get_connection(): GoogleOAuthConnection {

		return new GoogleOAuthConnection( (array) $this->get_connection_row() );
	}

	/**
	 * Warn while a consent group was left unticked; the feature stays off until a reconnect approves it.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function maybe_render_scope_warning() {

		if ( $this->get_status() !== 'active' ) {
			return;
		}

		$connection = $this->get_connection();
		$warnings   = [];

		if ( ! $connection->has_calendar_access() ) {
			$warnings[] = __( 'Google Calendar permissions were not approved. To use this feature, please reconnect and approve them.', 'sugar-calendar-lite' );
		}

		if ( ! $connection->has_meet_access() ) {
			$warnings[] = __( 'Google Meet permissions were not approved. To use this feature, please reconnect and approve them.', 'sugar-calendar-lite' );
		}

		foreach ( $warnings as $warning ) {
			?>
			<div class="notice notice-warning">
				<p><?php echo esc_html( $warning ); ?></p>
			</div>
			<?php
		}
	}
}
