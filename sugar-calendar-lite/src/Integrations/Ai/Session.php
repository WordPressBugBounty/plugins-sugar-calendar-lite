<?php
/**
 * A user's Gia AI chat session.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\OAuthRelay\RelayLicenseType;
use WP_Error;

/**
 * The per-user record persisted between requests: the relay-facing `TurnState`
 * plus the UI transcript and the turn status.
 *
 * The transcript is separate from `TurnState::$history` on purpose. The history
 * is compacted after each turn and loses `title`/`notice`, and the Stop rule
 * deletes the stopped user entry, while the UI must keep that message on
 * screen. So the transcript is the UI's record and the history is the relay's.
 *
 * @since 3.15.0
 */
class Session {

	const STATUS_IDLE        = 'idle';
	const STATUS_IN_PROGRESS = 'in_progress';
	const STATUS_ERROR       = 'error';
	const STATUS_NONE        = 'none';

	const RATING_UP   = 'up';
	const RATING_DOWN = 'down';
	const RATING_NONE = 'none';

	/**
	 * The rating vocabulary, matching the relay's `FeedbackRating`.
	 *
	 * `none` is a stored value, not an absence: clearing a thumb is a change the
	 * relay counts, so it travels as a rating rather than as a missing field.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	const RATINGS = [ self::RATING_UP, self::RATING_DOWN, self::RATING_NONE ];

	/**
	 * Transcript cap, kept separate from `Chat::MAX_HISTORY_ENTRIES` so a
	 * relay-driven change to the wire-history cap does not silently resize the
	 * UI transcript.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_TRANSCRIPT_ENTRIES = 100;

	/**
	 * Relay-facing turn state.
	 *
	 * @since 3.15.0
	 *
	 * @var TurnState
	 */
	private $state;

	/**
	 * Current turn status: one of the STATUS_* constants except NONE.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $status = self::STATUS_IDLE;

	/**
	 * UI messages, oldest first.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $transcript = [];

	/**
	 * Last failed step as `[ code, status ]`, or null. No message is stored.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $error = null;

	/**
	 * Prompt to re-send on Retry, set only when the round cap dropped the turn.
	 *
	 * @since 3.15.0
	 *
	 * @var string|null
	 */
	private $retry_prompt = null;

	/**
	 * When /stop last ran, or 0.
	 *
	 * Kept on the record, but it is `step_token`, not this timestamp, that
	 * tells an in-flight step it lost.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private $stopped_at = 0;

	/**
	 * The in-flight step's owner token, or '' when no step is running.
	 *
	 * Taken from the lock token, which is already unique per step, so a step
	 * can tell whether the stored session is still the one it wrote.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $step_token = '';

	/**
	 * Cleans model answers on the way in and on every read.
	 *
	 * @since 3.15.0
	 *
	 * @var AnswerSanitizer
	 */
	private $sanitizer;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState            $state     Turn state.
	 * @param AnswerSanitizer|null $sanitizer Answer sanitizer.
	 */
	public function __construct( TurnState $state, ?AnswerSanitizer $sanitizer = null ) {

		$this->state     = $state;
		$this->sanitizer = $sanitizer ?? new AnswerSanitizer();
	}

	/**
	 * The relay-facing turn state.
	 *
	 * @since 3.15.0
	 *
	 * @return TurnState
	 */
	public function state(): TurnState {

		return $this->state;
	}

	/**
	 * The session's funnel slug.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function funnel(): string {

		return $this->state->funnel();
	}

	/**
	 * Current status.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function status(): string {

		return $this->status;
	}

	/**
	 * Unix time of the last /stop, or 0.
	 *
	 * @since 3.15.0
	 *
	 * @return int
	 */
	public function stopped_at(): int {

		return $this->stopped_at;
	}

	/**
	 * The in-flight step's owner token, or ''.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function step_token(): string {

		return $this->step_token;
	}

	/**
	 * Begin a new turn: record the user message and queue the prompt.
	 *
	 * @since 3.15.0
	 *
	 * @param string $prompt    The user's message.
	 * @param bool   $confirmed Whether the recap's confirm control sent this turn.
	 * @param array  $picks     Chip picks Submit Answer sent with the message, sanitized.
	 *
	 * @return void
	 */
	public function start_turn( string $prompt, bool $confirmed = false, array $picks = [] ) {

		$this->drop_stopped_prompt();
		$this->append_user( $prompt, $picks );
		$this->state->with_prompt( $prompt, $confirmed );
	}

