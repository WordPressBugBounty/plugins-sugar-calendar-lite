<?php

/**
 * Sugar Calendar Legacy Theme Scripts.
 *
 * @since 1.0.0
 */

use Sugar_Calendar\Options;
use Sugar_Calendar\Helpers;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Register front-end assets.
 *
 * @since 2.0.8
 * @since 3.1.2 Move the `sc-time-zones` to new assets folder and add localized object.
 */
function sc_register_assets() {

	// AJAX.
	wp_register_script(
		'sc-ajax',
		SC_PLUGIN_ASSETS_URL . 'js/frontend/legacy/sc-ajax' . Helpers\WP::asset_min() . '.js',
		[ 'jquery' ],
		sugar_calendar_get_assets_version(),
		false
	);

	wp_localize_script(
		'sc-ajax',
		'sc_vars',
		[
			'ajaxurl'           => admin_url( 'admin-ajax.php' ),
			'date_format'       => sc_get_date_format(),
			'time_format'       => sc_get_time_format(),
			'start_of_week'     => sc_get_week_start_day(),
			'timezone'          => sc_get_timezone(),
			'cal_sc_visitor_tz' => Helpers::should_allow_visitor_tz_convert_cal_shortcode(),
		]
	);

	// Time zones.
	wp_register_script(
		'sc-time-zones',
		SC_PLUGIN_ASSETS_URL . 'js/frontend/legacy/sc-time-zones' . Helpers\WP::asset_min() . '.js',
		[ 'wp-date', 'sc-ajax' ],
		sugar_calendar_get_assets_version(),
		false
	);

	wp_localize_script(
		'sc-time-zones',
		'SCTimezoneConvert',
		[
			'date_format'   => sc_get_date_format(),
			'time_format'   => sc_get_time_format(),
			'start_of_week' => sc_get_week_start_day(),
			'timezone'      => sc_get_timezone(),
		]
	);

	// Events
	wp_register_style(
		'sc-events',
		SC_PLUGIN_URL . 'includes/themes/legacy/css/sc-events.css',
		array(),
		sugar_calendar_get_assets_version()
	);
}

/**
 * Load front-end scripts.
 *
 * @since 1.0.0
 */
function sc_load_front_end_scripts() {

	// Look for conditions where scripts should be enqueued
	if (
		sc_is_calendar_page()
		||
		sc_using_widget()
		||
		sc_doing_events()
		||
		sc_content_has_shortcodes()
	) {
		sc_enqueue_scripts();
		sc_enqueue_styles();
	}
}

/**
 * Check if a string contains shortcodes.
 *
 * @since 2.0.8
 *
 * @param string  $content
 *
 * @return bool
 * @global object $post
 */
function sc_content_has_shortcodes( $content = '' ) {

	// Fallback to current post content
	if ( empty( $content ) ) {
		global $post;

		// Get raw post content, if exists
		$content = ! empty( $post->post_content )
			? $post->post_content
			: '';

		// Bail if content is empty
		if ( empty( $content ) ) {
			return false;
		}
	}

	// Get shortcode IDs
	$shortcode_ids = sc_get_shortcode_ids();

	// Look for Sugar Calendar shortcodes
	if ( ! empty( $shortcode_ids ) ) {
		foreach ( $shortcode_ids as $id ) {
			if ( has_shortcode( $content, $id ) ) {
				return true;
			}
		}
	}

	// No shortcodes found
	return false;
}

/**
 * Peek into block content and check it for shortcode usage.
 *
 * @since 2.0.8
 *
 * @param string $content The block content
 *
 * @return string $content The block content
 */
function sc_enqueue_if_block_has_shortcodes( $content = '' ) {

	// Bail if content is empty
	if ( empty( $content ) ) {
		return $content;
	}

	// Check the block content for a shortcode
	if ( sc_content_has_shortcodes( $content ) ) {
		sc_enqueue_scripts();
		sc_enqueue_styles();
	}

	// Return the content, unchanged
	return $content;
}

/**
 * Enqueue scripts callback.
 *
 * @since 1.0.0
 * @since 3.1.2 Move the localized object for `sc-ajax` to where its registered.
 */
function sc_enqueue_scripts() {

	// Front-end AJAX.
	wp_enqueue_script( 'sc-ajax' );

	// Front-end Time Zones.
	if ( Options::get( 'timezone_convert' ) ) {
		wp_enqueue_script( 'sc-time-zones' );
	}
}

/**
 * Enqueue styles callback
 *
 * @since 1.0.0
 */
function sc_enqueue_styles() {

	// Front-end styling
	wp_enqueue_style( 'sc-events' );
}
