<?php

namespace Sugar_Calendar\SetupWizard;

/**
 * Class Auth.
 *
 * @since 3.7.0
 */
class Auth {

	/**
	 * Token transient name.
	 *
	 * Stores an array: [ 'token' => string, 'user_id' => int ].
	 *
	 * @since 3.7.0
	 * @since 3.11.1 Storage shape changed from string to array.
	 *
	 * @var string
	 */
	const TRANSIENT = 'sugar_calendar_setup_wizard_token';

	/**
	 * Get a new wizard token, or refresh the existing one for the same user.
	 *
	 * The token is bound to the user it was issued to, so it carries identity
	 * across the cross-origin wizard request that has no auth cookies to
	 * fall back on. The caller is responsible for checking that the current
	 * user is permitted to launch the wizard before invoking this method.
	 *
	 * @since 3.7.0
	 *
	 * @return string Hashed token, or empty string if there is no current
	 *                user to bind the token to.
	 */
	public function get_token() {

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		$stored = get_transient( self::TRANSIENT );

		if (
			is_array( $stored )
			&& ! empty( $stored['token'] )
			&& (int) ( $stored['user_id'] ?? 0 ) === $user_id
		) {
			$raw = $stored['token'];
		} else {
			$raw = hash( 'sha512', wp_rand() );
		}

		set_transient(
			self::TRANSIENT,
			[
				'token'   => $raw,
				'user_id' => $user_id,
			],
			HOUR_IN_SECONDS
		);

		return hash_hmac( 'sha512', $raw, wp_salt() );
	}

	/**
	 * Refresh the rolling expiry of the existing token, preserving its
	 * bound user. Caller is expected to have already verified the token.
	 *
	 * @since 3.7.0
	 */
	public function refresh_token() {

		$stored = get_transient( self::TRANSIENT );

		if ( ! is_array( $stored ) || empty( $stored['token'] ) ) {
			return;
		}

		set_transient( self::TRANSIENT, $stored, HOUR_IN_SECONDS );
	}

	/**
	 * Verify a hashed wizard token. Returns the bound user ID on success
	 * so the caller can hydrate the request as that user (e.g. via
	 * wp_set_current_user) when needed.
	 *
	 * Pure verification — no request-state side effects.
	 *
	 * @since 3.7.0
	 *
	 * @param string $hashed_token The hashed token from the X-TOKEN header.
	 *
	 * @return int|false The bound user ID on success, false on failure.
	 */
	public function verify_token( $hashed_token ) {

		if ( ! is_string( $hashed_token ) || $hashed_token === '' ) {
			return false;
		}

		$stored = get_transient( self::TRANSIENT );

		if ( ! is_array( $stored ) || empty( $stored['token'] ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha512', $stored['token'], wp_salt() );

		if ( ! hash_equals( $expected, $hashed_token ) ) {
			return false;
		}

		$user_id = (int) ( $stored['user_id'] ?? 0 );

		if ( $user_id <= 0 ) {
			return false;
		}

		return $user_id;
	}
}
