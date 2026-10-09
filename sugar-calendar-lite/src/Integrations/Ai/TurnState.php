<?php
/**
 * State of one assistant turn between relay rounds.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * TurnState: everything Turn::step() needs to run the next round, and
 * everything a caller needs to persist between HTTP requests.
 *
 * Read through getters, changed only through the named transitions below, so
 * each field has one place that decides how it moves.
 *
 * @since 3.15.0
 */
class TurnState {

	/**
	 * Funnel slug, one of the Funnels slugs.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $funnel;

	/**
	 * Relay wire history, oldest first.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $history = [];

	/**
	 * Relay session id. Empty before the first round.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $session_id = '';

	/**
	 * Relay response id for the round just received, or empty before the first
	 * round. Identifies one answer for `POST feedback`.
	 *
	 * Overwritten every round rather than kept: each response mints its own id,
	 * and the terminal round's is the one that names the answer the user sees.
	 * Holding a stale id would file a rating against the wrong answer.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $response_id = '';

	/**
	 * Last credits snapshot from the relay, or null before the first round.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $credits = null;

	/**
	 * Completed tool rounds in this turn.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private $round = 0;

	/**
	 * User prompt not yet sent, or null.
	 *
	 * @since 3.15.0
	 *
	 * @var string|null
	 */
	private $pending_prompt = null;

	/**
	 * Whether this turn is the user pressing the recap's confirm control.
	 *
	 * Lives for the whole turn, not the round: a confirmed turn can take
	 * several rounds and the continuation rounds carry no prompt, so this is
	 * the only signal left that the recap was accepted. Never set from typed
	 * text, however affirmative; the relay's phrase list covers that.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $confirmed = false;

	/**
	 * Optional relay request context.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $context = [];

	/**
	 * Terminal answer (`title`, `text`, `notice`, `blocks`, `done`), or null while in progress.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $answer = null;

	/**
	 * Tool results from the most recent round, from ToolCallRunner::run().
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $last_results = [];

	/**
	 * The event `create-event` made in this session (`event_id`, `post_id`,
	 * `edit_url`), or null.
	 *
	 * Set from the tool round's results: finish_turn() empties `last_results`
	 * before the answer is stored, so it cannot be read at answer time.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $created_event = null;

	/**
	 * Entity ledger: entities the conversation has resolved, and this turn's own picks.
	 *
	 * @since 3.15.0
	 *
	 * @var EntityMemory
	 */
	private $entity_memory;

	/**
	 * Turns started in this session. Tells a write round whether it belongs
	 * to the same turn as the previous one; `round` cannot, since a listing
	 * round may precede the write round.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private $turn_seq = 0;

	/**
	 * The most recent turn that called update-event: `id` (uuid), `turn`,
	 * `updated` (event id => `event_id`, `edit_url`, `title`), `failed` (event
	 * ids). Kept across turns that do not write; null until the first write.
	 *
	 * Captured on the tool round: finish_turn() empties `last_results` before
	 * the answer is stored.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $last_write = null;

	/**
	 * Tool rounds completed in this session, across turns. `round` is per turn.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private $tool_rounds = 0;

	/**
	 * Turns in this session that wrote at least one event.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private $writes = 0;

	/**
	 * Events created plus events successfully updated in this session.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private $events_written = 0;

	/**
	 * Whether a round of the current turn created the event. Rounds are separate
	 * requests, so this survives the save between them.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $turn_created = false;

	/**
	 * Whether the relay's prompt guard refused the current turn. Set and read in
	 * the same request, so not serialised.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $refused = false;

	/**
	 * The session's first write, set by Turn and announced by Conversation once the
	 * step's save is kept. Set and read in the same request, so not serialised.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $conversion = null;

	/**
	 * Undo ledger: previous values for events this conversation changed.
	 *
	 * @since 3.15.0
	 *
	 * @var UpdatedEvents
	 */
	private $updated;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param string $funnel  Funnel slug.
	 * @param array  $context Optional relay request context.
	 */
	public function __construct( string $funnel, array $context = [] ) {

		$this->funnel        = $funnel;
		$this->context       = $context;
		$this->entity_memory = new EntityMemory();
		$this->updated       = new UpdatedEvents();
	}