	/**
	 * Prepare a Retry after an error.
	 *
	 * A round-cap error stored the prompt to re-send and dropped the turn; a
	 * transport error left the state intact, so a plain re-step is the retry.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function prepare_retry() {

		if ( $this->retry_prompt !== null ) {
			$this->state->retry_prompt( $this->retry_prompt );

			$this->retry_prompt = null;
		}
	}

	/**
	 * Fold one `Turn::step()` outcome into the session.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_Error|null $error The step's return value.
	 *
	 * @return void
	 */
	public function record_step_result( $error ) {

		if ( is_wp_error( $error ) ) {
			$data               = $error->get_error_data();
			$this->status       = self::STATUS_ERROR;
			$this->retry_prompt = null;
			$this->error        = [
				'code'   => $error->get_error_code(),
				'status' => isset( $data['status'] ) ? (int) $data['status'] : 500,
			];

			// A refused round still reports the credit balance that refused it,
			// and the view's credits are what locks the composer.
			if ( isset( $data['credits'] ) && is_array( $data['credits'] ) ) {
				$this->state->update_credits( $data['credits'] );
			}

			// This code is returned after the prompt was recorded and the turn
			// must be dropped on Retry, so keep the prompt to re-send.
			if ( $error->get_error_code() === 'sc_ai_tool_loop' ) {
				$this->retry_prompt = $this->last_user_text();
			}

			return;
		}

		if ( $this->state->is_terminal() ) {
			$this->append_assistant( $this->state->answer(), $this->state->response_id() );
			$this->status       = self::STATUS_IDLE;
			$this->error        = null;
			$this->retry_prompt = null;

			return;
		}

		$this->status       = self::STATUS_IN_PROGRESS;
		$this->error        = null;
		$this->retry_prompt = null;
	}

	/**
	 * Mark a step as underway and tag the session with the step's identity.
	 *
	 * Called before the step and saved immediately, so the store holds a row
	 * carrying this step's token: the re-read after the step compares it and
	 * yields to anything that wrote the session in the meantime. A new turn
	 * also drops the previous Stop timestamp.
	 *
	 * @since 3.15.0
	 *
	 * @param string $token The step's owner token, from `SessionStore::lock()`.
	 *
	 * @return void
	 */
	public function mark_started( string $token ) {

		$this->status       = self::STATUS_IN_PROGRESS;
		$this->error        = null;
		$this->retry_prompt = null;
		$this->stopped_at   = 0;
		$this->step_token   = $token;
	}

	/**
	 * Apply the Stop rule: drop the half-finished turn, flag the last user
	 * message on screen, return to idle.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function mark_stopped() {

		$this->state->truncate_to_last_complete_turn();

		for ( $index = count( $this->transcript ) - 1; $index >= 0; $index-- ) {
			if ( ( $this->transcript[ $index ]['role'] ?? '' ) === 'user' ) {
				$this->transcript[ $index ]['stopped'] = true;

				break;
			}
		}

		$this->status       = self::STATUS_IDLE;
		$this->error        = null;
		$this->retry_prompt = null;
		$this->stopped_at   = time();
		$this->step_token   = '';
	}

	/**
	 * Response view shared by /turn, /stop and GET /session.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function view(): array {

		$error = null;

		if ( $this->error !== null ) {
			$error = [
				'code'   => $this->error['code'],
				'status' => $this->error['status'],
				'retry'  => $this->retry_prompt !== null ? 'prompt' : 'continue',
			];
		}

		return [
			'status'        => $this->status,
			'funnel'        => $this->state->funnel(),
			'transcript'    => $this->transcript,
			'last_labels'   => $this->last_labels(),
			// `TurnState::$round` keeps the finished turn's count until the
			// next one starts, and a client showing progress on any non-zero
			// round would draw a bar on a finished chat.
			'round'         => $this->status === self::STATUS_IDLE ? 0 : $this->state->round(),
			'max_rounds'    => Turn::MAX_TOOL_ROUNDS,
			'credits'       => $this->state->credits(),
			'created_event' => $this->state->created_event(),
			'last_write'    => self::last_write_view( $this->state->last_write() ),
			'license_type'  => RelayLicenseType::get(),
			'error'         => $error,
		];
	}

	/**
	 * The view for a user with no session, in the same shape as `view()` so a
	 * client has one contract.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public static function none_view(): array {

		return [
			'status'        => self::STATUS_NONE,
			'funnel'        => null,
			'transcript'    => [],
			'last_labels'   => [],
			'round'         => 0,
			'max_rounds'    => Turn::MAX_TOOL_ROUNDS,
			'credits'       => null,
			'created_event' => null,
			'last_write'    => null,
			'license_type'  => RelayLicenseType::get(),
			'error'         => null,
		];
	}

	/**
	 * The write record without its bookkeeping: `updated` as a list, no `turn`.
	 *
	 * @since 3.15.0
	 *
	 * @param array|null $write TurnState::$last_write.
	 *
	 * @return array|null
	 */
	private static function last_write_view( ?array $write ): ?array {

		if ( $write === null ) {
			return null;
		}

		return [
			'id'        => (string) ( $write['id'] ?? '' ),
			'updated'   => array_values( (array) ( $write['updated'] ?? [] ) ),
			'failed'    => array_values( (array) ( $write['failed'] ?? [] ) ),
			'retryable' => array_values( (array) ( $write['retryable'] ?? [] ) ),
		];
	}

