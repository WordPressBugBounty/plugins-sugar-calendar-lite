<?php

namespace Sugar_Calendar\Admin\Addons;

use Sugar_Calendar\Admin\Area;

/**
 * Class Addons.
 *
 * @since 3.7.0
 */
class Addons {

	/**
	 * Basic license.
	 *
	 * @since 3.7.0
	 */
	const BASIC = 'basic';

	/**
	 * Plus license.
	 *
	 * @since 3.7.0
	 */
	const PLUS = 'plus';

	/**
	 * Pro license.
	 *
	 * @since 3.7.0
	 */
	const PRO = 'pro';

	/**
	 * Elite license.
	 *
	 * @since 3.7.0
	 */
	const ELITE = 'elite';

	/**
	 * Addons cache object.
	 *
	 * @since 3.7.0
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * Addons data.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	private $addons;

	/**
	 * Addons text domains.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	private $addons_text_domains = [];

	/**
	 * Addons titles.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	private $addons_titles = [];

	/**
	 * Suggested plugins.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	protected $suggested_plugins = [
		'wp-mail-smtp' => 'https://downloads.wordpress.org/plugin/wp-mail-smtp.zip',
	];

	/**
	 * Constructor.
	 *
	 * @since 3.7.0
	 */
	public function __construct() {

		$this->cache = new Cache();
	}

	/**
	 * Check user capability.
	 *
	 * @since 3.7.0
	 *
	 * @var bool
	 */
	protected function check_capability() {

		return current_user_can( 'update_plugins' );
	}

	/**
	 * Hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		global $pagenow;

		// Force update addons cache if we are on the update-core.php page.
		// This is necessary to update addons data while checking for all available updates.
		if ( $pagenow === 'update-core.php' ) {
			$this->cache->update( true );
		}

		// Filter Gettext only on Plugin list and Updates pages.
		if ( $pagenow === 'update-core.php' || $pagenow === 'plugins.php' ) {
			add_action( 'gettext', [ $this, 'filter_gettext' ], 10, 3 );
		}

		$this->addons = $this->cache->get();

		$this->populate_addons_data();
	}

	/**
	 * Get addons cache.
	 *
	 * @since 3.7.0
	 */
	public function get_cache() {

		return $this->cache;
	}

	/**
	 * Get all addons data as array.
	 *
	 * @since 3.7.0
	 *
	 * @param bool $force_cache_update Determine if we need to update cache. Default is `false`.
	 * @param bool $check_capabilities Whether to check for management capabilities.
	 *
	 * @return array
	 */
	public function get_all( bool $force_cache_update = false, bool $check_capabilities = false ) {

		if ( $check_capabilities && ! $this->check_capability() ) {
			return [];
		}

		if ( $force_cache_update ) {
			$this->cache->update( true );

			$this->addons = $this->cache->get();
		}

		return $this->get_sorted_addons();
	}

	/**
	 * Get sorted addons data.
	 * Recommended addons will be displayed first,
	 * then new addons, then featured addons,
	 * and then all other addons.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	private function get_sorted_addons(): array {

		if ( empty( $this->addons ) ) {
			return [];
		}

		$recommended = array_filter(
			$this->addons,
			static function ( $addon ) {

				return ! empty( $addon['recommended'] );
			}
		);

		$new = array_filter(
			$this->addons,
			static function ( $addon ) {

				return ! empty( $addon['new'] );
			}
		);

		$featured = array_filter(
			$this->addons,
			static function ( $addon ) {

				return ! empty( $addon['featured'] );
			}
		);

		return array_merge( $recommended, $new, $featured, $this->addons );
	}

	/**
	 * Get filtered addons data.
	 *
	 * Usage:
	 *      ->get_filtered( $this->addons, [ 'category' => 'payments' ] )    - addons for the payments panel.
	 *      ->get_filtered( $this->addons, [ 'license' => 'elite' ] )        - addons available for 'elite' license.
	 *
	 * @since 3.7.0
	 *
	 * @param array $addons Raw addons data.
	 * @param array $args   Arguments array.
	 *
	 * @return array Addons data filtered according to given arguments.
	 */
	private function get_filtered( array $addons, array $args ) {

		if ( empty( $addons ) ) {
			return [];
		}

		$default_args = [
			'category' => '',
			'license'  => '',
		];

		$args = wp_parse_args( $args, $default_args );

		$filtered_addons = [];

		foreach ( $addons as $addon ) {
			foreach ( [ 'category', 'license' ] as $arg_key ) {
				if (
					! empty( $args[ $arg_key ] ) &&
					! empty( $addon[ $arg_key ] ) &&
					is_array( $addon[ $arg_key ] ) &&
					in_array( strtolower( $args[ $arg_key ] ), $addon[ $arg_key ], true )
				) {
					$filtered_addons[] = $addon;
				}
			}
		}

		return $filtered_addons;
	}

