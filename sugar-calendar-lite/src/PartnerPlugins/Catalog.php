<?php

namespace Sugar_Calendar\PartnerPlugins;

/**
 * Partner plugin identities and installation state.
 *
 * @since 3.15.0
 */
class Catalog {

	/**
	 * Get a partner plugin record.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Partner plugin key.
	 *
	 * @return array|null Plugin record, or null for an unknown key.
	 */
	public function get( $key ) {

		$plugins = $this->get_plugins();

		return $plugins[ $key ] ?? null;
	}

	/**
	 * Get a partner plugin's installation state.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Partner plugin key.
	 *
	 * @return string One of 'missing', 'installed', or 'active'.
	 */
	public function get_state( $key ) {

		$plugin = $this->get( $key );

		if ( $plugin === null ) {
			return 'missing';
		}

		$this->load_plugin_functions();

		$installed_plugins = get_plugins();
		$basenames         = array_merge( [ $plugin['basename'] ], $plugin['pro_basenames'] );

		foreach ( $basenames as $basename ) {
			if ( array_key_exists( $basename, $installed_plugins ) && $this->is_active( $basename ) ) {
				return 'active';
			}
		}

		foreach ( $basenames as $basename ) {
			if ( array_key_exists( $basename, $installed_plugins ) ) {
				return 'installed';
			}
		}

		return 'missing';
	}

	/**
	 * Get the partner's own admin screen, where an active partner is set up.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Partner plugin key.
	 *
	 * @return string Admin URL, or an empty string for an unknown key or a partner without one.
	 */
	public function get_admin_url( $key ) {

		$plugin = $this->get( $key );

		if ( $plugin === null || $plugin['admin_page'] === '' ) {
			return '';
		}

		return add_query_arg( 'page', $plugin['admin_page'], admin_url( 'admin.php' ) );
	}

	/**
	 * Build the installer's view of a partner plugin.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Partner plugin key.
	 *
	 * @return InstallTarget|null Target, or null for an unknown key.
	 */
	public function get_target( $key ) {

		$plugin = $this->get( $key );

		if ( $plugin === null ) {
			return null;
		}

		return new InstallTarget(
			$plugin['download_url'],
			array_merge( $plugin['pro_basenames'], [ $plugin['basename'] ] ),
			function () use ( $key ) {
				return $this->get_state( $key );
			}
		);
	}

	/**
	 * Get the partner plugin allowlist.
	 *
	 * @since 3.15.0
	 *
	 * @return array Partner plugin records keyed by identifier.
	 */
	private function get_plugins() {

		return [
			'wp-mail-smtp'    => $this->plugin(
				'wp-mail-smtp/wp_mail_smtp.php',
				[ 'wp-mail-smtp-pro/wp_mail_smtp.php' ],
				'https://downloads.wordpress.org/plugin/wp-mail-smtp.zip',
				'https://wordpress.org/plugins/wp-mail-smtp/',
				'wp-mail-smtp'
			),
			'wpconsent'       => $this->plugin(
				'wpconsent-cookies-banner-privacy-suite/wpconsent.php',
				[ 'wpconsent-premium/wpconsent-premium.php' ],
				'https://downloads.wordpress.org/plugin/wpconsent-cookies-banner-privacy-suite.zip',
				'https://wordpress.org/plugins/wpconsent-cookies-banner-privacy-suite/'
			),
			'wpvibe'          => $this->plugin(
				'vibe-ai/vibe-ai.php',
				[],
				'https://downloads.wordpress.org/plugin/vibe-ai.zip',
				'https://wordpress.org/plugins/vibe-ai/',
				'vibe-ai'
			),
			'aioseo'          => $this->plugin(
				'all-in-one-seo-pack/all_in_one_seo_pack.php',
				[ 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' ],
				'https://downloads.wordpress.org/plugin/all-in-one-seo-pack.zip',
				'https://wordpress.org/plugins/all-in-one-seo-pack/'
			),
			'universally'     => $this->plugin(
				'universally-language-translation-multilingual-tool/universally.php',
				[],
				'https://downloads.wordpress.org/plugin/universally-language-translation-multilingual-tool.zip',
				'https://wordpress.org/plugins/universally-language-translation-multilingual-tool/'
			),
			'duplicator'      => $this->plugin(
				'duplicator/duplicator.php',
				[ 'duplicator-pro/duplicator-pro.php' ],
				'https://downloads.wordpress.org/plugin/duplicator.zip',
				'https://wordpress.org/plugins/duplicator/'
			),
			'reviews-feed'    => $this->plugin(
				'reviews-feed/sb-reviews.php',
				[ 'reviews-feed-pro/sb-reviews-pro.php' ],
				'https://downloads.wordpress.org/plugin/reviews-feed.zip',
				'https://wordpress.org/plugins/reviews-feed/'
			),
			'optinmonster'    => $this->plugin(
				'optinmonster/optin-monster-wp-api.php',
				[],
				'https://downloads.wordpress.org/plugin/optinmonster.zip',
				'https://wordpress.org/plugins/optinmonster/'
			),
			'monsterinsights' => $this->plugin(
				'google-analytics-for-wordpress/googleanalytics.php',
				[ 'google-analytics-premium/googleanalytics-premium.php' ],
				'https://downloads.wordpress.org/plugin/google-analytics-for-wordpress.zip',
				'https://wordpress.org/plugins/google-analytics-for-wordpress/'
			),
		];
	}

	/**
	 * Build a partner plugin record.
	 *
	 * @since 3.15.0
	 *
	 * @param string $basename      Free plugin basename.
	 * @param array  $pro_basenames Recognized Pro basenames.
	 * @param string $download_url  WordPress.org download URL.
	 * @param string $wporg_url     WordPress.org listing URL.
	 * @param string $admin_page    The partner's own admin page slug, or empty when there is nothing to open.
	 *
	 * @return array Partner plugin record.
	 */
	private function plugin( $basename, $pro_basenames, $download_url, $wporg_url, $admin_page = '' ) {

		return [
			'basename'      => $basename,
			'pro_basenames' => $pro_basenames,
			'download_url'  => $download_url,
			'wporg_url'     => $wporg_url,
			'admin_page'    => $admin_page,
		];
	}

	/**
	 * Load WordPress plugin APIs when needed.
	 *
	 * @since 3.15.0
	 */
	private function load_plugin_functions() {

		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) || ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Check whether a plugin is active for the site or network.
	 *
	 * @since 3.15.0
	 *
	 * @param string $basename Plugin basename.
	 *
	 * @return bool Whether the plugin is active.
	 */
	private function is_active( $basename ) {

		return is_plugin_active( $basename ) || is_plugin_active_for_network( $basename );
	}
}
