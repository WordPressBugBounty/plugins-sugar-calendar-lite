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
use Sugar_Calendar\AddOn\Ticketing\Settings as Settings;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\WP;

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
		'sc-event-ticketing-stripe',
		Assets\get_url( 'js' ) . "/frontend/stripe{$min}.js",
		[ 'jquery', 'sandhills-stripe-js-v3' ],
		Helpers::get_asset_version()
	);

	wp_register_script(
		'sandhills-stripe-js-v3',
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
 */
function enqueue() {

	// Bail if not Event or Receipt page.
	if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) && ! is_page( Settings\get_setting( 'receipt_page' ) ) ) {
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
		sugar_calendar_get_event_by_object( get_the_ID() )
	);

	$enabled = get_event_meta( $event->id, 'tickets', true );

	// Bail if not enabled on this Event.
	if ( is_singular( sugar_calendar_get_event_post_type_id() ) && empty( $enabled ) ) {
		return;
	}

	wp_enqueue_style( 'sc-et-bootstrap' );
	wp_enqueue_style( 'sc-et-general' );

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
	wp_enqueue_script( 'sandhills-stripe-js-v3' );

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

	// Bail if not Event or Receipt page.
	if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) && ! is_page( Settings\get_setting( 'receipt_page' ) ) ) {
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
		sugar_calendar_get_event_by_object( get_the_ID() )
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
