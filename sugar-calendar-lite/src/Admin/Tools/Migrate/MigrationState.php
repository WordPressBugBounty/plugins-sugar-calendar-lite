<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

/**
 * The options that hold a source's migration state between requests.
 *
 * @since 3.18.0
 */
class MigrationState {

	/**
	 * The state option: a finish date, `in_progress`, or absent before the first run.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param string $source_id The source ID.
	 */
	public function __construct( $source_id ) {

		$this->key = 'sugar_calendar_' . $source_id . '_migration';
	}

	/**
	 * Whether a first run has started and not finished.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function is_in_progress() {

		return get_option( $this->key ) === 'in_progress';
	}

	/**
	 * Mark the run in progress, unless it re-runs a finished migration.
	 *
	 * @since 3.18.0
	 */
	public function start() {

		// A re-run keeps the finished migration's date, so the site still reads as migrated while it runs.
		if ( in_array( get_option( $this->key ), [ false, 'in_progress' ], true ) ) {
			update_option( $this->key, 'in_progress', false );
		}
	}

	/**
	 * The entity of the step the run is on, empty before its first batch.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_step() {

		return (string) get_option( $this->key . '_step', '' );
	}

	/**
	 * Save the entity of the step the run is on.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity The step's entity.
	 */
	public function set_step( $entity ) {

		update_option( $this->key . '_step', $entity, false );
	}

	/**
	 * Add one batch to a step's progress and return the new total.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity     The step's entity.
	 * @param int    $batch_size The step's batch size.
	 *
	 * @return int
	 */
	public function add_progress( $entity, $batch_size ) {

		$progresses            = get_option( $this->key . '_process', [] );
		$progresses[ $entity ] = ( $progresses[ $entity ] ?? 0 ) + $batch_size;

		update_option( $this->key . '_process', $progresses );

		return $progresses[ $entity ];
	}

	/**
	 * Forget the step and progress once the run finishes.
	 *
	 * @since 3.18.0
	 */
	public function clear() {

		delete_option( $this->key . '_step' );
		delete_option( $this->key . '_process' );
	}
}
