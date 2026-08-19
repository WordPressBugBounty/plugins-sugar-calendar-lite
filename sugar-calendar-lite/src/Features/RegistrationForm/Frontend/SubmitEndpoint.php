<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\Abandonment\RecipientResolver;
use Sugar_Calendar\Features\RegistrationForm\Email\AnswersConfirmationEmail;
use Sugar_Calendar\Features\RegistrationForm\RateLimiter;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;
use Sugar_Calendar\Features\RegistrationForm\WriteFailureLog;
use Throwable;
use WP_Error;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;

/**
 * The after-checkout submit endpoint, the feature's only unauthenticated write path.
 *
 * Authorisation is the token minted with the pending rows (PendingRows::mint());
 * knowing it is the capability, so no nonce is required, as Track D's resume link
 * carries none. It is deliberately not the order uuid, which leaks into emails,
 * browser history, Referer headers and access logs (spec §2.1).
 *
 * The order of the checks in handle() is the security property: context comes from
 * the matched rows, never the request, and ticket-type targeting is not re-derived.
 *
 * Every wp_send_json_*() call is followed by an explicit return; it only wp_die()s
 * when wp_doing_ajax() is true, which is not guaranteed in test/CLI contexts.
 *
 * @since 3.13.0
 */
class SubmitEndpoint {

