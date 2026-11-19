<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

use Sugar_Calendar\Admin\Tools\Importers\Importer;

/**
 * Runs one batch of a source's migration per request, step by step.
 *
 * @since 3.18.0
 */
class MigrateRunner {

	/**
	 * Run the next batch, or finish the migration when no step has items left.
	 *
	 * @since 3.18.0
	 *
	 * @param MigrationSource $source                 The migration source.
	 * @param array           $total_number_to_import The total per step that the caller already knows.
	 * @param bool            $from_start             Whether to look from the first step, as when a stopped run resumes.
	 *
	 * @return Progress
	 */
	public function step( MigrationSource $source, $total_number_to_import = [], $from_start = false ) {

		$state = $this->start( $source );
		$found = $this->find_batch( $source, $state, $from_start );

		if ( empty( $found ) ) {
			return Progress::finished( $this->finish( $source, $state ) );
		}

		[ $step, $batch ] = $found;

		$total    = $this->get_total( $step, $total_number_to_import );
		$progress = $state->add_progress( $step->get_entity(), $step->get_batch_size() );

		return Progress::batch(
			$step->get_response(
				[
					'total_number_to_import' => $total,
					'process'                => $step->get_entity(),
					'progress'               => $this->migrate_batch( $step, $batch, $source ),
					'status'                 => Importer::AJAX_RETURN_STATUS_IN_PROGRESS,
					'process_status'         => $progress >= $total ? Importer::AJAX_RETURN_STATUS_COMPLETE : Importer::AJAX_RETURN_STATUS_IN_PROGRESS,
				]
			)
		);
	}

	/**
	 * Run the source's finishing work and sum the migration up.
	 *
	 * @since 3.18.0
	 *
	 * @param MigrationSource $source The migration source.
	 * @param MigrationState  $state  The migration state.
	 *
	 * @return Report
	 */
	private function finish( MigrationSource $source, MigrationState $state ) {

		foreach ( $source->get_steps() as $step ) {
			if ( $step instanceof FinishableStep ) {
				$step->finish();
			}
		}

		$source->complete();

		$issue_log = $source->get_issue_log();
		$report    = Report::from_issue_log( $issue_log );

		$issue_log->clear();
		$state->clear();

		return $report;
	}

	/**
	 * The step's total: the number the caller passed, else a count of what is left.
	 *
	 * @since 3.18.0
	 *
	 * @param MigrationStep $step                   The step.
	 * @param array         $total_number_to_import The total per step that the caller already knows.
	 *
	 * @return int
	 */
	private function get_total( MigrationStep $step, $total_number_to_import ) {

		$entity = $step->get_entity();

		if ( isset( $total_number_to_import[ $entity ] ) && is_numeric( $total_number_to_import[ $entity ] ) ) {
			return absint( $total_number_to_import[ $entity ] );
		}

		return $step->count_pending();
	}

	/**
	 * Fire the start hook and mark the run in progress.
	 *
	 * @since 3.18.0
	 *
	 * @param MigrationSource $source The migration source.
	 *
	 * @return MigrationState
	 */
	private function start( MigrationSource $source ) {

		/**
		 * Fires when an importer starts a run: one batch request, or a whole one-request import.
		 *
		 * @since 3.15.0
		 *
		 * @param string $source Import source: tec, json or ics.
		 */
		do_action( 'sugar_calendar_importer_started', $source->get_id() ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$state = new MigrationState( $source->get_id() );

		$source->begin( $state );
		$state->start();

		return $state;
	}

	/**
	 * The first step, from the one the run is on, that has items left, with its batch.
	 *
	 * @since 3.18.0
	 *
	 * @param MigrationSource $source     The migration source.
	 * @param MigrationState  $state      The migration state.
	 * @param bool            $from_start Whether to look from the first step instead of the saved one.
	 *
	 * @return array Empty when every step is done, else the step and its batch.
	 */
	private function find_batch( MigrationSource $source, MigrationState $state, $from_start ) {

		$all      = $source->get_steps();
		$entities = array_map(
			static function ( MigrationStep $step ) {

				return $step->get_entity();
			},
			$all
		);
		$current  = $from_start ? false : array_search( $state->get_step(), $entities, true );
		$offset   = $current === false ? 0 : $current;

		// Within a run, only the step it is on and the later ones are queried; a resumed run looks from the first step, so items added while it was stopped go first.
		$found = $this->first_with_items( array_slice( $all, $offset ), $state );

		// Before the run finishes, look once from the first step for items added to an earlier step meanwhile.
		if ( empty( $found ) && $offset > 0 ) {
			$found = $this->first_with_items( array_slice( $all, 0, $offset ), $state );
		}

		return $found;
	}

	/**
	 * The first of the steps that has a batch, saved as the step the run is on.
	 *
	 * @since 3.18.0
	 *
	 * @param MigrationStep[] $steps The steps, in order.
	 * @param MigrationState  $state The migration state.
	 *
	 * @return array Empty when none has items left, else the step and its batch.
	 */
	private function first_with_items( array $steps, MigrationState $state ) {

		foreach ( $steps as $step ) {
			$batch = $step->get_batch();

			if ( ! empty( $batch ) ) {
				$state->set_step( $step->get_entity() );

				return [ $step, $batch ];
			}
		}

		return [];
	}

	/**
	 * Migrate a batch and return how many items made it.
	 *
	 * @since 3.18.0
	 *
	 * @param MigrationStep   $step   The step.
	 * @param array           $batch  The batch items.
	 * @param MigrationSource $source The migration source.
	 *
	 * @return int
	 */
	private function migrate_batch( MigrationStep $step, $batch, MigrationSource $source ) {

		$id_map    = $source->get_id_map();
		$issue_log = $source->get_issue_log();
		$migrated  = 0;

		foreach ( $batch as $item ) {
			$source_id = $step->get_item_id( $item );

			$id_map->start( $step->get_entity(), $source_id );

			$result = $step->migrate( $item );

			if ( $result->is_migrated() ) {
				++$migrated;

				continue;
			}

			$id_map->fail( $step->get_entity(), $source_id );

			if ( $result->get_issue_entity() !== '' ) {
				$issue_log->add( $result->get_issue_entity(), $source_id, $result->get_issue_name() );
			}
		}

		return $migrated;
	}
}
