<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use Sugar_Calendar\Vendor\ProductApi\Auth\HMACAuthStrategy;
use Sugar_Calendar\Vendor\ProductApi\Auth\InstallationId;
use Sugar_Calendar\Vendor\ProductApi\Context;
use Sugar_Calendar\Vendor\ProductApi\Options;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;
use WP_Error;

/**
 * OAuth Relay Client.
 *
 * Handles communication with the Product API OAuth Relay endpoints.
 *
 * @since 3.12.0
 */
class OAuthRelayClient {

	/**
	 * Get the installation ID from the Product API client.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	private function get_installation_id(): string {

		$options = ProductApi::get( Options::class );

		return ( new InstallationId( $options ) )->get();
	}

	/**
	 * Get the effective license type for relay routing.
	 *
	 * Returns 'pro' only when the Pro plugin is active AND the license is valid.
	 * Pro installs without a valid license fall back to 'lite' so OAuth flows
	 * keep working on Lite relay endpoints until the license is activated.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	private function get_license_type(): string {

		$context = ProductApi::get( Context::class );

		return ( $context->is_pro() && $context->is_license_valid() ) ? 'pro' : 'lite';
	}

	/**
	 * Get the authorization URL to start OAuth flow.
	 *
	 * @since 3.12.0
	 *
	 * @param string $provider   Provider name: 'google' or 'zoom'.
	 * @param string $return_url URL to return to after OAuth completes.
	 * @param string $feature    Feature identifier (e.g., 'integrations').
	 *
	 * @return string Authorization URL.
	 */
	public function get_authorization_url(
		string $provider,
		string $return_url,
		string $feature
	): string {

		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) {
			/**
			 * Filter to stub the relay authorization URL in E2E tests.
			 *
			 * @since 3.12.0
			 *
			 * @param string $url        Default empty.
			 * @param string $provider   Provider slug.
			 * @param string $return_url Return URL.
			 */
			return (string) apply_filters( 'sc_oauth_relay_test_authorization_url', '', $provider, $return_url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Internal test-mode hook seam (E2E only).
		}

		$context = ProductApi::get( Context::class );

		// Add security params to return URL for callback validation.
		$return_url = add_query_arg(
			[
				'nonce'    => wp_create_nonce( 'sc_oauth_connect' ),
				'provider' => $provider,
				'feature'  => $feature,
			],
			$return_url
		);

		$query_params = [
			'provider'        => $provider,
			'feature'         => $feature,
			'installation_id' => $this->get_installation_id(),
			'return_url'      => rawurlencode( $return_url ),
		];

		$auth_url = add_query_arg(
			$query_params,
			$context->get_api_url() . '/oauth-relay/v1/' . $this->get_license_type() . '/auth/start'
		);

		return $auth_url;
	}

	/**
	 * Exchange authorization code for encrypted tokens.
	 *
	 * @since 3.12.0
	 *
	 * @param string $code Exchange code from OAuth callback.
	 *
	 * @return array|WP_Error Token data or error.
	 */
	public function exchange_code( string $code ) {

		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) {
			/**
			 * Filter to stub the token-exchange response in E2E tests.
			 *
			 * @since 3.12.0
			 *
			 * @param mixed  $response Default null; return an array to short-circuit, WP_Error to fail.
			 * @param string $code     Exchange code.
			 */
			$stub = apply_filters( 'sc_oauth_relay_test_exchange_response', null, $code ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Internal test-mode hook seam (E2E only).

			if ( $stub !== null ) {
				return $stub;
			}
		}

		$hmac_auth = ProductApi::get( HMACAuthStrategy::class );

		$response = ProductApi::client()
			->post( '/oauth-relay/v1/' . $this->get_license_type() . '/auth/token' )
			->json( [ 'code' => $code ] )
			->auth_strategy( $hmac_auth )
			->send();

		$result = $this->handle_relay_response( $response );

		return $result;
	}

	/**
	 * Refresh an expired access token.
	 *
	 * @since 3.12.0
	 *
	 * @param string $refresh_token Encrypted refresh token.
	 * @param int    $app_id        OAuth app ID from original token exchange.
	 *
	 * @return array|WP_Error New token data or error.
	 */
	public function refresh_token( string $refresh_token, int $app_id ) {

		$hmac_auth = ProductApi::get( HMACAuthStrategy::class );

		$response = ProductApi::client()
			->post( '/oauth-relay/v1/' . $this->get_license_type() . '/auth/refresh' )
			->json(
				[
					'refresh_token' => $refresh_token,
					'app_id'        => $app_id,
				]
			)
			->auth_strategy( $hmac_auth )
			->send();

		return $this->handle_relay_response( $response );
	}

