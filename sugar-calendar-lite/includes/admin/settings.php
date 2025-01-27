<?php
/**
 * Sugar Calendar Admin Settings Screen
 *
 * @since 2.0.0
 */

namespace Sugar_Calendar\Admin\Settings;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Plugin;

/**
 * Is the current admin screen a settings page?
 *
 * @since 2.0.2
 *
 * @return bool
 */
function in() {

	return ( Plugin::instance()->get_admin()->is_page( 'settings' ) );
}

/**
 * Return array of settings sections
 *
 * @since 2.0.0
 *
 * @return array
 */
function get_sections() {

	static $retval = null;

	if ( null === $retval ) {
		$retval = apply_filters( 'sugar_calendar_settings_sections', [] );
	}

	// Return
	return $retval;
}

/**
 * Return the first/main section ID.
 *
 * @since 2.0.3
 *
 * @return string
 */
function get_main_section_id() {

	return key( get_sections() );
}

/**
 * Return array of settings sub-sections
 *
 * @since 2.0.0
 *
 * @return array
 */
function get_subsections( $section = '' ) {

	static $retval = null;

	// Store statically to avoid thrashing the gettext API
	if ( null === $retval ) {
		$retval = apply_filters( 'sugar_calendar_settings_subsections', [], $section );
	}

	// Maybe return a secific set of subsection
	if ( ! empty( $section ) && isset( $retval[ $section ] ) ) {
		return $retval[ $section ];
	}

	// Return all subsections
	return $retval;
}

/**
 * Return a subsection
 *
 * @since 2.0.0
 *
 * @param string $section
 * @param string $subsection
 *
 * @return array
 */
function get_subsection( $section = 'main', $subsection = '' ) {

	$subs = get_subsections( $section );

	// Default
	$default = array(
		get_main_section_id() => array(
			'name' => esc_html__( 'General', 'sugar-calendar-lite' ),
		),
	);

	// Return the subsection
	return isset( $subs[ $subsection ] )
		? $subs[ $subsection ]
		: $default;
}
