<?php

namespace Sugar_Calendar\Migrations;

use Sugar_Calendar\Helpers\WP;
use WP_Upgrader;

/**
 * Class Migrations.
 *
 * @since 3.0.0
 */
class Migrations {

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		// Initialize migrations during request in the admin panel only.
		add_action( 'admin_init', [ $this, 'init_migrations_on_request' ] );

		// Initialize migrations after plugin update.
		add_action( 'upgrader_process_complete', [ $this, 'init_migrations_after_upgrade' ], PHP_INT_MAX, 2 );
		add_action(
			'wp_ajax_nopriv_sugar_calendar_init_migrations',
			[ $this, 'init_migrations_ajax_handler' ]
		);
	}

	/**
	 * Initialize DB migrations during request.
	 *
	 * @since 3.0.0
	 */
	public function init_migrations_on_request() {

		// Do not initialize migrations during AJAX and cron requests.
		if ( WP::is_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$this->init_migrations();
	}

	/**
	 * Initialize DB migrations.
	 *
	 * @since 3.0.0
	 */
	private function init_migrations() {

		$migrations = $this->get_migrations();

		foreach ( $migrations as $migration ) {
			if ( is_subclass_of( $migration, MigrationAbstract::class ) && $migration::is_enabled() ) {
				( new $migration() )->init();
			}
		}
	}

	/**
	 * Get migrations classes.
	 *
	 * @since 3.0.0
	 *
	 * @return array Migrations classes.
	 */
	private function get_migrations() {

		$migrations = [
			Migration::class,
		];

		/**
		 * Filters DB migrations classes.
		 *
		 * @since 3.0.0
		 *
		 * @param array $migrations Migrations classes.
		 */
		return apply_filters( 'sugar_calendar_migrations_get_migrations', $migrations );
	}

	/**
	 * Initialize DB migrations after plugin update.
	 * Initiate ajax call to perform the migration with the new plugin version code.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Upgrader $upgrader WP_Upgrader instance.
	 * @param array       $options  Array of update data.
	 */
	public function init_migrations_after_upgrade( $upgrader, $options ) {

		if (
			// Skip if in admin panel.
			( is_admin() && ! wp_doing_ajax() ) ||
			// Skip if it's update from plugins list page.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			( wp_doing_ajax() && isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'update-plugin' )
		) {
			return;
		}

		$plugins = [];

		if ( isset( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$plugins = $options['plugins'];
		} elseif ( isset( $options['plugin'] ) && is_string( $options['plugin'] ) ) {
			$plugins = [ $options['plugin'] ];
		}

		if (
			! in_array( 'sugar-calendar-lite/sugar-calendar-lite.php', $plugins, true ) &&
			! in_array( 'sugar-calendar/sugar-calendar.php', $plugins, true )
		) {
			return;
		}

		$url = add_query_arg(
			[
				'action' => 'sugar_calendar_init_migrations',
			],
			admin_url( 'admin-ajax.php' )
		);

		$timeout = (int) ini_get( 'max_execution_time' );

		$args = [
			'sslverify' => false,
			'timeout'   => $timeout ? $timeout : 30,
		];

		wp_remote_post( $url, $args );
	}

	/**
	 * Initialize migrations via AJAX request.
	 *
	 * @since 3.0.0
	 */
	public function init_migrations_ajax_handler() {

		$this->init_migrations();

		wp_send_json_success();
	}
}
