<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Event;
use function Sugar_Calendar\AddOn\Ticketing\Common\Assets\get_url;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Frontend Loader.
 *
 * @since 3.1.0
 */
class Loader {

	/**
	 * Init the frontend.
	 *
	 * @since 3.1.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Front-end related hooks.
	 *
	 * @since 3.1.0
	 */
	private function hooks() {

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
		add_action( 'sugar_calendar_frontend_event_details_before', [ $this, 'render_single_event_ticket_button' ], 5 );
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 3.1.0
	 */
	public function enqueue_frontend_scripts() {

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

		if ( ! Helpers::is_event_ticketing_enabled( $event ) ) {
			return;
		}

		wp_register_style(
			'sc-event-ticketing-frontend-single-event',
			get_url( 'css' ) . '/frontend/single-event' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_style( 'sc-event-ticketing-frontend-single-event' );
	}

	/**
	 * Render the event ticket button.
	 *
	 * @since 3.1.0
	 * @since 3.2.0 Added a check to determine if the tickets should be displayed.
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 */
	public function render_single_event_ticket_button( $event ) {

		if (
			! is_singular( sugar_calendar_get_event_post_type_id() ) ||
			empty( Helpers::get_event_remaining_tickets( $event ) ) ||
			! Functions\should_display_tickets( $event )
		) {
			return;
		}

		$price         = get_event_meta( $event->id, 'ticket_price', true );
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
		 *
		 * @param string $label The "Buy Now" label.
		 * @param Event  $event The event object.
		 */
		$buy_now_label = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_ticketing_frontend_render_single_event_ticket_button_label',
			$buy_now_label,
			$event
		);

		$svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">' .
		'<path d="M9.09375 7.75C10 8.03125 10.625 8.9375 10.625 9.90625C10.625 11.125 9.65625 12.125 8.5 12.125V12.625C8.5 12.9062 8.25 13.125 8 13.125H7.5C7.21875 13.125 7 12.9062 7 12.625V12.125C6.46875 12.125 5.96875 11.9375 5.53125 11.6562C5.28125 11.4688 5.21875 11.0938 5.46875 10.875L5.84375 10.5312C6 10.375 6.25 10.3438 6.46875 10.4688C6.65625 10.5938 6.84375 10.625 7.0625 10.625H8.46875C8.84375 10.625 9.125 10.3125 9.125 9.90625C9.125 9.5625 8.9375 9.28125 8.65625 9.1875L6.40625 8.5C5.5 8.25 4.875 7.34375 4.875 6.375C4.875 5.15625 5.8125 4.15625 7 4.125V3.625C7 3.375 7.21875 3.125 7.5 3.125H8C8.28125 3.125 8.5 3.375 8.5 3.625V4.15625C9 4.15625 9.5 4.3125 9.9375 4.625C10.2188 4.8125 10.25 5.1875 10 5.40625L9.625 5.75C9.46875 5.90625 9.21875 5.9375 9.03125 5.8125C8.84375 5.6875 8.625 5.625 8.40625 5.625H7C6.65625 5.625 6.34375 5.96875 6.34375 6.375C6.34375 6.6875 6.5625 7 6.84375 7.09375L9.09375 7.75ZM7.75 0.375C12.0312 0.375 15.5 3.84375 15.5 8.125C15.5 12.4062 12.0312 15.875 7.75 15.875C3.46875 15.875 0 12.4062 0 8.125C0 3.84375 3.46875 0.375 7.75 0.375ZM7.75 14.375C11.1875 14.375 14 11.5938 14 8.125C14 4.6875 11.1875 1.875 7.75 1.875C4.28125 1.875 1.5 4.6875 1.5 8.125C1.5 11.5938 4.28125 14.375 7.75 14.375Z" fill="currentColor"/>' .
		'</svg>';

		$woocommerce_link = Helpers::get_woocommerce_event_ticket_link( $event );
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
				echo wp_kses(
					sprintf(
						'<button data-toggle="modal" data-target="#sc-event-ticketing-modal" class="sugar_calendar_event_ticketing_frontend_single_event__buy_now">%1$s %2$s</button>',
						$svg,
						$buy_now_label
					),
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
			?>
		</div>
		<?php
	}
}
