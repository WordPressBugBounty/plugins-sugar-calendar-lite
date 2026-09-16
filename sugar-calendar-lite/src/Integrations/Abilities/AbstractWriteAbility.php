<?php
/**
 * Base contract for a single sc-events write ability.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * AbstractWriteAbility — base for abilities that modify site data. Flips the
 * read-only annotations the parent hardcodes, keeps writes off the public MCP
 * surface, and provides the guard/idempotency helpers every write ability needs.
 *
 * @since 3.14.0
 */
abstract class AbstractWriteAbility extends AbstractAbility {

	/**
	 * Write calls allowed per user per rolling hour.
	 *
	 * Bounds a runaway agent loop. Deliberately generous: a human working
	 * through the Gia funnels will not approach it.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const WRITES_PER_HOUR = 60;

	/**
	 * Idempotency record lifetime, in seconds.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const IDEMPOTENCY_TTL = 900;

	/**
	 * Ability metadata for a write ability.
	 *
	 * `mcp.public` is false: Gia calls these from the admin over REST, and an
	 * external MCP client should not get event-writing tools by default.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	protected function meta(): array {

		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
			'mcp'          => [
				'public' => false,
			],
		];
	}

	/**
	 * Guard the call, then run the ability body.
	 *
	 * Final on purpose: the permission re-check and the rate-limit spend are
	 * load-bearing, and leaving `execute()` overridable meant every write
	 * ability had to remember to open with them. Subclasses implement `run()`.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	final public function execute( $input = null ) {

		$args  = $this->normalize_input( $input );
		$guard = $this->guard( $args );

		return is_wp_error( $guard ) ? $guard : $this->run( $args );
	}

	/**
	 * Ability body, reached only once the guard has passed.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args Normalized ability input.
	 *
	 * @return array|WP_Error
	 */
	abstract protected function run( array $args );

	/**
	 * Re-check permission and spend rate-limit budget before writing.
	 *
	 * The permission re-check is not redundant: `execute()` is a public method
	 * any plugin code can call directly, and core's `wp_ability_execute_pre`
	 * filter can short-circuit validation and the permission check entirely.
	 *
	 * Budget is spent on the attempt, before semantic validation, on purpose: a
	 * caller looping on malformed input is the runaway case worth bounding, and
	 * charging only successful writes would leave that loop unbounded.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|WP_Error
	 */
	protected function guard( $input ) {

		$permission = $this->check_permission( $input );

		if ( $permission !== true ) {
			return is_wp_error( $permission )
				? $permission
				: $this->forbidden( __( 'You do not have permission to do that.', 'sugar-calendar-lite' ) );
		}

		return $this->spend_rate_limit();
	}

	/**
	 * Spend one write from the current user's hourly budget.
	 *
	 * User-keyed, not IP-keyed: an IP-keyed limiter would let one office
	 * network lock out a colleague's admin.
	 *
	 * @since 3.14.0
	 *
	 * @return true|WP_Error
	 */
	private function spend_rate_limit() {

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return $this->forbidden( __( 'You must be logged in to do that.', 'sugar-calendar-lite' ) );
		}

		$key    = 'sc_ai_writes_' . $user_id;
		$window = (array) get_transient( $key );
		$start  = isset( $window['start'] ) ? (int) $window['start'] : 0;
		$count  = isset( $window['count'] ) ? (int) $window['count'] : 0;
		$now    = time();

		if ( $start <= 0 || ( $now - $start ) >= HOUR_IN_SECONDS ) {
			$start = $now;
			$count = 0;
		}

		if ( $count >= static::WRITES_PER_HOUR ) {
			$retry = ( $start + HOUR_IN_SECONDS ) - $now;

			return new WP_Error(
				'sc_events_rate_limited',
				__( 'Too many changes in a short time. Try again shortly.', 'sugar-calendar-lite' ),
				[
					'status'      => 429,
					'retry_after' => max( 1, $retry ),
				]
			);
		}

		set_transient(
			$key,
			[
				'start' => $start,
				'count' => $count + 1,
			],
			max( 1, ( $start + HOUR_IN_SECONDS ) - $now )
		);

		return true;
	}

	/**
	 * Resolve an idempotency key to an already-created event id.
	 *
	 * @since 3.14.0
	 *
	 * @param string $key Client-supplied key.
	 *
	 * @return int Event id, or 0 when the key is unknown.
	 */
	protected function idempotency_hit( string $key ): int {

		if ( $key === '' ) {
			return 0;
		}

		return (int) get_transient( $this->idempotency_key( $key ) );
	}

	/**
	 * Remember which event an idempotency key produced.
	 *
	 * @since 3.14.0
	 *
	 * @param string $key      Client-supplied key.
	 * @param int    $event_id Event id that was created.
	 *
	 * @return void
	 */
	protected function remember_idempotency( string $key, int $event_id ) {

		if ( $key === '' || $event_id <= 0 ) {
			return;
		}

		set_transient( $this->idempotency_key( $key ), $event_id, static::IDEMPOTENCY_TTL );
	}

	/**
	 * Build the user-scoped transient name for an idempotency key.
	 *
	 * The user id is part of the name so one user's key can never resolve to
	 * another user's event.
	 *
	 * @since 3.14.0
	 *
	 * @param string $key Client-supplied key.
	 *
	 * @return string
	 */
	private function idempotency_key( string $key ): string {

		return 'sc_ai_idem_' . get_current_user_id() . '_' . md5( $key );
	}

	/**
	 * Build the standard invalid-input (400) error.
	 *
	 * @since 3.14.0
	 *
	 * @param string $message Error message, naming the offending field.
	 *
	 * @return WP_Error
	 */
	protected function invalid_input( string $message ): WP_Error {

		return new WP_Error( 'sc_events_invalid_input', $message, [ 'status' => 400 ] );
	}

	/**
	 * Build the standard write-failure (500) error.
	 *
	 * @since 3.14.0
	 *
	 * @param string $message Error message.
	 *
	 * @return WP_Error
	 */
	protected function write_failed( string $message ): WP_Error {

		return new WP_Error( 'sc_events_write_failed', $message, [ 'status' => 500 ] );
	}

	/**
	 * Build the signup-in-use (409) error.
	 *
	 * Separate from `invalid_input()` because the input was well formed: the event's current
	 * state is what refuses, so the client should offer the editor rather than re-ask a field.
	 *
	 * @since 3.14.0
	 *
	 * @param string $reason Why the current mode cannot be switched away from.
	 *
	 * @return WP_Error
	 */
	protected function signup_in_use( string $reason ): WP_Error {

		return new WP_Error( 'sc_events_signup_in_use', $reason, [ 'status' => 409 ] );
	}

	/**
	 * Build the invalid-input error for a detail object that doesn't belong to the chosen
	 * signup mode, shared by the create and update abilities.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args Ability input to check.
	 *
	 * @return null|WP_Error Null when the input is coherent.
	 */
	protected function signup_mismatch_error( array $args ) {

		$mismatch = SignupModes::mismatch( $args );

		if ( $mismatch === '' ) {
			return null;
		}

		return $this->invalid_input(
			sprintf(
				/* translators: %s: input property name, for example ticketing. */
				__( 'The %s settings do not match the signup method you asked for. Send only the settings that belong to the method in signup.', 'sugar-calendar-lite' ),
				$mismatch
			)
		);
	}
}
