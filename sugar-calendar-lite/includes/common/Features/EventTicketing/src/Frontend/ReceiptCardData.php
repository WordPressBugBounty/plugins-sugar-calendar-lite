<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Database\Attendee;
use Sugar_Calendar\AddOn\Ticketing\Database\Order;
use Sugar_Calendar\AddOn\Ticketing\Database\Ticket;
use Sugar_Calendar\AddOn\Ticketing\Settings;
use Sugar_Calendar\Event;
use Sugar_Calendar\Integrations\OnlineMeeting;

/**
 * The data the receipt card renders, and nothing else.
 *
 * Every lookup, filter and formatted string happens in from_order(), so
 * `ReceiptCard` only turns this object into markup and a preview can fill one
 * in by hand.
 *
 * @since 3.14.0
 */
final class ReceiptCardData {

	/**
	 * Outer wrapper classes.
	 *
	 * @since 3.14.0
	 *
	 * @var string[]
	 */
	public array $classes = [ 'sc-ticket-card', 'sc-ticket-card--receipt' ];

	/**
	 * Order status: paid, refunded or trash. Picks the header icon and title.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $status = '';

	/**
	 * Order id.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $order_id = '';

	/**
	 * Purchaser name.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $purchaser = '';

	/**
	 * Purchaser email.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $email = '';

	/**
	 * Formatted purchase date and time.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $purchase_date = '';

	/**
	 * Formatted amount paid, no markup.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $amount = '';

	/**
	 * Gateway transaction id.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $transaction_id = '';

	/**
	 * The event itself, or null when it could not be resolved.
	 *
	 * @since 3.14.0
	 *
	 * @var Event|null
	 */
	public ?Event $event = null;

	/**
	 * Event title.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $event_title = '';

	/**
	 * Event permalink.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $event_url = '';

	/**
	 * Formatted event start date.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $event_date = '';

	/**
	 * Formatted event start time.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $event_time = '';

	/**
	 * Whether the event has an online meeting. False hides the meeting row.
	 *
	 * @since 3.14.0
	 *
	 * @var bool
	 */
	public bool $has_meeting = false;

	/**
	 * Meeting provider display name, e.g. Zoom.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $meeting_provider_name = '';

	/**
	 * Meeting join link.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $meeting_join_url = '';

	/**
	 * Meeting password, or '' when none.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $meeting_password = '';

	/**
	 * One entry per ticket row, each in the shape of ticket_defaults().
	 *
	 * @since 3.14.0
	 *
	 * @var array[]
	 */
	public array $tickets = [];

	/**
	 * Whether to omit the per-ticket action links.
	 *
	 * @since 3.14.0
	 *
	 * @var bool
	 */
	public bool $read_only = false;

	/**
	 * Build the receipt card data for a live order.
	 *
	 * @since 3.14.0
	 *
	 * @param Order      $order     The order.
	 * @param Event|null $event     The order's event. Resolved from the order when null. A trashed
	 *                              or unpublished event is treated as missing.
	 * @param bool       $read_only Whether to omit the per-ticket action links.
	 *
	 * @return self
	 */
	public static function from_order( Order $order, ?Event $event = null, bool $read_only = false ): self {

		if ( $event === null ) {
			/** This filter is documented in includes/common/shortcodes.php */
			$event = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- public since 3.6.0; renaming would break site snippets.
				'sc_et_receipt_shortcode_event',
				sugar_calendar_get_event( $order->event_id ),
				$order
			);
		}

		$data = new self();

		$data->status         = (string) $order->status;
		$data->order_id       = (string) $order->id;
		$data->purchaser      = trim( $order->first_name . ' ' . $order->last_name );
		$data->email          = (string) $order->email;
		$data->purchase_date  = self::datetime( $order->date_created );
		$data->amount         = wp_strip_all_tags( Functions\display_price( $order->total ) );
		$data->transaction_id = (string) $order->transaction_id;
		$data->tickets        = self::tickets( $order );
		$data->read_only      = $read_only;

		// A deleted or unpublished event leaves the event line and meeting row out; the order still shows.
		if ( ! $event instanceof Event || ! is_post_publicly_viewable( $event->object_id ) ) {
			return $data;
		}

