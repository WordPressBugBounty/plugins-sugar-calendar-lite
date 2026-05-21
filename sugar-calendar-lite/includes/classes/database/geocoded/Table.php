<?php
/**
 * Geocoded Database: Geocoded_Table class.
 *
 * @package Plugins/Events/Database/Object
 */
namespace Sugar_Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Database\Table;

/**
 * Setup the "geocoded" database table — caches the address→lat/lng map for the
 * Google Maps feature, replacing the previous `_transient_scgm_*` storage.
 * Renamed from a transient cache to a custom table so the cache is durable
 * (not cleared by transient-cleanup plugins) and can be gated by capability
 * checks at the write surface.
 *
 * @since 3.11.1
 */
final class Geocoded_Table extends Table {

	/**
	 * @var string Table name.
	 */
	protected $name = 'geocoded';

	/**
	 * @var string Database version.
	 */
	protected $version = 202605140001;

	/**
	 * @var string Table schema class.
	 */
	protected $schema = __NAMESPACE__ . '\\Geocoded_Schema';

	/**
	 * Setup the database schema.
	 *
	 * `address_hash` is the unique lookup key (md5 of the address string).
	 * `lat` and `lng` use DECIMAL(10, 7) so the DB enforces type and bounds
	 * — values outside the legal coordinate range cannot land in the column.
	 *
	 * @since 3.11.1
	 */
	protected function set_schema() {

		$this->schema = "id bigint(20) unsigned NOT NULL auto_increment,
			address_hash char(32) NOT NULL default '',
			address varchar(255) NOT NULL default '',
			lat decimal(10,7) NOT NULL default '0',
			lng decimal(10,7) NOT NULL default '0',
			PRIMARY KEY (id),
			UNIQUE KEY `geocoded_address_hash` (address_hash)";
	}
}
