<?php
/**
 * Signup-mode assembly for the event write abilities.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * SignupModes — core owns one `signup` property and the modes declare themselves.
 *
 * The declaration is a map (slug => detail property) rather than a list so core can reject a
 * detail object belonging to a mode the caller did not choose without naming any add-on. A
 * single-slot enum is also what makes "tickets or RSVP, never both" expressible in the schema:
 * the event editor's failure mode when it receives both is to turn both off.
 *
 * @since 3.14.0
 */
class SignupModes {

	/**
	 * The always-available mode.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const NONE = 'none';

	/**
	 * Declared modes, mode slug => detail property name ('' when the mode has no detail object).
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function available(): array {

		/**
		 * Filters the signup modes the event write abilities offer.
		 *
		 * Must be hooked so it runs during `wp_abilities_api_init`: a mode declared later is
		 * invisible to callers, because the schema is captured at registration.
		 *
		 * @since 3.14.0
		 *
		 * @param array $modes Map of mode slug => detail property name.
		 */
		$modes = apply_filters( 'sugar_calendar_integrations_abilities_signup_modes', [] );

		if ( ! is_array( $modes ) ) {
			return [];
		}

		$clean = [];

		foreach ( $modes as $slug => $property ) {

			$slug = sanitize_key( (string) $slug );

			// `none` is core's own value and cannot be claimed by a contributor.
			if ( $slug === '' || $slug === self::NONE ) {
				continue;
			}

			$clean[ $slug ] = is_string( $property ) ? $property : '';
		}

