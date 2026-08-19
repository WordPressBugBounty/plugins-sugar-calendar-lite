<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

/**
 * Proof that this browser is the one that checked out.
 *
 * The receipt page is reachable by anyone holding its link, and the link travels
 * through emails, forwarded messages, history and access logs. That is an
 * acceptable credential for *reading* a receipt, which is what the ticketing
 * add-on designed it for, but not for *writing* the organiser's answers on the
 * attendees' behalf. So the receipt host asks for a second factor: a cookie set
 * on the checkout response itself.
 *
 * A visitor who lacks the cookie is not locked out of the feature — the order
 * receipt email carries a resume link whose token is its own 128-bit credential
 * (OrderEmailResumeLink, TokenResume). Cookie or emailed token; the receipt URL
 * alone is deliberately not enough.
 *
 * @since 3.13.0
 */
class CheckoutSession {

	/**
	 * Cookie name prefix; the order id completes it.
	 *
	 * Per order rather than one rolling cookie: a buyer can check out twice, and
	 * the second purchase must not silently revoke the first one's form.
	 *
	 * @since 3.13.0
	 */
	const COOKIE_PREFIX = 'sc_regform_order_';

	/**
	 * How long the proof lasts, in seconds.
	 *
	 * 72 hours, matching the ticketing receipt link's own default TTL
	 * (sc_et_receipt_link_ttl) — the cookie outliving the link it authorises
	 * would be a promise this class cannot keep.
	 *
	 * @since 3.13.0
	 */
	const DEFAULT_TTL = 72 * HOUR_IN_SECONDS;

	/**
	 * Record that this browser completed this order's checkout.
	 *
	 * Called from the checkout request, which is the buyer's own browser with the
	 * headers still open (sc_et_checkout_pre_redirect). $_COOKIE is written too,
	 * so a later seam in this same request agrees with the browser that will carry
	 * the cookie on the next one.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $order_id Order id.
	 * @param string $token    The token minted with the order's pending rows.
	 *
	 * @return bool Whether the proof was recorded.
	 */
	public static function remember( $order_id, $token ) {

		$order_id = (int) $order_id;

		if ( $order_id <= 0 || ! PendingRows::is_valid_token( $token ) ) {
			return false;
		}

		$name     = self::cookie_name( $order_id );
		$verifier = self::verifier( (string) $token );

		// Nothing can be sent once output has started. The buyer keeps their
		// receipt and their emailed resume link either way, so this degrades
		// rather than failing the checkout.
		if ( ! headers_sent() ) {
			setcookie( $name, $verifier, self::cookie_options() );
		}

		$_COOKIE[ $name ] = $verifier;

		return true;
	}

	/**
	 * Whether this request carries the proof for an order's token.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $order_id Order id.
	 * @param string $token    The token the host is about to print.
	 *
	 * @return bool
	 */
	public static function proves( $order_id, $token ) {

		$order_id = (int) $order_id;

		if ( $order_id <= 0 || ! PendingRows::is_valid_token( $token ) ) {
			return false;
		}

		$name = self::cookie_name( $order_id );

		if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			return false;
		}

		// Unslashed because WordPress adds slashes to $_COOKIE on load; the stored
		// value is hex, so neither a slash nor anything sanitize_text_field() strips
		// can be part of a genuine proof.
		$presented = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );

		return hash_equals( self::verifier( (string) $token ), $presented );
	}

	/**
	 * This order's cookie name.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return string
	 */
	private static function cookie_name( $order_id ) {

		return self::COOKIE_PREFIX . (int) $order_id;
	}

	/**
	 * The value stored in the cookie for a token.
	 *
	 * A keyed hash of the token, not the token itself: the cookie rides every
	 * request to this site, and the token is the write credential. Keyed with
	 * wp_salt() so the value is worthless on any other install, and so rotating
	 * the salts invalidates every outstanding proof.
	 *
	 * @since 3.13.0
	 *
	 * @param string $token The token.
	 *
	 * @return string
	 */
	private static function verifier( $token ) {

		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * The cookie's options.
	 *
	 * HttpOnly because no script needs to read it — after.js gets the token from
	 * the printed markup. Lax rather than Strict so the proof survives arriving
	 * back from an off-site payment page.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private static function cookie_options() {

		return [
			'expires'  => time() + self::ttl(),
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH !== '' ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		];
	}

	/**
	 * How long a proof lasts.
	 *
	 * @since 3.13.0
	 *
	 * @return int Seconds; never below one hour, so a filter cannot make the
	 *             receipt-page form unreachable by accident.
	 */
	private static function ttl() {

		/**
		 * Filter how long the checkout proof cookie lasts.
		 *
		 * @since 3.13.0
		 *
		 * @param int $ttl Seconds. Default 72 hours.
		 */
		$ttl = (int) apply_filters( 'sugar_calendar_registration_checkout_session_ttl', self::DEFAULT_TTL ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return max( HOUR_IN_SECONDS, $ttl );
	}
}