	/**
	 * Get available addons data by category.
	 *
	 * @since 3.7.0
	 *
	 * @param string $category Addon category.
	 *
	 * @return array.
	 */
	public function get_by_category( string $category ) {

		return $this->get_filtered( $this->get_available(), [ 'category' => $category ] );
	}

	/**
	 * Get available addons data by license.
	 *
	 * @since        3.7.0
	 *
	 * @param string $license Addon license.
	 *
	 * @return array.
	 * @noinspection PhpUnused
	 */
	public function get_by_license( string $license ) {

		return $this->get_filtered( $this->get_available(), [ 'license' => $license ] );
	}

	/**
	 * Get available addon data by slug.
	 *
	 * @since 3.7.0
	 *
	 * @param string|bool $slug Addon slug can be both "sc-event-ticketing" and "event-ticketing".
	 *
	 * @return array Single addon data. Empty array if addon is not found.
	 */
	public function get_addon( $slug ) {

		$slug = (string) $slug;
		$slug = 'sc-' . str_replace( 'sc-', '', sanitize_key( $slug ) );

		$addon = $this->get_available()[ $slug ] ?? [];

		// In case if addon is "not available" let's try to get and prepare addon data from all addons.
		if ( empty( $addon ) ) {
			$addon = ! empty( $this->addons[ $slug ] ) ? $this->prepare_addon_data( $this->addons[ $slug ] ) : [];
		}

		return $addon;
	}

	/**
	 * Check if addon is active.
	 *
	 * @since 3.7.0
	 *
	 * @param string $slug Addon slug.
	 *
	 * @return bool
	 */
	public function is_active( string $slug ): bool {

		$addon = $this->get_addon( $slug );

		return isset( $addon['status'] ) && $addon['status'] === 'active';
	}

	/**
	 * Get license level of the addon.
	 *
	 * @since 3.7.0
	 *
	 * @param array|string $addon Addon data array OR addon slug.
	 *
	 * @return string License level: pro | elite.
	 */
	private function get_license_level( $addon ) {

		if ( empty( $addon ) ) {
			return '';
		}

		$levels        = [ self::BASIC, self::PLUS, self::PRO, self::ELITE ];
		$license       = '';
		$addon_license = $this->get_addon_license( $addon );

		foreach ( $levels as $level ) {
			if ( in_array( $level, $addon_license, true ) ) {
				$license = $level;

				break;
			}
		}

		if ( empty( $license ) ) {
			return '';
		}

		return in_array( $license, [ self::BASIC, self::PLUS, self::PRO ], true ) ? self::PRO : self::ELITE;
	}

	/**
	 * Get addon license.
	 *
	 * @since 3.7.0
	 *
	 * @param array|string $addon Addon data array OR addon slug.
	 *
	 * @return array
	 */
	private function get_addon_license( $addon ) {

		$addon = is_string( $addon ) ? $this->get_addon( $addon ) : $addon;

		return $this->default_data( $addon, 'license', [] );
	}

	/**
	 * Determine if a user's license level has access.
	 *
	 * @since 3.7.0
	 *
	 * @param array|string $addon Addon data array OR addon slug.
	 *
	 * @return bool
	 */
	protected function has_access( $addon ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		return false;
	}

