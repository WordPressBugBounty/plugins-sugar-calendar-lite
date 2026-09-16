<?php

namespace Sugar_Calendar\SmartTags\Ticketing;

/**
 * The Smart Tags Event Ticketing ships with.
 *
 * Also the authority for which callbacks count as native, so a renamed or
 * replaced callback cannot silently promote a third-party tag into the
 * positions that issue a live receipt credential. Built directly from the
 * literal list, never through `CallbackSmartTag::from_row()`, so the authority
 * never depends on whether a function happened to be loaded at build time.
 *
 * @since 3.14.0
 */
class DefaultTags {

	/**
	 * The registry scope Event Ticketing's senders read.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SCOPE = 'ticketing';

	/**
	 * Every built-in ticketing tag, in the released order.
	 *
	 * @since 3.14.0
	 *
	 * @return CallbackSmartTag[]
	 */
	public static function all(): array {

		return [
			new CallbackSmartTag( 'name', esc_html__( 'Customer Name', 'sugar-calendar-lite' ), 'order', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_name' ),
			new CallbackSmartTag( 'email', esc_html__( 'Customer Email', 'sugar-calendar-lite' ), 'order', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_email' ),
			new CallbackSmartTag( 'order_id', esc_html__( 'Order ID', 'sugar-calendar-lite' ), 'order', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_order_id' ),
			new CallbackSmartTag( 'order_amount', esc_html__( 'Order Total', 'sugar-calendar-lite' ), 'order', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_order_amount' ),
			new CallbackSmartTag( 'order_date', esc_html__( 'Order Date', 'sugar-calendar-lite' ), 'order', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_order_date' ),
			new CallbackSmartTag( 'receipt_url', esc_html__( 'Receipt URL', 'sugar-calendar-lite' ), 'order', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_receipt_url' ),
			new CallbackSmartTag( 'tickets', esc_html__( 'Ticket List', 'sugar-calendar-lite' ), 'order', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_tickets' ),
			new CallbackSmartTag( 'event_id', esc_html__( 'Event ID', 'sugar-calendar-lite' ), 'event', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_id' ),
			new CallbackSmartTag( 'event_title', esc_html__( 'Event Title', 'sugar-calendar-lite' ), 'event', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_title' ),
			new CallbackSmartTag( 'event_url', esc_html__( 'Event URL', 'sugar-calendar-lite' ), 'event', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_url' ),
			new CallbackSmartTag( 'event_date', esc_html__( 'Event Date', 'sugar-calendar-lite' ), 'event', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_date' ),
			new CallbackSmartTag( 'event_start_time', esc_html__( 'Event Start Time', 'sugar-calendar-lite' ), 'event', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_start_time' ),
			new CallbackSmartTag( 'event_end_time', esc_html__( 'Event End Time', 'sugar-calendar-lite' ), 'event', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_end_time' ),
			new CallbackSmartTag( 'ticket_id', esc_html__( 'Ticket ID', 'sugar-calendar-lite' ), 'ticket', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_ticket_id' ),
			new CallbackSmartTag( 'ticket_code', esc_html__( 'Ticket Code', 'sugar-calendar-lite' ), 'ticket', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_ticket_code' ),
			new CallbackSmartTag( 'ticket_url', esc_html__( 'Ticket URL', 'sugar-calendar-lite' ), 'ticket', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_ticket_url' ),
			new CallbackSmartTag( 'attendee_name', esc_html__( 'Attendee Name', 'sugar-calendar-lite' ), 'attendee', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_attendee_name' ),
			new CallbackSmartTag( 'attendee_email', esc_html__( 'Attendee Email', 'sugar-calendar-lite' ), 'attendee', '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_attendee_email' ),
		];
	}

	/**
	 * Whether a callback is the built-in callback for a slug.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag      Slug.
	 * @param mixed  $callback Selected callback.
	 *
	 * @return bool
	 */
	public static function is_default_callback( string $tag, $callback ): bool {

		foreach ( self::all() as $default ) {
			if ( $default->tag() === $tag ) {
				return $default->callback() === $callback;
			}
		}

		return false;
	}
}
