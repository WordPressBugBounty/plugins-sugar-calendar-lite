<?php
/**
 * The `tickets` signup mode for the event write abilities.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\AddOn\Ticketing\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar_Event_Ticketing\Integrations\Abilities\TicketRepository;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\count_orders;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\sanitize_amount;

/**
 * TicketingSignupMode — declares `tickets` to the abilities' signup discriminator and applies
 * its detail object after a write.
 *
 * Writes meta directly: the feature's own save path builds its slice of the
 * `sugar_calendar_event_to_save` array by reading `$_POST`, which an ability request does not
 * have.
 *
 * @since 3.14.0
 */
class TicketingSignupMode {

	/**
	 * Mode slug.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const MODE = 'tickets';

	/**
	 * Detail property name in the ability input.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const PROPERTY = 'ticketing';

	/**
	 * Register the hooks.
	 *
	 * A mode that cannot read its own numbers must not declare itself: `TicketRepository`
	 * ships in the separate sc-event-ticketing plugin, and without it
	 * `SignupModes::available()` stays non-empty on a site where nothing can answer, so
	 * `Abilities::build_abilities()` advertises `get-registration-stats` and it returns
	 * zeros. All six hooks go together because `add_schema()` adds the `ticketing`
	 * property independently of `declare_mode()`.
	 *
	 * @since 3.14.0
	 *
	 * @return void
	 */
	public static function hooks() {

		if ( ! class_exists( TicketRepository::class ) ) {
			return;
		}

		add_filter( 'sugar_calendar_integrations_abilities_signup_modes', [ self::class, 'declare_mode' ] );
		add_filter( 'sugar_calendar_integrations_abilities_event_input_schema', [ self::class, 'add_schema' ], 10, 2 );
		add_filter( 'sugar_calendar_integrations_abilities_signup_modes_current', [ self::class, 'current_mode' ], 10, 2 );
		add_filter( 'sugar_calendar_integrations_abilities_signup_modes_in_use', [ self::class, 'in_use' ], 10, 3 );
		add_filter( 'sugar_calendar_integrations_abilities_signup_modes_stats', [ self::class, 'stats' ], 10, 3 );
		add_action( 'sugar_calendar_events_event_writer_after_write', [ self::class, 'apply' ], 10, 3 );
	}

	/**
	 * Declare the mode.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $modes Modes declared so far.
	 *
	 * @return array
	 */
	public static function declare_mode( $modes ): array {

		$modes = is_array( $modes ) ? $modes : [];

		$modes[ self::MODE ] = self::PROPERTY;

		return $modes;
	}

	/**
	 * Widen the input schema with the ticketing detail object.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $properties Properties so far.
	 * @param mixed $partial    Whether this is the update variant.
	 *
	 * @return mixed
	 */
	public static function add_schema( $properties, $partial ) {

		if ( ! is_array( $properties ) ) {
			return $properties;
		}

		$properties[ self::PROPERTY ] = [
			'type'                 => 'object',
			'additionalProperties' => false,
			'description'          => __( 'Paid ticket settings. Used only when signup is tickets.', 'sugar-calendar-lite' ),
			'properties'           => [
				'price'          => [
					'type'        => 'number',
					'minimum'     => 0,
					'description' => __( 'Price per ticket in the store currency.', 'sugar-calendar-lite' ),
				],
				'limit_capacity' => [
					'type'    => 'boolean',
					'default' => false,
				],
				'quantity'       => [
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => __( 'How many tickets are available. Used only when limit_capacity is true.', 'sugar-calendar-lite' ),
				],
			],
		];

		return $properties;
	}

	/**
	 * Answer whether this event currently uses tickets.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $mode     Mode resolved so far.
	 * @param mixed $event_id Sugar Calendar event id.
	 *
	 * @return mixed
	 */
	public static function current_mode( $mode, $event_id ) {

		return ! empty( get_event_meta( (int) $event_id, 'tickets', true ) ) ? self::MODE : $mode;
	}

	/**
	 * Refuse a switch away from tickets once orders exist.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $reason   Reason so far.
	 * @param mixed $mode     Mode being switched away from.
	 * @param mixed $event_id Sugar Calendar event id.
	 *
	 * @return mixed
	 */
	public static function in_use( $reason, $mode, $event_id ) {

		if ( $mode !== self::MODE ) {
			return $reason;
		}

		$orders = (int) count_orders( [ 'event_id' => (int) $event_id ] );

		if ( $orders < 1 ) {
			return $reason;
		}

		return sprintf(
			/* translators: %d: number of ticket orders already placed. */
			_n(
				'This event already has %d ticket order, so its signup method cannot be changed here. Change it in the event editor.',
				'This event already has %d ticket orders, so its signup method cannot be changed here. Change it in the event editor.',
				$orders,
				'sugar-calendar-lite'
			),
			$orders
		);
	}

	/**
	 * Contribute this mode's paid-order count and revenue for a window.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $stats Totals contributed so far, keyed by mode slug.
	 * @param string $start Window start, UTC 'Y-m-d H:i:s', inclusive.
	 * @param string $end   Window end, UTC 'Y-m-d H:i:s', exclusive.
	 *
	 * @return array
	 */
	public static function stats( $stats, string $start, string $end ): array {

		$stats = is_array( $stats ) ? $stats : [];

		// Unreachable through the filter — `hooks()` declines to register when the
		// class is missing. Kept because this method is public and callable directly.
		if ( ! class_exists( TicketRepository::class ) ) {
			return $stats;
		}

		$repository = new TicketRepository();
		$totals     = $repository->window_totals( $start, $end );

		$stats[ self::MODE ] = [
			'count'    => $totals['count'],
			'revenue'  => $totals['revenue'],
			'currency' => $repository->get_currency(),
		];

		return $stats;
	}

	/**
	 * Enable or stand down after a write.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $event_id   Sugar Calendar event id.
	 * @param array $normalized Normalized input.
	 * @param array $result     Result so far, by reference.
	 *
	 * @return void
	 */
	public static function apply( $event_id, $normalized, &$result ) {

		$extra = isset( $normalized['extra'] ) ? (array) $normalized['extra'] : [];

		// Signup absent means the caller never mentioned it: leave the mode alone.
		if ( ! array_key_exists( 'signup', $extra ) ) {
			return;
		}

		$event_id = (int) $event_id;

		if ( (string) $extra['signup'] !== self::MODE ) {

			if ( ! empty( get_event_meta( $event_id, 'tickets', true ) ) ) {
				update_event_meta( $event_id, 'tickets', 0 );

				$result['applied'][] = __( 'Turned off ticket sales.', 'sugar-calendar-lite' );
			}

			return;
		}

		$detail = isset( $extra[ self::PROPERTY ] ) ? (array) $extra[ self::PROPERTY ] : [];
		$limit  = ! empty( $detail['limit_capacity'] );

		update_event_meta( $event_id, 'tickets', 1 );

		// update-event is a patch: write a key only when the caller sent it, leave the rest as-is.
		if ( isset( $detail['price'] ) ) {
			update_event_meta( $event_id, 'ticket_price', sanitize_amount( $detail['price'] ) );
		}

		if ( array_key_exists( 'limit_capacity', $detail ) ) {
			update_event_meta( $event_id, 'ticket_limit_capacity', $limit ? 1 : 0 );
		}

		if ( isset( $detail['quantity'] ) ) {
			update_event_meta( $event_id, 'ticket_quantity', absint( $detail['quantity'] ) );
		}

		$result['applied'][] = __( 'Turned on paid tickets.', 'sugar-calendar-lite' );
	}
}