	/**
	 * AJAX action.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ACTION = 'sc_registration_submit';

	/**
	 * Write-failure codes that no retry can ever clear.
	 *
	 * Anything else, a $wpdb error above all, is treated as retryable: the modal's
	 * terminal branch discards every answer the visitor typed, so a futile retry is
	 * the cheaper mistake.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const TERMINAL_WRITE_CODES = [
		'registration_submit_no_match',
		'registration_submit_row_vanished',
	];

	/**
	 * Register hooks.
	 *
	 * Registered for both logged-in and logged-out visitors: a ticket buyer is
	 * usually neither an author nor logged in at all.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Handle a submission.
	 *
	 * @since 3.13.0
	 */
	public function handle() {
		/*
		 * The hard ceiling: spent by every request, before any lookup, so an abusive
		 * IP cannot make the site work to discover its token is worthless.
		 * RateLimiter::attempt() fails open internally, so a false here is real
		 * budget exhaustion. The tighter per-IP budget is spent by misses only, in
		 * fail_unresolved_token().
		 */
		if ( ! RateLimiter::attempt( RateLimiter::ACTION_SUBMIT_CEILING ) ) {
			$this->fail_throttled();

			return;
		}

		$rows = $this->resolve_rows();

		if ( $rows === null ) {
			$this->fail_unresolved_token();

			return;
		}

		$renderer = Renderer::for_event( (int) $rows[0]['event_id'] );

		if ( $renderer === null ) {
			$this->fail_generic();

			return;
		}

		$schema = $renderer->schema();

		// One boolean decides the whole request. A mixed token is never an edit:
		// TokenResume only reopens a context with nothing left pending, and the two
		// surfaces must agree on which rows a token addresses.
		$is_edit = $this->all_complete( $rows ) && ! empty( $schema['allow_edit'] );

		if ( $this->all_complete( $rows ) && ! $is_edit ) {
			wp_send_json_success(
				[
					'completed' => true,
					'already'   => true,
				]
			);

			return;
		}

		// A form switched back to before-checkout, disabled, emptied, or downgraded
		// to Lite stops accepting late writes — but an edit reopens answers already
		// collected, so the collection mode no longer applies to it.
		if ( ! $is_edit && $renderer->mode() !== 'after' ) {
			$this->fail_generic();

			return;
		}

		// The host gate belongs on the write path too, not only where TokenResume
		// decides what to print: the edit link lives in an inbox, and the form markup
		// sits in a browser from before the refund. Without this, a refunded order's
		// stored answers stay rewritable — and confirm() would mail a fresh edit link
		// for an order the host already withdrew.
		if ( $is_edit && ! $this->host_permits_write( $rows ) ) {
			$this->fail_generic();

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The token IS the capability; see the class docblock. AnswerRequest normalises/unslashes $_POST.
		$answers = AnswerRequest::from_post( $_POST, true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		if ( AnswerRequest::leaf_count_mismatch( $_POST, $answers ) ) {
			wp_send_json_error( [ 'registration' => [ 'truncated' => ResponseGate::truncation_message() ] ] );

			return;
		}

		$result = ResponseGate::validate( $schema, $answers, $this->applicable( $rows, $is_edit, $answers ) );

		if ( empty( $result['valid'] ) ) {
			wp_send_json_error( [ 'registration' => [ 'errors' => $result['errors'] ] ] );

			return;
		}

		$write_result = $this->store( $rows, (array) $result['answers'], $is_edit );

		// A DB failure, or a submission matching none of the token's rows, must never
		// report success: the rows would stay pending while the buyer is told they
		// are done. See store()'s docblock.
		if ( $write_result instanceof WP_Error ) {
			$this->fail_write( $write_result );

			return;
		}

		$this->confirm( $rows, $schema );

		wp_send_json_success( [ 'completed' => true ] );
	}

	/**
	 * Whether both of this context's host states still permit a rewrite.
	 *
	 * The render-side twin is TokenResume::host_permits_printing(). This one reads
	 * the event from the token's rows rather than the queried post, because an AJAX
	 * request has no queried post to cross-check against.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows Rows sharing one token.
	 *
	 * @return bool
	 */
	private function host_permits_write( array $rows ) {

		$event = sugar_calendar_get_event( (int) $rows[0]['event_id'] );

		$post_id = isset( $event->object_id ) ? (int) $event->object_id : 0;

		if ( ! HostState::event_permits_printing( $post_id ) ) {
			return false;
		}

		// RSVP has no withdrawn state of its own.
		if ( (string) $rows[0]['context'] !== 'order' ) {
			return true;
		}

		return HostState::order_permits_printing( get_order( (int) $rows[0]['context_id'] ) );
	}

	/**
	 * Resolve the token from the request into its matched rows.
	 *
	 * @since 3.13.0
	 *
	 * @return array[]|null Rows sharing one token, or null when the token is
	 *                      missing, malformed, or matches nothing.
	 */
	private function resolve_rows() {

		$token = $this->posted_token();

		if ( $token === '' ) {
			return null;
		}

		$rows = ResponseRepository::find_by_token( $token );

		if ( $rows === [] ) {
			return null;
		}

		// The authoritative comparison, checked on every row rather than just
		// $rows[0]: find_by_token() is only an indexed fast path, and a later
		// refactor of that query must not be able to weaken rows 1..N while row 0
		// still happens to match.
		foreach ( $rows as $row ) {
			if ( ! hash_equals( (string) $row['token'], $token ) ) {
				return null;
			}
		}

		return $rows;
	}

	/**
	 * The token from the request.
	 *
	 * Lowercase hex only, matching what bin2hex() mints. The token column uses the
	 * database's case-insensitive collation, so an uppercase variant matches the row
	 * but then fails the hash_equals() comparison in resolve_rows(); since the token
	 * arrives by emailed link, a mail client that mangles case would otherwise show
	 * "no longer valid" to a respondent holding a perfectly good one.
	 *
	 * @since 3.13.0
	 *
	 * @return string The token, or '' when absent or malformed.
	 */
	private function posted_token() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The token IS the capability; see the class docblock.
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		// PendingRows::is_valid_token() is the one definition of the token shape: it
		// decides what the render seams print, so it must also decide what this
		// endpoint accepts. The sanitize above runs first on purpose, so a token
		// arriving with surrounding whitespace is still accepted.
		if ( ! PendingRows::is_valid_token( $token ) ) {
			return '';
		}

		return $token;
	}

	/**
	 * Whether every row of the context is already complete.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows Rows sharing one token.
	 *
	 * @return bool
	 */
	private function all_complete( array $rows ) {

		foreach ( $rows as $row ) {
			if ( $row['status'] !== 'complete' ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The applicable-attendee list, derived from the rows rather than the schema.
	 *
	 * Renderer::applicable_attendees() is deliberately not used: it re-derives
	 * applicability from the current schema, so a `collect` changed after mint would
	 * validate a key set agreeing with neither the minted rows nor the form the buyer
	 * sees, stranding rows as pending forever. Ticket-type targeting is likewise not
	 * re-derived; it was applied once at mint, and pending rows carry no ticket_type.
	 *
	 * Rows already 'complete' are excluded on a normal submit, matching what the
	 * receipt renders (TicketingReceipt::pending_rows()), so no `required` error can
	 * land on a field the buyer can no longer see. On an edit ($is_edit) they are
	 * included instead — rewriting them is the point. KEY_PATTERN is re-checked so a row whose
	 * attendee_key is no longer key-shaped never reaches ResponseGate::validate().
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows    Rows sharing one token.
	 * @param bool    $is_edit Whether this request is an accepted edit of already-
	 *                         complete rows (handle()'s $is_edit).
	 * @param array   $posted  The request's answers keyed by attendee key, used on an
	 *                         edit to ignore attendees the payload never mentioned.
	 *
	 * @return array[]
	 */
	private function applicable( array $rows, $is_edit = false, array $posted = [] ) {

		$attendees = [];

		foreach ( $rows as $row ) {

			// Excluding an answered row is right while it is unanswerable, but on an
			// edit it would hand ResponseGate an empty applicable list — so nothing
			// would validate, store() would match no row, and the respondent would be
			// told their edit failed while nothing was attempted.
			if ( ! $is_edit && $row['status'] === 'complete' ) {
				continue;
			}

			$key = (string) $row['attendee_key'];

			if ( ! preg_match( AnswerRequest::KEY_PATTERN, $key ) ) {
				continue;
			}

			// ResponseGate::validate() emits a sanitized entry for every applicable
			// attendee, present in the payload or not, and store() rewrites whatever it
			// is handed. On an edit that would blank an attendee the payload omitted, so
			// an unmentioned one is dropped here instead. A normal submit keeps them:
			// a missing pending answer is a `required` error, not a silent skip.
			if ( $is_edit && ! isset( $posted[ $key ] ) ) {
				continue;
			}

			$attendees[] = [
				'key'         => $key,
				'ticket_type' => 0,
			];
		}

		return $attendees;
	}

	/**
	 * Write the accepted answers to their rows.
	 *
	 * Rows are matched on attendee_key, never on row order or an id from the payload.
	 * On a normal submit a row already 'complete' is left untouched: a token's rows can
	 * be a mix of statuses, and that path only moves pending to complete. On an edit
	 * ($is_edit) a complete row is rewritten instead, via rewrite_row().
	 *
	 * $matched counts every row the answers addressed, written or skipped, so a
	 * submission whose addressed rows were all already complete still succeeds
	 * (written may legitimately be 0) while one that addressed nothing is a failure.
	 *
	 * A row that vanished mid-flight is the third failure and is not a DB error:
	 * mark_complete() reports 0 affected rows. A concurrent RSVP reconcile or a
	 * Cleanup deletion can remove a row between find_by_token() and the write, and no
	 * retry can help, so the visitor must not see a success card. No follow-up read is
	 * needed to interpret that 0 (as RsvpCheckout::reconcile() needs): the row was
	 * just confirmed 'pending' and this write always changes status, so a live row
	 * always affects 1.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows    Rows sharing one token.
	 * @param array   $answers Sanitized answers keyed by attendee key.
	 * @param bool    $is_edit Whether this request is an accepted edit of already-
	 *                         complete rows (handle()'s $is_edit).
	 *
	 * @return int|WP_Error Count of rows newly marked complete (may be 0 when
	 *                       every addressed row was already complete), or a
	 *                       WP_Error when nothing was addressed at all, a row
	 *                       write failed at the DB, or an addressed row no
	 *                       longer existed.
	 */
	private function store( array $rows, array $answers, $is_edit = false ) {

		$written = 0;
		$matched = 0;

		foreach ( $rows as $row ) {

			$key = (string) $row['attendee_key'];

			if ( ! isset( $answers[ $key ] ) ) {
				continue;
			}

			++$matched;

			if ( $row['status'] === 'complete' ) {

				if ( ! $is_edit ) {
					continue;
				}

				$result = $this->rewrite_row( $row, (array) $answers[ $key ] );
			} else {
				$result = $this->complete_row( $row, (array) $answers[ $key ] );
			}

			if ( $result instanceof WP_Error ) {

				// The visitor only ever sees one generic sentence, so without this the
				// organizer, the only party who can act, is never told. record() never
				// stores the answers, so the whole row can be passed as
				// ResponsePersister::persist() does.
				WriteFailureLog::record( $row, $result );

				return $result;
			}

			++$written;
		}

		if ( $matched === 0 ) {
			$error = new WP_Error( 'registration_submit_no_match', 'The submission matched none of the token\'s rows.' );

			// No single row failed, the whole submission addressed none of them, so
			// only the context is recorded (TicketingCheckout's shape).
			WriteFailureLog::record( $this->context_of( $rows ), $error );

			return $error;
		}

		return $written;
	}

	/**
	 * Email the respondent that their answers are in.
	 *
	 * Best effort by design: store() already wrote the answers before confirm()
	 * runs, so a mail failure surfaced as a submit error would tell the
	 * respondent their answers were lost when they were not — inviting a
	 * resubmit of data that is already saved. Wrapped and logged the same way
	 * EventMeetingManager::sync() wraps its relay calls: nothing the mail
	 * sender (or a `sc_email_message` filter it triggers) can throw is allowed
	 * to escape and pre-empt handle()'s wp_send_json_success(). Only the
	 * organizer, who reads the log, can act on a failure here.
	 *
	 * One mail per context, not per row — an each_attendee schema completes
	 * several rows in one request, and they share a respondent.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows   Rows sharing one token.
	 * @param array   $schema The event's validated schema.
	 */
	private function confirm( array $rows, array $schema ) {

		$row       = (array) $rows[0];
		$recipient = RecipientResolver::for_context( (string) $row['context'], (int) $row['context_id'] );

		if ( $recipient === [] ) {
			return;
		}

		try {
			AnswersConfirmationEmail::send(
				[
					'to'         => $recipient['email'],
					'name'       => $recipient['name'],
					'event_id'   => (int) $row['event_id'],
					'context'    => (string) $row['context'],
					'context_id' => (int) $row['context_id'],
					'token'      => (string) $row['token'],
					'allow_edit' => ! empty( $schema['allow_edit'] ),
				]
			);
		} catch ( Throwable $e ) {
			error_log( '[SC Registration Form] confirmation email failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * The order/RSVP a token's rows belong to, in WriteFailureLog's row shape.
	 *
	 * Deliberately carries no attendee_key: the failure recorded here is the whole
	 * submission matching nothing, which is not any one row's fault.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows Rows sharing one token. Every row of a token shares
	 *                      its context, so the first is representative.
	 *
	 * @return array
	 */
	private function context_of( array $rows ) {

		$row = isset( $rows[0] ) ? (array) $rows[0] : [];

		return [
			'context'    => isset( $row['context'] ) ? $row['context'] : '',
			'context_id' => isset( $row['context_id'] ) ? $row['context_id'] : 0,
		];
	}

	/**
	 * Mark one pending row complete, resolving mark_complete()'s return shapes.
	 *
	 * Extracted from store() so one write's three outcomes (DB error, vanished row,
	 * success) are interpreted in one place, and store() stays inside the project's
	 * cyclomatic-complexity ceiling.
	 *
	 * Neither failure reaches the requester beyond the message fail_write() picks, as
	 * a row id or $wpdb error would tell an unauthenticated caller which rows exist.
	 * The codes still matter: fail_write() reads them to tell the terminal vanished
	 * row from a retryable $wpdb failure, and both are recorded to WriteFailureLog.
	 *
	 * @since 3.13.0
	 *
	 * @param array $row     The row to complete. Callers must have confirmed it is still
	 *                       'pending'; the vanished-row reading depends on that.
	 * @param array $answers Sanitized answers for this row's attendee key.
	 *
	 * @return true|WP_Error
	 */
	private function complete_row( array $row, array $answers ) {

		$result = ResponseRepository::mark_complete( (int) $row['id'], $answers );

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		// Zero affected rows is not success: the row was deleted between
		// find_by_token() and this write. See store()'s docblock.
		if ( $result === 0 ) {
			return new WP_Error(
				'registration_submit_row_vanished',
				'An addressed row no longer exists; its answers were not stored.'
			);
		}

		return true;
	}

	/**
	 * Rewrite one already-complete row's answers.
	 *
	 * Separate from complete_row() because that method's vanished-row reading
	 * does not hold here: it treats 0 affected rows as "deleted mid-flight",
	 * which is sound only while the write also changes `status`. A rewrite
	 * changes no column when the answers are identical (and `updated_at` has
	 * second granularity), so a respondent who saves without editing anything
	 * would otherwise be told it failed. Existence decides it instead.
	 *
	 * @since 3.13.0
	 *
	 * @param array $row     The row to rewrite. Callers must have confirmed it is 'complete'.
	 * @param array $answers Sanitized answers for this row's attendee key.
	 *
	 * @return true|WP_Error
	 */
	private function rewrite_row( array $row, array $answers ) {

		$result = ResponseRepository::mark_complete( (int) $row['id'], $answers );

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		if ( $result === 0 && ResponseRepository::get( (int) $row['id'] ) === null ) {
			return new WP_Error(
				'registration_submit_row_vanished',
				'An addressed row no longer exists; its answers were not stored.'
			);
		}

		return true;
	}

	/**
	 * Send the one generic failure and stop.
	 *
	 * Bad token and unknown token share this response, so the endpoint is not an
	 * oracle for which tokens exist. Throttling is kept out of it (fail_throttled()),
	 * being keyed on the requester's IP rather than the token.
	 *
	 * `terminal` tells after.js the outcome cannot be retried, so it offers a way out
	 * of the modal instead of re-enabling Submit; the modal has no close button,
	 * backdrop click or Escape, so a repeating failure would otherwise trap the
	 * visitor. Retryable failures must not come through here.
	 *
	 * Plain __(), not esc_html__(): after.js paints this via .text(), so pre-escaping
	 * would double-encode an apostrophe as the literal "&#039;".
	 *
	 * @since 3.13.0
	 */
	private function fail_generic() {

		wp_send_json_error(
			[
				'message'  => __( 'This registration link is no longer valid. Please contact the organizer.', 'sugar-calendar-lite' ),
				'terminal' => true,
			]
		);
	}

	/**
	 * Refuse a request whose token resolved to nothing, and charge it.
	 *
	 * The per-IP submit budget is spent here, by misses only. When every request spent
	 * it, an abuser sending bad tokens could exhaust the window for every genuine
	 * respondent behind the same NAT (an office, conference wifi), leaving them
	 * holding a paid ticket with no way to answer. Telling the two apart costs one
	 * indexed SELECT for a request that turns out to be bogus; handle()'s ceiling
	 * bounds how many of those an IP gets.
	 *
	 * The throttled message is safe to distinguish from fail_generic()'s, since it
	 * says nothing about the token, only about how often this IP has missed.
	 *
	 * @since 3.13.0
	 */
	private function fail_unresolved_token() {

		if ( ! RateLimiter::attempt( RateLimiter::ACTION_SUBMIT ) ) {
			$this->fail_throttled();

			return;
		}

		$this->fail_generic();
	}

	/**
	 * Send a write failure, terminal or retryable according to its code.
	 *
	 * @since 3.13.0
	 *
	 * @param WP_Error $error The failure store() returned.
	 */
	private function fail_write( WP_Error $error ) {

		if ( in_array( $error->get_error_code(), self::TERMINAL_WRITE_CODES, true ) ) {
			$this->fail_generic();

			return;
		}

		$this->fail_transient();
	}

	/**
	 * Send a retryable write failure and stop.
	 *
	 * A $wpdb failure inside store() is a lock timeout or a dropped connection, so a
	 * retry moments later can genuinely succeed. No `terminal` key, so after.js
	 * re-enables Submit. store() records it to WriteFailureLog either way, because an
	 * abandoned retry leaves the row pending with nobody informed.
	 *
	 * Plain __() for the same .text()-consumption reason as fail_generic().
	 *
	 * @since 3.13.0
	 */
	private function fail_transient() {

		wp_send_json_error(
			[
				'message' => __( 'Your answers could not be saved just now. Please try again.', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * Send the throttled failure and stop.
	 *
	 * Deliberately distinct from fail_generic(): the limiter is keyed on (action,
	 * REMOTE_ADDR), a property of the requester rather than the token, so there is no
	 * oracle risk in saying so plainly, and a shared-NAT office can hit the budget
	 * without any token ever being wrong.
	 *
	 * Carries no `terminal` key, and must never grow one: waiting is the retry, so
	 * after.js has to leave Submit usable.
	 *
	 * Plain __() for the same .text()-consumption reason as fail_generic().
	 *
	 * @since 3.13.0
	 */
	private function fail_throttled() {

		wp_send_json_error(
			[
				'message' => __( 'Please wait a moment and try again.', 'sugar-calendar-lite' ),
			]
		);
	}
}
