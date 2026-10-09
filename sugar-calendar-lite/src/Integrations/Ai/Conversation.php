<?php
/**
 * The per-user Gia AI conversation: turns, Stop and Clear.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Throwable;
use WP_Error;

/**
 * Conversation — runs one `Turn::step()` per call against the stored session and keeps
 * Stop, Clear and concurrent steps from overwriting each other.
 *
 * @since 3.15.0
 */
class Conversation {

	/**
	 * Session persistence.
	 *
	 * @since 3.15.0
	 *
	 * @var SessionStore
	 */
	private $store;

	/**
	 * Turn driver.
	 *
	 * @since 3.15.0
	 *
	 * @var Turn
	 */
	private $turn;

	/**
	 * Resolves the records a prompt's tokens point at.
	 *
	 * @since 3.15.0
	 *
	 * @var Mentions
	 */
	private $mentions;

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
	 * @param SessionStore|null    $store    Session store.
	 * @param Turn|null            $turn     Turn driver.
	 * @param Mentions|null        $mentions Mention resolver.
	 * @param StepRateLimiter|null $limiter  Rate limiter.
	 */
	public function __construct( ?SessionStore $store = null, ?Turn $turn = null, ?Mentions $mentions = null, ?StepRateLimiter $limiter = null ) {

		$this->store    = $store ?? new SessionStore();
		$this->turn     = $turn ?? new Turn();
		$this->mentions = $mentions ?? new Mentions();
		$this->limiter  = $limiter ?? new StepRateLimiter();
	}

	/**
	 * Run one relay round.
	 *
	 * @since 3.15.0
	 *
	 * @param int       $user_id Current user.
	 * @param TurnInput $input   The request.
	 *
	 * @return array|WP_Error The session view.
	 */
	public function turn( int $user_id, TurnInput $input ) {

		return $this->store->locked(
			$user_id,
			function ( string $token ) use ( $user_id, $input ) {

				// Read inside the lock: the lock has to cover the whole
				// read-modify-write, not just the step, or an overlapping request
				// saves its stale snapshot over a completed turn.
				$session = $this->session_for_turn( $input, $user_id );

				if ( is_wp_error( $session ) ) {
					return $session;
				}

				// Charged after the refusals above, so a client retrying on a busy
				// or funnel-conflict response does not burn its window for nothing.
				$limited = $this->limiter->attempt( $user_id );

				if ( is_wp_error( $limited ) ) {
					return $limited;
				}

				$started = time();

				$session->mark_started( $token );
				$this->store->save( $user_id, $session );

				$this->announce_started( $session, $input );

				// A Stop that ran before the save above found nothing running and left this turn's id.
				// Checked after the save, so a later Stop sees the turn in progress and stops it directly.
				if ( $this->store->take_stop( $user_id, $input->turn_id() ) ) {
					$session->mark_stopped();
					$this->store->save( $user_id, $session );

					$this->announce_interrupted( $session, 'stopped' );

					return $session->view();
				}

				$error = $this->run_step( $session );

				// Stop, Clear or a later step may have written the session while this
				// step was in flight. None of them take the lock, so re-read and yield.
				// The save stays inside the lock: a locked writer (a rating) landing
				// between unlock and save would otherwise be overwritten unseen, since
				// the yield check keys on the step token, which such a write keeps.
				$yielded = $this->concurrent_change_view( $user_id, $started, $token );

				if ( $yielded === null ) {
					$session->record_step_result( $error );
					$this->store->save( $user_id, $session );

					$this->announce_step_result( $session, $error );
				}

				return $yielded ?? $session->view();
			}
		);
	}

	/**
	 * Stop the in-progress turn.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $user_id Current user.
	 * @param string $turn_id Client id of the turn being stopped, or ''.
	 *
	 * @return array The session view.
	 */
	public function stop( int $user_id, string $turn_id = '' ): array {

		// A Stop can reach the server before its turn's own request has saved anything,
		// so it leaves the turn's id for that request to find.
		if ( $turn_id !== '' ) {
			$this->store->request_stop( $user_id, $turn_id );
		}

		$session = $this->store->get( $user_id );

		if ( $session === null ) {
			// A Stop on a session that has gone missing must still be on
			// record, or an in-flight step reads the absence as a lost write
			// and saves its result.
			$this->store->clear( $user_id );
			$this->store->force_unlock( $user_id );

			return Session::none_view();
		}

		$status = $session->status();

		if ( in_array( $status, [ Session::STATUS_IN_PROGRESS, Session::STATUS_ERROR ], true ) ) {
			$session->mark_stopped();
			$this->store->save( $user_id, $session );
		}

		// A Stop on an errored session only dismisses the error, which was already reported.
		if ( $status === Session::STATUS_IN_PROGRESS ) {
			$this->announce_interrupted( $session, 'stopped' );
		}

		// Released on every Stop, whatever the status, so the next prompt is
		// not refused with sc_ai_busy for the rest of the lock's TTL.
		$this->store->force_unlock( $user_id );

		return $session->view();
	}

