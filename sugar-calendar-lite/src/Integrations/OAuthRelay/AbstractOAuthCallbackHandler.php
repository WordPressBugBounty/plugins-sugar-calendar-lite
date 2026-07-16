<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use Sugar_Calendar\Admin\Pages\SettingsIntegrationsTab;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\OAuthRelay\Webhooks\IncomingWebhookHandler;
use Sugar_Calendar\Integrations\OAuthRelay\Webhooks\WebhookUrlMonitor;
use Sugar_Calendar\Integrations\WebhookEventHandlerInterface;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract OAuth Callback Handler.
 *
 * Base class for handling OAuth callbacks from different providers.
 *
 * Adapted from Bookings: dropped CalendarConflictProviderInterface and
 * ExternalBusyTimeService (no SCE analog). The webhook-registration step
 * Bookings runs after store_connection() was restored in Segment 3a.
 *
 * @since 3.12.0
 */
abstract class AbstractOAuthCallbackHandler {

	/**
	 * OAuth Relay Client.
	 *
	 * @since 3.12.0
	 *
	 * @var OAuthRelayClient
	 */
	protected $oauth_client;

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 *
	 * @param OAuthRelayClient $oauth_client OAuth Relay Client.
	 */
	public function __construct( OAuthRelayClient $oauth_client ) {

		$this->oauth_client = $oauth_client;
	}

	/**
	 * Get the provider identifier (e.g., 'zoom').
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	abstract protected function get_provider(): string;

	/**
	 * Get the default redirect URL when redirect_to is not provided.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_default_redirect_url(): string {

		return SettingsIntegrationsTab::get_integration_url( $this->get_provider() );
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function init() {

		add_action( 'admin_init', [ $this, 'handle_callback' ] );
	}

	/**
	 * Handle the OAuth callback.
	 *
	 * @since 3.12.0
	 */
	public function handle_callback(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		// Check if this is our callback.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'sc_oauth_callback' ) {
			return;
		}

