<?php
/**
 * Events Schema Class.
 *
 * @package     Sugar Calendar
 * @subpackage  Database\Schemas
 * @since       1.0
 */
namespace Sugar_Calendar\AddOn\Ticketing\Database;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Database\Schema;

/**
 * Order Schema Class.
 *
 * @since 1.0
 */
final class Order_Schema extends Schema {

	/**
	 * Array of database column objects
	 *
	 * @since 1.0
	 * @access public
	 * @var array
	 */
	public $columns = array(

		// id
		array(
			'name'       => 'id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'extra'      => 'auto_increment',
			'primary'    => true,
			'sortable'   => true
		),

		// transaction_id
		array(
			'name'       => 'transaction_id',
			'type'       => 'varchar',
			'length'     => '100',
			'searchable' => true,
			'sortable'   => true
		),

		// status
		array(
			'name'       => 'status',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => 'pending',
			'searchable' => true,
			'sortable'   => true,
			'transition' => true
		),

		// currency
		array(
			'name'       => 'currency',
			'type'       => 'varchar',
			'length'     => '20'
		),

		// discount_id
		array(
			'name'       => 'discount_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'default'    => '0',
			'sortable'   => false
		),

		// email
		array(
			'name'       => 'email',
			'type'       => 'varchar',
			'length'     => '100',
			'searchable' => true,
			'sortable'   => true
		),

		// first_name
		array(
			'name'       => 'first_name',
			'type'       => 'varchar',
			'length'     => '100',
			'searchable' => true,
			'sortable'   => true
		),

		// last_name
		array(
			'name'       => 'last_name',
			'type'       => 'varchar',
			'length'     => '100',
			'searchable' => true,
			'sortable'   => true
		),

		// subtotal
		array(
			'name'       => 'subtotal',
			'type'       => 'decimal',
			'length'     => '18,9',
			'default'    => '0',
			'sortable'   => true
		),

		// discount
		array(
			'name'       => 'discount',
			'type'       => 'decimal',
			'length'     => '18,9',
			'default'    => '0',
			'sortable'   => true
		),

		// tax
		array(
			'name'       => 'tax',
			'type'       => 'decimal',
			'length'     => '18,9',
			'default'    => '0',
			'sortable'   => true
		),

		// total
		array(
			'name'       => 'total',
			'type'       => 'decimal',
			'length'     => '18,9',
			'default'    => '0',
			'searchable' => true,
			'sortable'   => true
		),

		// event_id
		array(
			'name'       => 'event_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'default'    => '0',
			'sortable'   => false
		),

		// event_date
		array(
			'name'       => 'event_date',
			'type'       => 'datetime',
			'default'    => '0000-00-00 00:00:00',
			'date_query' => true,
			'sortable'   => true
		),

		// checkout_type
		array(
			'name'       => 'checkout_type',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => 'core',
			'searchable' => false,
			'sortable'   => false
		),

		// checkout_id
		array(
			'name'       => 'checkout_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'default'    => '0',
			'sortable'   => false,
			'searchable' => true
		),

		// date_created
		array(
			'name'       => 'date_created',
			'type'       => 'datetime',
			'default'    => '0000-00-00 00:00:00',
			'created'    => true,
			'date_query' => true,
			'sortable'   => true
		),

		// date_paid
		array(
			'name'       => 'date_paid',
			'type'       => 'datetime',
			'default'    => '0000-00-00 00:00:00',
			'date_query' => true,
			'sortable'   => true
		),

		// date_modified
		array(
			'name'       => 'date_modified',
			'type'       => 'datetime',
			'default'    => '0000-00-00 00:00:00',
			'modified'   => true,
			'date_query' => true,
			'sortable'   => true
		),

		// uuid
		array(
			'uuid'       => true,
		),

		array(
			'name'     => 'occurrence_id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'default'  => '0',
			'sortable' => true,
		),
	);
}
