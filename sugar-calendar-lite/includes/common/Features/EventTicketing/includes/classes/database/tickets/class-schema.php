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
 * Ticket Schema Class.
 *
 * @since 1.0
 */
final class Ticket_Schema extends Schema {

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
			'searchable' => true,
			'sortable'   => true
		),

		// order_id
		array(
			'name'       => 'order_id',
			'type'       => 'varchar',
			'length'     => '20',
			'unsigned'   => true,
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
			'sortable'   => true
		),

		// attendee_id
		array(
			'name'       => 'attendee_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'default'    => '0',
			'sortable'   => true
		),

		// code
		array(
			'name'       => 'code',
			'type'       => 'varchar',
			'length'     => '20',
			'unsigned'   => true,
			'default'    => '',
			'searchable' => true,
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
