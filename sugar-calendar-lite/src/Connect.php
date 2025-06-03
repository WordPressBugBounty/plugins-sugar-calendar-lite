<?php

namespace Sugar_Calendar;

use Plugin_Upgrader;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Admin\PluginsInstallSkin;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Sugar Calendar Connect.
 *
 * Sugar Calendar Connect is our service that makes it easy for non-techy users to
 * upgrade to Pro version without having to manually install Pro plugin.
 *
 * @since 3.0.0
 */
class Connect {

	/**
	 * Hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_scripts' ] );
		add_action( 'sugar_calendar_ajax_connect_url', [ $this, 'ajax_generate_url' ] );
		add_action( 'wp_ajax_nopriv_sugar_calendar_connect_process', [ $this, 'process' ] );
	}

	/**
	 * Enqueue connect JS file to WP Mail SMTP admin area hook.
	 *
	 * @since 3.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_style( 'sugar-calendar-admin-confirm' );

		wp_enqueue_script(
			'sugar-calendar-admin-connect',
			SC_PLUGIN_ASSETS_URL . 'js/admin-connect' . WP::asset_min() . '.js',
			[ 'sugar-calendar-vendor-jquery-confirm' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-connect',
			'sugar_calendar_admin_connect',
			[
				'ajax_url'   => Plugin::instance()->get_admin()->ajax_url(),
				'plugin_url' => SC_PLUGIN_URL,
				'text'       => [
					'plugin_activate_btn' => esc_html__( 'Activate', 'sugar-calendar-lite' ),
					'almost_done'         => esc_html__( 'Almost Done', 'sugar-calendar-lite' ),
					'oops'                => esc_html__( 'Heads up!', 'sugar-calendar-lite' ),
					'ok'                  => esc_html__( 'Continue', 'sugar-calendar-lite' ),
					'server_error'        => esc_html__( 'Unfortunately there was a server connection error.', 'sugar-calendar-lite' ),
				],
			]
		);
	}

	/**
	 * Generate and return Sugar Calendar Connect URL.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key      The license key.
	 * @param string $oth      The One-time hash.
	 * @param string $redirect The redirect URL.
	 *
	 * @return bool|string
	 */
	public static function generate_url( $key, $oth = '', $redirect = '' ) {

		if ( empty( $key ) || Plugin::instance()->is_pro() ) {
			return false;
		}

		$oth        = ! empty( $oth ) ? $oth : hash( 'sha512', wp_rand() );
		$hashed_oth = hash_hmac( 'sha512', $oth, wp_salt() );

		$redirect = ! empty( $redirect ) ? $redirect : Plugin::instance()->get_admin()->get_page_url();

		update_option( 'sugar_calendar_connect_token', $oth );
		update_option( 'sugar_calendar_connect', $key );

		return add_query_arg(
			[
				'key'      => $key,
				'oth'      => $hashed_oth,
				'endpoint' => admin_url( 'admin-ajax.php' ),
				'version'  => SC_PLUGIN_VERSION,
				'siteurl'  => admin_url(),
				'homeurl'  => site_url(),
				'redirect' => rawurldecode( base64_encode( $redirect ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'v'        => 2,
			],
			'https://upgrade.sugarcalendar.com'
		);
	}

	/**
	 * AJAX callback to generate and return the Sugar Calendar Connect URL.
	 *
	 * @since 3.0.0
	 */
	public function ajax_generate_url() { //phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Check for permissions.
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'You are not allowed to install plugins.', 'sugar-calendar-lite' ),
				]
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$key = ! empty( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

		if ( empty( $key ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Please enter your license key to connect.', 'sugar-calendar-lite' ),
				]
			);
		}

		if ( Plugin::instance()->is_pro() ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Only the Lite version can be upgraded.', 'sugar-calendar-lite' ),
				]
			);
		}

		// Verify pro version is not installed.
		$active = activate_plugin( 'sugar-calendar/sugar-calendar.php', false, false, true );

		if ( ! is_wp_error( $active ) ) {

			// Deactivate Lite.
			deactivate_plugins( plugin_basename( SC_PLUGIN_FILE ) );

			wp_send_json_success(
				[
					'message' => esc_html__( 'Sugar Calendar Pro was already installed, but was not active. We activated it for you.', 'sugar-calendar-lite' ),
					'reload'  => true,
				]
			);
		}

		$url = self::generate_url( $key );

		if ( empty( $url ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'There was an error while generating an upgrade URL. Please try again.', 'sugar-calendar-lite' ),
				]
			);
		}

		wp_send_json_success( [ 'url' => $url ] );
	}

	/**
	 * AJAX callback to process Sugar Calendar Connect.
	 *
	 * @since 3.0.0
	 */
	public function process() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded,WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$error = esc_html__( 'There was an error while installing an upgrade. Please download the plugin from sugarcalendar.com and install it manually.', 'sugar-calendar-lite' );

		// Verify params present (oth & download link).
		$post_oth = ! empty( $_REQUEST['oth'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['oth'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$post_url = ! empty( $_REQUEST['file'] ) ? esc_url_raw( wp_unslash( $_REQUEST['file'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $post_oth ) || empty( $post_url ) ) {
			wp_send_json_error( $error );
		}

		// Verify oth.
		$oth = get_option( 'sugar_calendar_connect_token' );

		if ( empty( $oth ) ) {
			wp_send_json_error( $error );
		}

		if ( hash_hmac( 'sha512', $oth, wp_salt() ) !== $post_oth ) {
			wp_send_json_error( $error );
		}

		// Delete so cannot replay.
		delete_option( 'sugar_calendar_connect_token' );

		// Set the current screen to avoid undefined notices.
		set_current_screen( 'toplevel_page_sugar-calendar' );

		// Prepare variables.
		$url = esc_url_raw( Plugin::instance()->get_admin()->get_page_url() );

		// Verify pro not activated.
		if ( Plugin::instance()->is_pro() ) {
			wp_send_json_success( esc_html__( 'Plugin installed & activated.', 'sugar-calendar-lite' ) );
		}

		// Verify pro not installed.
		$active = activate_plugin( 'sugar-calendar/sugar-calendar.php', $url, false, true );

		if ( ! is_wp_error( $active ) ) {
			deactivate_plugins( plugin_basename( SC_PLUGIN_FILE ) );
			wp_send_json_success( esc_html__( 'Plugin installed & activated.', 'sugar-calendar-lite' ) );
		}

		/*
		 * The `request_filesystem_credentials` function will output a credentials form in case of failure.
		 * We don't want that, since it will break AJAX response. So just hide output with a buffer.
		 */
		ob_start();
		// phpcs:ignore WPForms.Formatting.EmptyLineAfterAssigmentVariables.AddEmptyLine
		$creds = request_filesystem_credentials( $url, '', false, false, null );
		ob_end_clean();

		// Check for file system permissions.
		$perm_error = esc_html__( 'There was an error while installing the upgrade. Please check file system permissions and try again. Also, you can download the plugin from sugarcalendar.com and install it manually.', 'sugar-calendar-lite' );

		if ( $creds === false || ! WP_Filesystem( $creds ) ) {
			wp_send_json_error( $perm_error );
		}

		/*
		 * We do not need any extra credentials if we have gotten this far, so let's install the plugin.
		 */

		// Do not allow WordPress to search/download translations, as this will break JS output.
		remove_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], 20 );

		// Import the plugin upgrader.
		Helpers::include_plugin_upgrader();

		// Create the plugin upgrader with our custom skin.
		$installer = new Plugin_Upgrader( new PluginsInstallSkin() );

		// Error check.
		if ( ! method_exists( $installer, 'install' ) ) {
			wp_send_json_error( $error );
		}

		// Check license key.
		$key = get_option( 'sugar_calendar_connect', false );

		delete_option( 'sugar_calendar_connect' );

		if ( empty( $key ) ) {
			wp_send_json_error(
				new WP_Error(
					'403',
					esc_html__( 'There was an error while installing the upgrade. Please try again.', 'sugar-calendar-lite' )
				)
			);
		}

		$installer->install( $post_url );

		// Flush the cache and return the newly installed plugin basename.
		wp_cache_flush();

		$plugin_basename = $installer->plugin_info();

		if ( $plugin_basename ) {

			// Deactivate the lite version first.
			deactivate_plugins( plugin_basename( SC_PLUGIN_FILE ) );

			// Activate the plugin silently.
			$activated = activate_plugin( $plugin_basename, '', false, true );

			if ( ! is_wp_error( $activated ) ) {

				// Save the license data, since it was verified on the connect page.
				$license = [
					'key'         => $key,
					'type'        => 'pro',
					'is_expired'  => false,
					'is_disabled' => false,
					'is_invalid'  => false,
				];

				Options::update( 'license', $license );

				wp_send_json_success( esc_html__( 'Plugin installed & activated.', 'sugar-calendar-lite' ) );
			} else {
				// Reactivate the lite plugin if pro activation failed.
				activate_plugin( plugin_basename( SC_PLUGIN_FILE ), '', false, true );
				wp_send_json_error( esc_html__( 'Pro version installed but needs to be activated on the Plugins page.', 'sugar-calendar-lite' ) );
			}
		}

		wp_send_json_error( $error );
	}
}
