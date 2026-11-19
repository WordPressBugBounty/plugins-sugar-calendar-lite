<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data;

/**
 * A TEC order's buyer, money, event and status.
 *
 * @since 3.18.0
 */
final class TecOrder {

	use TecRecord;

	/**
	 * One stored field, false when it is missing.
	 *
	 * @since 3.18.0
	 *
	 * @param string $key The field.
	 *
	 * @return mixed
	 */
	private function get( $key ) {

		return $this->fields[ $key ] ?? false;
	}

	/**
	 * The currency code.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_currency() {

		return $this->get( 'currency' );
	}

	/**
	 * The TEC event post ID the order is for, false when it is missing.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_event_post_id() {

		return $this->get( 'events_in_order' );
	}

	/**
	 * The payment gateway's order ID.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_gateway_order_id() {

		return $this->get( 'gateway_order_id' );
	}

	/**
	 * The buyer's email.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_email() {

		return $this->get( 'purchaser_email' );
	}

	/**
	 * The buyer's first name.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_first_name() {

		return $this->get( 'purchaser_first_name' );
	}

	/**
	 * The buyer's last name.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_last_name() {

		return $this->get( 'purchaser_last_name' );
	}

	/**
	 * When the order was placed, as `Y-m-d H:i:s`.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_purchase_time() {

		return $this->get( 'purchase_time' );
	}

	/**
	 * TEC's order status, without the `tec-tc-` prefix.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_status_slug() {

		return (string) $this->get( 'status_slug' );
	}

	/**
	 * The order total.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_total() {

		return $this->get( 'total' );
	}
}
