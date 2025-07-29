<?php
namespace Sugar_Calendar\AddOn\Ticketing\Frontend\Single;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers;
use DateTime;

/**
 * Render the ticket add-to-cart form.
 *
 * @since 1.0.0
 * @since 3.1.0 Only display on single event page.
 * @since 3.2.0 Added a check to determine if the tickets should be displayed.
 * @since 3.6.0 Added $event parameter.
 * @since 3.8.0 Added limit capacity check.
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

	// Bail if not enabled.
	if ( empty( Helpers::is_event_ticketing_enabled( $event ) ) ) {
		return;
	}

	if ( ! Functions\should_display_tickets( $event ) ) {
		return;
	}

	/**
	 * Filter to determine if the event has multiple tickets.
	 *
	 * @since 3.8.0
	 *
	 * @param bool   $is_multiple_tickets Whether the event has multiple tickets.
	 * @param Event  $event               The event object.
	 * @param string $post_id             The post ID.
	 */
	$is_multiple_tickets = apply_filters( 'sc_et_is_multiple_tickets', false, $event, $post_id );

	// If ticket is multiple, use multiple tickets form.
	if ( $is_multiple_tickets ) {

		/**
		 * Multiple tickets action hook.
		 *
		 * @since 3.8.0
		 *
		 * @param Event  $event   The event object.
		 * @param string $post_id The post ID.
		 */
		do_action( 'sc_et_multiple_tickets_form', $event, $post_id );

		return;
	}

	$tz    = wp_timezone();
	$start = new DateTime( $event->start, $tz );
	$today = new DateTime( 'now', $tz );
	$price = get_event_meta( $event->id, 'ticket_price', true );

	// Check if capacity limitation is enabled.
	$limit_capacity = absint( get_event_meta( $event->id, 'ticket_limit_capacity', true ) );
	$available      = Functions\get_available_tickets( $event->id );

	// Only limit if capacity limitation is enabled.
	$remaining = $limit_capacity ? absint( $available ) : null;
	$max       = $limit_capacity
		? wp_sprintf(
			'max=%s',
			$remaining
		)
		: '';
	?>

	<div id="sc-event-ticketing-wrap" class="sc-et-card">
		<div class="sc-et-card-header"><?php esc_html_e( 'Event Tickets', 'sugar-calendar-lite' ); ?></div>
		<?php
		/**
		 * Action hook that fires at the top of the ticket form.
		 *
		 * @since 3.8.0
		 *
		 * @param Event      $event   The event object.
		 * @param int|string $post_id The post ID.
		 */
		do_action( 'sc_event_tickets_form_top', $event, $post_id );
		?>
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
								<input
									type="number"
									name="sc-event-ticketing-quantity"
									id="sc-event-ticketing-quantity"
									class="sc-et-form-control"
									aria-label="<?php esc_attr_e( 'Quantity', 'sugar-calendar-lite' ); ?>"
									value="1"
									step="1"
									min="1"
									<?php echo esc_attr( $max ); ?>
								/>
							</div>
							<div class="sc-event-ticketing-price sc-et-card-title">
								<?php
								printf(
									/* translators: %s: price. */
									esc_html__(
										'%s per ticket',
										'sugar-calendar-lite'
									),
									wp_kses_post( Functions\currency_filter( $price ) )
								);
								?>
							</div>
						</div>
						<div class="sc-event-ticketing-price-wrap__add-to-cart-section sc-et-col-sm sc-et-text-right">
							<div class="sc-event-ticketing-price-wrap__add-to-cart-section__btn-container sc-et-card-title">
								<?php if ( ! $limit_capacity || $available >= 1 ) : ?>
									<?php echo wp_kses_post( get_purchase_button( $event ) ); ?>
								<?php else : ?>
									<strong><?php esc_html_e( 'Sold Out', 'sugar-calendar-lite' ); ?></strong>
								<?php endif; ?>
							</div>
							<div class="sc-event-ticketing-qty-available">
								<?php
								if ( $limit_capacity ) {
									/* translators: %d: number of available tickets. */
									printf( esc_html__( '%d available', 'sugar-calendar-lite' ), absint( $remaining ) );
								}
								?>
							</div>
						</div>

					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		/**
		 * Action hook that fires at the bottom of the ticket form.
		 *
		 * @since 1.0.0
		 *
		 * @param Event      $event   The event object.
		 * @param int|string $post_id The post ID.
		 */
		do_action( 'sc_event_tickets_form_bottom', $event, $post_id );
		?>
	</div>

<?php
}

/**
 * Retrieve the purchase button HTML.
 *
 * @since 1.1.0
 *
 * @param Event $event The event object.
 *
 * @return string The purchase button HTML.
 */
function get_purchase_button( $event ) {

	$button = '<a href="#" id="sc-event-ticketing-buy-button" class="sc-et-btn sc-et-btn-primary" data-toggle="modal" data-target="#sc-event-ticketing-modal">' . esc_html__( 'Go to Checkout', 'sugar-calendar-lite' ) . '</a>';

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
