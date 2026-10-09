<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Integrations\IntegrationsDisabler;
use Sugar_Calendar\Tasks\Task;

/**
 * Registers the site with the Product API in the background.
 *
 * Before this, a Pro site registered only on a Zoom Connect click, so Gia AI and the
 * credits panels stayed hidden on every Pro site that never connected Zoom.
 *
 * @since 3.15.0
 */
class SiteRegistrationTask extends Task {

	/**
	 * Action name for this task.
	 *
	 * @since 3.15.0
	 */
	const ACTION = 'sugar_calendar_register_site';

	/**
	 * Set when an attempt is queued, so a site that cannot register queues one an hour, not one per admin page.
	 *
	 * @since 3.15.0
	 */
	const THROTTLE_TRANSIENT = 'sugar_calendar_register_site_queued';

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 */
	public function __construct() {

		parent::__construct( self::ACTION );
	}

	/**
	 * Initialize the task.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function init() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_action( self::ACTION, [ $this, 'process' ] );
		add_action( 'admin_init', [ $this, 'maybe_schedule' ] );
	}

	/**
	 * Queue a registration attempt when the site has no credentials for its current URL.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function maybe_schedule() {

		if ( wp_doing_ajax() || ! self::is_allowed() ) {
			return;
		}

		if ( ( new SiteRegistrar() )->is_registered() || get_transient( self::THROTTLE_TRANSIENT ) ) {
			return;
		}

		set_transient( self::THROTTLE_TRANSIENT, true, HOUR_IN_SECONDS );

		$this->async()->unique()->register();
	}

	/**
	 * Register the site.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function process() {

		if ( ! self::is_allowed() ) {
			return;
		}

		$result = ( new SiteRegistrar() )->register();

		if ( is_wp_error( $result ) ) {
			error_log( '[SC Relay] background site registration failed: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Whether the site may be registered without an admin's click.
	 *
	 * Lite registers only while integrations are on, the same rule usage tracking
	 * follows. A local site is skipped: ownership verification calls it back over the internet.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private static function is_allowed() {

		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) {
			return false;
		}

		return ! IntegrationsDisabler::is_disabled()
			&& ! WP::is_local_environment()
			&& self::is_public_host( (string) wp_parse_url( get_site_url(), PHP_URL_HOST ) );
	}

	/**
	 * Whether the Product API could reach this host to verify ownership.
	 *
	 * Mirrors the API's own `SiteUrl::isNonPublicHost()`. `WP::is_local_environment()` misses a
	 * bare `localhost` (the E2E stack) and IP hosts.
	 *
	 * @since 3.15.0
	 *
	 * @param string $host Site host.
	 *
	 * @return bool
	 */
	private static function is_public_host( $host ) {

		$host = strtolower( trim( $host, '[]' ) );

		if ( $host === '' ) {
			return false;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) !== false ) {
			return filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false;
		}

		$tld = substr( strrchr( '.' . $host, '.' ), 1 );

		return ! in_array( $tld, [ 'localhost', 'local', 'test', 'internal', 'invalid', 'example' ], true );
	}
}
