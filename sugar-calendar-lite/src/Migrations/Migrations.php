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

		add_action(
			'wp_ajax_sugar_calendar_init_migrations',
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
			GeocodedCacheMigration::class,
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
	 * Initialize migrations via AJAX request.
	 *
	 * Authenticated admins only. The handler is gated by a nonce keyed on
	 * `sugar_calendar_init_migrations` and the `manage_options` capability.
	 *
	 * @since 3.0.0
	 */
	public function init_migrations_ajax_handler() {

		if ( ! check_ajax_referer( 'sugar_calendar_init_migrations', false, false ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'sugar-calendar-lite' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to run migrations.', 'sugar-calendar-lite' ) );
		}

		$this->init_migrations();

		wp_send_json_success();
	}
}
