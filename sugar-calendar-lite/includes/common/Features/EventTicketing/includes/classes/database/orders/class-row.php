<?php
/**
 * Tickets Row Class.
 *
 * @package     Sugar Calendar
 * @subpackage  Database\Schemas
 * @since       1.0
 */
namespace Sugar_Calendar\AddOn\Ticketing\Database;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Database\Row;

/**
 * Order Class
 *
 * @since 1.0.0
 */
final class Order extends Row {

	/**
	 * Order ID.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var int
	 */
	public $id;

	/**
	 * Subtotal before discount and tax.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $subtotal;

	/**
	 * Discount amount.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $discount;

	/**
	 * Discount (coupon) ID, 0 for none.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	public $discount_id;

	/**
	 * Coupon code as used at checkout.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $discount_code;
}