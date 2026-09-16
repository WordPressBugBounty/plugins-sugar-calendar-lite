<?php
/**
 * Event Ticketing Front-end Assets
 *
 * @package Plugins/Site/Events/FrontEnd/Assets
 */
namespace Sugar_Calendar\AddOn\Ticketing\Frontend\Assets;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
use Sugar_Calendar\AddOn\Ticketing\Common\Assets as Assets;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\AddOn\Ticketing\Settings as Settings;
use Sugar_Calendar\AddOn\Ticketing\Frontend\EditorPreview;
use Sugar_Calendar\AddOn\Ticketing\Frontend\TicketPageTemplate;
use Sugar_Calendar\AddOn\Ticketing\Gateways\Checkout;
use Sugar_Calendar\Event;

/**
 * Whether this request renders one of the ticket shortcodes.
 *
 * Keyed on the shortcode too, not only the two settings: the shortcode renders
 * wherever it is placed, so a page holding it before the setting points at it
 * would otherwise draw an unstyled card.
 *
 * @since 3.14.0
 *
 * @return bool
 */
function is_ticket_or_receipt_page() {

	if ( TicketPageTemplate::is_receipt_page() || TicketPageTemplate::is_ticket_page() ) {
		return true;
	}

	if ( ! is_singular() ) {
		return false;
	}

	$content = get_post_field( 'post_content', get_queried_object_id() );

	return has_shortcode( (string) $content, 'sc_event_tickets_receipt' )
		|| has_shortcode( (string) $content, 'sc_event_tickets_details' );
}

/**
 * Whether this request renders the receipt shortcode.
 *
 * Narrower than is_ticket_or_receipt_page(): only the receipt lists tickets, so
 * only the receipt carries an Email Ticket link.
 *
 * @since 3.14.0
 *
 * @return bool
 */
function is_receipt_page() {

	if ( TicketPageTemplate::is_receipt_page() ) {
		return true;
	}

	if ( ! is_singular() ) {
		return false;
	}

	return has_shortcode(
		(string) get_post_field( 'post_content', get_queried_object_id() ),
		'sc_event_tickets_receipt'
	);
}

/**
 * The event this request is about, or an empty Event on the ticket pages.
 *
 * Those two are ordinary WP pages, so the object lookup can only ever miss.
 * Skipping it hands the filters below the same empty Event they already got.
 *
 * @since 3.14.0
 *
 * @return Event
 */
function get_request_event() {

	// An event keeps its own checkout even when its body also holds a ticket shortcode.
	if ( is_singular( sugar_calendar_get_event_post_type_id() ) ) {
		return sugar_calendar_get_event_by_object( get_the_ID() );
	}

	return is_ticket_or_receipt_page()
		? new Event()
		: sugar_calendar_get_event_by_object( get_the_ID() );
}

/**
 * Register front-end assets.
 *
 * @since 1.0.1
 */
function register() {

	$path = Assets\get_css_path();
	$min  = WP::asset_min();

	wp_register_style(
		'sc-et-bootstrap',
		Assets\get_url( 'css' ) . "/frontend/et-bootstrap{$min}.css",
		[],
		Helpers::get_asset_version()
	);

	wp_register_style(
		'sc-et-general',
		Assets\get_url( 'css' ) . "/frontend/{$path}general{$min}.css",
		[],
		Helpers::get_asset_version()
	);

	wp_register_script(
		'sc-et-bootstrap',
		Assets\get_url( 'js' ) . "/frontend/bootstrap{$min}.js",
		[ 'jquery' ],
		Helpers::get_asset_version(),
		false
	);

	wp_register_script(
		'sc-et-popper',
		Assets\get_url( 'js' ) . "/frontend/popper{$min}.js",
		[ 'jquery' ],
		Helpers::get_asset_version(),
		false
	);

	wp_register_script(
		'sc-et-general',
		Assets\get_url( 'js' ) . "/frontend/general{$min}.js",
		[ 'jquery' ],
		Helpers::get_asset_version(),
		false
	);

	// Stripe.
	wp_register_script(
		'sc-et-receipt-email',
		Assets\get_url( 'js' ) . "/frontend/receipt-email{$min}.js",
		[ 'jquery' ],
		Helpers::get_asset_version(),
		true
	);

	wp_register_script(
		'sc-event-ticketing-stripe',
		Assets\get_url( 'js' ) . "/frontend/stripe{$min}.js",
		[ 'jquery', 'sugarcalendar-stripe-js-v3' ],
		Helpers::get_asset_version()
	);

	wp_register_script(
		'sugarcalendar-stripe-js-v3',
		'https://js.stripe.com/v3/',
		[],
		SC_PLUGIN_VERSION,
		false
	);
}

/**
 * Enqueue front-end assets.
 *
 * @since 1.0.0
 * @since 3.6.0 Added localized variables.
 * @since 3.8.0 Added script handle filter.
 * @since 3.14.0 Load the frontend Tailwind bundle on the receipt page.
 */