	/**
	 * Queue a user prompt for the next round, dropping any half-finished turn first.
	 *
	 * @since 3.15.0
	 *
	 * @param string $prompt    The user's message.
	 * @param bool   $confirmed Whether the recap's confirm control sent this turn.
	 *
	 * @return TurnState
	 */
	public function with_prompt( string $prompt, bool $confirmed = false ): TurnState {

		$this->truncate_to_last_complete_turn();

		++$this->turn_seq;

		$this->turn_created = false;
		$this->refused      = false;
		$this->conversion   = null;

		$this->pending_prompt = $prompt;
		$this->confirmed      = $confirmed;

		return $this;
	}

	/**
	 * Re-queue the prompt of a turn the round cap dropped.
	 *
	 * The dropped turn never finished, so its confirm flag is still set and the
	 * re-sent prompt keeps it. The turn keeps its number too: the retry re-runs
	 * the same request, so WriteRecorder folds into the write record the capped
	 * rounds already filled instead of starting an empty one.
	 *
	 * @since 3.15.0
	 *
	 * @param string $prompt The prompt to re-send.
	 *
	 * @return TurnState
	 */
	public function retry_prompt( string $prompt ): TurnState {

		$turn_seq     = $this->turn_seq;
		$turn_created = $this->turn_created;

		$this->with_prompt( $prompt, $this->confirmed );

		$this->turn_seq     = $turn_seq;
		$this->turn_created = $turn_created;

		return $this;
	}

	/**
	 * Start from an earlier conversation's history and relay session, for Turn::run().
	 *
	 * @since 3.15.0
	 *
	 * @param array  $history    Prior wire history, oldest first.
	 * @param string $session_id Relay session id, or ''.
	 *
	 * @return TurnState
	 */
	public function continue_from( array $history, string $session_id ): TurnState {

		$this->history    = $history;
		$this->session_id = $session_id;

		return $this;
	}

	/**
	 * Move the pending prompt into the history once the relay has seen it.
	 *
	 * The endpoint appends `userPrompt` to the history it sees for this round
	 * only; recording it here carries it into the next round, and clearing it
	 * marks every later round as a continuation.
	 *
	 * @since 3.15.0
	 *
	 * @return TurnState
	 */
	public function commit_prompt(): TurnState {

		if ( $this->pending_prompt !== null ) {
			$this->history[]      = [
				'role'    => 'user',
				'content' => $this->pending_prompt,
			];
			$this->pending_prompt = null;
		}

		return $this;
	}

	/**
	 * Record the identifiers and balance a relay response carried.
	 *
	 * The response id is not defaulted to the previous round's value: every
	 * response mints its own, and a rating filed under a stale one would name the
	 * wrong answer. An absent id leaves the answer unrateable, which is honest.
	 *
	 * @since 3.15.0
	 *
	 * @param string|null $session_id  Relay session id, or null to keep the current one.
	 * @param string      $response_id This response's id, '' when absent.
	 * @param array|null  $credits     Credits snapshot, or null to keep the current one.
	 *
	 * @return TurnState
	 */
	public function record_response( ?string $session_id, string $response_id, ?array $credits ): TurnState {

		if ( $session_id !== null ) {
			$this->session_id = $session_id;
		}

		$this->response_id = $response_id;

		if ( $credits !== null ) {
			$this->update_credits( $credits );
		}

		return $this;
	}

	/**
	 * Keep the latest credits snapshot, including one a refused round reported.
	 *
	 * @since 3.15.0
	 *
	 * @param array $credits Credits snapshot from the relay.
	 *
	 * @return TurnState
	 */
	public function update_credits( array $credits ): TurnState {

		$this->credits = $credits;

		return $this;
	}

