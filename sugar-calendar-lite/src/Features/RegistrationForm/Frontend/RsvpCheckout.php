<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Event;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;
use Sugar_Calendar\Features\RegistrationForm\WriteFailureLog;
use Throwable;
use WP_Error;

/**
 * Registration Form collection inside the RSVP response modal.
 *
 * The RSVP counterpart to TicketingCheckout, sharing Renderer, AnswerRequest and
 * ResponseGate. Unlike ticketing, a resubmission has to reconcile stored rows
 * rather than only insert them; the matching rules live in PendingRowReconciler,
 * while this class resolves who is submitting and applies the outcome.
 *
 * Every hook used here is added by sc-rsvp, so nothing fires without the add-on.
 *
 * @since 3.13.0
 */
class RsvpCheckout {

	/**
	 * The context value stored on every row this host writes.
	 *
	 * @since 3.13.0
	 */
	const CONTEXT = 'rsvp';

	/**
	 * The gate's result for this request.
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	private $gate_result = [];

	/**
	 * The Event the main-column seam rendered the step for.
	 *
	 * The footer seam gets no Event, and re-deriving one from get_the_ID() misses
	 * recurring events, so it is carried over from render_step() instead.
	 *
	 * @since 3.13.0
	 *
	 * @var Event|null
	 */
	private $rendered_event = null;

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_rsvp_frontend_modal_response_main_bottom', [ $this, 'render_step' ], 10, 2 );
		add_action( 'sugar_calendar_rsvp_frontend_modal_response_footer', [ $this, 'render_footer_back' ] );
		add_action( 'sugar_calendar_rsvp_frontend_after_rsvp_box', [ $this, 'maybe_confirm' ], 10, 2 );
		add_filter( 'sugar_calendar_rsvp_event_integration_pre_save_errors', [ $this, 'validate' ], 10, 3 );
		add_action( 'sugar_calendar_rsvp_response_saved', [ $this, 'persist' ], 10, 3 );
		add_filter( 'sugar_calendar_rsvp_event_integration_promotion_pending_consumer_data', [ $this, 'carry_across_verification' ], 10, 1 );
	}

	/**
	 * Print the registration step inside the response modal's main column.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed      $event_rsvp The event's RSVP settings. Unused; applicability is
	 *                               a property of the registration schema.
	 * @param Event|null $event      The event object.
	 */
	public function render_step( $event_rsvp, $event = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $event_rsvp is part of the sugar_calendar_rsvp_frontend_modal_response_main_bottom action signature.

		$renderer = $this->renderer_for_event( $event );

		if ( $renderer === null || $renderer->mode() !== 'before' ) {
			return;
		}

		$renderer->enqueue();

		// Kept for the footer seam, which is handed no Event of its own. The main
		// column renders first, so render_footer_back() sees the right Event.
		$this->rendered_event = $event;

		echo $renderer->render_step( self::CONTEXT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer.
	}

	/**
	 * Render the step's Back control inside the response modal's own footer.
	 *
	 * Runs the same gate as render_step() rather than assuming that one passed: this
	 * is a separate seam, and a Back button on a modal with no step goes nowhere.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $event_rsvp The event's RSVP settings. Unused; applicability is a
	 *                          property of the registration schema.
	 */
	public function render_footer_back( $event_rsvp ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $event_rsvp is part of the sugar_calendar_rsvp_frontend_modal_response_footer action signature.

		$renderer = $this->renderer_for_event(
			$this->rendered_event !== null ? $this->rendered_event : $this->event_from_request()
		);

		if ( $renderer === null || $renderer->mode() !== 'before' ) {
			return;
		}

		echo $renderer->render_footer_back(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer.
	}

	/**
	 * Raise the confirmation on the reloaded event page, once.
	 *
	 * The add-on answers a submitted RSVP with `location.reload()`, so the modal the
	 * visitor filled is gone before anything can be shown in it. Prints nothing: the
	 * only decision here is whether to load the controller that owns the stage, and
	 * a cache serving that script to a visitor with no note shows them nothing.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed      $event_rsvp The event's RSVP settings. Unused; applicability is
	 *                               a property of the registration schema.
	 * @param Event|null $event      The event object.
	 */
	public function maybe_confirm( $event_rsvp, $event = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $event_rsvp is part of the sugar_calendar_rsvp_frontend_after_rsvp_box action signature.

		$renderer = $this->renderer_for_event( $event );

		if ( $renderer === null || $renderer->mode() !== 'before' ) {
			return;
		}

		$event_id = $renderer->event_id();

		if ( ! CompletionFlash::has( $event_id ) ) {
			return;
		}

		$renderer->enqueue();

		// The button keeps its default "Close": an RSVP has no order to send anyone to.
		AfterCheckoutAssets::enqueue(
			[
				'openSuccess' => true,
				'flashCookie' => CompletionFlash::cookie_name( $event_id ),
			]
		);
	}

	/**
	 * Resolve the current event when the footer seam gives us none.
	 *
	 * Fallback only, prefer $rendered_event: this misses recurring events and
	 * occurrence URLs, having nothing but get_the_ID() to work from.
	 *
	 * @since 3.13.0
	 *
	 * @return Event|null
	 */
	private function event_from_request() {

		return sugar_calendar_get_event_by_object( get_the_ID() );
	}

	/**
	 * Resolve the renderer for an event object.
	 *
	 * @since 3.13.0
	 *
	 * @param Event|null $event The event object as the host passed it.
	 *
	 * @return Renderer|null
	 */
	private function renderer_for_event( $event ) {

		$event_id = is_object( $event ) && ! empty( $event->id ) ? (int) $event->id : 0;

		if ( $event_id === 0 ) {
			return null;
		}

		return Renderer::for_event( $event_id );
	}

	/**
	 * Validate the submitted answers before the RSVP row is written.
	 *
	 * Runs on sc-rsvp's pre-save filter, so a failure persists nothing at all:
	 * no RSVP, no attendee rows, no answers, no confirmation email.
	 *
	 * @since 3.13.0
	 *
	 * @param array $errors    Errors keyed by group.
	 * @param array $rsvp_data The RSVP data about to be saved.
	 * @param array $post      The raw $_POST array (still slashed).
	 *
	 * @return array
	 */
	public function validate( $errors, $rsvp_data, $post ) {

		$event_id = isset( $rsvp_data['event_id'] ) ? (int) $rsvp_data['event_id'] : 0;
		$renderer = $event_id > 0 ? Renderer::for_event( $event_id ) : null;

		if ( $renderer === null || $renderer->mode() !== 'before' ) {
			return $errors;
		}

		// Not Going posts no attendees and collects nothing. Validating it would
		// make declining impossible on any event with a required question.
		if ( empty( $rsvp_data['going'] ) ) {
			return $errors;
		}

		$applicable = $renderer->applicable_attendees(
			self::attendees_from_post( $post ),
			false
		);

		$answers = AnswerRequest::from_post( $post, true );

		if ( AnswerRequest::leaf_count_mismatch( $post, $answers ) ) {
			$errors['registration'] = [ 'truncated' => ResponseGate::truncation_message() ];

			return $errors;
		}

		$this->gate_result = ResponseGate::validate( $renderer->schema(), $answers, $applicable );

		if ( empty( $this->gate_result['valid'] ) ) {
			$errors['registration'] = [ 'errors' => $this->gate_result['errors'] ];
		}

		return $errors;
	}

	/**
	 * Build the attendee list from an RSVP submission.
	 *
	 * The RSVP owner is `main`; each additional row is `a{rowId}` from the row's own
	 * data-row-id. `row_id` is a per-render positional counter sc-rsvp renumbers on
	 * every render, so an attendee_key is stable only within one submission; see
	 * resolve_attendee_ids() for how a key is paired to a real attendee record.
	 *
	 * The submit endpoint is unauthenticated, so `rowId` is attacker-controlled and
	 * its shape is checked rather than coerced (absint() would fold '-1' onto a
	 * genuine row 1). Keys are de-duplicated, and the list is capped by
	 * AnswerRequest::max_attendees(); it decides how many rows reconcile() writes,
	 * so uncapped it was bounded only by max_input_vars.
	 *
	 * RSVP has no ticket types, so every entry carries ticket_type 0.
	 *
	 * @since 3.13.0
	 *
	 * @param array $post The raw $_POST array.
	 *
	 * @return array[]
	 */
	public static function attendees_from_post( array $post ) {

		$attendees = [
			[
				'key'         => AnswerRequest::MAIN_KEY,
				'ticket_type' => 0,
			],
		];

		$rows = isset( $post['additionalAttendees'] ) && is_array( $post['additionalAttendees'] )
			? $post['additionalAttendees']
			: [];

		$seen_keys = [];
		$max       = AnswerRequest::max_attendees();

		foreach ( $rows as $row ) {

			if ( count( $attendees ) >= $max ) {
				break;
			}

			$key = self::attendee_key_from_row( $row );

			if ( $key === '' || isset( $seen_keys[ $key ] ) ) {
				continue;
			}

			$seen_keys[ $key ] = true;

			$attendees[] = [
				'key'         => $key,
				'ticket_type' => 0,
			];
		}

		return $attendees;
	}

	/**
	 * The attendee key one submitted repeater row maps to, or '' when it maps to none.
	 *
	 * Split out of attendees_from_post() to stay inside the phpcs complexity ceiling.
	 *
	 * `rowId` is attacker-controlled (see attendees_from_post()): ctype_digit rejects
	 * signs, decimals and whitespace, KEY_PATTERN bounds the digit count, and the
	 * explicit `a0` check excludes the add-on's hidden template row. Both builders of
	 * this key apply the same three, so only one shape is ever emitted.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $row One entry from $_POST['additionalAttendees'].
	 *
	 * @return string
	 */
	private static function attendee_key_from_row( $row ) {

		if ( ! is_array( $row ) || ! isset( $row['rowId'] ) || ! ctype_digit( (string) $row['rowId'] ) ) {
			return '';
		}

		$key = 'a' . $row['rowId'];

		if ( ! preg_match( AnswerRequest::KEY_PATTERN, $key ) || $key === 'a0' ) {
			return '';
		}

		return $key;
	}

	/**
	 * The sanitized answers the gate accepted, keyed by attendee key.
	 *
	 * Read once in validate() and reused by persist(); re-reading $_POST after the save
	 * risks the two passes disagreeing, leaving an RSVP with no answers stored.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	public function validated_answers() {

		return isset( $this->gate_result['answers'] ) ? (array) $this->gate_result['answers'] : [];
	}

	/**
	 * Hand the just-validated answers to sc-rsvp to carry across the
	 * promotion-verification diversion.
	 *
	 * That diversion ends the submit request without creating the RSVP; it is created on
	 * the later confirm request, where $this->gate_result no longer exists. Without
	 * this, a visitor who confirms by email loses their answers silently.
	 *
	 * Only the answers are carried; the rest of what reconcile() needs is rebuilt from
	 * the $rsvp_data sc-rsvp reconstructs from the same transient.
	 *
	 * @since 3.13.0
	 *
	 * @param array $data Data to carry, keyed by consumer.
	 *
	 * @return array
	 */
	public function carry_across_verification( $data ) {

		$data = is_array( $data ) ? $data : [];

		$answers = $this->validated_answers();

		// Stash no key rather than an empty one, so persist() on the confirm side can
		// tell "carried nothing" from "carried empty".
		if ( empty( $answers ) ) {
			return $data;
		}

		$data[ self::CONTEXT ] = [ 'answers' => $answers ];

		return $data;
	}

	/**
	 * Store the validated answers, reconciling against what is already stored.
	 *
	 * RSVP is the one host that writes the same context more than once: the submit
	 * handler accepts an update and the visitor can flip Going to Not Going, so
	 * insert-only persistence would duplicate and orphan rows.
	 *
	 * Wrapped so a storage failure never breaks the RSVP, which is already saved by
	 * the time this runs. On the promotion-verification confirm request nothing
	 * validated here, so the answers arrive via $consumer_data instead.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $rsvp          The saved RSVP.
	 * @param array $rsvp_data     The data it was saved from.
	 * @param array $consumer_data State carried across the promotion-verification
	 *                             diversion, keyed by consumer. Empty on the submit path.
	 */
	public function persist( $rsvp, $rsvp_data, $consumer_data = [] ) {

		// Resolved up front so the catch below can name the RSVP whose answers were
		// lost even if the throw happened earlier.
		$context_id = 0;

		try {
			$this->maybe_restore_carried_answers( $consumer_data );

			$context_id = (int) $rsvp->post_id;
			$event_id   = isset( $rsvp_data['event_id'] ) ? (int) $rsvp_data['event_id'] : 0;
			$renderer   = $event_id > 0 ? Renderer::for_event( $event_id ) : null;

			if ( $renderer === null ) {
				return;
			}

			if ( $renderer->mode() === 'after' ) {
				$this->reconcile_after( $rsvp, (array) $rsvp_data, $renderer );

				return;
			}

			$this->reconcile( $rsvp, $rsvp_data, $renderer );
		} catch ( Throwable $e ) {

			// The request is deliberately not failed: the RSVP is already saved. But a
			// throwable here loses every answer for it, so it goes to the same log the
			// per-row failures use rather than vanishing.
			WriteFailureLog::record(
				[
					'context'    => self::CONTEXT,
					'context_id' => $context_id,
				],
				new WP_Error(
					'registration_form_rsvp_persist_exception',
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Adopt answers carried across the promotion-verification diversion.
	 *
	 * A no-op when validate() ran in this request; that pass wins, since it reflects
	 * the current schema. Only a request that never validated adopts the carried set.
	 *
	 * The carried answers are not re-validated: ResponseGate already accepted them
	 * against the schema at submit time, the stash is server-side and token-keyed, and
	 * the RSVP exists by now, so a rejection could only discard the visitor's answers.
	 * A form edited mid-flight therefore lands answers against a newer schema, the same
	 * staleness the confirm path accepts for the rest of the stashed submission.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $consumer_data State carried across the diversion, keyed by consumer.
	 */
	private function maybe_restore_carried_answers( $consumer_data ) {

		// validate() always assigns a non-empty gate result, so an empty one means it
		// never ran in this request.
		if ( ! empty( $this->gate_result ) ) {
			return;
		}

		if ( ! is_array( $consumer_data ) || ! isset( $consumer_data[ self::CONTEXT ]['answers'] ) ) {
			return;
		}

		$answers = $consumer_data[ self::CONTEXT ]['answers'];

		if ( ! is_array( $answers ) || empty( $answers ) ) {
			return;
		}

		$this->gate_result = [
			'valid'   => true,
			'errors'  => [],
			'answers' => $answers,
		];
	}

	/**
	 * Existing answer-row ids for this RSVP, keyed by attendee key.
	 *
	 * Rows with a NULL attendee_key are skipped: neither the update pass nor the
	 * removal pass can speak for them.
	 *
	 * @since 3.13.0
	 *
	 * @param int $context_id The RSVP post id.
	 *
	 * @return array<string,int> Row id keyed by attendee key.
	 */
	private function existing_row_ids_by_key( $context_id ) {

		$existing = [];

		foreach ( ResponseRepository::get_for_rsvp( $context_id ) as $row ) {
			if ( $row['attendee_key'] !== null ) {
				$existing[ $row['attendee_key'] ] = $row['id'];
			}
		}

		return $existing;
	}

	/**
	 * Reconcile stored answer rows against a submission.
	 *
	 * Not Going deletes every row for the RSVP, so a Going/Not Going/Going flip loses
	 * the earlier answers rather than leaving rows on a not-going RSVP. Otherwise each
	 * answered key is updated or inserted, and an existing row is deleted only when
	 * nobody in this submission carries its key: absent answers are not absent people.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed    $rsvp      The saved RSVP.
	 * @param array    $rsvp_data The data it was saved from.
	 * @param Renderer $renderer  The renderer for this event. Unused; taken only to
	 *                            mirror reconcile_after()'s contract, so a future
	 *                            caller cannot skip persist()'s mode gate by accident.
	 */
	private function reconcile( $rsvp, $rsvp_data, Renderer $renderer ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $renderer documents the caller's already-run gate; see the docblock.

		$event_id   = isset( $rsvp_data['event_id'] ) ? (int) $rsvp_data['event_id'] : 0;
		$context_id = (int) $rsvp->post_id;
		$existing   = $this->existing_row_ids_by_key( $context_id );

		// Not Going posts no attendees and collects nothing; any rows left from
		// an earlier Going submission belong to no one now.
		if ( empty( $rsvp_data['going'] ) ) {
			ResponseRepository::delete( array_values( $existing ) );

			return;
		}

		$attendee_ids = $this->resolve_attendee_ids( $rsvp, $rsvp_data );

		foreach ( $this->validated_answers() as $key => $fields ) {

			$key = (string) $key;

			if ( isset( $existing[ $key ] ) ) {

				// The attendee id is re-resolved on every update: RSVP renumbers row
				// ids on each render, so this key may now belong to a different guest,
				// and carrying the stored id forward pointed at a deleted record.
				$updated = ResponseRepository::update_answers(
					$existing[ $key ],
					(array) $fields,
					$attendee_ids[ $key ] ?? null
				);

				// Nothing here can retry, but a dropped answer must not be silent. A
				// vanished row (0 affected) is not recorded: it was deleted under us,
				// so there is no respondent left to follow up with.
				if ( is_wp_error( $updated ) ) {
					WriteFailureLog::record(
						[
							'context'      => self::CONTEXT,
							'context_id'   => $context_id,
							'attendee_key' => $key,
						],
						$updated
					);
				}

				unset( $existing[ $key ] );

				continue;
			}

			// Through ResponsePersister, not ResponseRepository::insert(), so a failed
			// write reaches WriteFailureLog and the admin notice.
			ResponsePersister::persist(
				[
					[
						'event_id'     => $event_id,
						'context'      => self::CONTEXT,
						'context_id'   => $context_id,
						'attendee_key' => $key,
						'attendee_id'  => $attendee_ids[ $key ] ?? null,
						'answers'      => (array) $fields,
						'status'       => 'complete',
					],
				]
			);
		}

		// A row whose key nobody submitted belongs to an attendee who left. A submitted
		// key carrying no answers does not: narrowing `collect` to main_attendee empties
		// every guest key while the guests are still there.
		ResponseRepository::delete( array_values( array_diff_key( $existing, $attendee_ids ) ) );

		// Asks maybe_confirm() for the confirmation after the add-on's reload. Set on
		// the going path only: Not Going returned above, having collected nothing.
		CompletionFlash::set( $event_id );
	}

	/**
	 * Store or reconcile after-checkout rows for a saved RSVP.
	 *
	 * Unlike TicketingCheckout's one-shot after branch, this fires on both create and
	 * update, so an update is reconciled against $existing via
	 * respondents_needing_rows(): an attendee that already has a row is left alone, a
	 * newly-applicable one gets a pending row, and an unmatchable row is deleted
	 * unless it already carries a real answer.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed    $rsvp      The saved RSVP.
	 * @param array    $rsvp_data The data it was saved from.
	 * @param Renderer $renderer  The renderer for this event, already resolved by persist().
	 */
	private function reconcile_after( $rsvp, array $rsvp_data, Renderer $renderer ) {

		$event_id   = (int) $rsvp_data['event_id'];
		$context_id = (int) $rsvp->post_id;
		$existing   = ResponseRepository::get_for_rsvp( $context_id );

		// Not Going collects nothing; rows left from an earlier Going belong to nobody.
		if ( empty( $rsvp_data['going'] ) ) {
			ResponseRepository::delete( wp_list_pluck( $existing, 'id' ) );

			return;
		}

		$attendee_ids = $this->applicable_attendee_ids( $renderer, $this->resolve_attendee_ids( $rsvp, $rsvp_data ) );
		$result       = PendingRowReconciler::respondents_needing_rows( $existing, $attendee_ids );

		$this->record_malformed_token( $context_id, $existing );

		if ( $result['respondents'] !== [] ) {
			PendingRows::mint(
				$event_id,
				self::CONTEXT,
				$context_id,
				$result['respondents'],
				PendingRowReconciler::context_token( $existing )
			);
		}

		ResponseRepository::delete( $result['stale_ids'] );
	}

	/**
	 * Record a stored token that no render seam will ever print.
	 *
	 * Both after-checkout seams refuse a token failing PendingRows::is_valid_token()
	 * rather than print one SubmitEndpoint would reject. The refusal is permanent:
	 * context_token() only reuses a valid token, and re-minting would rotate a token a
	 * respondent may already hold in an open tab. Without this record the suppression
	 * is invisible everywhere. Once per save, and never the token value itself.
	 *
	 * Lives here rather than in context_token() because that helper is static and
	 * WordPress-free, and WriteFailureLog::record() would fatal in its unit test.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $context_id The RSVP post id.
	 * @param array $existing   The context's stored rows.
	 */
	private function record_malformed_token( $context_id, array $existing ) {

		foreach ( $existing as $row ) {
			$token = $row['token'] ?? null;

			// An empty/NULL token is the before-checkout shape, not a defect.
			if ( $token === null || $token === '' || PendingRows::is_valid_token( $token ) ) {
				continue;
			}

			WriteFailureLog::record(
				[
					'context'      => self::CONTEXT,
					'context_id'   => $context_id,
					'attendee_key' => $row['attendee_key'] ?? '',
				],
				new WP_Error(
					'registration_malformed_row_token',
					'A stored registration row carries a malformed token, so no after-checkout seam will print the form for this RSVP and nothing will repair it.'
				)
			);

			// One entry per save: the rows share a context token, so a second row would
			// only restate the same fault and crowd the log's fixed capacity.
			return;
		}
	}

	/**
	 * Narrow resolved attendee ids down to the keys the schema's applicability
	 * predicate accepts.
	 *
	 * Renderer::applicable_attendees() collapses the respondent list to just `main`
	 * unless the schema collects per attendee. Without it, after-checkout RSVP would
	 * mint a pending row per guest and ask questions the organiser never configured.
	 * RSVP has no ticket types, so targeting is not applied.
	 *
	 * @since 3.13.0
	 *
	 * @param Renderer $renderer     The renderer for this event.
	 * @param array    $attendee_ids Attendee id keyed by attendee key.
	 *
	 * @return array<string,int|null> The subset of $attendee_ids whose key is applicable.
	 */
	private function applicable_attendee_ids( Renderer $renderer, array $attendee_ids ) {

		$entries = [];

		foreach ( array_keys( $attendee_ids ) as $key ) {
			$entries[] = [
				'key'         => (string) $key,
				'ticket_type' => 0,
			];
		}

		$applicable_keys = wp_list_pluck( $renderer->applicable_attendees( $entries, false ), 'key' );

		return array_intersect_key( $attendee_ids, array_flip( $applicable_keys ) );
	}

	/**
	 * Resolve the real attendee id behind every key this RSVP could carry.
	 *
	 * 'main' pairs with the RSVP's own main attendee. Each 'a{n}' pairs with the
	 * submitted row whose row_id is n, resolved to a real attendee record by email
	 * rather than by position, which would bind an answer to the wrong person once a
	 * middle row is removed. An unmatched key is absent from the map, so the caller
	 * stores NULL rather than guessing.
	 *
	 * row_id is read from $rsvp_data, not from $rsvp->get_additional_attendees():
	 * `wp_sc_rsvp_attendees` has no row_id column, and on the guest-removal path
	 * Rsvp::create() replaces the submitted list with a DB-rehydrated one before this
	 * action fires, which would resolve every 'a{n}' to null. Email is a real column,
	 * so it survives that rehydrate.
	 *
	 * Email uniqueness is sc-rsvp's invariant, not this class's, and out-of-band data
	 * (the seeder, legacy rows) can violate it, so
	 * additional_attendee_ids_by_email() poisons a duplicated email and both keys
	 * degrade to NULL rather than binding to the wrong record.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $rsvp      The saved RSVP.
	 * @param array $rsvp_data The data it was saved from.
	 *
	 * @return array Attendee id keyed by attendee key.
	 */
	private function resolve_attendee_ids( $rsvp, array $rsvp_data ) {

		$main_id = ! empty( $rsvp->main_attendee ) ? (int) $rsvp->main_attendee->id : 0;

		$ids = [
			AnswerRequest::MAIN_KEY => $main_id > 0 ? $main_id : null,
		];

		$id_by_email = $this->additional_attendee_ids_by_email( $rsvp );

		foreach ( $this->submitted_additional_attendees( $rsvp_data ) as $entry ) {

			$row_id = $this->submitted_row_id( $entry );
			$email  = $this->submitted_email( $entry );

			// empty(), not isset(): a poisoned entry is stored as 0 (see
			// additional_attendee_ids_by_email()) and is just as unresolvable.
			if ( $row_id === '' || $email === '' || empty( $id_by_email[ $email ] ) ) {
				continue;
			}

			$key = 'a' . $row_id;

			// The same shape guard attendees_from_post() applies: KEY_PATTERN bounds the
			// digit count ctype_digit does not, and the `a0` check excludes the add-on's
			// hidden template row, which KEY_PATTERN would accept.
			if ( ! preg_match( AnswerRequest::KEY_PATTERN, $key ) || $key === 'a0' ) {
				continue;
			}

			$ids[ $key ] = $id_by_email[ $email ];
		}

		return $ids;
	}

	/**
	 * Real attendee id keyed by lowercased email, for every additional
	 * attendee the RSVP currently carries.
	 *
	 * Email uniqueness is sc-rsvp's invariant, not this class's, and this also runs
	 * against data its duplicate check never saw (the seeder, legacy rows). One email
	 * paired with two different attendee ids is poisoned to 0, which no real attendee
	 * id can be, so the caller's `empty()` guard treats it as a missing key. The same
	 * id seen twice for one email is not a collision.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $rsvp The saved RSVP.
	 *
	 * @return array<string,int>
	 */
	private function additional_attendee_ids_by_email( $rsvp ) {

		$id_by_email = [];

		foreach ( $rsvp->get_additional_attendees() as $attendee ) {

			$email = ! empty( $attendee->email ) ? strtolower( (string) $attendee->email ) : '';
			$id    = ! empty( $attendee->id ) ? (int) $attendee->id : 0;

			if ( $email === '' || $id <= 0 ) {
				continue;
			}

			if ( isset( $id_by_email[ $email ] ) && $id_by_email[ $email ] !== $id ) {
				$id_by_email[ $email ] = 0;

				continue;
			}

			$id_by_email[ $email ] = $id;
		}

		return $id_by_email;
	}

	/**
	 * The submission's additional-attendee entries, or an empty array.
	 *
	 * @since 3.13.0
	 *
	 * @param array $rsvp_data The data the RSVP was saved from.
	 *
	 * @return array
	 */
	private function submitted_additional_attendees( array $rsvp_data ) {

		return isset( $rsvp_data['additional_attendees'] ) && is_array( $rsvp_data['additional_attendees'] )
			? $rsvp_data['additional_attendees']
			: [];
	}

	/**
	 * A submitted additional-attendee entry's row_id, or '' when absent or
	 * not a non-negative integer as submitted.
	 *
	 * @since 3.13.0
	 *
	 * @param object|array $entry One entry from $rsvp_data['additional_attendees'].
	 *
	 * @return string
	 */
	private function submitted_row_id( $entry ) {

		$entry           = (array) $entry;
		$additional_data = isset( $entry['additional_data'] ) ? (array) $entry['additional_data'] : [];
		$row_id          = isset( $additional_data['row_id'] ) ? (string) $additional_data['row_id'] : '';

		return $row_id !== '' && ctype_digit( $row_id ) ? $row_id : '';
	}

	/**
	 * A submitted additional-attendee entry's lowercased email, or ''.
	 *
	 * @since 3.13.0
	 *
	 * @param object|array $entry One entry from $rsvp_data['additional_attendees'].
	 *
	 * @return string
	 */
	private function submitted_email( $entry ) {

		$entry = (array) $entry;

		return ! empty( $entry['email'] ) ? strtolower( (string) $entry['email'] ) : '';
	}
}
