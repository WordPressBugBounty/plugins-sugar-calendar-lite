<?php
/**
 * Events Admin Nav
 *
 * @package Plugins/Site/Events/Admin/Nav
 */

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Nav;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
use Sugar_Calendar\Admin\Nav as Nav;

/**
 * Get the product tabs for Events, Calendars, and more.
 *
 * @since 1.0.0
 */
function display( $tabs, $active_tab ) {


	// Output the nav
	echo Nav\get( $tabs, $active_tab );
}

/**
 * Maybe show a Test Mode warning
 *
 * @since 1.0.1
 */
function test_mode() {

	// Bail if not in test mode
	if ( ! Functions\is_sandbox() ) {
		return;
	}

	// Output the link
	?><a href="<?php echo esc_url( admin_url( 'admin.php?page=sugarcalendar-settings&section=payments' ) ); ?>" class="page-title-action test-mode"><?php
	esc_html_e( 'Test Mode: On', 'sugar-calendar-lite' );
	?></a><?php
}

/**
 * Maybe show a Stripe Connect warning
 *
 * @since 1.0.1
 */
function stripe_connect() {

	// Bail if Stripe connected
	if ( Functions\get_stripe_publishable_key() ) {
		return;
	}

	// Output the link
	?><a href="<?php echo esc_url( admin_url( 'admin.php?page=sugarcalendar-settings&section=payments' ) ); ?>" class="page-title-action stripe"><?php
	esc_html_e( 'Stripe: Disconnected', 'sugar-calendar-lite' );
	?></a><?php
}
