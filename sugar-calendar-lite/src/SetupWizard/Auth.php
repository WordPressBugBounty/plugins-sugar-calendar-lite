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
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const TRANSIENT = 'sugar_calendar_setup_wizard_token';

	/**
	 * Get a new token or the existing one.
	 *
	 * @since 3.7.0
	 */
	public function get_token() {

		$token        = get_transient( self::TRANSIENT );
		$token        = ! empty( $token ) ? $token : hash( 'sha512', wp_rand() );
		$hashed_token = hash_hmac( 'sha512', $token, wp_salt() );

		set_transient( self::TRANSIENT, $token, HOUR_IN_SECONDS );

		return $hashed_token;
	}

	/**
	 * Refresh the token.
	 *
	 * @since 3.7.0
	 */
	public function refresh_token() {

		$token = get_transient( self::TRANSIENT );

		if ( empty( $token ) ) {
			return;
		}

		set_transient( self::TRANSIENT, $token, HOUR_IN_SECONDS );
	}

	/**
	 * Verify the token.
	 *
	 * @since 3.7.0
	 *
	 * @param string $hashed_token Hashed token.
	 *
	 * @return bool
	 */
	public function verify_token( $hashed_token ) {

		$token = get_transient( self::TRANSIENT );

		if ( hash_hmac( 'sha512', $token, wp_salt() ) !== $hashed_token ) {
			return false;
		}

		return true;
	}
}
