<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin;

use Sugar_Calendar\AddOn\Ticketing\Orders\OrderDiscount;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Discount column of the Orders list.
 *
 * The column exists only once the site has an order that used a coupon.
 *
 * @since 3.17.0
 */
class OrdersDiscountColumn {

	/**
	 * Autoloaded option that records the first coupon order.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const OPTION = 'sugar_calendar_ticketing_has_coupon_orders';

	/**
	 * User meta that marks the column as offered to the user.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const USER_MARKER = 'sugar_calendar_table_orders_discount_default';

	/**
	 * User meta that holds the visible columns of the Orders list.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const ACTIVE_COLUMNS_META = 'sugar_calendar_table_orders_active_columns';

	/**
	 * Answer of the first check in this request.
	 *
	 * @since 3.17.0
	 *
	 * @var bool|null
	 */
	private $available;

	/**
	 * Whether the site has an order that used a coupon, in any status.
	 *
	 * @since 3.17.0
	 *
	 * @return bool
	 */
	public function is_available() {

		if ( $this->available !== null ) {
			return $this->available;
		}

		$this->available = (bool) get_option( self::OPTION, false );

		if ( $this->available ) {
			return true;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One indexed lookup, recorded in an autoloaded option on a hit.
		$found = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}sc_orders WHERE discount_id > 0 LIMIT 1" );

		$this->available = ! empty( $found );

		if ( $this->available ) {
			update_option( self::OPTION, 1, true );
		}

		return $this->available;
	}

	/**
	 * Add the Discount column right after Total.
	 *
	 * @since 3.17.0
	 *
	 * @param array $columns Orders list columns.
	 *
	 * @return array
	 */
	public function insert( array $columns ) {

		if ( ! $this->is_available() ) {
			return $columns;
		}

		$retval = [];

		foreach ( $columns as $key => $label ) {
			$retval[ $key ] = $label;

			if ( $key === 'total' ) {
				$retval['discount'] = esc_html__( 'Discount', 'sugar-calendar-lite' );
			}
		}

		return $retval;
	}

	/**
	 * Show the column by default the first time it is offered to a user.
	 *
	 * Later saves, including unticking it, are never touched.
	 *
	 * @since 3.17.0
	 *
	 * @param int $user_id User ID.
	 */
	public function apply_user_default( $user_id ) {

		if ( ! $user_id || ! $this->is_available() || get_user_meta( $user_id, self::USER_MARKER, true ) ) {
			return;
		}

		$active = get_user_meta( $user_id, self::ACTIVE_COLUMNS_META, true );

		if ( is_array( $active ) && ! in_array( 'discount', $active, true ) ) {
			$active[] = 'discount';

			update_user_meta( $user_id, self::ACTIVE_COLUMNS_META, $active );
		}

		update_user_meta( $user_id, self::USER_MARKER, 1 );
	}

	/**
	 * Cell HTML of an order.
	 *
	 * @since 3.17.0
	 *
	 * @param object $order Order.
	 *
	 * @return string
	 */
	public function cell( $order ) {

		$discount = OrderDiscount::from_order( $order );

		if ( ! $discount->applies() ) {
			return '-';
		}

		$retval = esc_html( $discount->negative_amount() );

		if ( $discount->code() !== '' ) {
			$retval .= '<br><code>' . esc_html( $discount->code() ) . '</code>';
		}

		return $retval;
	}
}
