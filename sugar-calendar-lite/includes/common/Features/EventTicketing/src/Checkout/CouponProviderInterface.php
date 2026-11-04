<?php

namespace Sugar_Calendar\AddOn\Ticketing\Checkout;

/**
 * What answers the checkout's coupon questions.
 *
 * Core asks, a paid plan answers through the
 * `sugar_calendar_ticketing_checkout_coupon_provider` filter.
 *
 * @since 3.17.0
 */
interface CouponProviderInterface {

	/**
	 * Whether any coupon could apply to this event, so the modal shows the field.
	 *
	 * @since 3.17.0
	 *
	 * @param int $event_id Event ID.
	 */
	public function has_coupons_for( int $event_id ): bool;

	/**
	 * Price a code against a checkout.
	 *
	 * @since 3.17.0
	 *
	 * @param CouponRequest $request The checkout and the code.
	 *
	 * @return CheckoutDiscount|\WP_Error
	 */
	public function quote( CouponRequest $request );

	/**
	 * Keep one use of the coupon for this checkout.
	 *
	 * @since 3.17.0
	 *
	 * @param CouponRequest    $request  The checkout and the code.
	 * @param CheckoutDiscount $discount What quote() returned.
	 *
	 * @return CheckoutDiscount|\WP_Error The discount carrying its hold token.
	 */
	public function hold( CouponRequest $request, CheckoutDiscount $discount );

	/**
	 * Tie a hold to the PaymentIntent created for it, so only that intent can spend it.
	 *
	 * @since 3.17.0
	 *
	 * @param CheckoutDiscount $discount The held discount.
	 * @param string           $intent   PaymentIntent id.
	 *
	 * @return bool False when the hold could not be written, so it is not tied to the intent.
	 */
	public function bind_payment( CheckoutDiscount $discount, string $intent ): bool;

	/**
	 * Give a held use back.
	 *
	 * @since 3.17.0
	 *
	 * @param CheckoutDiscount $discount The held discount.
	 */
	public function release( CheckoutDiscount $discount ): void;
}
