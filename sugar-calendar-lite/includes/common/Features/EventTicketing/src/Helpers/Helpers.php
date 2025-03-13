<?php

namespace Sugar_Calendar\AddOn\Ticketing\Helpers;

use DateTime;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Settings;

/**
 * Helper functions for the Event Ticketing feature.
 *
 * @since 3.1.0
 */
class Helpers {

	/**
	 * Get the remaining tickets for an event.
	 *
	 * @since 3.1.0
	 *
	 * @param \Sugar_Calendar\Event $event Event object.
	 *
	 * @return false|int Return `false` if tickets are not enabled in the event,
	 *                   otherwise returns the number of tickets available.
	 *                   `0` can also mean that the event was in the past.
	 */
	public static function get_event_remaining_tickets( $event ) {

		$enabled = get_event_meta( $event->id, 'tickets', true );

		if ( empty( $enabled ) ) {
			return false;
		}

		$timezone         = wp_timezone();
		$event_start_date = new DateTime( $event->start, $timezone );
		$date_today       = new DateTime( 'now', $timezone );

		if ( $date_today > $event_start_date ) {
			return 0;
		}

		return ( Functions\get_available_tickets( $event->id ) ) >= 1;
	}

	/**
	 * Get the WooCommerce event ticket link.
	 *
	 * @since 3.1.0
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 *
	 * @return string|false The WooCommerce event ticket link or `false` if not using WooCommerce.
	 */
	public static function get_woocommerce_event_ticket_link( $event ) {

		// Bail if not using Woo.
		if (
			! class_exists( 'WooCommerce' ) ||
			! get_event_meta( $event->ID, 'woocommerce_checkout', true )
		) {
			return false;
		}

		$product_id = Settings\get_setting( 'woocommerce_ticket_product' );

		if ( empty( $product_id ) ) {
			return false;
		}

		return add_query_arg(
			[
				'add-to-cart' => $product_id,
				'event_id'    => $event->ID,
				'quantity'    => 1,
			],
			wc_get_cart_url()
		);
	}

	/**
	 * Whether or not ticketing is enabled for an event.
	 *
	 * @since 3.6.0
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 *
	 * @return bool
	 */
	public static function is_event_ticketing_enabled( $event ) {

		/**
		 * Filter to determine if event ticketing is enabled.
		 *
		 * @since 3.6.0
		 *
		 * @param string                $enabled The tickets event meta value. `1` if enabled.
		 * @param \Sugar_Calendar\Event $event   The event object.
		 */
		return (bool) apply_filters(
			'sugar_calendar_add_on_ticketing_helpers_is_event_ticketing_enabled',
			get_event_meta( $event->id, 'tickets', true ),
			$event
		);
	}
}
