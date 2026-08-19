<?php

namespace Sugar_Calendar\Features\RegistrationForm;

/**
 * Per-IP request throttle for the unauthenticated registration endpoint.
 *
 * A port of sc-rsvp's RateLimiter (core cannot depend on the add-on). Fixed-window
 * transient counter keyed on (action, client IP), storing the window's start time
 * alongside the count so the transient's TTL can be set to the time actually left
 * in the window. Bounds database load rather than defending against token
 * guessing; design rationale in .claude/rules/features/rsvp-rate-limiting.md.
 *
 * @since 3.13.0
 */
class RateLimiter {

	/**
	 * Rate-limited action: after-checkout registration submit.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ACTION_SUBMIT = 'submit';

	/**
	 * Rate-limited action: the hard ceiling on submit requests of any kind.
	 *
	 * The companion to ACTION_SUBMIT. See SubmitEndpoint::handle() for why the
	 * endpoint needs two budgets rather than one.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ACTION_SUBMIT_CEILING = 'submit_ceiling';

	/**
	 * Rate-limited action: a resume-link GET whose token resolved to nothing.
	 *
	 * Spent only by misses (a well-shaped token matching no rows), not ordinary
	 * views; see TokenResume::decide() for the shared-NAT lockout this avoids.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ACTION_RESUME = 'resume';

	/**
	 * Rate-limited action: the hard ceiling on resume-link GETs of any kind.
	 *
	 * The companion to ACTION_RESUME: this bounds every lookup an IP gets, while
	 * ACTION_RESUME bounds only the misses.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ACTION_RESUME_CEILING = 'resume_ceiling';

	/**
	 * Default per-window request budget, per action.
	 *
	 * ACTION_SUBMIT is looser than the RSVP endpoints' 5, since a shared-NAT
	 * office finishing the same event's forms must not collide. Each *_CEILING
	 * is spent by every request rather than only genuine attempts, so it sits
	 * far above any plausible legitimate burst; the RESUME pair leaves even more
	 * headroom, since its ceiling is spent by ordinary link reloads.
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	const DEFAULT_LIMITS = [
		self::ACTION_SUBMIT         => 10,
		self::ACTION_SUBMIT_CEILING => 60,
		self::ACTION_RESUME         => 30,
		self::ACTION_RESUME_CEILING => 120,
	];

	/**
	 * Record one attempt for $action from the current client and report whether
	 * it is within budget.
	 *
	 * Fails open on a missing IP or storage anomaly rather than block a
	 * legitimate visitor; the caller is responsible for failing closed
	 * (rejecting before any write) when this returns false.
	 *
	 * @since 3.13.0
	 *
	 * @param string $action One of the ACTION_* constants.
	 *
	 * @return bool True if allowed (and recorded); false if throttled.
	 */
	public static function attempt( $action ) {

		/**
		 * Filters whether the registration rate limiter is active for an action.
		 *
		 * Return false to disable the throttle (e.g. on a site that rate-limits at
		 * the edge and wants to avoid per-IP NAT false positives).
		 *
		 * @since 3.13.0
		 *
		 * @param bool   $enabled Whether the limiter runs. Default true.
		 * @param string $action  The RateLimiter ACTION_* being checked.
		 */
		$enabled = (bool) apply_filters( 'sugar_calendar_registration_rate_limiter_enabled', true, $action ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( ! $enabled ) {
			return true;
		}

		$ip = self::get_client_ip( $action );

		// No resolvable client IP: fail open rather than collapse every such
		// caller onto one shared bucket.
		if ( $ip === '' ) {
			return true;
		}

		$default_limit = self::DEFAULT_LIMITS[ $action ] ?? 10;

		/**
		 * Filters the per-window request budget for an action.
		 *
		 * The escape hatch for legitimately high-traffic events. This is a security
		 * control, not a UX knob — raising it re-opens the abuse surface
		 * proportionally.
		 *
		 * @since 3.13.0
		 *
		 * @param int    $limit  Max allowed attempts per window.
		 * @param string $action The RateLimiter ACTION_* being checked.
		 */
		$limit = (int) apply_filters( 'sugar_calendar_registration_rate_limiter_limit', $default_limit, $action ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		/**
		 * Filters the rate-limit window length, in seconds, for an action.
		 *
		 * @since 3.13.0
		 *
		 * @param int    $window Window length in seconds. Default MINUTE_IN_SECONDS.
		 * @param string $action The RateLimiter ACTION_* being checked.
		 */
		$window = (int) apply_filters( 'sugar_calendar_registration_rate_limiter_window', MINUTE_IN_SECONDS, $action ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		// A non-positive limit or window disables throttling for this action.
		if ( $limit < 1 || $window < 1 ) {
			return true;
		}

		$key                           = 'sc_regform_rl_' . $action . '_' . md5( $ip );
		[ $start, $count, $remaining ] = self::current_window( get_transient( $key ), $window );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient(
			$key,
			[
				'start' => $start,
				'count' => $count + 1,
			],
			$remaining
		);

		return true;
	}

	/**
	 * Resolve the current window's start time, attempt count, and remaining
	 * seconds from a stored transient value.
	 *
	 * A transient's remaining TTL cannot be read back through any public API, so
	 * the start time is stored alongside the count and the remaining seconds are
	 * computed here.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $stored The raw transient value: `false` on a cache miss, a
	 *                      legacy bare int (this class's pre-3.13.0 shape), or
	 *                      anything a hand-written/seeded row could contain.
	 * @param int   $window Window length in seconds.
	 *
	 * @return array{0: int, 1: int, 2: int} Window start time, attempt count, and
	 *                                       remaining seconds in that window.
	 */
	private static function current_window( $stored, $window ) {

		// A malformed or legacy value must fail open rather than block: treat it
		// as no prior attempts and start a fresh window.
		if ( is_array( $stored ) && isset( $stored['start'], $stored['count'] ) && is_int( $stored['start'] ) && is_int( $stored['count'] ) ) {
			$start = $stored['start'];
			$count = $stored['count'];
		} else {
			$start = time();
			$count = 0;
		}

		$remaining = $window - ( time() - $start );

		// The window has elapsed (or $start is bogus): begin a new one. Ordinarily
		// the transient itself expires by then; this only matters for a stale value.
		if ( $remaining <= 0 ) {
			$start     = time();
			$count     = 0;
			$remaining = $window;
		}

		return [ $start, $count, $remaining ];
	}

	/**
	 * Resolve the client IP the limiter keys on.
	 *
	 * REMOTE_ADDR only, by design: X-Forwarded-For is client-supplied and
	 * spoofable, letting an attacker rotate keys to bypass the budget. Sites
	 * behind a trusted reverse proxy may filter this to parse it themselves.
	 *
	 * @since 3.13.0
	 *
	 * @param string $action The RateLimiter ACTION_* being checked.
	 *
	 * @return string The client IP, or '' when none is resolvable.
	 */
	private static function get_client_ip( $action ) {

		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		/**
		 * Filters the client IP the registration rate limiter keys on.
		 *
		 * Defaults to REMOTE_ADDR. See get_client_ip() for why X-Forwarded-For is
		 * not trusted by default.
		 *
		 * @since 3.13.0
		 *
		 * @param string $ip     The resolved client IP (REMOTE_ADDR by default).
		 * @param string $action The RateLimiter ACTION_* being checked.
		 */
		$ip = apply_filters( 'sugar_calendar_registration_rate_limiter_client_ip', $ip, $action ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return is_string( $ip ) ? trim( $ip ) : '';
	}
}
