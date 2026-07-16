<?php

namespace Sugar_Calendar\Integrations\OAuthRelay\Webhooks;

use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\WebhookEventHandlerInterface;
use Sugar_Calendar\Vendor\ProductApi\Auth\AuthOptions;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Incoming webhook handler.
 *
 * Provider-agnostic REST endpoint that receives forwarded webhook events
 * from the Product API relay and dispatches them to the matching
 * WebhookEventHandlerInterface implementation via the capability registry.
 *
 * Ported from Bookings. SCE deviations (3a design §2):
 *   - REST namespace `sugar-calendar/v1` (matches the SetupWizard REST
 *     precedent; Bookings uses `scb/v1`).
 *   - A handler exception returns 200, not Bookings' 500: the signature was
 *     valid and the payload received — a deterministic handler bug plus
 *     relay retries is a storm that can never succeed. The failure is logged.
 *   - get_signing_secret() tolerates an unconfigured ProductApi container
 *     (the relay is configured on both editions, but configure() could fail) —
 *     an unauthenticated REST hit must never fatal.
 *
 * @since 3.12.0
 */
class IncomingWebhookHandler {

	/**
	 * REST API namespace.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'sugar-calendar/v1';

	/**
	 * Whether we are currently processing an incoming webhook.
	 *
	 * Read by EventMeetingManager so a change that originated at the
	 * provider never bounces back to the provider as another API call.
	 *
	 * @since 3.12.0
	 *
	 * @var bool
	 */
	private static $processing = false;

	/**
	 * Check if a webhook is currently being processed.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public static function is_processing(): bool {

		return self::$processing;
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 3.12.0
	 */
	public function register_routes() {

		register_rest_route(
			self::REST_NAMESPACE,
			'/webhooks/(?P<provider>[a-z-]+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_webhook' ],
				'permission_callback' => [ $this, 'verify_request' ],
				'args'                => [
					'provider' => [
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return $this->resolve_handler( (string) $value ) !== null;
						},
					],
				],
			]
		);
	}

	/**
	 * Verify the incoming webhook request via HMAC signature.
	 *
	 * The Product API relay signs forwarded webhook payloads with the
	 * site-wide signing_secret minted at site registration:
	 * HMAC-SHA256 of "{timestamp}.{body}".
	 *
	 * Note: WordPress runs permission_callback BEFORE arg validation, so a
	 * bad signature 401s even for an unknown provider slug.
	 *
	 * @since 3.12.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return bool|WP_Error True if valid, WP_Error (401) otherwise.
	 */
	public function verify_request( WP_REST_Request $request ) {

		$signature = $request->get_header( 'X-Signature' );
		$timestamp = $request->get_header( 'X-Timestamp' );

		if ( empty( $signature ) || empty( $timestamp ) ) {
			return new WP_Error( 'missing_signature', 'Missing signature headers.', [ 'status' => 401 ] );
		}

		// Reject requests older than 5 minutes (replay window).
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return new WP_Error( 'timestamp_expired', 'Request timestamp expired.', [ 'status' => 401 ] );
		}

		$signing_secret = $this->get_signing_secret();

		if ( empty( $signing_secret ) ) {
			return new WP_Error( 'not_registered', 'Site not registered.', [ 'status' => 401 ] );
		}

		$body     = $request->get_body();
		$expected = hash_hmac( 'sha256', "{$timestamp}.{$body}", $signing_secret );

		if ( ! hash_equals( $expected, $signature ) ) {
			error_log( '[SC Webhooks] invalid signature on incoming webhook.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return new WP_Error( 'invalid_signature', 'Invalid signature.', [ 'status' => 401 ] );
		}

		return true;
	}

	/**
	 * Handle an incoming webhook event.
	 *
	 * @since 3.12.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {

		$provider = (string) $request->get_param( 'provider' );
		$body     = $request->get_json_params();

		if ( empty( $body['event'] ) ) {
			return new WP_REST_Response( [ 'error' => 'Missing event type.' ], 400 );
		}

		$handler = $this->resolve_handler( $provider );

		// Defensive: WordPress's validate_callback prevents reaching here with
		// an unknown provider, but guard for direct test invocations.
		if ( ! $handler ) {
			return new WP_REST_Response( [ 'error' => 'Unsupported provider.' ], 400 );
		}

		self::$processing = true;

		try {
			$handler->handle_event( $body );
		} catch ( \Throwable $e ) {
			// 200 on purpose (3a design §2 call 4): acknowledging a received,
			// signature-valid payload is honest; a 500 would make the relay
			// retry a deterministic failure forever. Bookings 500s here.
			error_log( '[SC Webhooks] handler failed for ' . $provider . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		} finally {
			self::$processing = false;
		}

		return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
	}

	/**
	 * Resolve the webhook event handler for a provider.
	 *
	 * @since 3.12.0
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return WebhookEventHandlerInterface|null
	 */
	private function resolve_handler( string $provider ) {

		$handler = IntegrationCapabilityRegistry::instance()->find(
			WebhookEventHandlerInterface::class,
			$provider
		);

		return $handler instanceof WebhookEventHandlerInterface ? $handler : null;
	}

	/**
	 * Read the site-wide signing secret, tolerating an unconfigured ProductApi.
	 *
	 * @since 3.12.0
	 *
	 * @return string Empty string when unavailable.
	 */
	private function get_signing_secret(): string {

		try {
			return (string) ProductApi::get( AuthOptions::class )->get( 'signing_secret', '' );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Get the webhook URL for a provider.
	 *
	 * @since 3.12.0
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return string
	 */
	public static function get_webhook_url( string $provider ): string {

		return rest_url( self::REST_NAMESPACE . '/webhooks/' . $provider );
	}
}
