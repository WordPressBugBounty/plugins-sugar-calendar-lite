<?php

namespace Sugar_Calendar\PartnerPlugins;

use Sugar_Calendar\Helpers\ActionResult;
use Sugar_Calendar\Helpers\Installer as PluginInstaller;
use WP_Error;

/**
 * Install and activate one plugin: a Catalog partner or a Pro add-on.
 *
 * Every partner install goes through here; the caller decides what may be installed.
 * Partner welcome redirects are left intact.
 *
 * @since 3.15.0
 */
class Installer {

	/**
	 * Run the operation the target's current state allows.
	 *
	 * @since 3.15.0
	 *
	 * @param InstallTarget $target     Plugin to install or activate.
	 * @param string        $return_url Local admin return URL.
	 *
	 * @return array|WP_Error Service result or operation error.
	 */
	public function run( InstallTarget $target, $return_url ) {

		$state = $target->get_state();

		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'checklist_plugin_forbidden', __( 'You are not allowed to manage plugins.', 'sugar-calendar-lite' ), $state );
		}

		if ( $state === 'missing' ) {
			return $this->install( $target, $return_url );
		}

		if ( $state === 'installed' ) {
			return $this->activate( $target );
		}

		return $this->error( 'checklist_plugin_stale_action', __( 'This plugin action is no longer available. Refresh the page and try again.', 'sugar-calendar-lite' ), $state );
	}

	/**
	 * Install the target's package, then activate it when permitted.
	 *
	 * @since 3.15.0
	 *
	 * @param InstallTarget $target     Plugin to install.
	 * @param string        $return_url Local admin return URL.
	 *
	 * @return array|WP_Error
	 */
	public function install( InstallTarget $target, $return_url ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- separates partial installation from activation outcomes.

		if ( ! current_user_can( 'install_plugins' ) || ! wp_is_file_mod_allowed( 'plugins' ) ) {
			return $this->error( 'checklist_plugin_install_forbidden', __( 'Plugin installation is not available on this site.', 'sugar-calendar-lite' ), $target->get_state() );
		}

		$language_pack_hook = has_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ] );

		try {
			$basename = PluginInstaller::install_plugin( $return_url, $target->get_package_url(), false, true, [] );
		} finally {
			$this->restore_language_pack_hook( $language_pack_hook );
		}

		$state = $target->get_state();

		if ( is_wp_error( $basename ) ) {
			if ( $state === 'installed' ) {
				return ActionResult::build( 'partial', __( 'The plugin was installed, but setup did not finish. Try activating it.', 'sugar-calendar-lite' ), '', true, $state );
			}

			return $this->error_from( $basename, __( 'The plugin could not be installed. Please try again.', 'sugar-calendar-lite' ), $state );
		}

		if ( ! in_array( $basename, $target->get_basenames(), true ) ) {
			return $this->error( 'checklist_plugin_unexpected_package', __( 'The downloaded package did not match the selected plugin.', 'sugar-calendar-lite' ), $state );
		}

		if ( $state === 'missing' ) {
			return $this->error( 'checklist_plugin_install_state_failed', __( 'The plugin installation could not be verified. Please try again.', 'sugar-calendar-lite' ), $state );
		}

		if ( $state === 'active' ) {
			return ActionResult::build( 'success', __( 'Plugin installed and activated.', 'sugar-calendar-lite' ), '', true, $state );
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return ActionResult::build( 'partial', __( 'Plugin installed. An administrator with plugin access can activate it.', 'sugar-calendar-lite' ), '', true, $state );
		}

		return $this->activate_basename( $target, $basename );
	}

	/**
	 * Activate an installed target.
	 *
	 * @since 3.15.0
	 *
	 * @param InstallTarget $target Plugin to activate.
	 *
	 * @return array|WP_Error
	 */
	public function activate( InstallTarget $target ) {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return $this->error( 'checklist_plugin_activate_forbidden', __( 'You are not allowed to activate plugins on this site.', 'sugar-calendar-lite' ), $target->get_state() );
		}

		$basename = $this->get_installed_basename( $target );

		if ( $basename === '' ) {
			return $this->error( 'checklist_plugin_not_installed', __( 'The selected plugin is not installed.', 'sugar-calendar-lite' ), $target->get_state() );
		}

		return $this->activate_basename( $target, $basename );
	}

	/**
	 * Activate a recognized basename.
	 *
	 * @since 3.15.0
	 *
	 * @param InstallTarget $target   Plugin being activated.
	 * @param string        $basename Recognized installed basename.
	 *
	 * @return array|WP_Error
	 */
	private function activate_basename( InstallTarget $target, $basename ) {

		$error = activate_plugin( $basename );

		if ( ! is_wp_error( $error ) ) {
			/** This action is documented in Helpers\Installer. */
			// phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName, WPForms.Comments.PHPDocHooks.RequiredHookDocumentation
			do_action( 'sugar_calendar_plugin_activated', $basename );
		}

		$state = $target->get_state();

		if ( is_wp_error( $error ) ) {
			return $this->error_from( $error, __( 'The plugin could not be activated. Please activate it from the Plugins page.', 'sugar-calendar-lite' ), $state );
		}

		if ( $state !== 'active' ) {
			return $this->error( 'checklist_plugin_activation_failed', __( 'The plugin could not be activated. Please activate it from the Plugins page.', 'sugar-calendar-lite' ), $state );
		}

		return ActionResult::build( 'success', __( 'Plugin activated.', 'sugar-calendar-lite' ), '', true, $state );
	}

	/**
	 * Find the first accepted basename that is installed.
	 *
	 * @since 3.15.0
	 *
	 * @param InstallTarget $target Plugin to activate.
	 *
	 * @return string
	 */
	private function get_installed_basename( InstallTarget $target ) {

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = get_plugins();

		foreach ( $target->get_basenames() as $basename ) {
			if ( array_key_exists( $basename, $installed ) ) {
				return $basename;
			}
		}

		return '';
	}

	/**
	 * Restore WordPress's language pack callback removed by the shared helper.
	 *
	 * @since 3.15.0
	 *
	 * @param int|false $priority Original callback priority.
	 */
	private function restore_language_pack_hook( $priority ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- restore only the callback removed by the shared installer.

		if ( $priority !== false && has_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ] ) === false ) {
			add_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], $priority );
		}
	}

	/**
	 * Build an error carrying the actual current plugin state.
	 *
	 * @since 3.15.0
	 *
	 * @param string $code    Error code.
	 * @param string $message Plain message.
	 * @param string $state   Actual plugin state.
	 *
	 * @return WP_Error
	 */
	private function error( $code, $message, $state ) {

		return new WP_Error( $code, wp_strip_all_tags( $message ), [ 'plugin_state' => $state ] );
	}

	/**
	 * Preserve an operation error and attach the actual plugin state.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_Error $error    Operation error.
	 * @param string   $fallback Fallback message.
	 * @param string   $state    Actual plugin state.
	 *
	 * @return WP_Error
	 */
	private function error_from( WP_Error $error, $fallback, $state ) {

		$message = $error->get_error_message();

		return $this->error( (string) $error->get_error_code(), $message !== '' ? $message : $fallback, $state );
	}
}
