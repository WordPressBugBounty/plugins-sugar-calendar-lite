<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

/**
 * A per-browser note that this browser just completed a before-checkout registration.
 *
 * Both hosts end their checkout with a full page load — a redirect to the receipt for
 * ticketing, a reload of the event page for RSVP — so the confirmation has to be
 * raised on the page the visitor lands on. A cookie rather than a transient, which
 * would raise it for every visitor of that event. It authorises nothing, and stays
 * readable by scripts because after.js is what consumes it: both landing seams run
 * mid-body, with the headers already sent.
 *
 * @since 3.13.0
 */
class CompletionFlash {

	/**
	 * Cookie name prefix; the event id completes it.
	 *
	 * @since 3.13.0
	 */
	const COOKIE_PREFIX = 'sc_regform_done_';

	/**
	 * How long the note lasts, in seconds.
	 *
	 * Long enough to survive a redirect through an off-site payment page, short
	 * enough that a browser which never ran the consuming script forgets it.
	 *
	 * @since 3.13.0
	 */
	const TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Note that this browser has just completed an event's registration.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id The Sugar Calendar event id.
	 *
	 * @return bool Whether the note was set.
	 */
	public static function set( $event_id ) {

		$event_id = (int) $event_id;

		if ( $event_id <= 0 || headers_sent() ) {
			return false;
		}

		setcookie( self::cookie_name( $event_id ), '1', self::cookie_options() );

		return true;
	}

	/**
	 * Whether this request carries the note for an event.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id The Sugar Calendar event id.
	 *
	 * @return bool
	 */
	public static function has( $event_id ) {

		$event_id = (int) $event_id;

		return $event_id > 0 && isset( $_COOKIE[ self::cookie_name( $event_id ) ] );
	}

	/**
	 * An event's cookie name.
	 *
	 * Public because the hosts hand it to after.js, which deletes the note.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id The Sugar Calendar event id.
	 *
	 * @return string
	 */
	public static function cookie_name( $event_id ) {

		return self::COOKIE_PREFIX . (int) $event_id;
	}

	/**
	 * The cookie's options.
	 *
	 * Site root so the deleting script needs no subdirectory path, and Lax so the
	 * note survives arriving back from an off-site payment page.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private static function cookie_options() {

		return [
			'expires'  => time() + self::TTL,
			'path'     => '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		];
	}
}
