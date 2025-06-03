<?php

namespace Sugar_Calendar\SetupWizard;

use Sugar_Calendar\Admin\Pages\Addons;
use Sugar_Calendar\Admin\Pages\EventNew;
use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;
use Sugar_Calendar\Connect;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\Installer;
use Sugar_Calendar\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_currencies;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_currency;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_stripe_connect_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_stripe_credentials_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\is_sandbox;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\stripe_is_connected;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\update_stripe_credentials;

/**
 * Class Rest API.
 *
 * @since 3.7.0
 */
class RestApi {

	/**
	 * URL prefix.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const URL_PREFIX = 'sugar-calendar/setup-wizard/v1';

	/**
	 * Get the API URL.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public function get_url() {

		return get_rest_url( null, self::URL_PREFIX );
	}

	/**
	 * Register routes.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function register_routes() {

		$routes = [
			'/hydrate'            => [
				WP_REST_Server::READABLE,
				[ $this, 'hydrate' ],
			],
			'/tec-import'         => [
				WP_REST_Server::CREATABLE,
				[ $this, 'tec_import' ],
			],
			'/update'             => [
				WP_REST_Server::CREATABLE,
				[ $this, 'update' ],
			],
			'/stripe-connect'     => [
				WP_REST_Server::CREATABLE,
				[ $this, 'stripe_connect' ],
			],
			'/verify-license'     => [
				WP_REST_Server::CREATABLE,
				[ $this, 'verify_license' ],
			],
			'/deactivate-license' => [
				WP_REST_Server::CREATABLE,
				[ $this, 'deactivate_license' ],
			],
			'/lite-upgrade'       => [
				WP_REST_Server::CREATABLE,
				[ $this, 'lite_upgrade' ],
			],
			'/install-plugins'    => [
				WP_REST_Server::CREATABLE,
				[ $this, 'install_plugins' ],
			],
		];

		foreach ( $routes as $route => $route_params ) {
			[ $method, $callback ] = $route_params;

			register_rest_route(
				self::URL_PREFIX,
				$route,
				[
					'methods'             => $method,
					'callback'            => $callback,
					'permission_callback' => [ $this, 'validate_request' ],
				]
			);
		}
	}

	/**
	 * Validate request.
	 *
	 * @since 3.7.0
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_request( $request ) {

		$token = $request->get_header( 'X-TOKEN' );
		$auth  = sugar_calendar()->get_setup_wizard()->get_auth();

		if ( ! $auth->verify_token( $token ) ) {
			return $this->error( __( 'Session expired.', 'sugar-calendar-lite' ), 401 );
		}

		$auth->refresh_token();

		return true;
	}

	/**
	 * Hydrate setup wizard data.
	 *
	 * @since 3.7.0
	 *
	 * @return WP_REST_Response
	 */
	public function hydrate() {

		// Remove first run transient.
		delete_transient( SetupWizard::TRANSIENT_FIRST_RUN );

		return $this->response(
			[
				'settings' => $this->get_settings(),
				'options'  => $this->get_options(),
			]
		);
	}

	/**
	 * Import The Events Calendar entities.
	 *
	 * @since 3.7.0
	 *
	 * @return WP_REST_Response;
	 */
	public function tec_import() {

		$result = ( new TheEventCalendar() )->run();
		$result = ! empty( $result ) ? $result : [];

		return $this->response( $result );
	}

	/**
	 * Update plugin options.
	 *
	 * @since 3.7.0
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return WP_REST_Response;
	 */
	public function update( $request ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$data = $request->get_json_params();
		$data = ! empty( $data ) ? $data : [];

		foreach ( $data as $option_name => $option_data ) {
			$callback = null;

			switch ( $option_name ) {
				case 'core':
					$callback = [ $this, 'update_core_option' ];
					break;

				case 'event_ticketing':
					$callback = [ $this, 'update_event_ticketing_option' ];
					break;
			}

			if ( empty( $callback ) ) {
				continue;
			}

			foreach ( $option_data as $option_key => $option_value ) {
				call_user_func( $callback, $option_key, $option_value );
			}
		}

		return $this->response(
			[
				'settings' => $this->get_settings(),
				'options'  => $this->get_options(),
			]
		);
	}

