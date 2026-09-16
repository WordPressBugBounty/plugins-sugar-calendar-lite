<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

use Elementor\Plugin as ElementorPlugin;
use Sugar_Calendar\AddOn\Ticketing\Database\Attendee;
use Sugar_Calendar\AddOn\Ticketing\Database\Order;
use Sugar_Calendar\AddOn\Ticketing\Database\Ticket;
use Sugar_Calendar\AddOn\Ticketing\Settings;
use Sugar_Calendar\Event;

use Sugar_Calendar\Features\RegistrationForm\RespondentNaming;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\display_price;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_event_start_time;
use function Sugar_Calendar\AddOn\Ticketing\Shortcodes\is_legacy_details;
use function Sugar_Calendar\AddOn\Ticketing\Shortcodes\is_legacy_receipt;
use function Sugar_Calendar\AddOn\Ticketing\Shortcodes\render_legacy_receipt_body;
use function Sugar_Calendar\AddOn\Ticketing\Shortcodes\render_legacy_ticket_body;
use function Sugar_Calendar\AddOn\Ticketing\Shortcodes\render_receipt_card;
use function Sugar_Calendar\AddOn\Ticketing\Shortcodes\render_ticket_card;

/**
 * A sample card for a page builder's editor.
 *
 * The two ticket pages hold nothing but a shortcode, and a builder renders that
 * shortcode with no order in the URL, so the editor canvas showed the
 * invalid-link card and there was nothing to lay out against. Here the cards
 * get a sample data object instead, so no lookup runs. The legacy tables still
 * run their own lookups, so for those the two public filters they call are
 * answered with a fabricated order, ticket and attendee for the duration of
 * the render.
 *
 * @since 3.14.0
 */
class EditorPreview {