	/**
	 * Store the terminal answer and collapse the finished turn.
	 *
	 * `blocks` rides along on the history entry because the relay counts
	 * clarification rounds by the `question_chips` blocks on assistant entries.
	 *
	 * @since 3.15.0
	 *
	 * @param array $answer The answer: `title`, `text`, `notice`, `blocks`, `done`.
	 *
	 * @return TurnState
	 */
	public function finish( array $answer ): TurnState {

		$this->answer       = $answer;
		$this->last_results = [];
		$this->confirmed    = false;
		$this->history[]    = [
			'role'    => 'assistant',
			'content' => $answer['text'],
			'blocks'  => $answer['blocks'],
		];

		return $this->compact_completed_turn();
	}

	/**
	 * Append one executed tool round and count it.
	 *
	 * @since 3.15.0
	 *
	 * @param array $entries The round's assistant `tool_calls` entry and its `tool` results.
	 * @param array $results Results from ToolCallRunner::run().
	 *
	 * @return TurnState
	 */
	public function record_tool_round( array $entries, array $results ): TurnState {

		$this->history      = array_merge( $this->history, $entries );
		$this->last_results = $results;

		++$this->round;

		++$this->tool_rounds;

		return $this;
	}

	/**
	 * Remember the event create-event made, keeping the first one.
	 *
	 * @since 3.15.0
	 *
	 * @param array|null $created The created event (`event_id`, `post_id`, `edit_url`), or null.
	 *
	 * @return TurnState
	 */
	public function remember_created_event( ?array $created ): TurnState {

		if ( $this->created_event === null ) {
			$this->created_event = $created;
		}

		return $this;
	}

	/**
	 * Replace the write record with the one WriteRecorder folded this round into.
	 *
	 * @since 3.15.0
	 *
	 * @param array $write The write record: `id`, `turn`, `updated`, `failed`.
	 *
	 * @return TurnState
	 */
	public function remember_write( array $write ): TurnState {

		$this->last_write = $write;

		return $this;
	}

	/**
	 * Remember entities the model resolved, most recent mention first.
	 *
	 * @since 3.15.0
	 *
	 * @param array $found Same shape as `entities()` returns: type => list of `[ 'id', 'name' ]`.
	 *
	 * @return TurnState
	 */
	public function remember_entities( array $found ): TurnState {

		$this->entity_memory->remember_entities( $found );

		return $this;
	}

	/**
	 * Merge one successful update-event result into the undo ledger.
	 *
	 * @since 3.15.0
	 *
	 * @param int         $event_id Sugar Calendar event id.
	 * @param string|null $title    The event's title after the write, or null when unknown.
	 * @param array       $previous The result's `previous` map.
	 *
	 * @return TurnState
	 */
	public function remember_update( int $event_id, ?string $title, array $previous ): TurnState {

		$this->updated->remember_update( $event_id, $title, $previous );

		return $this;
	}

	/**
	 * Remember that a round of this turn created the event.
	 *
	 * @since 3.15.0
	 *
	 * @return TurnState
	 */
	public function mark_turn_created(): TurnState {

		$this->turn_created = true;

		return $this;
	}

	/**
	 * Remember that the relay's prompt guard refused this turn.
	 *
	 * @since 3.15.0
	 *
	 * @return TurnState
	 */
	public function mark_refused(): TurnState {

		$this->refused = true;

		return $this;
	}

	/**
	 * Remember that this turn was the session's first to write.
	 *
	 * @since 3.15.0
	 *
	 * @param string $action  Either `create` or `update`.
	 * @param int    $updated Events created or successfully updated by the turn.
	 * @param int    $failed  Events the turn failed to update.
	 *
	 * @return TurnState
	 */
	public function mark_converted( string $action, int $updated, int $failed ): TurnState {

		$this->conversion = [
			'action'  => $action,
			'updated' => $updated,
			'failed'  => $failed,
		];

		return $this;
	}

	/**
	 * Count a turn that wrote events.
	 *
	 * @since 3.15.0
	 *
	 * @param int $events Events created or successfully updated by the turn.
	 *
	 * @return TurnState
	 */
	public function count_write( int $events ): TurnState {

		++$this->writes;

		$this->events_written += $events;

		return $this;
	}

