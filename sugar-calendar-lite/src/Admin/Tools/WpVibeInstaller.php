<?php

namespace Sugar_Calendar\Admin\Tools;

use Sugar_Calendar\Helpers\Installer;

/**
 * Installs, activates, and reports the install state of the WPVibe.ai MCP
 * plugin. Extracted out of `ToolsAiTab` (issue #618 review) so the tab's
 * display logic isn't also the WPVibe install/activate controller. Lives
 * under `Admin\Tools` rather than `Admin\Pages` — it's a service, not a page.
 *
 * @since 3.13.0
 */
class WpVibeInstaller {

	/**
	 * WPVibe plugin basename on wp.org.
	 *
	 * @since 3.13.0
	 */
	const BASENAME = 'vibe-ai/vibe-ai.php';

	/**
	 * WPVibe wp.org download URL, used for the install AJAX call.
	 *
	 * @since 3.13.0
	 */
	const DOWNLOAD_URL = 'https://downloads.wordpress.org/plugin/vibe-ai.zip';

	/**
	 * WPVibe wp.org listing URL, used when the site disallows plugin installs
	 * from the dashboard.
	 *
	 * @since 3.13.0
	 */
	const WPORG_URL = 'https://wordpress.org/plugins/vibe-ai/';

	/**
	 * WPVibe top-level admin page slug, used for the "Set Up" link once active.
	 *
	 * @since 3.13.0
	 */
	const PAGE_SLUG = 'vibe-ai';

	/**
	 * Resolve the WPVibe install state.
	 *
	 * @since 3.13.0
	 *
	 * @return string One of 'not_installed', 'installed_inactive', 'active'.
	 */
	public function get_state() {

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		if ( ! array_key_exists( self::BASENAME, $plugins ) ) {
			return 'not_installed';
		}

		if ( ! is_plugin_active( self::BASENAME ) ) {
			return 'installed_inactive';
		}

		return 'active';
	}

	/**
	 * Install (and activate, if permitted) WPVibe.
	 *
	 * @since 3.13.0
	 *
	 * @param string $return_url Redirect target for the filesystem
	 *                            credentials form, if WordPress needs
	 *                            to prompt for them.
	 *
	 * @return array {
	 *     @type bool   $success      Whether the call succeeded.
	 *     @type bool   $is_activated Whether WPVibe ended up active.
	 *     @type string $basename     Plugin basename.
	 *     @type string $message      User-facing message.
	 * }
	 */
	public function install( $return_url ) {

		set_current_screen( 'events_page_sc-tools' );

		// This method only ever installs WPVibe — ignore any client-supplied
		// plugin URL and use the known-good constant, rather than trusting
		// the caller to name the plugin to install.
		$plugin_basename = Installer::install_plugin(
			$return_url,
			self::DOWNLOAD_URL,
			current_user_can( 'activate_plugins' )
		);

		if ( is_wp_error( $plugin_basename ) ) {

			// Installer::install_plugin() returns a WP_Error both when the
			// install itself failed AND when only the post-install activation
			// failed. In the latter case the plugin is installed — report
			// "installed_inactive" instead of a misleading install failure
			// (retrying the install would dead-end on the already-existing
			// plugin folder).
			if ( $this->get_state() !== 'not_installed' ) {
				return $this->result( true, false, self::BASENAME, __( 'Plugin installed.', 'sugar-calendar-lite' ) );
			}

			return $this->result( false, false, '', __( 'Could not install the plugin. Please download and install it manually.', 'sugar-calendar-lite' ) );
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return $this->result( true, false, $plugin_basename, __( 'Plugin installed.', 'sugar-calendar-lite' ) );
		}

		return $this->result( true, true, $plugin_basename, __( 'Plugin installed & activated.', 'sugar-calendar-lite' ) );
	}

	/**
	 * Activate an already-installed WPVibe.
	 *
	 * @since 3.13.0
	 *
	 * @return array Same shape as install().
	 */
	public function activate() {

		// This method only ever activates WPVibe — ignore any client-supplied
		// plugin basename and use the known-good constant, rather than
		// trusting the caller to name the plugin to activate.
		$activation_result = activate_plugin( self::BASENAME );

		if ( is_wp_error( $activation_result ) ) {
			return $this->result( false, false, self::BASENAME, wp_strip_all_tags( $activation_result->get_error_message() ) );
		}

		/** This action is documented in sugar-calendar/src/Helpers/Installer.php */
		// phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName, WPForms.Comments.PHPDocHooks.RequiredHookDocumentation
		do_action( 'sugar_calendar_plugin_activated', self::BASENAME );

		return $this->result( true, true, self::BASENAME, __( 'Plugin activated.', 'sugar-calendar-lite' ) );
	}

	/**
	 * Build an `install()`/`activate()` result array.
	 *
	 * @since 3.13.0
	 *
	 * @param bool   $success      Whether the call succeeded.
	 * @param bool   $is_activated Whether WPVibe ended up active.
	 * @param string $basename     Plugin basename.
	 * @param string $message      User-facing message.
	 *
	 * @return array Same shape as install().
	 */
	private function result( $success, $is_activated, $basename, $message ) {

		return [
			'success'      => $success,
			'is_activated' => $is_activated,
			'basename'     => $basename,
			'message'      => $message,
		];
	}
}
