<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;

/**
 * Renders the after-checkout registration form on the ticketing receipt page.
 *
 * The gate is the order, not the rendered event: the receipt page is an
 * ordinary WP page hosting a shortcode. A resolvable form, after-checkout
 * mode, and pending rows are all required, or nothing renders.
 *
 * @since 3.13.0
 */
class TicketingReceipt {

	/**
	 * Response context for ticketing orders.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const CONTEXT = 'order';

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_ticketing_receipt_after', [ $this, 'render' ], 10, 2 );
	}

	/**
	 * Render the form, when this order needs one.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order The order.
	 * @param object $event The event, as the receipt page around the modal resolved it.
	 *                      Used for the summary column only; every gate below keys on
	 *                      the order's own event id.
	 */
	public function render( $order, $event ) {

		$order_id = isset( $order->id ) ? (int) $order->id : 0;
		$event_id = isset( $order->event_id ) ? (int) $order->event_id : 0;

		if ( $order_id <= 0 || $event_id <= 0 ) {
			return;
		}

		if ( ! $this->host_permits_printing( $order, $event_id ) ) {
			return;
		}

		$renderer = Renderer::for_event( $event_id );

		if ( $renderer === null ) {
			return;
		}

		// Before-checkout has nothing to ask here — the answers were stored inside the
		// checkout — but it still owes the confirmation the redirect cut short.
		if ( $renderer->mode() !== 'after' ) {
			$this->maybe_confirm( $renderer );

			return;
		}

		$pending = $this->pending_rows( $order_id );

		if ( $pending === [] ) {
			return;
		}

		$token = (string) $pending[0]['token'];

		// Shape, not merely non-empty; see PendingRows::is_valid_token(). The modal
		// is non-dismissible (AfterCheckoutAssets::enqueue()), so a form that can
		// never be submitted is worse than no form.
		if ( ! PendingRows::is_valid_token( $token ) ) {
			return;
		}

		// The receipt link alone does not authorise writing answers: this browser
		// must be the one that checked out. Anyone else — a forwarded link, a second
		// device — completes the form through the resume link in the order email
		// instead, whose token is its own credential. See CheckoutSession.
		if ( ! CheckoutSession::proves( $order_id, $token ) ) {
			return;
		}

		$renderer->enqueue();

		// The buyer has already paid at this point, so the CTA completes the order
		// rather than merely submitting a form.
		// The success stage names what completed, so on this host it is the order
		// rather than the registration (Track B design §7.4).
		AfterCheckoutAssets::enqueue(
			[
				'submitLabel'  => __( 'Complete Order', 'sugar-calendar-lite' ),
				'successTitle' => __( 'Your Order was Successful!', 'sugar-calendar-lite' ),
				// Dismisses rather than navigates: the receipt is already behind the modal.
				'successCta'   => __( 'View Order Details', 'sugar-calendar-lite' ),
			]
		);

		$form = $renderer->render_static(
			RespondentRows::for_order( $order, $pending ),
			AfterCheckoutSummary::for_order( $order, $event )
		);

		printf(
			'<div class="sc-regform-after" data-token="%1$s" data-order-id="%2$d">%3$s</div>',
			esc_attr( $token ),
			absint( $order_id ),
			$form // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped per field and per label inside render_static().
		);
	}

	/**
	 * Raise the confirmation, when this browser is the one that just checked out.
	 *
	 * Prints nothing: the only decision here is whether to load the controller that
	 * owns the stage. A full-page cache serving that script to a later visitor is
	 * harmless — the controller looks for the same note, which they do not have.
	 *
	 * @since 3.13.0
	 *
	 * @param Renderer $renderer The renderer for the order's event.
	 */
	private function maybe_confirm( $renderer ) {

		$event_id = $renderer->event_id();

		if ( ! CompletionFlash::has( $event_id ) ) {
			return;
		}

		$renderer->enqueue();

		AfterCheckoutAssets::enqueue(
			[
				'openSuccess'  => true,
				'flashCookie'  => CompletionFlash::cookie_name( $event_id ),
				'successTitle' => __( 'Your Order was Successful!', 'sugar-calendar-lite' ),
				'successCta'   => __( 'View Order Details', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * Whether both of this host's states still invite an answer.
	 *
	 * Checks the order (a refund keeps its rows but must stop asking for new
	 * answers) and the event (this seam never consults the event post, so
	 * without this check it would print a token for a trashed event).
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $order    The order.
	 * @param int   $event_id The Sugar Calendar event id.
	 *
	 * @return bool
	 */
	private function host_permits_printing( $order, $event_id ) {

		if ( ! HostState::order_permits_printing( $order ) ) {
			return false;
		}

		return HostState::event_permits_printing( $this->event_post_id( $event_id ) );
	}

	/**
	 * The WordPress post id behind a Sugar Calendar event id.
	 *
	 * Resolved rather than taken from render()'s $event argument: this seam keys
	 * everything on the order's event id, and the two are not guaranteed to agree
	 * for a recurring occurrence.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id The Sugar Calendar event id.
	 *
	 * @return int The post id, or 0 when it cannot be resolved.
	 */
	private function event_post_id( $event_id ) {

		$event = sugar_calendar_get_event( (int) $event_id );

		return isset( $event->object_id ) ? (int) $event->object_id : 0;
	}

	/**
	 * The order's rows that still need answers.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array[]
	 */
	private function pending_rows( $order_id ) {

		$out = [];

		foreach ( ResponseRepository::get_for_order( $order_id ) as $row ) {
			if ( $row['status'] !== 'complete' ) {
				$out[] = $row;
			}
		}

		return $out;
	}
}
