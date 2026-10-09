<?php
/**
 * Thumbs ratings on Gia AI answers.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * AnswerFeedback — stores a rating on the session, then reports it to the relay.
 *
 * @since 3.15.0
 */
class AnswerFeedback {

	/**
	 * Session persistence.
	 *
	 * @since 3.15.0
	 *
	 * @var SessionStore
	 */
	private $store;

	/**
	 * Relay client.
	 *
	 * @since 3.15.0
	 *
	 * @var Chat
	 */
	private $chat;

	/**
	 * Per-user request budget.
	 *
	 * @since 3.15.0
	 *
	 * @var StepRateLimiter
	 */
	private $limiter;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param SessionStore|null    $store   Session store.
	 * @param Chat|null            $chat    Relay client.
	 * @param StepRateLimiter|null $limiter Rate limiter.
	 */
	public function __construct( ?SessionStore $store = null, ?Chat $chat = null, ?StepRateLimiter $limiter = null ) {

		$this->store   = $store ?? new SessionStore();
		$this->chat    = $chat ?? new Chat();
		$this->limiter = $limiter ?? new StepRateLimiter();
	}

	/**
	 * Record a thumbs rating for one stored answer.
	 *
	 * The rating is only meaningful against an answer that exists, so an index
	 * that is not an assistant entry is rejected rather than tracked. A pick is
	 * reversible: `none` retracts it, and `previous` carries what it replaced.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $user_id     Current user.
	 * @param int    $index       Transcript position as the client sees it.
	 * @param string $rating      The new rating.
	 * @param string $response_id The answer's relay id, '' from an older client.
	 * @param string $previous    The rating it replaces, as the client reports it.
	 *
	 * @return array|WP_Error
	 */
	public function rate( int $user_id, int $index, string $rating, string $response_id, string $previous ) {

		// Its own window, like /usage: this route reaches the relay too, and a
		// refused call must cost no lock and no transient write.
		$limited = $this->limiter->attempt( $user_id, 'feedback' );

		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		// Locked for the same reason a turn locks: the write has to cover the
		// whole read-modify-write. A step holds its pre-round snapshot across
		// the relay round and saves it afterwards, so an unlocked rating written
		// mid-round would be silently overwritten by the finishing turn.
		$stored = $this->store->locked(
			$user_id,
			function () use ( $user_id, $index, $rating, $response_id ) {

				$session = $this->store->get( $user_id );

				if ( $session === null ) {
					return new WP_Error( 'sc_ai_no_session', __( 'There is no conversation to rate.', 'sugar-calendar-lite' ), [ 'status' => 404 ] );
				}

				$rated = $session->rate_answer( $index, $rating, $response_id );

				if ( $rated === null ) {
					return new WP_Error( 'sc_ai_no_answer', __( 'That answer can no longer be rated.', 'sugar-calendar-lite' ), [ 'status' => 404 ] );
				}

				// The stored position, which can differ from the client's once the
				// transcript has been trimmed from the front.
				$stored = [
					'relay'  => $session->relay_session_id(),
					'entry'  => $rated['entry'],
					'index'  => $rated['index'],
					'prompt' => $session->prompt_before( $rated['index'] ),
					'view'   => $session->view(),
				];

				// Stored before the relay is told, and kept whatever the relay says:
				// the pick is the user's and must survive a relay outage. The relay is
				// the analytics store, not the source the UI reads from.
				$this->store->save( $user_id, $session );

				return $stored;
			}
		);

		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		// Outside the lock on purpose: this call crosses the network, and holding
		// the session lock across it would make a rating delay the next turn.
		$this->report( $stored['relay'], $stored['entry'], $stored['index'], $rating, $previous, $stored['prompt'], $stored['view'] );

		return [
			'rated'  => true,
			'rating' => $rating,
		];
	}

	/**
	 * Forward one rating to the relay, fire and forget.
	 *
	 * Deliberately not surfaced to the caller. The rating is already stored, the
	 * user has nothing to do about a relay failure, and a rating is not worth an
	 * error state in the chat panel — so a failure is logged and swallowed. This
	 * is also what lets the two sides ship in either order: against a relay
	 * without the endpoint the call 404s into the log and the UI is unaffected.
	 *
	 * @since 3.15.0
	 *
	 * @param string $relay_session_id The relay's session id for this conversation.
	 * @param array  $entry            The rated transcript entry.
	 * @param int    $index            Its transcript position.
	 * @param string $rating           The new rating.
	 * @param string $previous         The rating it replaces, as the client reports it.
	 * @param string $prompt           The prompt that produced the answer.
	 * @param array  $view             Session view, for the funnel and conversation context.
	 *
	 * @return void
	 */
	private function report( string $relay_session_id, array $entry, int $index, string $rating, string $previous, string $prompt, array $view ) {

		$result = $this->chat->feedback(
			[
				'session_id'   => $relay_session_id,
				'response_id'  => (string) ( $entry['response_id'] ?? '' ),
				'answer_index' => $index,
				'funnel'       => (string) $view['funnel'],
				'rating'       => $rating,
				'previous'     => $previous,
				'user_prompt'  => $prompt === '' ? null : $prompt,
				'answer'       => isset( $entry['answer'] ) && is_array( $entry['answer'] ) ? $entry['answer'] : [],
				'context'      => [
					'exchanges'    => $this->count_user_turns( $view['transcript'] ),
					'createdEvent' => $view['created_event'] !== null,
				],
			]
		);

		// Behind WP_DEBUG like `ToolCallRunner`'s ability failures: the rating is
		// already stored and the UI is correct, so a delivery failure costs only
		// the analytics record and must not fill a production log.
		if ( is_wp_error( $result ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[SC Gia] rating not reported (%s): %s', $result->get_error_code(), $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * How many turns the user has taken in this conversation.
	 *
	 * Not `view['round']`: that reports 0 whenever the session is idle, which is
	 * the only state a rated answer is ever rendered in.
	 *
	 * @since 3.15.0
	 *
	 * @param array $transcript Session transcript.
	 *
	 * @return int
	 */
	private function count_user_turns( array $transcript ): int {

		$turns = 0;

		foreach ( $transcript as $entry ) {
			if ( ( $entry['role'] ?? '' ) === 'user' ) {
				++$turns;
			}
		}

		return $turns;
	}
}
