<?php

namespace Sugar_Calendar\Admin\Addons;

use Sugar_Calendar\Helpers\Helpers;

/**
 * Requirements management.
 *
 * @since 3.7.0
 */
class Requirements {

	/**
	 * Whether deactivate addon if requirements not met.
	 *
	 * @since 3.7.0
	 */
	private const DEACTIVATE_IF_NOT_MET = false;

	/**
	 * Whether to show PHP version notice.
	 *
	 * @since 3.7.0
	 */
	private const SHOW_PHP_NOTICE = true;

	/**
	 * Whether to show PHP extension notice.
	 *
	 * @since 3.7.0
	 */
	private const SHOW_EXT_NOTICE = true;

	/**
	 * Whether to show WordPress version notice.
	 *
	 * @since 3.7.0
	 */
	private const SHOW_WP_NOTICE = true;

	/**
	 * Whether to show Sugar Calendar version notice.
	 *
	 * @since 3.7.0
	 */
	private const SHOW_SUGAR_CALENDAR_NOTICE = true;

	/**
	 * Whether to show license level notice.
	 *
	 * @since 3.7.0
	 */
	private const SHOW_LICENSE_NOTICE = false;

	/**
	 * Whether to show addon version notice.
	 *
	 * @since 3.7.0
	 */
	private const SHOW_ADDON_NOTICE = true;

	/**
	 * PHP version requirement key.
	 *
	 * @since 3.7.0
	 */
	private const PHP = 'php';

	/**
	 * PHP extensions requirement key.
	 *
	 * @since 3.7.0
	 */
	private const EXT = 'ext';

	/**
	 * WordPress version requirement key.
	 *
	 * @since 3.7.0
	 */
	private const WP = 'wp';

	/**
	 * Sugar Calendar version requirement key.
	 *
	 * @since 3.7.0
	 */
	private const SUGAR_CALENDAR = 'sugar_calendar';

	/**
	 * License type requirement key.
	 *
	 * @since 3.7.0
	 */
	private const LICENSE = 'license';

	/**
	 * Priority requirement key.
	 *
	 * @since 3.7.0
	 */
	private const PRIORITY = 'priority';

	/**
	 * Addon name requirement key.
	 *
	 * @since 3.7.0
	 */
	private const ADDON = 'addon';

	/**
	 * Addon version constant requirement key.
	 *
	 * @since 3.7.0
	 */
	private const ADDON_VERSION_CONSTANT = 'addon_version_constant';

	/**
	 * Version requirement key.
	 *
	 * @since 3.7.0
	 */
	private const VERSION = 'version';

	/**
	 * Comparison requirement key.
	 *
	 * @since 3.7.0
	 */
	private const COMPARE = 'compare';

	/**
	 * Comparison type requirement key.
	 *
	 * @since 3.7.0
	 */
	private const COMPARE_DEFAULT = '>=';

	/**
	 * Development version of Sugar Calendar. Can be specified in an addon.
	 *
	 * @since 3.7.0
	 */
	private const SUGAR_CALENDAR_DEV_VERSION_IN_ADDON = '{SC_PLUGIN_VERSION}';

	/**
	 * Plus, Pro and Top level licenses.
	 * Must be a list separated by comma and space.
	 *
	 * @since 3.7.0
	 */
	private const PLUS_PRO_AND_TOP = [ 'plus', 'pro', 'elite', 'agency', 'ultimate' ];

	/**
	 * Pro and Top level licenses.
	 * Must be a list separated by comma and space.
	 *
	 * @since 3.7.0
	 */
	private const PRO_AND_TOP = [ 'pro', 'elite', 'agency', 'ultimate' ];

	/**
	 * Top level licenses.
	 * Must be a list separated by comma and space.
	 *
	 * @since 3.7.0
	 */
	private const TOP = [ 'elite', 'agency', 'ultimate' ];

	/**
	 * Default minimal addon requirements.
	 *
	 * @since 3.7.0
	 *
	 * @var string[]
	 */
	private $defaults = [
		self::PHP            => '7.4',
		self::WP             => '5.9',
		self::SUGAR_CALENDAR => self::SUGAR_CALENDAR_DEV_VERSION_IN_ADDON,
		self::LICENSE        => self::PLUS_PRO_AND_TOP,
		self::PRIORITY       => 10,
	];

