<?php

/**
 * Event Taxonomies
 *
 * @package Plugins/Site/Events/Taxonomies
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Common\Editor as Editor;
use Sugar_Calendar\Options;

/**
 * Return the taxonomy ID for the primary calendar taxonomy.
 *
 * This remains named `sc_event_category` for backwards compatibility reasons,
 * and is abstracted out here to avoid naming confusion.
 *
 * @since 2.0
 *
 * @return string
 */
function sugar_calendar_get_calendar_taxonomy_id() {

	return 'sc_event_category';
}

/**
 * Event calendar taxonomy
 *
 * @since 2.0.0
 */
function sugar_calendar_register_calendar_taxonomy() {

	// Get the taxonomy ID
	$tax = sugar_calendar_get_calendar_taxonomy_id();

	// Labels
	$labels = [
		'name'                       => esc_html__( 'Calendars', 'sugar-calendar-lite' ),
		'singular_name'              => esc_html__( 'Calendar', 'sugar-calendar-lite' ),
		'search_items'               => esc_html__( 'Search', 'sugar-calendar-lite' ),
		'popular_items'              => esc_html__( 'Popular Calendars', 'sugar-calendar-lite' ),
		'all_items'                  => esc_html__( 'All Calendars', 'sugar-calendar-lite' ),
		'parent_item'                => esc_html__( 'Parent Calendar', 'sugar-calendar-lite' ),
		'parent_item_colon'          => esc_html__( 'Parent Calendar:', 'sugar-calendar-lite' ),
		'edit_item'                  => esc_html__( 'Edit Calendar', 'sugar-calendar-lite' ),
		'view_item'                  => esc_html__( 'View Calendar', 'sugar-calendar-lite' ),
		'update_item'                => esc_html__( 'Update Calendar', 'sugar-calendar-lite' ),
		'add_new_item'               => esc_html__( 'Add New Calendar', 'sugar-calendar-lite' ),
		'new_item_name'              => esc_html__( 'New Calendar Name', 'sugar-calendar-lite' ),
		'separate_items_with_commas' => esc_html__( 'Separate calendars with commas', 'sugar-calendar-lite' ),
		'add_or_remove_items'        => esc_html__( 'Add or remove calendars', 'sugar-calendar-lite' ),
		'choose_from_most_used'      => esc_html__( 'Choose from the most used calendars', 'sugar-calendar-lite' ),
		'no_terms'                   => esc_html__( 'No Calendars', 'sugar-calendar-lite' ),
		'not_found'                  => esc_html__( 'No calendars found', 'sugar-calendar-lite' ),
		'items_list_navigation'      => esc_html__( 'Calendars list navigation', 'sugar-calendar-lite' ),
		'items_list'                 => esc_html__( 'Calendars list', 'sugar-calendar-lite' ),
		'back_to_items'              => esc_html__( '&larr; Back to Calendars', 'sugar-calendar-lite' ),
	];

	// Rewrite rules
	$rewrite = [
		'slug'       => 'events/calendar',
		'with_front' => false,
	];

	// Capabilities
	$caps = [
		'manage_terms' => 'manage_event_calendars',
		'edit_terms'   => 'edit_event_calendars',
		'delete_terms' => 'delete_event_calendars',
		'assign_terms' => 'assign_event_calendars',
	];

	// Arguments
	$args = [
		'labels'                => $labels,
		'rewrite'               => $rewrite,
		'capabilities'          => $caps,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => $tax,
		'show_tagcloud'         => true,
		'hierarchical'          => true,
		'show_in_nav_menus'     => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_rest'          => true,
		'colors'                => true,
		'timezones'             => true,
		'source'                => 'sugar-calendar',
		'meta_box_cb'           => 'Sugar_Calendar\\Admin\\Editor\\Meta\\calendars',
	];

	// Default Calendar
	$default = sugar_calendar_get_default_calendar();

	// Default exists
	if ( ! empty( $default ) ) {

		// Get term (does not use taxonomy, because it's not registered yet!)
		$term = get_term( $default );

		// Term exists
		if ( ! empty( $term->name ) ) {
			$args['default_term'] = [ 'name' => $term->name ];
		}
	}

	// Get the editor type
	$editor = Editor\current();

	// Register
	register_taxonomy(
		$tax,
		sugar_calendar_get_event_post_type_id(),
		$args
	);
}

