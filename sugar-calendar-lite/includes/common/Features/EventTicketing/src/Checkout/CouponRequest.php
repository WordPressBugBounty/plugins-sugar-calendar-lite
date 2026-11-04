<?php

namespace Sugar_Calendar\AddOn\Ticketing\Checkout;

/**
 * One coupon question: which checkout, which code.
 *
 * @since 3.17.0
 */
final class CouponRequest {

	/**
	 * Event ID.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	private $event_id;

	/**
	 * Subtotal in minor units.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	private $subtotal;

	/**
	 * Submitted code.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Submitted hold token, or an empty string.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	private $hold;

	/**
	 * Submitted PaymentIntent id, or an empty string.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	private $payment = '';

	/**
	 * Whether this request spends the hold on an order that needs no payment.
	 *
	 * @since 3.17.0
	 *
	 * @var bool
	 */
	private $claim = false;

	/**
	 * Constructor.
	 *
	 * @since 3.17.0
	 *
	 * @param int    $event_id Event ID.
	 * @param int    $subtotal Subtotal in minor units.
	 * @param string $code     Submitted code.
	 * @param string $hold     Submitted hold token.
	 */
	public function __construct( int $event_id, int $subtotal, string $code, string $hold ) {

		$this->event_id = $event_id;
		$this->subtotal = $subtotal;
		$this->code     = $code;
		$this->hold     = $hold;
	}

	/**
	 * Event ID.
	 *
	 * @since 3.17.0
	 */
	public function event_id(): int {

		return $this->event_id;
	}

	/**
	 * Subtotal in minor units.
	 *
	 * @since 3.17.0
	 */
	public function subtotal(): int {

		return $this->subtotal;
	}

	/**
	 * Submitted code.
	 *
	 * @since 3.17.0
	 */
	public function code(): string {

		return $this->code;
	}

	/**
	 * Submitted hold token, or an empty string.
	 *
	 * @since 3.17.0
	 */
	public function hold(): string {

		return $this->hold;
	}

	/**
	 * Submitted PaymentIntent id, or an empty string.
	 *
	 * @since 3.17.0
	 */
	public function payment(): string {

		return $this->payment;
	}

	/**
	 * Whether this request spends the hold on an order that needs no payment.
	 *
	 * @since 3.17.0
	 */
	public function claims(): bool {

		return $this->claim;
	}

	/**
	 * A copy that carries the submitted PaymentIntent id.
	 *
	 * @since 3.17.0
	 *
	 * @param string $payment PaymentIntent id.
	 */
	public function with_payment( string $payment ): self {

		$copy          = clone $this;
		$copy->payment = $payment;

		return $copy;
	}

	/**
	 * A copy that spends the hold on a free order.
	 *
	 * @since 3.17.0
	 */
	public function claiming(): self {

		$copy        = clone $this;
		$copy->claim = true;

		return $copy;
	}
}