	/**
	 * Some things to do.
	 *
	 * @todo Add custom message for form-templates-pack.
	 */

	// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
	/**
	 * Addon requirements.
	 *
	 * Array has the format 'addon basename' => 'addon requirements array'.
	 *
	 * The requirement array can have the following keys:
	 * self::PHP ('php') for the minimal PHP version required,
	 * self::EXT ('ext') for the PHP extensions required,
	 * self::WP ('wp') for the minimal WordPress version required,
	 * self::SUGAR_CALENDAR ('sugar_calendar') for the minimal Sugar Calendar version required,
	 * self::LICENSE ('license') for the license level required,
	 * self::ADDON ('addon') for the minimal addon version required,
	 * self::ADDON_VERSION_CONSTANT ('addon_version_constant') for the addon version constant.
	 * self::PRIORITY ('priority') for the priority of the current requirements.
	 *
	 * The requirement array can have the following values:
	 * The 'php' value can be string like '5.6' or an array like 'php' => [ 'version' => '7.2', compare => '=' ].
	 * The 'ext' value can be string like 'curl' or an array like 'ext' => [ 'curl', 'mbstring' ].
	 * The 'wp' value can be string like '5.5' or an array like 'wp' => [ 'version' => '6.4', compare => '=' ].
	 * The 'sugar_calendar' value can be string like '1.8.2' or an array like 'sugar_calendar' => [ 'version' => '1.7.5', compare =>
	 * '=' ]. When 'sugar_calendar' value is '{SUGAR_CALENDAR_VERSION}', it is not checked and should be used for development. The
	 * 'license' value can be string like 'elite, agency, ultimate', an array like 'license' => [ 'elite', 'agency',
	 * 'ultimate' ]. When 'license' value is an empty like null, false, [], it is not checked. The 'addon' value can be
	 * string like '2.0.1' or an array like 'addon' => [ 'version' => '2.0.1', 'compare' => '<=' ]. The
	 * 'addon_version_constant' must be a string like 'SUGAR_CALENDAR_ACTIVECAMPAIGN_VERSION'. The 'priority' must be an
	 * integer like 20. By default, it is 10.
	 *
	 * By default, 'compare' is '>='.
	 *
	 * Default addon version constant is formed from addon directory name like this:
	 * sc-activecampaign -> SUGAR_CALENDAR_ACTIVECAMPAIGN_VERSION.
	 *
	 * Requirements can be specified here or in the addon as a parameter of is_validated().
	 * The priorities from lower to higher (if PRIORITY is not set or equal):
	 * 1. Default parameters from $this->defaults.
	 * 2. Current array $this->requirements.
	 * 3. Parameter of is_validated() call in the addon.
	 * Settings with a higher priority overwrite lower priority settings.
	 *
	 * Minimal required version of Sugar Calendar should be specified in the addons.
	 * Minimal required version of addons should be specified here, in $this->requirements array.
	 *
	 * We do not plan to restrict the lower addon version so far.
	 * However, if in the future we may need to do so,
	 * we should add to the addon-related requirement array the line like
	 * self::ADDON => '1.x.x' or
	 * self::ADDON => '{SUGAR_CALENDAR_ACTIVECAMPAIGN_VERSION}'.
	 * Here 1.x.x is the specific addon version, and
	 * SUGAR_CALENDAR_ACTIVECAMPAIGN_VERSION is the addon version constant name.
	 * The script will replace the addon version constant name during the addon release.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	private $requirements = [
		'sc-event-ticketing/sc-event-ticketing.php' => [],
		'sc-zapier/sc-zapier.php'                   => [],
		'sc-rsvp/sc-rsvp.php'                       => [],
	];
	// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow

	/**
	 * Addon requirements.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	private $addon_requirements = [];

	/**
	 * Addon basename.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	private $basename = '';

	/**
	 * Validated addons.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	private $validated = [];

	/**
	 * Not validated addons.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	private $not_validated = [];

	/**
	 * Get a single instance of the addon.
	 *
	 * @since 3.7.0
	 *
	 * @return Requirements
	 */
	public static function get_instance(): Requirements {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();

			$instance->init();
		}

