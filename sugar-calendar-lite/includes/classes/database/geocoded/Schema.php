<?php
/**
 * Geocoded Schema Class.
 *
 * @package     Sugar Calendar
 * @subpackage  Database\Schemas
 * @since       3.11.1
 */
namespace Sugar_Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Database\Schema;

/**
 * Geocoded Schema Class.
 *
 * Column metadata for the wp_sc_geocoded table — the canonical address→coords
 * map for the Google Maps feature. See `Geocoded_Table::set_schema()` for the
 * raw CREATE TABLE statement.
 *
 * @since 3.11.1
 */
final class Geocoded_Schema extends Schema {

	/**
	 * Array of database column objects.
	 *
	 * @since 3.11.1
	 * @access public
	 * @var array
	 */
	public $columns = [
		[
			'name'     => 'id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'extra'    => 'auto_increment',
			'primary'  => true,
			'sortable' => true,
		],
		[
			'name'       => 'address_hash',
			'type'       => 'char',
			'length'     => '32',
			'default'    => '',
			'searchable' => true,
		],
		[
			'name'       => 'address',
			'type'       => 'varchar',
			'length'     => '255',
			'default'    => '',
			'searchable' => true,
		],
		[
			'name'    => 'lat',
			'type'    => 'decimal',
			'length'  => '10,7',
			'default' => '0',
		],
		[
			'name'    => 'lng',
			'type'    => 'decimal',
			'length'  => '10,7',
			'default' => '0',
		],
	];
}
