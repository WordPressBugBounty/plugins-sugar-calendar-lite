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
	protected $version = 202609010001;

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
		'202609010001' => 202609010001,
	);

	/**
	 * Setup the database schema
	 *
	 * @since 1.0.0
	 */
	protected function set_schema() {
		$this->schema = "id bigint(20) unsigned NOT NULL auto_increment,
			email varchar(254) NOT NULL default '',
			first_name varchar(100) NOT NULL default '',
			last_name varchar(100) NOT NULL default '',
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

	/**
	 * Upgrade to version 202609010001
	 * - Widen `first_name` and `last_name` to 100, `email` to 254.
	 *
	 * A name longer than the old 20-character columns failed the insert, which
	 * surfaced as a fatal mid-checkout. 254 is the RFC 5321 maximum address length.
	 *
	 * @since 3.14.0
	 *
	 * @return boolean
	 */
	protected function __202609010001() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		$db    = $this->get_db();
		$table = $this->table_name;

		// Idempotency: already widened?
		$width = $db->get_var( $db->prepare(
			"SELECT CHARACTER_MAXIMUM_LENGTH
			   FROM INFORMATION_SCHEMA.COLUMNS
			  WHERE TABLE_SCHEMA = %s
			    AND TABLE_NAME   = %s
			    AND COLUMN_NAME  = 'first_name'",
			DB_NAME,
			$table
		) );

		if ( (int) $width >= 100 ) {
			return $this->is_success( true );
		}

		// Collation is named explicitly: a bare MODIFY inherits the TABLE default,
		// which silently rewrites a column whose collation differs.
		$result = $db->query(
			"ALTER TABLE {$table}
			 MODIFY COLUMN `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
			 MODIFY COLUMN `last_name`  varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
			 MODIFY COLUMN `email`      varchar(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT ''"
		);

		return $this->is_success( $result );
	}
}