	/**
	 * Tool rounds completed in this session.
	 *
	 * @since 3.15.0
	 *
	 * @return int
	 */
	public function tool_rounds(): int {

		return $this->tool_rounds;
	}

	/**
	 * Turns in this session that wrote.
	 *
	 * @since 3.15.0
	 *
	 * @return int
	 */
	public function writes(): int {

		return $this->writes;
	}

	/**
	 * Events created or successfully updated in this session.
	 *
	 * @since 3.15.0
	 *
	 * @return int
	 */
	public function events_written(): int {

		return $this->events_written;
	}

	/**
	 * Whether a round of the current turn created the event.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public function turn_created(): bool {

		return $this->turn_created;
	}

	/**
	 * Whether the relay refused the current turn.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public function refused(): bool {

		return $this->refused;
	}

	/**
	 * The first-write marker for the current turn, if it set one.
	 *
	 * @since 3.15.0
	 *
	 * @return array|null `action`, `updated` and `failed`, or null.
	 */
	public function conversion(): ?array {

		return $this->conversion;
	}

	/**
	 * Keep this turn's picked records and put them first among the remembered entities.
	 *
	 * @since 3.15.0
	 *
	 * @param array $resolved Same shape as `entities()` returns.
	 *
	 * @return TurnState
	 */
	public function remember_mentions( array $resolved ): TurnState {

		$this->entity_memory->remember_mentions( $resolved );

		return $this;
	}

	/**
	 * This turn's mentions not already among the remembered entities, for
	 * re-adding after a harvest that may have evicted them.
	 *
	 * @since 3.15.0
	 *
	 * @return array Same shape as `EntityMemory`'s mentions.
	 */
	public function missing_mentions(): array {

		return $this->entity_memory->missing_mentions();
	}

	/**
	 * Fold a tool round's harvested entities in, then re-add this turn's own picks so the
	 * harvest cannot push them out of the capped list mid-turn.
	 *
	 * @since 3.15.0
	 *
	 * @param array $harvested Entities named in the round's results, same shape as `entities()` returns.
	 *
	 * @return TurnState
	 */
	public function remember_harvest( array $harvested ): TurnState {

		$this->entity_memory->remember_harvest( $harvested );

		return $this;
	}

	/**
	 * Whether the turn has reached an answer.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public function is_terminal(): bool {

		return $this->answer !== null;
	}

	/**
	 * Abandon a half-finished turn: cut history back to the last assistant
	 * answer entry and reset the per-turn fields.
	 *
	 * The relay would accept a new prompt after a dangling tool round, but the
	 * model would then see the abandoned request next to the new one, which in
	 * a write funnel means a stopped "update every March event" stays live.
	 * The stopped turn's own `user` entry goes too; the UI keeps it on screen.
	 * A no-op on a complete history.
	 *
	 * @since 3.15.0
	 *
	 * @return TurnState
	 */
	public function truncate_to_last_complete_turn(): TurnState {

		$this->history        = array_slice( $this->history, 0, $this->last_answer_index() + 1 );
		$this->pending_prompt = null;
		$this->confirmed      = false;
		$this->answer         = null;
		$this->round          = 0;
		$this->last_results   = [];

		return $this;
	}

	/**
	 * Collapse the just-finished turn to its `user` entry and its answer.
	 *
	 * The relay counts tool rounds and clarification rounds over the whole
	 * submitted history, so a finished turn's tool entries would eat the next
	 * turn's budget. Call once, right after the answer entry is appended.
	 *
	 * @since 3.15.0
	 *
	 * @return TurnState
	 */
	public function compact_completed_turn(): TurnState {

		$answer_index = $this->last_answer_index();

		if ( $answer_index < 1 ) {
			return $this;
		}

		$user_index = $answer_index - 1;

		while ( $user_index > 0 && ( $this->history[ $user_index ]['role'] ?? '' ) !== 'user' ) {
			--$user_index;
		}

		$this->history = array_merge(
			array_slice( $this->history, 0, $user_index + 1 ),
			[ $this->history[ $answer_index ] ]
		);

		return $this;
	}