	/**
	 * Register a webhook URL with the Product API.
	 *
	 * Tells the relay to forward provider webhook events to the given URL.
	 *
	 * @since 3.12.0
	 *
	 * @param string $provider         Provider slug (e.g. 'zoom').
	 * @param string $provider_user_id Provider account/user ID.
	 * @param string $webhook_url      Publicly accessible URL to receive webhooks.
	 *
	 * @return array|WP_Error Response body or error.
	 */
	public function register_webhook( string $provider, string $provider_user_id, string $webhook_url ) {

		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) {
			/**
			 * Filter to stub the relay register-webhook response in E2E tests.
			 *
			 * @since 3.12.0
			 *
			 * @param mixed  $response         Default null; array to short-circuit success, WP_Error to fail.
			 * @param string $provider         Provider slug the production code passed.
			 * @param string $provider_user_id Provider account id the production code passed.
			 * @param string $webhook_url      Webhook URL the production code built.
			 */
			return apply_filters( 'sc_oauth_relay_test_register_webhook_response', null, $provider, $provider_user_id, $webhook_url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Internal test-mode hook seam (E2E only).
		}

		// The relay is configured on both editions (Pro in includes/pro/Pro.php,
		// Lite in OAuthRelay\Loader) — guard defensively so an unconfigured relay
		// (e.g. configure() failed) degrades to a WP_Error instead of fataling.
		try {
			$hmac_auth = ProductApi::get( HMACAuthStrategy::class );

			$response = ProductApi::client()
				->post( '/oauth-relay/v1/' . $this->get_license_type() . '/webhooks/register' )
				->json(
					[
						'provider'         => $provider,
						'provider_user_id' => $provider_user_id,
						'webhook_url'      => $webhook_url,
					]
				)
				->auth_strategy( $hmac_auth )
				->send();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'relay_unconfigured', $e->getMessage() );
		}

		return $this->handle_relay_response( $response );
	}

	/**
	 * Unregister a webhook URL from the Product API.
	 *
	 * Stops the relay from forwarding provider webhook events for this user.
	 *
	 * @since 3.12.0
	 *
	 * @param string $provider         Provider slug (e.g. 'zoom').
	 * @param string $provider_user_id Provider account/user ID.
	 *
	 * @return array|WP_Error Response body or error.
	 */
	public function unregister_webhook( string $provider, string $provider_user_id ) {

		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) {
			/**
			 * Filter to stub the relay unregister-webhook response in E2E tests.
			 *
			 * @since 3.12.0
			 *
			 * @param mixed  $response         Default null; array to short-circuit success, WP_Error to fail.
			 * @param string $provider         Provider slug the production code passed.
			 * @param string $provider_user_id Provider account id the production code passed.
			 */
			return apply_filters( 'sc_oauth_relay_test_unregister_webhook_response', null, $provider, $provider_user_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Internal test-mode hook seam (E2E only).
		}

		// The relay is configured on both editions (Pro in includes/pro/Pro.php,
		// Lite in OAuthRelay\Loader) — guard defensively so an unconfigured relay
		// (e.g. configure() failed) degrades to a WP_Error instead of fataling.
		try {
			$hmac_auth = ProductApi::get( HMACAuthStrategy::class );

			$response = ProductApi::client()
				->post( '/oauth-relay/v1/' . $this->get_license_type() . '/webhooks/unregister' )
				->json(
					[
						'provider'         => $provider,
						'provider_user_id' => $provider_user_id,
					]
				)
				->auth_strategy( $hmac_auth )
				->send();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'relay_unconfigured', $e->getMessage() );
		}

		return $this->handle_relay_response( $response );
	}

	/**
	 * Handle a relay API response.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $response Response from ProductApi client.
	 *
	 * @return array|WP_Error Response body or error.
	 */
	private function handle_relay_response( $response ) {

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! $response->is_successful() ) {
			$error = $response->get_errors();

			// Attach HTTP status code to the error for diagnostics.
			if ( is_wp_error( $error ) ) {
				$existing_data = $error->get_error_data( $error->get_error_code() );
				$merged_data   = is_array( $existing_data ) ? $existing_data : [];

				$merged_data['http_status'] = $response->get_status_code();

				$error->add_data( $merged_data, $error->get_error_code() );
			}

			return $error;
		}

		return $response->get_body();
	}
}
