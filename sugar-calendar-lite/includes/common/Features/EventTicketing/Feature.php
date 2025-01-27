<?php

namespace Sugar_Calendar\Common\Features\EventTicketing;

use Sugar_Calendar\AddOn\Ticketing\Frontend;
use Sugar_Calendar\Common\Features\FeatureAbstract;
use Sugar_Calendar\AddOn\Ticketing\Admin\Area;
use Sugar_Calendar\AddOn\Ticketing\Settings;
use Sugar_Calendar\AddOn\Ticketing\Database;
use Sugar_Calendar\Helpers;

class Feature extends FeatureAbstract {

	/**
	 * Setup.
	 *
	 * @since 3.1.0
	 */
	protected function setup() {}

	/**
	 * Setup.
	 *
	 * @since 3.1.0
	 */
	public function setup_plugin() {

		$this->setup_constants();
		$this->setup_files();
		$this->setup_application();
	}

	/**
	 * Setup the constants.
	 *
	 * @since 3.1.0
	 */
	private function setup_constants() {

		// Prepare file & directory.
		$dir = basename( __DIR__ );

		if ( ! defined( 'SC_CORE_ET_PLUGIN_DIR' ) ) {
			/**
			 * Feature Folder Path.
			 *
			 * @since 3.1.0
			 */
			define( 'SC_CORE_ET_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
		}

		if ( ! defined( 'SC_CORE_ET_PLUGIN_URL' ) ) {
			/**
			 * Feature Folder URL.
			 *
			 * @since 3.1.0
			 */
			define( 'SC_CORE_ET_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) . $dir ) );
		}

		if ( ! defined( 'SC_CORE_ET_PLUGIN_ASSETS_URL' ) ) {
			/**
			 * Feature assets URL.
			 *
			 * @since 3.1.0
			 */
			define( 'SC_CORE_ET_PLUGIN_ASSETS_URL', SC_PLUGIN_ASSETS_URL . 'css/features/event-ticketing' );
		}
	}

	/**
	 * Setup files.
	 *
	 * @since 3.1.0
	 *
	 * @return void
	 */
	private function setup_files() {

		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$this->include_admin();
		} else {
			$this->get_frontend();
			$this->include_frontend();
		}

		$this->include_classes();
		$this->include_common();
		$this->include_dropins();
		$this->include_gateways();
		$this->include_integrations();
	}

	/**
	 * Automatically include administration specific files.
	 *
	 * @since 3.1.0
	 */
	private function include_admin() {

		$this->slurp( 'admin' );

		( new Area() )->hooks();
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
	 * Automatically include front-end specific files.
	 *
	 * @since 3.1.0
	 */
	private function include_frontend() {

		$this->slurp( 'front-end' );
	}

	/**
	 * Automatically include any files in the /includes/classes/ directory.
	 *
	 * @since 3.1.0
	 */
	private function include_classes() {

		$this->slurp( 'classes' );

		$dir = trailingslashit( __DIR__ ) . 'includes/classes/';

		// Attendees Databases.
		require_once $dir . 'database/attendees/class-query.php';
		require_once $dir . 'database/attendees/class-row.php';
		require_once $dir . 'database/attendees/class-schema.php';
		require_once $dir . 'database/attendees/class-table-attendees.php';

		// Discounts Databases.
		require_once $dir . 'database/discounts/class-query.php';
		require_once $dir . 'database/discounts/class-row.php';
		require_once $dir . 'database/discounts/class-schema.php';
		require_once $dir . 'database/discounts/class-table-discounts.php';

		// Orders Databases.
		require_once $dir . 'database/orders/class-query.php';
		require_once $dir . 'database/orders/class-row.php';
		require_once $dir . 'database/orders/class-schema.php';
		require_once $dir . 'database/orders/class-table-orders.php';

		// Tickets Databases.
		require_once $dir . 'database/tickets/class-query.php';
		require_once $dir . 'database/tickets/class-row.php';
		require_once $dir . 'database/tickets/class-schema.php';
		require_once $dir . 'database/tickets/class-table-tickets.php';

		// Emails Class.
		require_once $dir . 'emails/class-emails.php';

		// Export.
		require_once $dir . 'utilities/csv-export.php';
		require_once $dir . 'export/tickets.php';
	}

	/**
	 * Automatically include files that are shared between all contexts.
	 *
	 * @since 3.1.0
	 */
	private function include_common() {

		$this->slurp( 'common' );
	}

	/**
	 * Automatically include any files in the /includes/drop-ins/ directory.
	 *
	 * @since 3.1.0
	 */
	private function include_dropins() {

		$this->slurp( 'drop-ins' );
	}

	/**
	 * Automatically include any files in the /includes/gateways/ directory.
	 *
	 * @since 3.1.0
	 */
	private function include_gateways() {

		$this->slurp( 'gateways' );
	}

	/**
	 * Automatically include any files in the /includes/integrations/ directory.
	 *
	 * @since 3.1.0
	 */
	private function include_integrations() {

		$this->slurp( 'integrations' );
	}

	/**
	 * Automatically include any files in a given directory.
	 *
	 * @since 3.1.0
	 *
	 * @param string $dir The name of the directory to include files from.
	 */
	private function slurp( $dir = '' ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Files & directory.
		$files = [];
		$dir   = trailingslashit( __DIR__ ) . 'includes/' . $dir;

		// Bail if standard directory does not exist.
		if ( ! is_dir( $dir ) ) {
			return;
		}

		// Try to open the directory.
		$dh = opendir( $dir );

		// Bail if directory exists but cannot be opened.
		if ( empty( $dh ) ) {
			return;
		}

		// Look for files in the directory.
		while ( ( $plugin = readdir( $dh ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$ext = substr( $plugin, -4 );

			if ( $ext === '.php' ) {
				$name           = substr( $plugin, 0, strlen( $plugin ) - 4 );
				$files[ $name ] = trailingslashit( $dir ) . $plugin;
			}
		}

		// Close the directory.
		closedir( $dh );

		// Bail if no files.
		if ( empty( $files ) ) {
			return;
		}

		// Sort files alphabetically.
		ksort( $files );

		// Include each file.
		foreach ( $files as $file ) {
			require_once $file;
		}
	}

	/**
	 * Setup the rest of the application.
	 *
	 * @since 3.1.0
	 */
	private function setup_application() {

		// Database tables.
		new Database\Attendees_Table();
		new Database\Discounts_Table();
		new Database\Orders_Table();
		new Database\Tickets_Table();

		// Instantiate any classes or setup any dependency injections here.
		new \Sugar_Calendar\AddOn\Ticketing\Gateways\Checkout();

		$this->handle_pro_addon_integrations();
	}

	/**
	 * Handle Pro Addon Integrations.
	 *
	 * @since 3.1.0
	 */
	private function handle_pro_addon_integrations() {

		if (
			class_exists( 'WooCommerce' ) &&
			sugar_calendar()->is_pro() &&
			is_plugin_active( 'sc-event-ticketing/sc-event-ticketing.php' )
		) {
			$wc_integration_file = plugin_dir_path( SC_PLUGIN_DIR ) . 'sc-event-ticketing/sc-event-ticketing/includes/integrations/woocommerce.php';

			if ( file_exists( $wc_integration_file ) ) {
				// We are manually including the WooCommerce file since we de-hook it.
				require_once $wc_integration_file;

				new \Sugar_Calendar\AddOn\Ticketing\Integrations\WooCommerce();
			}
		}
	}

	/**
	 * WP Hooks.
	 *
	 * @since 3.1.0
	 */
	protected function hooks() {

		add_action( 'plugins_loaded', [ $this, 'handle_addon_plugin' ], 11 );
		add_action( 'plugins_loaded', [ $this, 'setup_plugin' ], 20 );

		add_action( 'admin_init', [ $this, 'install' ] );
		add_action( 'sugar_calendar_updater', [ $this, 'updater' ] );
	}

	/**
	 * Attempt to load the updater.
	 *
	 * @since 3.1.0
	 *
	 * @param string $key The License Key.
	 */
	public function updater( $key ) {

		$version = $this->get_addon_version();

		// For versions `1.2.0`, the updater is initialized in the addon plugin.
		if (
			$version === false ||
			version_compare( $version, '1.2.0', '>' )
		) {
			return;
		}

		if ( ! class_exists( '\Sugar_Calendar\Pro\License\Updater' ) ) {
			return;
		}

		new \Sugar_Calendar\Pro\License\Updater(
			[
				'plugin_name' => 'Sugar Calendar - Event Ticketing',
				'plugin_slug' => 'sc-event-ticketing',
				'plugin_path' => 'sc-event-ticketing/sc-event-ticketing.php',
				'plugin_url'  => trailingslashit( plugins_url( 'sc-event-ticketing/sc-event-ticketing' ) ),
				'version'     => $version,
				'key'         => $key,
			]
		);
	}

	/**
	 * Whether we should load the add-on updater.
	 *
	 * @since 3.1.0
	 *
	 * @return false|string Returns `false` if the license key is not valid or the addon is not activated.
	 *                      Returns `1.2.0` if the addon version is not found, otherwise returns the addon version.
	 */
	private function get_addon_version() {

		if ( ! Helpers::is_license_valid() ) {
			return false;
		}

		// If the add-on is not activated, then don't hook the updater.
		if ( ! class_exists( '\Sugar_Calendar\AddOn\Ticketing\Plugin' ) ) {
			return false;
		}

		$event_ticketing_addon = new \Sugar_Calendar\AddOn\Ticketing\Plugin();

		// If we can't find the version, then hook the updater.
		if ( ! property_exists( $event_ticketing_addon, 'version' ) ) {
			return '1.2.0';
		}

		return $event_ticketing_addon->version;
	}

	/**
	 * Unhook the add-on plugin.
	 *
	 * @since 3.1.0
	 */
	public function handle_addon_plugin() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks, Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.NestingLevel.MaxExceeded

		global $wp_filter;

		foreach ( $wp_filter as $filter_name => $hook ) {
			if (
				$filter_name !== 'plugins_loaded' &&
				strpos( $filter_name, 'deactivate_sc-event-ticketing' ) === false &&
				strpos( $filter_name, 'activate_sc-event-ticketing' ) === false
			) {
				continue;
			}

			// Loop through each of the callbacks.
			foreach ( $hook->callbacks as $priority => $callbacks ) {

				foreach ( $callbacks as $callback_key => $callback_info ) {

					if (
						empty( $callback_info['function'] ) ||
						! is_array( $callback_info['function'] ) ||
						! is_a( $callback_info['function'][0], 'SC_Event_Ticketing_Requirements_Check' )
					) {
						continue;
					}

					$hook->remove_filter(
						$filter_name,
						$hook->callbacks[ $priority ][ $callback_key ]['function'],
						$priority
					);
				}
			}
		}
	}

	/**
	 * Ticketing Feature installer.
	 *
	 * @since 3.1.0
	 */
	public function install() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$settings = get_option( 'sc_et_settings' );

		if ( ! empty( $settings ) ) {
			return; // Install already completed.
		}

		$author_id = current_user_can( 'edit_others_pages' ) ? get_current_user_id() : 1;

		if ( ! Settings\get_setting( 'receipt_page' ) ) {

			// Receipt Page.
			$receipt_page = wp_insert_post(
				[
					'post_title'     => esc_html__( 'Ticket Receipt', 'sugar-calendar-lite' ),
					'post_content'   => '[sc_event_tickets_receipt]',
					'post_status'    => 'publish',
					'post_author'    => $author_id,
					'post_type'      => 'page',
					'comment_status' => 'closed',
				]
			);

			Settings\update_setting( 'receipt_page', $receipt_page );
		}

		if ( ! Settings\get_setting( 'ticket_page' ) ) {

			// Tickets Page.
			$ticket_page = wp_insert_post(
				[
					'post_title'     => esc_html__( 'Ticket Details', 'sugar-calendar-lite' ),
					'post_content'   => '[sc_event_tickets_details]',
					'post_status'    => 'publish',
					'post_author'    => $author_id,
					'post_type'      => 'page',
					'comment_status' => 'closed',
				]
			);

			Settings\update_setting( 'ticket_page', $ticket_page );

		}

		if ( ! Settings\get_setting( 'receipt_message' ) ) {

			$subject = 'Your event ticket purchase!';
			$message = "Hello {name},

Your event ticket purchase to <strong>{event_title}</strong> was successful! See below for the specifics of when and where the event is taking place!

<strong>Event date and time</strong>: {event_date} at {event_start_time}

See event location and further details <a href=\"{event_url}\">here</a>.

Your tickets:

{tickets}

<a href=\"{receipt_url}\">View this receipt and event details in your browser</a>.";

			Settings\update_setting( 'receipt_subject', $subject );
			Settings\update_setting( 'receipt_message', $message );

		}

		if ( ! Settings\get_setting( 'ticket_message' ) ) {

			$subject = 'Your event ticket!';
			$message = "Hello {attendee_name},

Your event ticket to <strong>{event_title}</strong> is below! See the following details for specifics of when and where the event is taking place!

<strong>Event date and time</strong>: {event_date} at {event_start_time}

See event location and further details <a href=\"{event_url}\">here</a>.

Your ticket:
<ul>
 	<li>ID: {ticket_id}</li>
 	<li>Code: {ticket_code}</li>
</ul>
<a href=\"{ticket_url}\">View this ticket in your browser</a>.";

			Settings\update_setting( 'ticket_subject', $subject );
			Settings\update_setting( 'ticket_message', $message );

		}
	}

	/**
	 * Get the Event Ticketing Feature requirements.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	public function get_requirements() {

		return [];
	}
}
