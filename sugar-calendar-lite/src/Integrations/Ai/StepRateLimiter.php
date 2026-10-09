<?php
/**
 * Per-user rate limit for Gia AI turn requests.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * A per-user rolling window, one window per scope: `/turn` uses the default and
 * `/usage` its own, so a cached read cannot spend the budget prompts need.
 * Separate from the write abilities' 60-writes-per-hour budget, which still
 * applies to the tool calls inside a turn. Not extracted into a shared limiter:
 * the existing one is private and its budget key is shared with event writes.
 *
 * @since 3.15.0
 */
class StepRateLimiter {

	const KEY_PREFIX = 'sc_ai_steps_';

	/**
	 * Requests allowed per window, per scope. A turn is at most 9 requests, so
	 * the default scope is about 13 turns per window.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_STEPS = 120;

	/**
	 * Window length in seconds.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const WINDOW = 600;

	/**
	 * Spend one request from the user's window.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $user_id User id.
	 * @param string $scope   Optional window suffix, so a route that costs no
	 *                        relay round keeps its own budget.
	 *
	 * @return true|WP_Error
	 */
	public function attempt( int $user_id, string $scope = '' ) {

		$key    = self::KEY_PREFIX . $user_id . ( $scope !== '' ? '_' . $scope : '' );
		$window = (array) get_transient( $key );
		$start  = isset( $window['start'] ) ? (int) $window['start'] : 0;
		$count  = isset( $window['count'] ) ? (int) $window['count'] : 0;
		$now    = time();

		if ( $start <= 0 || ( $now - $start ) >= self::WINDOW ) {
			$start = $now;
			$count = 0;
		}

		if ( $count >= self::MAX_STEPS ) {
			return new WP_Error(
				'sc_ai_rate_limited',
				__( 'Too many requests in a short time. Please wait a moment.', 'sugar-calendar-lite' ),
				[
					'status'      => 429,
					'retry_after' => max( 1, ( $start + self::WINDOW ) - $now ),
				]
			);
		}

		set_transient(
			$key,
			[
				'start' => $start,
				'count' => $count + 1,
			],
			max( 1, ( $start + self::WINDOW ) - $now )
		);

		return true;
	}
}
