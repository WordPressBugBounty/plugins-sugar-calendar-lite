<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Options;
use Sugar_Calendar\Helpers as BaseHelper;
use Sugar_Calendar\Integrations\IntegrationsDisabler;

/**
 * General Settings tab.
 *
 * @since 3.0.0
 */
class SettingsMiscTab extends Settings {

	/**
	 * Hooks.
	 *
	 * @since 3.8.0
	 */
	public function hooks() {

		parent::hooks();

		add_filter( 'sugar_calendar_helpers_ui_help_url', [ $this, 'help_url' ] );
	}

	/**
	 * Enqueue page assets.
	 *
	 * Adds the jQuery-Confirm library + localized strings used to confirm the
	 * Lite "Disable Integrations" toggle before it tears down every connection.
	 * Scoped to Lite, where that toggle is the only one rendered.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		parent::enqueue_assets();

		// The Disable Integrations toggle (and its confirm) only exist on Lite.
		if ( sugar_calendar()->is_pro() ) {
			return;
		}

		// Use the Sugar Calendar jQuery-Confirm theme (a full stylesheet that
		// replaces the vendor CSS), not the raw vendor style — the raw style
		// would load after the theme and override it (icon inline instead of
		// centered). Mirrors how Connect enqueues the confirm dialog.
		wp_enqueue_style( 'sugar-calendar-admin-confirm' );
		wp_enqueue_script( 'sugar-calendar-vendor-jquery-confirm' );

		wp_localize_script(
			'sugar-calendar-admin-settings',
			'sugar_calendar_admin_settings_misc',
			[
				'disable_integrations_confirm' => [
					'title'   => esc_html__( 'Disable Integrations?', 'sugar-calendar-lite' ),
					'message' => esc_html__( 'All existing integration connections will be permanently removed. This action cannot be undone.', 'sugar-calendar-lite' ),
					'confirm' => esc_html__( 'Disable', 'sugar-calendar-lite' ),
					'cancel'  => esc_html__( 'Cancel', 'sugar-calendar-lite' ),
					'icon'    => SC_PLUGIN_ASSETS_URL . 'images/icons/exclamation-circle-solid-yellow.svg',
				],
			]
		);
	}

	/**
	 * Filter the help URL in the Settings page -> Misc tab.
	 *
	 * @since 3.8.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return BaseHelper\Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/',
			[
				'content' => 'Help',
				'medium'  => 'plugin-settings-misc',
			]
		);
	}

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

		// Lite-only kill-switch: disables every external integration AND usage
		// tracking (tracking is derived from this option). Pro is always enabled
		// and always tracks (forced via the sugar_calendar_usage_tracking_is_enabled
		// filter in includes/pro/Pro.php), so the toggle is never shown there.
		if ( ! sugar_calendar()->is_pro() ) {

			$disable_integrations = Options::get( 'disable_integrations', false );

			UI::toggle_control(
				[
					'id'          => 'disable_integrations',
					'name'        => 'disable_integrations',
					'value'       => $disable_integrations,
					'label'       => esc_html__( 'Disable Integrations', 'sugar-calendar-lite' ),
					'description' => esc_html__( 'Disconnect and disable all external integrations and usage tracking for Sugar Calendar.', 'sugar-calendar-lite' ),
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
	public function handle_post( $post_data = [] ) {

		// Hide Announcements (all editions).
		Options::update( 'hide_announcements', isset( $post_data['hide_announcements'] ) );

		// Disable Integrations is a Lite-only control; never let a Pro request
		// write it (the toggle is not rendered there).
		if ( ! sugar_calendar()->is_pro() ) {

			$was_disabled = (bool) Options::get( 'disable_integrations', false );
			$now_disabled = isset( $post_data['disable_integrations'] );

			Options::update( 'disable_integrations', $now_disabled );

			// On the off -> on transition, tear down every external connection.
			if ( ! $was_disabled && $now_disabled ) {
				( new IntegrationsDisabler() )->disable();
			}
		}

		WP::add_admin_notice( esc_html__( 'Settings saved.', 'sugar-calendar-lite' ), WP::ADMIN_NOTICE_SUCCESS );
	}
}
