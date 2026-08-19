<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Throwable;

/**
 * Mints the pending response rows and the token that authorises writing to them.
 *
 * Rows exist from the moment the order or RSVP does, not from the moment the
 * visitor submits, so abandonment is detectable even if the modal never
 * renders. Host-agnostic: the caller resolves which respondents apply and
 * supplies the attendee_key -> attendee_id mapping (see TicketingCheckout::persist()).
 *
 * @since 3.13.0
 */
class PendingRows {

	/**
	 * The shape every consumer of a stored token must agree on.
	 *
	 * Lowercase hex only, anchored with /D against a trailing newline. Lowercase
	 * because the token column's collation is case-insensitive, so an uppercase
	 * variant would match in SQL and then fail hash_equals().
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const TOKEN_PATTERN = '/^[0-9a-f]{32}$/D';

	/**
	 * Whether a stored token is one the submit endpoint will accept.
	 *
	 * Every place that reuses or prints a stored token must ask this rather than
	 * checking non-empty. A malformed token printed into data-token would produce
	 * a form the endpoint rejects on every attempt, inside a modal with no close
	 * button, backdrop click, or Escape.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $token Candidate token.
	 *
	 * @return bool
	 */
	public static function is_valid_token( $token ) {

		return is_string( $token ) && preg_match( self::TOKEN_PATTERN, $token ) === 1;
	}

	/**
	 * Create the pending rows for one context and return their shared token.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id    Sugar Calendar event id.
	 * @param string $context     Either 'order' or 'rsvp'.
	 * @param int    $context_id  Order id or RSVP id.
	 * @param array  $respondents Each entry: [ 'attendee_key' => 'main'|'a{n}', 'attendee_id' => int|null ].
	 * @param string $token       Existing token to reuse; empty mints a new one.
	 *
	 * @return string The 32-character token, or '' when nothing was minted.
	 */
	public static function mint( $event_id, $context, $context_id, array $respondents, $token = '' ) {

		$event_id   = (int) $event_id;
		$context    = (string) $context;
		$context_id = (int) $context_id;

		if ( $event_id <= 0 || $context === '' || $context_id <= 0 || $respondents === [] ) {
			return '';
		}

		$token = (string) $token;

		if ( $token === '' ) {
			// This runs after the charge already happened (TicketingCheckout::mint_pending_rows()),
			// so a random_bytes() failure must degrade to "mint nothing" rather than
			// throw and block the receipt email/redirect. The caller records the loss.
			try {
				$token = bin2hex( random_bytes( 16 ) );
			} catch ( Throwable $e ) {
				return '';
			}
		}

		$rows = [];

		foreach ( $respondents as $respondent ) {

			$key = isset( $respondent['attendee_key'] ) ? (string) $respondent['attendee_key'] : '';

			// The same shape guard the rendered form and the gate use. A malformed
			// key here would break the endpoint's row matching later, silently.
			if ( ! preg_match( AnswerRequest::KEY_PATTERN, $key ) ) {
				continue;
			}

			$attendee_id = isset( $respondent['attendee_id'] ) && $respondent['attendee_id'] !== null
				? (int) $respondent['attendee_id']
				: null;

			$rows[] = [
				'event_id'     => $event_id,
				'context'      => $context,
				'context_id'   => $context_id,
				'attendee_key' => $key,
				'attendee_id'  => $attendee_id,
				'answers'      => [],
				'status'       => 'pending',
				'token'        => $token,
			];
		}

		if ( $rows === [] ) {
			return '';
		}

		ResponsePersister::persist( $rows );

		return $token;
	}
}
