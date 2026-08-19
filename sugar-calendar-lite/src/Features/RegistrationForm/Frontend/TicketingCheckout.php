<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\AddOn\Ticketing\Gateways\Checkout;
use Sugar_Calendar\Event;
use Sugar_Calendar\Features\RegistrationForm\WriteFailureLog;
use Throwable;
use WP_Error;

/**
 * Wires the registration step into the ticketing checkout.
 *
 * Holds every piece of per-request state the flow needs, since the checkout spreads
 * the work across render, two validation passes (before and after the charge),
 * attendee resolution, and persistence once the order exists.
 *
 * @since 3.13.0
 */
class TicketingCheckout {

	/**
	 * Response context stored on every row this class writes.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const CONTEXT = 'order';

	/**
	 * Transient prefix for the marker recording a passed pre-charge validation.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const PRECHARGE_MARKER_PREFIX = 'sc_regform_precharge_';

	/**
	 * The hidden field carrying this checkout attempt's id.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ATTEMPT_FIELD = 'sc_regform_attempt';

	/**
	 * How long a pre-charge marker stays valid, in seconds.
	 *
	 * Covers the gap between the admin-ajax validation pass and the form POST, i.e.
	 * the time the buyer spends entering card details, without letting a stale marker
	 * license a later unrelated submission.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	const PRECHARGE_MARKER_TTL = 900;

	/**
	 * Whether this request may let an invalid submission through, or null before
	 * may_fail_open() has resolved it.
	 *
	 * @since 3.13.0
	 *
	 * @var bool|null
	 */
	private $fail_open_allowed = null;

	/**
	 * Renderer for the current request, resolved once.
	 *
	 * `false` means "not resolved yet"; `null` means "resolved to no form".
	 *
	 * @since 3.13.0
	 *
	 * @var Renderer|null|false
	 */
	private $request_renderer = false;

	/**
	 * The gate result for this request, or null before validation ran.
	 *
	 * @since 3.13.0
	 *
	 * @var array|null
	 */
	private $gate_result = null;

	/**
	 * Posted attendee keys in POST order, indexed by ordinal.
	 *
	 * One entry per posted row, so the ordinal stays aligned with the host's own
	 * iteration; a row whose key cannot be an attendee key holds null.
	 *
	 * @since 3.13.0
	 *
	 * @var array<int,string|null>|null
	 */
	private $attendee_keys = null;

