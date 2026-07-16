<?php

namespace Sugar_Calendar\Integrations\Zoom;

use Sugar_Calendar\Integrations\OAuthRelay\AbstractOAuthConnection;

/**
 * Zoom-specific OAuth connection.
 *
 * Zoom access tokens expire after 1 hour. A 10-minute buffer ensures the
 * token is refreshed well before expiry, accounting for network latency
 * through the relay. Matches the Bookings implementation.
 *
 * @since 3.12.0
 */
class ZoomOAuthConnection extends AbstractOAuthConnection {

	/**
	 * Seconds before expiration to trigger refresh.
	 *
	 * @since 3.12.0
	 *
	 * @var int
	 */
	protected $expiration_buffer = 600;
}
