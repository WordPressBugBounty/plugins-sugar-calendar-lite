<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;
use Sugar_Calendar\Helpers\WP;

/**
 * Calendar Migrate Tools tab.
 *
 * @since 3.3.0
 */
class ToolsMigrateTab extends Tools {

	/**
	 * Whether migration is possible.
	 *
	 * @since 3.3.0
	 *
	 * @var null|bool Whether migration is possible.
	 */
	private static $is_migration_possible = null;

	/**
	 * Whether a migration is possible.
	 *
	 * @since 3.3.0
	 *
	 * @return bool
	 */
	public static function is_migration_possible() {

		if ( ! is_null( self::$is_migration_possible ) ) {
			return (bool) self::$is_migration_possible;
		}

		// For now, we are only migrating from The Events Calendar.
		self::$is_migration_possible = TheEventCalendar::is_migration_possible();

		return (bool) self::$is_migration_possible;
	}

	/**
	 * Get the tab URL.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_url() {

		return add_query_arg(
			[
				'page'     => self::get_slug(),
				'section'  => self::get_tab_slug(),
				'importer' => 'the-events-calendar',
			],
			WP::admin_url( 'admin.php' )
		);
	}

	/**
	 * Register Export tab hooks.
	 *
	 * @since 3.3.0
	 */
	public function hooks() {

		parent::hooks();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 3.3.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( 'sugar-calendar-admin-importers' );
	}

	/**
	 * Page tab slug.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'migrate';
	}

	/**
	 * Page label.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Migrate', 'sugar-calendar-lite' );
	}

	/**
	 * Output the tab.
	 *
	 * @since 3.3.0
	 */
	protected function display_tab() {

		if ( self::is_migration_possible() ) {
			$this->display_importer_tab();

			return;
		}

		esc_html_e( 'No migration available.', 'sugar-calendar-lite' );
	}
}
