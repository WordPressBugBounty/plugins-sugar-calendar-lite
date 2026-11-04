<?php

namespace Sugar_Calendar\AddOn\Ticketing\Checkout;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;

/**
 * A coupon's effect on one checkout, in minor units.
 *
 * The amount charged, the free decision and the order row all read this one
 * object, so they cannot disagree.
 *
 * @since 3.17.0
 */
final class CheckoutDiscount {

	/**
	 * Coupon ID.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	private $coupon_id;

	/**
	 * Coupon code as the coupon has it.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Subtotal before the discount, minor units.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	private $subtotal;

	/**
	 * Amount off, minor units.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	private $discount;

	/**
	 * Hold token, or an empty string.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	private $hold;

	/**
	 * Constructor.
	 *
	 * The discount is clamped between 0 and the subtotal.
	 *
	 * @since 3.17.0
	 *
	 * @param int    $coupon_id Coupon ID.
	 * @param string $code      Coupon code.
	 * @param int    $subtotal  Subtotal in minor units.
	 * @param int    $discount  Amount off in minor units.
	 * @param string $hold      Hold token.
	 *
	 * @phpstan-ignore sugarCalendar.methodParameters (constructor signature is the contract layer 5 and Pro build against)
	 */
	public function __construct( int $coupon_id, string $code, int $subtotal, int $discount, string $hold = '' ) {

		$this->coupon_id = $coupon_id;
		$this->code      = $code;
		$this->subtotal  = max( 0, $subtotal );
		$this->discount  = min( max( 0, $discount ), $this->subtotal );
		$this->hold      = $hold;
	}

	/**
	 * Coupon ID.
	 *
	 * @since 3.17.0
	 */
	public function coupon_id(): int {

		return $this->coupon_id;
	}

	/**
	 * Coupon code.
	 *
	 * @since 3.17.0
	 */
	public function code(): string {

		return $this->code;
	}

	/**
	 * Subtotal before the discount, minor units.
	 *
	 * @since 3.17.0
	 */
	public function subtotal(): int {

		return $this->subtotal;
	}

	/**
	 * Amount off, minor units.
	 *
	 * @since 3.17.0
	 */
	public function discount(): int {

		return $this->discount;
	}

	/**
	 * Amount to charge, minor units.
	 *
	 * @since 3.17.0
	 */
	public function total(): int {

		return $this->subtotal - $this->discount;
	}

	/**
	 * Hold token, or an empty string.
	 *
	 * @since 3.17.0
	 */
	public function hold(): string {

		return $this->hold;
	}

	/**
	 * Copy of this discount carrying a hold token.
	 *
	 * @since 3.17.0
	 *
	 * @param string $hold Hold token.
	 */
	public function with_hold( string $hold ): self {

		return new self( $this->coupon_id, $this->code, $this->subtotal, $this->discount, $hold );
	}

	/**
	 * The order columns this discount sets. Never `total`: the gateway records what it charged.
	 *
	 * @since 3.17.0
	 *
	 * @return array
	 */
	public function to_order_fields(): array {

		return [
			'subtotal'      => self::major( $this->subtotal ),
			'discount'      => self::major( $this->discount ),
			'discount_id'   => $this->coupon_id,
			'discount_code' => $this->code,
		];
	}

	/**
	 * Major units to minor units, rounded (19.99 * 100 is 1998.999…).
	 *
	 * @since 3.17.0
	 *
	 * @param float|int|string $amount Amount in major units.
	 */
	public static function minor( $amount ): int {

		return Functions\is_zero_decimal_currency()
			? (int) round( (float) $amount )
			: (int) round( (float) $amount * 100 );
	}

	/**
	 * Minor units to major units.
	 *
	 * @since 3.17.0
	 *
	 * @param int $amount Amount in minor units.
	 */
	public static function major( int $amount ): float {

		return Functions\is_zero_decimal_currency()
			? (float) $amount
			: $amount / 100;
	}
}
