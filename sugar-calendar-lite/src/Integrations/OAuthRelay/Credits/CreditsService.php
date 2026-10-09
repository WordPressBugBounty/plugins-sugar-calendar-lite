<?php

namespace Sugar_Calendar\Integrations\OAuthRelay\Credits;

use Sugar_Calendar\Integrations\OAuthRelay\RelayLicenseType;
use Sugar_Calendar\Vendor\ProductApi\Auth\HMACAuthStrategy;
use Sugar_Calendar\Vendor\ProductApi\Auth\SiteRegistration;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;

/**
 * Integration credits service.
 *
 * Reads aggregate credit usage from the Product API relay
 * (`/integrations/v1/{tier}/credits`), formats it, and caches it. Credit
 * accounting is server-side — the plugin never tracks per-call cost.
 *
 * Two read modes, deliberately split by latency:
 *   - get_credits()        — fresh (API-first, cache fallback); fires the
 *                            low-credit alert. Used ONLY by the Settings panel.
 *   - get_cached_credits()
 *     / is_out_of_credits() — transient-only, no HTTP; used by the gate
 *                            (is_available / editor / save). Fail-open.
 *
 * Defensive: every ProductApi access is wrapped so an unconfigured relay
 * (e.g. configure() failed) degrades to null — no panel, no gate, no alert —
 * instead of fataling. The relay is configured on BOTH editions (Pro in
 * includes/pro/Pro.php; Lite in Integrations\OAuthRelay\Loader), with the tier
 * resolving to 'lite' on free installs.
 *
 * @since 3.12.0
 */
class CreditsService {

	/**
	 * Base transient key (suffixed with the license tier).
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'sugar_calendar_integration_credits';

	/**
	 * Option storing the stable slug -> palette-index map.
	 *
	 * Assigned here (at fetch/format time) rather than in the presenter so the
	 * write happens at most once per fresh read, never from a view getter.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const COLOR_MAP_OPTION = 'sugar_calendar_integration_credit_colors';

	/**
	 * Cache duration (5 minutes).
	 *
	 * @since 3.12.0
	 *
	 * @var int
	 */
	const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Per-request memo. False = not yet resolved.
	 *
	 * @since 3.12.0
	 *
	 * @var array|null|false
	 */
	private $credits_memo = false;

	/**
	 * Fresh credits read (API-first, cache fallback). Fires the alert.
	 *
	 * Returns null (callers hide the UI / the gate fails open) when the site
	 * is unregistered, ProductApi is unconfigured (Lite), or the relay returns
	 * nothing usable and no cache exists.
	 *
	 * @since 3.12.0
	 *
	 * @return array|null
	 */
	public function get_credits(): ?array {

		if ( $this->credits_memo !== false ) {
			return $this->credits_memo;
		}

		// Under the E2E relay test mode the whole relay — including site
		// registration — is stubbed, so skip the registration gate and let
		// fetch_credits() resolve the seeded stub body. Mirrors the test-mode
		// short-circuits in OAuthRelayClient and Zoom::handle_connect().
		if ( ! ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) ) {
			$registration = $this->safe_get( SiteRegistration::class );

			if ( $registration === null || ! $registration->is_registered() ) {
				return $this->credits_memo = null;
			}
		}

		$fresh = $this->fetch_credits();

		if ( $fresh !== null ) {
			return $this->credits_memo = $fresh;
		}

		$cached = get_transient( $this->get_transient_key() );

