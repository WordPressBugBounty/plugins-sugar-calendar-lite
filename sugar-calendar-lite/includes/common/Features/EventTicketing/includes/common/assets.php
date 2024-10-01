<?php
/**
 * Event Ticketing Common Asset
 *
 * @package Plugins/Site/Events/Common/Assets
 */
namespace Sugar_Calendar\AddOn\Ticketing\Common\Assets;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Helpers\WP;

/**
 * Get the plugin URL.
 *
 * @since 3.1.0
 *
 * @param string $type The type of assets URL.
 *
 * @return string
 */
function get_url( $type ) {

	$type = $type === 'js' ? 'js' : 'css';

	return SC_PLUGIN_ASSETS_URL . $type . '/features/event-ticketing';
}

/**
 * Get the CSS path.
 *
 * @since 1.0.1
 *
 * @return string
 */
function get_css_path() {

	if ( is_rtl() ) {
		$css_path = 'rtl';
	} else {
		$css_path = 'ltr';
	}

	return trailingslashit( $css_path );
}
