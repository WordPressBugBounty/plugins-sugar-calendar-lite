<?php

namespace Sugar_Calendar\Admin\Tools;

use PHPMailer\PHPMailer\PHPMailer;
use Sugar_Calendar\PartnerPlugins\Catalog;
use WPMailSMTP\Options as WpMailSmtpOptions;

/**
 * Detect WP Mail SMTP activation and configuration.
 *
 * @since 3.15.0
 */
class Smtp {

	/**
	 * Partner plugin catalogue.
	 *
	 * @since 3.15.0
	 *
	 * @var Catalog
	 */
	private $catalog;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param Catalog|null $catalog Partner catalogue; a new one when omitted.
	 */
	public function __construct( Catalog $catalog = null ) {

		$this->catalog = $catalog ?? new Catalog();
	}

	/**
	 * Whether WP Mail SMTP is active and loaded.
	 *
	 * @since 3.15.0
	 *
	 * @return bool Whether WP Mail SMTP is available.
	 */
	public function is_active() {

		return $this->catalog->get_state( 'wp-mail-smtp' ) === 'active' && function_exists( 'wp_mail_smtp' );
	}

	/**
	 * Whether WP Mail SMTP has a complete non-default mailer.
	 *
	 * @since 3.15.0
	 *
	 * @return bool Whether SMTP is configured.
	 */
	public function is_configured() {

		if ( ! $this->is_active() || ! class_exists( WpMailSmtpOptions::class ) ) {
			return false;
		}

		$options = WpMailSmtpOptions::init();

		if ( ! is_object( $options ) || ! method_exists( $options, 'get' ) ) {
			return false;
		}

		$mailer = $options->get( 'mail', 'mailer' );

		if ( $mailer === '' || $mailer === 'mail' ) {
			return false;
		}

		$wp_mail_smtp = wp_mail_smtp();

		if ( ! is_object( $wp_mail_smtp ) || ! method_exists( $wp_mail_smtp, 'get_providers' ) ) {
			return false;
		}

		$providers = $wp_mail_smtp->get_providers();

		if ( ! is_object( $providers ) || ! method_exists( $providers, 'get_mailer' ) ) {
			return false;
		}

		$provider = $providers->get_mailer( $mailer, $this->get_phpmailer() );

		return is_object( $provider ) && method_exists( $provider, 'is_mailer_complete' ) && $provider->is_mailer_complete();
	}

	/**
	 * Get the WordPress PHPMailer instance.
	 *
	 * @since 3.15.0
	 *
	 * @return PHPMailer Instance of PHPMailer.
	 */
	private function get_phpmailer() {

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
