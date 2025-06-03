<?php
/**
 * Event Ticketing Admin Assets
 *
 * @package Plugins/Site/Events/Admin/Assets
 */

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Assets;

defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Assets;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Register assets.
 *
 * @since 3.1.0
 */
function register() {

	$path = Assets\get_css_path();

	wp_register_script(
		'sc-et-general',
		Assets\get_url( 'js' ) . '/admin' . WP::asset_min() . '.js',
		[ 'jquery' ],
		BaseHelpers::get_asset_version()
	);

	wp_register_style(
		'sc-event-ticketing',
		Assets\get_url( 'css' ) . "/{$path}general.css",
		[],
		BaseHelpers::get_asset_version()
	);
}

/**
 * Enqueue assets.
 *
 * @since 3.1.0
 */
function enqueue() {

	wp_enqueue_style( 'sc-event-ticketing' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['page'] ) && ( $_GET['page'] === 'sc-event-ticketing' ) ) {

		wp_enqueue_script( 'sc-et-general' );
	}

	if (
		sugar_calendar()->get_admin()->is_page( 'settings_payments' )
		|| sugar_calendar()->get_admin()->is_page( 'settings_emails' )
	) {
		wp_enqueue_style(
			'sugar-calendar-ticketing-admin-settings',
			Assets\get_url( 'css' ) . '/admin-settings' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);
	}

	if (
		sugar_calendar()->get_admin()->is_page( 'event_new' )
		|| sugar_calendar()->get_admin()->is_page( 'event_edit' )
	) {
		wp_enqueue_style(
			'sugar-calendar-ticketing-admin-event-metabox',
			Assets\get_url( 'css' ) . '/admin-event-metabox' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);
	}
}

/**
 * Localize scripts.
 *
 * @since 3.1.0
 */
function localize() {

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['page'] ) || $_GET['page'] !== 'sc-event-ticketing' ) {
		return;
	}

	wp_localize_script(
		'sc-et-general',
		'sc_event_ticket_vars',
		[
			'refund_notice'  => esc_html__( 'Updating this order will issue a refund through Stripe', 'sugar-calendar-lite' ),
			'delete_notice'  => esc_html__( "Are you sure you want to delete this order? Tickets associated with this order will also be deleted.\n\nThis action cannot be undone.", 'sugar-calendar-lite' ),
			'export_tickets' => esc_html__( 'This will export all tickets for the current filters. Do you want to continue?', 'sugar-calendar-lite' ),
		]
	);
}
