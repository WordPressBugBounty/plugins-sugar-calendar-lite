<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecIdMap;

/**
 * Creates the tables that record which TEC item became which Sugar Calendar item.
 *
 * @since 3.18.0
 */
class TheEventCalendarTables {

	/**
	 * Check whether a table exists.
	 *
	 * @since 3.18.0
	 *
	 * @param string $table Table name without the prefix.
	 *
	 * @return bool
	 */
	public static function exists( $table ) {

		global $wpdb;

		// A query, not SHOW TABLES, which doesn't list a temporary table.
		$suppress = $wpdb->suppress_errors();
		$exists   = $wpdb->query( 'SELECT 1 FROM `' . esc_sql( $wpdb->prefix . $table ) . '` LIMIT 0' ) !== false; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Direct query for the import tool.

		$wpdb->suppress_errors( $suppress );

		return $exists;
	}

	/**
	 * Create an entity's mapping table from its `TecIdMap` spec, unless it exists.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity The step entity.
	 *
	 * @return void
	 */
	public static function ensure( $entity ) {

		$spec = TecIdMap::spec( $entity );

		if ( $spec !== null && ! self::exists( $spec['table'] ) ) {
			self::create( $spec );
		}
	}

	/**
	 * Create a mapping table from its spec.
	 *
	 * @since 3.18.0
	 *
	 * @param array $spec The `TecIdMap::ENTITIES` entry.
	 *
	 * @return void
	 */
	private static function create( $spec ) {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$definitions = [ '`id` int AUTO_INCREMENT' ];

		foreach ( $spec['columns'] as $column ) {
			$definitions[] = '`' . $column . '` int';
		}

		$definitions[] = 'PRIMARY KEY (id)';

		if ( $spec['key'] !== null ) {
			$definitions[] = 'KEY ' . $spec['key'] . ' (' . $spec['key'] . ')';
		}

		dbDelta( 'CREATE TABLE ' . $wpdb->prefix . $spec['table'] . ' (' . implode( ', ', $definitions ) . ');' );
	}
}
