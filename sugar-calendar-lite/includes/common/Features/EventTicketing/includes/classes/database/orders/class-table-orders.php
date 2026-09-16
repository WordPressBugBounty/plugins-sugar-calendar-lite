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
	 * @since 3.12.0 Updated to `202607020001`.
	 * @since 3.12.0 Updated to `202607080001`.
	 */
	protected $version = 202609010001;

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
	 * @since 3.12.0 Added `202607020001` upgrade.
	 * @since 3.12.0 Added `202607080001` upgrade.
	 */
	protected $upgrades = [
		'202010270001' => 202010270001,
		'202010270002' => 202010270002,
		'202010270003' => 202010270003,
		'202010270004' => 202010270004,
		'202501150001' => 202501150001,
		'202605070001' => 202605070001,
		'202607020001' => 202607020001,
		'202607080001' => 202607080001,
		'202609010001' => 202609010001,
	];

	/**
	 * Setup the database schema.
	 *
	 * @since 1.0.0
	 * @since 3.6.0 Added `occurrence_id` column.
	 */
	protected function set_schema() {

		$this->schema = "id bigint(20) unsigned NOT NULL auto_increment,
			transaction_id varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
			status varchar(20) NOT NULL default '',
			currency varchar(20) NOT NULL default '',
			discount_id bigint(20) unsigned NOT NULL default '0',
			email varchar(254) NOT NULL default '',
			first_name varchar(100) NOT NULL default '',
			last_name varchar(100) NOT NULL default '',
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
			gateway varchar(20) NOT NULL default 'stripe',
			PRIMARY KEY (id),
			KEY `email` (email),
			UNIQUE KEY `transaction_id` (`transaction_id`)";
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

	/**
	 * Upgrade to version 202607020001.
	 *
	 * Add the `gateway` varchar column recording which payment gateway processed
	 * the order, so refunds can be dispatched to the right processor.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	protected function __202607020001() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		// Look for column
		$result = $this->column_exists( 'gateway' );

		// Maybe add column
		if ( false === $result ) {
			$result = $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN `gateway` varchar(20) NOT NULL default 'stripe' AFTER `uuid`;" );
		}

		// Return success/fail
		return $this->is_success( $result );
	}

	/**
	 * Upgrade to version 202607080001.
	 *
	 * Add a UNIQUE constraint on `transaction_id` as the DB-level last line of
	 * defense against Stripe PaymentIntent replay (#650, follow-up to #520).
	 * Uses NULL (not '') as the "no transaction" sentinel so free orders don't
	 * collide. Pre-existing duplicate transaction ids (from sites exploited
	 * before the #520 app-level fix) are de-collided non-destructively:
	 * recorded to an option, then the later duplicates are rewritten to a
	 * unique `sc_dup_<id>` value so the constraint can be added. Orders and
	 * tickets are never deleted.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	protected function __202607080001() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		$db    = $this->get_db();
		$table = $this->table_name;

		// 1. Idempotency — already constrained?
		$has_index = $db->get_var( $db->prepare(
			"SELECT COUNT(*)
			   FROM INFORMATION_SCHEMA.STATISTICS
			  WHERE TABLE_SCHEMA = %s
			    AND TABLE_NAME   = %s
			    AND INDEX_NAME   = 'transaction_id'",
			DB_NAME,
			$table
		) );

		if ( ! empty( $has_index ) ) {
			return $this->is_success( true );
		}

		// 2. Make the column nullable (keep the collation set by 202605070001).
		$nullable = $db->query(
			"ALTER TABLE {$table}
			 MODIFY COLUMN `transaction_id`
			 varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
			 NULL DEFAULT NULL"
		);

		// Bail (and allow a retry on the next admin_init) rather than proceed to
		// de-collide/ADD UNIQUE against a column that is still NOT NULL — that
		// would brick free-order inserts with no automatic way to re-run this.
		if ( false === $nullable ) {
			return $this->is_success( false );
		}

		// 3. Empty-string sentinels -> NULL (MySQL allows many NULLs but one '').
		$db->query( "UPDATE {$table} SET transaction_id = NULL WHERE transaction_id = ''" );

		// 4. Capture the rows we will rewrite (every non-earliest duplicate),
		// with the original pi, BEFORE overwriting. Set-based (no GROUP_CONCAT
		// truncation if one pi was replayed many times).
		$dupes = $db->get_results(
			"SELECT o.id AS id, o.transaction_id AS pi
			   FROM {$table} o
			   JOIN ( SELECT transaction_id, MIN(id) AS keep_id
			            FROM {$table}
			           WHERE transaction_id IS NOT NULL
			           GROUP BY transaction_id
			          HAVING COUNT(*) > 1 ) d
			     ON o.transaction_id = d.transaction_id
			    AND o.id <> d.keep_id",
			ARRAY_A
		);

		// 5. Record + 6. rewrite.
		if ( ! empty( $dupes ) ) {

			$record = [];
			foreach ( $dupes as $row ) {
				$record[ (int) $row['id'] ] = $row['pi'];
			}
			update_option( 'sc_et_dedup_transaction_ids', $record, false );

			foreach ( $dupes as $row ) {
				$db->query( $db->prepare(
					"UPDATE {$table} SET transaction_id = %s WHERE id = %d",
					'sc_dup_' . (int) $row['id'],
					(int) $row['id']
				) );
			}
		}

		// 7. Re-check — never fire ADD UNIQUE unless the column is collision-free.
		$still = $db->get_var(
			"SELECT transaction_id
			   FROM {$table}
			  WHERE transaction_id IS NOT NULL
			  GROUP BY transaction_id
			 HAVING COUNT(*) > 1
			  LIMIT 1"
		);

		if ( ! empty( $still ) ) {
			// Should be impossible (sc_dup_<id> is PK-unique). Degrade honestly:
			// keep the app-level guard, record the anomaly, do NOT loop.
			error_log( '[SC #650] sc_orders still has duplicate transaction_id values after de-duplication; UNIQUE index not added. App-level replay guard remains active.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			update_option( 'sc_et_dedup_transaction_ids_anomaly', gmdate( 'Y-m-d H:i:s' ), false );

			return $this->is_success( true );
		}

		// 8. Add the constraint.
		$result = $db->query(
			"ALTER TABLE {$table}
			 ADD UNIQUE KEY `transaction_id` (`transaction_id`)"
		);

		return $this->is_success( $result );
	}

	/**
	 * Upgrade to version 202609010001
	 * - Widen `first_name` and `last_name` to 100, `email` to 254.
	 *
	 * Mirrors the same widening on `sc_attendees`. The buyer's own name and email
	 * reach this table through a different POST path from the attendee fields, so
	 * both tables have to move together.
	 *
	 * @since 3.14.0
	 *
	 * @return boolean
	 */
	protected function __202609010001() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		$db    = $this->get_db();
		$table = $this->table_name;

		$width = $db->get_var( $db->prepare(
			"SELECT CHARACTER_MAXIMUM_LENGTH
			   FROM INFORMATION_SCHEMA.COLUMNS
			  WHERE TABLE_SCHEMA = %s
			    AND TABLE_NAME   = %s
			    AND COLUMN_NAME  = 'first_name'",
			DB_NAME,
			$table
		) );

		$has_index = $db->get_var( $db->prepare(
			"SELECT COUNT(*)
			   FROM INFORMATION_SCHEMA.STATISTICS
			  WHERE TABLE_SCHEMA = %s
			    AND TABLE_NAME   = %s
			    AND INDEX_NAME   = 'email'",
			DB_NAME,
			$table
		) );

		// Idempotency covers the index too, not just the width: a run interrupted
		// between the widen and the re-index would otherwise satisfy a width-only
		// check and report success, leaving the column permanently un-indexed.
		if ( (int) $width >= 100 && ! empty( $has_index ) ) {
			return $this->is_success( true );
		}

		// The `email` KEY spans the whole column. At utf8mb4, 254 chars is 1016
		// bytes, over the 767-byte index limit on COMPACT/REDUNDANT tables, so
		// widening underneath the full-column key would fail there and the upgrade
		// would retry forever. Drop the key, widen, then re-add it as a 191-char
		// prefix (WordPress core's utf8mb4-safe number). Lookups here are
		// exact-match, so the prefix costs nothing.
		//
		// Order matters: MySQL rejects a prefix longer than the column, so the
		// index cannot be re-added until after the widen.
		if (
			! empty( $has_index )
			&&
			false === $db->query( "ALTER TABLE {$table} DROP INDEX `email`" )
		) {
			return $this->is_success( false );
		}

		// Collation is named explicitly: a bare MODIFY inherits the TABLE default,
		// which silently rewrites a column whose collation differs. This table
		// already holds one such column (`transaction_id`).
		$result = $db->query(
			"ALTER TABLE {$table}
			 MODIFY COLUMN `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
			 MODIFY COLUMN `last_name`  varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
			 MODIFY COLUMN `email`      varchar(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT ''"
		);

		if ( false === $result ) {
			// The column is still its original width here, so the key has to go
			// back un-prefixed: a 191-char prefix is longer than the column and
			// MySQL would reject it, leaving the table with no `email` index and
			// the next retry bailing on the DROP.
			$db->query( "ALTER TABLE {$table} ADD INDEX `email` (`email`)" );

			return $this->is_success( false );
		}

		$reindexed = $db->query( "ALTER TABLE {$table} ADD INDEX `email` (`email`(191))" );

		return $this->is_success( $reindexed );
	}
}
