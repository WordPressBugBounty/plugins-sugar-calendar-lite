<?php

namespace Sugar_Calendar\AddOn\Ticketing\Settings;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Get a setting
 *
 * @since 1.0
 * @return mixed The stored setting or default
 */
function get_setting( $key = '', $default = '' ) {

	$options = get_option( 'sc_et_settings', [] );

	if ( ! isset( $options[ $key ] ) ) {
		$options[ $key ] = $default;
	}

	$ret = $options[ $key ];

	return apply_filters( 'sc_et_get_setting', $ret, $key, $default, $options );
}

/**
 * Update a setting
 *
 * @since 1.0
 * @return mixed The stored setting or default
 */
function update_setting( $key = '', $value = '' ) {

	$options = get_option( 'sc_et_settings', [] );

	$options[ $key ] = $value;

	return update_option( 'sc_et_settings', $options );
}