	/**
	 * Drop the oldest completed turns until the history has at most `$max_entries`.
	 *
	 * Drops completed turns first and touches the in-progress turn (everything
	 * from the last `user` entry on) only when it alone exceeds the cap, which
	 * takes rounds answered with more calls than the per-round limit.
	 *
	 * @since 3.15.0
	 *
	 * @param int $max_entries Relay validation cap.
	 *
	 * @return TurnState
	 */
	public function trim_history( int $max_entries ): TurnState {

		$in_progress_start = count( $this->history );

		foreach ( $this->history as $index => $entry ) {
			if ( ( $entry['role'] ?? '' ) === 'user' ) {
				$in_progress_start = $index;
			}
		}

		$hist_count = count( $this->history );
		$drop       = max( 0, min( $in_progress_start, $hist_count - $max_entries ) );

		if ( $drop > 0 ) {
			$this->history      = array_slice( $this->history, $drop );
			$in_progress_start -= $drop;
			$hist_count        -= $drop;
		}

		if ( $hist_count <= $max_entries ) {
			return $this;
		}

		// The in-progress turn alone is over the cap: rounds answered with more
		// than MAX_TOOL_CALLS_PER_ROUND calls. Keep its `user` entry and drop its
		// oldest entries; the relay drops the tool results this orphans.
		$this->history = array_merge(
			array_slice( $this->history, $in_progress_start, 1 ),
			array_slice( $this->history, $hist_count - ( $max_entries - 1 ) )
		);

		return $this;
	}

	/**
	 * Index of the last assistant answer entry (one without tool_calls), or -1.
	 *
	 * A blocks-only clarification has empty content and is still an answer.
	 *
	 * @since 3.15.0
	 *
	 * @return int
	 */
	private function last_answer_index(): int {

		for ( $index = count( $this->history ) - 1; $index >= 0; $index-- ) {

			$entry = $this->history[ $index ];

			if ( ( $entry['role'] ?? '' ) === 'assistant' && empty( $entry['tool_calls'] ) ) {
				return $index;
			}
		}

		return -1;
	}

	/**
	 * Funnel slug.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function funnel(): string {

		return (string) $this->funnel;
	}

	/**
	 * Relay wire history, oldest first.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function history(): array {

		return $this->history;
	}

	/**
	 * Relay session id, '' before the first round.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function session_id(): string {

		return $this->session_id;
	}

	/**
	 * Relay response id of the round just received, '' when absent.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function response_id(): string {

		return $this->response_id;
	}

	/**
	 * Last credits snapshot, or null before the first round.
	 *
	 * @since 3.15.0
	 *
	 * @return array|null
	 */
	public function credits() {

		return $this->credits;
	}

	/**
	 * Completed tool rounds in this turn.
	 *
	 * @since 3.15.0
	 *
	 * @return int
	 */
	public function round(): int {

		return $this->round;
	}

	/**
	 * User prompt not yet sent, or null.
	 *
	 * @since 3.15.0
	 *
	 * @return string|null
	 */
	public function pending_prompt() {

		return $this->pending_prompt;
	}

	/**
	 * Whether this turn is the recap's confirm control.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public function confirmed(): bool {

		return $this->confirmed;
	}

	/**
	 * Optional relay request context.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function context(): array {

		return $this->context;
	}

	/**
	 * Terminal answer, or null while in progress.
	 *
	 * @since 3.15.0
	 *
	 * @return array|null
	 */
	public function answer() {

		return $this->answer;
	}

	/**
	 * Tool results from the most recent round.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function last_results(): array {

		return $this->last_results;
	}

	/**
	 * The event create-event made in this session, or null.
	 *
	 * @since 3.15.0
	 *
	 * @return array|null
	 */
	public function created_event() {

		return $this->created_event;
	}

	/**
	 * Remembered entities, type => list of `[ id, name ]`.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function entities(): array {

		return $this->entity_memory->entities();
	}

	/**
	 * Turns started in this session.
	 *
	 * @since 3.15.0
	 *
	 * @return int
	 */
	public function turn_seq(): int {

		return $this->turn_seq;
	}

