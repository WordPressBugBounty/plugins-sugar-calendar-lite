<?php

namespace Sugar_Calendar\Integrations\OAuthRelay\Webhooks;

use Sugar_Calendar\Options;
use Sugar_Calendar\Admin\Pages\SettingsIntegrationsTab;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthRelayClient;
use Sugar_Calendar\Integrations\WebhookEventHandlerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook URL monitor.
 *
 * Detects when the site's REST API base URL changes (permalink structure
 * change, rest_url_prefix filter) and re-registers webhook URLs with the
 * Product API relay so incoming webhooks keep being delivered. Also runs a
 * loopback REST health check and warns on the Integrations settings page
 * when the REST API is unreachable (e.g. blocked by a security plugin) —
 * webhook delivery would otherwise just silently stop.
 *
 * Ported from Bookings; SCE adaptations: gates on
 * WebhookEventHandlerInterface, single-row find_active_by_provider()
 * lookups, and a direct admin_notices echo (the OAuthConnectionErrorNotice
 * pattern) instead of Bookings' Notices service.
 *
 * @since 3.12.0
 */
class WebhookUrlMonitor {

	/**
	 * Option key for the last-known REST API base URL.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const OPTION_REST_BASE_URL = 'sugar_calendar_webhook_rest_base_url';

	/**
	 * Option key for the REST API health check result.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const OPTION_REST_API_HEALTHY = 'sugar_calendar_rest_api_healthy';

	/**
	 * Capability registry.
	 *
	 * @since 3.12.0
	 *
	 * @var IntegrationCapabilityRegistry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 *
	 * @param IntegrationCapabilityRegistry $registry Capability registry.
	 */
	public function __construct( IntegrationCapabilityRegistry $registry ) {

		$this->registry = $registry;
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		add_action( 'update_option_permalink_structure', [ $this, 'on_permalink_change' ], 10, 0 );
		add_action( 'admin_notices', [ $this, 'maybe_display_rest_api_notice' ] );
	}

	/**
	 * Handle a permalink structure change: the REST base URL changes with it.
	 *
	 * @since 3.12.0
	 */
	public function on_permalink_change() {

		// Core fires update_option_permalink_structure BEFORE WP_Rewrite::init()
		// refreshes $wp_rewrite, but get_rest_url() branches on the option (already
		// written), so the plain<->pretty toggle is reflected here. The only stale
		// read is the /index.php/wp-json sub-form, which the Integrations-page
		// drift-sync self-heals on the next load (matches Bookings).
		$current_base = rest_url();
		$stored_base  = get_option( self::OPTION_REST_BASE_URL, '' );

		if ( $current_base === $stored_base ) {
			return;
		}

		$this->re_register_webhooks();

		update_option( self::OPTION_REST_BASE_URL, $current_base, true );

		// Skip the (up to 5s) synchronous loopback when there is nothing to
		// protect — the result would not be read until a connection exists.
		if ( $this->has_active_webhook_connections() ) {
			$this->run_rest_api_health_check();
		}
	}

	/**
	 * Warn on the Integrations settings page when the REST API is unreachable.
	 *
	 * Also drift-syncs the stored REST base URL (catches rest_url_prefix
	 * changes that never fire the permalink hook).
	 *
	 * @since 3.12.0
	 */
	public function maybe_display_rest_api_notice() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// The "Disable Integrations" kill-switch tears down every integration, so
		// webhook reachability is moot — never show the notice while it's on.
		if ( Options::get( 'disable_integrations', false ) ) {
			return;
		}

		if ( ! SettingsIntegrationsTab::is_current_page() ) {
			return;
		}

		// Only relevant when an active webhook-capable connection exists.
		if ( ! $this->has_active_webhook_connections() ) {
			return;
		}

		$this->maybe_sync_rest_base_url();

		$is_healthy = get_option( self::OPTION_REST_API_HEALTHY, '' );

		// Never checked yet — run the check now.
		if ( $is_healthy === '' ) {
			$is_healthy = $this->run_rest_api_health_check();
		}

		if ( $is_healthy ) {
			return;
		}

		$test_url = rest_url( IncomingWebhookHandler::REST_NAMESPACE . '/webhooks/test' );

		?>
		<div class="notice notice-warning">
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s - REST API test URL. */
						__( 'The WordPress REST API appears to be unreachable on this site. Integration webhooks require the REST API to function. Please ensure it is not disabled by a security plugin or server configuration. Tested URL: <code>%s</code>', 'sugar-calendar-lite' ),
						esc_html( $test_url )
					),
					[ 'code' => [] ]
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Drift-sync the stored REST base URL outside the permalink hook.
	 *
	 * @since 3.12.0
	 */
	private function maybe_sync_rest_base_url() {

		$current_base = rest_url();
		$stored_base  = get_option( self::OPTION_REST_BASE_URL, '' );

		if ( $current_base === $stored_base ) {
			return;
		}

		$this->re_register_webhooks();

		update_option( self::OPTION_REST_BASE_URL, $current_base, true );

		$this->run_rest_api_health_check();
	}

	/**
	 * Re-register webhook URLs for every webhook-capable provider with an
	 * active connection.
	 *
	 * @since 3.12.0
	 */
	private function re_register_webhooks() {

		$handlers = $this->registry->get( WebhookEventHandlerInterface::class );

		if ( empty( $handlers ) ) {
			return;
		}

		$relay = new OAuthRelayClient();

		foreach ( array_keys( $handlers ) as $slug ) {

			$row = OAuthConnectionModel::find_active_by_provider( $slug );

			if ( $row === null ) {
				continue;
			}

			// Best-effort re-register; drift-sync retries on the next page load.
			$relay->register_webhook(
				$slug,
				(string) $row['account_id'],
				IncomingWebhookHandler::get_webhook_url( $slug )
			);
		}
	}

	/**
	 * Run a REST API health check via a loopback request.
	 *
	 * Any response below 500 counts healthy — including the endpoint's own
	 * 401/400 — because the check tests that the REST infrastructure
	 * responds, not the specific route.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	private function run_rest_api_health_check(): bool {

		$response = wp_remote_get(
			rest_url( IncomingWebhookHandler::REST_NAMESPACE . '/webhooks/test' ),
			[
				'timeout'   => 5,
				'sslverify' => false,
			]
		);

		$is_healthy = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 500;

		update_option( self::OPTION_REST_API_HEALTHY, $is_healthy ? 1 : 0, true );

		return $is_healthy;
	}

	/**
	 * Whether any webhook-capable provider has an active connection.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	private function has_active_webhook_connections(): bool {

		foreach ( array_keys( $this->registry->get( WebhookEventHandlerInterface::class ) ) as $slug ) {
			if ( OAuthConnectionModel::find_active_by_provider( $slug ) !== null ) {
				return true;
			}
		}

		return false;
	}
}
