<?php
/**
 * Events Database: WP_DB_Table_Events class
 *
 * @package Plugins/Events/Database/Object
 */
namespace Sugar_Calendar\AddOn\Ticketing\Database;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Database\Table;

/**
 * Setup the global "discounts" database table.
 *
 * @since 3.17.0
 */
final class Discounts_Table extends Table {

	/**
	 * Table name.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	protected $name = 'discounts';

	/**
	 * Database version.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	protected $version = 202610300001;

	/**
	 * Table schema.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	protected $schema = __NAMESPACE__ . '\\Discount_Schema';

	/**
	 * Array of upgrade versions and methods.
	 *
	 * @since 3.17.0
	 *
	 * @var array
	 */
	protected $upgrades = [
		'202010270001' => 202010270001,
		'202010270002' => 202010270002,
		'202010270003' => 202010270003,
		'202610300001' => 202610300001,
	];

	/**
	 * Setup the database schema.
	 *
	 * @since 3.17.0
	 */
	protected function set_schema() {

		$this->schema = "id bigint(20) unsigned NOT NULL auto_increment,
			code varchar(100) NOT NULL default '',
			code_key varchar(100) NOT NULL default '',
			name varchar(255) NOT NULL default '',
			amount decimal(18,9) NOT NULL default '0',
			type varchar(20) NOT NULL default 'percentage',
			start_date date NULL default NULL,
			end_date date NULL default NULL,
			usage_limit int(10) unsigned NOT NULL default 0,
			paused tinyint(1) unsigned NOT NULL default 0,
			date_created datetime NOT NULL default '0000-00-00 00:00:00',
			date_modified datetime NOT NULL default '0000-00-00 00:00:00',
			uuid varchar(100) NOT NULL default '',
			PRIMARY KEY (id),
			KEY code_key (code_key)";
	}

	/**
	 * Upgrade to version 202010270001.
	 * - Add the `date_created` datetime column.
	 *
	 * @since 3.17.0
	 *
	 * @return boolean
	 */
	protected function __202010270001() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		// Look for column.
		$result = $this->column_exists( 'date_created' );

		// Maybe add column.
		if ( $result === false ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `date_created` datetime NOT NULL default '0000-00-00 00:00:00' AFTER `type`;" );
		}

		// Return success/fail.
		return $this->is_success( $result );
	}

	/**
	 * Upgrade to version 202010270002.
	 * - Add the `date_modified` datetime column.
	 *
	 * @since 3.17.0
	 *
	 * @return boolean
	 */
	protected function __202010270002() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		// Look for column.
		$result = $this->column_exists( 'date_modified' );

		// Maybe add column.
		if ( $result === false ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `date_modified` datetime NOT NULL default '0000-00-00 00:00:00' AFTER `date_created`;" );
		}

		// Return success/fail.
		return $this->is_success( $result );
	}

	/**
	 * Upgrade to version 202010270003.
	 * - Add the `uuid` varchar column.
	 *
	 * @since 3.17.0
	 *
	 * @return boolean
	 */
	protected function __202010270003() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		// Look for column.
		$result = $this->column_exists( 'uuid' );

		// Maybe add column.
		if ( $result === false ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `uuid` varchar(100) default '' AFTER `date_modified`;" );
		}

		// Return success/fail.
		return $this->is_success( $result );
	}

	/**
	 * Upgrade to version 202610300001
	 * - Add the coupon columns, narrow `code` to 100, default `type` to `percentage`.
	 * - Fill `code_key` from `code`, then index it.
	 *
	 * Idempotent: a run stopped by a failed ALTER completes on the next request.
	 *
	 * @since 3.17.0
	 *
	 * @return boolean
	 */
	protected function __202610300001() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		// `code` is narrowed first: a 1000-character utf8mb4 key is over InnoDB's limit.
		if (
			! $this->narrow_code_and_type()
			||
			! $this->add_coupon_columns()
		) {
			return $this->is_success( false );
		}

		$db = $this->get_db();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the schema class.
		$db->query( "UPDATE {$this->table_name} SET `code_key` = LOWER( TRIM( `code` ) ) WHERE `code_key` = ''" );

		return $this->is_success( $this->add_code_key_index() );
	}

	/**
	 * Narrow `code` to 100 characters and default `type` to `percentage`, when they differ.
	 *
	 * @since 3.17.0
	 *
	 * @return bool False when an ALTER failed.
	 */
	private function narrow_code_and_type() {

		$db = $this->get_db();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the schema class.
		$code = $db->get_row( "SHOW COLUMNS FROM {$this->table_name} LIKE 'code'", ARRAY_A );

		if ( $code && $code['Type'] !== 'varchar(100)' ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the schema class.
			if ( $db->query( "ALTER TABLE {$this->table_name} MODIFY COLUMN `code` varchar(100) NOT NULL default ''" ) === false ) {
				return false;
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the schema class.
		$type = $db->get_row( "SHOW COLUMNS FROM {$this->table_name} LIKE 'type'", ARRAY_A );

		if ( $type && $type['Default'] !== 'percentage' ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the schema class.
			return $db->query( "ALTER TABLE {$this->table_name} MODIFY COLUMN `type` varchar(20) NOT NULL default 'percentage'" ) !== false;
		}

		return true;
	}

	/**
	 * Add each coupon column that is missing.
	 *
	 * @since 3.17.0
	 *
	 * @return bool False when an ALTER failed.
	 */
	private function add_coupon_columns() {

		$columns = [
			'code_key'    => "varchar(100) NOT NULL default '' AFTER `code`",
			'name'        => "varchar(255) NOT NULL default '' AFTER `code_key`",
			'start_date'  => 'date NULL default NULL AFTER `type`',
			'end_date'    => 'date NULL default NULL AFTER `start_date`',
			'usage_limit' => 'int(10) unsigned NOT NULL default 0 AFTER `end_date`',
			'paused'      => 'tinyint(1) unsigned NOT NULL default 0 AFTER `usage_limit`',
		];

		foreach ( $columns as $column => $definition ) {
			if ( $this->column_exists( $column ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and definitions are literals.
			if ( $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `{$column}` {$definition}" ) === false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Add the `code_key` index when it is missing.
	 *
	 * @since 3.17.0
	 *
	 * @return bool False when the ALTER failed.
	 */
	private function add_code_key_index() {

		if ( $this->index_exists( 'code_key' ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the schema class.
		return $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD INDEX `code_key` (`code_key`)" ) !== false;
	}
}
