<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use Sugar_Calendar\Vendor\ProductApi\Http\Middleware\MiddlewareInterface;
use Sugar_Calendar\Vendor\ProductApi\Http\Request;
use Sugar_Calendar\Vendor\ProductApi\Http\Response;
use WP_Error;

/**
 * OAuth Token Middleware.
 *
 * Product API client middleware that handles OAuth token refresh
 * and adds Authorization header to requests.
 *
 * @since 3.12.0
 */
class OAuthTokenMiddleware implements MiddlewareInterface {

	/**
	 * OAuth connection.
	 *
	 * @since 3.12.0
	 *
	 * @var OAuthConnectionInterface
	 */
	private $connection;

	/**
	 * OAuth relay client for token refresh.
	 *
	 * @since 3.12.0
	 *
	 * @var OAuthRelayClient
	 */
	private $oauth_client;

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 *
	 * @param OAuthConnectionInterface $connection   OAuth connection.
	 * @param OAuthRelayClient         $oauth_client OAuth relay client.
	 */
	public function __construct(
		OAuthConnectionInterface $connection,
		OAuthRelayClient $oauth_client
	) {

		$this->connection   = $connection;
		$this->oauth_client = $oauth_client;
	}

	/**
	 * Handle the request.
	 *
	 * Refreshes token if expiring soon, then adds Authorization header.
	 *
	 * @since 3.12.0
	 *
	 * @param Request  $request The request object.
	 * @param callable $next    The next middleware in the stack.
	 *
	 * @return Response|WP_Error
	 */
	public function handle( Request $request, callable $next ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Refresh token if expiring soon.
		if ( $this->connection->is_expiring_soon() ) {
			$result = $this->oauth_client->refresh_token(
				$this->connection->get_refresh_token(),
				$this->connection->get_app_id()
			);

			// Retry once on transient failures before giving up.
			if ( is_wp_error( $result ) && ! $this->is_auth_failure_error( $result ) ) {
				$result = $this->oauth_client->refresh_token(
					$this->connection->get_refresh_token(),
					$this->connection->get_app_id()
				);
			}

			if ( is_wp_error( $result ) ) {

				// Only mark as auth_error on real auth failures from the provider.
				// Transport errors and upstream 5xx are transient — keep the
				// connection active so the next request can retry.
				if ( $this->is_auth_failure_error( $result ) ) {
					$this->connection->mark_auth_error();
				}

				return $result;
			}

			// Successful HTTP response but malformed body — likely a relay bug,
			// not an auth problem. Retry on the next request instead of locking
			// the connection out.
			if ( empty( $result['tokens'] ) || empty( $result['tokens']['access_token'] ) || empty( $result['tokens']['expires_in'] ) ) {
				return new WP_Error(
					'oauth_refresh_invalid_response',
					esc_html__( 'Token refresh response did not contain valid token data.', 'sugar-calendar-lite' )
				);
			}

			$this->connection->update_tokens( $result['tokens'] );
		}

		// Add token to Authorization header.
		$request->header(
			'Authorization',
			'Bearer ' . $this->connection->get_access_token()
		);

		$response = $next( $request );

		// Retry once on transient provider failures (transport error, 5xx, 429).
		if ( $this->is_transient_response_failure( $response ) ) {
			$response = $next( $request );
		}

		// Mark connection as auth_error if the API returns 401 (unauthorized).
		if ( $response instanceof Response && $response->get_status_code() === 401 ) {
			$this->connection->mark_auth_error();
		}

		return $response;
	}

	/**
	 * Check whether a response should be retried as a transient failure.
	 *
	 * Transport errors (WP_Error without http_status), upstream 5xx, and
	 * rate-limiting (429) are safe to retry once. Auth failures and other
	 * business-logic errors are not.
	 *
	 * @since 3.12.0
	 *
	 * @param Response|WP_Error $response Response or error from the pipeline.
	 *
	 * @return bool
	 */
	private function is_transient_response_failure( $response ): bool {

		if ( is_wp_error( $response ) ) {
			return ! $this->is_auth_failure_error( $response );
		}

		if ( ! $response instanceof Response ) {
			return false;
		}

		$status = $response->get_status_code();

		return $status === 429 || ( $status >= 500 && $status < 600 );
	}

	/**
	 * Check whether a refresh-token error actually indicates an auth failure.
	 *
	 * Distinguishes 4xx auth failures (refresh token is dead, user must
	 * reconnect) from transport errors and upstream 5xx (transient, retry).
	 *
	 * @since 3.12.0
	 *
	 * @param WP_Error $error Error returned by the relay client.
	 *
	 * @return bool
	 */
	private function is_auth_failure_error( WP_Error $error ): bool {

		$data        = $error->get_error_data( $error->get_error_code() );
		$http_status = is_array( $data ) ? (int) ( $data['http_status'] ?? 0 ) : 0;

		return $http_status === 400 || $http_status === 401 || $http_status === 403;
	}
}
