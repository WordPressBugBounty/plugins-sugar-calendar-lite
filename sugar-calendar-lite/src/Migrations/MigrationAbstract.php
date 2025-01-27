<?php

namespace Sugar_Calendar\Migrations;

use Sugar_Calendar\Helpers\WP;

/**
 * Class MigrationAbstract helps migrate plugin options, DB tables and more.
 *
 * @since 3.0.0
 */
abstract class MigrationAbstract {

	/**
	 * Version of the latest migration.
	 *
	 * @since 3.0.0
	 *
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * Option key where we save the current migration version.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar_migration_version';

	/**
	 * Option key where we save any errors while performing migration.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	const ERROR_OPTION_NAME = 'sugar_calendar_migration_error';

	/**
	 * Current migration version, received from static::OPTION_NAME WP option.
	 *
	 * @since 3.0.0
	 *
	 * @var int
	 */
	protected $current_version;

	/**
	 * Migration constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		$this->current_version = static::get_current_version();
	}

	/**
	 * Initialize migration.
	 *
	 * @since 3.0.0
	 */
	public function init() {

		$this->maybe_migrate();
	}

	/**
	 * Whether migration is enabled.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public static function is_enabled() {

		return true;
	}

	/**
	 * Static on purpose, to get current DB version without __construct() and validation.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_current_version() {

		return (int) get_option( static::OPTION_NAME, 0 );
	}

	/**
	 * Update DB version in options table.
	 *
	 * @since 3.0.0
	 *
	 * @param int $version Version number.
	 */
	protected function update_db_ver( $version = 0 ) {

		$version = (int) $version;

		if ( empty( $version ) ) {
			$version = static::VERSION;
		}

		// Autoload it, because this value is checked all the time
		// and no need to request it separately from all autoloaded options.
		update_option( static::OPTION_NAME, $version, true );
	}

	/**
	 * Run the migration if needed.
	 *
	 * @since 3.0.0
	 */
	protected function maybe_migrate() {

		if ( version_compare( $this->current_version, static::VERSION, '<' ) ) {
			$this->run( static::VERSION );
		}
	}

	/**
	 * Prevent running the same migration twice.
	 * Run migration only when required.
	 *
	 * @since 3.0.0
	 *
	 * @param int $version The current migration version.
	 */
	protected function maybe_run_previous_migration( $version ) {

		if ( version_compare( $this->current_version, $version, '<' ) ) {
			$this->run( $version );
		}
	}

	/**
	 * Actual migration launcher.
	 *
	 * @since 3.0.0
	 *
	 * @param int $version The specified migration version to run.
	 */
	protected function run( $version ) {

		$version = (int) $version;

		if ( method_exists( $this, 'migrate_to_' . $version ) ) {
			$this->{'migrate_to_' . $version}();
		} elseif ( WP::in_wp_admin() ) {
			$message = sprintf( /* translators: %1$s - the DB option name, %2$s - Sugar Calendar, %3$s - error message. */
				esc_html__( 'There was an error while upgrading the %1$s database. Please contact %2$s support with this information: %3$s.', 'sugar-calendar-lite' ),
				static::OPTION_NAME,
				'<strong>Sugar Calendar</strong>',
				'<code>migration from v' . static::get_current_version() . ' to v' . static::VERSION . ' failed. Plugin version: v' . SC_PLUGIN_VERSION . '</code>'
			);

			WP::add_admin_notice( $message, WP::ADMIN_NOTICE_ERROR );
		}
	}
}
