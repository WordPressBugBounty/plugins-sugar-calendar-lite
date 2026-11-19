<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

/**
 * Records which source item is being, or failed to be, migrated.
 *
 * @since 3.18.0
 */
interface IdMap {

	/**
	 * Mark an item as being migrated, before its step runs.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity    The step's entity.
	 * @param int    $source_id The item's ID in the source.
	 */
	public function start( $entity, $source_id );

	/**
	 * Mark an item as not migrated.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity    The step's entity.
	 * @param int    $source_id The item's ID in the source.
	 */
	public function fail( $entity, $source_id );
}
