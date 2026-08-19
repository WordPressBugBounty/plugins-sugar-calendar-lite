<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Event;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;
use Sugar_Calendar_Rsvp\EventRsvp;
use Sugar_Calendar_Rsvp\Helper;

/**
 * Renders the after-checkout registration form on the public event page.
 *
 * The RSVP counterpart to TicketingReceipt: RSVP has no receipt page, so the
 * form prints inline via sc-rsvp's after-RSVP-box seam and after.js detaches
 * it into a modal. The row lookup is scoped to the RSVP post id only, and the
 * event cross-check is load-bearing, since the add-on's own visitor
 * resolution carries no event check of its own. The gate runs once on
 * template_redirect (see maybe_disable_caching()); sc-rsvp classes are
 * reached behind class_exists() since the add-on ships independently.
 *
 * @since 3.13.0
 */
class RsvpAfterCheckout {

	/**
	 * This request's memoized decision, or null when nothing will be printed.
	 *
	 * @since 3.13.0
	 *
	 * @var array|null
	 */
	private $decision = null;

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		// Priority 0, registered by both after-checkout hosts that can print on an
		// event page, so the one-root claim starts each request cleared; the
		// duplicate registration collapses into one since WordPress keys a static
		// callable by its own identity.
		add_action( 'template_redirect', [ AfterCheckoutAssets::class, 'reset_root_claim' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_disable_caching' ] );
		add_action( 'sugar_calendar_rsvp_frontend_after_rsvp_box', [ $this, 'render' ], 10, 3 );

		// 2 args, not the default 1: without $event the mode can never resolve and
		// the card never prints.
		add_action( 'sugar_calendar_rsvp_frontend_modal_response_main_bottom', [ $this, 'render_loading_card' ], 10, 2 );
	}

	/**
	 * Run the gate while the headers are still open, and mark the page uncacheable.
	 *
	 * This host prints a write credential inline on an ordinary, cache-friendly
	 * singular event URL, and full-page caches don't vary on the RSVP
	 * recognition cookie, so without these headers one visitor's token could be
	 * cached and served to everyone. The render seam fires too late in the
	 * response for nocache_headers() to take effect, hence: gate here, memoize,
	 * print there.
	 *
	 * @since 3.13.0
	 */
	public function maybe_disable_caching() {

		$event = $this->event_from_request();

		$this->decision = $this->decide( $event, $this->visitor_rsvp_for( $event ) );

		if ( $this->decision === null ) {
			return;
		}

		nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {

			/**
			 * The de-facto opt-out every buffering full-page cache honours.
			 *
			 * @since 3.13.0
			 */
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/**
	 * Whether this request will print a token.
	 *
	 * The memoized decision, public so tests can assert on the decision itself
	 * rather than on the constant the same line defines.
	 *
	 * @internal Test-only. Public only so the test suite can assert on the
	 *           decision directly; not part of this class's production API.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	public function will_print_token() {

		return $this->decision !== null;
	}

	/**
	 * Print the form root and the context's token.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $event_rsvp   The event's RSVP settings. Unused — applicability is a
	 *                            property of the registration schema, not of the RSVP
	 *                            configuration.
	 * @param mixed $event        The event. Unused — the gate resolved and cross-checked
	 *                            the event on template_redirect.
	 * @param mixed $visitor_rsvp The visitor's RSVP, or false when unrecognised.
	 */
	public function render( $event_rsvp, $event, $visitor_rsvp ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $event_rsvp and $event are part of the sugar_calendar_rsvp_frontend_after_rsvp_box action signature.

		if ( $this->decision === null ) {
			return;
		}

		// The seam's own resolution is authoritative for who the visitor is: print
		// only when it agrees with the RSVP the gate ran against, since a
		// disagreement means printing either answer would be a guess.
		if ( ! is_object( $visitor_rsvp ) || (int) $visitor_rsvp->post_id !== $this->decision['context_id'] ) {
			return;
		}

		// One root per request. Claimed here rather than in decide(), since the
		// check above can still decline after the gate resolved.
		if ( ! AfterCheckoutAssets::claim_root() ) {
			return;
		}

		$renderer = $this->decision['renderer'];

		$renderer->enqueue();
		AfterCheckoutAssets::enqueue();

		$form = $renderer->render_static(
			RespondentRows::for_rsvp( $this->decision['rsvp'], $this->decision['rows'] ),
			// The event card only: an RSVP has no money to recap, so the design's
			// order card has no RSVP counterpart.
			AfterCheckoutSummary::for_event( $this->decision['event'] )
		);

		printf(
			'<div class="sc-regform-after" data-token="%1$s" data-rsvp-id="%2$d">%3$s</div>',
			esc_attr( $this->decision['token'] ),
			absint( $this->decision['context_id'] ),
			$form // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped per field and per label inside render_static().
		);
	}

	/**
	 * Print the hidden loading card inside the RSVP response modal, in `after`
	 * mode only.
	 *
	 * Printed hidden; single-event.js reveals it right before location.reload()
	 * so the reload's dead-button gap has a spinner over it instead (spec §7.3).
	 *
	 * @since 3.13.0
	 *
	 * @param mixed      $event_rsvp The event's RSVP settings. Unused — applicability
	 *                                is a property of the registration schema, not of
	 *                                the RSVP configuration.
	 * @param Event|null $event      The event object.
	 */
	public function render_loading_card( $event_rsvp, $event = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $event_rsvp is part of the sugar_calendar_rsvp_frontend_modal_response_main_bottom action signature.

		$event_id = is_object( $event ) && ! empty( $event->id ) ? (int) $event->id : 0;

		if ( $event_id === 0 ) {
			return;
		}

		$renderer = Renderer::for_event( $event_id );

		if ( $renderer === null || $renderer->mode() !== 'after' ) {
			return;
		}

		// Without this the card's only stylesheet rule (display: none) never
		// loads, so it prints visible before the visitor has submitted anything.
		// This seam runs on wp_footer:10, before styles print at :20, so
		// enqueueing here still makes it into the response.
		$renderer->enqueue();

		printf(
			'<div class="sc-regform-loading sc-regform-loading--hidden"><div class="sc-regform-loading__spinner" aria-hidden="true"></div><p class="sc-regform-loading__text">%s</p></div>',
			esc_html__( 'Processing your Registration…', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Run the gate, in the order spec §3 sets out.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $event        The event being rendered.
	 * @param mixed $visitor_rsvp The visitor's RSVP, or false when unrecognised.
	 *
	 * @return array|null The decision, or null when nothing should be printed.
	 */
	private function decide( $event, $visitor_rsvp ) {

		if ( ! is_object( $visitor_rsvp ) || ! is_object( $event ) || empty( $event->id ) ) {
			return null;
		}

		$event_id = (int) $event->id;

		if ( (int) $visitor_rsvp->event_id !== $event_id ) {
			return null;
		}

		// The gate is `going`, not "the rows were deleted": a failed Not-Going
		// delete can leave rows behind, since ResponsePersister::persist()
		// deliberately swallows storage failures.
		if ( empty( $visitor_rsvp->going ) ) {
			return null;
		}

		$renderer = $this->renderer_for_host( $event, $event_id );

		if ( $renderer === null ) {
			return null;
		}

		return $this->decision_for( $renderer, $visitor_rsvp, $event );
	}

	/**
	 * The host gate, then the renderer.
	 *
	 * One rule, applied at both after-checkout seams: print only while the
	 * host's state still invites an answer. See HostState.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $event    The event being rendered.
	 * @param int   $event_id The Sugar Calendar event id.
	 *
	 * @return Renderer|null The renderer, or null when nothing should be printed.
	 */
	private function renderer_for_host( $event, $event_id ) {

		$event_post_id = isset( $event->object_id ) ? (int) $event->object_id : 0;

		if ( ! HostState::event_permits_printing( $event_post_id ) ) {
			return null;
		}

		$renderer = Renderer::for_event( $event_id );

		if ( $renderer === null || $renderer->mode() !== 'after' ) {
			return null;
		}

		return $renderer;
	}

	/**
	 * The gate's last two steps: a non-complete row, and a token on it.
	 *
	 * @since 3.13.0
	 *
	 * @param Renderer $renderer     The resolved renderer.
	 * @param mixed    $visitor_rsvp The visitor's RSVP.
	 * @param mixed    $event        The event being rendered, carried through for the
	 *                               modal's summary column.
	 *
	 * @return array|null The decision, or null when nothing should be printed.
	 */
	private function decision_for( $renderer, $visitor_rsvp, $event ) {

		$context_id = (int) $visitor_rsvp->post_id;
		$pending    = $this->pending_rows( $context_id );

		if ( $pending === [] ) {
			return null;
		}

		$token = (string) $pending[0]['token'];

		// Shape, not merely non-empty: printing a token the endpoint will reject
		// traps the visitor in a modal that cannot be dismissed or submitted.
		if ( ! PendingRows::is_valid_token( $token ) ) {
			return null;
		}

		return [
			'renderer'   => $renderer,
			'rows'       => $pending,
			'rsvp'       => $visitor_rsvp,
			'event'      => $event,
			'context_id' => $context_id,
			'token'      => $token,
		];
	}

	/**
	 * Resolve the current singular event.
	 *
	 * The same source RsvpCheckout::event_from_request() and sc-rsvp's own
	 * Frontend use for the current singular request.
	 *
	 * @since 3.13.0
	 *
	 * @return object A Sugar Calendar Event. sugar_calendar_get_event_by_object()
	 *                never returns null — an unresolvable request yields an empty
	 *                Event, tested for via `empty( $event->id )`, not `=== null`.
	 */
	private function event_from_request() {

		if ( ! is_singular( [ 'sc_event', 'sc_recurring_event' ] ) ) {
			return new Event();
		}

		return sugar_calendar_get_event_by_object( get_the_ID() );
	}

	/**
	 * Resolve the visitor's RSVP for an event, the way the add-on does.
	 *
	 * The recognition chain (cookie -> transient -> RSVP post id) belongs to
	 * sc-rsvp; re-implementing it here would give one request two answers.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $event The event being rendered.
	 *
	 * @return mixed The Rsvp object, or false.
	 */
	private function visitor_rsvp_for( $event ) {

		if ( ! is_object( $event ) || empty( $event->id ) ) {
			return false;
		}

		if ( ! class_exists( Helper::class ) || ! class_exists( EventRsvp::class ) ) {
			return false;
		}

		$event_rsvp = EventRsvp::get( (int) $event->id );

		// Mirrors the add-on's own gate: RSVP switched off for this event must be
		// indistinguishable from RSVP never having existed.
		if ( empty( $event_rsvp ) || ! method_exists( $event_rsvp, 'is_enabled' ) || ! $event_rsvp->is_enabled() ) {
			return false;
		}

		return Helper::is_visitor_already_submitted( $event_rsvp );
	}

	/**
	 * The RSVP's rows that still need answers.
	 *
	 * Scoped to the RSVP post id and nothing else — see the class docblock.
	 *
	 * @since 3.13.0
	 *
	 * @param int $context_id RSVP post id.
	 *
	 * @return array[]
	 */
	private function pending_rows( $context_id ) {

		$out = [];

		foreach ( ResponseRepository::get_for_rsvp( $context_id ) as $row ) {
			if ( $row['status'] !== 'complete' ) {
				$out[] = $row;
			}
		}

		return $out;
	}
}
