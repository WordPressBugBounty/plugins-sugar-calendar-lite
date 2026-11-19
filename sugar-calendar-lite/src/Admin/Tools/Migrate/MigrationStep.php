<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

/**
 * One kind of item a migration source moves, such as events or orders.
 *
 * @since 3.18.0
 */
interface MigrationStep {

	/**
	 * The kind of item, used as the progress key.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_entity();

	/**
	 * The number of items not migrated yet.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function count_pending();

	/**
	 * The next items to migrate, empty when the step is done.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_batch();

	/**
	 * The number of items one batch counts towards the step's progress.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_batch_size();

	/**
	 * The source ID of one batch item.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return int
	 */
	public function get_item_id( $item );

	/**
	 * Migrate one batch item.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return StepResult
	 */
	public function migrate( $item );

	/**
	 * The step's own fields in a batch response, after the batch ran.
	 *
	 * @since 3.18.0
	 *
	 * @param array $response The batch response.
	 *
	 * @return array
	 */
	public function get_response( $response );
}
