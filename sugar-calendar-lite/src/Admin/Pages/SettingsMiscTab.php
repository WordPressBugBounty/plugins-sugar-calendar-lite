<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Options;

/**
 * General Settings tab.
 *
 * @since 3.0.0
 */
class SettingsMiscTab extends Settings {

	/**
	 * Page tab slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'misc';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Misc', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 70;
	}

	/**
	 * Output setting fields.
	 *
	 * @since 3.0.0
	 *
	 * @param string $section Section id.
	 */
	protected function display_tab( $section = '' ) {

		UI::heading(
			[
				'title' => esc_html__( 'Miscellaneous', 'sugar-calendar-lite' ),
			]
		);

		$hide_announcements = Options::get( 'hide_announcements', false );

		// Hide Announcements.
		UI::toggle_control(
			[
				'id'          => 'hide_announcements',
				'name'        => 'hide_announcements',
				'value'       => $hide_announcements,
				'label'       => esc_html__( 'Hide Announcements', 'sugar-calendar-lite' ),
				'description' => __( 'Hide plugin announcements and update details.', 'sugar-calendar-lite' ),
			]
		);

		/**
		 * Whether to allow tracking of user settings.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $allow Whether to allow tracking of user settings.
		 */
		if ( apply_filters( 'sugar_calendar_admin_pages_settings_misc_tab_show_allow_usage_tracking_setting', true ) ) {

			$allow_usage_tracking = Options::get( 'allow_usage_tracking', false );

			// Hide Announcements.
			UI::toggle_control(
				[
					'id'          => 'allow_usage_tracking',
					'name'        => 'allow_usage_tracking',
					'value'       => $allow_usage_tracking,
					'label'       => esc_html__( 'Allow Usage Tracking', 'sugar-calendar-lite' ),
					'description' => __( 'By allowing us to track usage data we can better help you because we know with which WordPress configurations, themes and plugins we should test.', 'sugar-calendar-lite' ),
				]
			);
		}
	}

	/**
	 * Handle POST requests.
	 *
	 * @since 3.0.0
	 *
	 * @param array $post_data Post data.
	 */
	public function handle_post( $post_data = [] ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$settings = [
			'hide_announcements',
			'allow_usage_tracking',
		];

		foreach ( $settings as $key ) {
			$value = $post_data[ $key ] ?? '';

			switch ( $key ) {
				case 'hide_announcements':
				case 'allow_usage_tracking':
					$value = isset( $post_data[ $key ] );
					break;
			}

			Options::update( $key, $value );
		}

		WP::add_admin_notice( esc_html__( 'Settings saved.', 'sugar-calendar-lite' ), WP::ADMIN_NOTICE_SUCCESS );
	}
}