		return $instance;
	}

	/**
	 * Init class.
	 *
	 * @since 3.7.0
	 */
	private function init(): void {

		foreach ( $this->requirements as $basename => $requirement ) {
			$this->init_addon_requirements( $basename );
		}

		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 3.7.0
	 */
	private function hooks(): void {

		add_action( 'admin_init', [ $this, 'deactivate' ] );
		add_action( 'admin_notices', [ $this, 'show_notices' ] );
		add_action( 'network_admin_notices', [ $this, 'show_notices' ] );
	}

	/**
	 * Validate an addon.
	 *
	 * @since 3.7.0
	 *
	 * @param array $addon_requirements Addon requirements.
	 *
	 * @return bool
	 */
	public function validate( array $addon_requirements ): bool {

		$this->addon_requirements = $addon_requirements;
		$file                     = $this->addon_requirements['file'] ?? null;

		$this->basename = ! empty( $file ) ? plugin_basename( $file ) : $addon_requirements['basename'];

		$this->init_addon_requirements( $this->basename );

		$this->addon_requirements = $this->merge_requirements(
			$this->defaults,
			$this->requirements[ $this->basename ],
			$this->addon_requirements
		);

		$php_valid            = $this->validate_php();
		$ext_valid            = $this->validate_ext();
		$wp_valid             = $this->validate_wp();
		$sugar_calendar_valid = $this->validate_sugar_calendar();
		$license_valid        = $this->validate_license();
		$addon_valid          = $this->validate_addon();

		if ( $php_valid && $ext_valid && $wp_valid && $sugar_calendar_valid && $license_valid && $addon_valid ) {
			$this->validated[] = $this->basename;
		}

		$this->requirements[ $this->basename ] = $this->addon_requirements;

		return empty( $this->not_validated[ $this->basename ] );
	}

	/**
	 * Determine if addon is validated.
	 *
	 * @since 3.7.0
	 *
	 * @param string $basename Addon basename.
	 *
	 * @return bool
	 */
	public function is_validated( string $basename ): bool {

		if ( ! $this->is_addon( $basename ) ) {
			// No more actions if it is not a Sugar Calendar addon.
			return true;
		}

		$addon_requirements = $this->requirements[ $basename ] ?? null;

		if ( empty( $addon_requirements ) ) {
			return false;
		}

		// We didn't check the addon before.
		if ( ! isset( $this->not_validated[ $basename ] ) && ! in_array( $basename, $this->validated, true ) ) {
			// TODO: support addon-initiated requirement checks.
			$this->validate( $addon_requirements );
		}

		return in_array( $basename, $this->validated, true );
	}

	/**
	 * Merge requirements by priority.
	 *
	 * @since 3.7.0
	 *
	 * @param array $defaults           Default requirements.
	 * @param array $requirements       Requirements.
	 * @param array $addon_requirements Addon requirements.
	 *
	 * @return array
	 */
	private function merge_requirements( array $defaults, array $requirements, array $addon_requirements ): array {

		$chunks = [ $defaults, $requirements, $addon_requirements ];

		usort(
			$chunks,
			static function ( $chunk1, $chunk2 ) {

				// phpcs:ignore WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement
				return ( $chunk1[ self::PRIORITY ] ?? 10 ) <=> ( $chunk2[ self::PRIORITY ] ?? 10 );
			}
		);

		return array_merge( ...$chunks );
	}

	/**
	 * Try to deactivate not valid addon.
	 *
	 * @since 3.7.0
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins' directory.
	 *
	 * @return bool True if addon was deactivated.
	 */
	public function deactivate_not_valid_addon( string $plugin ): bool {

		if ( ! self::DEACTIVATE_IF_NOT_MET ) {
			// No more actions if we not demand deactivation.
			return false;
		}

		// Addon may get deactivated after this statement.
		$this->deactivate();

		return ! is_plugin_active( $plugin );
	}

	/**
	 * Check whether a plugin is a Sugar Calendar addon.
	 *
	 * @since 3.7.0
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins' directory.
	 *
	 * @return bool
	 */
	private function is_addon( string $plugin ): bool {

		if ( strpos( $plugin, 'sc-' ) !== 0 ) {
			return false;
		}

		/**
		 * Check the Author name in the plugin header.
		 */
		$plugin_data   = $this->get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
		$plugin_author = isset( $plugin_data['Author'] ) ? strtolower( $plugin_data['AuthorName'] ) : '';

		return $plugin_author === 'sugar calendar';
	}

	/**
	 * Wrapper for get_plugin_data.
	 * Check the plugin file for existence to avoid warnings.
	 *
	 * @since        3.7.0
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param bool   $markup      Optional. If the returned data should have HTML markup applied.
	 * @param bool   $translate   Optional. If the returned data should be translated. Default true.
	 *
	 * @return array
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function get_plugin_data( string $plugin_file, bool $markup = true, bool $translate = true ): array {

		if ( ! file_exists( $plugin_file ) ) {
			return [];
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugin_data( $plugin_file, $markup, $translate );
	}

	/**
	 * Normalize version-based requirement.
	 *
	 * @since 3.7.0
	 *
	 * @param string $key Requirements key.
	 *
	 * @return array[]
	 */
	private function normalize_version_requirement( string $key ): array {

		if ( ! isset( $this->addon_requirements[ $key ] ) ) {
			$this->addon_requirements[ $key ] = [];

			return [];
		}

		$requirement = (array) $this->addon_requirements[ $key ];

		$version = isset( $requirement[0] ) ?
			array_map( 'trim', (array) $requirement[0] ) :
			[ '' ];
		$version = isset( $requirement[ self::VERSION ] ) ?
			array_map( 'trim', (array) $requirement[ self::VERSION ] ) :
			$version;
		$compare = isset( $requirement[ self::COMPARE ] ) ?
			array_map( 'trim', (array) $requirement[ self::COMPARE ] ) :
			[ self::COMPARE_DEFAULT ];
		$compare = array_pad( $compare, count( $version ), self::COMPARE_DEFAULT );

		$requirement = [
			self::VERSION => $version,
			self::COMPARE => $compare,
		];

		$this->addon_requirements[ $key ] = $requirement;

		return $requirement;
	}

	/**
	 * Normalize array-based requirement.
	 *
	 * @since 3.7.0
	 *
	 * @param string $key Requirements key.
	 *
	 * @return string[]
	 */
	private function normalize_array_requirement( string $key ): array {

		if ( ! isset( $this->addon_requirements[ $key ] ) ) {
			$this->addon_requirements[ $key ] = [];

			return [];
		}

		$requirement = $this->addon_requirements[ $key ];

		if ( is_string( $requirement ) ) {
			$requirement = explode( ',', $requirement );
		}

		if ( ! is_array( $requirement ) ) {
			$requirement = [];
		}

		$requirement                      = array_filter( array_map( 'trim', $requirement ) );
		$this->addon_requirements[ $key ] = $requirement;

		return $requirement;
	}

	/**
	 * Validate php.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	private function validate_php(): bool {

		$php = $this->normalize_version_requirement( self::PHP );

		if ( empty( $php ) ) {
			return true;
		}

		if (
			$php[ self::VERSION ] &&
			! $this->version_compare( PHP_VERSION, $php )
		) {
			$this->not_validated[ $this->basename ][] = self::PHP;

			return false;
		}

		return true;
	}

	/**
	 * Validate php extensions.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	private function validate_ext(): bool {

		foreach ( $this->normalize_array_requirement( self::EXT ) as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$this->not_validated[ $this->basename ][] = self::EXT;

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate WP.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	private function validate_wp(): bool {

		global $wp_version;

		$wp = $this->normalize_version_requirement( self::WP );

		if ( empty( $wp ) ) {
			return true;
		}

		if (
			$wp[ self::VERSION ] &&
			! $this->version_compare( $wp_version, $wp )
		) {
			$this->not_validated[ $this->basename ][] = self::WP;

			return false;
		}

		return true;
	}

	/**
	 * Validate Sugar Calendar.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	private function validate_sugar_calendar(): bool {

		$version = $this->normalize_version_requirement( self::SUGAR_CALENDAR );

		if ( empty( $version ) ) {
			return true;
		}

		if ( in_array( self::SUGAR_CALENDAR_DEV_VERSION_IN_ADDON, $version[ self::VERSION ], true ) ) {
			return true;
		}

		if (
			$version[ self::VERSION ] &&
			! $this->version_compare( SC_PLUGIN_VERSION, $version )
		) {
			$this->not_validated[ $this->basename ][] = self::SUGAR_CALENDAR;

			return false;
		}

		return true;
	}

	/**
	 * Version compare.
	 *
	 * @since 3.7.0
	 *
	 * @param string $version     Version to compare.
	 * @param array  $requirement Requirement.
	 *
	 * @return bool
	 */
	private function version_compare( string $version, array $requirement ): bool {

		$compare_arr = $this->get_compare_array( $requirement );

		foreach ( $compare_arr as $version2 => $compare ) {
			$result = version_compare( $version, $version2, $compare );

			if ( ! $result ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate license.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	private function validate_license(): bool {

		$license = $this->normalize_array_requirement( self::LICENSE );

		if ( empty( $license ) ) {
			return true;
		}

		if ( ! in_array( sugar_calendar()->get_license_type(), $license, true ) ) {
			$this->not_validated[ $this->basename ][] = self::LICENSE;

			return false;
		}

		return true;
	}

	/**
	 * Validate addon.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	private function validate_addon(): bool {

		$addon                  = $this->normalize_version_requirement( self::ADDON );
		$addon_version_constant = trim( $this->addon_requirements[ self::ADDON_VERSION_CONSTANT ] );

		if ( empty( $addon ) || empty( $addon_version_constant ) ) {
			return true;
		}

		if ( preg_grep( '/{.+_VERSION}/', $addon[ self::VERSION ] ) ) {
			return true;
		}

		if (
			$addon[ self::VERSION ] &&
			( ! defined( $addon_version_constant ) || ! $this->version_compare( constant( $addon_version_constant ), $addon ) )
		) {
			$this->not_validated[ $this->basename ][] = self::ADDON;

			return false;
		}

		return true;
	}

	/**
	 * Deactivate not validated addons.
	 *
	 * @since 3.7.0
	 */
	public function deactivate(): void {

		if ( ! self::DEACTIVATE_IF_NOT_MET ) {
			return;
		}

		if ( empty( $this->not_validated ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		unset( $_GET['activate'] );

		if ( empty( $this->validated ) ) {
			unset( $_GET['activate-multi'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		foreach ( $this->not_validated as $basename => $errors ) {
			if ( $errors === [ 'license' ] ) {
				continue;
			}

			deactivate_plugins( $basename );
		}
	}

	/**
	 * Show admin notices.
	 *
	 * @since 3.7.0
	 */
	public function show_notices(): void {

		$notices = $this->get_notices();

		if ( ! $notices ) {
			return;
		}

		$this->show_notice( '<p>' . implode( '</p><p>', $notices ) . '</p>' );
	}

	/**
	 * Get admin notices.
	 *
	 * @since 3.7.0
	 *
	 * @return string[]
	 */
	public function get_notices(): array {

		$notices = [];

		if ( empty( $this->not_validated ) ) {
			return $notices;
		}

		foreach ( $this->not_validated as $basename => $errors ) {
			$notice = $this->get_notice( $basename );

			if ( ! $notice ) {
				continue;
			}

			$notices[] = $notice;
		}

		return $notices;
	}

	/**
	 * Get an addon compatible message.
	 *
	 * @since        3.7.0
	 *
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 * @noinspection HtmlUnknownTarget
	 */
	public function get_addon_compatible_message( string $basename ): string {

		if ( empty( $this->not_validated[ $basename ] ) ) {
			return '';
		}

		$errors  = $this->not_validated[ $basename ];
		$message = $this->get_validation_message( $errors, $basename );

		if ( ! $message ) {
			return '';
		}

		$notice = sprintf(
		/* translators: translators: %1$s - requirements message. */
			__( 'It requires %1$s.', 'sugar-calendar' ),
			$message
		);

		return $notice;
	}

	/**
	 * Get notice.
	 *
	 * @since        3.7.0
	 *
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 * @noinspection HtmlUnknownTarget
	 */
	public function get_notice( string $basename ): string {

		if ( empty( $this->not_validated[ $basename ] ) ) {
			return '';
		}

		$errors  = $this->not_validated[ $basename ];
		$message = $this->get_validation_message( $errors, $basename );

		if ( ! $message ) {
			return '';
		}

		$is_sugar_calendar_plugin = false !== strpos( $basename, 'sugar-calendar.php' );

		if ( $is_sugar_calendar_plugin || in_array( self::ADDON, $errors, true ) ) {
			$source = __( 'Sugar Calendar plugin', 'sugar-calendar' );
		} else {
			$plugin_headers = $this->get_plugin_data( $this->requirements[ $basename ]['file'] );
			$source         = sprintf( /* translators: translators: %1$s - Sugar Calendar addon name. */
				__( '%1$s addon', 'sugar-calendar' ),
				$plugin_headers['Name']
			);
		}

		$notice = sprintf(
		/* translators: translators: %1$s - Sugar Calendar plugin or addon name, %2$d - requirements message. */
			__( 'The %1$s requires %2$s.', 'sugar-calendar' ),
			$source,
			$message
		);

		/**
		 * Filter the requirements' notice.
		 *
		 * @since 3.7.0
		 *
		 * @param string $notice       Notice.
		 * @param array  $errors       Validation errors.
		 * @param string $basename     Plugin basename.
		 * @param array  $requirements Addon requirements.
		 */
		return (string) apply_filters( 'sugar_calendar_requirements_notice', $notice, $errors, $basename, $this->requirements[ $basename ] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Get a validation message.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $errors   Validation errors.
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 */
	private function get_validation_message( array $errors, string $basename ): string {

		$addon_validation_message = $this->get_addon_validation_message( $errors, $basename );

		if ( $addon_validation_message ) {
			// Do not proceed further if addon is required in a higher version.
			return Helpers::array_string_list( [ $addon_validation_message ] );
		}

		$messages = [];

		$messages[] = $this->get_php_validation_message( $errors, $basename );
		$messages[] = $this->get_ext_validation_message( $errors, $basename );
		$messages[] = $this->get_wp_validation_message( $errors, $basename );
		$messages[] = $this->get_sugar_calendar_validation_message( $errors, $basename );
		$messages[] = $this->get_license_validation_message( $errors, $basename );

		return Helpers::array_string_list( array_filter( $messages ) );
	}

	/**
	 * Get a PHP validation message.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $errors   Validation errors.
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 */
	private function get_php_validation_message( array $errors, string $basename ): string {

		if ( self::SHOW_PHP_NOTICE && in_array( self::PHP, $errors, true ) ) {
			return $this->list_version_detailed( $this->requirements[ $basename ][ self::PHP ], 'PHP' );
		}

		return '';
	}

	/**
	 * Get an EXT validation message.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $errors   Validation errors.
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 */
	private function get_ext_validation_message( array $errors, string $basename ): string {

		if ( self::SHOW_EXT_NOTICE && in_array( self::EXT, $errors, true ) ) {
			$extension = Helpers::array_string_list( $this->requirements[ $basename ][ self::EXT ] );

			return sprintf(
			/* translators: %s - PHP extension name(s). */
				_n(
					'%s PHP extension',
					'%s PHP extensions',
					count( $this->requirements[ $basename ][ self::EXT ] ),
					'sugar-calendar'
				),
				$extension
			);
		}

		return '';
	}

	/**
	 * Get WP validation message.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $errors   Validation errors.
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 */
	private function get_wp_validation_message( array $errors, string $basename ): string {

		if ( self::SHOW_WP_NOTICE && in_array( self::WP, $errors, true ) ) {
			return $this->list_version_detailed( $this->requirements[ $basename ][ self::WP ], 'WordPress' );
		}

		return '';
	}

	/**
	 * Get Sugar Calendar validation message.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $errors   Validation errors.
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 */
	private function get_sugar_calendar_validation_message( array $errors, string $basename ): string {

		if ( self::SHOW_SUGAR_CALENDAR_NOTICE && in_array( self::SUGAR_CALENDAR, $errors, true ) ) {
			return $this->list_version_detailed( $this->requirements[ $basename ][ self::SUGAR_CALENDAR ], 'Sugar Calendar' );
		}

		return '';
	}

	/**
	 * Get LICENSE validation message.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $errors   Validation errors.
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 */
	private function get_license_validation_message( array $errors, string $basename ): string {

		if ( self::SHOW_LICENSE_NOTICE && in_array( self::LICENSE, $errors, true ) ) {
			$license = Helpers::array_string_list(
				array_map( 'ucfirst', $this->requirements[ $basename ][ self::LICENSE ] ),
				false
			);

			return sprintf(
			/* translators: %s - license name(s). */
				__( '%s license', 'sugar-calendar' ),
				$license
			);
		}

		return '';
	}

	/**
	 * Get an ADDON validation message.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $errors   Validation errors.
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 */
	private function get_addon_validation_message( array $errors, string $basename ): string {

		if ( self::SHOW_ADDON_NOTICE && in_array( self::ADDON, $errors, true ) ) {
			return $this->list_version_detailed(
				$this->requirements[ $basename ][ self::ADDON ],
				$this->get_plugin_data( $this->requirements[ $basename ]['file'] )['Name']
			);
		}

		return '';
	}

	/**
	 * Show admin notice.
	 *
	 * @since 3.7.0
	 *
	 * @param string $notice Message.
	 */
	private function show_notice( string $notice ): void {

		echo '<div class="notice notice-error">';
		echo wp_kses_post( $notice );
		echo '</div>';
	}

	/**
	 * Init addon requirements.
	 *
	 * @since 3.7.0
	 *
	 * @param string $basename Addon basename.
	 */
	private function init_addon_requirements( string $basename ): void {

		if ( ! array_key_exists( $basename, $this->requirements ) ) {
			$this->requirements[ $basename ] = [];
		}

		// Set addon basename.
		$this->requirements[ $basename ]['basename'] = $basename;

		// Set default addon version constant.
		if ( array_key_exists( self::ADDON_VERSION_CONSTANT, $this->requirements[ $basename ] ) ) {
			return;
		}

		$const = str_replace(
			'-',
			'_',
			strtoupper( explode( '/', $basename, 2 )[0] ) . '_VERSION'
		);

		$this->requirements[ $basename ][ self::ADDON_VERSION_CONSTANT ] = $const;
	}

	/**
	 * Get version from requirements array.
	 *
	 * @since 3.7.0
	 *
	 * @param array $requirement Array containing a requirement.
	 *
	 * @return string
	 */
	public function list_version( array $requirement ): string {

		$compare_arr = $this->get_compare_array( $requirement );
		$list        = [];

		foreach ( $compare_arr as $version2 => $compare ) {
			$list[] = $compare . $version2;
		}

		return implode( ', ', $list );
	}

	/**
	 * Get a version from requirements array in human-readable format.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $requirement Array containing a requirement.
	 * @param string $what        What is being checked.
	 *
	 * @return string
	 */
	private function list_version_detailed( array $requirement, string $what = '' ): string {

		$compare_arr = $this->get_compare_array( $requirement );
		$list        = [];

		$compare_to_string = [
			/* translators: %1$s - What is being checked (PHP, Sugar Calendar, etc.), %2$s - required version. This is used as the completion of the sentence "The {addon name} addon requires {here goes this string}". */
			'>=' => __( '%1$s %2$s or above', 'sugar-calendar' ),
			/* translators: %1$s - What is being checked (PHP, Sugar Calendar, etc.), %2$s - required version. This is used as the completion of the sentence "The {addon name} addon requires {here goes this string}". */
			'<=' => __( '%1$s %2$s or below', 'sugar-calendar' ),
			'='  => '%1$s %2$s',
			/* translators: %1$s - What is being checked (PHP, Sugar Calendar, etc.), %2$s - required version. This is used as the completion of the sentence "The {addon name} addon requires {here goes this string}". */
			'>'  => __( 'a newer version of %1$s than %2$s', 'sugar-calendar' ),
			/* translators: %1$s - What is being checked (PHP, Sugar Calendar, etc.), %2$s - required version. This is used as the completion of the sentence "The {addon name} addon requires {here goes this string}". */
			'<'  => __( 'an older version of %1$s than %2$s', 'sugar-calendar' ),
		];

		foreach ( $compare_arr as $version2 => $compare ) {
			if ( isset( $compare_to_string[ $compare ] ) ) {
				$list[] = sprintf( $compare_to_string[ $compare ], $what, $version2 );
			} else {
				$list[] = $what . ' ' . $compare . ' ' . $version2;
			}
		}

		return implode( ', ', $list );
	}

	/**
	 * Get a compare array in the following format: [ 'version' => 'compare', ... ].
	 *
	 * @since 3.7.0
	 *
	 * @param array $requirement Requirement.
	 *
	 * @return array
	 */
	public function get_compare_array( array $requirement ): array {

		$versions = $requirement[ self::VERSION ];
		$compares = $requirement[ self::COMPARE ];

		return array_combine( $versions, $compares );
	}

	/**
	 * Get requirements.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_requirements(): array {

		return $this->requirements;
	}

	/**
	 * Get not validated addons.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_not_validated_addons(): array {

		$all_addons = array_keys( $this->requirements );

		return array_values( array_diff( $all_addons, $this->validated ) );
	}
}