		// Check if this callback is for our provider.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['provider'] ) || $_GET['provider'] !== $this->get_provider() ) {
			return;
		}

		// Verify user has permission.
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_error( 'local:callback:unauthorized' );

			return;
		}

		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'sc_oauth_connect' ) ) {
			$this->redirect_with_error( 'local:callback:invalid_nonce' );

			return;
		}

		// Check for errors from OAuth provider.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$this->redirect_with_error( sanitize_text_field( wp_unslash( $_GET['error'] ) ) );

			return;
		}

		// Get exchange code.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['exchange_code'] ) ) {
			$this->redirect_with_error( 'local:callback:missing_exchange_code' );

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$exchange_code = sanitize_text_field( wp_unslash( $_GET['exchange_code'] ) );

		// Exchange code for tokens.
		$result = $this->oauth_client->exchange_code( $exchange_code );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( $result->get_error_code() );

			return;
		}

		// Validate exchange result.
		$is_test_mode = defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE;

		if ( $is_test_mode ) {
			// Test-mode stubs may return a flat shape (no 'tokens' wrapper).
			$tokens = $result['tokens'] ?? $result;
			$user   = $result['user'] ?? [];
			$app_id = (int) ( $result['app_id'] ?? $tokens['app_id'] ?? 0 );

			// Some E2E stubs return a flat array shape — accept either.
			if ( empty( $user ) ) {
				$user = [
					'id'     => $tokens['account_id'] ?? '',
					'email'  => $tokens['account_email'] ?? '',
					'name'   => $tokens['account_name'] ?? '',
					'avatar' => $tokens['account_avatar'] ?? '',
				];
			}
		} else {
			// Production: require the documented nested shape.
			if ( empty( $result['tokens'] ) || empty( $result['user'] ) || empty( $result['app_id'] ) ) {
				error_log( '[SC OAuth ' . $this->get_provider() . '] Unexpected relay response shape on exchange.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$this->redirect_with_error( 'local:callback:invalid_response' );

				return;
			}

			$tokens = $result['tokens'];
			$user   = $result['user'];
			$app_id = (int) $result['app_id'];
		}

		if ( empty( $tokens['access_token'] ) || empty( $tokens['refresh_token'] ) ) {
			$this->redirect_with_error( 'local:callback:missing_tokens' );

			return;
		}

		if ( empty( $user['id'] ) ) {
			$this->redirect_with_error( 'local:callback:missing_account_id' );

			return;
		}

		// Store the connection (insert or update).
		$stored = $this->store_connection( $tokens, $user, $app_id );

		if ( is_wp_error( $stored ) ) {
			$this->redirect_with_error( $stored->get_error_code() );

			return;
		}

		// Register the provider webhook with the relay (3a). Log-only on
		// failure: the admin's click was "Connect", and Connect succeeded —
		// webhook delivery degrades gracefully.
		$this->maybe_register_webhook( (string) $user['id'] );

		// Redirect with success.
		$this->redirect_with_success();
	}

	/**
	 * Store OAuth connection data.
	 *
	 * The connection is site-wide: exactly one account per provider, regardless
	 * of which admin connects it. Connecting therefore REPLACES whatever was
	 * connected before — any existing rows for the provider (this admin's or
	 * another admin's) are deleted first, then the freshly authorized account is
	 * inserted as the single row. Deleting before inserting also sidesteps the
	 * UNIQUE(user_id, provider, app_id, account_id) constraint entirely.
	 *
	 * Collapsing to one row is what keeps "is there an active row?" (the
	 * integration is usable) and "is there an auth_error row?" (the reconnect
	 * notice) from ever disagreeing.
	 *
	 * @since 3.12.0
	 *
	 * @param array $tokens Token data (access_token, refresh_token, expires_in, token_type, scope, expires_at).
	 * @param array $user   User data (id, email, name, avatar).
	 * @param int   $app_id OAuth application ID.
	 *
	 * @return int|WP_Error Inserted row ID on success.
	 */
	protected function store_connection( array $tokens, array $user, int $app_id ) {

		$expires_at = ! empty( $tokens['expires_at'] )
			? $tokens['expires_at']
			: gmdate( 'Y-m-d H:i:s', time() + (int) ( $tokens['expires_in'] ?? 3600 ) );

		// Replace any existing connection for this provider: the account being
		// authorized becomes the one and only site-wide connection.
		OAuthConnectionModel::delete_by_provider( $this->get_provider() );

		$connection_data = [
			'status'         => 'active',
			'user_id'        => get_current_user_id(),
			'provider'       => $this->get_provider(),
			'app_id'         => $app_id,
			'account_id'     => (string) $user['id'],
			'access_token'   => $tokens['access_token'],
			'refresh_token'  => $tokens['refresh_token'],
			'expires_at'     => $expires_at,
			'token_type'     => $tokens['token_type'] ?? 'bearer',
			'scope'          => $tokens['scope'] ?? '',
			'account_email'  => $user['email'] ?? '',
			'account_name'   => $user['name'] ?? '',
			'account_avatar' => $user['avatar'] ?? '',
			'connected_at'   => current_time( 'mysql', true ),
		];

		$id = OAuthConnectionModel::insert( $connection_data );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return (int) $id;
	}

	/**
	 * Register the provider webhook URL with the relay after a successful
	 * connect.
	 *
	 * Only fires when the provider has a registered
	 * WebhookEventHandlerInterface capability — no handler, no reason to
	 * receive webhooks. Also stores the REST base URL baseline that
	 * WebhookUrlMonitor uses for drift detection.
	 *
	 * (Bookings runs this same step in its callback handler; the Segment-1
	 * port dropped it because the webhook subsystem did not exist yet.)
	 *
	 * @since 3.12.0
	 *
	 * @param string $account_id Provider account id of the stored connection.
	 */
	protected function maybe_register_webhook( string $account_id ): void {

		$handler = IntegrationCapabilityRegistry::instance()->find(
			WebhookEventHandlerInterface::class,
			$this->get_provider()
		);

		if ( $handler === null ) {
			return;
		}

		$result = $this->oauth_client->register_webhook(
			$this->get_provider(),
			$account_id,
			IncomingWebhookHandler::get_webhook_url( $this->get_provider() )
		);

		if ( is_wp_error( $result ) ) {
			error_log( '[SC OAuth ' . $this->get_provider() . '] webhook registration failed: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// Leave the monitor baseline untouched: a stale/empty baseline
			// makes WebhookUrlMonitor's drift-sync re-attempt registration on
			// the next Integrations-page load.
			return;
		}

		// Baseline for WebhookUrlMonitor drift detection — only once
		// registration actually succeeded.
		update_option( WebhookUrlMonitor::OPTION_REST_BASE_URL, rest_url(), true );
	}

	/**
	 * Get the redirect URL from request or default.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_redirect_url(): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

		// When no redirect_to is supplied, return the default directly:
		// wp_validate_redirect() returns its fallback only when $location is
		// actively rejected, NOT when it is empty — an empty string passes
		// validation and is returned as-is, which would strip the admin URL
		// and land the success/error notice on a bare admin.php with no panel.
		if ( $redirect_to === '' ) {
			return $this->get_default_redirect_url();
		}

		return wp_validate_redirect( $redirect_to, $this->get_default_redirect_url() );
	}

	/**
	 * Redirect with relay-unavailable error notice.
	 *
	 * @since 3.12.0
	 *
	 * @param string $error_code Error code (currently only used for logging).
	 */
	protected function redirect_with_error( string $error_code ): void {

		error_log( '[SC OAuth ' . $this->get_provider() . '] callback error: ' . $error_code ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		$url = add_query_arg(
			'sc_notice',
			$this->get_provider() . '_relay_unavailable',
			$this->get_redirect_url()
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Redirect with success notice.
	 *
	 * @since 3.12.0
	 */
	protected function redirect_with_success(): void {

		$url = add_query_arg(
			'sc_notice',
			$this->get_provider() . '_connected',
			$this->get_redirect_url()
		);

		wp_safe_redirect( $url );
		exit;
	}
}
