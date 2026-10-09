<?php
/**
 * Records what a tool round wrote.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WriteRecorder: folds a round's create-event/update-event outcomes into the
 * turn's write bookkeeping, split out of Turn to keep the round loop focused
 * on driving rounds rather than shaping their results.
 *
 * @since 3.15.0
 */
class WriteRecorder {

	/**
	 * Failure codes a second attempt can fix. Every other code is final, and offering Retry for
	 * one only repeats the refusal.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const RETRYABLE_CODES = [ 'sc_events_write_failed', 'sc_events_rate_limited', 'too_many_calls' ];

	/**
	 * The first successful create-event result in a round, shaped for the view.
	 *
	 * @since 3.15.0
	 *
	 * @param array $results Results from ToolCallRunner::run().
	 *
	 * @return array|null `event_id`, `post_id`, `edit_url`, or null.
	 */
	public function created_event( array $results ): ?array {

		foreach ( $results as $result ) {
			if ( ( $result['name'] ?? '' ) !== Turn::CREATE_EVENT_ABILITY || ! empty( $result['is_error'] ) ) {
				continue;
			}

			$output = json_decode( (string) ( $result['content'] ?? '' ), true );

			if ( ! is_array( $output ) || empty( $output['event_id'] ) || empty( $output['edit_url'] ) ) {
				continue;
			}

			return [
				'event_id' => (int) $output['event_id'],
				'post_id'  => (int) ( $output['post_id'] ?? 0 ),
				'edit_url' => (string) $output['edit_url'],
			];
		}

		return null;
	}

	/**
	 * Fold this round's update-event outcomes into the turn's write record.
	 *
	 * A failed call's result carries no event id, so the target comes from the
	 * call's own arguments, cast the way `TurnState::clean_entities()` casts
	 * model ids.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState $state      Current state.
	 * @param array     $tool_calls The round's `tool_calls`, as received.
	 * @param array     $results    Results from ToolCallRunner::run().
	 *
	 * @return void
	 */
	public function record( TurnState $state, array $tool_calls, array $results ) {

		$outcomes = array_filter(
			$results,
			static function ( $result ) {

				return ( $result['name'] ?? '' ) === Turn::UPDATE_EVENT_ABILITY;
			}
		);

		if ( $outcomes === [] ) {
			return;
		}

		$write = $state->last_write();

		if ( ! is_array( $write ) || $state->turn_seq() !== ( $write['turn'] ?? -1 ) ) {
			$write = [
				'id'        => wp_generate_uuid4(),
				'turn'      => $state->turn_seq(),
				'updated'   => [],
				'failed'    => [],
				'retryable' => [],
			];
		}

		$targets = $this->update_targets( $tool_calls );

		foreach ( $outcomes as $result ) {
			$write = $this->apply_write_outcome( $write, $result, $targets );

			$this->record_previous_values( $state, $result );
		}

		$state->remember_write( $write );
	}

	/**
	 * Call id => the event id each update-event call named, 0 when unusable.
	 *
	 * @since 3.15.0
	 *
	 * @param array $tool_calls The round's `tool_calls`, as received.
	 *
	 * @return array
	 */
	private function update_targets( array $tool_calls ): array {

		$targets = [];

		foreach ( $tool_calls as $call ) {

			if ( ! is_array( $call ) || ( $call['name'] ?? '' ) !== Turn::UPDATE_EVENT_ABILITY ) {
				continue;
			}

			$arguments = ToolCallRunner::decode_arguments( $call['arguments'] ?? null );
			$event_id  = $arguments['event_id'] ?? 0;

			$targets[ (string) ( $call['result_id'] ?? '' ) ] = is_numeric( $event_id ) ? (int) $event_id : 0;
		}

		return $targets;
	}

	/**
	 * Merge one update-event result into the write record.
	 *
	 * @since 3.15.0
	 *
	 * @param array $write   The record being built.
	 * @param array $result  One result from ToolCallRunner::run().
	 * @param array $targets Output of update_targets().
	 *
	 * @return array
	 */
	private function apply_write_outcome( array $write, array $result, array $targets ): array {

		if ( ! empty( $result['is_error'] ) ) {
			$event_id = $targets[ (string) ( $result['tool_call_id'] ?? '' ) ] ?? 0;

			if ( $event_id > 0 ) {
				if ( ! in_array( $event_id, $write['failed'], true ) ) {
					$write['failed'][] = $event_id;
				}

				$write['retryable'] = array_values( array_diff( (array) ( $write['retryable'] ?? [] ), [ $event_id ] ) );

				if ( in_array( ToolCallRunner::error_code( (string) ( $result['content'] ?? '' ) ), self::RETRYABLE_CODES, true ) ) {
					$write['retryable'][] = $event_id;
				}

				// An earlier round of this turn may have updated the same event;
				// the later failure is the outcome the user needs to act on.
				unset( $write['updated'][ $event_id ] );
			}

			return $write;
		}

		return $this->apply_successful_write( $write, $result );
	}

	/**
	 * Merge one successful update-event result into the write record.
	 *
	 * Split out of apply_write_outcome() to keep both under the cyclomatic
	 * complexity limit.
	 *
	 * @since 3.15.0
	 *
	 * @param array $write  The record being built.
	 * @param array $result One successful result from ToolCallRunner::run().
	 *
	 * @return array
	 */
	private function apply_successful_write( array $write, array $result ): array {

		$output   = json_decode( (string) ( $result['content'] ?? '' ), true );
		$event_id = is_array( $output ) && is_numeric( $output['event_id'] ?? null ) ? absint( $output['event_id'] ) : 0;

		if ( $event_id <= 0 ) {
			return $write;
		}

		// Only what the client reads. The event's title is deliberately left out:
		// nothing renders it, and an unread string of event text in the payload
		// is one the next renderer has to remember to escape.
		$write['updated'][ $event_id ] = [
			'event_id' => $event_id,
			'edit_url' => (string) ( $output['edit_url'] ?? '' ),
		];
		$write['failed']               = array_values( array_diff( $write['failed'], [ $event_id ] ) );
		$write['retryable']            = array_values( array_diff( (array) ( $write['retryable'] ?? [] ), [ $event_id ] ) );

		return $write;
	}

	/**
	 * Record a successful update-event's previous values for the relay's `updatedEvents`.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState $state  Current state.
	 * @param array     $result One result from ToolCallRunner::run().
	 *
	 * @return void
	 */
	private function record_previous_values( TurnState $state, array $result ) {

		$output = empty( $result['is_error'] ) ? json_decode( (string) ( $result['content'] ?? '' ), true ) : null;

		if ( ! is_array( $output ) || ! is_numeric( $output['event_id'] ?? null ) ) {
			return;
		}

		$title    = isset( $output['event']['title'] ) && is_string( $output['event']['title'] ) ? $output['event']['title'] : null;
		$previous = isset( $output['previous'] ) && is_array( $output['previous'] ) ? $output['previous'] : [];

		$state->remember_update( absint( $output['event_id'] ), $title, $previous );
	}
}
