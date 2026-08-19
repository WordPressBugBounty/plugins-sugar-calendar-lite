<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\RateLimiter;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;

/**
 * Renders the after-checkout form from a token in the URL — the resume link.
 *
 * The third render host, after TicketingReceipt and RsvpAfterCheckout. The gate
 * runs on template_redirect while headers are still open, since this host prints
 * a write credential on an ordinary, cache-friendly singular event URL. Renders
 * nothing except when the token's already complete, which shows a notice instead
 * of a page that looks broken (spec §2.2).
 *
 * @since 3.13.0
 */
class TokenResume {

	/**
	 * The query arg carrying the context's token.
	 *
	 * @since 3.13.0
	 */
	const QUERY_ARG = 'sc_registration';

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
		add_action( 'template_redirect', [ $this, 'decide' ] );
		add_filter( 'the_content', [ $this, 'render' ] );
	}

	/**
	 * Resolve the token while the headers are still open.
	 *
	 * @since 3.13.0
	 */
	public function decide() {

		// Reset first, not merely "set on success", so a second call on this
		// instance (e.g. a test harness firing hooks() twice) can never inherit a
		// previous call's decision.
		$this->decision = null;

		$token = $this->posted_token();

		if ( $token === '' ) {
			return;
		}

		// The hard ceiling, checked before the lookup so an abusive IP can't make
		// the site work to discover its token is worthless. Set far above any
		// legitimate burst, since being refused here is indistinguishable from an
		// outage for everyone behind the same NAT.
		if ( ! RateLimiter::attempt( RateLimiter::ACTION_RESUME_CEILING ) ) {
			return;
		}

		$rows = ResponseRepository::find_by_token( $token );

		if ( $rows === [] ) {
			// The tight budget is spent by misses only, mirroring
			// SubmitEndpoint::fail_unresolved_token() — a shared budget would let bad
			// tokens exhaust it and refuse genuine respondents behind the same NAT.
			RateLimiter::attempt( RateLimiter::ACTION_RESUME );

			return;
		}

		$decision = $this->decision_for( $rows, $token );

		if ( $decision === null ) {
			return;
		}

		$this->decision = $decision;

		nocache_headers();

		// nocache_headers() no-ops if headers are already sent, so DONOTCACHEPAGE
		// is set too as a belt to its braces, not a replacement for it.
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
	 * Whether this request will print anything.
	 *
	 * The memoized decision, public so tests can assert on the decision itself
	 * rather than on DONOTCACHEPAGE, a constant that once defined stays defined.
	 * Mirrors RsvpAfterCheckout::will_print_token().
	 *
	 * @internal Test-only. Public only so the test suite can assert on the
	 *           decision directly; not part of this class's production API.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	public function will_print() {

		return $this->decision !== null;
	}

	/**
	 * Print the form or the notice.
	 *
	 * @since 3.13.0
	 *
	 * @param string $content The post content.
	 *
	 * @return string
	 */
	public function render( $content ) {

		if ( $this->decision === null ) {
			return $content;
		}

		// `the_content` fires for every post whose content is rendered, not just
		// the one being viewed (a widget, a Query Loop, a REST field). The markup
		// carries the write credential, so printing it under the wrong post or
		// twice is not a cosmetic duplicate.
		if ( ! in_the_loop() || ! is_main_query() || get_the_ID() !== $this->queried_post_id() ) {
			return $content;
		}

		if ( $this->decision['state'] === 'complete' ) {
			return $content . $this->notice_html();
		}

		// One root per request; this host stands down for a sibling that already
		// printed one, since after.js only mounts the first `.sc-regform-after`.
		// Claimed at print time, not at decide() time, because RsvpAfterCheckout
		// can still decline in its own render() and standing down on its mere
		// intent could silence both hosts.
		if ( ! AfterCheckoutAssets::claim_root() ) {
			return $content;
		}

		$renderer = $this->decision['renderer'];

		$renderer->enqueue();
		AfterCheckoutAssets::enqueue( $this->labels() );

		return $content . sprintf(
			'<div class="sc-regform-after" data-token="%1$s">%2$s</div>',
			esc_attr( $this->decision['token'] ),
			$renderer->render_static( $this->decision['render_rows'], $this->summary() )
		);
	}

	/**
	 * The modal chrome for the state being printed.
	 *
	 * Editing is a different job from finishing an unanswered form, so the edit
	 * design names it: "Edit Your Registration" over the modal, "Save Edits" on
	 * the button, and a confirmation that reports a save rather than a new
	 * registration. The pending state keeps AfterCheckoutAssets' defaults.
	 *
	 * @since 3.13.0
	 *
	 * @return array Host overrides for AfterCheckoutAssets::enqueue().
	 */
	private function labels() {

		if ( $this->decision['state'] !== 'edit' ) {
			return [];
		}

		return [
			'title'        => __( 'Edit Your Registration', 'sugar-calendar-lite' ),
			'submitLabel'  => __( 'Save Edits', 'sugar-calendar-lite' ),
			'successTitle' => __( 'Your Edits Were Saved Successfully', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * The summary column beside the form.
	 *
	 * The edit design puts the order card under the event card, so an order
	 * context editing its answers sees what it bought. A still-pending resume
	 * link gets the event card alone: it can be opened long after checkout, and
	 * that surface's job is the unanswered questions, not a copy of the receipt.
	 *
	 * @since 3.13.0
	 *
	 * @return array An AfterCheckoutSummary::render() shape.
	 */
	private function summary() {

		$event = sugar_calendar_get_event( (int) $this->decision['event_id'] );

		if ( $this->decision['state'] !== 'edit' || (string) $this->decision['context'] !== 'order' ) {
			return AfterCheckoutSummary::for_event( $event );
		}

		$order = get_order( (int) $this->decision['context_id'] );

		return empty( $order )
			? AfterCheckoutSummary::for_event( $event )
			: AfterCheckoutSummary::for_order( $order, $event );
	}

	/**
	 * The token this request carries, when it is well shaped and this is an event page.
	 *
	 * Shape, not merely non-empty — see PendingRows::is_valid_token(). A malformed
	 * token never reaches the database.
	 *
	 * @since 3.13.0
	 *
	 * @return string Empty when this request carries no usable token.
	 */
	private function posted_token() {

		if ( ! is_singular() || ! is_main_query() ) {
			return '';
		}

		if ( ! isset( $_GET[ self::QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token IS the credential; no nonce exists on an emailed link.
			return '';
		}

		$token = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.

		return PendingRows::is_valid_token( $token ) ? $token : '';
	}

	/**
	 * Decide what to print for a resolved token.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows  The token's rows.
	 * @param string  $token The token.
	 *
	 * @return array|null Null when nothing should print.
	 */
	private function decision_for( array $rows, $token ) {

		$event_id = (int) $rows[0]['event_id'];

		// The cross-check: a token is valid for its context no matter which page
		// it's pasted onto. Without this, event B's token would print under event
		// A's schema and never validate (spec §6.2).
		if ( ! $this->event_matches( $event_id ) ) {
			return null;
		}

		$pending = self::pending_only( $rows );

		if ( $pending === [] ) {
			return $this->edit_decision( $rows, $token, $event_id );
		}

		$renderer = $this->renderer_for_host( $event_id, $rows[0] );

		if ( $renderer === null ) {
			return null;
		}

		$render_rows = $this->render_rows( (string) $rows[0]['context'], (int) $rows[0]['context_id'], $pending );

		if ( $render_rows === [] ) {
			return null;
		}

		return [
			'state'       => 'pending',
			'token'       => (string) $token,
			'renderer'    => $renderer,
			'render_rows' => $render_rows,
			'event_id'    => $event_id,
			'context'     => (string) $rows[0]['context'],
			'context_id'  => (int) $rows[0]['context_id'],
		];
	}

	/**
	 * What to print for a token whose every row is already answered.
	 *
	 * The read-only notice unless the organizer allowed editing, in which case
	 * every row is offered back, prefilled. Deliberately all rows and not a
	 * subset: a mixed token never reaches here, so "all" is exactly "the ones
	 * that were answered".
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows     The token's rows.
	 * @param string  $token    The token.
	 * @param int     $event_id The token's event id.
	 *
	 * @return array|null
	 */
	private function edit_decision( array $rows, $token, $event_id ) {

		$renderer = $this->renderer_for_host( $event_id, $rows[0], true );

		if ( $renderer === null || ! $this->editing_allowed( $renderer ) ) {
			return [ 'state' => 'complete' ];
		}

		$render_rows = $this->render_rows( (string) $rows[0]['context'], (int) $rows[0]['context_id'], $rows );

		if ( $render_rows === [] ) {
			return [ 'state' => 'complete' ];
		}

		return [
			'state'       => 'edit',
			'token'       => (string) $token,
			'renderer'    => $renderer,
			'render_rows' => $render_rows,
			'event_id'    => $event_id,
			'context'     => (string) $rows[0]['context'],
			'context_id'  => (int) $rows[0]['context_id'],
		];
	}

	/**
	 * Whether this event's organizer allowed answers to be rewritten.
	 *
	 * @since 3.13.0
	 *
	 * @param Renderer $renderer The event's renderer.
	 *
	 * @return bool
	 */
	private function editing_allowed( $renderer ) {

		$schema = $renderer->schema();

		return ! empty( $schema['allow_edit'] );
	}

	/**
	 * The host gate, then the renderer.
	 *
	 * Applies HostState's rule here as at the other two print seams, so a
	 * refunded order (a reminder link can outlive the refund) refuses printing
	 * the same way a trashed event already did. The already-complete notice in
	 * decision_for() is deliberately not behind this gate, since it carries no
	 * token and there's nothing to be trapped in.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $event_id The token's Sugar Calendar event id.
	 * @param array $row      Any one of the token's rows, for context/context_id.
	 * @param bool  $for_edit Whether this lookup is for the edit path, which
	 *                        reopens answers already collected under any mode.
	 *
	 * @return Renderer|null The renderer, or null when nothing should be printed.
	 */
	private function renderer_for_host( $event_id, array $row, $for_edit = false ) {

		if ( ! $this->host_permits_printing( (string) $row['context'], (int) $row['context_id'] ) ) {
			return null;
		}

		$renderer = Renderer::for_event( $event_id );

		if ( $renderer === null ) {
			return null;
		}

		// An edit reopens answers that were already collected, so the host that
		// collected them is irrelevant — the fields and the token are the same
		// either way. This covers a context minted while the schema said after_checkout
		// on an event since switched to before_checkout: requiring `after` here would
		// strand answers that still hold a valid token. A genuinely before-checkout
		// context never reaches this at all — that path mints no token (see
		// TicketingCheckout::persist()).
		if ( ! $for_edit && $renderer->mode() !== 'after' ) {
			return null;
		}

		return $renderer;
	}

	/**
	 * Whether both of this request's host states still invite an answer.
	 *
	 * @since 3.13.0
	 *
	 * @param string $context    Either 'order' or 'rsvp'.
	 * @param int    $context_id Order id or RSVP post id.
	 *
	 * @return bool
	 */
	private function host_permits_printing( $context, $context_id ) {

		// event_matches() already established the viewed post is this event's,
		// so the same reader is used here to keep the gate and the cross-check
		// in agreement.
		if ( ! HostState::event_permits_printing( $this->queried_post_id() ) ) {
			return false;
		}

		// RSVP has no withdrawn state of its own.
		if ( $context !== 'order' ) {
			return true;
		}

		return HostState::order_permits_printing( get_order( $context_id ) );
	}

	/**
	 * The rows of one token's set that still need answers.
	 *
	 * Split out of decision_for() to keep it under the project's phpcs complexity
	 * ceiling.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows The token's rows.
	 *
	 * @return array[]
	 */
	private static function pending_only( array $rows ) {

		$out = [];

		foreach ( $rows as $row ) {
			if ( $row['status'] !== 'complete' ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Whether the token's event is the event being rendered.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id The token's event id.
	 *
	 * @return bool
	 */
	private function event_matches( $event_id ) {

		$event = sugar_calendar_get_event( $event_id );

		$post_id = isset( $event->object_id ) ? (int) $event->object_id : 0;

		return $post_id > 0 && $post_id === $this->queried_post_id();
	}

	/**
	 * The post being viewed.
	 *
	 * One reader for both the event cross-check and render()'s loop guard, so the two
	 * cannot disagree about which post this request is for.
	 *
	 * @since 3.13.0
	 *
	 * @return int
	 */
	private function queried_post_id() {

		return (int) get_queried_object_id();
	}

	/**
	 * Shape the pending rows for rendering, per context.
	 *
	 * @since 3.13.0
	 *
	 * @param string  $context    Either 'order' or 'rsvp'.
	 * @param int     $context_id Context id.
	 * @param array[] $rows       Pending rows.
	 *
	 * @return array[]
	 */
	private function render_rows( $context, $context_id, array $rows ) {

		if ( $context === 'order' ) {

			$order = get_order( $context_id );

			return empty( $order ) ? [] : RespondentRows::for_order( $order, $rows );
		}

		// The RSVP host resolves its own model; mirror whatever
		// RsvpAfterCheckout::visitor_rsvp_for() uses, behind class_exists().
		return RespondentRows::for_rsvp_id( $context_id, $rows );
	}

	/**
	 * The already-complete notice.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	private function notice_html() {

		return sprintf(
			'<p class="sc-regform-resume-complete">%s</p>',
			esc_html__( 'Your registration is already complete. Thank you!', 'sugar-calendar-lite' )
		);
	}
}
