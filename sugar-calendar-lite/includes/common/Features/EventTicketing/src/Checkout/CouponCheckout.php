<?php

namespace Sugar_Calendar\AddOn\Ticketing\Checkout;

use WP_Error;

/**
 * Applies the bridge's discount to the checkout's amount, $0 check and order row.
 *
 * Built once from the ticketing feature: the gateways are instantiated several
 * times a request and would add these hooks each time.
 *
 * @since 3.17.0
 */
final class CouponCheckout {

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_filter( 'sc_et_stripe_get_amount', [ $this, 'discount_amount' ], 20, 3 );
		add_filter( 'sc_et_stripe_validate_transaction', [ $this, 'claim_hold' ], 20, 3 );
		add_filter( 'sc_et_checkout_complete_order_data_before_save', [ $this, 'add_order_fields' ] );
		add_action( 'sc_et_checkout_pre_redirect', [ $this, 'release_hold' ], 5, 2 );
	}

	/**
	 * Charge the discounted amount.
	 *
	 * @since 3.17.0
	 *
	 * @param int|float $amount   Amount in minor units.
	 * @param int       $event_id Event ID.
	 * @param int       $quantity Quantity, unused: the add-on priced it at priority 10.
	 *
	 * @return int|float
	 */
	public function discount_amount( $amount, $event_id, $quantity = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		// The incoming value can be a float.
		$quote = $amount > 0 ? CouponBridge::quote( (int) $event_id, (int) round( $amount ) ) : null;

		return $quote instanceof CheckoutDiscount ? $quote->total() : $amount;
	}

	/**
	 * Spend the coupon use on the order being saved.
	 *
	 * Purchase held the use; paid orders spend it only with the PaymentIntent it was bound to.
	 *
	 * @since 3.17.0
	 *
	 * @param true|WP_Error $valid      Validity so far.
	 * @param mixed         $event      Event.
	 * @param array         $order_data Order data.
	 *
	 * @return true|WP_Error
	 */
	public function claim_hold( $valid, $event, $order_data ) {

		$event_id = (int) ( $order_data['event_id'] ?? 0 );

		if (
			is_wp_error( $valid ) ||
			! CouponBridge::applied( $event_id ) instanceof CheckoutDiscount
		) {
			return $valid;
		}

		$hold = CouponBridge::hold( $event_id, true );

		return $hold instanceof WP_Error
			? new WP_Error( CouponBridge::ERROR_CODE, $hold->get_error_message() )
			: $valid;
	}

	/**
	 * Store the discount on the order row. `total` stays what the gateway recorded.
	 *
	 * @since 3.17.0
	 *
	 * @param array $order_data Order data.
	 *
	 * @return array
	 */
	public function add_order_fields( $order_data ) {

		$discount = CouponBridge::applied( (int) ( $order_data['event_id'] ?? 0 ) );

		return $discount instanceof CheckoutDiscount
			? array_merge( $order_data, $discount->to_order_fields() )
			: $order_data;
	}

	/**
	 * Release the hold once the order is saved.
	 *
	 * @since 3.17.0
	 *
	 * @param int   $order_id   Order ID.
	 * @param array $order_data Order data.
	 */
	public function release_hold( $order_id, $order_data ) {

		CouponBridge::release( CouponBridge::applied( (int) ( $order_data['event_id'] ?? 0 ) ) );
	}
}
