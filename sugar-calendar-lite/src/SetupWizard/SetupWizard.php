<?php

namespace Sugar_Calendar\SetupWizard;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Admin\Pages\SettingsGeneralTab;
use Sugar_Calendar\Admin\Pages\Welcome;
use Sugar_Calendar\Helpers\WP;

/**
 * Class SetupWizard.
 *
 * @since 3.7.0
 */
class SetupWizard {

	/**
	 * Rest API instance.
	 *
	 * @since 3.7.0
	 *
	 * @var RestApi
	 */
	private $api;

	/**
	 * Auth instance.
	 *
	 * @since 3.7.0
	 *
	 * @var Auth
	 */
	private $auth;

	/**
	 * Setup Wizard URL.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const URL = 'https://events.sugarcalendarapi.com/setupwizard/v1';

	/**
	 * Setup Wizard first run transient.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const TRANSIENT_FIRST_RUN = 'sugar_calendar_setup_wizard_first_run';

	/**
	 * Setup Wizard redirect parameter.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const REDIRECT_PARAMETER = 'sugar_calendar_setup_wizard';

	/**
	 * Constructor.
	 *
	 * @since 3.7.0
	 */
	public function __construct() {

		$this->auth = new Auth();
		$this->api  = new RestApi();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'maybe_redirect' ], PHP_INT_MAX );
		add_action( 'rest_api_init', [ $this, 'initialize_api' ] );

		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'register_allowed_hosts' ] );
		}
	}

	/**
	 * Register allowed redirect hosts filter.
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	public function register_allowed_hosts() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_filter( 'allowed_redirect_hosts', [ $this, 'get_redirect_hosts' ] );
	}

	/**
	 * Get the token instance.
	 *
	 * @since 3.7.0
	 *
	 * @return Auth
	 */
	public function get_auth() {

		if ( is_null( $this->auth ) ) {
			$this->auth = new Auth();
		}

		return $this->auth;
	}

	/**
	 * Maybe redirect to Setup Wizard.
	 *
	 * @since 3.7.0
	 */
	public function maybe_redirect() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! sugar_calendar()->get_admin()->is_page( 'settings_general' ) ) {
			return;
		}

		if ( empty( $_GET[ self::REDIRECT_PARAMETER ] ) ) {
			return;
		}

		if ( WP::is_local_environment() ) {
			return;
		}

		$this->render_bridge();
		exit();
	}

	/**
	 * Render the wizard launch bridge.
	 *
	 * Builds the wizard bootstrap data, sends the standalone document headers
	 * and hands off to the welcome screen, which posts that data to the wizard
	 * SPA once the user clicks through.
	 *
	 * @since 3.11.1
	 * @since 3.13.0 Renders the welcome screen instead of auto-submitting.
	 *
	 * @return void
	 */
	public function render_bridge() {

		$fields = [
			'token'       => $this->get_auth()->get_token(),
			'rest_url'    => $this->api->get_url(),
			'exit_url'    => $this->get_exit_url(),
			'restart_url' => $this->get_restart_url(),
		];

		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Frame-Options: DENY' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		( new SetupWizardLandingPage( $fields ) )->render();
	}

	/**
	 * Initialize the rest API.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function initialize_api() {

		$this->api->register_routes();
	}

	/**
	 * Get the setup wizard URL.
	 *
	 * @since 3.7.0
	 *
	 * @param string $path Optional URL path.
	 *
	 * @return string
	 */
	public function get_url( $path = '' ) {

		$base_url = defined( 'SC_SETUP_WIZARD_URL' ) ? SC_SETUP_WIZARD_URL : self::URL;

		return "$base_url$path";
	}

	/**
	 * Filter safe redirect hosts.
	 *
	 * @since 3.7.0
	 *
	 * @param array $hosts List of hosts.
	 *
	 * @return array
	 */
	public function get_redirect_hosts( $hosts ) {

		// Parse the host from the wizard base URL directly so this filter
		// callback does not issue a token as a side effect on every
		// wp_safe_redirect call.
		$base_url = defined( 'SC_SETUP_WIZARD_URL' ) ? SC_SETUP_WIZARD_URL : self::URL;
		$host     = wp_parse_url( $base_url, PHP_URL_HOST );

		if ( ! empty( $host ) ) {
			$hosts[] = $host;
		}

		return $hosts;
	}

	/**
	 * Whether it is the first time Setup Wizard is run.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public function is_first_run() {

		return get_transient( self::TRANSIENT_FIRST_RUN );
	}

	/**
	 * The URL the user should land on when closing the Setup Wizard.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public function get_exit_url() {

		$events_count = sugar_calendar_count_events();

		if ( $events_count > 0 ) {
			return Settings::get_url();
		}

		return Welcome::get_url();
	}

	/**
	 * Get the URL the user should land on to start the Setup Wizard.
	 *
	 * @since 3.11.1
	 *
	 * @return string
	 */
	public function get_start_url() {

		// The redirect gate in maybe_redirect() only fires on the General tab,
		// so pin the section — the settings page's default landing tab is
		// License & Usage, which would bail the gate.
		return add_query_arg(
			[
				'section'                => SettingsGeneralTab::get_tab_slug(),
				self::REDIRECT_PARAMETER => 1,
			],
			Settings::get_url()
		);
	}

	/**
	 * The URL the user should land on when restarting the Setup Wizard.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public function get_restart_url() {

		// See get_start_url(): pin the General tab so the redirect gate fires.
		return add_query_arg(
			[
				'section'                => SettingsGeneralTab::get_tab_slug(),
				self::REDIRECT_PARAMETER => 1,
			],
			Settings::get_url()
		);
	}
}