	/**
	 * Query arg that asks for the sample card.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const REQUEST_ARG = 'sc_preview';

	/**
	 * Nonce action for REQUEST_ARG.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'sc_ticket_page_preview';

	/**
	 * Query arg carrying the settings on screen, keyed by setting name.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SETTINGS_ARG = 'sc_preview_settings';

	/**
	 * Register the hooks the preview needs before anything renders.
	 *
	 * Gated on the request actually carrying preview settings: any callback on
	 * `sc_et_get_setting` makes Settings\get_email_copy_resolution() treat every
	 * email-copy default as third-party owned, which reverts the Modern email
	 * defaults to their pre-#517 copy site-wide.
	 *
	 * @since 3.14.0
	 */
	public static function hooks() {

		if ( ! self::has_preview_settings() ) {
			return;
		}

		add_filter( 'sc_et_get_setting', [ self::class, 'filter_preview_setting' ], 10, 3 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Whether this request carries the Preview link's settings.
	 *
	 * Mirrors the first guard in `filter_preview_setting()`, which keeps its own
	 * copy so the sniffs can see the `$_GET` index is checked.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	private static function has_preview_settings() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only; the values are nonce-gated in filter_preview_setting().
		return ! empty( $_GET[ self::SETTINGS_ARG ] ) && is_array( $_GET[ self::SETTINGS_ARG ] );
	}

	/**
	 * Let the Preview link's own values win over what is stored.
	 *
	 * Without this, changing a colour and hitting Preview shows the saved colour,
	 * which is worse than no preview: it looks right and is not.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed  $value   Stored value, or the caller's default.
	 * @param string $key     Setting key.
	 * @param mixed  $default The caller's default.
	 *
	 * @return mixed
	 */
	public static function filter_preview_setting( $value, $key, $default ) {

		if ( empty( $_GET[ self::SETTINGS_ARG ] ) || ! is_array( $_GET[ self::SETTINGS_ARG ] ) ) {
			return $value;
		}

		$overrides = wp_unslash( $_GET[ self::SETTINGS_ARG ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key below.

		if ( ! isset( $overrides[ $key ] ) ) {
			return $value;
		}

		// Derived from the fields array, so a fifth colour needs no edit here.
		$colors = Settings\get_color_scheme_defaults();

		if ( isset( $colors[ $key ] ) ) {

			if ( ! self::is_preview_request() ) {
				return $value;
			}

			$color = sanitize_hex_color( (string) $overrides[ $key ] );

			return empty( $color ) ? $value : $color;
		}

		if ( $key === 'use_legacy_template' ) {
			return self::is_preview_request() ? (bool) (int) $overrides[ $key ] : $value;
		}

		return $value;
	}

	/**
	 * Whether this is the Settings screen's Preview link, from a user who may use it.
	 *
	 * The sample card carries no real order, so the gate is about who can preview an
	 * unconfigured page, not about protecting customer data.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public static function is_preview_request() {

		if ( empty( $_GET[ self::REQUEST_ARG ] ) ) {
			return false;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Whether a page builder is rendering its editor.
	 *
	 * Elementor runs the shortcode in both the `post.php` editor request and the
	 * preview iframe, and it is the editor request whose markup the canvas
	 * actually shows (the iframe ships an empty wrapper the editor's JS fills),
	 * so both modes have to be claimed.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public static function is_active() {

		if ( self::is_preview_request() ) {
			return true;
		}

		if ( ! class_exists( ElementorPlugin::class ) || empty( ElementorPlugin::$instance ) ) {
			return false;
		}

		$elementor = ElementorPlugin::$instance;

		// The pair Elementor\Widgets\AbstractEventWidget::is_in_editor_mode() reads.
		$active = $elementor->editor->is_edit_mode() || $elementor->preview->is_preview_mode();

		/**
		 * Filters whether the ticket shortcodes render a sample card.
		 *
		 * Another builder that renders shortcodes in its editor can return true
		 * here rather than showing the invalid-link card.
		 *
		 * @since 3.14.0
		 *
		 * @param bool $active Whether a builder editor is rendering this request.
		 */
		return (bool) apply_filters( 'sugar_calendar_ticketing_editor_preview', $active ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * The sample receipt.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public static function receipt() {

		$order = self::order();
		$event = self::event();

		if ( is_legacy_receipt( $order, $event ) ) {
			$html = self::with_legacy_filters(
				static function () use ( $order, $event ) {

					return render_legacy_receipt_body( $order, $event );
				}
			);
		} else {
			$html = render_receipt_card( self::receipt_data( $order, $event ), $order, $event );
		}

		return self::inert_links( $html );
	}

	/**
	 * The sample ticket details.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public static function details() {

		$order  = self::order();
		$ticket = self::ticket();
		$event  = self::event();

		if ( is_legacy_details( $ticket, $event ) ) {
			$html = self::with_legacy_filters(
				static function () use ( $order, $ticket, $event ) {

					return render_legacy_ticket_body( $order, $ticket, $event );
				}
			);
		} else {
			$html = render_ticket_card( self::details_data( $order, $ticket, $event ) );
		}

		return self::inert_links( $html );
	}

	/**
	 * Point every link in a sample card at nothing.
	 *
	 * The ids in a sample are fabricated, so the real hrefs lead somewhere useless
	 * or misleading: printing a ticket that does not exist, mailing it, downloading
	 * an empty calendar entry, or — where a permalink falls back to the current
	 * page — silently reloading the preview. Done to the finished markup rather
	 * than at each render site, which keeps the real cards untouched and covers the
	 * Pro Add to Calendar links too.
	 *
	 * `target` goes with the href: pointing a new tab at `#` opens a blank copy of
	 * the page, which is worse than doing nothing.
	 *
	 * @since 3.14.0
	 *
	 * @param string $html Rendered card.
	 *
	 * @return string
	 */
	private static function inert_links( $html ) {

		$html = preg_replace( '/\shref="[^"]*"/', ' href="#"', $html );

		return preg_replace( '/\starget="[^"]*"/', '', $html );
	}

	/**
	 * Sample receipt card data.
	 *
	 * Links are placeholders: inert_links() blanks them anyway.
	 *
	 * @since 3.14.0
	 *
	 * @param Order $order The sample order.
	 * @param Event $event The sample event.
	 *
	 * @return ReceiptCardData
	 */
	private static function receipt_data( $order, $event ) {

		$attendee = self::attendee();
		$ticket   = self::ticket();

		$data = new ReceiptCardData();

		$data->status         = (string) $order->status;
		$data->order_id       = (string) $order->id;
		$data->purchaser      = trim( $order->first_name . ' ' . $order->last_name );
		$data->email          = (string) $order->email;
		$data->purchase_date  = ReceiptCardData::datetime( $order->date_created );
		$data->amount         = wp_strip_all_tags( display_price( $order->total ) );
		$data->transaction_id = (string) $order->transaction_id;
		$data->event          = $event;
		$data->event_title    = (string) $event->title;
		$data->event_url      = '#';
		$data->event_date     = $event->format_date( sc_get_date_format(), $event->start );
		$data->event_time     = get_event_start_time( $event );
		$data->tickets        = [
			[
				'id'          => (string) $ticket->id,
				'name'        => trim( $attendee->first_name . ' ' . $attendee->last_name ),
				'email'       => (string) $attendee->email,
				'code'        => (string) $ticket->code,
				'print_url'   => '#',
				'email_url'   => '#',
				'details_url' => '#',
			],
		];

		return $data;
	}

	/**
	 * Sample details card data.
	 *
	 * The sample event has no post behind it, so the image is a bundled one, and
	 * no form, so the questions and answers are spelled out here.
	 *
	 * @since 3.14.0
	 *
	 * @param Order  $order  The sample order.
	 * @param Ticket $ticket The sample ticket.
	 * @param Event  $event  The sample event.
	 *
	 * @return DetailsCardData
	 */
	private static function details_data( $order, $ticket, $event ) {

		$attendee = self::attendee();
		$answer   = __( 'Sample answer', 'sugar-calendar-lite' );

		$data = new DetailsCardData();

		$data->image            = SC_PLUGIN_ASSETS_URL . 'images/emails/preview-featured-image.png';
		$data->event_title      = (string) $event->title;
		$data->event_month      = $event->format_date( 'M', $event->start );
		$data->event_day        = $event->format_date( 'j', $event->start );
		$data->event_time       = get_event_start_time( $event );
		$data->event_url        = '#';
		$data->location_address = (string) $event->location;
		$data->event            = $event;
		$data->attendee_name    = trim( $attendee->first_name . ' ' . $attendee->last_name );
		$data->ticket_type      = RespondentNaming::general_admission( 0 );
		$data->code             = (string) $ticket->code;
		$data->cost             = wp_strip_all_tags( display_price( (float) $order->total ) );
		$data->registration     = [
			[
				'label'  => __( 'Sample question 1', 'sugar-calendar-lite' ),
				'answer' => $answer,
			],
			[
				'label'  => __( 'Sample question 2', 'sugar-calendar-lite' ),
				'answer' => $answer,
			],
			[
				'label'  => __( 'Sample question 3', 'sugar-calendar-lite' ),
				'answer' => $answer,
			],
		];

		return $data;
	}

	/**
	 * Run a legacy table renderer with its two lookups answered by the sample rows.
	 *
	 * The legacy tables call the public filters themselves; the callbacks only
	 * answer for the sample order and ticket, and are removed however the render ends.
	 *
	 * @since 3.14.0
	 *
	 * @param callable $render Returns the rendered HTML.
	 *
	 * @return string
	 */
	private static function with_legacy_filters( callable $render ) {

		add_filter( 'sugar_calendar_ticketing_receipt_card_tickets', [ self::class, 'filter_tickets' ], 10, 2 );
		add_filter( 'sugar_calendar_ticketing_details_card_attendee', [ self::class, 'filter_attendee' ], 10, 2 );

		try {
			return (string) $render();
		} finally {
			remove_filter( 'sugar_calendar_ticketing_receipt_card_tickets', [ self::class, 'filter_tickets' ] );
			remove_filter( 'sugar_calendar_ticketing_details_card_attendee', [ self::class, 'filter_attendee' ] );
		}
	}

	/**
	 * The legacy receipt's ticket list, for the sample order only.
	 *
	 * @since 3.14.0
	 *
	 * @param Ticket[] $tickets The looked-up tickets.
	 * @param Order    $order   The order being rendered.
	 *
	 * @return Ticket[]
	 */
	public static function filter_tickets( $tickets, $order ) {

		if ( (int) $order->id !== self::order()->id ) {
			return $tickets;
		}

		return [ self::ticket() ];
	}

	/**
	 * The legacy tables' attendee, for the sample ticket only.
	 *
	 * @since 3.14.0
	 *
	 * @param Attendee|false $attendee The looked-up attendee.
	 * @param Ticket         $ticket   The ticket being rendered.
	 *
	 * @return Attendee|false
	 */
	public static function filter_attendee( $attendee, $ticket ) {

		if ( (int) $ticket->id !== self::ticket()->id ) {
			return $attendee;
		}

		return self::attendee();
	}

	/**
	 * The sample event.
	 *
	 * @since 3.14.0
	 *
	 * @return Event
	 */
	private static function event() {

		$start = gmdate( 'Y-m-d H:i:s', strtotime( '+14 days 19:00' ) );

		return new Event(
			[
				'id'        => 0,
				'object_id' => 0,
				'title'     => __( 'Sample Event', 'sugar-calendar-lite' ),
				'status'    => 'publish',
				'start'     => $start,
				'end'       => $start,
				'location'  => __( '123 Sample Street, Sampletown', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * The sample order.
	 *
	 * @since 3.14.0
	 *
	 * @return Order
	 */
	private static function order() {

		return new Order(
			[
				'id'             => 1234,
				'event_id'       => 0,
				'status'         => 'paid',
				'first_name'     => 'Jane',
				'last_name'      => 'Doe',
				'email'          => 'jane@example.com',
				'total'          => 25.00,
				'transaction_id' => 'sample-transaction-id',
				'date_created'   => gmdate( 'Y-m-d H:i:s' ),
			]
		);
	}

	/**
	 * The sample ticket.
	 *
	 * @since 3.14.0
	 *
	 * @return Ticket
	 */
	private static function ticket() {

		return new Ticket(
			[
				'id'             => 4321,
				'order_id'       => 1234,
				'event_id'       => 0,
				'ticket_type_id' => 0,
				'attendee_id'    => 8765,
				'code'           => 'SAMPLE-TICKET-CODE',
			]
		);
	}

	/**
	 * The sample attendee.
	 *
	 * @since 3.14.0
	 *
	 * @return Attendee
	 */
	private static function attendee() {

		return new Attendee(
			[
				'id'         => 8765,
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
				'email'      => 'jane@example.com',
			]
		);
	}
}
