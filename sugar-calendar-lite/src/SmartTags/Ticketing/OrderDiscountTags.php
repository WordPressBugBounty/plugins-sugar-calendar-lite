<?php

namespace Sugar_Calendar\SmartTags\Ticketing;

use Sugar_Calendar\AddOn\Ticketing\Orders\OrderDiscount;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;

/**
 * Callbacks for the `{order_discount}` and `{coupon_code}` Smart Tags.
 *
 * Both are empty for an order without a discount, so a template can carry them
 * without a conditional.
 *
 * @since 3.17.0
 */
class OrderDiscountTags {

	/**
	 * The discount amount, positive and formatted like `{order_amount}`.
	 *
	 * @since 3.17.0
	 *
	 * @param int    $object_id   Order id.
	 * @param string $object_type Object type.
	 * @param string $tag         Slug.
	 *
	 * @return string
	 */
	public static function order_discount( $object_id = 0, $object_type = 'order', $tag = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Smart Tag callbacks share one signature.

		$order = get_order( $object_id );

		return $order ? OrderDiscount::from_order( $order )->amount() : '';
	}

	/**
	 * The coupon code stored on the order, escaped.
	 *
	 * @since 3.17.0
	 *
	 * @param int    $object_id   Order id.
	 * @param string $object_type Object type.
	 * @param string $tag         Slug.
	 *
	 * @return string
	 */
	public static function coupon_code( $object_id = 0, $object_type = 'order', $tag = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Smart Tag callbacks share one signature.

		$order = get_order( $object_id );

		return $order ? esc_html( OrderDiscount::from_order( $order )->code() ) : '';
	}
}