	/**
	 * Ordinal of the attendee currently being resolved.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	private $attendee_ordinal = -1;

	/**
	 * Resolved attendee ids, keyed by attendee key.
	 *
	 * @since 3.13.0
	 *
	 * @var int[]
	 */
	private $attendee_ids = [];

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_ticketing_checkout_main_bottom', [ $this, 'render_step' ] );
		add_action( 'sc_et_modal_form_bottom', [ $this, 'render_footer_back' ] );
		add_action( 'sc_et_checkout_validate_data', [ $this, 'validate' ] );
		add_filter( 'sc_et_checkout_ajax_validation_response', [ $this, 'attach_errors' ], 10, 2 );
		add_filter( 'sugar_calendar_add_on_ticketing_gateways_checkout_attendee_before_create', [ $this, 'note_attendee_position' ] );
		add_filter( 'sugar_calendar_add_on_ticketing_gateways_checkout_attendee_after_create', [ $this, 'note_attendee_id' ], 10, 2 );
		// Priority 5, ahead of the ticketing add-on's own send_order_receipt_email()
		// at 10: that email carries the resume link, which cannot be built before the
		// pending rows this callback mints exist. Both were at 10, where insertion
		// order decided it — see OrderEmailResumeLink.
		add_action( 'sc_et_checkout_pre_redirect', [ $this, 'persist' ], 5, 2 );
	}

	/**
	 * Render the registration step inside the checkout modal.
	 *
	 * @since 3.13.0
	 *
	 * @param Event $event The event being checked out.
	 */
	public function render_step( $event ) {

		$event_id = is_object( $event ) && ! empty( $event->id ) ? (int) $event->id : 0;

		$renderer = Renderer::for_event( $event_id );

		// After-checkout collection happens on the receipt page, not here.
		if ( $renderer === null || $renderer->mode() !== 'before' ) {
			return;
		}

		$renderer->enqueue();

		echo $renderer->render_step( self::CONTEXT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped per field in Renderer.

		$this->render_attempt_field();
	}

	/**
	 * The hidden field that identifies one checkout attempt across its two requests.
	 *
	 * Used by precharge_marker_key() to scope the fail-open evidence. It rides the modal's
	 * own form, so both requests carry it. A CSPRNG failure prints nothing, which
	 * resolves to "no evidence" and so to enforcement, which halts before the gateway.
	 *
	 * @since 3.13.0
	 */
	private function render_attempt_field() {

		try {
			$attempt = bin2hex( random_bytes( 16 ) );
		} catch ( Throwable $e ) {
			return;
		}

		printf(
			'<input type="hidden" name="%1$s" value="%2$s" />',
			esc_attr( self::ATTEMPT_FIELD ),
			esc_attr( $attempt )
		);
	}

	/**
	 * Render the step's Back control inside the modal's own footer.
	 *
	 * Runs the same gate as render_step() rather than assuming that one passed: this is
	 * a separate seam, and a Back button on a modal with no step goes nowhere.
	 *
	 * @since 3.13.0
	 *
	 * @param Event $event The event being checked out.
	 */
	public function render_footer_back( $event ) {

		$event_id = is_object( $event ) && ! empty( $event->id ) ? (int) $event->id : 0;

		$renderer = Renderer::for_event( $event_id );

		if ( $renderer === null || $renderer->mode() !== 'before' ) {
			return;
		}

		echo $renderer->render_footer_back(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer.
	}

	/**
	 * The renderer for the event being checked out in this request.
	 *
	 * Resolved from the posted event id, memoised because the checkout validates
	 * twice per purchase and persistence reads it again.
	 *
	 * @since 3.13.0
	 *
	 * @return Renderer|null
	 */
	public function renderer_for_request() {

		if ( $this->request_renderer !== false ) {
			return $this->request_renderer;
		}

		// Nonce verification is the host's: both Checkout::process_form() and
		// process_ajax_validation() verify sc_et_nonce before validate() runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$event_id = isset( $_POST['sc_et_event_id'] ) ? absint( $_POST['sc_et_event_id'] ) : 0;

		$this->request_renderer = Renderer::for_event( $event_id );

		return $this->request_renderer;
	}

	/**
	 * Validate the posted registration answers.
	 *
	 * Runs on both checkout validation passes, with the charge in between. The admin-ajax
	 * pass enforces: an invalid form adds an error and no gateway call is made. The
	 * native form POST lets an invalid submission through, but only when a pre-charge
	 * pass is on record, since process_form() answers a failure by redirecting before
	 * complete() runs and would leave a charged buyer with no order or tickets.
	 *
	 * Which pass this is cannot be read off the request: wp_doing_ajax() says how
	 * $_POST was built, not whether money moved, and process_form() is reachable
	 * directly with a nonce lifted from the public modal. So the request must present
	 * evidence that the pre-charge gate passed (may_fail_open()); with none we enforce,
	 * which halts before send_to_gateway() so nothing is charged.
	 *
	 * @since 3.13.0
	 *
	 * @param Checkout $checkout Checkout object.
	 */
	public function validate( $checkout ) {

		$renderer = $this->renderer_for_request();

		if ( $renderer === null || $renderer->mode() !== 'before' ) {
			return;
		}

		// Only describes how this request built $_POST: process_ajax_validation()
		// already rebuilt it with parse_str( wp_unslash( … ) ), while the form POST is
		// still slashed. It is not a proxy for which side of the charge we are on.
		$ajax_pass = wp_doing_ajax();
		$slashed   = ! $ajax_pass;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The host verifies sc_et_nonce before validate() runs.
		$post = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$answers    = AnswerRequest::from_post( $post, $slashed );
		$attendees  = AnswerRequest::attendees_from_post( $post, $slashed );
		$applicable = $renderer->applicable_attendees( $attendees );

		$this->gate_result = ResponseGate::validate( $renderer->schema(), $answers, $applicable );

		if ( AnswerRequest::leaf_count_mismatch( $post, $answers ) ) {
			$this->report_truncation( $checkout, $this->may_fail_open( $ajax_pass ) );

			return;
		}

		if ( $this->gate_result['valid'] ) {

			// Record the evidence the form POST looks for. Only a pass counts: a failed
			// pre-charge pass never reached the gateway, so it licenses nothing.
			if ( $ajax_pass ) {
				$this->record_precharge_pass();
			}

			return;
		}

		if ( ! $this->may_fail_open( $ajax_pass ) ) {
			$checkout->add_error( ResponseGate::ERROR_ID, ResponseGate::error_message(), ResponseGate::SELECTOR );

			return;
		}

		// Reaching here means the two passes disagreed (AnswerRequest's slash
		// normalisation should make that impossible) and the buyer is already charged.
		// Their answers are stored as sanitized; blocking a completed purchase is worse.
	}

	/**
	 * Whether an invalid submission may be let through instead of blocked.
	 *
	 * True only for a non-admin-ajax request presenting a consumed pre-charge marker,
	 * i.e. a genuine continuation of a checkout that already passed and charged.
	 * Memoized because the marker is single-use, so a second caller would consume the
	 * evidence twice.
	 *
	 * @since 3.13.0
	 *
	 * @param bool $ajax_pass Whether this request is the admin-ajax pass.
	 *
	 * @return bool
	 */
	private function may_fail_open( $ajax_pass ) {

		if ( $ajax_pass ) {
			return false;
		}

		if ( $this->fail_open_allowed === null ) {
			$this->fail_open_allowed = $this->consume_precharge_pass();
		}

		return $this->fail_open_allowed;
	}

	/**
	 * Record that the pre-charge gate ran and passed for this attempt.
	 *
	 * @since 3.13.0
	 */
	private function record_precharge_pass() {

		$key = $this->precharge_marker_key();

		if ( $key === '' ) {
			return;
		}

		set_transient( $key, 1, self::PRECHARGE_MARKER_TTL );
	}

	/**
	 * Read and destroy this attempt's pre-charge marker.
	 *
	 * Single-use: one pre-charge pass licenses exactly one lenient form POST, so
	 * a marker cannot be re-spent across repeated submissions.
	 *
	 * @since 3.13.0
	 *
	 * @return bool Whether a marker was present.
	 */
	private function consume_precharge_pass() {

		$key = $this->precharge_marker_key();

		if ( $key === '' ) {
			return false;
		}

		if ( ! get_transient( $key ) ) {
			return false;
		}

		delete_transient( $key );

		return true;
	}

	/**
	 * Transient key identifying one checkout attempt across its two requests.
	 *
	 * Scoped to (event, attempt id), the attempt id being the random per-render value
	 * render_attempt_field() prints into the modal, identical in both requests.
	 *
	 * It must not be scoped to sc_et_nonce: wp_create_nonce() is deterministic per
	 * action/user/session/tick, so logged-out buyers all share one key, letting buyer B
	 * consume buyer A's marker (blocking A after the charge) and letting one attacker's
	 * pass license a stripped POST from anyone else buying that event.
	 *
	 * Returns '' when either value is missing or malformed, which resolves to
	 * enforcement, and that halts before send_to_gateway(). The field rides the same
	 * form as the sc_et_nonce process_form() requires, so a request that reached the
	 * gateway carried it.
	 *
	 * Residual, accepted: the evidence is client-carried, so a buyer can run their own
	 * pass and then strip their own answers. Binding the marker to the answered key set
	 * would close that but would block a charged buyer whose POST was truncated by
	 * max_input_vars, which matters more.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	private function precharge_marker_key() {

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The host verifies sc_et_nonce before validate() runs; the value is shape-checked below and only hashed into a transient key.
		$attempt  = isset( $_POST[ self::ATTEMPT_FIELD ] ) ? (string) wp_unslash( $_POST[ self::ATTEMPT_FIELD ] ) : '';
		$event_id = isset( $_POST['sc_et_event_id'] ) ? absint( $_POST['sc_et_event_id'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Same 32-hex shape as a response token: the value is attacker-supplied and an
		// unbounded one would be hashed into a key.
		if ( ! PendingRows::is_valid_token( $attempt ) || $event_id <= 0 ) {
			return '';
		}

		return self::PRECHARGE_MARKER_PREFIX . md5( $event_id . '|' . $attempt );
	}

	/**
	 * Attach the structured error map to the AJAX failure payload.
	 *
	 * @since 3.13.0
	 *
	 * @param array    $response The wp_send_json_error payload.
	 * @param Checkout $checkout Checkout object.
	 *
	 * @return array
	 */
	public function attach_errors( $response, $checkout ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $checkout is part of the sc_et_checkout_ajax_validation_response filter signature.

		if ( empty( $this->gate_result['errors'] ) ) {
			return $response;
		}

		// Codes, not sentences: the controller maps them to localized copy and paints
		// them with textContent, so no label or answer travels in the payload.
		$response['sc_registration_errors'] = $this->gate_result['errors'];

		return $response;
	}

	/**
	 * The sanitized answers the gate accepted, keyed by attendee key.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	public function validated_answers() {

		return isset( $this->gate_result['answers'] ) ? (array) $this->gate_result['answers'] : [];
	}

	/**
	 * Report a submission that arrived truncated.
	 *
	 * Blocked on the same terms as invalid answers: only let through when the buyer has
	 * demonstrably already been charged.
	 *
	 * @since 3.13.0
	 *
	 * @param Checkout $checkout      Checkout object.
	 * @param bool     $may_fail_open Whether this request may pass despite the truncation.
	 */
	private function report_truncation( $checkout, $may_fail_open ) {

		if ( ! $may_fail_open ) {
			$checkout->add_error( ResponseGate::TRUNCATED_ERROR_ID, ResponseGate::truncation_message(), ResponseGate::SELECTOR );
		}
	}

	/**
	 * Track which posted attendee is being resolved.
	 *
	 * The host's prepare_attendees() iterates the posted attendees in order but discards
	 * their keys, and sc_et_checkout_complete's payload cannot recover them (it
	 * re-queries in DB order, deduplicated, without anonymous attendees). This filter
	 * fires once per posted attendee, so counting it gives the position within
	 * $_POST['attendees']. Pass-through: the attendee is never modified.
	 *
	 * @since 3.13.0
	 *
	 * @param object $attendee Attendee object built from the POST row.
	 *
	 * @return object
	 */
	public function note_attendee_position( $attendee ) {

		if ( $this->attendee_keys === null ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The host verifies sc_et_nonce before complete() runs; only the numeric keys are read below, never the values.
			$posted              = isset( $_POST['attendees'] ) && is_array( $_POST['attendees'] ) ? wp_unslash( $_POST['attendees'] ) : [];
			$this->attendee_keys = [];

			// Every posted row gets an entry: the host fires the create filters once per
			// row, so skipping one would shift every later ordinal and bind answers to
			// someone else's attendee. A non-numeric key can never be an attendee key,
			// so it is recorded as null and binds nothing.
			foreach ( array_keys( $posted ) as $key ) {
				$this->attendee_keys[] = is_numeric( $key ) ? 'a' . (int) $key : null;
			}
		}

		++$this->attendee_ordinal;

		return $attendee;
	}

	/**
	 * Bind the resolved attendee id to the key of the attendee being resolved.
	 *
	 * Fires only when an attendee record was created or found, so an ordinal with no
	 * call here is an anonymous attendee and keeps a NULL attendee id. Pass-through:
	 * the attendee object is never modified.
	 *
	 * @since 3.13.0
	 *
	 * @param object $attendee_object The resolved attendee record.
	 * @param object $attendee        The attendee object built from the POST row.
	 *
	 * @return object
	 */
	public function note_attendee_id( $attendee_object, $attendee ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $attendee is part of the sugar_calendar_add_on_ticketing_gateways_checkout_attendee_after_create filter signature.

		$key = $this->attendee_keys[ $this->attendee_ordinal ] ?? null;

		if ( $key !== null && ! empty( $attendee_object->id ) ) {
			$this->attendee_ids[ $key ] = (int) $attendee_object->id;
		}

		return $attendee_object;
	}

	/**
	 * Store the accepted answers against the completed order.
	 *
	 * Runs on sc_et_checkout_pre_redirect, the first point where the order id exists and
	 * attendee resolution has finished. Not sc_et_checkout_complete, whose payload
	 * cannot express the POST-key to attendee-id mapping (see note_attendee_position()).
	 *
	 * @since 3.13.0
	 *
	 * @param int   $order_id   The order id.
	 * @param array $order_data The order data.
	 */
	public function persist( $order_id, $order_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $order_data is part of the sc_et_checkout_pre_redirect action signature.

		$order_id = (int) $order_id;
		$renderer = $this->renderer_for_request();

		if ( $order_id <= 0 || $renderer === null ) {
			return;
		}

		if ( $renderer->mode() === 'after' ) {
			$this->mint_pending_rows( $order_id, $renderer );

			return;
		}

		$answers = $this->validated_answers();

		if ( $answers === [] ) {
			return;
		}

		$rows = [];

		foreach ( $answers as $key => $fields ) {
			$rows[] = [
				'event_id'     => $renderer->event_id(),
				'context'      => self::CONTEXT,
				'context_id'   => $order_id,
				'attendee_key' => (string) $key,
				'attendee_id'  => $this->attendee_ids[ $key ] ?? null,
				'answers'      => (array) $fields,
				'status'       => 'complete',
			];
		}

		ResponsePersister::persist( $rows );

		// The redirect to the receipt is the last thing this request does, so this is
		// the only chance to ask that page for the confirmation. See CompletionFlash.
		CompletionFlash::set( $renderer->event_id() );
	}

	/**
	 * Mint the pending rows for an after-checkout form.
	 *
	 * Runs from the same instance as the before-checkout persist because the
	 * attendee_key to attendee_id mapping is private state here, populated by two
	 * filters that increment an ordinal per call. A separate listener would need its own
	 * copy of those filters, and two ordinals over one loop bind the wrong attendee.
	 *
	 * Ticket-type targeting is applied here, once: the rows that exist are the
	 * applicable respondents, and they carry no ticket_type for the endpoint to re-read.
	 *
	 * @since 3.13.0
	 *
	 * @param int      $order_id The order id.
	 * @param Renderer $renderer The renderer for this request.
	 */
	private function mint_pending_rows( $order_id, $renderer ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The host verifies sc_et_nonce before complete() runs; $_POST is slashed here (process_form), which AnswerRequest normalises.
		$applicable = $renderer->applicable_attendees( AnswerRequest::attendees_from_post( $_POST, true ) );

		$respondents = [];

		foreach ( $applicable as $attendee ) {
			$key = (string) $attendee['key'];

			$respondents[] = [
				'attendee_key' => $key,
				'attendee_id'  => $this->attendee_ids[ $key ] ?? null,
			];
		}

		$token = PendingRows::mint( $renderer->event_id(), self::CONTEXT, $order_id, $respondents );

		// This request is the buyer's own browser with the headers still open, so it
		// is the only chance to prove to the receipt page that this browser is the
		// one that checked out. See CheckoutSession.
		if ( $token !== '' ) {
			CheckoutSession::remember( $order_id, $token );
		}

		// An empty token with respondents to mint means nothing was written, and neither
		// cause reaches WriteFailureLog on its own. Nothing re-runs this hook either, so
		// the buyer keeps their receipt but is never asked the organiser's questions.
		if ( $respondents !== [] && $token === '' ) {
			WriteFailureLog::record(
				[
					'context'    => self::CONTEXT,
					'context_id' => $order_id,
				],
				new WP_Error(
					'registration_pending_rows_not_minted',
					'No after-checkout registration rows could be created for this order, so its attendees will never be asked.'
				)
			);
		}
	}
}
