<?php

namespace Sugar_Calendar\UsageTracking;

use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Options;
use Sugar_Calendar\Plugin;

/**
 * Usage Tracker functionality.
 *
 * @since 3.0.0
 */
class UsageTracking {

	/**
	 * The slug that will be used to save the option of Usage Tracker.
	 *
	 * @since 3.0.0
	 */
	const SETTINGS_SLUG = 'allow_usage_tracking';

	/**
	 * Load usage tracking functionality.
	 *
	 * @since 3.0.0
	 */
	public function load() {

		/**
		 * Whether loading the usage tracking functionality is allowed.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $allowed Whether to load usage tracking functionality.
		 */
		if ( ! (bool) apply_filters( 'sugar_calendar_usage_tracking_load_allowed', true ) ) { // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			return;
		}

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function hooks() {

		// Deregister the action if option is disabled.
		add_action(
			'sugar_calendar_options_set_after',
			function () {

				if ( ! $this->is_enabled() ) {
					( new SendUsageTask() )->cancel();
				}
			}
		);

		// Register the action handler only if enabled.
		if ( $this->is_enabled() ) {
			add_filter(
				'sugar_calendar_tasks_get_tasks',
				static function ( $tasks ) {

					$tasks[] = SendUsageTask::class;

					return $tasks;
				}
			);
		}
	}

	/**
	 * Whether Usage Tracking is enabled.
	 * Needs to check with a fresh copy of options in order to provide accurate results.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function is_enabled() {

		/**
		 * Whether usage tracking is enabled.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $enabled Whether usage tracking is enabled.
		 */
		return (bool) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_usage_tracking_is_enabled',
			Options::get( self::SETTINGS_SLUG, false )
		);
	}

	/**
	 * Get the User Agent string that will be sent to the API.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_user_agent() {

		return Helpers::get_default_user_agent();
	}

	/**
	 * Get data for sending to the server.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_data() {

		global $wpdb;

		$theme_data = wp_get_theme();
		$events     = sugar_calendar_get_event_counts();

		$data = array_merge(
			$this->get_required_data(),
			$this->get_additional_data(),
			[
				// Generic data (environment).
				'mysql_version'                                 => $wpdb->db_version(),
				'server_version'                                => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
				'is_ssl'                                        => is_ssl(),
				'is_multisite'                                  => is_multisite(),
				'sites_count'                                   => $this->get_sites_total(),
				'theme_name'                                    => $theme_data->name,
				'theme_version'                                 => $theme_data->version,
				'locale'                                        => get_locale(),
				'timezone_offset'                               => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '',
				// Sugar Calendar - specific data.
				'sugar_calendar_version'                        => SC_PLUGIN_VERSION,
				'sugar_calendar_activated'                      => get_option( 'sugar_calendar_activated_time', 0 ),
				'sugar_calendar_source'                         => sanitize_title( get_option( 'sugar_calendar_source', '' ) ),
				'sugar_calendar_settings_general_editor'        => sanitize_title( Options::get( 'editor_type', 'classic' ) ),
				'sugar_calendar_settings_general_custom_fields' => (bool) Options::get( 'custom_fields', false ),
				'sugar_calendar_total_events'                   => ! empty( $events['total'] ) ? intval( $events['total'] ) : 0,
				'sugar_calendar_total_calendars'                => intval( wp_count_terms( sugar_calendar_get_calendar_taxonomy_id() ) ),
			]
		);

		/**
		 * Filter usage tracking data.
		 *
		 * @since 3.0.0
		 *
		 * @param array $data Usage tracking data.
		 */
		return apply_filters( 'sugar_calendar_usage_tracking_get_data', $data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Get the required request data.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_required_data() {

		return [
			'url'            => home_url(),
			'php_version'    => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'wp_version'     => get_bloginfo( 'version' ),
			'active_plugins' => $this->get_active_plugins(),
		];
	}

	/**
	 * Get the additional data required by the usage tracking API.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_additional_data() {

		$activated_dates = get_option( 'sugar_calendar_activated', [] );

		return [
			'sugar_calendar_license_key'         => Plugin::instance()->get_license_key(),
			'sugar_calendar_license_type'        => Plugin::instance()->get_license_type(),
			'sugar_calendar_is_pro'              => Plugin::instance()->is_pro(),
			'sugar_calendar_lite_installed_date' => $this->get_installed( $activated_dates, 'lite' ),
			'sugar_calendar_pro_installed_date'  => $this->get_installed( $activated_dates, 'pro' ),
		];
	}

	/**
	 * Get the list of active plugins.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_active_plugins() {

		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$active_plugins = [];

		foreach ( get_mu_plugins() as $path => $plugin ) {
			$active_plugins[ $path ] = isset( $plugin['Version'] ) ? $plugin['Version'] : 'Not Set';
		}

		foreach ( get_plugins() as $path => $plugin ) {
			if ( is_plugin_active( $path ) ) {
				$active_plugins[ $path ] = isset( $plugin['Version'] ) ? $plugin['Version'] : 'Not Set';
			}
		}

		return $active_plugins;
	}

	/**
	 * Installed date.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $activated_dates Input array with dates.
	 * @param string $key             Input key what you want to get.
	 *
	 * @return mixed
	 */
	private function get_installed( $activated_dates, $key ) {

		if ( ! empty( $activated_dates[ $key ] ) ) {
			return $activated_dates[ $key ];
		}

		return false;
	}

	/**
	 * Total number of sites.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	private function get_sites_total() {

		return function_exists( 'get_blog_count' ) ? (int) get_blog_count() : 1;
	}
}
