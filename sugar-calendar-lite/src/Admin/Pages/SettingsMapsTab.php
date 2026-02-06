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

		add_filter( 'sugar_calendar_helpers_ui_help_url', [ $this, 'help_url' ] );
	}

	/**
	 * Filter the help URL in the Settings page -> Maps tab.
	 *
	 * @since 3.8.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/events/using-google-maps-to-display-event-location/',
			[
				'content' => 'Help',
				'medium'  => 'plugin-settings-maps',
			]
		);
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.5.0
	 * @since 3.10.0 Removed the enqueued `admin-settings-maps.js`.
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-settings' );
		wp_enqueue_script( 'sugar-calendar-admin-settings' );

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
			'https://sugarcalendar.com/docs/events/using-google-maps-to-display-event-location/',
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
