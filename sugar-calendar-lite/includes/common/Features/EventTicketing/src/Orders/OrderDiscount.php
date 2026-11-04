<?php

namespace Sugar_Calendar\AddOn\Ticketing\Orders;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\currency_filter;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\display_price;

/**
 * The discount an order stored when it was placed.
 *
 * Reads only the order's own columns, never the coupon row, so it keeps
 * answering after the coupon is edited or deleted. Every string is empty when
 * the order has no discount.
 *
 * @since 3.17.0
 */
final class OrderDiscount {

	/**
	 * Stored subtotal, before the discount.
	 *
	 * @since 3.17.0
	 *
	 * @var mixed
	 */
	private $subtotal;

	/**
	 * Stored discount amount.
	 *
	 * @since 3.17.0
	 *
	 * @var float
	 */
	private $discount;

	/**
	 * Stored coupon id.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	private $coupon_id;

	/**
	 * Stored coupon code.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Constructor.
	 *
	 * @since 3.17.0
	 *
	 * @param mixed  $subtotal  Stored subtotal.
	 * @param mixed  $discount  Stored discount.
	 * @param mixed  $coupon_id Stored coupon id.
	 * @param string $code      Stored coupon code.
	 */
	private function __construct( $subtotal, $discount, $coupon_id, string $code ) {

		$this->subtotal  = $subtotal;
		$this->discount  = is_numeric( $discount ) ? (float) $discount : 0.0;
		$this->coupon_id = (int) $coupon_id;
		$this->code      = $code;
	}

	/**
	 * Build from an order row.
	 *
	 * @since 3.17.0
	 *
	 * @param object $order The order row.
	 *
	 * @return self
	 */
	public static function from_order( $order ): self {

		return new self(
			$order->subtotal ?? 0,
			$order->discount ?? 0,
			$order->discount_id ?? 0,
			(string) ( $order->discount_code ?? '' )
		);
	}

	/**
	 * Whether the order stored a discount above zero.
	 *
	 * @since 3.17.0
	 *
	 * @return bool
	 */
	public function applies(): bool {

		return $this->discount > 0;
	}

	/**
	 * The stored coupon id.
	 *
	 * @since 3.17.0
	 *
	 * @return int
	 */
	public function coupon_id(): int {

		return $this->coupon_id;
	}

	/**
	 * The stored coupon code, plain text.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	public function code(): string {

		return $this->applies() ? $this->code : '';
	}

	/**
	 * The subtotal as a plain-text price.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	public function subtotal(): string {

		return $this->applies() ? wp_strip_all_tags( display_price( $this->subtotal ) ) : '';
	}

	/**
	 * The discount as a positive plain-text price.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	public function amount(): string {

		return $this->applies() ? wp_strip_all_tags( display_price( $this->discount ) ) : '';
	}

	/**
	 * The discount as a negative plain-text price, minus before the currency sign.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	public function negative_amount(): string {

		return $this->applies() ? wp_strip_all_tags( currency_filter( (string) -$this->discount ) ) : '';
	}

	/**
	 * The negative amount followed by the code, e.g. "-$15.75 (SCHOOL25)".
	 *
	 * @since 3.17.0
	 *
	 * @param string|null $code_html Replacement for the plain code, already escaped. Null uses the plain code.
	 *
	 * @return string
	 */
	public function line( ?string $code_html = null ): string {

		$code = $code_html ?? $this->code();

		if ( $code === '' ) {
			return $this->negative_amount();
		}

		return sprintf(
			/* translators: %1$s - negative discount amount, %2$s - coupon code. */
			__( '%1$s (%2$s)', 'sugar-calendar-lite' ),
			$this->negative_amount(),
			$code
		);
	}
}
