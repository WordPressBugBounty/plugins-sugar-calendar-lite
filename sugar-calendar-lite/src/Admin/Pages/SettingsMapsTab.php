<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Options;
use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Plugin;
/**
 * Maps Settings tab.
 *
 * @since 3.0.0
 */
class SettingsMapsTab extends Settings {

	/**
	 * Page tab slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'maps';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Maps', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 10;
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function hooks() {

		// Load assets.
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		// Handle POST requests.
		add_action( 'sugar_calendar_ajax_verify_maps_api_key', [ $this, 'handle_verify_maps_api_key' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-settings' );
		wp_enqueue_script( 'sugar-calendar-admin-settings' );

		wp_enqueue_script(
			'sugar-calendar-admin-settings-maps',
			SC_PLUGIN_ASSETS_URL . 'js/admin-settings-maps' . WP::asset_min() . '.js',
			[ 'jquery' ],
			Sugar_Calendar_Helpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-settings',
			'sugar_calendar_admin_settings',
			[
				'ajax_url'   => Plugin::instance()->get_admin()->ajax_url(),
				'plugin_url' => SC_PLUGIN_URL,
				'text'       => [
					'ok'    => esc_html__( 'Continue', 'sugar-calendar-lite' ),
				],
			]
		);
	}

	/**
	 * Output setting fields.
	 *
	 * @since 3.0.0
	 *
	 * @param string $section Settings section.
	 */
	protected function display_tab( $section = '' ) {

		$api_key_link_url       = 'https://developers.google.com/maps/documentation/javascript/get-api-key';
		$documentation_link_url = Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/using-google-maps-to-display-event-location',
			[
				'content' => 'our documentation',
				'medium'  => 'settings-maps',
			]
		);

		UI::heading(
			[
				'title'       => esc_html__( 'Google Maps', 'sugar-calendar-lite' ),
				'description' => sprintf( /* translators: %1$s - Google Maps API Key link url; %1$s - Documentation link url. */
					__( 'In order to display maps with pins and dynamic views, you\'ll need to obtain and enter your own <a href="%1$s" target="_blank">Google Maps API Key</a>.<br>If you need help, please refer to <a href="%2$s" target="_blank">our documentation</a>.', 'sugar-calendar-lite' ),
					esc_url( $api_key_link_url ),
					esc_url( $documentation_link_url )
				),
			]
		);

		$api_key = Options::get( 'maps_google_api_key', '' );

		UI::password_input(
			[
				'name'     => 'maps_google_api_key',
				'id'       => 'maps_google_api_key',
				'value'    => $api_key,
				'label'    => esc_html__( 'API Key', 'sugar-calendar-lite' ),
				'required' => true,
			]
		);
	}

	/**
	 * Handle verify maps API key.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function handle_verify_maps_api_key() {

		// Bail if maps_google_api_key is not set.
		if ( ! isset( $_POST['api_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error();
		}

		$maps_api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$response = [
			'success' => false,
			'message' => '',
		];

		// Test the API key.
		$maps_api_key_check = Sugar_Calendar_Helpers::verify_google_maps_api_key( $maps_api_key );

		// If the API key is string it is not valid.
		if ( $maps_api_key_check['success'] ) {

			$response['success'] = true;
			$response['message'] = esc_html__( 'Settings saved.', 'sugar-calendar-lite' );

		} else {

			$message = sprintf(
				'<p>%1$s: %2$s</p>',
				__( 'An error occurred while connecting to Google Maps Services', 'sugar-calendar-lite' ),
				$maps_api_key_check['message']
			);

			// If code is REQUEST_DENIED, we need to show the required APIs.
			if (
				$maps_api_key_check['code'] === 'REQUEST_DENIED'
				&&
				$maps_api_key_check['message'] === 'This API project is not authorized to use this API.'
			) {

				$message .= sprintf(
					'<p>The provided Google Maps API key does not support the <a href="%1$s" target="_blank">Google Geocoding API service</a> or the <a href="%2$s" target="_blank">Google Maps JavaScript API</a>. Please enable them in your Google Console.</p>',
					esc_url( 'https://console.cloud.google.com/apis/library/geocoding-backend.googleapis.com' ),
					esc_url( 'https://console.cloud.google.com/apis/library/maps-backend.googleapis.com' )
				);
			}

			$message .= sprintf(
				'<p>%2$s <a href="%1$s" target="_blank">%3$s</a> %4$s.</p>',
				esc_url(
					Helpers::get_utm_url(
						'https://sugarcalendar.com/docs/using-google-maps-to-display-event-location',
						[
							'content' => 'our documentation',
							'medium'  => 'settings-maps',
						]
					)
				),
				__( 'Please read', 'sugar-calendar-lite' ),
				__( 'our documentation', 'sugar-calendar-lite' ),
				__( 'on how to set up Google Maps in Sugar Calendar', 'sugar-calendar-lite' ),
			);

			$response['message'] = $message;
		}

		// Return response.
		wp_send_json_success( $response );
	}

	/**
	 * Handle POST requests.
	 *
	 * @since 3.0.0
	 *
	 * @param array $post_data Post data.
	 */
	public function handle_post( $post_data = [] ) {

		$api_key = $post_data['maps_google_api_key'] ?? '';
		$api_key = sanitize_text_field( $api_key );

		Options::update( 'maps_google_api_key', $api_key );

		WP::add_admin_notice( esc_html__( 'Settings saved.', 'sugar-calendar-lite' ), WP::ADMIN_NOTICE_SUCCESS );
	}
}
