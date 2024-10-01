<?php
/**
 * Events Database: WP_DB_Table_Events class
 *
 * @package Plugins/Events/Database/Object
 */
namespace Sugar_Calendar\AddOn\Ticketing\Database;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Database\Table;

/**
 * Setup the global "events" database table
 *
 * @since 1.0.0
 */
final class Attendees_Table extends Table {

	/**
	 * @var string Table name
	 */
	protected $name = 'attendees';

	/**
	 * @var string Database version
	 */
	protected $version = 202010270003;

	/**
	 * @var string Table schema
	 */
	protected $schema = __NAMESPACE__ . '\\Attendee_Schema';

	/**
	 * @var array Array of upgrade versions and methods.
	 */
	protected $upgrades = array(
		'202010270001' => 202010270001,
		'202010270002' => 202010270002,
		'202010270003' => 202010270003,
	);

	/**
	 * Setup the database schema
	 *
	 * @since 1.0.0
	 */
	protected function set_schema() {
		$this->schema = "id bigint(20) unsigned NOT NULL auto_increment,
			email varchar(100) NOT NULL default '',
			first_name varchar(20) NOT NULL default '',
			last_name varchar(20) NOT NULL default '',
			date_created datetime NOT NULL default '0000-00-00 00:00:00',
			date_modified datetime NOT NULL default '0000-00-00 00:00:00',
			uuid varchar(100) NOT NULL default '',
			PRIMARY KEY (id)";
	}

	/**
	 * Upgrade to version 202010270001
	 * - Add the `date_created` datetime column
	 *
	 * @since 1.1.0
	 *
	 * @return boolean
	 */
	protected function __202010270001() {

		// Look for column
		$result = $this->column_exists( 'date_created' );

		// Maybe add column
		if ( false === $result ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `date_created` datetime NOT NULL default '0000-00-00 00:00:00' AFTER `type`;" );
		}

		// Return success/fail
		return $this->is_success( $result );
	}

	/**
	 * Upgrade to version 202010270002
	 * - Add the `date_modified` datetime column
	 *
	 * @since 1.1.0
	 *
	 * @return boolean
	 */
	protected function __202010270002() {

		// Look for column
		$result = $this->column_exists( 'date_modified' );

		// Maybe add column
		if ( false === $result ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `date_modified` datetime NOT NULL default '0000-00-00 00:00:00' AFTER `date_created`;" );
		}

		// Return success/fail
		return $this->is_success( $result );
	}

	/**
	 * Upgrade to version __202010270004
	 * - Add the `uuid` varchar column
	 *
	 * @since 1.1.0
	 *
	 * @return boolean
	 */
	protected function __202010270003() {

		// Look for column
		$result = $this->column_exists( 'uuid' );

		// Maybe add column
		if ( false === $result ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `uuid` varchar(100) default '' AFTER `date_modified`;" );
		}

		// Return success/fail
		return $this->is_success( $result );
	}
}