function enqueue() {

	// Bail if not an Event, Receipt or Ticket page.
	if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) && ! is_ticket_or_receipt_page() ) {
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
		'sugar_calendar_addon_ticketing_enqueue_event_object',
		get_request_event()
	);

	$enabled = get_event_meta( $event->id, 'tickets', true );

	// Bail if not enabled on this Event.
	if ( is_singular( sugar_calendar_get_event_post_type_id() ) && empty( $enabled ) ) {
		return;
	}

	wp_enqueue_style( 'sc-et-bootstrap' );
	wp_enqueue_style( 'sc-et-general' );

	// Both ticket cards are built from the frontend Tailwind utilities; the legacy
	// tables use none of them, so a legacy site must not pay for the stylesheet.
	if ( is_ticket_or_receipt_page() && ! Settings\use_legacy_template() ) {
		wp_enqueue_style( 'sugar-calendar-frontend-tailwind' );
	}

	// Enqueued before the date check below, which bails on the ticket pages: they
	// are ordinary pages, so the Event they resolve to is empty and reads as past.
	//
	// Not on a sample card: its ticket code is fabricated, so a click would post a
	// code the endpoint cannot find and turn the link red for no reason.
	if ( is_receipt_page() && ! EditorPreview::is_active() ) {

		wp_enqueue_script( 'sc-et-receipt-email' );

		wp_localize_script(
			'sc-et-receipt-email',
			'sc_et_receipt_email',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'sending' => esc_html__( 'Sending', 'sugar-calendar-lite' ),
				'sent'    => esc_html__( 'Email Sent', 'sugar-calendar-lite' ),
				'failed'  => esc_html__( 'Failed. Retry?', 'sugar-calendar-lite' ),

				/* translators: %s - seconds remaining, e.g. 09s. */
				'retry'   => esc_html__( 'Try again in %s', 'sugar-calendar-lite' ),
			]
		);
	}

	$tz    = wp_timezone();
	$start = new \DateTime( $event->start, $tz );
	$today = new \DateTime( 'now', $tz );

	// Do not load JS if the event's date has passed.
	if ( $today > $start ) {
		return;
	}

	wp_enqueue_script( 'sc-et-bootstrap' );
	wp_enqueue_script( 'sc-et-popper' );

	/**
	 * Filter the script handle to use for event ticketing.
	 *
	 * @since 3.8.0
	 *
	 * @param string $handle  The script handle.
	 * @param Event  $event   The event object.
	 */
	$script_handle = apply_filters( 'sc_et_frontend_script_handle', 'sc-et-general', $event );

	wp_enqueue_script( $script_handle );

	/**
	 * Filter the script handle to use for event ticketing Stripe.
	 *
	 * @since 3.8.0
	 *
	 * @param string $handle  The script handle.
	 * @param Event  $event   The event object.
	 */
	$stripe_script_handle = apply_filters( 'sc_et_frontend_stripe_script_handle', 'sc-event-ticketing-stripe', $event );

	wp_enqueue_script( $stripe_script_handle );
	wp_enqueue_script( 'sugarcalendar-stripe-js-v3' );

	wp_localize_script(
		$stripe_script_handle,
		'sc_event_ticket_stripe_vars',
		/**
		 * Filter the Stripe variables to be localized.
		 *
		 * @since 3.6.0
		 *
		 * @param array $vars Variables to be available in the frontend.
		 */
		apply_filters(
			'sc_et_stripe_vars',
			[
				'currency'   => Functions\get_currency(),
				'min_charge' => 100,
			]
		)
	);
}

/**
 * Localize scripts.
 *
 * @since 1.0.1
 * @since 3.8.0 Added script handle filter.
 */
function localize() {

	// Bail if not an Event, Receipt or Ticket page.
	if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) && ! is_ticket_or_receipt_page() ) {
		return;
	}

	/**
	 * Filter the Event object to be used in the enqueueing of the Event Ticketing assets.
	 *
	 * @since 3.8.0
	 *
	 * @param \Sugar_Calendar\Event $event The Event object.
	 */
	$event = apply_filters(
		'sugar_calendar_addon_ticketing_enqueue_event_object',
		get_request_event()
	);

	/**
	 * Filter the script handle to use for event ticketing.
	 *
	 * @since 3.8.0
	 *
	 * @param string $handle  The script handle.
	 * @param Event  $event   The event object.
	 */
	$script_handle = apply_filters( 'sc_et_frontend_script_handle', 'sc-et-general', $event );

	/**
	 * Filter the sc_event_ticket_vars variables to be localized.
	 *
	 * @since 3.8.0
	 *
	 * @param array  $vars          Variables to be available in the frontend.
	 * @param Event  $event         The event object.
	 * @param string $script_handle The script handle.
	 */
	$sc_event_ticket_vars = apply_filters(
		'sc_et_frontend_script_vars',
		[
			'ajaxurl'           => admin_url( 'admin-ajax.php' ),
			'test_mode'         => Functions\is_sandbox(),
			'publishable_key'   => Functions\get_stripe_publishable_key(),
			'qty_limit_reached' => esc_html__( 'You have reached the maximum number of tickets available to be purchased. No more tickets are available.', 'sugar-calendar-lite' ),
			'max_name_length'   => Checkout::MAX_NAME_LENGTH,
			'max_email_length'  => Checkout::MAX_EMAIL_LENGTH,
			'name_too_long'     => sprintf(
				/* translators: %1$d - maximum number of characters. */
				esc_html__( 'Please use %1$d characters or fewer for the name.', 'sugar-calendar-lite' ),
				Checkout::MAX_NAME_LENGTH
			),
			'email_too_long'    => sprintf(
				/* translators: %1$d - maximum number of characters. */
				esc_html__( 'Please use an email address of %1$d characters or fewer.', 'sugar-calendar-lite' ),
				Checkout::MAX_EMAIL_LENGTH
			),
		],
		$event,
		$script_handle
	);

	wp_localize_script(
		$script_handle,
		'sc_event_ticket_vars',
		$sc_event_ticket_vars
	);
}
