<?php
/**
 * Payment gateway contract for Event Ticketing.
 *
 * A gateway registers its class via the `sc_et_gateways` filter and extends
 * Checkout. Its key in that filter map is its slug (e.g. `stripe`), recorded on
 * each order it processes so refunds can be dispatched back to it.
 */

namespace Sugar_Calendar\AddOn\Ticketing\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Interface GatewayInterface
 *
 * @since 3.12.0
 */
interface GatewayInterface {

	/**
	 * Process a purchase: build the order data and call Checkout::complete().
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function process();

	/**
	 * Validate gateway-specific checkout fields, adding errors via add_error().
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function validate_gateway_data();

	/**
	 * Refund an order through this gateway's processor.
	 *
	 * @since 3.12.0
	 *
	 * @param object $order The order object (from Functions\get_order()).
	 *
	 * @return bool|\WP_Error True on success or legitimate no-op; WP_Error on failure.
	 */
	public function refund( $order );
}
