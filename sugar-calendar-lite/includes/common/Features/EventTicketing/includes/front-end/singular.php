<?php
namespace Sugar_Calendar\AddOn\Ticketing\Frontend\Single;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers;

/**
 * Render the ticket add-to-cart form.
 *
 * @since 1.0.0
 * @since 3.1.0 Only display on single event page.
 * @since 3.2.0 Added a check to determine if the tickets should be displayed.
 * @since 3.6.0 Added $event parameter.
 *
 * @param int|string $post_id The post ID.
 */
function display( $post_id = 0, $event = null ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

	// Bail if no post.
	if ( empty( $post_id ) || ! is_singular( sugar_calendar_get_event_post_type_id() ) ) {
		return;
	}

	if ( empty( $event ) ) {
		$event = sugar_calendar_get_event_by_object( $post_id );
	}

	// Bail if not enabled.
	if ( empty( Helpers::is_event_ticketing_enabled( $event ) ) ) {
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

	<div id="sc-event-ticketing-wrap" class="sc-et-card">
		<div class="sc-et-card-header"><?php esc_html_e( 'Event Tickets', 'sugar-calendar-lite' ); ?></div>
		<?php do_action( 'sc_event_tickets_form_top' ); ?>
		<div id="sc-event-ticketing-price-wrap" class="sc-et-card-body">
			<div class="sc-et-container">
				<div class="sc-et-row">

					<?php if ( $today > $start ) : ?>

						<?php esc_html_e( 'This event has past so tickets are no longer available.', 'sugar-calendar-lite' ); ?>

					<?php else : ?>

						<div class="sc-et-col-sm">
							<div class="sc-event-ticketing-price-wrap__input-group sc-et-input-group sc-et-mb-3">
								<div class="sc-et-input-group-prepend">
									<span class="sc-et-input-group-text"><?php esc_html_e( 'Qty', 'sugar-calendar-lite' ); ?></span>
								</div>
								<input type="number" class="sc-et-form-control" step="1" min="1" aria-label="<?php esc_attr_e( 'Quantity', 'sugar-calendar-lite' ); ?>" name="sc-event-ticketing-quantity" id="sc-event-ticketing-quantity" value="1" max="<?php echo esc_attr( $remaining ); ?>" />
							</div>
							<div class="sc-event-ticketing-price sc-et-card-title">
								<?php
								printf(
									esc_html__(
										'%s per ticket', 'sugar-calendar-lite' ),
									Functions\currency_filter( $price )
								);
								?>
							</div>
						</div>
						<div class="sc-event-ticketing-price-wrap__add-to-cart-section sc-et-col-sm sc-et-text-right">
							<div class="sc-event-ticketing-price-wrap__add-to-cart-section__btn-container sc-et-card-title">
								<?php if ( $available >= 1 ) : ?>
									<?php echo get_purchase_button( $event ); ?>
								<?php else : ?>
									<strong><?php esc_html_e( 'Sold Out', 'sugar-calendar-lite' ); ?></strong>
								<?php endif; ?>
							</div>
							<div class="sc-event-ticketing-qty-available">
								<?php printf( esc_html__( '%d available', 'sugar-calendar-lite' ), $remaining ); ?>
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
 * Retrieve the purchase button HTML.
 *
 * @since 1.1.0
 */
function get_purchase_button( $event ) {

	$button = '<a href="#" id="sc-event-ticketing-buy-button" class="sc-et-btn sc-et-btn-primary" data-toggle="modal" data-target="#sc-event-ticketing-modal">' . esc_html__( 'Add to Cart', 'sugar-calendar-lite' ) . '</a>';

	return apply_filters( 'sc_et_purchase_button_html', $button, $event );
}
