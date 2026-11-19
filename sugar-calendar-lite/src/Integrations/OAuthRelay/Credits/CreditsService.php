<?php

namespace Sugar_Calendar\Integrations\OAuthRelay\Credits;

use Sugar_Calendar\Integrations\IntegrationsDisabler;
use Sugar_Calendar\Integrations\OAuthRelay\RelayLicenseType;
use Sugar_Calendar\Vendor\ProductApi\Auth\HMACAuthStrategy;
use Sugar_Calendar\Vendor\ProductApi\Auth\SiteRegistration;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;

/**
 * Integration credits service.
 *
 * Reads aggregate credit usage from the Product API relay
 * (`/integrations/v1/{tier}/credits`), formats it, and stores the last read per
 * tier in an autoloaded option. Credit accounting is server-side: the plugin
 * never tracks per-call cost.
 *
 * Two read modes:
 *   - get_credits( $force ) fetches when forced or out of date, and fires the
 *     low-credit alert on success.
 *   - get_cached_credits() / is_out_of_credits() read the stored option, no HTTP.
 *     Display only: the relay refuses a create the account cannot pay for.
 *
 * Defensive: every ProductApi access is wrapped so an unconfigured relay
 * (e.g. configure() failed) degrades to null (no panel, no notice, no alert)
 * instead of fataling. The relay is configured on BOTH editions (Pro in
 * includes/pro/Pro.php; Lite in Integrations\OAuthRelay\Loader), with the tier
 * resolving to 'lite' on free installs.
 *
 * @since 3.12.0
 */
class CreditsService {

	/**
	 * Base option key for the stored read (suffixed with the license tier).
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	const OPTION_KEY = 'sugar_calendar_integration_credits';

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
	 * How long a successful read stays current for a non-forced call.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	const REFRESH_INTERVAL = DAY_IN_SECONDS;

	/**
	 * How long a non-forced call waits after the last fetch attempt, successful or not.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	const RETRY_INTERVAL = HOUR_IN_SECONDS;

	/**
	 * How long after a successful fetch the event editor acts on an out-of-credits read.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	const RECENT_READ_WINDOW = 5 * MINUTE_IN_SECONDS;

	/**
	 * Per-request memo. False = not yet resolved.
	 *
	 * @since 3.12.0
	 *
	 * @var array|null|false
	 */
	private $credits_memo = false;

	/**
	 * Credits read: the stored read, or a relay fetch when forced or out of date.
	 *
	 * Out of date: older than a day or past its reset date, with no fetch attempt in
	 * the last hour. Null when the site is unregistered, the relay is unconfigured,
	 * Lite's Disable Integrations switch is on, or nothing is stored yet.
	 *
	 * @since 3.12.0
	 * @since 3.18.0 Stores the read in an option, fetches only when forced or out of date, and never while Lite's Disable Integrations switch is on.
	 *
	 * @param bool $force Fetch whatever the stored read's age.
	 *
	 * @return array|null
	 */
	public function get_credits( bool $force = false ): ?array {

		if ( ! $force && $this->credits_memo !== false ) {
			return $this->credits_memo;
		}

		// The switch leaves the relay registration in place, so the registration check below does not stop the call.
		if ( IntegrationsDisabler::is_disabled() ) {
			return null;
		}

		if ( ! $this->is_registered() ) {
			// A stored read must not outlive the site's registration (a domain move, a staging clone).
			if ( get_option( $this->get_option_key(), null ) !== null ) {
				delete_option( $this->get_option_key() );
			}

			$this->credits_memo = null;

			return null;
		}

		$record = $this->get_record();
		$fresh  = ( $force || $this->needs_fetch( $record ) ) ? $this->fetch_credits( $record ) : null;

		$this->credits_memo = $fresh ?? $this->get_cached_credits();

		return $this->credits_memo;
	}

	/**
	 * The stored read for the current tier (no HTTP), or null once its reset date has passed: it
	 * then describes a finished period, so no surface shows it as current.
	 *
	 * Null while Lite's Disable Integrations switch is on: no fetch replaces the read then, and a
	 * Lite read never resets, so it would show as current for as long as the switch stays on.
	 *
	 * @since 3.12.0
	 * @since 3.18.0 Reads the stored option instead of the transient, drops a read past its reset date, and returns null while Lite's Disable Integrations switch is on.
	 *
	 * @return array|null
	 */
	public function get_cached_credits(): ?array {

		if ( IntegrationsDisabler::is_disabled() ) {
			return null;
		}

		$credits = $this->get_record()['credits'];

		return is_array( $credits ) && ! $this->has_reset_passed( $credits ) ? $credits : null;
	}

