<?php
/**
 * Main Plugin Class.
 *
 * @since 1.0.0
 */

namespace Sugar_Calendar;

use Sugar_Calendar\Admin\Addons\Addons;
use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Admin\Notifications;
use Sugar_Calendar\Admin\Tools\Importers;
use Sugar_Calendar\Block\Loader;
use Sugar_Calendar\Migrations\Migrations;
use Sugar_Calendar\SetupWizard\SetupWizard;
use Sugar_Calendar\Tasks\Tasks;
use Sugar_Calendar\UsageTracking\UsageTracking;
use Sugar_Calendar\Features\Loader as FeaturesLoader;
use Sugar_Calendar\Shortcodes\ModernShortcodes;
use Sugar_Calendar\Admin\Tools\DashboardWidget;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Main Plugin Class.
 *
 * @since 2.0.0
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @since 2.0.0
	 * @var object|Plugin
	 */
	private static $instance = null;

	/**
	 * Loader file.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $file = '';

	/**
	 * Whether or not this is the Pro version.
	 *
	 * @since 3.0.0
	 *
	 * @var bool
	 */
	private $is_pro = false;

	/**
	 * Main instance.
	 *
	 * Ensures that only one instance exists in memory at any one time.
	 * Also prevents needing to define globals all over the place.
	 *
	 * @since     2.0.0
	 *
	 * @static
	 * @staticvar array $instance
	 * @return object|Plugin
	 */
	public static function instance( $file = '' ) {

		// Return if already instantiated
		if ( self::is_instantiated() ) {
			return self::$instance;
		}

		// Setup the singleton
		self::setup_instance( $file );

		// Bootstrap.
		self::$instance->setup_files();
		self::$instance->setup_application();
		self::$instance->hooks();

		// Return the instance
		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function __clone() {

		_doing_it_wrong( __FUNCTION__, __NAMESPACE__, '2.0' );
	}

	/**
	 * Disable un-serializing of the class.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function __wakeup() {

		_doing_it_wrong( __FUNCTION__, __NAMESPACE__, '2.0' );
	}

	/**
	 * Public magic isset method allows checking any key from any scope.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __isset( $key = '' ) {

		return (bool) isset( $this->{$key} );
	}

	/**
	 * Public magic get method allows getting any value from any scope.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key = '' ) {

		return $this->__isset( $key )
			? $this->{$key}
			: null;
	}

	/**
	 * Return whether the main loading class has been instantiated or not.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if instantiated. False if not.
	 */
	private static function is_instantiated() {

		// Return true if instance is correct class
		if ( ! empty( self::$instance ) && ( self::$instance instanceof Plugin ) ) {
			return true;
		}

		// Return false if not instantiated correctly
		return false;
	}

	/**
	 * Setup the singleton instance
	 *
	 * @since 2.0.0
	 *
	 * @param string $file
	 */
	private static function setup_instance( $file = '' ) {

		self::$instance       = new Plugin;
		self::$instance->file = $file;
	}

	/**
	 * Setup files.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function setup_files() {

		// Lite
		$this->include_lite();

		if ( $this->is_pro() ) {
			$this->include_pro();
		}

		// Admin specific
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$this->include_admin();

			// Front-end specific
		} else {
			$this->include_frontend();
		}
	}

	/**
	 * Setup the rest of the application.
	 *
	 * @since 2.0.0
	 * @since 3.7.0 Move the modern shortcodes to load last.
	 */
	private function setup_application() {

		// Database tables.
		new Events_Table();
		new Meta_Table();

		// Backwards Compatibility.
		new Posts\Meta\Back_Compat();

		// Taxonomy Features.
		new Term_Timezones( $this->file );
		new Term_Colors( $this->file );

		// Load the common Features.
		$this->get_common_features();

		// Load the common src Features.
		$this->get_src_features();

		// Load the Block.
		$this->get_blocks();

		// Load the integrations.
		$this->get_integrations();

		// Load the Setup Wizard.
		$this->get_setup_wizard();

		if ( is_admin() ) {
			$this->get_admin();
			$this->get_importers();
		}

		if ( $this->is_pro() ) {
			$this->get_pro();
		}

		$this->get_frontend();

		// Load the Shortcodes.
		$this->get_modern_shortcodes();

		// Load the Dashboard Widget.
		$this->get_dashboard_widget();
	}

	/**
	 * Load the common Features.
	 *
	 * @since 3.0.0
	 *
	 * @return Common\Features\Loader()
	 */
	public function get_common_features() {

		static $features;

		if ( ! isset( $features ) ) {
			/**
			 * Filters the common Features loader.
			 *
			 * @since 3.0.0
			 *
			 * @param Common\Features\Loader $loader The common Features loader.
			 */
			$features = apply_filters( 'sugar_calendar_get_common_features', new Common\Features\Loader() );

			if ( method_exists( $features, 'init' ) ) {
				$features->init();
			}
		}

		return $features;
	}

	/**
	 * Load the common src Features.
	 *
	 * @since 3.0.0
	 *
	 * @return Sugar_Calendar\Features\Loader
	 */
	public function get_src_features() {

		static $features;

		if ( ! isset( $features ) ) {

			$features = new FeaturesLoader();

			if ( method_exists( $features, 'init' ) ) {
				$features->init();
			}
		}

		return $features;
	}

	/** Includes **************************************************************/

	/**
	 * Include non-specific files.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	private function include_lite() {

		// Database Engine
		require_once SC_PLUGIN_DIR . 'includes/classes/database/engine/Base.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/engine/Table.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/engine/Query.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/engine/Column.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/engine/Row.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/engine/Schema.php';

		// Database Queries
		require_once SC_PLUGIN_DIR . 'includes/classes/database/engine/Queries/Meta.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/engine/Queries/Compare.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/engine/Queries/Date.php';

		// Events Databases
		require_once SC_PLUGIN_DIR . 'includes/classes/database/events/Query.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/events/Row.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/events/Schema.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/events/TableEvents.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/database/events/TableEventmeta.php';

		// Utilities
		require_once SC_PLUGIN_DIR . 'includes/classes/utilities/class-term-meta-ui.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/utilities/ical-to-array.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/utilities/ical-rrule-sequencer.php';

		// Terms
		require_once SC_PLUGIN_DIR . 'includes/classes/terms/class-term-colors.php';
		require_once SC_PLUGIN_DIR . 'includes/classes/terms/class-term-timezones.php';

		// Event files
		require_once SC_PLUGIN_DIR . 'includes/events/capabilities.php';
		require_once SC_PLUGIN_DIR . 'includes/events/functions.php';
		require_once SC_PLUGIN_DIR . 'includes/events/meta-data.php';
		require_once SC_PLUGIN_DIR . 'includes/events/relationships.php';

		// Post files
		require_once SC_PLUGIN_DIR . 'includes/post/cron.php';
		require_once SC_PLUGIN_DIR . 'includes/post/feed.php';
		require_once SC_PLUGIN_DIR . 'includes/post/functions.php';
		require_once SC_PLUGIN_DIR . 'includes/post/meta.php';
		require_once SC_PLUGIN_DIR . 'includes/post/query-filters.php';
		require_once SC_PLUGIN_DIR . 'includes/post/taxonomies.php';
		require_once SC_PLUGIN_DIR . 'includes/post/types.php';
		require_once SC_PLUGIN_DIR . 'includes/post/relationship.php';

		// Legacy
		require_once SC_PLUGIN_DIR . 'includes/themes/legacy/functions.php';
		require_once SC_PLUGIN_DIR . 'includes/themes/legacy/scripts.php';
		require_once SC_PLUGIN_DIR . 'includes/themes/legacy/shortcodes.php';
		require_once SC_PLUGIN_DIR . 'includes/themes/legacy/widgets.php';
		require_once SC_PLUGIN_DIR . 'includes/themes/legacy/hooks.php';

		// Common files
		require_once SC_PLUGIN_DIR . 'includes/common/assets.php';
		require_once SC_PLUGIN_DIR . 'includes/common/color.php';
		require_once SC_PLUGIN_DIR . 'includes/common/editor.php';
		require_once SC_PLUGIN_DIR . 'includes/common/general.php';
		require_once SC_PLUGIN_DIR . 'includes/common/preferences.php';
		require_once SC_PLUGIN_DIR . 'includes/common/settings.php';
		require_once SC_PLUGIN_DIR . 'includes/common/time-zones.php';
		require_once SC_PLUGIN_DIR . 'includes/common/time.php';
		require_once SC_PLUGIN_DIR . 'includes/common/hooks.php';
		require_once SC_PLUGIN_DIR . 'includes/common/Utils.php';

		// Common Features.
		require_once SC_PLUGIN_DIR . 'includes/common/Features/HasRequirementsInterface.php';
		require_once SC_PLUGIN_DIR . 'includes/common/Features/CheckRequirements.php';
		require_once SC_PLUGIN_DIR . 'includes/common/Features/LoaderAbstract.php';
		require_once SC_PLUGIN_DIR . 'includes/common/Features/FeatureAbstract.php';
		require_once SC_PLUGIN_DIR . 'includes/common/Features/Loader.php';
	}

	/**
	 * Include administration specific files.
	 *
	 * @since 2.0.0
	 */
	private function include_admin() {

		// Include the admin files
		require_once SC_PLUGIN_DIR . 'includes/admin/assets.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/editor.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/general.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/help.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/screen-options.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/menu.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/meta-boxes.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/nav.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/posts.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/upgrades.php';
		require_once SC_PLUGIN_DIR . 'includes/admin/hooks.php';

		// Legacy
		require_once SC_PLUGIN_DIR . 'includes/admin/settings.php';

		// Maybe include front-end on AJAX, add/edit post page, or widgets, to
		// load all shortcodes, widgets, assets, etc...
		if (

			// Admin AJAX
			wp_doing_ajax()

			||

			// Specific admin pages
			(
				! empty( $GLOBALS['pagenow'] )

				&&

				in_array( $GLOBALS['pagenow'], [ 'post.php', 'widgets.php' ], true )
			)
		) {
			$this->include_frontend();
		}
	}

	/**
	 * Include front-end specific files.
	 *
	 * @since 2.0.0
	 */
	private function include_frontend() {

		// Legacy Theme
		require_once SC_PLUGIN_DIR . 'includes/themes/legacy/ajax.php';
		require_once SC_PLUGIN_DIR . 'includes/themes/legacy/calendar.php';
		require_once SC_PLUGIN_DIR . 'includes/themes/legacy/event-display.php';
		require_once SC_PLUGIN_DIR . 'includes/themes/legacy/events-list.php';
	}

	/**
	 * Include Standard (non Lite) files, if they exist.
	 *
	 * @since 2.0.3
	 */
	private function include_standard() {

		// Files & directory
		$files = [];
		$dir   = trailingslashit( __DIR__ ) . 'includes/standard';

		// Bail if standard directory does not exist
		if ( ! is_dir( $dir ) ) {
			return;
		}

		// Try to open the directory
		$dh = opendir( $dir );

		// Bail if directory exists but cannot be opened
		if ( empty( $dh ) ) {
			return;
		}

		// Look for files in the directory
		while ( ( $plugin = readdir( $dh ) ) !== false ) {
			$ext = substr( $plugin, -4 );

			if ( $ext === '.php' ) {
				$name           = substr( $plugin, 0, strlen( $plugin ) - 4 );
				$files[ $name ] = trailingslashit( $dir ) . $plugin;
			}
		}

		// Close the directory
		closedir( $dh );

		// Skip empty index files
		unset( $files['index'] );

		// Bail if no files
		if ( empty( $files ) ) {
			return;
		}

		// Sort files alphabetically
		ksort( $files );

		// Include each file
		foreach ( $files as $file ) {
			require_once $file;
		}
	}

	/**
	 * Whether or not this is the Pro version.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function is_pro() {

		if ( file_exists( SC_PLUGIN_DIR . 'includes/pro/Pro.php' ) ) {
			$this->is_pro = true;
		}

		return $this->is_pro;
	}

	/**
	 * Include the Pro version.
	 *
	 * @since 3.0.0
	 */
	public function include_pro() {

		require_once SC_PLUGIN_DIR . 'includes/pro/Pro.php';
	}

	/**
	 * Get the Pro instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Pro\Pro
	 */
	public function get_pro() {

		static $pro;

		if ( ! isset( $pro ) ) {
			/**
			 * Filters the pro initializer.
			 *
			 * @since 3.0.0
			 *
			 * @param Pro\Pro $pro The Pro version initializer.
			 */
			$pro = apply_filters( 'sugar_calendar_get_pro', new Pro\Pro() );

			if ( method_exists( $pro, 'init' ) ) {
				$pro->init();
			}
		}

		return $pro;
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 * @since 3.5.0 Add the `perform_post_upgrade` hook.
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'install' ] );
		add_action( 'plugins_loaded', [ $this, 'get_connect' ], 15 );

		// Initialize Action Scheduler tasks.
		add_action( 'init', [ $this, 'get_tasks' ], 5 );

		add_action( 'plugins_loaded', [ $this, 'get_migrations' ] );
		add_action( 'plugins_loaded', [ $this, 'get_usage_tracking' ] );
		add_action( 'plugins_loaded', [ $this, 'get_notifications' ] );

		// Priority is 99 so it runs after the main upgrade routine.
		add_action( 'init', [ $this, 'perform_post_upgrade' ], 99 );

		add_action( 'init', [ $this, 'get_addons' ] );
	}

	/**
	 * Plugin installation.
	 *
	 * @since 3.0.0
	 */
	public function install() {

		// Bail if installation already happened.
		if ( ! get_option( 'sugar_calendar_is_first_activation', false ) ) {
			return;
		}

		// Prevent future activations from running
		// the below installation logic.
		update_option( 'sugar_calendar_is_first_activation', false );

		// Create default content if it's the first activation.
		$this->maybe_create_default_calendar();
	}

	/**
	 * Create a default calendar.
	 *
	 * @since 3.0.0
	 */
	private function maybe_create_default_calendar() {

		// Get the default calendar.
		$default_calendar = sugar_calendar_get_default_calendar();

		// Bail if a default calendar is already set,
		// and it actually exists.
		if ( ! empty( $default_calendar ) && get_term( $default_calendar ) ) {
			return;
		}

		// Create a new calendar.
		$calendar = wp_insert_term(
			esc_html__( 'My Calendar', 'sugar-calendar-lite' ),
			sugar_calendar_get_calendar_taxonomy_id(),
			[
				'description' => esc_html__( 'The default calendar events will be added to.', 'sugar-calendar-lite' ),
			]
		);

		// Bail if a new calendar couldn't be created.
		if ( is_wp_error( $calendar ) ) {
			return;
		}

		if ( ! empty( $calendar['term_id'] ) ) {
			add_term_meta( $calendar['term_id'], 'color', '#5685BD', true );
		}

		// Set the new calendar as default.
		Options::update(
			sugar_calendar_get_default_calendar_option_name(),
			$calendar['term_id']
		);
	}

	/**
	 * Get the default capability to manage everything.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_capability_manage_options() {

		/**
		 * Filters the default capability to manage everything.
		 *
		 * @since 3.0.0
		 *
		 * @param string $capability The default capability to manage everything.
		 */
		return apply_filters( 'sugar_calendar_get_capability_manage_options', 'manage_options' );
	}

	/**
	 * Load the plugin admin notifications functionality and initializes it.
	 *
	 * @since 3.0.0
	 *
	 * @return Notifications
	 */
	public function get_notifications() {

		static $notifications;

		if ( ! isset( $notifications ) ) {

			/**
			 * Filters Notifications instance.
			 *
			 * @since 3.0.0
			 *
			 * @param Notifications $notifications Notifications instance.
			 */
			$notifications = apply_filters(
				'sugar_calendar_get_notifications',
				new Notifications()
			);

			if ( method_exists( $notifications, 'init' ) ) {
				$notifications->init();
			}
		}

		return $notifications;
	}

	/**
	 * Perform post-upgrade tasks.
	 *
	 * @since 3.5.0
	 * @since 3.6.0 Added `maybe_flush_rewrite_rules` method.
	 */
	public function perform_post_upgrade() {

		// Get the plugin version before the upgrade.
		$old_sugar_calendar_version = get_option( 'sugar_calendar_version', false );

		// Bail if the plugin is already at the latest version.
		if (
			! empty( $old_sugar_calendar_version )
			&&
			version_compare( $old_sugar_calendar_version, SC_PLUGIN_VERSION, '>=' )
		) {
			// Nothing to do here since we are already at the latest version.
			return;
		}

		$this->maybe_flush_rewrite_rules();

		/**
		 * Post upgrade action.
		 *
		 * @since 3.5.0
		 */
		do_action( 'sugar_calendar_post_upgrade' );

		// Set the plugin version to the current version.
		update_option( 'sugar_calendar_version', SC_PLUGIN_VERSION, true );
	}

	/**
	 * Load the plugin usage tracking.
	 *
	 * @since 3.0.0
	 *
	 * @return UsageTracking
	 */
	public function get_usage_tracking() {

		static $usage_tracking;

		if ( ! isset( $usage_tracking ) ) {
			$usage_tracking = apply_filters( 'sugar_calendar_core_get_usage_tracking', new UsageTracking() );

			if ( method_exists( $usage_tracking, 'load' ) ) {
				add_action( 'after_setup_theme', [ $usage_tracking, 'load' ] );
			}
		}

		return $usage_tracking;
	}

	/**
	 * Get/load the tasks code of the plugin.
	 *
	 * @since 3.0.0
	 *
	 * @return Tasks
	 */
	public function get_tasks() {

		static $tasks;

		if ( ! isset( $tasks ) ) {
			/**
			 * Filters Tasks instance.
			 *
			 * @since 3.0.0
			 *
			 * @param Tasks $tasks Tasks instance.
			 */
			$tasks = apply_filters( 'sugar_calendar_get_tasks', new Tasks() );

			if ( method_exists( $tasks, 'init' ) ) {
				$tasks->init();
			}
		}

		return $tasks;
	}

	/**
	 * Get the Migrations object.
	 *
	 * @since 3.0.0
	 *
	 * @return Migrations
	 */
	public function get_migrations() {

		static $migrations;

		if ( ! isset( $migrations ) ) {
			$migrations = new Migrations();

			if ( method_exists( $migrations, 'hooks' ) ) {
				$migrations->hooks();
			}
		}

		return $migrations;
	}

	/**
	 * Load the plugin admin area.
	 *
	 * @since 3.0.0
	 *
	 * @return Area
	 */
	public function get_admin() {

		static $admin;

		if ( ! isset( $admin ) ) {
			$admin = apply_filters( 'sugar_calendar_get_admin', new Area() );

			if ( method_exists( $admin, 'hooks' ) ) {
				$admin->hooks();
			}
		}

		return $admin;
	}

	/**
	 * Initialize the Connect functionality.
	 * This has to execute after pro was loaded, since we need check for plugin license type (if pro or not).
	 * That's why it's hooked to the same WP hook (`plugins_loaded`) as `get_pro` with lower priority.
	 *
	 * @since 3.0.0
	 */
	public function get_connect() {

		static $connect;

		if ( ! isset( $connect ) && ! $this->is_pro() ) {
			$connect = apply_filters( 'sugar_calendar_core_get_connect', new Connect() );

			if ( method_exists( $connect, 'hooks' ) ) {
				$connect->hooks();
			}
		}

		return $connect;
	}

	/**
	 * Get the Frontend instance.
	 *
	 * @since 3.1.0
	 *
	 * @return Frontend\Loader
	 */
	public function get_frontend() {

		static $frontend;

		if ( ! isset( $frontend ) ) {
			$frontend = new Frontend\Loader();

			if ( method_exists( $frontend, 'init' ) ) {
				$frontend->init();
			}
		}

		return $frontend;
	}

	/**
	 * Get the Blocks.
	 *
	 * @since 3.1.0
	 *
	 * @return mixed
	 */
	public function get_blocks() {

		static $blocks;

		if ( ! isset( $blocks ) ) {
			// Initialize the Blocks loader.
			$loader = new Loader();

			$loader->init();

			$blocks = $loader->get_blocks();
		}

		return $blocks;
	}

	/**
	 * Get the Shortcodes.
	 *
	 * @since 3.4.0
	 *
	 * @return ModernShortcodes
	 */
	public function get_modern_shortcodes() {

		static $shortcodes;

		if ( ! isset( $shortcodes ) ) {

			// Initialize the Shortcodes loader.
			$shortcodes = new ModernShortcodes();

			$shortcodes->init();
		}

		return $shortcodes;
	}

	/**
	 * Get the Dashboard Widget.
	 *
	 * @since 3.7.0
	 *
	 * @return DashboardWidget
	 */
	public function get_dashboard_widget() {

		static $dashboard_widget;

		if ( ! isset( $dashboard_widget ) ) {

			$dashboard_widget = new DashboardWidget();

			$dashboard_widget->init();
		}

		return $dashboard_widget;
	}

	/**
	 * Get the Integrations.
	 *
	 * @since 3.2.0
	 *
	 * @return Integrations\Loader
	 */
	public function get_integrations() {

		static $integrations;

		if ( ! isset( $integrations ) ) {
			$integrations = new Integrations\Loader();

			if ( method_exists( $integrations, 'init' ) ) {
				$integrations->init();
			}
		}

		return $integrations;
	}

	/**
	 * Get the current license type.
	 *
	 * @since 3.0.0
	 *
	 * @return string Default value: lite.
	 */
	public function get_license_type() {

		$license = Options::get( 'license' );
		$type    = $license['type'] ?? null;

		if ( empty( $type ) ) {
			$type = 'lite';
		}

		return strtolower( $type );
	}

	/**
	 * Get the current license key.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_license_key() {

		$license = Options::get( 'license' );
		$key     = $license['key'] ?? '';

		return $key;
	}

	/**
	 * Get the importers.
	 *
	 * @since 3.3.0
	 *
	 * @return Importers
	 */
	public function get_importers() {

		static $importers;

		if ( ! isset( $importers ) ) {

			$importers = new Importers();

			$importers->hooks();
		}

		return $importers;
	}

	/**
	 * Flush rewrite rules if needed.
	 *
	 * @since 3.6.0
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules() {

		if ( version_compare( SC_PLUGIN_VERSION, '3.7.0', '<=' ) ) {
			// Flush rewrite rules.
			flush_rewrite_rules();

			// To flush the rules in DB (without .htaccess).
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Get the Setup Wizard.
	 *
	 * @since 3.7.0
	 *
	 * @return SetupWizard
	 */
	public function get_setup_wizard() {

		static $setup_wizard;

		if ( ! isset( $setup_wizard ) ) {
			$setup_wizard = new SetupWizard();

			if ( method_exists( $setup_wizard, 'hooks' ) ) {
				$setup_wizard->hooks();
			}
		}

		return $setup_wizard;
	}

	/**
	 * Check if the RSVP addon is active.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public function is_rsvp_addon_active() {

		static $is_rsvp_addon_active;

		if ( ! isset( $is_rsvp_addon_active ) ) {
			$is_rsvp_addon_active = is_plugin_active( 'sc-rsvp/sc-rsvp.php' );
		}

		return $is_rsvp_addon_active;
	}

	/**
	 * Get the addons.
	 *
	 * @since 3.7.0
	 *
	 * @return Addons
	 */
	public function get_addons() {

		static $addons;

		if ( ! isset( $addons ) ) {
			/**
			 * Filters the Addons loader.
			 *
			 * @since 3.7.0
			 *
			 * @param Addons $addons The Addons loader.
			 */
			$addons = apply_filters( 'sugar_calendar_get_addons', new Addons() );

			if ( method_exists( $addons, 'hooks' ) ) {
				$addons->hooks();
			}
		}

		return $addons;
	}
}
