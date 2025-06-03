<?php

namespace Sugar_Calendar\Helpers;

use Plugin_Upgrader;
use WP_Ajax_Upgrader_Skin;
use WP_Error;

/**
 * Plugin install helper.
 *
 * @since 3.7.0
 */
class Installer {

	/**
	 * Filesystem permissions error.
	 *
	 * @since 3.7.0
	 */
	const ERROR_FILESYSTEM_PERMISSIONS = 'filesystem_permissions';

	/**
	 * Filesystem initialization error.
	 *
	 * @since 3.7.0
	 */
	const ERROR_FILESYSTEM_INITIALIZATION = 'filesystem_initialization';

	/**
	 * Plugin upgrader error.
	 *
	 * @since 3.7.0
	 */
	const ERROR_PLUGIN_UPGRADER = 'plugin_upgrader';

	/**
	 * Plugin installation error.
	 *
	 * @since 3.7.0
	 */
	const ERROR_PLUGIN_NOT_INSTALLED = 'plugin_not_installed';

	/**
	 * Plugin activation error.
	 *
	 * @since 3.7.0
	 */
	const ERROR_PLUGIN_NOT_ACTIVE = 'plugin_not_active';

	/**
	 * Install a plugin.
	 *
	 * @since 3.7.0
	 *
	 * @param string $credentials_url    Credentials URL.
	 * @param string $plugin_url         Plugin URL.
	 * @param bool   $activate           Whether to activate the plugin.
	 * @param bool   $check_capabilities Whether to check user capabilities.
	 * @param array  $args               Plugin installer arguments.
	 */
	public static function install_plugin( $credentials_url, $plugin_url, $activate = false, $check_capabilities = true, $args = [] ) {

		ob_start();

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$credentials = request_filesystem_credentials( $credentials_url, '', false, false );

		// Hide the filesystem credentials form.
		ob_end_clean();

		// Check for file system permissions.
		if ( $credentials === false ) {
			return new WP_Error( self::ERROR_FILESYSTEM_PERMISSIONS );
		}

		if ( ! WP_Filesystem( $credentials ) ) {
			return new WP_Error( self::ERROR_FILESYSTEM_INITIALIZATION );
		}

		/*
		 * We do not need any extra credentials if we have gotten this far, so let's install the plugin.
		 */

		// Do not allow WordPress to search/download translations, as this will break JS output.
		remove_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], 20 );

		/** \WP_Upgrader class */
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		/** \Plugin_Upgrader class */
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

		// Create the plugin upgrader with our custom skin.
		$installer = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );

		// Error check.
		if ( ! method_exists( $installer, 'install' ) ) {
			return new WP_Error( self::ERROR_PLUGIN_UPGRADER );
		}

		$installer->install( $plugin_url, $args );

		// Flush the cache and return the newly installed plugin basename.
		wp_cache_flush();

		$plugin_basename = $installer->plugin_info();

		if ( empty( $plugin_basename ) ) {
			return new WP_Error( self::ERROR_PLUGIN_NOT_INSTALLED );
		}

		if ( ! $activate ) {
			return $plugin_basename;
		}

		// Check for permissions.
		if ( $check_capabilities && ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( self::ERROR_PLUGIN_NOT_ACTIVE );
		}

		// Activate the plugin silently.
		$activated = activate_plugin( $plugin_basename );

		if ( ! is_wp_error( $activated ) ) {

			/**
			 * Fire after plugin activating via the Sugar Calendar installer.
			 *
			 * @since 3.7.0
			 *
			 * @param string $plugin_basename Path to the plugin file relative to the plugins' directory.
			 */
			do_action( 'sugar_calendar_plugin_activated', $plugin_basename );

			return $plugin_basename;
		}

		// Fallback error just in case.
		return new WP_Error( self::ERROR_PLUGIN_NOT_ACTIVE );
	}
}