		return $this->credits_memo = ( $cached !== false ? $cached : null );
	}

	/**
	 * Cached credits read (transient only, no HTTP).
	 *
	 * @since 3.12.0
	 *
	 * @return array|null
	 */
	public function get_cached_credits(): ?array {

		$cached = get_transient( $this->get_transient_key() );

		return $cached !== false ? $cached : null;
	}

	/**
	 * Whether the account is out of credits, from cached data only.
	 *
	 * Fail-open: a cold/missing cache returns false (the relay remains the
	 * ultimate enforcer), so the gate never blocks on absent data.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_out_of_credits(): bool {

		$credits = $this->get_cached_credits();

		return is_array( $credits ) && ! empty( $credits['is_maxed'] );
	}

	/**
	 * Fetch fresh credits, cache them, and fire the alert.
	 *
	 * @since 3.12.0
	 *
	 * @return array|null
	 */
	private function fetch_credits(): ?array {

		$body = $this->request_credits_body();

		if (
			! is_array( $body ) ||
			! isset( $body['credits'] ) ||
			empty( $body['credits']['total'] )
		) {
			return null;
		}

		$formatted = $this->format_response( $body );

		set_transient( $this->get_transient_key(), $formatted, self::CACHE_TTL );

		$email = apply_filters( 'sugar_calendar_credit_alert_email', new CreditAlertEmail() );

		if ( $email instanceof CreditAlertEmail ) {
			$email->maybe_send( $formatted );
		}

		return $formatted;
	}

	/**
	 * Get the raw relay response body (or the E2E stub under test mode).
	 *
	 * The test seam provides the relay BODY (not a fabricated success shape),
	 * so format_response()/the gate/the alert all run for real on it (Rule 12).
	 *
	 * @since 3.12.0
	 *
	 * @return array|null
	 */
	private function request_credits_body(): ?array {

		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) {
			/**
			 * Filter to stub the relay credits response body in E2E tests.
			 *
			 * @since 3.12.0
			 *
			 * @param mixed $body Default null; return the raw relay body array.
			 */
			$stub = apply_filters( 'sc_credits_test_response', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Internal test-mode hook seam (E2E only).

			return is_array( $stub ) ? $stub : null;
		}

		$hmac = $this->safe_get( HMACAuthStrategy::class );

		if ( $hmac === null ) {
			return null;
		}

		try {
			$response = ProductApi::client()
				->get( '/integrations/v1/' . RelayLicenseType::get() . '/credits' )
				->auth_strategy( $hmac )
				->send();
		} catch ( \Throwable $e ) {
			return null;
		}

		if ( is_wp_error( $response ) || ! $response->is_successful() ) {
			return null;
		}

		$body = $response->get_body();

		return is_array( $body ) ? $body : null;
	}

	/**
	 * Shape the relay body into the array the UI / gate / email consume.
	 *
	 * @since 3.12.0
	 *
	 * @param array $response Raw relay body.
	 *
	 * @return array
	 */
	private function format_response( array $response ): array {

		// Sanitize the relay-supplied plan slug on intake (defense-in-depth):
		// it is never output today, but sanitize_key() keeps it a safe slug if a
		// future consumer ever echoes it.
		$plan = isset( $response['plan'] ) ? sanitize_key( $response['plan'] ) : 'lite';

		$out = [ 'plan' => $plan ] + CreditsPayload::format( $response['credits'] );

		$out['show_upgrade'] = $plan !== 'elite';
		$out['usage']        = UsageBreakdown::rows( $response['usage'] ?? [], $out['total'], self::COLOR_MAP_OPTION );

		return $out;
	}

	/**
	 * Resolve a ProductApi service, returning null instead of throwing when the
	 * container isn't configured (Lite, or a misconfigured Pro install).
	 *
	 * Centralizes the try/catch every ProductApi::get() call site here needed —
	 * each used to write its own catch body for the same "container not
	 * configured" case.
	 *
	 * @since 3.12.0
	 *
	 * @param string $class Service class to resolve.
	 *
	 * @return object|null
	 */
	private function safe_get( string $class ) {

		try {
			return ProductApi::get( $class );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Transient key scoped by tier (prevents lite/pro cache poisoning).
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	private function get_transient_key(): string {

		return self::TRANSIENT_KEY . '_' . RelayLicenseType::get();
	}
}
