<?php
namespace Sugar_Calendar\AddOn\Ticketing\Frontend\Single;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers;
use Sugar_Calendar\AddOn\Ticketing\Renderer;

/**
 * Render the ticket add-to-cart form.
 *
 * @since 1.0.0
 * @since 3.1.0 Only display on single event page.
 * @since 3.2.0 Added a check to determine if the tickets should be displayed.
 * @since 3.6.0 Added $event parameter.
 * @since 3.8.0 Added limit capacity check.
 * @since 3.10.0 Added Renderer class.
 *
 * @param int|string $post_id The post ID.
 * @param Event      $event   The event object.
 */
function display( $post_id = 0, $event = null ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

	// Bail if no post.
	if ( empty( $post_id ) || ! is_singular( sugar_calendar_get_event_post_type_id() ) ) {
		return;
	}

	if ( empty( $event ) ) {
		$event = sugar_calendar_get_event_by_object( $post_id );
	}

	$renderer = new Renderer( $event, $post_id );

	$renderer->maybe_render_ticket_box();
}

/**
 * Retrieve the purchase button HTML.
 *
 * @since 1.1.0
 * @since 3.11.0 Added `sc-event-ticketing-buy-button` in DOM class.
 * @since 3.10.0 Added $should_enable_modal parameter.
 *
 * @param Event $event               The event object.
 * @param bool  $should_enable_modal Whether to enable the modal.
 *
 * @return string The purchase button HTML.
 */
function get_purchase_button( $event, $should_enable_modal = true ) {

	$modal_dom_attr = '';

	if ( $should_enable_modal ) {
		$modal_dom_attr = 'data-toggle="modal" data-target="#sc-event-ticketing-modal"';
	}

	$button = '<a href="#" id="sc-event-ticketing-buy-button" class="sc-et-btn sc-et-btn-primary sc-event-ticketing-buy-button" ' . $modal_dom_attr . '>' . esc_html__( 'Go to Checkout', 'sugar-calendar-lite' ) . '</a>';

	/**
	 * Filters the purchase button HTML.
	 *
	 * @since 1.1.0
	 *
	 * @param string $button The button HTML.
	 * @param Event  $event  The event object.
	 */
	return apply_filters( 'sc_et_purchase_button_html', $button, $event );
}
