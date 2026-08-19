<?php

namespace Sugar_Calendar\Integrations\OAuthRelay\Credits;

use Sugar_Calendar\Vendor\ProductApi\Auth\HMACAuthStrategy;
use Sugar_Calendar\Vendor\ProductApi\Auth\SiteRegistration;
use Sugar_Calendar\Vendor\ProductApi\Context;
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
				->get( '/integrations/v1/' . $this->get_license_type() . '/credits' )
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

		$credits   = $response['credits'];
		$total     = (int) ( $credits['total'] ?? 0 );
		$used      = (int) ( $credits['used'] ?? 0 );
		$remaining = isset( $credits['remaining'] ) ? (int) $credits['remaining'] : max( 0, $total - $used );

		$resets_at = ( ! empty( $credits['resets_at'] ) && strtotime( $credits['resets_at'] ) !== false )
			? sanitize_text_field( $credits['resets_at'] )
			: null;

		// Sanitize the relay-supplied plan slug on intake (defense-in-depth):
		// it is never output today, but sanitize_key() keeps it a safe slug if a
		// future consumer ever echoes it.
		$plan = isset( $response['plan'] ) ? sanitize_key( $response['plan'] ) : 'lite';

		$usage = $this->format_usage( $response['usage'] ?? [], $total );

		return [
			'plan'         => $plan,
			'total'        => $total,
			'used'         => $used,
			'remaining'    => $remaining,
			'resets_at'    => $resets_at,
			'reset_date'   => $resets_at ? $this->format_reset_date( $resets_at ) : '',
			'percentage'   => $total > 0 ? min( (int) round( ( $used / $total ) * 100 ), 100 ) : 0,
			'is_maxed'     => $remaining <= 0 && $total > 0,
			'show_upgrade' => $plan !== 'elite',
			'usage'        => $usage,
		];
	}

	/**
	 * Format per-integration usage into rows with percentages.
	 *
	 * The relay sends `usage` as a map keyed by integration slug
	 * (`{"zoom":1}`) — NOT a list of row objects. Iterate the slug => count
	 * pairs directly (mirrors the relay's CreditsController + Bookings).
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $usage Raw usage map (slug => spent count).
	 * @param int   $total Total credit limit, for the percentage.
	 *
	 * @return array[] Each: slug, count, percentage, index. Highest usage first.
	 */
	private function format_usage( $usage, int $total ): array {

		if ( empty( $usage ) || ! is_array( $usage ) || $total <= 0 ) {
			return [];
		}

		$formatted = [];

		foreach ( $usage as $slug => $count ) {
			$count = (int) $count;

			if ( $count <= 0 ) {
				continue;
			}

			$formatted[] = [
				'slug'       => sanitize_key( $slug ),
				'count'      => $count,
				'percentage' => min( (int) round( ( $count / $total ) * 100 ), 100 ),
			];
		}

		// Highest usage first.
		usort(
			$formatted,
			static function ( $a, $b ) {

				return $b['count'] <=> $a['count'];
			}
		);

		return $this->assign_color_indexes( $formatted );
	}

	/**
	 * Stamp each usage row with its stable palette index.
	 *
	 * A slug keeps the same index as the integration set grows: a new slug
	 * takes `max(existing) + 1` (first slug -> 0), persisted to an option so the
	 * color is stable across requests. The presenter maps the index to a hex
	 * color. The option write happens here (fetch/format time), not in the
	 * presenter, so a render never triggers a DB write.
	 *
	 * @since 3.12.0
	 *
	 * @param array[] $rows Usage rows (each with a `slug`).
	 *
	 * @return array[] The rows with an added integer `index`.
	 */
	private function assign_color_indexes( array $rows ): array {

		$map = get_option( self::COLOR_MAP_OPTION, [] );

		// Keep only int values: a corrupted option must not break max().
		$map     = is_array( $map ) ? array_filter( $map, 'is_int' ) : [];
		$changed = false;

		foreach ( $rows as $row ) {
			if ( ! isset( $map[ $row['slug'] ] ) ) {
				$map[ $row['slug'] ] = ! empty( $map ) ? max( $map ) + 1 : 0;
				$changed             = true;
			}
		}

		if ( $changed ) {
			update_option( self::COLOR_MAP_OPTION, $map, false );
		}

		foreach ( $rows as &$row ) {
			$row['index'] = $map[ $row['slug'] ] ?? 0;
		}

		unset( $row );

		return $rows;
	}

	/**
	 * Format an ISO reset date for display, or '' when unparseable.
	 *
	 * @since 3.12.0
	 *
	 * @param string $resets_at Raw reset date.
	 *
	 * @return string
	 */
	private function format_reset_date( string $resets_at ): string {

		$ts = strtotime( $resets_at );

		if ( $ts === false ) {
			return '';
		}

		$formatted = wp_date( get_option( 'date_format' ), $ts );

		return $formatted !== false ? $formatted : '';
	}

	/**
	 * Effective license tier for credits routing. Guarded for Lite.
	 *
	 * @since 3.12.0
	 *
	 * @return string 'pro' or 'lite'.
	 */
	private function get_license_type(): string {

		$context = $this->safe_get( Context::class );

		if ( $context === null ) {
			return 'lite';
		}

		return ( $context->is_pro() && $context->is_license_valid() ) ? 'pro' : 'lite';
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

		return self::TRANSIENT_KEY . '_' . $this->get_license_type();
	}
}
