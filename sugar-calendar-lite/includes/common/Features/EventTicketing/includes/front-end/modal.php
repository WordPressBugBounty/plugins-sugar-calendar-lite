<?php
namespace Sugar_Calendar\AddOn\Ticketing\Frontend\Modal;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Gateways\Checkout;
use Sugar_Calendar\AddOn\Ticketing\Settings;
use Sugar_Calendar\Event;
use Sugar_Calendar\Helpers;

/**
 * Render the event date time.
 *
 * @since 3.1.0
 * @since 3.1.2 Use `wp_kses` instead of `esc_html` when rendering the output value.
 *
 * @param \Sugar_Calendar\Event $event The event object.
 *
 * @return void
 */
function render_event_date_time( $event ) {

	if ( $event->is_multi() ) {
		?>
		<p class="sc-event-ticketing-checkout-totals__summary-block__multi-day-datetime">
			<span><?php esc_html_e( 'Date/Time:', 'sugar-calendar-lite' ); ?></span>
			<span class="sc-event-ticketing-checkout-totals__summary-block__multi-day-datetime__val"><strong><?php
					echo wp_kses(
						Helpers::get_multi_day_event_datetime( $event ),
						[
							'span' => [
								'class' => true,
							],
							'time' => [
								'data-timezone' => true,
								'datetime'      => true,
								'title'         => true,
							],
						]
					);
					?></strong></span>
		</p>
		<?php

		return;
	}

	$date_time = [
		[
			'class' => 'sc-event-ticketing-checkout-totals__summary-block__date',
			'label' => __( 'Date:', 'sugar-calendar-lite' ),
			'value' => Helpers::get_event_datetime( $event ),
		],
		[
			'class' => 'sc-event-ticketing-checkout-totals__summary-block__time',
			'label' => __( 'Time:', 'sugar-calendar-lite' ),
			'value' => Helpers::get_event_datetime( $event, 'time' ),
		],
	];

	foreach ( $date_time as $dt ) {
		?>
		<p class="<?php echo esc_attr( $dt['class'] ); ?>">
			<span><?php echo esc_html( $dt['label'] ); ?></span>
			<span><strong>
					<?php
					echo wp_kses(
						$dt['value'],
						[
							'time' => [
								'data-timezone' => true,
								'datetime'      => true,
								'title'         => true,
							],
						]
					);
					?>
			</strong></span>
		</p>
		<?php
	}
}

/**
 * Render the checkout modal
 *
 * @since 1.0.0
 * @since 3.6.0 Modify optional description for attendee information based on settings.
 */
