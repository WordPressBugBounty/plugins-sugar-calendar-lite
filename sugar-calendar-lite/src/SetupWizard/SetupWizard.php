<?php

namespace Sugar_Calendar\SetupWizard;

use Sugar_Calendar\Admin\Pages\Settings;
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
	 * Outputs a tiny HTML page with an auto-submitting POST form whose
	 * body carries the wizard bootstrap data to the wizard SPA.
	 *
	 * @since 3.11.1
	 *
	 * @return void
	 */
	public function render_bridge() {

		$action_url = defined( 'SC_SETUP_WIZARD_URL' ) ? SC_SETUP_WIZARD_URL : self::URL;

		$fields = [
			'token'       => $this->get_auth()->get_token(),
			'rest_url'    => $this->api->get_url(),
			'exit_url'    => $this->get_exit_url(),
			'restart_url' => $this->get_restart_url(),
		];

		$inputs = '';

		foreach ( $fields as $name => $value ) {
			$inputs .= sprintf(
				'<input type="hidden" name="%s" value="%s" />',
				esc_attr( $name ),
				esc_attr( $value )
			);
		}

		$action  = esc_url( $action_url );
		$charset = esc_attr( get_bloginfo( 'charset' ) );
		$title   = esc_html__( 'Sugar Calendar', 'sugar-calendar-lite' );
		$loading = esc_html__( 'Launching the Sugar Calendar setup wizard…', 'sugar-calendar-lite' );
		$cta     = esc_html__( 'Continue to Setup Wizard', 'sugar-calendar-lite' );

		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Frame-Options: DENY' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo <<<HTML
<!DOCTYPE html>
<html>
<head>
	<meta charset="{$charset}" />
	<meta name="robots" content="noindex,nofollow" />
	<title>{$title}</title>
	<style>
		html, body { margin: 0; }
		body {
			min-height: calc(100vh - 4px);
			background: #F0F0F1;
			border-top: 4px solid #FF8845;
			display: flex;
			align-items: center;
			justify-content: center;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			color: #2C3338;
		}
		.sc-bridge { text-align: center; }
		.sc-bridge svg { display: block; margin: 0 auto; width: 250px; height: 250px; }
		.sc-bridge svg #indicator { transform-origin: 125px 120px; animation: sc-spin 1s linear infinite; }
		.sc-bridge p { margin: 0; color: #50575E; font-size: 14px; }
		@keyframes sc-spin { to { transform: rotate( 360deg ); } }
	</style>
</head>
<body>
	<div class="sc-bridge">
		<svg width="250" height="250" viewBox="0 0 250 250" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><g filter="url(#sc-bridge-shadow)"><path d="M125 195C166.421 195 200 161.421 200 120C200 78.5786 166.421 45 125 45C83.5787 45 50.0001 78.5786 50.0001 120C50.0001 161.421 83.5787 195 125 195Z" fill="white"/><path fill-rule="evenodd" clip-rule="evenodd" d="M108.441 92.9827C108.441 92.2197 108.788 91.4566 109.342 90.9017C109.965 90.3468 110.728 90 111.559 90C112.39 90 113.222 90.3468 113.776 90.9017C114.33 91.4566 114.677 92.1503 114.677 92.9827H135.323C135.323 92.2197 135.67 91.4566 136.224 90.9017C136.778 90.3468 137.61 90 138.441 90C139.273 90 140.104 90.3468 140.658 90.9017C141.213 91.4566 141.559 92.1503 141.559 92.9827H142.598C149.457 92.9827 155 98.5318 155 105.399V137.584C155 144.451 149.457 150 142.598 150H107.402C100.543 150 95.0001 144.451 95.0001 137.584V105.399C95.0001 98.5318 100.543 92.9827 107.402 92.9827H108.441ZM147.379 134.671C147.379 136.821 146.547 138.832 145.023 140.358C143.499 141.884 141.49 142.717 139.342 142.717H110.658C106.224 142.717 102.621 139.11 102.621 134.671V133.214C102.621 133.214 102.829 132.728 103.037 132.728H132.136C133.938 132.728 135.323 131.341 135.323 129.538C135.323 127.803 133.868 126.347 132.136 126.347H106.224C105.254 126.347 104.353 126 103.661 125.306C102.968 124.613 102.621 123.711 102.621 122.809V105.954C102.621 104.428 103.245 102.971 104.284 101.931C105.323 100.89 106.778 100.266 108.303 100.266C108.303 100.266 108.303 100.266 108.372 100.266C108.372 100.266 108.372 100.266 108.372 100.335C108.372 101.861 109.619 103.11 111.143 103.11H111.905C113.43 103.11 114.677 101.861 114.677 100.335C114.677 100.335 114.677 100.197 114.815 100.197H135.185C135.185 100.197 135.323 100.197 135.323 100.335C135.323 101.861 136.571 103.11 138.095 103.11H138.857C140.381 103.11 141.628 101.861 141.628 100.335V100.266C141.628 100.266 141.628 100.266 141.698 100.266C144.885 100.266 147.379 102.832 147.379 105.954V109.769C147.379 109.769 147.171 110.254 146.963 110.254H117.933C116.201 110.254 114.746 111.642 114.746 113.445C114.746 115.179 116.201 116.636 117.933 116.636H143.845C144.815 116.636 145.716 116.983 146.409 117.676C147.102 118.37 147.448 119.272 147.448 120.173V134.671H147.379Z" fill="#FF8845"/><path d="M192.5 120C192.5 157.279 162.279 187.5 125 187.5C87.7207 187.5 57.5 157.279 57.5 120C57.5 82.7208 87.7207 52.5 125 52.5C162.279 52.5 192.5 82.7208 192.5 120Z" stroke="#DCDCDE"/><path d="M125 181C158.689 181 186 153.689 186 120C186 86.3106 158.689 59 125 59C91.3106 59 64 86.3106 64 120C64 153.689 91.3106 181 125 181Z" stroke="#DCDCDE" stroke-width="14"/><path id="indicator" fill-rule="evenodd" clip-rule="evenodd" d="M141.361 60.8196C142.809 57.2351 146.889 55.5033 150.473 56.9515C166.599 63.4666 180.258 76.1471 187.594 93.4303C189.105 96.989 187.445 101.098 183.886 102.609C180.327 104.12 176.218 102.459 174.707 98.9005C168.88 85.1725 158.052 75.1132 145.229 69.9321C141.644 68.4839 139.913 64.4041 141.361 60.8196Z" fill="#FF8845"/></g><defs><filter id="sc-bridge-shadow" x="0" y="0" width="250" height="250" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB"><feFlood flood-opacity="0" result="BackgroundImageFix"/><feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/><feOffset dy="5"/><feGaussianBlur stdDeviation="25"/><feComposite in2="hardAlpha" operator="out"/><feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.25 0"/><feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow"/><feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow" result="shape"/></filter></defs></svg>
		<p>{$loading}</p>
	</div>
	<form id="sc-setup-wizard-bridge" method="POST" action="{$action}">{$inputs}<noscript><button type="submit">{$cta}</button></noscript></form>
	<script>document.getElementById('sc-setup-wizard-bridge').submit();</script>
</body>
</html>
HTML;
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
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

		return add_query_arg(
			self::REDIRECT_PARAMETER,
			1,
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

		return add_query_arg(
			self::REDIRECT_PARAMETER,
			1,
			Settings::get_url()
		);
	}
}