		/**
		 * Filters the classes on the receipt card's outer wrapper.
		 *
		 * @since 3.14.0
		 *
		 * @param string[] $classes Class names.
		 * @param Order    $order   The order.
		 * @param Event    $event   The order's event.
		 */
		$classes = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_receipt_card_class',
			[ 'sc-ticket-card', 'sc-ticket-card--receipt' ],
			$order,
			$event
		);

		/** This filter is documented in includes/common/shortcodes.php */
		$event_url = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- public since 3.6.0; renaming would break site snippets.
			'sc_et_receipt_shortcode_event_url',
			get_permalink( $event->object_id ),
			$event,
			$order
		);

		$data->classes     = (array) $classes;
		$data->event       = $event;
		$data->event_title = (string) $event->title;
		$data->event_url   = (string) $event_url;
		$data->event_date  = $event->format_date( sc_get_date_format(), $event->start );
		$data->event_time  = Functions\get_event_start_time( $event );

		$meeting = OnlineMeeting::for_event( $event );

		if ( $meeting !== null ) {
			$data->has_meeting           = true;
			$data->meeting_provider_name = (string) $meeting['provider_name'];
			$data->meeting_join_url      = (string) $meeting['join_url'];
			$data->meeting_password      = (string) $meeting['password'];
		}

		return $data;
	}

	/**
	 * Whether the event behind this receipt still exists and is publicly viewable.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function has_valid_event(): bool {

		return $this->event instanceof Event;
	}

	/**
	 * The empty shape of one entry in $tickets.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function ticket_defaults(): array {

		return [
			'id'          => '',
			'name'        => '',
			'email'       => '',
			'code'        => '',
			'print_url'   => '',
			'email_url'   => '',
			'email_nonce' => '',
			'details_url' => '',
		];
	}

	/**
	 * The ticket rows, with their attendees resolved in one query.
	 *
	 * @since 3.14.0
	 *
	 * @param Order $order The order.
	 *
	 * @return array[]
	 */
	private static function tickets( $order ) {

		// An unset or deleted page must not fall back to get_permalink()'s current-post default.
		$page         = (int) Settings\get_setting( 'ticket_page' );
		$details_page = $page > 0 ? (string) get_permalink( $page ) : '';

		/**
		 * Filters the tickets listed on the receipt card.
		 *
		 * @since 3.14.0
		 *
		 * @param Ticket[] $tickets The order's tickets.
		 * @param Order    $order   The order.
		 */
		$tickets = (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_receipt_card_tickets',
			Functions\get_order_tickets( $order->id ),
			$order
		);

		/**
		 * Filters the attendees keyed by id that the ticket rows read from.
		 *
		 * @since 3.14.0
		 *
		 * @param Attendee[] $attendees Attendees keyed by attendee id.
		 * @param Ticket[]   $tickets   The order's tickets.
		 * @param Order      $order     The order.
		 */
		$attendees = (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_receipt_card_attendees',
			self::attendee_map( $tickets ),
			$tickets,
			$order
		);

		$rows = [];

		foreach ( $tickets as $ticket ) {
			$rows[] = self::ticket( $ticket, $attendees[ (int) $ticket->attendee_id ] ?? false, $order, $details_page );
		}

		return $rows;
	}

	/**
	 * The order's attendees keyed by attendee id.
	 *
	 * Hydrated in one query, because a lookup per ticket is a SELECT per row.
	 *
	 * @since 3.14.0
	 *
	 * @param Ticket[] $tickets The order's tickets.
	 *
	 * @return array Attendees keyed by attendee id.
	 */
	private static function attendee_map( $tickets ) {

		$attendee_ids = array_filter( array_map( 'intval', wp_list_pluck( $tickets, 'attendee_id' ) ) );

		if ( empty( $attendee_ids ) ) {
			return [];
		}

		$rows = Functions\get_attendees(
			[
				'id__in' => $attendee_ids,
				'number' => count( $attendee_ids ),
			]
		);

		$attendees = [];

		foreach ( $rows as $row ) {
			$attendees[ (int) $row->id ] = $row;
		}

		return $attendees;
	}

	/**
	 * One ticket row.
	 *
	 * A refunded or canceled order keeps only Details: its tickets are no longer
	 * worth printing or emailing.
	 *
	 * @since 3.14.0
	 *
	 * @param Ticket         $ticket       The ticket.
	 * @param Attendee|false $attendee     The ticket's attendee, or false when it has none.
	 * @param Order          $order        The order.
	 * @param string         $details_page Permalink of the ticket details page, or '' when unset.
	 *
	 * @return array Keys: id, name, email, code, print_url, email_url, email_nonce, details_url.
	 *               The three urls are '' when that action is not offered.
	 */
	private static function ticket( $ticket, $attendee, $order, $details_page ) {

		$home      = home_url();
		$live      = ! in_array( $order->status, [ 'refunded', 'trash' ], true );
		$can_email = $live && ! empty( $attendee );

		$print_url   = '';
		$email_url   = '';
		$details_url = '';

		if ( $live ) {
			$print_url = wp_nonce_url(
				add_query_arg(
					[
						'sc_et_action' => 'print',
						'ticket_code'  => $ticket->code,
					],
					$home
				),
				$ticket->code
			);
		}

		if ( $can_email ) {
			$email_url = wp_nonce_url(
				add_query_arg(
					[
						'sc_et_action' => 'email_ticket',
						'ticket_code'  => $ticket->code,
					],
					$home
				),
				$ticket->code
			);
		}

		if ( $details_page !== '' ) {
			$details_url = add_query_arg(
				[
					'order_id'    => $order->id,
					'ticket_code' => $ticket->code,
				],
				$details_page
			);
		}

		return [
			'id'          => (string) $ticket->id,
			'name'        => empty( $attendee ) ? '' : trim( $attendee->first_name . ' ' . $attendee->last_name ),
			'email'       => empty( $attendee ) ? '' : (string) $attendee->email,
			'code'        => (string) $ticket->code,
			'print_url'   => $print_url,
			'email_url'   => $email_url,
			'email_nonce' => $can_email ? wp_create_nonce( $ticket->code ) : '',
			'details_url' => $details_url,
		];
	}

	/**
	 * Format a stored datetime the way the design shows it.
	 *
	 * @since 3.14.0
	 *
	 * @param string $datetime A UTC `Y-m-d H:i:s` string.
	 *
	 * @return string
	 */
	public static function datetime( $datetime ) {

		if ( empty( $datetime ) ) {
			return '';
		}

		// Order timestamps are stored in UTC, so convert before formatting.
		$datetime = get_date_from_gmt( $datetime );

		return sprintf(
			/* translators: %1$s - date, %2$s - time. */
			__( '%1$s at %2$s', 'sugar-calendar-lite' ),
			mysql2date( sc_get_date_format(), $datetime ),
			mysql2date( sc_get_time_format(), $datetime )
		);
	}
}