	/**
	 * Perform Stripe authentication.
	 *
	 * @since 3.7.0
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return WP_REST_Response|WP_Error;
	 */
	public function stripe_connect( $request ) {

		$data = $request->get_json_params();

		if ( empty( $data['state'] ) ) {
			return $this->error( __( 'Missing required parameters.', 'sugar-calendar-lite' ), 401 );
		}

		$state                  = sanitize_text_field( $data['state'] );
		$stripe_credentials_url = $this->get_stripe_credentials_url( $state );
		$response               = wp_remote_get( esc_url_raw( $stripe_credentials_url ) );

		if (
			is_wp_error( $response ) ||
			200 !== wp_remote_retrieve_response_code( $response ) ||
			! wp_remote_retrieve_body( $response )
		) {
			return $this->error(
				__(
					'There was an error getting your Stripe credentials. Please try again. If you continue to have this problem, please contact support.',
					'sugar-calendar-lite'
				),
				401
			);
		}

		$response   = json_decode( $response['body'], true );
		$data       = $response['data'];
		$is_sandbox = is_sandbox();

		update_stripe_credentials(
			$data['publishable_key'],
			$data['secret_key'],
			$data['stripe_user_id'],
			$is_sandbox
		);

		return $this->response(
			[
				'connected' => stripe_is_connected(),
			]
		);
	}

	/**
	 * Verify license.
	 *
	 * @since 3.7.0
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify_license( $request ) {

		$data = $request->get_json_params();

		if ( empty( $data['license_key'] ) ) {
			return $this->error( __( 'Missing license key.', 'sugar-calendar-lite' ) );
		}

		$license = sugar_calendar()->get_pro()->get_license();

		if ( ! $license->verify_key( $data['license_key'] ) ) {
			return $this->error( $license->errors[0] ?? __( 'License key could not be verified.', 'sugar-calendar-lite' ) );
		}

		return $this->response(
			[
				'type' => sugar_calendar()->get_license_type(),
			]
		);
	}

	/**
	 * Deactivate license.
	 *
	 * @since 3.7.0
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivate_license( $request ) {

		sugar_calendar()->get_pro()->get_license()->deactivate_key( true );

		return $this->response();
	}

	/**
	 * Get the plugin upgrade URL.
	 *
	 * @since 3.7.0
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function lite_upgrade( $request ) {

		$data = $request->get_json_params();

		if ( empty( $data['license_key'] ) ) {
			return $this->error( __( 'Missing license key.', 'sugar-calendar-lite' ) );
		}

		if ( sugar_calendar()->is_pro() ) {
			return $this->error( __( 'Only the Lite version can be upgraded.', 'sugar-calendar-lite' ) );
		}

		// Connect class expects function defined in this file.
		require_once ABSPATH . '/wp-admin/includes/plugin.php';

		$url = Connect::generate_url( $data['license_key'], '', $this->get_upgrade_redirect_url() );

		return $this->response(
			[
				'url' => $url,
			]
		);
	}

	/**
	 * Install plugins.
	 *
	 * @since 3.7.0
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function install_plugins( $request ) {

		$data = $request->get_json_params();

		if ( empty( $data['plugins'] ) ) {
			return $this->error( __( 'Missing plugin list.', 'sugar-calendar-lite' ) );
		}

		$plugins = (array) $data['plugins'];

		if ( empty( $plugins ) ) {
			return $this->error( __( 'Missing plugin list.', 'sugar-calendar-lite' ) );
		}

		$addons = sugar_calendar()->get_addons();

		$plugins = array_map(
			function ( $plugin_slug ) use ( $addons ) {

				$addon = $addons->get_addon( $plugin_slug );

				if ( ! empty( $addon['url'] ) ) {
					return $addon['url'];
				}

				return $addons->get_suggested_plugin_url( $plugin_slug );
			},
			$plugins
		);

		if ( empty( $plugins ) ) {
			return $this->error( __( 'No plugins found.', 'sugar-calendar-lite' ) );
		}

		$plugins = array_filter( $plugins );

		// Prepare variables.
		$credentials_url = esc_url_raw(
			add_query_arg(
				[
					'page' => Addons::get_slug(),
				],
				admin_url( 'admin.php' )
			)
		);

		foreach ( $plugins as $plugin_url ) {
			Installer::install_plugin( $credentials_url, $plugin_url, true, false );
		}

		return $this->response(
			[
				'plugins' => [
					'smtp'   => $this->detect_smtp_plugins(),
					'addons' => $this->get_installed_addons(),
				],
			]
		);
	}

	/**
	 * Output response.
	 *
	 * @since 3.7.0
	 *
	 * @param mixed $data    Response data.
	 * @param bool  $success Response status.
	 *
	 * @return WP_REST_Response;
	 */
	private function response( $data = [], $success = true ) {

		$data = [
			'success' => $success,
			'data'    => $data,
		];

		return rest_ensure_response( $data );
	}

