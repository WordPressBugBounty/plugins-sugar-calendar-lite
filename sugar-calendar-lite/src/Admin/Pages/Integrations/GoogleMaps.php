<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Options;

/**
 * Google Maps integration page.
 *
 * Provides metadata (slug/name/icon/status) consumed by
 * SettingsIntegrationsTab and a render_content() that fills the content
 * panel with the Maps description + API Key field. Saving routes through
 * the tab's generic handle_post() delegate.
 *
 * Google Maps is a local integration: it does NOT use the Product API and
 * is NOT tied to usage credits, so it stays functional even when the Lite
 * "Disable Integrations" kill-switch is on (is_local() === true). Keys are
 * verified against Google on save (see verify_api_key()); get_status() itself
 * remains key-presence-only and does not re-verify.
 *
 * @since 3.12.0
 */
class GoogleMaps extends AbstractIntegrationPage {

	/**
	 * Base documentation URL. Each entry point adds its own UTM params.
	 *
	 * @since 3.12.0
	 */
	const DOCS_URL = 'https://sugarcalendar.com/docs/events/using-google-maps-to-display-event-location/';

	/**
	 * Integration slug (URL key).
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_slug() {

		return 'google-maps';
	}

	/**
	 * Display name.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_name() {

		return esc_html__( 'Google Maps', 'sugar-calendar-lite' );
	}

	/**
	 * Sidebar icon URL.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_icon_url() {

		return SC_PLUGIN_ASSETS_URL . 'images/integrations/integration-google-maps.png';
	}

	/**
	 * Documentation URL shown via the Integrations tab help button.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_help_url() {

		return Helpers::get_utm_url(
			self::DOCS_URL,
			[
				'content' => 'Help',
				'medium'  => 'plugin-settings-integrations-google-maps',
			]
		);
	}

	/**
	 * A local (non Product-API) integration — exempt from the disabled state.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_local() {

		return true;
	}

	/**
	 * Connection status: active when an API key is saved, else not_connected.
	 *
	 * Presence-only by design — a key that failed verify_api_key() is never
	 * saved (handle_post() returns before persisting), so a saved non-empty
	 * key counts as connected without needing to re-verify here.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_status() {

		$api_key = (string) Options::get( 'maps_google_api_key', '' );

		return $api_key !== '' ? 'active' : 'not_connected';
	}

	/**
	 * Render the content panel body (description + API Key field).
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function render_content() {

		$api_key_link_url       = 'https://developers.google.com/maps/documentation/javascript/get-api-key';
		$documentation_link_url = Helpers::get_utm_url(
			self::DOCS_URL,
			[
				'content' => 'our documentation',
				'medium'  => 'settings-integrations-google-maps',
			]
		);

		// The content panel's title bar already shows the "Google Maps" name +
		// status badge, so the body renders only the description (no repeated
		// title), then the API Key field via the shared UI helper. The body is a
		// flex column with gap:30px, so the description carries no margin of its
		// own (mirroring the Zoom description). The integration-panel row look is
		// applied in CSS to the helper's .sugar-calendar-setting-row output.
		?>
		<p class="sugar-calendar-google-maps__description">
			<?php
			printf(
				wp_kses(
					/* translators: %1$s - Google Maps API Key link URL; %2$s - Documentation link URL. */
					__( 'In order to display maps with pins and dynamic views, you\'ll need to obtain and enter your own <a href="%1$s" target="_blank">Google Maps API Key</a>.<br>If you need help, please refer to <a href="%2$s" target="_blank">our documentation</a>.', 'sugar-calendar-lite' ),
					[
						'a'  => [
							'href'   => [],
							'target' => [],
						],
						'br' => [],
					]
				),
				esc_url( $api_key_link_url ),
				esc_url( $documentation_link_url )
			);
			?>
		</p>
		<?php

		UI::password_input(
			[
				'name'     => 'maps_google_api_key',
				'id'       => 'maps_google_api_key',
				'value'    => (string) Options::get( 'maps_google_api_key', '' ),
				'label'    => esc_html__( 'API Key', 'sugar-calendar-lite' ),
				'required' => true,
			]
		);
	}

	/**
	 * Verify an API key against Google before it is accepted.
	 *
	 * Calls the Geocoding API as a lightweight, low-cost way to exercise the
	 * key, and returns Google's own `status` value as-is so the caller can
	 * give each of Google's 7 documented statuses its own tailored notice
	 * (see get_verification_notice()) instead of collapsing them into a
	 * generic valid/invalid bucket. Two synthetic statuses cover cases
	 * Google's `status` field can't express: 'INVALID_KEY' (a REQUEST_DENIED
	 * specifically caused by the key itself being invalid — a merely
	 * restricted or rate-limited key also returns REQUEST_DENIED, but with a
	 * different error_message, and must not be treated the same) and
	 * 'UNREACHABLE' (network failure or an unparseable response — a soft-fail
	 * so a transient Google outage can't lock an admin out of saving a
	 * working key).
	 *
	 * @since 3.12.0
	 *
	 * @param string $api_key The API key to verify.
	 *
	 * @return string 'INVALID_KEY', 'UNREACHABLE', 'UNEXPECTED_STATUS' (a
	 *                missing/unparseable status field), or one of Google's
	 *                Geocoding `status` values (e.g. 'OK', 'REQUEST_DENIED',
	 *                the literal 'UNKNOWN_ERROR').
	 */
	private function verify_api_key( $api_key ) {

		// add_query_arg() doesn't URL-encode its values, and esc_url_raw()
		// silently strips (not escapes) characters like `"`/`>` — an
		// unencoded key containing one would reach Google truncated.
		$url = add_query_arg(
			[
				'address' => 'New York',
				'key'     => rawurlencode( $api_key ),
			],
			'https://maps.googleapis.com/maps/api/geocode/json'
		);

		$response = wp_remote_get( esc_url_raw( $url ), [ 'timeout' => 5 ] );

		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return 'UNREACHABLE';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return 'UNREACHABLE';
		}

		// Guards against a malformed response where status/error_message
		// decode to an array instead of a string (fatals downstream otherwise).
		$status = isset( $body['status'] ) && is_string( $body['status'] ) ? $body['status'] : '';

		if (
			$status === 'REQUEST_DENIED'
			&& isset( $body['error_message'] )
			&& is_string( $body['error_message'] )
			&& stripos( $body['error_message'], 'provided API key is invalid' ) !== false
		) {
			return 'INVALID_KEY';
		}

		// A missing/unparseable status isn't Google's own 'UNKNOWN_ERROR'.
		return $status !== '' ? $status : 'UNEXPECTED_STATUS';
	}

	/**
	 * Map a verify_api_key() status to an admin notice.
	 *
	 * Every known non-OK status Google can return, plus the statuses
	 * synthesized by verify_api_key(), gets its own tailored message. A
	 * method rather than a class const, since a const can't hold the
	 * esc_html__() calls the messages need.
	 *
	 * @since 3.12.0
	 *
	 * @param string $status A verify_api_key() return value.
	 *
	 * @return array{block: bool, type: string, message: string}|null Null for
	 *               'OK' — no extra notice needed beyond "Settings saved."
	 */
	private function get_verification_notice( $status ) {

		if ( $status === 'OK' ) {
			return null;
		}

		$notices = [
			'INVALID_KEY'       => [
				'block'   => true,
				'type'    => WP::ADMIN_NOTICE_ERROR,
				'message' => esc_html__( 'That API key doesn\'t look valid. Please check it and enter it again.', 'sugar-calendar-lite' ),
			],
			'REQUEST_DENIED'    => [
				'block'   => false,
				'type'    => WP::ADMIN_NOTICE_WARNING,
				'message' => esc_html__( 'Google denied this request. Geocoding isn\'t enabled or the key is restricted to specific sites or IPs. Check Google Cloud Console if your map doesn\'t load.', 'sugar-calendar-lite' ),
			],
			'OVER_DAILY_LIMIT'  => [
				'block'   => false,
				'type'    => WP::ADMIN_NOTICE_WARNING,
				'message' => esc_html__( 'This key has reached its daily usage limit on Google. Your maps may not load until the limit resets.', 'sugar-calendar-lite' ),
			],
			'OVER_QUERY_LIMIT'  => [
				'block'   => false,
				'type'    => WP::ADMIN_NOTICE_WARNING,
				'message' => esc_html__( 'This key has hit Google\'s rate limit from too many requests. Your maps may be temporarily unavailable.', 'sugar-calendar-lite' ),
			],
			'ZERO_RESULTS'      => [
				'block'   => false,
				'type'    => WP::ADMIN_NOTICE_WARNING,
				'message' => esc_html__( 'Google accepted this key, but our test lookup returned no results. Please check that your maps load correctly on your site.', 'sugar-calendar-lite' ),
			],
			'UNKNOWN_ERROR'     => [
				'block'   => false,
				'type'    => WP::ADMIN_NOTICE_WARNING,
				'message' => esc_html__( 'Google had a temporary problem while checking this key. Please check that your maps load correctly on your site.', 'sugar-calendar-lite' ),
			],
			'INVALID_REQUEST'   => [
				'block'   => false,
				'type'    => WP::ADMIN_NOTICE_WARNING,
				'message' => esc_html__( 'Google couldn\'t complete our check for this key. Please check that your maps load correctly on your site.', 'sugar-calendar-lite' ),
			],
			'UNREACHABLE'       => [
				'block'   => false,
				'type'    => WP::ADMIN_NOTICE_WARNING,
				'message' => esc_html__( 'We couldn\'t reach Google to check this key. Please check that your maps load correctly on your site.', 'sugar-calendar-lite' ),
			],
			'UNEXPECTED_STATUS' => [
				'block'   => false,
				'type'    => WP::ADMIN_NOTICE_WARNING,
				'message' => esc_html__( 'Google returned an unexpected response while we checked this key. Please check that your maps load correctly on your site.', 'sugar-calendar-lite' ),
			],
		];

		return $notices[ $status ] ?? $notices['UNEXPECTED_STATUS'];
	}

	/**
	 * Persist the API key. Called by SettingsIntegrationsTab::handle_post().
	 *
	 * @since 3.12.0
	 *
	 * @param array $post_data Posted `sugar-calendar` settings array.
	 *
	 * @return void
	 */
	public function handle_post( $post_data = [] ) {

		$api_key      = sanitize_text_field( $post_data['maps_google_api_key'] ?? '' );
		$existing_key = (string) Options::get( 'maps_google_api_key', '' );

		if ( $api_key !== '' && $api_key !== $existing_key ) {
			$notice = $this->get_verification_notice( $this->verify_api_key( $api_key ) );

			if ( $notice !== null ) {
				WP::add_admin_notice( $notice['message'], $notice['type'] );

				if ( $notice['block'] ) {
					return;
				}
			}
		}

		Options::update( 'maps_google_api_key', $api_key );

		WP::add_admin_notice( esc_html__( 'Settings saved.', 'sugar-calendar-lite' ), WP::ADMIN_NOTICE_SUCCESS );
	}
}