	/**
	 * Whether the stored read says the account is out of credits (no HTTP).
	 *
	 * A maxed read whose reset date has passed no longer counts: get_cached_credits() drops it.
	 *
	 * @since 3.12.0
	 * @since 3.18.0 Reads the stored option and ignores a read past its reset date.
	 *
	 * @return bool
	 */
	public function is_out_of_credits(): bool {

		$credits = $this->get_cached_credits();

		return is_array( $credits ) && ! empty( $credits['is_maxed'] );
	}

	/**
	 * Whether a read fetched in the last five minutes says the account is out of credits (no HTTP).
	 *
	 * The relay's create check reads the account balance, which this read never sees, so an
	 * older "out" could block a create the relay would accept.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function is_recently_out_of_credits(): bool {

		return $this->is_out_of_credits()
			&& time() - $this->get_record()['fetched_at'] < self::RECENT_READ_WINDOW;
	}

	/**
	 * Whether a non-forced read would reach the relay now (no HTTP).
	 *
	 * The Dashboard asks for a background refresh only then.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function is_refresh_due(): bool {

		return ! IntegrationsDisabler::is_disabled() && $this->is_registered() && $this->needs_fetch( $this->get_record() );
	}

	/**
	 * Fetch fresh credits, store them, and fire the alert.
	 *
	 * @since 3.12.0
	 * @since 3.18.0 Stores the read in the per-tier option instead of a transient.
	 *
	 * @param array $record The stored record. A failed fetch keeps its read and starts the retry wait.
	 *
	 * @return array|null Null when the fetch failed.
	 */
	private function fetch_credits( array $record ): ?array {

		$body = $this->request_credits_body();

		// No body, no credits or a zero total: not a read.
		if ( empty( $body['credits']['total'] ) ) {
			$this->save_record( array_merge( $record, [ 'attempted_at' => time() ] ) );

			return null;
		}

		$formatted = $this->format_response( $body );
		$now       = time();

		$this->save_record(
			[
				'credits'      => $formatted,
				'fetched_at'   => $now,
				'attempted_at' => $now,
			]
		);

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
	 * so format_response()/the stored read/the alert all run for real on it.
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
	 * Shape the relay body into the array the UI / notice / email consume.
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
	 * Whether a non-forced call should reach the relay.
	 *
	 * @since 3.18.0
	 *
	 * @param array $record Stored record from get_record().
	 *
	 * @return bool
	 */
	private function needs_fetch( array $record ): bool {

		$now = time();

		$is_current = $record['credits'] !== null
			&& $now - $record['fetched_at'] < self::REFRESH_INTERVAL
			&& ! $this->has_reset_passed( $record['credits'] );

		// Any attempt, not only a failure: a read already past its reset date would
		// otherwise refetch on every load.
		$is_backing_off = $record['attempted_at'] > 0
			&& $now - $record['attempted_at'] < self::RETRY_INTERVAL;

		return ! $is_current && ! $is_backing_off;
	}

	/**
	 * Whether a read's reset date has passed. A lifetime read never resets.
	 *
	 * @since 3.18.0
	 *
	 * @param array $credits Formatted credits.
	 *
	 * @return bool
	 */
	private function has_reset_passed( array $credits ): bool {

		if ( empty( $credits['resets_at'] ) ) {
			return false;
		}

		$resets_at = strtotime( $credits['resets_at'] );

		return $resets_at !== false && $resets_at <= time();
	}

	/**
	 * The stored record for the current tier, with every key present.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	private function get_record(): array {

		$record = get_option( $this->get_option_key(), [] );
		$record = is_array( $record ) ? $record : [];

		return [
			'credits'      => isset( $record['credits'] ) && is_array( $record['credits'] ) ? $record['credits'] : null,
			'fetched_at'   => (int) ( $record['fetched_at'] ?? 0 ),
			'attempted_at' => (int) ( $record['attempted_at'] ?? 0 ),
		];
	}

	/**
	 * Save the record for the current tier.
	 *
	 * Autoloaded, because the out-of-credits notice reads it on every admin page.
	 *
	 * @since 3.18.0
	 *
	 * @param array $record Record to store.
	 *
	 * @return void
	 */
	private function save_record( array $record ) {

		update_option( $this->get_option_key(), $record, true );
	}

	/**
	 * Option key scoped by tier, so a Lite read and a Pro read never mix.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	private function get_option_key(): string {

		return self::OPTION_KEY . '_' . RelayLicenseType::get();
	}

	/**
	 * Whether the site is registered with the relay (options only, no HTTP).
	 *
	 * The E2E relay test mode stubs the whole relay, registration included, so the
	 * site counts as registered. Mirrors the test-mode short-circuits in
	 * OAuthRelayClient and Zoom::handle_connect().
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	private function is_registered(): bool {

		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) {
			return true;
		}

		$registration = $this->safe_get( SiteRegistration::class );

		return $registration !== null && $registration->is_registered();
	}
}
