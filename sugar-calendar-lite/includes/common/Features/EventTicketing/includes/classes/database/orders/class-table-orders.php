<?php
/**
 * Orders Database: WP_DB_Table_Events class
 *
 * @package Plugins/Events/Database/Object
 */
namespace Sugar_Calendar\AddOn\Ticketing\Database;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Database\Table;

/**
 * Setup the global "orders" database table
 *
 * @since 1.0.0
 */
final class Orders_Table extends Table {

	/**
	 * @var string Table name
	 */
	protected $name = 'orders';

	/**
	 * Database version.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 * @since 3.6.0 Updated to `202501150001`.
	 * @since 3.11.1 Updated to `202605070001`.
	 */
	protected $version = 202605070001;

	/**
	 * @var string Table schema
	 */
	protected $schema = __NAMESPACE__ . '\\Order_Schema';

	/**
	 * Array of upgrade versions and methods.
	 *
	 * @var array
	 *
	 * @since 1.0.0
	 * @since 3.6.0 Added `202010270003` upgrade.
	 * @since 3.11.1 Added `202605070001` upgrade.
	 */
	protected $upgrades = [
		'202010270001' => 202010270001,
		'202010270002' => 202010270002,
		'202010270003' => 202010270003,
		'202010270004' => 202010270004,
		'202501150001' => 202501150001,
		'202605070001' => 202605070001,
	];

	/**
	 * Setup the database schema.
	 *
	 * @since 1.0.0
	 * @since 3.6.0 Added `occurrence_id` column.
	 */
	protected function set_schema() {

		$this->schema = "id bigint(20) unsigned NOT NULL auto_increment,
			transaction_id varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL default '',
			status varchar(20) NOT NULL default '',
			currency varchar(20) NOT NULL default '',
			discount_id bigint(20) unsigned NOT NULL default '0',
			email varchar(100) NOT NULL default '',
			first_name varchar(20) NOT NULL default '',
			last_name varchar(20) NOT NULL default '',
			subtotal decimal(18,9) NOT NULL default '0',
			discount decimal(18,9) NOT NULL default '0',
			tax decimal(18,9) NOT NULL default '0',
			total decimal(18,9) NOT NULL default '0',
			event_id bigint(20) unsigned NOT NULL default '0',
			occurrence_id bigint(20) unsigned NOT NULL default 0,
			event_date datetime NOT NULL default '0000-00-00 00:00:00',
			checkout_type varchar(20) NOT NULL default 'core',
			checkout_id bigint(20) unsigned NOT NULL default '0',
			date_created datetime NOT NULL default '0000-00-00 00:00:00',
			date_modified datetime NOT NULL default '0000-00-00 00:00:00',
			date_paid datetime NOT NULL default '0000-00-00 00:00:00',
			uuid varchar(100) NOT NULL default '',
			PRIMARY KEY (id),
			KEY `email` (email)";
	}

	/**
	 * Upgrade to version 202010270001
	 * - Add `checkout_type` column.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	protected function __202010270001() {

		// Alter the database
		$result = $this->column_exists( 'checkout_type' );

		// Maybe add column
		if ( false === $result ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `checkout_type` varchar(20) NOT NULL default 'core' AFTER `status`;" );
		}

		// Return success/fail
		return $this->is_success( true );
	}

	/**
	 * Upgrade to version 202010270002
	 * - Add `checkout_id` column.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	protected function __202010270002() {

		// Alter the database
		$result = $this->column_exists( 'checkout_id' );
		$after  = $this->column_exists( 'checkout_type' );

		// Maybe add column
		if ( ( false === $result ) && ( true === $after ) ) {
			$this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `checkout_id` bigint(20) unsigned NOT NULL default '0' AFTER `checkout_type`;" );
		}

		// Return success/fail
		return $this->is_success( true );
	}

	/**
	 * Upgrade to version 202010270003
	 * - Add the `date_modified` datetime column
	 *
	 * @since 1.1.0
	 *
	 * @return boolean
	 */
	protected function __202010270003() {

		// Look for column
		$result = $this->column_exists( 'date_modified' );

		// Maybe add column
		if ( false === $result ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `date_modified` datetime NOT NULL default '0000-00-00 00:00:00' AFTER `date_paid`;" );
		}

		// Return success/fail
		return $this->is_success( $result );
	}

	/**
	 * Upgrade to version 202010270004
	 * - Add the `uuid` varchar column
	 *
	 * @since 1.1.0
	 *
	 * @return boolean
	 */
	protected function __202010270004() {

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
	 * Upgrade to version 202501150001.
	 *
	 * Add the `occurrence_id` column.
	 *
	 * @since 3.6.0
	 *
	 * @return bool
	 */
	protected function __202501150001() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		$result = $this->column_exists( 'occurrence_id' );

		if ( $result === false ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `occurrence_id` bigint(20) unsigned NOT NULL default 0 AFTER `event_id`;" );
		}

		return $this->is_success( $result );
	}

	/**
	 * Upgrade to version 202605070001.
	 *
	 * Force `transaction_id` to utf8mb4 / utf8mb4_unicode_ci so replay-prevention
	 * lookups are case-insensitive on every install regardless of DB defaults.
	 *
	 * @since 3.11.1
	 *
	 * @return bool
	 */
	protected function __202605070001() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		$db = $this->get_db();

		$current = $db->get_var( $db->prepare(
			"SELECT COLLATION_NAME
			   FROM INFORMATION_SCHEMA.COLUMNS
			  WHERE TABLE_SCHEMA = %s
			    AND TABLE_NAME   = %s
			    AND COLUMN_NAME  = 'transaction_id'",
			DB_NAME,
			$this->table_name
		) );

		if ( $current === 'utf8mb4_unicode_ci' ) {
			return $this->is_success( true );
		}

		$result = $db->query(
			"ALTER TABLE {$this->table_name}
			 MODIFY COLUMN `transaction_id`
			 varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
			 NOT NULL DEFAULT ''"
		);

		return $this->is_success( $result );
	}
}