	/**
	 * Return array of addons available to display. All data is prepared and normalized.
	 * "Available to display" means that addon needs to be displayed as an education item (addon is not installed or not activated).
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_available() {

		if ( empty( $this->addons ) || ! is_array( $this->addons ) ) {
			return [];
		}

		$available_addons = array_map( [ $this, 'prepare_addon_data' ], $this->addons );
		$available_addons = array_filter(
			$available_addons,
			static function ( $addon ) {

				return isset( $addon['status'], $addon['plugin_allow'] ) && ( $addon['status'] !== 'active' || ! $addon['plugin_allow'] );
			}
		);

		return $available_addons;
	}

	/**
	 * Prepare addon data.
	 *
	 * @since 3.7.0
	 *
	 * @param array|mixed $addon Addon data.
	 *
	 * @return array Extended addon data.
	 */
	protected function prepare_addon_data( $addon ) {

		if ( empty( $addon ) ) {
			return [];
		}

		$addon['title'] = $this->default_data( $addon, 'title', '' );
		$addon['slug']  = $this->default_data( $addon, 'slug', '' );

		// We need the cleared name of the addon, without the 'addon' suffix, for further use.
		$addon['name'] = preg_replace( '/ addon$/i', '', $addon['title'] );

		$addon['modal_name']    = sprintf( /* translators: %s - addon name. */
			esc_html__( '%s addon', 'sugar-calendar-lite' ),
			$addon['name']
		);
		$addon['clear_slug']    = str_replace( 'sc-', '', $addon['slug'] );
		$addon['utm_content']   = ucwords( str_replace( '-', ' ', $addon['clear_slug'] ) );
		$addon['license']       = $this->default_data( $addon, 'license', [] );
		$addon['license_level'] = $this->get_license_level( $addon );
		$addon['icon']          = $this->default_data( $addon, 'icon', '' );
		$addon['path']          = sprintf( '%1$s/%1$s.php', $addon['slug'] );
		$addon['video']         = $this->default_data( $addon, 'video', '' );
		$addon['plugin_allow']  = $this->has_access( $addon );
		$addon['status']        = 'missing';
		$addon['action']        = 'upgrade';
		$addon['page_url']      = $this->default_data( $addon, 'url', '' );
		$addon['doc_url']       = $this->default_data( $addon, 'doc', '' );
		$addon['url']           = '';

		$nonce          = wp_create_nonce( Area::SLUG );
		$addon['nonce'] = $nonce;

		return $addon;
	}

	/**
	 * Get default data.
	 *
	 * @since 3.7.0
	 *
	 * @param array|mixed $addon        Addon data.
	 * @param string      $key          Key.
	 * @param mixed       $default_data Default data.
	 *
	 * @return array|string|mixed
	 */
	private function default_data( $addon, string $key, $default_data ) {

		if ( is_string( $default_data ) ) {
			return ! empty( $addon[ $key ] ) ? $addon[ $key ] : $default_data;
		}

		if ( is_array( $default_data ) ) {
			return ! empty( $addon[ $key ] ) ? (array) $addon[ $key ] : $default_data;
		}

		return $addon[ $key ] ?? '';
	}

	/**
	 * Populate addons data.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	private function populate_addons_data() {

		foreach ( $this->addons as $addon ) {
			$this->addons_text_domains[] = $addon['slug'];
			$this->addons_titles[]       = 'Sugar Calendar ' . str_replace( ' Addon', '', $addon['title'] );
		}
	}

	/**
	 * Filter Gettext.
	 *
	 * This filter allows us to prevent empty translations from being returned
	 * on the `plugins` page for addon name and description.
	 *
	 * @since 3.7.0
	 *
	 * @param string|mixed $translation Translated text.
	 * @param string|mixed $text        Text to translate.
	 * @param string|mixed $domain      Text domain.
	 *
	 * @return string Translated text.
	 */
	public function filter_gettext( $translation, $text, $domain ): string {

		$translation = (string) $translation;
		$text        = (string) $text;
		$domain      = (string) $domain;

		if ( ! in_array( $domain, $this->addons_text_domains, true ) ) {
			return $translation;
		}

		// Prevent empty translations from being returned and don't translate addon names.
		if ( ! trim( $translation ) || in_array( $text, $this->addons_titles, true ) ) {
			$translation = $text;
		}

		return $translation;
	}

	/**
	 * Get suggested plugin URL.
	 *
	 * @since 3.7.0
	 *
	 * @param string $plugin_slug Plugin slug.
	 *
	 * @return string Plugin URL.
	 */
	public function get_suggested_plugin_url( string $plugin_slug ) {

		return $this->suggested_plugins[ $plugin_slug ] ?? false;
	}
}
