<?php

namespace Sugar_Calendar\Admin\Pages;

use PHPMailer\PHPMailer\PHPMailer;
use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Admin\Tools\Smtp as SmtpDetector;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\PartnerPlugins\Catalog;
use Sugar_Calendar\PartnerPlugins\Installer;

/**
 * SMTP Sub-page.
 *
 * @since 3.11.0
 */
class SMTP extends PageAbstract {

	/**
	 * Admin menu page slug.
	 *
	 * @since 3.11.0
	 *
	 * @var string
	 */
	const SLUG = 'sugar-calendar-smtp';

	/**
	 * WP Mail SMTP's `PartnerPlugins\Catalog` key.
	 *
	 * @since 3.15.0
	 */
	const WP_MAIL_SMTP_KEY = 'wp-mail-smtp';

	/**
	 * Configuration. WP Mail SMTP's basenames, package and admin page come from `PartnerPlugins\Catalog`.
	 *
	 * @since 3.11.0
	 *
	 * @var array
	 */
	private $config = [
		'smtp_wizard_url' => 'admin.php?page=wp-mail-smtp-setup-wizard',
	];

	/**
	 * Runtime data used for generating page HTML.
	 *
	 * @since 3.11.0
	 *
	 * @var array
	 */
	private $output_data = [];

	/**
	 * Constructor.
	 *
	 * @since 3.11.0
	 */
	public function __construct() {

		$this->hooks();
	}

	/**
	 * Register the early init hooks.
	 *
	 * @since 3.11.0
	 */
	public function early_init() {

		if ( wp_doing_ajax() ) {
			add_action( 'sugar_calendar_ajax_sce_install_smtp', [ $this, 'ajax_install_smtp' ] );
			add_action( 'sugar_calendar_ajax_sce_activate_smtp', [ $this, 'ajax_activate_smtp' ] );
			add_action( 'sugar_calendar_ajax_sce_check_plugin_status', [ $this, 'ajax_check_plugin_status' ] );
		}

		add_action( 'admin_init', [ $this, 'redirect_to_smtp_settings' ] );
	}

	/**
	 * Get the label of the SMTP page.
	 *
	 * @since 3.11.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return __( 'SMTP', 'sugar-calendar-lite' );
	}

	/**
	 * Hooks.
	 *
	 * @since 3.11.0
	 */
	public function hooks() {

		// Check what page we are on.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		// Only load if we are actually on the SMTP page.
		if ( $page !== self::SLUG ) {
			return;
		}

		add_filter( 'admin_title', [ $this, 'get_admin_title' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'sugar_calendar_admin_area_display_admin_header', [ $this, 'hide_admin_header' ], 10, 2 );
	}

	/**
	 * Ajax endpoint to activate SMTP.
	 *
	 * @since 3.11.0
	 */
	public function ajax_install_smtp() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission.', 'sugar-calendar-lite' ) );
		}

