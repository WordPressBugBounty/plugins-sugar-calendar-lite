<?php

namespace Sugar_Calendar\AddOn\Ticketing\Checkout;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\Event;
use WP_Error;

/**
 * The one door between the checkout and whatever answers coupon questions.
 *
 * Every call is static and works on the current request: what quote() and
 * hold() return is recorded per event, so the amount, the free decision and
 * the order row all read the same answer.
 *
 * @since 3.17.0
 */
final class CouponBridge {

	/**
	 * POST field carrying the applied code.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const CODE_FIELD = 'sc_et_coupon_code';

	/**
	 * POST field carrying the hold token.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const HOLD_FIELD = 'sc_et_coupon_hold';

	/**
	 * POST field carrying the PaymentIntent id.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const INTENT_FIELD = 'sc_et_payment_intent';

	/**
	 * Pattern a PaymentIntent id must match.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const INTENT_PATTERN = '/^pi_[A-Za-z0-9_]+$/';

	/**
	 * Error code of a refused $0 order.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const ERROR_CODE = 'sc_et_coupon_invalid';

	/**
	 * Result of the last quote() or hold(), by event ID.
	 *
	 * @since 3.17.0
	 *
	 * @var array<int, CheckoutDiscount|WP_Error>
	 */
	private static $recorded = [];

	/**
	 * The submitted code, or an empty string.
	 *
	 * @since 3.17.0
	 */
	public static function code(): string {

		// The price request is nonce-free like the rest of the modal's reads, and the
		// charge request verifies its nonce before it gets here.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ self::CODE_FIELD ] ) || ! is_string( $_POST[ self::CODE_FIELD ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_text_field( wp_unslash( $_POST[ self::CODE_FIELD ] ) );
	}

