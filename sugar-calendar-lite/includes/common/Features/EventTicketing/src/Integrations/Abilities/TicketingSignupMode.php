<?php
/**
 * The `tickets` signup mode for the event write abilities.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\AddOn\Ticketing\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractEventFieldContributor;
use Sugar_Calendar_Event_Ticketing\Integrations\Abilities\TicketRepository;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\count_orders;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\sanitize_amount;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\ticketing_provider_available_for_admin;

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
class TicketingSignupMode extends AbstractEventFieldContributor {

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
	 * zeros. All the hooks go together because `add_schema()` adds the `ticketing`
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

		parent::hooks();

		add_filter( 'sugar_calendar_integrations_abilities_signup_modes', [ self::class, 'declare_mode' ] );
		add_filter( 'sugar_calendar_integrations_abilities_signup_modes_current', [ self::class, 'current_mode' ], 10, 2 );
		add_filter( 'sugar_calendar_integrations_abilities_signup_modes_in_use', [ self::class, 'in_use' ], 10, 3 );
		add_filter( 'sugar_calendar_integrations_ai_funnels_event_properties', [ self::class, 'add_funnel_property' ], 5 );
		add_filter( 'sugar_calendar_integrations_ai_funnels_ask_properties', [ self::class, 'add_ask_property' ], 5 );

		// Add-on 1.6.0 has the class but not this method. Without it, the mode is reported
		// as uncounted instead of fataling.
		if ( method_exists( TicketRepository::class, 'window_totals' ) ) {
			add_filter( 'sugar_calendar_integrations_abilities_signup_modes_stats', [ self::class, 'stats' ], 10, 3 );
		}
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
			'description'          => __( 'Paid ticket settings. Applies only when signup is tickets; send both together, for example signup: tickets with ticketing: { price: 10, limit_capacity: true, quantity: 20 }.', 'sugar-calendar-lite' ),
			'properties'           => [
				'price'          => [
					'type'        => 'number',
					'minimum'     => 0,
					'description' => __( 'Price of one ticket in the store currency, for example 10 or 12.50. 0 makes the tickets free.', 'sugar-calendar-lite' ),
				],
				'limit_capacity' => [
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Whether ticket sales stop once quantity tickets are sold. Send true whenever you send quantity. Default false.', 'sugar-calendar-lite' ),
				],
				'quantity'       => [
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => __( 'How many tickets can be sold in total, for example 20. Applies only when limit_capacity is true; sent alone it is stored but has no effect.', 'sugar-calendar-lite' ),
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
	 * Prepend the "Ticketed Event" start-screen chip.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $properties Chips so far.
	 *
	 * @return mixed
	 */
	public static function add_funnel_property( $properties ) {

		if ( ! is_array( $properties ) ) {
			return $properties;
		}

		array_unshift(
			$properties,
			[
				'slug'  => 'ticketed',
				'label' => __( 'Ticketed Event', 'sugar-calendar-lite' ),
				'icon'  => 'ticket',
			]
		);

		return $properties;
	}

	/**
	 * Add the ask funnel's "Ticket Sales" topic chip, second as in Figma (after Registrations).
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $properties Chips so far.
	 *
	 * @return mixed
	 */
	public static function add_ask_property( $properties ) {

		if ( ! is_array( $properties ) ) {
			return $properties;
		}

		array_splice(
			$properties,
			1,
			0,
			[
				[
					'slug'  => 'ticket_sales',
					'label' => __( 'Ticket Sales', 'sugar-calendar-lite' ),
					'icon'  => 'ticket',
				],
			]
		);

		return $properties;
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
	 * @since 3.15.0 The paid-or-free line asks `sugar_calendar_integrations_abilities_ticketing_is_paid`.
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
		$was_on   = ! empty( get_event_meta( $event_id, 'tickets', true ) );

		if ( (string) $extra['signup'] !== self::MODE ) {

			if ( $was_on ) {
				update_event_meta( $event_id, 'tickets', 0 );

				$result['applied'][] = __( 'Turned off ticket sales.', 'sugar-calendar-lite' );
			}

			return;
		}

		$detail = isset( $extra[ self::PROPERTY ] ) ? (array) $extra[ self::PROPERTY ] : [];

		update_event_meta( $event_id, 'tickets', 1 );

		// update-event is a patch: write a key only when the caller sent it, leave the rest as-is.
		if ( isset( $detail['price'] ) ) {
			self::apply_price( $event_id, $detail['price'], $result );
		}

		self::apply_capacity( $event_id, $detail, $result );

		// Only announce a mode switch when this write is the one that flipped it: a
		// price/quantity change on an event that already sells tickets is not "turning on" anything.
		if ( ! $was_on ) {
			$price = (float) get_event_meta( $event_id, 'ticket_price', true );

			/**
			 * Filters whether an event that just turned on tickets sells a paid ticket.
			 *
			 * The default reads the main ticket only; an add-on with more ticket types answers for those.
			 *
			 * @since 3.15.0
			 *
			 * @param bool $paid     Whether the main ticket has a price.
			 * @param int  $event_id Sugar Calendar event id.
			 */
			$paid = (bool) apply_filters( 'sugar_calendar_integrations_abilities_ticketing_is_paid', $price > 0, $event_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			$result['applied'][] = $paid
				? __( 'Turned on paid tickets.', 'sugar-calendar-lite' )
				: __( 'Turned on free tickets.', 'sugar-calendar-lite' );
		}
	}

	/**
	 * Store the ticket price, matching the editor's save path: a submitted price
	 * is clamped to 0 while no payment provider is connected, since nothing could charge it.
	 *
	 * Resending the price already stored is a no-op: writing it again would run the
	 * no-payment-method clamp against the current connection state and could zero out
	 * a price that was legitimately set while a payment method was connected.
	 *
	 * @since 3.15.0
	 *
	 * @param int   $event_id Event ID.
	 * @param mixed $price    Requested price, as sent.
	 * @param array $result   Writer result, by reference.
	 *
	 * @return void
	 */
	private static function apply_price( int $event_id, $price, array &$result ) {

		$price  = sanitize_amount( $price );
		$stored = sanitize_amount( get_event_meta( $event_id, 'ticket_price', true ) );

		if ( (float) $price === (float) $stored ) {
			return;
		}

		if ( (float) $price > 0 && ! ticketing_provider_available_for_admin() ) {
			$price = 0;

			$result['errors'][]    = __( 'No payment method is connected, so tickets stay free. Connect one in Sugar Calendar settings to charge for them.', 'sugar-calendar-lite' );
			$result['explained'][] = self::PROPERTY . '.price';
		}

		update_event_meta( $event_id, 'ticket_price', $price );
	}

	/**
	 * Store `limit_capacity` and `quantity` together, refusing a combination the
	 * front end can't display: a capacity limit with an effective quantity under 1
	 * closes sales with nothing telling the caller why.
	 *
	 * The schema's `minimum: 0` on quantity is advisory in tool calling, and
	 * `absint()` would turn -1 into a plausible 1 the user never asked for.
	 *
	 * @since 3.15.0
	 *
	 * @param int   $event_id Event ID.
	 * @param array $detail   The ticketing detail as sent.
	 * @param array $result   Writer result, by reference.
	 *
	 * @return void
	 */
	private static function apply_capacity( int $event_id, array $detail, array &$result ) {

		$has_limit    = array_key_exists( 'limit_capacity', $detail );
		$has_quantity = isset( $detail['quantity'] );

		if ( ! $has_limit && ! $has_quantity ) {
			return;
		}

		if ( $has_quantity && (int) $detail['quantity'] < 0 ) {
			$result['errors'][] = __( 'The ticket quantity was not changed because it cannot be negative.', 'sugar-calendar-lite' );

			$has_quantity = false;
		}

		// A patch may send only one of the pair, so read the stored value of the other.
		$limit    = $has_limit ? ! empty( $detail['limit_capacity'] ) : ! empty( get_event_meta( $event_id, 'ticket_limit_capacity', true ) );
		$quantity = $has_quantity ? (int) $detail['quantity'] : (int) get_event_meta( $event_id, 'ticket_quantity', true );

		if ( $limit && $quantity < 1 ) {
			$result['errors'][] = __( 'With a capacity limit, the ticket quantity must be at least 1.', 'sugar-calendar-lite' );

			if ( $has_limit ) {
				$result['explained'][] = self::PROPERTY . '.limit_capacity';
			}

			if ( $has_quantity ) {
				$result['explained'][] = self::PROPERTY . '.quantity';
			}

			return;
		}

		if ( $has_limit ) {
			update_event_meta( $event_id, 'ticket_limit_capacity', $limit ? 1 : 0 );
		}

		if ( ! $has_quantity ) {
			return;
		}

		update_event_meta( $event_id, 'ticket_quantity', $quantity );

		if ( ! $limit ) {
			$result['applied'][] = sprintf(
				/* translators: %d - ticket quantity. */
				__( 'Saved a ticket quantity of %d. It has no effect until limit_capacity is turned on.', 'sugar-calendar-lite' ),
				$quantity
			);
		}
	}

	/**
	 * Add the stored ticketing detail to the post-write snapshot, when tickets are enabled.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $snapshot Snapshot so far.
	 * @param mixed $event_id Sugar Calendar event id.
	 *
	 * @return mixed
	 */
	public static function snapshot( $snapshot, $event_id ) {

		if ( ! is_array( $snapshot ) ) {
			return $snapshot;
		}

		$event_id = (int) $event_id;

		if ( empty( get_event_meta( $event_id, 'tickets', true ) ) ) {
			return $snapshot;
		}

		$snapshot[ self::PROPERTY ] = [
			'price'          => (float) get_event_meta( $event_id, 'ticket_price', true ),
			'limit_capacity' => (bool) get_event_meta( $event_id, 'ticket_limit_capacity', true ),
			'quantity'       => (int) get_event_meta( $event_id, 'ticket_quantity', true ),
		];

		return $snapshot;
	}
}
