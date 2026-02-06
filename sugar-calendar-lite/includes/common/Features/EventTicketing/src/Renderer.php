<?php

namespace Sugar_Calendar\AddOn\Ticketing;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers as TicketingHelpers;
use DateTime;

use function Sugar_Calendar\AddOn\Ticketing\Frontend\Single\get_purchase_button;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\count_tickets;

/**
 * Class Renderer.
 *
 * Handles the frontend renders.
 *
 * @since 3.10.0
 */
class Renderer {

	/**
	 * Event object.
	 *
	 * @since 3.10.0
	 *
	 * @var \Sugar_Calendar\Event
	 */
	private $event;

	/**
	 * Post ID.
	 *
	 * @since 3.10.0
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Whether to enable the modal.
	 *
	 * @since 3.10.0
	 *
	 * @var bool
	 */
	public $should_enable_modal = true;

	/**
	 * Extra data.
	 *
	 * @since 3.10.0
	 *
	 * @var array
	 */
	private $extra_data = null;

	/**
	 * Constructor.
	 *
	 * @since 3.10.0
	 *
	 * @param \Sugar_Calendar\Event $event   Event object.
	 * @param int                   $post_id Post ID.
	 */
	public function __construct( $event, $post_id = 0 ) {

		if ( empty( $event ) ) {
			return;
		}

		$this->event = $event;

		if ( empty( $post_id ) ) {
			$this->post_id = $event->object_id;
		} else {
			$this->post_id = $post_id;
		}
	}

	/**
	 * Maybe render the buy now button.
	 *
	 * @since 3.10.0
	 */
	public function maybe_render_buy_now_button() {

		if ( ! $this->should_render_widget() ) {
			return;
		}

		$price         = get_event_meta( $this->event->id, 'ticket_price', true );
		$buy_now_label = sprintf(
			/*
			 * translators: %1$s is the price of the ticket.
			 */
			__( 'Buy Tickets - %1$s', 'sugar-calendar-lite' ),
			Functions\currency_filter( $price )
		);

		/**
		 * Filters the "Buy Now" text.
		 *
		 * @since 3.1.0
		 * @since 3.8.0 Added filter for the "Buy Now" button.
		 *
		 * @param string $label The "Buy Now" label.
		 * @param Event  $event The event object.
		 */
		$buy_now_label = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_ticketing_frontend_render_single_event_ticket_button_label',
			$buy_now_label,
			$this->event
		);

