<?php
namespace Sugar_Calendar\AddOn\Ticketing\Frontend\Single;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;

/**
 * Render the ticket add-to-cart form.
 *
 * @since 1.0.0
 * @since 3.1.0 Only display on single event page.
 * @since 3.2.0 Added a check to determine if the tickets should be displayed.
 *
 * @param int|string $post_id The post ID.
 */
function display( $post_id = 0 ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

	// Bail if no post.
	if ( empty( $post_id ) || ! is_singular( sugar_calendar_get_event_post_type_id() ) ) {
		return;
	}

	$event   = sugar_calendar_get_event_by_object( $post_id );
	$enabled = get_event_meta( $event->id, 'tickets', true );

	// Bail if not enabled.
	if ( empty( $enabled ) ) {
		return;
	}

	if ( ! Functions\should_display_tickets( $event ) ) {
		return;
	}

	$tz        = wp_timezone();
	$start     = new \DateTime( $event->start, $tz );
	$today     = new \DateTime( 'now', $tz );
	$price     = get_event_meta( $event->id, 'ticket_price', true );
	$available = Functions\get_available_tickets( $event->id );

	$remaining = ( $available < 0 )
		? 0
		: absint( $available ); ?>

	<div id="sc-event-ticketing-wrap" class="card">
		<div class="card-header"><?php esc_html_e( 'Event Tickets', 'sugar-calendar' ); ?></div>
		<?php do_action( 'sc_event_tickets_form_top' ); ?>
		<div id="sc-event-ticketing-price-wrap" class="card-body">
			<div class="container">
				<div class="row">

					<?php if ( $today > $start ) : ?>

						<?php esc_html_e( 'This event has past so tickets are no longer available.', 'sugar-calendar' ); ?>

					<?php else : ?>

						<div class="col-sm">
							<div class="sc-event-ticketing-price-wrap__input-group input-group mb-3">
								<div class="input-group-prepend">
									<span class="input-group-text"><?php esc_html_e( 'Qty', 'sugar-calendar' ); ?></span>
								</div>
								<input type="number" class="form-control" step="1" min="1" aria-label="<?php esc_attr_e( 'Quantity', 'sugar-calendar' ); ?>" name="sc-event-ticketing-quantity" id="sc-event-ticketing-quantity" value="1" max="<?php echo esc_attr( $remaining ); ?>" />
							</div>
							<div class="sc-event-ticketing-price card-title">
								<?php printf( esc_html__( '%s per ticket', 'sugar-calendar' ), Functions\currency_filter( $price ) ); ?>
							</div>
						</div>
						<div class="sc-event-ticketing-price-wrap__add-to-cart-section col-sm text-right">
							<div class="sc-event-ticketing-price-wrap__add-to-cart-section__btn-container card-title">
								<?php if ( $available >= 1 ) : ?>
									<?php echo get_purchase_button( $event ); ?>
								<?php else : ?>
									<strong><?php esc_html_e( 'Sold Out', 'sugar-calendar' ); ?></strong>
								<?php endif; ?>
							</div>
							<div class="sc-event-ticketing-qty-available">
								<?php printf( esc_html__( '%d available', 'sugar-calendar' ), $remaining ); ?>
							</div>
						</div>

					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php do_action( 'sc_event_tickets_form_bottom', $event, $post_id ); ?>
	</div>

<?php
}

/**
 * Retrieve the purchase button HTML
 *
 * @since 1.1.0
 */
function get_purchase_button( $event ) {
	$button = '<a href="#" id="sc-event-ticketing-buy-button" class="btn btn-primary" data-toggle="modal" data-target="#sc-event-ticketing-modal">' . esc_html__( 'Add to Cart', 'sugar-calendar' ) . '</a>';
	return apply_filters( 'sc_et_purchase_button_html', $button, $event );
}
