<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\WP;

/**
 * The Dashboard page's styles and scripts, and the modules and strings its script reads.
 *
 * @since 3.18.0
 */
class Assets {

	/**
	 * Modules the page script loads with import(): name => file under `assets/js/`, without `.min` or `.js`.
	 *
	 * @since 3.18.0
	 */
	const MODULES = [
		'updater'     => 'admin-dashboard-updater',
		'dateRange'   => 'admin-dashboard-date-range',
		'statCards'   => 'admin-dashboard-stat-cards',
		'activity'    => 'admin-dashboard-activity',
		'installer'   => 'admin-dashboard-installer',
		'growthTools' => 'admin-dashboard-growth-tools',
		'credits'     => 'admin-dashboard-credits',
	];

	/**
	 * Enqueue the page's styles and scripts.
	 *
	 * @since 3.18.0
	 *
	 * @param array $script_dependencies The page script's dependencies.
	 * @param array $localized_data      Data localized for the page script.
	 */
	public function enqueue( array $script_dependencies, array $localized_data ) {

		$this->enqueue_styles();
		$this->enqueue_vendor_scripts();
		$this->enqueue_scripts( $script_dependencies, $localized_data );
	}

	/**
	 * The page script's modules, each with its version-stamped URL.
	 *
	 * @since 3.18.0
	 *
	 * @return array Items: `name` and `path`.
	 */
	public static function get_modules(): array {

		$modules = [];

		foreach ( self::MODULES as $name => $handle ) {
			$modules[] = [
				'name' => $name,
				'path' => self::get_module_url( $handle ),
			];
		}

		return $modules;
	}

	/**
	 * Version-stamped URL of a module reached by import(), which WordPress never stamps.
	 *
	 * @since 3.18.0
	 *
	 * @param string $handle File name without `.min` or `.js`.
	 * @param string $folder Folder under `assets/`, with its trailing slash.
	 *
	 * @return string
	 */
	public static function get_module_url( string $handle, string $folder = 'js/' ): string {

		return add_query_arg(
			'ver',
			BaseHelpers::get_asset_version(),
			SC_PLUGIN_ASSETS_URL . $folder . $handle . WP::asset_min() . '.js'
		);
	}

	/**
	 * Strings the page script shows.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public static function get_i18n(): array {

		return [
			'reload'             => __( 'Reload', 'sugar-calendar-lite' ),
			'retry'              => __( 'Retry', 'sugar-calendar-lite' ),
			'session_expired'    => __( 'Your session has expired. Please reload the page.', 'sugar-calendar-lite' ),
			'network_failure'    => __( 'Something went wrong while loading that date range.', 'sugar-calendar-lite' ),
			'installing'         => __( 'Installing...', 'sugar-calendar-lite' ),
			'activating'         => __( 'Activating...', 'sugar-calendar-lite' ),
			'growth_tool_failed' => __( 'Something went wrong. Please try again.', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Enqueue the styles.
	 *
	 * @since 3.18.0
	 */
	private function enqueue_styles() {

		wp_enqueue_style(
			'sugar-calendar-admin-dashboard-page',
			SC_PLUGIN_ASSETS_URL . 'css/admin-dashboard-page' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_style(
			'sugar-calendar-flatpickr',
			SC_PLUGIN_ASSETS_URL . 'lib/flatpickr/flatpickr.min.css',
			[],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_style(
			'sugar-calendar-admin-integration-usage',
			SC_PLUGIN_ASSETS_URL . 'css/admin-integration-usage' . WP::asset_min() . '.css',
			[ 'sugar-calendar-admin-dashboard-page' ],
			BaseHelpers::get_asset_version()
		);
	}

	/**
	 * Enqueue the date picker and the chart library.
	 *
	 * @since 3.18.0
	 */
	private function enqueue_vendor_scripts() {

		wp_enqueue_script(
			'sugar-calendar-flatpickr',
			SC_PLUGIN_ASSETS_URL . 'lib/flatpickr/flatpickr.min.js',
			[],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_enqueue_script(
			'sugar-calendar-vendor-chart',
			SC_PLUGIN_ASSETS_URL . 'lib/chart.js/chart.min.js',
			[],
			'4.4.9',
			true
		);
	}

	/**
	 * Enqueue the page script with its data, and the credits usage script.
	 *
	 * @since 3.18.0
	 *
	 * @param array $dependencies   The page script's dependencies.
	 * @param array $localized_data Data localized for the page script.
	 */
	private function enqueue_scripts( array $dependencies, array $localized_data ) {

		wp_enqueue_script(
			'sugar-calendar-admin-dashboard-page',
			SC_PLUGIN_ASSETS_URL . 'js/admin-dashboard-page' . WP::asset_min() . '.js',
			$dependencies,
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script( 'sugar-calendar-admin-dashboard-page', 'sugar_calendar_admin_dashboard', $localized_data );

		wp_enqueue_script(
			'sugar-calendar-admin-integration-usage',
			SC_PLUGIN_ASSETS_URL . 'js/admin-integration-usage' . WP::asset_min() . '.js',
			[],
			BaseHelpers::get_asset_version(),
			true
		);
	}
}