		return $clean;
	}

	/**
	 * The enum for the `signup` property.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function enum(): array {

		return array_merge( [ self::NONE ], array_keys( self::available() ) );
	}

	/**
	 * Per-mode registration totals for one window.
	 *
	 * Contributors return raw numbers for the window they are handed; core owns the
	 * sums, the previous-window call and the percentages. A mode that never declared
	 * itself through `available()` cannot contribute.
	 *
	 * @since 3.14.0
	 *
	 * @param string $start Window start, UTC 'Y-m-d H:i:s', inclusive.
	 * @param string $end   Window end, UTC 'Y-m-d H:i:s', exclusive.
	 *
	 * @return array Map of mode slug => [ 'count' => int, 'revenue' => float|null, 'currency' => string|null ].
	 */
	public static function stats( string $start, string $end ): array {

		/**
		 * Filters the per-mode registration totals for a window.
		 *
		 * @since 3.14.0
		 *
		 * @param array  $stats Map of mode slug => [ count, revenue, currency ].
		 * @param string $start Window start, UTC 'Y-m-d H:i:s', inclusive.
		 * @param string $end   Window end, UTC 'Y-m-d H:i:s', exclusive.
		 */
		$stats = apply_filters( 'sugar_calendar_integrations_abilities_signup_modes_stats', [], $start, $end );

		return self::clean_stats( $stats );
	}

	/**
	 * Per-mode registration totals for one window, counted only for the given events.
	 *
	 * Its own filter, not a fourth `stats()` argument: a provider that hasn't hooked it is
	 * reported as uncounted instead of answering with its site-wide totals.
	 *
	 * @since 3.15.0
	 *
	 * @param string $start     Window start, UTC 'Y-m-d H:i:s', inclusive.
	 * @param string $end       Window end, UTC 'Y-m-d H:i:s', exclusive.
	 * @param int[]  $event_ids Sugar Calendar event ids. May be empty: a contributor returns zeros.
	 *
	 * @return array Map of mode slug => [ 'count' => int, 'revenue' => float|null, 'currency' => string|null ].
	 */
	public static function scoped_stats( string $start, string $end, array $event_ids ): array {

		/**
		 * Filters the per-mode registration totals for a window, counted only for the given events.
		 *
		 * @since 3.15.0
		 *
		 * @param array  $stats     Map of mode slug => [ count, revenue, currency ].
		 * @param string $start     Window start, UTC 'Y-m-d H:i:s', inclusive.
		 * @param string $end       Window end, UTC 'Y-m-d H:i:s', exclusive.
		 * @param int[]  $event_ids Sugar Calendar event ids. A series' parent id covers all its dates.
		 */
		$stats = apply_filters( 'sugar_calendar_integrations_abilities_signup_modes_scoped_stats', [], $start, $end, $event_ids );

		return self::clean_stats( $stats );
	}

	/**
	 * Keep only declared modes' contributions, each in the shape callers rely on.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $stats Filtered map of mode slug => contribution.
	 *
	 * @return array
	 */
	private static function clean_stats( $stats ): array {

		if ( ! is_array( $stats ) ) {
			return [];
		}

		$known = self::available();
		$clean = [];

		foreach ( $stats as $slug => $entry ) {

			$slug = sanitize_key( (string) $slug );

			if ( ! isset( $known[ $slug ] ) || ! is_array( $entry ) ) {
				continue;
			}

			$clean[ $slug ] = self::sanitize_stat_entry( $entry );
		}

		return $clean;
	}

	/**
	 * Coerce one mode's contributed totals into the shape callers can rely on.
	 *
	 * A null revenue means the mode has no revenue concept at all, which is not the
	 * same as earning nothing, so a non-numeric contribution stays null rather than
	 * collapsing to 0.0 and dragging a currency into the aggregate's total.
	 *
	 * @since 3.14.0
	 *
	 * @param array $entry One mode's contribution.
	 *
	 * @return array
	 */
	private static function sanitize_stat_entry( array $entry ): array {

		$revenue  = $entry['revenue'] ?? null;
		$currency = $entry['currency'] ?? null;

		return [
			'count'    => absint( $entry['count'] ?? 0 ),
			'revenue'  => is_numeric( $revenue ) ? round( (float) $revenue, 2 ) : null,
			'currency' => is_string( $currency ) && $currency !== '' ? sanitize_text_field( $currency ) : null,
		];
	}

	/**
	 * Find a detail object that belongs to a mode the caller did not choose.
	 *
	 * @since 3.14.0
	 *
	 * @param array $input Raw ability input.
	 *
	 * @return string Offending property name, or an empty string when the input is coherent.
	 */
	public static function mismatch( array $input ): string {

		$chosen = isset( $input['signup'] ) ? (string) $input['signup'] : '';

		// No early return on an absent/empty $chosen: a detail object sent with no discriminator
		// is itself a mismatch, and the loop below already treats it as belonging to no mode.
		foreach ( self::available() as $slug => $property ) {

			if ( $property === '' || $slug === $chosen ) {
				continue;
			}

			if ( isset( $input[ $property ] ) ) {
				return $property;
			}
		}

		return '';
	}

	/**
	 * The mode whose detail object the input carries, whatever `signup` says.
	 *
	 * @since 3.15.0
	 *
	 * @param array $input Raw ability input.
	 *
	 * @return string Mode slug when exactly one mode's detail property is present, else an empty string.
	 */
	public static function detail_mode( array $input ): string {

		$matches = [];

		foreach ( self::available() as $slug => $property ) {

			if ( $property !== '' && isset( $input[ $property ] ) ) {
				$matches[] = $slug;
			}
		}

		// Two detail objects conflict, so there is nothing safe to infer; the
		// shared mismatch check reports them.
		return count( $matches ) === 1 ? $matches[0] : '';
	}

	/**
	 * The mode an event currently uses.
	 *
	 * @since 3.14.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 *
	 * @return string
	 */
	public static function current( int $event_id ): string {

		/**
		 * Filters the signup mode an event currently uses.
		 *
		 * A contributor returns its own slug when its mode is enabled for this event, and the
		 * incoming value otherwise.
		 *
		 * @since 3.14.0
		 *
		 * @param string $mode     Mode resolved so far.
		 * @param int    $event_id Sugar Calendar event id.
		 */
		$mode = apply_filters( 'sugar_calendar_integrations_abilities_signup_modes_current', self::NONE, $event_id );

		return ( is_string( $mode ) && $mode !== '' ) ? $mode : self::NONE;
	}

	/**
	 * Why a mode cannot be switched away from, if it cannot.
	 *
	 * @since 3.14.0
	 *
	 * @param string $mode     Mode to test.
	 * @param int    $event_id Sugar Calendar event id.
	 *
	 * @return string Reason to relay to the caller, or an empty string when free to switch.
	 */
	public static function in_use_reason( string $mode, int $event_id ): string {

		if ( $mode === '' || $mode === self::NONE ) {
			return '';
		}

		/**
		 * Filters whether a signup mode holds data that a switch would orphan.
		 *
		 * Return `false` to allow the switch, or a human-readable reason to refuse it. Only the
		 * mode's owner knows what counts, so only it should answer.
		 *
		 * @since 3.14.0
		 *
		 * @param bool|string $reason   False so far, or a reason to refuse.
		 * @param string      $mode     Mode being switched away from.
		 * @param int         $event_id Sugar Calendar event id.
		 */
		$reason = apply_filters( 'sugar_calendar_integrations_abilities_signup_modes_in_use', false, $mode, $event_id );

		return is_string( $reason ) ? $reason : '';
	}
}