function display() {

	if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) ) {
		return;
	}

	/**
	 * Filter the Event object to be used in the enqueueing of the Event Ticketing assets.
	 *
	 * @since 3.6.0
	 *
	 * @param \Sugar_Calendar\Event $event The Event object.
	 */
	$event = apply_filters(
		'sugar_calendar_add_on_ticketing_frontend_loader_event_object',
		sugar_calendar_get_event_by_object( get_the_ID() )
	);

	$enabled = get_event_meta( $event->id, 'tickets', true );

	if ( empty( $enabled ) ) {
		return;
	}

	$tz    = wp_timezone();
	$start = new \DateTime( $event->start, $tz );
	$today = new \DateTime( 'now',         $tz );

	if ( $today > $start ) {
		return;
	}

	$price = get_event_meta( $event->id, 'ticket_price', true );

	$is_ticket_free = false;

	if (
		empty( $price ) ||
		floatval( $price ) <= 0
	) {
		$is_ticket_free = true;
	}

	$field_attendee_info_description = sprintf(
		/* translators: %s: Optional text. */
		__( 'Enter the name and email of all attendees%s.', 'sugar-calendar-lite' ),
		Settings\get_setting( 'attendee_fields_is_required', false ) ? '' : ' (optional)'
	);
	?>

	<div class="modal fade" id="sc-event-ticketing-modal" tabindex="-1" role="dialog" aria-labelledby="sc-event-ticketing-modalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
			<div class="modal-content">
				<form id="sc-event-ticketing-checkout" method="post">

					<div class="modal-header">
						<h3 class="modal-title" id="sc-event-ticketing-modalLabel"><?php esc_html_e( 'Event Tickets', 'sugar-calendar-lite' ); ?></h3>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M8 0.25C12.2812 0.25 15.75 3.71875 15.75 8C15.75 12.2812 12.2812 15.75 8 15.75C3.71875 15.75 0.25 12.2812 0.25 8C0.25 3.71875 3.71875 0.25 8 0.25ZM8 14.25C11.4375 14.25 14.25 11.4688 14.25 8C14.25 4.5625 11.4375 1.75 8 1.75C4.53125 1.75 1.75 4.5625 1.75 8C1.75 11.4688 4.53125 14.25 8 14.25ZM11.1562 6.0625L9.21875 8L11.1562 9.96875C11.3125 10.0938 11.3125 10.3438 11.1562 10.5L10.4688 11.1875C10.3125 11.3438 10.0625 11.3438 9.9375 11.1875L8 9.25L6.03125 11.1875C5.90625 11.3438 5.65625 11.3438 5.5 11.1875L4.8125 10.5C4.65625 10.3438 4.65625 10.0938 4.8125 9.96875L6.75 8L4.8125 6.0625C4.65625 5.9375 4.65625 5.6875 4.8125 5.53125L5.5 4.84375C5.65625 4.6875 5.90625 4.6875 6.03125 4.84375L8 6.78125L9.9375 4.84375C10.0625 4.6875 10.3125 4.6875 10.4688 4.84375L11.1562 5.53125C11.3125 5.6875 11.3125 5.9375 11.1562 6.0625Z" fill="currentColor"/>
							</svg>
						</button>
					</div>

					<div class="sc-et-container modal-body">
						<div class="sc-et-row">
							<div class="sc-et-col-8" id="sc-event-ticketing-checkout-main">

								<fieldset id="sc-event-ticketing-modal-billing-fieldset" class="sc-et-fieldset">
									<legend class="sc-et-legend"><?php esc_html_e( 'Billing Details', 'sugar-calendar-lite' ); ?></legend>

									<div class="sc-event-ticketing-modal-billing-fieldset__names">
										<div class="sc-et-form-group">
											<label class="sc-et-label" for="sc-event-ticketing-first-name"><?php esc_html_e( 'First Name', 'sugar-calendar-lite' ); ?></label>
											<input type="text" class="sc-et-form-control" name="first_name" id="sc-event-ticketing-first-name" value="" placeholder="<?php esc_attr_e( 'Your first name', 'sugar-calendar-lite' ); ?>" />
										</div>
										<div class="sc-et-form-group">
											<label class="sc-et-label" for="sc-event-ticketing-last-name"><?php esc_html_e( 'Last Name', 'sugar-calendar-lite' ); ?></label>
											<input type="text" class="sc-et-form-control" name="last_name" id="sc-event-ticketing-last-name" value="" placeholder="<?php esc_attr_e( 'Your last name', 'sugar-calendar-lite' ); ?>" />
										</div>
									</div>

									<div class="sc-event-ticketing-modal-billing-fieldset__email sc-et-form-group">
										<label class="sc-et-label" for="sc-event-ticketing-email"><?php esc_html_e( 'Email Address', 'sugar-calendar-lite' ); ?></label>
										<input type="email" class="sc-et-form-control" name="email" id="sc-event-ticketing-email" value="" placeholder="<?php esc_attr_e( 'Enter email address', 'sugar-calendar-lite' ); ?>" />
									</div>
								</fieldset>

								<fieldset id="sc-event-ticketing-modal-attendee-fieldset" class="sc-et-fieldset">
									<legend class="sc-et-legend"><?php esc_html_e( 'Attendee Information', 'sugar-calendar-lite' ); ?></legend>
									<p><?php echo esc_html( $field_attendee_info_description ); ?> <a href="#" id="sc-event-ticketing-copy-billing-attendee"><?php esc_html_e( 'Copy from Billing Details.', 'sugar-calendar-lite' ); ?></a></p>
									<div id="sc-event-ticketing-modal-attendee-list">
										<div class="sc-et-form-group sc-event-ticketing-attendee" data-key="1" attendee-key="1">
											<div class="sc-event-ticketing-attendee__input-group sc-et-input-group">
												<div class="sc-et-input-group-prepend">
													<span class="sc-et-input-group-text sc-event-ticketing-attendee__input-group__attendee-label" id=""><?php esc_html_e( 'Attendee 1', 'sugar-calendar-lite' ); ?></span>
												</div>
												<input type="text" class="sc-event-ticketing-attendee__input-first-name sc-et-form-control" name="attendees[1][first_name]" placeholder="<?php esc_attr_e( 'First name', 'sugar-calendar-lite' ); ?>">
												<input type="text" class="sc-event-ticketing-attendee__input-last-name sc-et-form-control" name="attendees[1][last_name]" placeholder="<?php esc_attr_e( 'Last name', 'sugar-calendar-lite' ); ?>">
												<input type="text" class="sc-event-ticketing-attendee__input-email sc-et-form-control" name="attendees[1][email]" placeholder="<?php esc_attr_e( 'Email', 'sugar-calendar-lite' ); ?>">

												<div class="sc-event-ticketing-attendee-controls-group">
													<svg class="sc-event-ticketing-add-attendee" width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
														<path d="M12 8V9C12 9.21875 11.8125 9.375 11.625 9.375H8.875V12.125C8.875 12.3438 8.6875 12.5 8.5 12.5H7.5C7.28125 12.5 7.125 12.3438 7.125 12.125V9.375H4.375C4.15625 9.375 4 9.21875 4 9V8C4 7.8125 4.15625 7.625 4.375 7.625H7.125V4.875C7.125 4.6875 7.28125 4.5 7.5 4.5H8.5C8.6875 4.5 8.875 4.6875 8.875 4.875V7.625H11.625C11.8125 7.625 12 7.8125 12 8ZM15.75 8.5C15.75 12.7812 12.2812 16.25 8 16.25C3.71875 16.25 0.25 12.7812 0.25 8.5C0.25 4.21875 3.71875 0.75 8 0.75C12.2812 0.75 15.75 4.21875 15.75 8.5ZM14.25 8.5C14.25 5.0625 11.4375 2.25 8 2.25C4.53125 2.25 1.75 5.0625 1.75 8.5C1.75 11.9688 4.53125 14.75 8 14.75C11.4375 14.75 14.25 11.9688 14.25 8.5Z" fill="currentColor"/>
													</svg>

													<svg class="sc-event-ticketing-remove-attendee sc-event-ticketing-control-inactive" role="button" width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
														<path d="M4.375 9.375C4.15625 9.375 4 9.21875 4 9V8C4 7.8125 4.15625 7.625 4.375 7.625H11.625C11.8125 7.625 12 7.8125 12 8V9C12 9.21875 11.8125 9.375 11.625 9.375H4.375ZM15.75 8.5C15.75 12.7812 12.2812 16.25 8 16.25C3.71875 16.25 0.25 12.7812 0.25 8.5C0.25 4.21875 3.71875 0.75 8 0.75C12.2812 0.75 15.75 4.21875 15.75 8.5ZM14.25 8.5C14.25 5.0625 11.4375 2.25 8 2.25C4.53125 2.25 1.75 5.0625 1.75 8.5C1.75 11.9688 4.53125 14.75 8 14.75C11.4375 14.75 14.25 11.9688 14.25 8.5Z" fill="currentColor"/>
													</svg>
												</div>
											</div>
										</div>
									</div>
								</fieldset>

								<?php
								$payment_fieldset_display = $is_ticket_free ? 'display:none' : '';
								?>

								<fieldset id="sc-event-ticketing-modal-payment-fieldset" class="sc-et-fieldset" style="<?php echo esc_attr( $payment_fieldset_display ); ?>">
									<legend class="sc-et-legend"><?php esc_html_e( 'Payment Card', 'sugar-calendar-lite' ); ?></legend>
									<div class="sc-et-form-group">
										<div class="sc-et-input-group">
											<div id="sc-event-ticketing-card-element" class="sc-et-form-control">
												<!-- Elements will create input elements here -->
											</div>
										</div>
										<div id="sc-event-ticketing-card-errors" role="alert">
											<!-- We'll put the error messages in this element -->
										</div>
									</div>
								</fieldset>
							</div>

							<div class="sc-et-col-4" id="sc-event-ticketing-checkout-totals">

								<div class="sc-event-ticketing-checkout-totals__summary-block">
									<fieldset class="sc-et-fieldset">
										<legend class="sc-et-legend"><?php esc_html_e( 'Event Summary', 'sugar-calendar-lite' ); ?></legend>
										<p>
											<?php
											echo wp_kses(
												sprintf(
													/* translators: %s: Event name. */
													__( '<span>Event:</span> %s', 'sugar-calendar-lite' ),
													'<span><strong>' . $event->title . '</strong></span>'
												),
												[
													'strong' => [],
													'span' => [],
												]
											);
											?>
										</p>
										<?php
										render_event_date_time( $event );

										/**
										 * Fires after the event date time is rendered in the modal.
										 *
										 * @since 3.2.0
										 *
										 * @param Event $event The event object.
										 */
										do_action( 'sc_et_modal_event_summary_render_event_date_time_after', $event );
										?>
									</fieldset>
								</div>

								<div class="sc-event-ticketing-checkout-totals__summary-block">
									<fieldset class="sc-et-fieldset">
										<legend class="sc-et-legend"><?php esc_html_e( 'Order Summary', 'sugar-calendar-lite' ); ?></legend>
										<p>
											<?php
											echo wp_kses(
												sprintf(
													/* translators: %s: Tickets quantity. */
													__( '<span>Tickets:</span> %s', 'sugar-calendar-lite' ),
													'<strong><span id="sc-event-ticketing-quantity-span"></span></strong>'
												),
												[
													'strong' => [],
													'span' => [
														'id' => [],
													],
												]
											);
											?>
										</p>
										<p>
											<?php
											echo wp_kses(
												sprintf(
													/* translators: %s: Ticket Price. */
													__( '<span>Ticket Price:</span> <strong>%s</strong>', 'sugar-calendar-lite' ),
													Functions\currency_filter( $price )
												),
												[
													'strong' => [],
													'span' => [],
												]
											);
											?>
										</p>
										<p id="sc-event-ticketing-checkout-totals-total-wrap">
											<span><?php esc_html_e( 'Total:', 'sugar-calendar-lite' ); ?>&nbsp;</span>
											<strong>
												<span id="sc-event-ticketing-checkout-total">
													<span class="sc-et-spinner-border" role="status"><span class="sr-only"><?php esc_html_e( 'Loading...', 'sugar-calendar-lite' ); ?></span></span>
												</span>
											</strong>
										</p>
									</fieldset>
								</div>

							</div>
						</div>

						<div class="sc-et-row modal-footer">
							<div class="sc-et-spinner-border" role="status" style="display:none;"><span class="sr-only"><?php esc_html_e( 'Loading...', 'sugar-calendar-lite' ); ?></span></div>
							<button type="button" class="sc-et-btn sc-et-btn-secondary" id="sc-event-ticketing-cancel" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'sugar-calendar-lite' ); ?></button>
							<button type="button" class="sc-et-btn sc-et-btn-primary" id="sc-event-ticketing-purchase"><?php esc_html_e( 'Purchase', 'sugar-calendar-lite' ); ?></button>
							<input type="hidden" name="sc_et_event_id" id="sc_et_event_id" value="<?php echo absint( $event->id ); ?>" />
							<input type="hidden" name="sc_et_quantity" id="sc_et_quantity" value="1" />
							<input type="hidden" id="sc_et_ticket_price" value="<?php echo esc_attr( $price ); ?>" />
							<input type="hidden" name="sc_et_gateway" value="stripe" />
							<input type="hidden" name="sc_et_action" value="checkout" />
							<input type="hidden" id="sc_et_nonce" name="sc_et_nonce" value="<?php echo esc_attr( wp_create_nonce( Checkout::NONCE_KEY ) ); ?>" />
							<?php
							/**
							 * Fires before the closing </form> tag in the modal.
							 *
							 * @since 3.6.0
							 *
							 * @param Event $event The event object.
							 */
							do_action( 'sc_et_modal_form_bottom', $event );
							?>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php
}
