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
		Assets\get_url( 'css' ) . "/frontend/bootstrap{$min}.css",
		[],
		SC_PLUGIN_VERSION
	);

	wp_register_style(
		'sc-et-general',
		Assets\get_url( 'css' ) . "/frontend/{$path}general{$min}.css",
		[],
		SC_PLUGIN_VERSION
	);

	wp_register_script(
		'sc-et-bootstrap',
		Assets\get_url( 'js' ) . "/frontend/bootstrap{$min}.js",
		[ 'jquery' ],
		SC_PLUGIN_VERSION,
		false
	);

	wp_register_script(
		'sc-et-popper',
		Assets\get_url( 'js' ) . "/frontend/popper{$min}.js",
		[ 'jquery' ],
		SC_PLUGIN_VERSION,
		false
	);

	wp_register_script(
		'sc-et-general',
		Assets\get_url( 'js' ) . "/frontend/general{$min}.js",
		[ 'jquery' ],
		SC_PLUGIN_VERSION,
		false
	);

	// Stripe.
	wp_register_script(
		'sc-event-ticketing-stripe',
		Assets\get_url( 'js' ) . "/frontend/stripe{$min}.js",
		[ 'jquery', 'sc-et-general' ],
		SC_PLUGIN_VERSION
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
 */
function enqueue() {

	// Bail if not Event or Receipt page
	if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) && ! is_page( Settings\get_setting( 'receipt_page' ) ) ) {
		return;
	}

	// Check if ticketing is enabled
	$event   = sugar_calendar_get_event_by_object( get_the_ID() );
	$enabled = get_event_meta( $event->id, 'tickets', true );

	// Bail if not enabled on this Event
	if ( is_singular( sugar_calendar_get_event_post_type_id() ) && empty( $enabled ) ) {
		return;
	}

	// Enqueue CSS
	wp_enqueue_style( 'sc-et-bootstrap' );
	wp_enqueue_style( 'sc-et-general' );

	// Timezone
	$tz    = wp_timezone();
	$start = new \DateTime( $event->start, $tz );
	$today = new \DateTime( 'now', $tz );

	// Do not load JS if the event's date has passed.
	if ( $today > $start ) {
		return;
	}

	// Do not load JS when not on single event page
	if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) ) {
		return;
	}

	// Enqueue JS
	wp_enqueue_script( 'sc-et-bootstrap' );
	wp_enqueue_script( 'sc-et-popper' );
	wp_enqueue_script( 'sc-et-general' );

	// Stripe
	wp_enqueue_script( 'sc-event-ticketing-stripe' );
	wp_enqueue_script( 'sandhills-stripe-js-v3' );
}

/**
 * Localize scripts
 *
 * @since 1.0.1
 */
function localize() {
	wp_localize_script(
		'sc-et-general',
		'sc_event_ticket_vars',
		array(
			'ajaxurl'           => admin_url( 'admin-ajax.php' ),
			'test_mode'         => Functions\is_sandbox(),
			'publishable_key'   => Functions\get_stripe_publishable_key(),
			'qty_limit_reached' => esc_html__( 'You have reached the maximum number of tickets available to be purchased. No more tickets are available.', 'sugar-calendar' )
		)
	);
}