	/**
	 * Clear the session.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id Current user.
	 *
	 * @return array The empty view.
	 */
	public function clear( int $user_id ): array {

		$session = $this->store->get( $user_id );

		$this->store->clear( $user_id );
		$this->store->force_unlock( $user_id );

		if ( $session !== null ) {
			/**
			 * The user cleared their assistant session.
			 *
			 * @since 3.15.0
			 *
			 * @param Session $session The session as it was before the clear.
			 */
			do_action( 'sugar_calendar_ai_session_cleared', $session ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		}

		return Session::none_view();
	}

	/**
	 * Announce a session that has just taken its first prompt.
	 *
	 * @since 3.15.0
	 *
	 * @param Session   $session The session, already saved.
	 * @param TurnInput $input   The request.
	 *
	 * @return void
	 */
	private function announce_started( Session $session, TurnInput $input ) {

		// turn_seq is 1 only for the session's first prompt: a stopped or
		// failed first turn keeps its number and the next prompt is turn 2.
		if ( $input->prompt() !== '' && $session->state()->turn_seq() === 1 ) {
			/**
			 * A new assistant session took its first prompt and was saved.
			 *
			 * @since 3.15.0
			 *
			 * @param Session   $session The session.
			 * @param TurnInput $input   The request that started it.
			 */
			do_action( 'sugar_calendar_ai_session_started', $session, $input ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		}
	}

	/**
	 * Announce a step that ended in an error or a refusal.
	 *
	 * @since 3.15.0
	 *
	 * @param Session       $session The session, already saved.
	 * @param WP_Error|null $error   The step's error, if any.
	 *
	 * @return void
	 */
	private function announce_step_result( Session $session, $error ) {

		if ( is_wp_error( $error ) ) {
			$this->announce_interrupted( $session, 'error', $error->get_error_code() );
		} elseif ( $session->state()->refused() ) {
			$this->announce_interrupted( $session, 'refusal' );
		}

		if ( ! is_wp_error( $error ) ) {
			$this->announce_conversion( $session );
		}
	}

	/**
	 * Announce the session's first write, once the save that keeps it has happened.
	 *
	 * @since 3.15.0
	 *
	 * @param Session $session The session, already saved.
	 *
	 * @return void
	 */
	private function announce_conversion( Session $session ) {

		$conversion = $session->state()->conversion();

		if ( $conversion === null ) {
			return;
		}

		/**
		 * The session's first turn that wrote an event has finished.
		 *
		 * @since 3.15.0
		 *
		 * @param TurnState $state   The session state, counters already updated.
		 * @param string    $action  `create` or `update`.
		 * @param int       $updated Events created or successfully updated by the turn.
		 * @param int       $failed  Events the turn failed to update.
		 */
		do_action( 'sugar_calendar_ai_session_converted', $session->state(), $conversion['action'], $conversion['updated'], $conversion['failed'] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Announce a turn that did not end in an answer.
	 *
	 * @since 3.15.0
	 *
	 * @param Session $session    The session, already saved.
	 * @param string  $reason     One of `error`, `refusal` or `stopped`.
	 * @param string  $error_code WP_Error code for `error`, otherwise empty.
	 *
	 * @return void
	 */
	private function announce_interrupted( Session $session, string $reason, string $error_code = '' ) {

		/**
		 * A turn ended in an error, a refusal or a Stop instead of an answer.
		 *
		 * @since 3.15.0
		 *
		 * @param Session $session    The session.
		 * @param string  $reason     `error`, `refusal` or `stopped`.
		 * @param string  $error_code WP_Error code for `error`, otherwise empty.
		 */
		do_action( 'sugar_calendar_ai_turn_interrupted', $session, $reason, $error_code ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * The stored session's view, for hydration.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id Current user.
	 *
	 * @return array
	 */
	public function current( int $user_id ): array {

		$session = $this->store->get( $user_id );

		return $session === null ? Session::none_view() : $session->view();
	}

	/**
	 * Run one step on a session already saved as in progress.
	 *
	 * The caller saves first so the stored row carries this step's token before
	 * the round begins. The guard covers only `step()`: a `Throwable` from the
	 * relay client becomes a recorded step error, while one from the store is a
	 * genuine 500.
	 *
	 * @since 3.15.0
	 *
	 * @param Session $session The session to step.
	 *
	 * @return WP_Error|null The step's outcome.
	 */
	private function run_step( Session $session ) {

		try {
			return $this->turn->step( $session->state() );
		} catch ( Throwable $e ) {
			// Never log the exception message: it can carry provider or
			// exception text, and only codes are allowed to travel.
			return new WP_Error( 'sc_ai_step_exception', __( 'The assistant hit an unexpected error.', 'sugar-calendar-lite' ), [ 'status' => 500 ] );
		}
	}

	/**
	 * The view to return when something else wrote the session during the step,
	 * or null when this step's own result stands.
	 *
	 * A session that has gone missing counts as a removal only when the removal
	 * is on record; otherwise it was lost (an evicted transient, a failed write)
	 * and throwing away a round the relay already charged for would be wrong.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $user_id Current user.
	 * @param int    $started Unix time the step began.
	 * @param string $token   This step's owner token.
	 *
	 * @return array|null
	 */
	private function concurrent_change_view( int $user_id, int $started, string $token ) {

		$current = $this->store->get( $user_id );

		if ( $current === null ) {
			return $this->store->cleared_since( $user_id, $started ) ? Session::none_view() : null;
		}

		// Someone wrote the session after this step started: Stop, Clear then a
		// new prompt, or another step. Yield to whatever is stored now.
		return $current->step_token() === $token ? null : $current->view();
	}

	/**
	 * The session this turn runs on: a continuation or retry of the stored one,
	 * or a new turn started from a prompt.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnInput $input   The request.
	 * @param int       $user_id Current user.
	 *
	 * @return Session|WP_Error
	 */
	private function session_for_turn( TurnInput $input, int $user_id ) {

		$session = $this->store->get( $user_id );

		if ( $input->prompt() !== '' ) {
			return $this->session_for_prompt( $input, $session );
		}

		if ( $session === null || $session->status() === Session::STATUS_IDLE ) {
			return new WP_Error( 'sc_ai_prompt_required', __( 'A message is required.', 'sugar-calendar-lite' ), [ 'status' => 400 ] );
		}

		if ( $session->status() === Session::STATUS_ERROR ) {
			$session->prepare_retry();
		}

		return $session;
	}

	/**
	 * Queue a new user prompt, refusing when another funnel or turn is open.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnInput    $input   The request.
	 * @param Session|null $session Stored session, if any.
	 *
	 * @return Session|WP_Error
	 */
	private function session_for_prompt( TurnInput $input, ?Session $session ) {

		$funnel = $input->funnel();

		if ( $session !== null ) {

			if ( $funnel !== '' && $funnel !== $session->funnel() ) {
				return new WP_Error( 'sc_ai_funnel_active', __( 'A different assistant task is open. Clear the chat first.', 'sugar-calendar-lite' ), [ 'status' => 409 ] );
			}

			// A live overlapping request is already refused by the lock, so this
			// is the stale-status case: a previous step died from a fatal and
			// left the session in progress with no lock held.
			if ( $session->status() === Session::STATUS_IN_PROGRESS ) {
				return new WP_Error( 'sc_ai_busy', __( 'The assistant is still working.', 'sugar-calendar-lite' ), [ 'status' => 409 ] );
			}
		}

		if ( $session === null ) {

			if ( $funnel === '' ) {
				return new WP_Error( 'sc_ai_funnel_required', __( 'Choose what you want help with first.', 'sugar-calendar-lite' ), [ 'status' => 400 ] );
			}

			$session = new Session( new TurnState( $funnel, $input->context() ) );

			// Seeded once, before this turn's mentions so they still rank first.
			$session->state()->remember_entities( $this->mentions->seed() );
		}

		$session->start_turn( $input->prompt(), $input->confirmed(), $input->picks() );

		$session->state()->remember_mentions( $this->mentions->resolve( $input->mentions() ) );

		return $session;
	}
}
