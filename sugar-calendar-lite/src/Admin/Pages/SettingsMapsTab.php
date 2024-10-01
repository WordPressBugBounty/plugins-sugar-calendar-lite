<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Options;

/**
 * Maps Settings tab.
 *
 * @since 3.0.0
 */
class SettingsMapsTab extends Settings {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sc-settings';
	}

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

		return esc_html__( 'Maps', 'sugar-calendar' );
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
				'title'       => esc_html__( 'Google Maps', 'sugar-calendar' ),
				'description' => sprintf( /* translators: %1$s - Google Maps API Key link url; %1$s - Documentation link url. */
					__( 'In order to display maps with pins and dynamic views, you’ll need to obtain and enter your own <a href="%1$s" target="_blank">Google Maps API Key</a>.<br>If you need help, please refer to <a href="%2$s" target="_blank">our documentation</a>.', 'sugar-calendar' ),
					esc_url( $api_key_link_url ),
					esc_url( $documentation_link_url )
				),
			]
		);

		$api_key = Options::get( 'maps_google_api_key', '' );

		UI::password_input(
			[
				'name'  => 'maps_google_api_key',
				'id'    => 'maps_google_api_key',
				'value' => $api_key,
				'label' => esc_html__( 'API Key', 'sugar-calendar' ),
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

		WP::add_admin_notice( esc_html__( 'Settings saved.', 'sugar-calendar' ), WP::ADMIN_NOTICE_SUCCESS );
	}
}
