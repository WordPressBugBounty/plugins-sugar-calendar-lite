<?php

namespace Sugar_Calendar\Features\RegistrationForm\Abandonment;

use Sugar_Calendar\Features\RegistrationForm\RespondentNaming;
use Sugar_Calendar_Rsvp\Model\Rsvp;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;

/**
 * Who receives a context's abandonment reminder.
 *
 * The only place in Track D that needs host knowledge. One class with a switch on
 * context rather than a host-adapter interface: there are two contexts, and a
 * two-case abstraction costs more than it explains (spec §3.4).
 *
 * Every sc-rsvp reference sits behind class_exists() — the add-on ships and updates
 * independently of core, so its absence must be an empty result rather than a fatal.
 *
 * @since 3.13.0
 */
class RecipientResolver {

	/**
	 * Resolve a context's reminder recipient.
	 *
	 * @since 3.13.0
	 *
	 * @param string $context    Context, either 'order' or 'rsvp'.
	 * @param int    $context_id Order id or RSVP post id.
	 *
	 * @return array [ 'email' => string, 'name' => string ], or [] when unresolvable.
	 */
	public static function for_context( $context, $context_id ) {

		$context_id = (int) $context_id;

		if ( $context_id <= 0 ) {
			return [];
		}

		if ( (string) $context === 'order' ) {
			return self::for_order( $context_id );
		}

		if ( (string) $context === 'rsvp' ) {
			return self::for_rsvp( $context_id );
		}

		return [];
	}

	/**
	 * The purchaser behind an order.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array
	 */
	private static function for_order( $order_id ) {

		$order = get_order( $order_id );

		if ( empty( $order ) || empty( $order->email ) ) {
			return [];
		}

		$email = sanitize_email( (string) $order->email );

		if ( ! is_email( $email ) ) {
			return [];
		}

		return [
			'email' => $email,
			'name'  => RespondentNaming::purchaser( $order ),
		];
	}

	/**
	 * The main attendee behind an RSVP.
	 *
	 * @since 3.13.0
	 *
	 * @param int $rsvp_id RSVP post id.
	 *
	 * @return array
	 */
	private static function for_rsvp( $rsvp_id ) {

		if ( ! class_exists( Rsvp::class ) ) {
			return [];
		}

		$rsvp = Rsvp::get( $rsvp_id );

		if ( empty( $rsvp ) || empty( $rsvp->main_attendee ) || empty( $rsvp->main_attendee->email ) ) {
			return [];
		}

		$email = sanitize_email( (string) $rsvp->main_attendee->email );

		if ( ! is_email( $email ) ) {
			return [];
		}

		return [
			'email' => $email,
			'name'  => isset( $rsvp->main_attendee->name ) ? trim( (string) $rsvp->main_attendee->name ) : '',
		];
	}
}
