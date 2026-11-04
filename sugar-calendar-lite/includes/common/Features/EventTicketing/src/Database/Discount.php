<?php
/**
 * Tickets Row Class.
 *
 * @package     Sugar Calendar
 * @subpackage  Database\Schemas
 * @since       3.17.0
 */
namespace Sugar_Calendar\AddOn\Ticketing\Database;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Database\Row;

/**
 * Discount class.
 *
 * @since 3.17.0
 */
final class Discount extends Row {

	/**
	 * Discount ID.
	 *
	 * @since 3.17.0
	 *
	 * @access public
	 * @var int
	 */
	public $id;

	/**
	 * Code as the admin typed it.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Trimmed, lower-cased code used for lookups.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $code_key;

	/**
	 * Admin-only name.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Discount amount, a decimal string.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $amount;

	/**
	 * Discount type: `percentage` or `flat`.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $type;

	/**
	 * First day the coupon works (`Y-m-d`), null for none.
	 *
	 * @since 3.17.0
	 *
	 * @var string|null
	 */
	public $start_date;

	/**
	 * Last day the coupon works (`Y-m-d`), null for none.
	 *
	 * @since 3.17.0
	 *
	 * @var string|null
	 */
	public $end_date;

	/**
	 * Maximum uses, 0 for unlimited.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	public $usage_limit;

	/**
	 * 1 when the coupon is paused.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	public $paused;

	/**
	 * Created date, set once.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $date_created;

	/**
	 * Last modified date.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $date_modified;

	/**
	 * Row UUID.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	public $uuid;
}