/**
 * Relate taxonomy to post types.
 *
 * @since 2.0.6
 */
function sugar_calendar_relate_taxonomy_to_post_types() {

	// Get the taxonomy
	$tax = sugar_calendar_get_calendar_taxonomy_id();

	// Get the types
	$types = sugar_calendar_allowed_post_types();

	// Bail if no types
	if ( empty( $types ) ) {
		return;
	}

	// Loop through types and relate them
	foreach ( $types as $type ) {
		register_taxonomy_for_object_type( $tax, $type );
	}
}

/**
 * Get all taxonomies for all supported Event relationships.
 *
 * @since 2.0.19
 *
 * @param string|array $types
 *
 * @return array
 */
function sugar_calendar_get_object_taxonomies( $types = '', $output = 'names' ) {

	// Default return value
	$retval = [];

	// Fallback types to an array of post types that support Events
	if ( empty( $types ) ) {
		$types = sugar_calendar_allowed_post_types();
	}

	// Bail if no post types allow Events (weird!)
	if ( empty( $types ) ) {
		return $retval;
	}

	// Default output to names
	if ( ! in_array( $output, [ 'objects', 'names' ], true ) ) {
		$output = 'names';
	}

	// Cast strings to array
	if ( is_string( $types ) ) {
		$types = (array) $types;
	}

	// Loop through types
	foreach ( $types as $type ) {

		// Get taxonomies for post type
		$taxonomies = get_object_taxonomies( $type, $output );

		// Skip if empty
		if ( empty( $taxonomies ) ) {
			continue;
		}

		// Merge
		$retval = array_merge( $retval, $taxonomies );
	}

	// Filter & return
	return (array) apply_filters( 'sugar_calendar_get_taxonomies', $retval );
}

/**
 * Get the color of a calendar
 *
 * @since 2.0.0
 *
 * @param int $calendar_id
 *
 * @return string
 */
function sugar_calendar_get_calendar_color( $calendar_id = 0 ) {

	$meta = get_term_meta( $calendar_id, 'color', true );

	// Default color.
	if ( empty( $meta ) ) {
		$meta = '#5685BD';
	}

	return sugar_calendar_sanitize_hex_color( $meta );
}

/**
 * Get the color for an event
 *
 * @since 2.0.0
 *
 * @param int    $object_id   ID of the object
 * @param string $object_type The type of object
 *
 * @return string
 */
function sugar_calendar_get_event_color( $object_id = 0, $object_type = 'post' ) {

	$taxonomy = sugar_calendar_get_calendar_taxonomy_id();

	// Look for a single calendar
	$calendar = wp_get_post_terms( $object_id, $taxonomy, [
		'number' => 1,
	] );

	// Bail if no calendar
	if ( empty( $calendar ) ) {
		return false;
	}

	// Use the first result
	$calendar = reset( $calendar );
	$color    = sugar_calendar_get_calendar_color( $calendar->term_id );

	// Return color or "none"
	return ! empty( $color )
		? $color
		: 'none';
}

/**
 * Return the name of the option used to store the default Event Calendar.
 *
 * @since 2.1.9
 *
 * @return string
 */
function sugar_calendar_get_default_calendar_option_name() {

	return 'default_calendar';
}

/**
 * Return the value of the default Event Calendar.
 *
 * @since 2.1.9
 *
 * @return string
 */
function sugar_calendar_get_default_calendar() {

	// Get the option
	$name   = sugar_calendar_get_default_calendar_option_name();
	$retval = Options::get( $name );

	// Return
	return $retval;
}

/**
 * Filter the default WordPress option names, and use our custom one instead.
 *
 * @since 2.1.9
 *
 * @param string $value
 * @param string $option
 *
 * @return string
 */
function sugar_calendar_pre_get_default_calendar_option( $value = false, $option = '', $default = '' ) {

	// Bail if not filtering the correct option
	if ( ! in_array( $option, [ 'default_sc_event_category', 'default_term_sc_event_category' ], true ) ) {
		return $value;
	}

	// Get the correct ption
	$value = sugar_calendar_get_default_calendar();

	// Return the filtered option
	return $value;
}