	/**
	 * The most recent turn's write record, or null.
	 *
	 * @since 3.15.0
	 *
	 * @return array|null
	 */
	public function last_write() {

		return $this->last_write;
	}

	/**
	 * Events this conversation changed, event id => `title`, `previous`, `skipped`.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function updated_events(): array {

		return $this->updated->updated_events();
	}

	/**
	 * Serialize for storage between requests.
	 *
	 * The entity ledger and undo ledger splice their own keys (`entities`, `mentions`,
	 * `updated_events`) into this array: the serialized shape is a stored contract
	 * (sessions live in transients across requests).
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function to_array(): array {

		return array_merge(
			[
				'funnel'         => $this->funnel,
				'history'        => $this->history,
				'session_id'     => $this->session_id,
				'response_id'    => $this->response_id,
				'credits'        => $this->credits,
				'round'          => $this->round,
				'pending_prompt' => $this->pending_prompt,
				'confirmed'      => $this->confirmed,
				'context'        => $this->context,
				'answer'         => $this->answer,
				'last_results'   => $this->last_results,
				'created_event'  => $this->created_event,
				'turn_seq'       => $this->turn_seq,
				'last_write'     => $this->last_write,
				'tool_rounds'    => $this->tool_rounds,
				'writes'         => $this->writes,
				'events_written' => $this->events_written,
				'turn_created'   => $this->turn_created,
			],
			$this->entity_memory->to_array(),
			$this->updated->to_array()
		);
	}

	/**
	 * Rebuild from to_array() output. Unknown keys are ignored, missing keys keep defaults.
	 *
	 * @since 3.15.0
	 *
	 * @param array $data Serialized state.
	 *
	 * @return TurnState
	 */
	public static function from_array( array $data ): TurnState {

		$state = new self(
			isset( $data['funnel'] ) ? (string) $data['funnel'] : '',
			self::array_field( $data, 'context', [] )
		);

		$state->history        = self::array_field( $data, 'history', [] );
		$state->session_id     = isset( $data['session_id'] ) ? (string) $data['session_id'] : '';
		$state->response_id    = isset( $data['response_id'] ) ? (string) $data['response_id'] : '';
		$state->credits        = self::array_field( $data, 'credits' );
		$state->round          = isset( $data['round'] ) ? (int) $data['round'] : 0;
		$state->pending_prompt = isset( $data['pending_prompt'] ) && is_string( $data['pending_prompt'] ) ? $data['pending_prompt'] : null;
		$state->confirmed      = ! empty( $data['confirmed'] );
		$state->answer         = self::array_field( $data, 'answer' );
		$state->last_results   = self::array_field( $data, 'last_results', [] );
		$state->created_event  = self::array_field( $data, 'created_event' );
		$state->entity_memory  = EntityMemory::from_array( $data );
		$state->turn_seq       = isset( $data['turn_seq'] ) ? (int) $data['turn_seq'] : 0;
		$state->last_write     = self::array_field( $data, 'last_write' );
		$state->tool_rounds    = isset( $data['tool_rounds'] ) ? (int) $data['tool_rounds'] : 0;
		$state->writes         = isset( $data['writes'] ) ? (int) $data['writes'] : 0;
		$state->events_written = isset( $data['events_written'] ) ? (int) $data['events_written'] : 0;
		$state->turn_created   = ! empty( $data['turn_created'] );
		$state->updated        = UpdatedEvents::from_array( $data );

		return $state;
	}

	/**
	 * Read a serialized field, keeping it only when it really is an array.
	 *
	 * @since 3.15.0
	 *
	 * @param array      $data    Serialized state.
	 * @param string     $key     Field name.
	 * @param array|null $default Value when the field is absent or malformed.
	 *
	 * @return array|null
	 */
	private static function array_field( array $data, string $key, ?array $default = null ) {

		return isset( $data[ $key ] ) && is_array( $data[ $key ] ) ? $data[ $key ] : $default;
	}
}
