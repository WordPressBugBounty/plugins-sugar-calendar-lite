<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use Sugar_Calendar\Vendor\ProductApi\Auth\HMACAuthStrategy;
use Sugar_Calendar\Vendor\ProductApi\Context;
use Sugar_Calendar\Vendor\ProductApi\Http\Client;
use Sugar_Calendar\Vendor\ProductApi\Http\Request;
use Sugar_Calendar\Vendor\ProductApi\Http\Response;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;
use WP_Error;

/**
 * Abstract Integration Actions OAuth Client.
 *
 * Base class for OAuth-based integration action clients.
 * Provides HTTP methods with HMAC auth and OAuth token middleware.
 *
 * @since 3.12.0
 */
abstract class AbstractIntegrationActionsOAuthClient {

	/**
	 * Product API client with HMAC auth configured.
	 *
	 * @since 3.12.0
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * OAuth relay client for token refresh.
	 *
	 * @since 3.12.0
	 *
	 * @var OAuthRelayClient
	 */
	private $oauth_relay_client;

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 */
	public function __construct() {

		$this->client = ProductApi::client()
			->auth_strategy( ProductApi::get( HMACAuthStrategy::class ) );

		$this->oauth_relay_client = new OAuthRelayClient();
	}

	/**
	 * Create a POST request with OAuth middleware.
	 *
	 * @since 3.12.0
	 *
	 * @param string                   $path       Endpoint path (without base path).
	 * @param OAuthConnectionInterface $connection OAuth connection.
	 *
	 * @return Request Request builder for further customization.
	 */
	protected function post( string $path, OAuthConnectionInterface $connection ): Request {

		return $this->client
			->middleware( new OAuthTokenMiddleware( $connection, $this->oauth_relay_client ) )
			->post( $this->get_base_path() . $path );
	}

	/**
	 * Create a DELETE request with OAuth middleware.
	 *
	 * @since 3.12.0
	 *
	 * @param string                   $path       Endpoint path (without base path).
	 * @param OAuthConnectionInterface $connection OAuth connection.
	 *
	 * @return Request Request builder for further customization.
	 */
	protected function delete( string $path, OAuthConnectionInterface $connection ): Request {

		return $this->client
			->middleware( new OAuthTokenMiddleware( $connection, $this->oauth_relay_client ) )
			->delete( $this->get_base_path() . $path );
	}

	/**
	 * Create a GET request with OAuth middleware.
	 *
	 * @since 3.12.0
	 *
	 * @param string                   $path       Endpoint path (without base path).
	 * @param OAuthConnectionInterface $connection OAuth connection.
	 *
	 * @return Request Request builder for further customization.
	 */
	protected function get( string $path, OAuthConnectionInterface $connection ): Request {

		return $this->client
			->middleware( new OAuthTokenMiddleware( $connection, $this->oauth_relay_client ) )
			->get( $this->get_base_path() . $path );
	}

	/**
	 * Create a PATCH request with OAuth middleware.
	 *
	 * @since 3.12.0
	 *
	 * @param string                   $path       Endpoint path (without base path).
	 * @param OAuthConnectionInterface $connection OAuth connection.
	 *
	 * @return Request Request builder for further customization.
	 */
	protected function patch( string $path, OAuthConnectionInterface $connection ): Request {

		// The vendor Client does not provide a native patch() method,
		// so we construct the Request manually with the PATCH method.
		$full_path = $this->get_base_path() . $path;

		$request = new Request( 'PATCH', $full_path );

		return $request->client(
			$this->client
				->middleware( new OAuthTokenMiddleware( $connection, $this->oauth_relay_client ) )
		);
	}

	/**
	 * Get base path for integration endpoints.
	 *
	 * Routes to the Pro segment only when Pro is active AND the license is valid.
	 * Pro installs without a valid license fall back to the Lite segment so
	 * API calls keep working on Lite limits until the license is activated.
	 *
	 * @since 3.12.0
	 *
	 * @return string Base path (e.g., '/integrations/v1/pro').
	 */
	protected function get_base_path(): string {

		$context      = ProductApi::get( Context::class );
		$license_type = ( $context->is_pro() && $context->is_license_valid() ) ? 'pro' : 'lite';

		return '/integrations/v1/' . $license_type;
	}

	/**
	 * Whether the relay E2E test-mode seam is active.
	 *
	 * Centralizes the constant check so each action method's test-stub guard
	 * doesn't re-derive it — and any future provider's actions client gets
	 * the same seam for free instead of re-copying the condition.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	protected function is_test_mode(): bool {

		return defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE;
	}

	/**
	 * Handle API response consistently.
	 *
	 * @since 3.12.0
	 *
	 * @param Response|WP_Error $response API response.
	 *
	 * @return array|WP_Error Response body or error.
	 */
	protected function handle_response( $response ) {

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! $response->is_successful() ) {
			return $response->get_errors();
		}

		return $response->get_body();
	}
}