	/**
	 * Whether coupons apply to this event at all.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event Event.
	 */
	public static function supports( Event $event ): bool {

		/**
		 * Filters whether the coupon field and discount apply to an event.
		 *
		 * @since 3.17.0
		 *
		 * @param bool  $supported True unless the event sells several ticket types.
		 * @param Event $event     Event.
		 */
		return (bool) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_checkout_coupons_supported',
			! self::is_multiple_tickets( $event ),
			$event
		);
	}

	/**
	 * Whether the modal shows the coupon field.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event          Event.
	 * @param bool  $is_ticket_free Whether the ticket is free.
	 */
	public static function show_field( Event $event, bool $is_ticket_free ): bool {

		$provider = self::provider();

		if ( $is_ticket_free || $provider === null || ! self::supports( $event ) ) {
			return false;
		}

		/**
		 * Filters whether the checkout shows the coupon field, once the provider says a coupon could apply.
		 *
		 * A gateway that cannot charge a discounted amount answers false.
		 *
		 * @since 3.17.0
		 *
		 * @param bool  $show  Whether to show the field.
		 * @param Event $event Event.
		 */
		return (bool) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_checkout_show_coupon_field',
			$provider->has_coupons_for( (int) $event->id ),
			$event
		);
	}

	/**
	 * Price the submitted code against a subtotal.
	 *
	 * @since 3.17.0
	 *
	 * @param int $event_id Event ID.
	 * @param int $subtotal Subtotal in minor units.
	 *
	 * @return CheckoutDiscount|WP_Error|null Null when there is nothing to apply.
	 */
	public static function quote( int $event_id, int $subtotal ) {

		$code     = self::code();
		$event    = $code === '' ? null : sugar_calendar_get_event( $event_id );
		$provider = $event instanceof Event && self::supports( $event ) ? self::provider() : null;

		if ( $provider === null ) {
			return null;
		}

		return self::record(
			$event_id,
			$provider->quote( ( new CouponRequest( $event_id, $subtotal, $code, self::posted( self::HOLD_FIELD, '/^[0-9a-f]{32}$/' ) ) )->with_payment( self::posted( self::INTENT_FIELD, self::INTENT_PATTERN ) ) )
		);
	}

	/**
	 * What quote() or hold() last returned for an event in this request.
	 *
	 * @since 3.17.0
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return CheckoutDiscount|WP_Error|null
	 */
	public static function applied( int $event_id ) {

		return self::$recorded[ $event_id ] ?? null;
	}

	/**
	 * Hold one use of the recorded discount. Call it after the amount was computed.
	 *
	 * @since 3.17.0
	 *
	 * @param int  $event_id Event ID.
	 * @param bool $claim    Whether the hold is spent on the order being saved.
	 *
	 * @return CheckoutDiscount|WP_Error|null The recorded error or null unchanged.
	 */
	public static function hold( int $event_id, bool $claim = false ) {

		$discount = self::applied( $event_id );
		$provider = self::provider();

		if ( ! $discount instanceof CheckoutDiscount || $provider === null ) {
			return $discount;
		}

		$request = ( new CouponRequest( $event_id, $discount->subtotal(), $discount->code(), self::posted( self::HOLD_FIELD, '/^[0-9a-f]{32}$/' ) ) )->with_payment( self::posted( self::INTENT_FIELD, self::INTENT_PATTERN ) );
		$request = $claim ? $request->claiming() : $request;

		return self::record( $event_id, $provider->hold( $request, $discount ) );
	}

	/**
	 * Tie the hold to the PaymentIntent created for it.
	 *
	 * @since 3.17.0
	 *
	 * @param CheckoutDiscount|WP_Error|null $hold   What hold() returned.
	 * @param string                         $intent PaymentIntent id.
	 *
	 * @return true|WP_Error The coupon error to answer with when the hold could not be tied;
	 *                       the caller releases the hold, since its form POST could not spend it.
	 */
	public static function bind_payment( $hold, string $intent ) {

		$provider = self::provider();

		if ( ! $hold instanceof CheckoutDiscount || $provider === null || $intent === '' ) {
			return true;
		}

		if ( $provider->bind_payment( $hold, $intent ) ) {
			return true;
		}

		return new WP_Error( self::ERROR_CODE, __( 'We could not reserve this coupon. Please try again.', 'sugar-calendar-lite' ) );
	}

	/**
	 * Give a held use back.
	 *
	 * @since 3.17.0
	 *
	 * @param CheckoutDiscount|WP_Error|null $hold What hold() returned.
	 */
	public static function release( $hold ): void {

		$provider = self::provider();

		if ( $hold instanceof CheckoutDiscount && $provider !== null ) {
			$provider->release( $hold );
		}
	}

	/**
	 * The coupon part of a price response.
	 *
	 * @since 3.17.0
	 *
	 * @param int $event_id Event ID.
	 * @param int $subtotal Subtotal in minor units.
	 *
	 * @return array{code: string, error: string, subtotal: string, discount: string, total: string, total_raw: float}|null
	 */
	public static function quote_payload( int $event_id, int $subtotal ) {

		$quote = self::quote( $event_id, $subtotal );

		if ( $quote instanceof WP_Error ) {
			return [
				'code'      => self::code(),
				'error'     => $quote->get_error_message(),
				'subtotal'  => '',
				'discount'  => '',
				'total'     => Functions\display_price( CheckoutDiscount::major( $subtotal ) ),
				'total_raw' => CheckoutDiscount::major( $subtotal ),
			];
		}

		if ( ! $quote instanceof CheckoutDiscount ) {
			return null;
		}

		return [
			'code'      => $quote->code(),
			'error'     => '',
			'subtotal'  => Functions\currency_filter( (string) CheckoutDiscount::major( $quote->subtotal() ) ),
			'discount'  => Functions\currency_filter( (string) -CheckoutDiscount::major( $quote->discount() ) ),
			'total'     => Functions\currency_filter( (string) CheckoutDiscount::major( $quote->total() ) ),
			'total_raw' => CheckoutDiscount::major( $quote->total() ),
		];
	}

	/**
	 * The AJAX error answer for a refused coupon.
	 *
	 * @since 3.17.0
	 *
	 * @param WP_Error $error Error.
	 */
	public static function error_response( WP_Error $error ): array {

		return [
			// Plain text, not escaped: on `coupon_error` the Stripe scripts pass it to the coupon UI, which sets it with .text().
			'error_msg'    => $error->get_error_message(),
			'coupon_error' => true,
		];
	}

	/**
	 * Add the hold token to a payment response.
	 *
	 * @since 3.17.0
	 *
	 * @param array                          $response The response.
	 * @param CheckoutDiscount|WP_Error|null $hold     What hold() returned.
	 */
	public static function payment_response( array $response, $hold ): array {

		if ( $hold instanceof CheckoutDiscount && $hold->hold() !== '' ) {
			$response['coupon_hold'] = $hold->hold();
		}

		return $response;
	}

	/**
	 * Whether the event sells several ticket types.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event Event.
	 */
	private static function is_multiple_tickets( Event $event ): bool {

		/**
		 * Whether the event sells several ticket types (documented in modal.php).
		 *
		 * Same arguments as modal.php: the third is the post id, because get_the_ID() is empty during AJAX.
		 *
		 * @since 3.17.0
		 *
		 * @param bool  $is_multiple_tickets Whether multiple tickets are enabled.
		 * @param Event $event               The event object.
		 * @param int   $post_id             The post ID.
		 */
		$is_multiple_tickets = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'sc_et_is_multiple_tickets',
			false,
			$event,
			(int) $event->object_id
		);

		return (bool) $is_multiple_tickets;
	}

	/**
	 * Record a provider's answer for an event.
	 *
	 * @since 3.17.0
	 *
	 * @param int                       $event_id Event ID.
	 * @param CheckoutDiscount|WP_Error $result   Provider answer.
	 *
	 * @return CheckoutDiscount|WP_Error
	 */
	private static function record( int $event_id, $result ) {

		self::$recorded[ $event_id ] = $result;

		return $result;
	}

	/**
	 * The provider, or null when nothing answers (every Lite site).
	 *
	 * @since 3.17.0
	 */
	private static function provider(): ?CouponProviderInterface {

		/**
		 * Filters the object that answers the checkout's coupon questions.
		 *
		 * @since 3.17.0
		 *
		 * @param CouponProviderInterface|null $provider Provider, null by default.
		 */
		$provider = apply_filters( 'sugar_calendar_ticketing_checkout_coupon_provider', null ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return $provider instanceof CouponProviderInterface ? $provider : null;
	}

	/**
	 * A submitted token field (string only) when it matches the pattern, otherwise an empty string.
	 *
	 * @since 3.17.0
	 *
	 * @param string $field   POST field.
	 * @param string $pattern Pattern the whole value must match.
	 */
	public static function posted( string $field, string $pattern ): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] )
			? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';

		return preg_match( $pattern, $value ) === 1 ? $value : '';
	}
}