		/**
		 * Fires when the promo page's step-1 install/activate button is clicked.
		 *
		 * @since 3.12.0
		 *
		 * @param string $type Button variant: 'install' or 'activate'.
		 */
		do_action( 'sugar_calendar_admin_pages_smtp_install_clicked', 'install' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$result = $this->run_smtp();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			[
				'is_activated' => $result['plugin_state'] === 'active',
				'msg'          => $result['message'],
			]
		);
	}

	/**
	 * Activate WP Mail SMTP plugin.
	 *
	 * @since 3.11.0
	 */
	public function ajax_activate_smtp() {

		// Check for permissions.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( esc_html__( 'Plugin activation is disabled for you on this site.', 'sugar-calendar-lite' ) );
		}

		/** This action is documented in src/Admin/Pages/SMTP.php (ajax_install_smtp). */
		do_action( 'sugar_calendar_admin_pages_smtp_install_clicked', 'activate' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$result = $this->run_smtp();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result['message'] );
	}

	/**
	 * Install or activate WP Mail SMTP, whichever its live state allows.
	 *
	 * The package and basename come from `PartnerPlugins\Catalog`, never from the request.
	 *
	 * @since 3.15.0
	 *
	 * @return array|WP_Error `Helpers\ActionResult` array, or the operation error.
	 */
	private function run_smtp() {

		// Set the current screen to avoid undefined notices.
		set_current_screen( 'events_page_sugar-calendar-smtp' );

		$credentials_url = esc_url_raw(
			add_query_arg(
				[
					'page' => self::get_slug(),
				],
				admin_url( 'admin.php' )
			)
		);

		return ( new Installer() )->run( ( new Catalog() )->get_target( self::WP_MAIL_SMTP_KEY ), $credentials_url );
	}

	/**
	 * WP Mail SMTP's Catalog record: basenames, package, wp.org listing and admin page.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function get_partner() {

		return ( new Catalog() )->get( self::WP_MAIL_SMTP_KEY );
	}

	/**
	 * WP Mail SMTP's settings screen, relative to wp-admin as the page script expects.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	private function get_settings_path() {

		return 'admin.php?page=' . $this->get_partner()['admin_page'];
	}

	/**
	 * Check the status of the WP Mail SMTP plugin.
	 *
	 * @since 3.11.0
	 */
	public function ajax_check_plugin_status() {

		// Security checks.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'error' => esc_html__( 'You do not have permission.', 'sugar-calendar-lite' ),
				]
			);
		}

		$result = [];

		if ( ! $this->is_smtp_activated() ) {
			wp_send_json_error(
				[
					'error' => esc_html__( 'Plugin unavailable.', 'sugar-calendar-lite' ),
				]
			);
		}

		$result['setup_status']  = (int) $this->is_smtp_configured();
		$result['license_level'] = wp_mail_smtp()->get_license_type();

		// Prevent redirect to the WP Mail SMTP Setup Wizard on the fresh installs.
		// We need this workaround since WP Mail SMTP doesn't check whether the mailer is already configured when redirecting to the Setup Wizard on the first run.
		if ( $result['setup_status'] > 0 ) {
			update_option( 'wp_mail_smtp_activation_prevent_redirect', true );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Enqueue JS and CSS files.
	 *
	 * @since 3.11.0
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-vendor-lity');
		wp_enqueue_script( 'sugar-calendar-vendor-lity' );

		wp_enqueue_style(
			'sugar-calendar-admin-smtp',
			SC_PLUGIN_ASSETS_URL . 'css/admin-smtp' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_script(
			'sugar-calendar-admin-smtp',
			SC_PLUGIN_ASSETS_URL . 'admin/js/sc-admin-smtp' . WP::asset_min() . '.js',
			[ 'jquery' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-smtp',
			'sugar_calendar_admin_smtp',
			$this->get_js_strings()
		);
	}

	/**
	 * Set wp_mail_smtp_source option to 'sugar-calendar-events' on WP Mail SMTP plugin activation.
	 *
	 * @since 3.11.0
	 *
	 * @param string $plugin_basename Plugin basename.
	 */
	public function smtp_activated( $plugin_basename ) {

		if ( $plugin_basename !== $this->get_partner()['basename'] ) {
			return;
		}

		// If user came from some certain page to install WP Mail SMTP, we can get the source and write it instead of default one.
		$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : 'sugar-calendar-events'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		update_option( 'wp_mail_smtp_source', $source );
	}

	/**
	 * JS Strings.
	 *
	 * @since 3.11.0
	 *
	 * @return array Array of strings.
	 */
	protected function get_js_strings() {

		$download_url = $this->get_partner()['download_url'];

		$error_could_not_install = sprintf(
			wp_kses( /* translators: %s - Lite plugin download URL. */
				__( 'Could not install the plugin automatically. Please <a href="%s">download</a> it and install it manually.', 'sugar-calendar-lite' ),
				[
					'a' => [
						'href' => true,
					],
				]
			),
			esc_url( $download_url )
		);

		$error_could_not_activate = sprintf(
			wp_kses( /* translators: %s - Lite plugin download URL. */
				__( 'Could not activate the plugin. Please activate it on the <a href="%s">Plugins page</a>.', 'sugar-calendar-lite' ),
				[
					'a' => [
						'href' => true,
					],
				]
			),
			esc_url( admin_url( 'plugins.php' ) )
		);

		return [
			'installing'               => esc_html__( 'Installing...', 'sugar-calendar-lite' ),
			'activating'               => esc_html__( 'Activating...', 'sugar-calendar-lite' ),
			'activated'                => esc_html__( 'WP Mail SMTP Installed & Activated', 'sugar-calendar-lite' ),
			'install_now'              => esc_html__( 'Install Now', 'sugar-calendar-lite' ),
			'activate_now'             => esc_html__( 'Activate Now', 'sugar-calendar-lite' ),
			'download_now'             => esc_html__( 'Download Now', 'sugar-calendar-lite' ),
			'plugins_page'             => esc_html__( 'Go to Plugins page', 'sugar-calendar-lite' ),
			'error_could_not_install'  => $error_could_not_install,
			'error_could_not_activate' => $error_could_not_activate,
			'manual_install_url'       => $download_url,
			'manual_activate_url'      => admin_url( 'plugins.php' ),
			'smtp_settings'            => esc_html__( 'Go to SMTP settings', 'sugar-calendar-lite' ),
			'smtp_wizard'              => esc_html__( 'Open Setup Wizard', 'sugar-calendar-lite' ),
			'smtp_settings_url'        => esc_url( $this->get_settings_path() ),
			'smtp_wizard_url'          => esc_url( $this->config['smtp_wizard_url'] ),
			'ajax_url'                 => admin_url( 'admin-ajax.php' ),
			'nonce'                    => wp_create_nonce( 'sugar-calendar' ),
		];
	}

	/**
	 * Generate and output page HTML.
	 *
	 * @since 3.11.0
	 */
	public function display() {

		echo '<div id="sugar-calendar-smtp" class="wrap sugar-calendar-admin-wrap">';

		$this->output_section_heading();
		$this->output_section_screenshot();
		$this->output_section_step_install();
		$this->output_section_step_setup();

		echo '</div>';
	}

	/**
	 * Generate and output heading section HTML.
	 *
	 * @since 3.11.0
	 */
	protected function output_section_heading() {

		// Heading section.
		printf(
			'<section class="top">
				<img class="img-top" src="%1$s" srcset="%2$s 2x" alt="%3$s"/>
				<h1>%4$s</h1>
				<p>%5$s</p>
			</section>',
			esc_url( SC_PLUGIN_ASSETS_URL . '/images/smtp/sugar-calendar-wpmailsmtp.png' ),
			esc_url( SC_PLUGIN_ASSETS_URL . '/images/smtp/sugar-calendar-wpmailsmtp@2x.png' ),
			esc_attr__( 'Sugar Calendar ♥ WP Mail SMTP', 'sugar-calendar-lite' ),
			esc_html__( 'Making Email Deliverability Easy for WordPress', 'sugar-calendar-lite' ),
			esc_html__( 'WP Mail SMTP fixes deliverability problems with your WordPress emails and event notifications. It\'s built by the same folks behind Sugar Calendar and is used by more than 4 million websites.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Generate and output screenshot section HTML.
	 *
	 * @since 3.11.0
	 */
	protected function output_section_screenshot() {

		// Screenshot section.
		printf(
			'<section class="screenshot">
				<div class="cont">
					<img src="%1$s" alt="%2$s"/>
					<a href="%3$s" class="hover" data-lity></a>
				</div>
				<ul>
					<li>%4$s</li>
					<li>%5$s</li>
					<li>%6$s</li>
				</ul>
			</section>',
			esc_url( SC_PLUGIN_ASSETS_URL . 'images/smtp/screenshot-tnail.png?ver=' . BaseHelpers::get_asset_version() ),
			esc_attr__( 'WP Mail SMTP screenshot', 'sugar-calendar-lite' ),
			esc_url( SC_PLUGIN_ASSETS_URL . 'images/smtp/screenshot-full.png?ver=' . BaseHelpers::get_asset_version() ),
			esc_html__( 'Resolves email deliverability for your site.', 'sugar-calendar-lite' ),
			esc_html__( 'Email logs allow you to resend failed emails, alerts notify you about failures and you can set a backup provider for total peace of mind.', 'sugar-calendar-lite' ),
			esc_html__( 'Choose from many providers: SendLayer, SMTP.com, Brevo, Gmail, Outlook, SES, and more.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Generate and output step 'Install' section HTML.
	 *
	 * @since 3.11.0
	 */
	protected function output_section_step_install() {

		$step = $this->get_data_step_install();

		if ( empty( $step ) ) {
			return;
		}

		$button_format       = '<button class="button %3$s" data-plugin="%1$s" data-action="%4$s" data-source="%5$s"%6$s>%2$s</button>';
		$button_allowed_html = [
			'button' => [
				'class'       => true,
				'data-plugin' => true,
				'data-action' => true,
				'data-source' => true,
				'disabled'    => true,
			],
		];

		if (
			! $this->output_data['plugin_installed'] &&
			! $this->output_data['pro_plugin_installed'] &&
			! current_user_can( 'install_plugins' )
		) {
			$button_format       = '<a class="link" href="%1$s" target="_blank" rel="nofollow noopener">%2$s <span aria-hidden="true" class="dashicons dashicons-external"></span></a>';
			$button_allowed_html = [
				'a'    => [
					'class'  => true,
					'href'   => true,
					'target' => true,
					'rel'    => true,
				],
				'span' => [
					'class'       => true,
					'aria-hidden' => true,
				],
			];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source = 'sugar-calendar-events';

		// Render the actionable button disabled + dimmed until sc-admin-smtp.js binds its
		// click handler (the script loads in the footer). This prevents a click made before
		// the handler exists — likely on a slow admin page — from being silently dropped.
		$awaiting_js = in_array( $step['button_action'] ?? '', [ 'install', 'activate' ], true );

		if ( $awaiting_js ) {
			$step['button_class'] .= ' grey';
		}

		$button = sprintf(
			$button_format,
			esc_attr( $step['plugin'] ),
			esc_html( $step['button_text'] ),
			esc_attr( $step['button_class'] ),
			esc_attr( $step['button_action'] ),
			esc_attr( $source ),
			$awaiting_js ? ' disabled="disabled"' : ''
		);

		printf(
			'<section class="step step-install">
				<aside class="num">
					<img src="%1$s" alt="%2$s" />
					<i class="loader hidden"></i>
				</aside>
				<div>
					<h2>%3$s</h2>
					<p>%4$s</p>
					%5$s
				</div>
			</section>',
			esc_url( SC_PLUGIN_ASSETS_URL . 'images/' . $step['icon'] ),
			esc_attr__( 'Step 1', 'sugar-calendar-lite' ),
			esc_html( $step['heading'] ),
			esc_html( $step['description'] ),
			wp_kses( $button, $button_allowed_html )
		);
	}

	/**
	 * Generate and output step 'Setup' section HTML.
	 *
	 * @since 3.11.0
	 */
	protected function output_section_step_setup() {

		$step = $this->get_data_step_setup();

		if ( empty( $step ) ) {
			return;
		}

		printf(
			'<section class="step step-setup %1$s">
				<aside class="num">
					<img src="%2$s" alt="%3$s" />
					<i class="loader hidden"></i>
				</aside>
				<div>
					<h2>%4$s</h2>
					<p>%5$s</p>
					<button class="button %6$s" data-url="%7$s">%8$s</button>
				</div>
			</section>',
			esc_attr( $step['section_class'] ),
			esc_url( SC_PLUGIN_ASSETS_URL . 'images/' . $step['icon'] ),
			esc_attr__( 'Step 2', 'sugar-calendar-lite' ),
			esc_html__( 'Set Up WP Mail SMTP', 'sugar-calendar-lite' ),
			esc_html__( 'Select and configure your mailer.', 'sugar-calendar-lite' ),
			esc_attr( $step['button_class'] ),
			esc_url( admin_url( $this->config['smtp_wizard_url'] ) ),
			esc_html( $step['button_text'] )
		);
	}

	/**
	 * Step 'Install' data.
	 *
	 * @since 3.11.0
	 *
	 * @return array Step data.
	 */
	protected function get_data_step_install() {

		$step = [];

		$step['heading']     = esc_html__( 'Install and Activate WP Mail SMTP', 'sugar-calendar-lite' );
		$step['description'] = esc_html__( 'Install WP Mail SMTP from the WordPress.org plugin repository.', 'sugar-calendar-lite' );

		$partner    = $this->get_partner();
		$pro_plugin = $partner['pro_basenames'][0];

		$this->output_data['all_plugins']          = get_plugins();
		$this->output_data['plugin_installed']     = array_key_exists( $partner['basename'], $this->output_data['all_plugins'] );
		$this->output_data['pro_plugin_installed'] = array_key_exists( $pro_plugin, $this->output_data['all_plugins'] );
		$this->output_data['plugin_activated']     = false;
		$this->output_data['plugin_setup']         = false;

		if ( ! $this->output_data['plugin_installed'] && ! $this->output_data['pro_plugin_installed'] ) {
			$step['icon']          = 'step-1.svg';
			$step['button_text']   = esc_html__( 'Install WP Mail SMTP', 'sugar-calendar-lite' );
			$step['button_class']  = 'button-primary';
			$step['button_action'] = 'install';
			$step['plugin']        = $partner['download_url'];

			if ( ! current_user_can( 'install_plugins' ) ) {
				$step['heading']     = esc_html__( 'WP Mail SMTP', 'sugar-calendar-lite' );
				$step['description'] = '';
				$step['button_text'] = esc_html__( 'WP Mail SMTP on WordPress.org', 'sugar-calendar-lite' );
				$step['plugin']      = $partner['wporg_url'];
			}
		} else {
			$this->output_data['plugin_activated'] = $this->is_smtp_activated();
			$this->output_data['plugin_setup']     = $this->is_smtp_configured();
			$step['icon']                          = $this->output_data['plugin_activated'] ? 'step-complete.svg' : 'step-1.svg';
			$step['button_text']                   = $this->output_data['plugin_activated'] ? esc_html__( 'WP Mail SMTP Installed & Activated', 'sugar-calendar-lite' ) : esc_html__( 'Activate WP Mail SMTP', 'sugar-calendar-lite' );
			$step['button_class']                  = $this->output_data['plugin_activated'] ? 'grey disabled' : 'button-primary';
			$step['button_action']                 = $this->output_data['plugin_activated'] ? '' : 'activate';
			$step['plugin']                        = $this->output_data['pro_plugin_installed'] ? $pro_plugin : $partner['basename'];
		}

		return $step;
	}

	/**
	 * Step 'Setup' data.
	 *
	 * @since 3.11.0
	 *
	 * @return array Step data.
	 */
	protected function get_data_step_setup() {

		$step = [
			'icon' => 'step-2.svg',
		];

		if ( $this->output_data['plugin_activated'] ) {
			$step['section_class'] = '';
			$step['button_class']  = 'button-primary';
			$step['button_text']   = esc_html__( 'Open Setup Wizard', 'sugar-calendar-lite' );
		} else {
			$step['section_class'] = 'grey';
			$step['button_class']  = 'grey disabled';
			$step['button_text']   = esc_html__( 'Start Setup', 'sugar-calendar-lite' );
		}

		if ( $this->output_data['plugin_setup'] ) {
			$step['icon']        = 'step-complete.svg';
			$step['button_text'] = esc_html__( 'Go to SMTP settings', 'sugar-calendar-lite' );
		}

		return $step;
	}

	/**
	 * Whether WP Mail SMTP plugin configured or not.
	 *
	 * @since 3.11.0
	 *
	 * @return bool True if some mailer is selected and configured properly.
	 */
	protected function is_smtp_configured() {

		return ( new SmtpDetector() )->is_configured();
	}

	/**
	 * Whether WP Mail SMTP plugin active or not.
	 *
	 * @since 3.11.0
	 *
	 * @return bool True if SMTP plugin is active.
	 */
	protected function is_smtp_activated() {

		return ( new SmtpDetector() )->is_active();
	}

	/**
	 * Redirect to SMTP settings page.
	 *
	 * @since 3.11.0
	 */
	public function redirect_to_smtp_settings() {

		// Redirect to SMTP plugin if it is activated.
		if ( $this->is_smtp_configured() ) {
			wp_safe_redirect( admin_url( $this->get_settings_path() ) );
			exit;
		}
	}

	/**
	 * Hide the admin header.
	 *
	 * @since 3.11.0
	 *
	 * @param bool         $display_admin_header Whether or not to display the admin header.
	 * @param PageAbstract $page                 Page instance.
	 *
	 * @return bool
	 */
	public function hide_admin_header( $display_admin_header, $page ) {

		if (
			is_null( $page ) ||
			! $page instanceof PageAbstract
		) {
			return $display_admin_header;
		}

		if ( $page->get_slug() !== self::SLUG ) {
			return $display_admin_header;
		}

		return false;
	}

	/**
	 * Get the slug of the SMTP page.
	 *
	 * @since 3.11.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return self::SLUG;
	}

	/**
	 * Get the title of the SMTP page.
	 *
	 * @since 3.11.0
	 *
	 * @return string 
	 */
	public static function get_title() {

		return __( 'SMTP', 'sugar-calendar-lite' );
	}

	/**
	 * Get the admin title.
	 *
	 * @since 3.11.0
	 *
	 * @param string $admin_title The admin title.
	 *
	 * @return string
	 */
	public function get_admin_title( $admin_title ) {

		return sprintf( '%s %s', self::get_title(), $admin_title );
	}

	/**
	 * Get the WordPress PHPMailer instance.
	 *
	 * @since 3.11.0
	 * @deprecated 3.15.0 SMTP detection moved to Sugar_Calendar\Admin\Tools\Smtp; kept for subclasses.
	 *
	 * @return PHPMailer
	 */
	protected function get_phpmailer() {

		global $phpmailer;

		if ( ! ( $phpmailer instanceof PHPMailer ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			$phpmailer = new PHPMailer( true ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $phpmailer;
	}
}