	/**
	 * Serialize for storage.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function to_array(): array {

		return [
			'version'      => 1,
			'state'        => $this->state->to_array(),
			'status'       => $this->status,
			'transcript'   => $this->transcript,
			'error'        => $this->error,
			'retry_prompt' => $this->retry_prompt,
			'stopped_at'   => $this->stopped_at,
			'step_token'   => $this->step_token,
			'updated_at'   => time(),
		];
	}

	/**
	 * Rebuild from to_array() output; missing keys keep defaults.
	 *
	 * @since 3.15.0
	 *
	 * @param array                $data      Serialized session.
	 * @param AnswerSanitizer|null $sanitizer Answer sanitizer.
	 *
	 * @return Session
	 */
	public static function from_array( array $data, ?AnswerSanitizer $sanitizer = null ): Session {

		$state_data = isset( $data['state'] ) && is_array( $data['state'] ) ? $data['state'] : [];

		$session = new self( TurnState::from_array( $state_data ), $sanitizer );

		$session->status       = isset( $data['status'] ) ? (string) $data['status'] : self::STATUS_IDLE;
		$session->transcript   = array_map(
			[ $session, 'stored_entry' ],
			isset( $data['transcript'] ) && is_array( $data['transcript'] ) ? $data['transcript'] : []
		);
		$session->error        = isset( $data['error'] ) && is_array( $data['error'] ) ? $data['error'] : null;
		$session->retry_prompt = isset( $data['retry_prompt'] ) && is_string( $data['retry_prompt'] ) ? $data['retry_prompt'] : null;
		$session->stopped_at   = isset( $data['stopped_at'] ) ? (int) $data['stopped_at'] : 0;
		$session->step_token   = isset( $data['step_token'] ) && is_string( $data['step_token'] ) ? $data['step_token'] : '';

		return $session;
	}

	/**
	 * Append a user message and bound the transcript.
	 *
	 * The picks are kept so the answered question row can still show them after a reload.
	 *
	 * @since 3.15.0
	 *
	 * @param string $text  The message.
	 * @param array  $picks Chip picks sent with it.
	 *
	 * @return void
	 */
	private function append_user( string $text, array $picks = [] ) {

		$entry = [
			'role'    => 'user',
			'text'    => $text,
			'stopped' => false,
		];

		if ( $picks ) {
			$entry['picks'] = $picks;
		}

		$this->transcript[] = $entry;

		$this->bound_transcript();
	}

	/**
	 * Remove a stopped message left at the end of the transcript.
	 *
	 * The next message replaces it. Its turn is already gone from the history, so a second copy on
	 * screen would only read as a message still waiting.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	private function drop_stopped_prompt() {

		$last = end( $this->transcript );

		if ( is_array( $last ) && ( $last['role'] ?? '' ) === 'user' && ! empty( $last['stopped'] ) ) {
			array_pop( $this->transcript );
		}
	}

	/**
	 * Append an assistant answer and bound the transcript.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $answer      The terminal answer: title, text, notice, blocks.
	 * @param string $response_id Relay response id naming this answer, or '' when the
	 *                            round did not carry one. An entry without it cannot
	 *                            be rated, and the client hides its thumbs.
	 *
	 * @return void
	 */
	private function append_assistant( array $answer, string $response_id = '' ) {

		$this->transcript[] = [
			'role'        => 'assistant',
			'response_id' => $response_id,
			// The rating lives on the entry, server side, so the pick renders on
			// every device and tab instead of only where it was clicked.
			'rating'      => self::RATING_NONE,
			'answer'      => $this->sanitizer->answer( $answer ),
		];

		$this->bound_transcript();
	}

