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
 * Discount Schema Class.
 *
 * @since 1.0
 */
final class Discount_Schema extends Schema {

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

		// code
		array(
			'name'       => 'code',
			'type'       => 'varchar',
			'length'     => '100',
			'default'    => '',
			'sortable'   => true,
			'searchable' => true
		),

		// code_key.
		[
			'name'       => 'code_key',
			'type'       => 'varchar',
			'length'     => '100',
			'default'    => '',
			'sortable'   => false,
			'searchable' => false,
		],

		// name.
		[
			'name'       => 'name',
			'type'       => 'varchar',
			'length'     => '255',
			'default'    => '',
			'searchable' => true,
		],

		// amount
		array(
			'name'       => 'amount',
			'type'       => 'decimal',
			'length'     => '18,9',
			'default'    => '0',
			'sortable'   => true
		),

		// type
		array(
			'name'       => 'type',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => 'percentage',
			'sortable'   => true
		),

		// start_date.
		[
			'name'       => 'start_date',
			'type'       => 'date',
			'default'    => null,
			'allow_null' => true,
			'sortable'   => true,
		],

		// end_date.
		[
			'name'       => 'end_date',
			'type'       => 'date',
			'default'    => null,
			'allow_null' => true,
			'sortable'   => true,
		],

		// usage_limit.
		[
			'name'     => 'usage_limit',
			'type'     => 'int',
			'length'   => '10',
			'unsigned' => true,
			'default'  => '0',
			'sortable' => true,
		],

		// paused.
		[
			'name'     => 'paused',
			'type'     => 'tinyint',
			'length'   => '1',
			'unsigned' => true,
			'default'  => '0',
		],

		// date_created
		array(
			'name'       => 'date_created',
			'type'       => 'datetime',
			'default'    => '0000-00-00 00:00:00',
			'created'    => true,
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
		)
	);
}