	/**
	 * Output error.
	 *
	 * @since 3.7.0
	 *
	 * @param string $message     Error message.
	 * @param array  $status_code Error status code.
	 *
	 * @return WP_REST_Response|WP_Error;
	 */
	private function error( $message, $status_code = 200 ) {

		if ( $status_code !== 200 ) {
			return new WP_Error(
				'auth',
				$message,
				[
					'status' => $status_code,
				]
			);
		}

		return $this->response(
			[
				'message' => $message,
			],
			false
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	private function get_settings() {

		// Core plugin settings.
		$timezone = sugar_calendar_get_timezone();

		// Date format.
		$date_formats = sugar_calendar_date_formats();
		$date_format  = sc_get_date_format();

		if ( ! in_array( $date_format, $date_formats, true ) ) {
			$date_formats[] = $date_format;
		}

		$date_formats = $this->get_formatted_datetime( $timezone, $date_formats );

		// Time format.
		$time_formats = sugar_calendar_time_formats();
		$time_format  = sc_get_time_format();

		if ( ! in_array( $time_format, $time_formats, true ) ) {
			$time_formats[] = $time_format;
		}

		$time_formats = $this->get_formatted_datetime( $timezone, $time_formats );

		$week_days            = $this->get_weekdays();
		$admin_email          = get_option( 'admin_email' );
		$smtp_plugins         = $this->detect_smtp_plugins();
		$addons               = $this->get_installed_addons();
		$user_agent           = Helpers\Helpers::get_default_user_agent();
		$is_pro               = sugar_calendar()->is_pro();
		$wpforms_version_type = $this->get_wpforms_version_type();
		$plugin_version       = SC_PLUGIN_VERSION;
		$complete_url         = admin_url( EventNew::get_slug() );

		// Ticketing plugin settings.
		$currencies = get_currencies();

		// Stripe settings.
		$stripe_is_connected = stripe_is_connected();
		$stripe_connect_url  = $this->get_stripe_connect_url();

		// License settings.
		$license_type = sugar_calendar()->get_license_type();
		$license_key  = sugar_calendar()->get_license_key();

		// The Events Calendar settings.
		$tec_entries = 0;

		if ( TheEventCalendar::is_migration_possible() ) {
			$tec_entries = ( new TheEventCalendar() )->get_number_of_tec_events_to_import();
		}

		$settings = [
			'core'            => [
				'date_formats'         => $date_formats,
				'time_formats'         => $time_formats,
				'week_days'            => $week_days,
				'admin_email'          => $admin_email,
				'complete_url'         => $complete_url,
				'user_agent'           => $user_agent,
				'is_pro'               => $is_pro,
				'plugin_version'       => $plugin_version,
				'wpforms_version_type' => $wpforms_version_type,
			],
			'license'         => [
				'type' => $license_type,
				'key'  => $license_key,
			],
			'event_ticketing' => [
				'currencies' => $currencies,
			],
			'stripe'          => [
				'connect_url' => $stripe_connect_url,
				'connected'   => $stripe_is_connected,
			],
			'tec'             => [
				'entries' => $tec_entries,
			],
			'plugins'         => [
				'smtp'   => $smtp_plugins,
				'addons' => $addons,
			],
		];

		return $settings;
	}

	/**
	 * Get plugin options.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	private function get_options() {

		$currency = get_currency();

		$options = [
			'core'            => Options::all(),
			'event_ticketing' => [
				'currency' => $currency,
				'sandbox'  => is_sandbox(),
			],
		];

		return $options;
	}

	/**
	 * Update a core option.
	 *
	 * @since 3.7.0
	 *
	 * @param string $option_key   Option key.
	 * @param mixed  $option_value Option value.
	 *
	 * @return void
	 */
	private function update_core_option( $option_key, $option_value ) {

		Options::update( $option_key, $option_value );
	}

	/**
	 * Update an ticketing addon option.
	 *
	 * @since 3.7.0
	 *
	 * @param string $option_key   Option key.
	 * @param mixed  $option_value Option value.
	 *
	 * @return void
	 */
	private function update_event_ticketing_option( $option_key, $option_value ) {

		$options = get_option( 'sc_et_settings', [] );

		$options[ $option_key ] = $option_value;

		update_option( 'sc_et_settings', $options );
	}

	/**
	 * Get Stripe connect URL.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	private function get_stripe_connect_url() {

		$is_sandbox         = is_sandbox();
		$stripe_connect_url = get_stripe_connect_url( $is_sandbox, $this->get_stripe_callback_url() );

		return $stripe_connect_url;
	}

	/**
	 * Get Stripe credentials URL.
	 *
	 * @since 3.7.0
	 *
	 * @param string $state OAuth state parameter.
	 *
	 * @return string
	 */
	private function get_stripe_credentials_url( $state ) {

		$is_sandbox             = is_sandbox();
		$stripe_credentials_url = get_stripe_credentials_url( $is_sandbox, $state, $this->get_stripe_callback_url() );

		return $stripe_credentials_url;
	}

	/**
	 * Get Stripe callback URL.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	private function get_stripe_callback_url() {

		return sugar_calendar()->get_setup_wizard()->get_url( '/steps/stripe-setup' );
	}

	/**
	 * Detect if any other SMTP plugin options are defined.
	 * Other SMTP plugins:
	 * - WP Mail SMTP.
	 * - Easy WP SMTP.
	 * - Easy SMTP.
	 * - Post SMTP Mailer.
	 * - SMTP Mailer.
	 * - WP SMTP.
	 * - FluentSMTP.
	 * - SureMail.
	 * - GOSMTP.
	 * - Solid Mail.
	 * - Site Mailer.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	private function detect_smtp_plugins() {

		$data = [];

		$plugins = [
			'wp-mail-smtp' => 'wp_mail_smtp',
			//			'easy-wp-smtp'     => 'easy_wp_smtp',
			//			'easy-smtp'        => 'swpsmtp_options',
			//			'post-smtp-mailer' => 'postman_options',
			//			'smtp-mailer'      => 'smtp_mailer_options',
			//			'wp-smtp'          => 'wp_smtp_options',
			//			'fluent-smtp'      => 'fluentmail-settings',
			//			'sure-mail'        => 'suremails_connections',
			//			'go-smtp'          => 'gosmtp_options',
			//			'site-mailer'      => 'site_mailer_sender_domain',
		];

		foreach ( $plugins as $plugin_slug => $plugin_options ) {
			$options = get_option( $plugin_options );

			if ( ! empty( $options ) ) {
				$data[] = $plugin_slug;
			}
		}

		return $data;
	}

	/**
	 * Get installed addons.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	private function get_installed_addons() {

		$addons             = sugar_calendar()->get_addons();
		$available_addons   = $addons->get_all();
		$installed_addons   = [];
		$installed_statuses = [ 'installed', 'active' ];

		foreach ( $available_addons as $addon ) {
			$addon = $addons->get_addon( $addon['slug'] );

			if ( in_array( $addon['status'], $installed_statuses, true ) ) {
				$installed_addons[] = $addon['slug'];
			}
		}

		return $installed_addons;
	}

	/**
	 * Get the redirect URL for the plugin upgrade flow.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	private function get_upgrade_redirect_url() {

		return sugar_calendar()->get_setup_wizard()->get_url( '/complete' );
	}

	/**
	 * Get formatted date/time settings.
	 *
	 * @since 3.7.0
	 *
	 * @param string $timezone Current timezone.
	 * @param array  $formats  Formats array.
	 *
	 * @return array
	 */
	private function get_formatted_datetime( $timezone, $formats ) {

		return array_map(
			function ( $format ) use ( $timezone ) {

				return [
					'value' => $format,
					'label' => sugar_calendar_format_date_i18n( $format, null, $timezone ),
				];
			},
			$formats
		);
	}

	/**
	 * Get the WPForms version type if it's installed.
	 *
	 * @since 3.7.0
	 *
	 * @return false|string Return `false` if WPForms is not installed, otherwise return either `lite` or `pro`.
	 */
	private function get_wpforms_version_type() {

		if ( ! function_exists( 'wpforms' ) ) {
			return false;
		}

		if ( method_exists( wpforms(), 'is_pro' ) ) {
			$is_wpforms_pro = wpforms()->is_pro();
		} else {
			$is_wpforms_pro = wpforms()->pro;
		}

		return $is_wpforms_pro ? 'pro' : 'lite';
	}

	/**
	 * Get days of the week in abbreviated textual representation.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	private function get_weekdays() {

		global $wp_locale;

		$day_indices = [ 1, 2, 3, 4, 5, 6, 0 ];

		$week_days = array_map(
			function ( $day ) use ( $wp_locale ) {

				$week_day        = $wp_locale->get_weekday( $day );
				$week_day_abbrev = $wp_locale->get_weekday_abbrev( $week_day );

				return [
					'value' => $day,
					'label' => $week_day_abbrev,
				];
			},
			$day_indices
		);

		return $week_days;
	}
}