		$svg              = $this->get_buy_now_button_svg();
		$woocommerce_link = TicketingHelpers::get_woocommerce_event_ticket_link( $this->event );
		?>
		<div class="sugar_calendar_event_ticketing_frontend_single_event">
			<?php
			if ( ! empty( $woocommerce_link ) ) {
				echo wp_kses(
					sprintf(
						'<a href="%1$s" class="sugar_calendar_event_ticketing_frontend_single_event__buy_now--woocommerce">%2$s %3$s</a>',
						$woocommerce_link,
						$svg,
						$buy_now_label
					),
					[
						'a'    => [
							'href'  => [],
							'class' => [],
						],
						'svg'  => [
							'width'   => [],
							'height'  => [],
							'viewBox' => [],
							'fill'    => [],
							'xmlns'   => [],
						],
						'path' => [
							'd'    => [],
							'fill' => [],
						],
					]
				);
			} else {

				$this->render_buy_now_button( $buy_now_label );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Get the buy now button SVG.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	private function get_buy_now_button_svg() {

		return '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">' .
		'<path d="M9.09375 7.75C10 8.03125 10.625 8.9375 10.625 9.90625C10.625 11.125 9.65625 12.125 8.5 12.125V12.625C8.5 12.9062 8.25 13.125 8 13.125H7.5C7.21875 13.125 7 12.9062 7 12.625V12.125C6.46875 12.125 5.96875 11.9375 5.53125 11.6562C5.28125 11.4688 5.21875 11.0938 5.46875 10.875L5.84375 10.5312C6 10.375 6.25 10.3438 6.46875 10.4688C6.65625 10.5938 6.84375 10.625 7.0625 10.625H8.46875C8.84375 10.625 9.125 10.3125 9.125 9.90625C9.125 9.5625 8.9375 9.28125 8.65625 9.1875L6.40625 8.5C5.5 8.25 4.875 7.34375 4.875 6.375C4.875 5.15625 5.8125 4.15625 7 4.125V3.625C7 3.375 7.21875 3.125 7.5 3.125H8C8.28125 3.125 8.5 3.375 8.5 3.625V4.15625C9 4.15625 9.5 4.3125 9.9375 4.625C10.2188 4.8125 10.25 5.1875 10 5.40625L9.625 5.75C9.46875 5.90625 9.21875 5.9375 9.03125 5.8125C8.84375 5.6875 8.625 5.625 8.40625 5.625H7C6.65625 5.625 6.34375 5.96875 6.34375 6.375C6.34375 6.6875 6.5625 7 6.84375 7.09375L9.09375 7.75ZM7.75 0.375C12.0312 0.375 15.5 3.84375 15.5 8.125C15.5 12.4062 12.0312 15.875 7.75 15.875C3.46875 15.875 0 12.4062 0 8.125C0 3.84375 3.46875 0.375 7.75 0.375ZM7.75 14.375C11.1875 14.375 14 11.5938 14 8.125C14 4.6875 11.1875 1.875 7.75 1.875C4.28125 1.875 1.5 4.6875 1.5 8.125C1.5 11.5938 4.28125 14.375 7.75 14.375Z" fill="currentColor"/>' .
		'</svg>';
	}

	/**
	 * Render the buy now button placeholder.
	 *
	 * @since 3.10.0
	 */
	public function render_buy_now_button_placeholder() {

		if ( empty( $this->event ) || empty( $this->event->id ) ) {
			$buy_now_label = __( 'Buy Tickets', 'sugar-calendar-lite' );
		} else {
			$price         = get_event_meta( $this->event->id, 'ticket_price', true );
			$buy_now_label = sprintf(
				/*
				* translators: %1$s is the price of the ticket.
				*/
				__( 'Buy Tickets - %1$s', 'sugar-calendar-lite' ),
				Functions\currency_filter( $price )
			);
		}

		$this->render_buy_now_button( $buy_now_label );
	}

	/**
	 * Render the buy now button.
	 *
	 * @since 3.10.0
	 *
	 * @param string $buy_now_label Buy now button label.
	 */
	private function render_buy_now_button( $buy_now_label ) {

		$svg = $this->get_buy_now_button_svg();

		$modal_dom_attr = $this->should_enable_modal ? 'data-toggle="modal" data-target="#sc-event-ticketing-modal"' : '';

		/**
		 * Filter the template for the single event buy now button.
		 *
		 * @since 3.8.0
		 *
		 * @param string $btn_template_single_event_buy_now The template for the single event buy now button.
		 * @param Event  $event                             The event object.
		 * @param string $svg                               The SVG icon.
		 * @param string $buy_now_label                     The "Buy Now" label.
		 */
		$btn_single_event_buy_now = apply_filters(
			'sugar_calendar_add_on_ticketing_frontend_loader_single_event_buy_now_button_template',
			sprintf(
				'<button ' . $modal_dom_attr . ' class="sugar_calendar_event_ticketing_frontend_single_event__buy_now">%1$s %2$s</button>',
				$svg,
				$buy_now_label
			),
			$this->event,
			$svg,
			$buy_now_label
		);

		echo wp_kses(
			$btn_single_event_buy_now,
			[
				'button' => [
					'data-target' => [],
					'data-toggle' => [],
					'class'       => [],
				],
				'svg'    => [
					'width'   => [],
					'height'  => [],
					'viewBox' => [],
					'fill'    => [],
					'xmlns'   => [],
				],
				'path'   => [
					'd'    => [],
					'fill' => [],
				],
			]
		);
	}

	/**
	 * Check if the widget should be rendered.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	public function should_render_widget() {

		if (
			empty( $this->event ) ||
			empty( $this->event->id )
		) {
			return false;
		}

		// Bail if not enabled.
		if ( empty( TicketingHelpers::is_event_ticketing_enabled( $this->event ) ) ) {
			return false;
		}

		if ( ! Functions\should_display_tickets( $this->event ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Maybe render the ticket box.
	 *
	 * @since 3.10.0
	 */
	public function maybe_render_ticket_box() {

		if ( ! $this->should_render_widget() ) {
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
		$is_multiple_tickets = apply_filters( 'sc_et_is_multiple_tickets', false, $this->event, $this->post_id );

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
			do_action( 'sc_et_multiple_tickets_form', $this->event, $this->post_id );

			return;
		}

		$tz    = wp_timezone();
		$start = new DateTime( $this->event->start, $tz );
		$today = new DateTime( 'now', $tz );
		$price = get_event_meta( $this->event->id, 'ticket_price', true );

		// Check if capacity limitation is enabled.
		$limit_capacity = absint( get_event_meta( $this->event->id, 'ticket_limit_capacity', true ) );
		$available      = Functions\get_available_tickets( $this->event->id );

		$this->render_ticket_box(
			$start,
			$today,
			$price,
			$limit_capacity,
			$available
		);
	}

	/**
	 * Render the ticket box placeholder.
	 *
	 * @since 3.10.0
	 */
	public function render_ticket_box_placeholder() {

		// Set placeholder data.
		$tz                        = wp_timezone();
		$price                     = 10.00;
		$start                     = new DateTime( '+7 days', $tz );
		$limit_capacity            = 1;
		$available                 = 50;
		$today                     = new DateTime( 'now', $tz );
		$this->should_enable_modal = false;

		$this->render_ticket_box(
			$start,
			$today,
			$price,
			$limit_capacity,
			$available
		);
	}

	/**
	 * Render the ticket box.
	 *
	 * @since 3.10.0
	 */
	private function render_ticket_box(
		$start,
		$today,
		$price,
		$limit_capacity,
		$available
	) {

		/**
		 * Filter to determine if the limit capacity should be shown.
		 *
		 * @since 3.8.2
		 *
		 * @param bool $show_limit Whether the limit capacity should be shown.
		 * @param int  $event      The event object.
		 */
		$show_limit = apply_filters( 'sc_et_show_limit_capacity', boolval( $limit_capacity ), $this->event );

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
			do_action( 'sc_event_tickets_form_top', $this->event, $this->post_id );
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
										<?php echo wp_kses_post( get_purchase_button( $this->event, $this->should_enable_modal ) ); ?>
									<?php else : ?>
										<strong><?php esc_html_e( 'Sold Out', 'sugar-calendar-lite' ); ?></strong>
									<?php endif; ?>
								</div>
								<div class="sc-event-ticketing-qty-available">
									<?php
									if ( $show_limit ) {
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
			do_action( 'sc_event_tickets_form_bottom', $this->event, $this->post_id );
			?>
		</div>
		<?php
	}

	/**
	 * Get the extra data.
	 *
	 * @since 3.10.0
	 *
	 * @return array
	 */
	public function get_extra_data() {

		if ( ! is_null( $this->extra_data ) ) {
			return $this->extra_data;
		}

		$this->extra_data = [];

		if ( ! $this->should_render_widget() ) {
			return [];
		}

		// Get ticket limit capacity.
		$ticket_limit_capacity = absint( get_event_meta( $this->event->id, 'ticket_limit_capacity', true ) );
		$ticket_limit_capacity = $ticket_limit_capacity === 1;

		// Get ticket total.
		$event_data['ticket_total'] = $ticket_limit_capacity ? intval( get_event_meta( $this->event->id, 'ticket_quantity', true ) ) : 0;

		// Get tickets purchased.
		$event_data['tickets_purchased'] = max( 0, count_tickets( [ 'event_id' => $this->event->id ] ) );

		// Get ticket list url with event filter.
		$event_data['ticket_url'] = add_query_arg(
			[
				'page'     => 'sc-event-ticketing',
				'event_id' => $this->event->id,
			],
			get_admin_url( null, 'admin.php' )
		);

		$this->extra_data = $event_data;

		return $this->extra_data;
	}
}
