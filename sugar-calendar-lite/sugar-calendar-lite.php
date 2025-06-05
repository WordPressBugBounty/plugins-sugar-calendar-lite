<?php
/**
 * Plugin Name:       Sugar Calendar (Lite)
 * Plugin URI:        https://sugarcalendar.com
 * Description:       A calendar with a sweet disposition.
 * Author:            Sugar Calendar
 * Author URI:        https://sugarcalendar.com
 * License:           GNU General Public License v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sugar-calendar-lite
 * Domain Path:       /assets/languages
 * Requires PHP:      7.4
 * Requires at least: 5.9
 * Version:           3.7.2
 */

// Exit if accessed directly.
use Sugar_Calendar\Admin\Addons\Requirements;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SC_PLUGIN_VERSION' ) ) {
	/**
	 * Plugin version.
	 *
	 * @since 3.0.0
	 */
	define( 'SC_PLUGIN_VERSION', '3.7.2' );
}

if ( ! defined( 'SC_PLUGIN_FILE' ) ) {

	/**
	 * Plugin file.
	 *
	 * @since 3.0.0
	 */
	define( 'SC_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'SC_PLUGIN_BASE' ) ) {

	/**
	 * Plugin file.
	 *
	 * @since 3.0.0
	 */
	define( 'SC_PLUGIN_BASE', plugin_basename( SC_PLUGIN_FILE ) );
}

if ( ! defined( 'SC_PLUGIN_DIR' ) ) {

	/**
	 * Plugin directory.
	 *
	 * @since 3.0.0
	 */
	define( 'SC_PLUGIN_DIR', trailingslashit( plugin_dir_path( SC_PLUGIN_FILE ) ) );
}

if ( ! defined( 'SC_PLUGIN_URL' ) ) {

	/**
	 * Plugin URL.
	 *
	 * @since 3.0.0
	 */
	define( 'SC_PLUGIN_URL', trailingslashit( plugin_dir_url( SC_PLUGIN_FILE ) ) );
}

if ( ! defined( 'SC_PLUGIN_ASSETS_URL' ) ) {
	/**
	 * Plugin assets URL.
	 *
	 * @since 3.0.0
	 */
	define( 'SC_PLUGIN_ASSETS_URL', SC_PLUGIN_URL . 'assets/' );
}

// Make sure CAL_GREGORIAN is defined.
if ( ! defined( 'CAL_GREGORIAN' ) ) {
	/**
	 * Calendar type.
	 *
	 * @since 3.0.0
	 */
	define( 'CAL_GREGORIAN', 1 );
}

if ( ! defined( 'EP_CALENDARS' ) ) {
	/**
	 * Calendar endpoint mask.
	 *
	 * @since 3.0.0
	 */
	define( 'EP_CALENDARS', 512 * 512 );
}

if ( function_exists( 'sugar_calendar' ) ) {

	if ( ! function_exists( 'deactivate_sugar_calendar_lite' ) ) {

		/**
		 * Deactivate Sugar Calendar Lite.
		 *
		 * @since 3.1.2
		 */
		function deactivate_sugar_calendar_lite() {

			require_once ABSPATH . WPINC . '/pluggable.php';

			deactivate_plugins( 'sugar-calendar-lite/sugar-calendar-lite.php' );

			add_action( 'admin_notices', 'sugar_calendar_lite_deactivated_notice' );
		}
	}

	add_action( 'admin_init', 'deactivate_sugar_calendar_lite' );
}

if ( ! function_exists( 'sugar_calendar_lite_deactivated_notice' ) ) {

	/**
	 * Display a notice that Sugar Calendar Lite has been de-activated.
	 *
	 * @since 3.1.2
	 */
	function sugar_calendar_lite_deactivated_notice() {

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Sugar Calendar PRO version is activated. We de-activated the Sugar Calendar Lite version.', 'sugar-calendar-lite' ) . '</p></div>';
	}
}

/**
 * This class_exists() check avoids a fatal error when this plugin is activated
 * in more than one way and should not be removed.
 */
if ( ! class_exists( 'Sugar_Calendar\\Requirements_Check' ) ) {

	// Include the Requirements file
	include_once dirname( __FILE__ ) . '/requirements-check.php';

	// Invoke the checker
	if ( class_exists( 'Sugar_Calendar\\Requirements_Check' ) ) {
		new Sugar_Calendar\Requirements_Check( __FILE__ );
	}
}

if ( ! function_exists( 'sugar_calendar_check_requirements' ) ) {

	/**
	 * Check addon requirements.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	function sugar_calendar_check_requirements( array $requirements ) {

		return Requirements::get_instance()->validate( $requirements );
	}
}

if ( ! function_exists( 'sugar_calendar' ) ) {

	/**
	 * Return the one Sugar Calendar instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Sugar_Calendar\Plugin
	 */
	function sugar_calendar() {

		return Sugar_Calendar\Plugin::instance();
	}
}