	/**
	 * The relay's session id for this conversation, or '' before the first round.
	 *
	 * Deliberately not in `view()`: the client has no use for it, and the only
	 * caller is the feedback report, which runs server side.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function relay_session_id(): string {

		return $this->state->session_id();
	}

	/**
	 * Record the user's rating on one assistant answer.
	 *
	 * The answer is found by `response_id` when the client sends one: the stored
	 * transcript is trimmed from the front, so a position taken from an older
	 * view can name a different entry. The index is the fallback for a client
	 * that sent no id. An answer with no `response_id` is not rateable: the
	 * relay keys on it, so there would be nothing to file the rating under.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $index       Transcript position of the answer, as the client saw it.
	 * @param string $rating      One of the RATING_* values.
	 * @param string $response_id Relay response id of the answer, or '' to use the index.
	 *
	 * @return array|null `index` (the stored position) and `entry`, or null when it cannot be rated.
	 */
	public function rate_answer( int $index, string $rating, string $response_id = '' ) {

		if ( ! in_array( $rating, self::RATINGS, true ) ) {
			return null;
		}

		if ( $response_id !== '' ) {
			$index = $this->index_of_response( $response_id );
		}

		$entry = $index >= 0 ? ( $this->transcript[ $index ] ?? null ) : null;

		if ( ! is_array( $entry ) || ( $entry['role'] ?? '' ) !== 'assistant' ) {
			return null;
		}

		if ( ( $entry['response_id'] ?? '' ) === '' ) {
			return null;
		}

		$this->transcript[ $index ]['rating'] = $rating;

		return [
			'index' => $index,
			'entry' => $this->transcript[ $index ],
		];
	}

	/**
	 * The transcript position of the assistant entry carrying a response id.
	 *
	 * @since 3.15.0
	 *
	 * @param string $response_id Relay response id.
	 *
	 * @return int The position, or -1 when no entry carries it.
	 */
	private function index_of_response( string $response_id ): int {

		foreach ( $this->transcript as $position => $entry ) {
			if ( ! is_array( $entry ) || ( $entry['role'] ?? '' ) !== 'assistant' ) {
				continue;
			}

			$stored = (string) ( $entry['response_id'] ?? '' );

			if ( $stored === $response_id ) {
				return (int) $position;
			}
		}

		return -1;
	}

	/**
	 * The user's message that preceded an answer, for the feedback record.
	 *
	 * Walks back from the answer rather than pairing by offset: a transcript
	 * bounded at its cap can lose the opening entries, so positions shift but
	 * the nearest preceding user entry is still the prompt that produced it.
	 *
	 * @since 3.15.0
	 *
	 * @param int $index Transcript position of the answer.
	 *
	 * @return string The prompt, or '' when none precedes it.
	 */
	public function prompt_before( int $index ): string {

		for ( $position = $index - 1; $position >= 0; $position-- ) {
			$entry = $this->transcript[ $position ] ?? null;

			if ( is_array( $entry ) && ( $entry['role'] ?? '' ) === 'user' ) {
				return isset( $entry['text'] ) ? (string) $entry['text'] : '';
			}
		}

		return '';
	}

	/**
	 * Re-sanitize a stored assistant entry on the way in.
	 *
	 * The client renders `text` and `recap.html` as HTML on the promise that
	 * they were sanitized. A transcript written before the tag set existed
	 * (a live 24h transient across an upgrade) has to be held to it too.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $entry One stored transcript entry.
	 *
	 * @return mixed
	 */
	private function stored_entry( $entry ) {

		if ( ! is_array( $entry ) || ! isset( $entry['answer'] ) || ! is_array( $entry['answer'] ) ) {
			return $entry;
		}

		$entry['answer'] = $this->sanitizer->stored_answer( $entry['answer'] );

		// An entry written before ratings existed (a live 24h transient across an
		// upgrade) has neither field. Defaulting `response_id` to '' is what makes
		// the client hide its thumbs rather than offer a rating that cannot be
		// filed, since the relay keys on that id.
		$entry['response_id'] = isset( $entry['response_id'] ) ? (string) $entry['response_id'] : '';
		$entry['rating']      = isset( $entry['rating'] ) && in_array( $entry['rating'], self::RATINGS, true )
			? (string) $entry['rating']
			: self::RATING_NONE;

		return $entry;
	}

	/**
	 * Keep the transcript within its own entry cap, oldest first.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	private function bound_transcript() {

		$overflow = count( $this->transcript ) - self::MAX_TRANSCRIPT_ENTRIES;

		if ( $overflow > 0 ) {
			$this->transcript = array_slice( $this->transcript, $overflow );
		}
	}

	/**
	 * The most recent user message text, or ''.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	private function last_user_text(): string {

		for ( $index = count( $this->transcript ) - 1; $index >= 0; $index-- ) {
			if ( ( $this->transcript[ $index ]['role'] ?? '' ) === 'user' ) {
				return (string) ( $this->transcript[ $index ]['text'] ?? '' );
			}
		}

		return '';
	}

	/**
	 * Non-empty labels from the most recent tool round, for the working line.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function last_labels(): array {

		$labels = [];

		foreach ( $this->state->last_results() as $result ) {
			$label = isset( $result['label'] ) ? (string) $result['label'] : '';

			if ( $label !== '' ) {
				$labels[] = $label;
			}
		}

		return $labels;
	}
}
