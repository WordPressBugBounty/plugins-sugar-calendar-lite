<?php

/**
 * Event Meta Data
 *
 * @package Plugins/Site/Events/Meta data
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Add metadata to an event.
 *
 * Event ID is *not* of type: Post, Term, Comment, or User. It is the ID of the
 * Event from a Sugar_Calendar\Event() object from the sc_events database table.
 *
 * @since 2.0.0
 *
 * @param int    $id         Event ID, from the sc_events database table.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param bool   $unique     Optional. Whether the same key should not be added.
 *                           Default false.
 * @return int|false Meta ID on success, false on failure.
 */
function add_event_meta( $id, $meta_key, $meta_value, $unique = false ) {
	return add_metadata( 'sc_event', $id, $meta_key, $meta_value, $unique );
}

/**
 * Remove from an event, metadata matching key and/or value.
 *
 * Event ID is *not* of type: Post, Term, Comment, or User. It is the ID of the
 * Event from a Sugar_Calendar\Event() object from the sc_events database table.
 *
 * You can match based on the key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching key, if needed.
 *
 * @since 2.0.0
 *
 * @param int    $id         Event ID, from the sc_events database table.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Optional. Metadata value. Must be serializable if
 *                           non-scalar. Default empty.
 * @return bool True on success, false on failure.
 */
function delete_event_meta( $id, $meta_key, $meta_value = '' ) {
	return delete_metadata( 'sc_event', $id, $meta_key, $meta_value );
}

/**
 * Retrieve from an event, metadata value by key.
 *
 * Event ID is *not* of type: Post, Term, Comment, or User. It is the ID of the
 * Event from a Sugar_Calendar\Event() object from the sc_events database table.
 *
 * @since 2.0.0
 *
 * @param int    $id        Event ID, from the sc_events database table.
 * @param string $meta_key  Optional. The meta key to retrieve. By default, returns
 *                          data for all keys. Default empty.
 * @param bool   $single    Optional. Whether to return a single value. Default false.
 * @return mixed Will be an array if $single is false. Will be value of meta data
 *               field if $single is true.
 */
function get_event_meta( $id, $meta_key = '', $single = false ) {
	return get_metadata( 'sc_event', $id, $meta_key, $single );
}

/**
 * Update metadata for an event ID, and/or key, and/or value.
 *
 * Event ID is *not* of type: Post, Term, Comment, or User. It is the ID of the
 * Event from a Sugar_Calendar\Event() object from the sc_events database table.
 *
 * Use the $prev_value parameter to differentiate between meta fields with the
 * same key and event ID.
 *
 * If the meta field for the event does not exist, it will be added.
 *
 * @since 2.0.0
 *
 * @param int    $id         Event ID, from the sc_events database table.
 * @param string $meta_key   Metadata key.
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param mixed  $prev_value Optional. Previous value to check before removing.
 *                           Default empty.
 * @return int|bool Meta ID if the key didn't exist, true on successful update,
 *                  false on failure.
 */
function update_event_meta( $id, $meta_key, $meta_value, $prev_value = '' ) {
	return update_metadata( 'sc_event', $id, $meta_key, $meta_value, $prev_value );
}

/**
 * Updates metadata cache for list of event IDs.
 *
 * Performs SQL query to retrieve the metadata for the event IDs and
 * updates the metadata cache for the events. Therefore, the functions,
 * which call this function, do not need to perform SQL queries on their own.
 *
 * @since 2.0.0
 *
 * @param array $ids List of event IDs.
 * @return array|false Returns false if there is nothing to update or an array
 *                     of meta data.
 */
function update_eventmeta_cache( $ids ) {
	return update_meta_cache( 'sc_event', $ids );
}

/**
 * Return the meta data schema for Events.
 *
 * @since 3.4.0
 *
 * @return array
 */
function sugar_calendar_get_meta_data_schema() {

	/**
	 * Filters the meta data schema for Events.
	 *
	 * @since 3.4.0
	 *
	 * @param array $schema The meta data schema for Events.
	 */
	return apply_filters(
		'sugar_calendar_meta_data',
		[
			'audience' => [
				'type'              => 'string',
				'description'       => '',
				'single'            => true,
				'sanitize_callback' => 'sugar_calendar_sanitize_audience',
				'auth_callback'     => null,
				'show_in_rest'      => false,
			],
			'capacity' => [
				'type'              => 'string',
				'description'       => '',
				'single'            => true,
				'sanitize_callback' => 'sugar_calendar_sanitize_capacity',
				'auth_callback'     => null,
				'show_in_rest'      => false,
			],
			'language' => [
				'type'              => 'string',
				'description'       => '',
				'single'            => true,
				'sanitize_callback' => 'sugar_calendar_sanitize_language',
				'auth_callback'     => null,
				'show_in_rest'      => false,
			],
			'location' => [
				'type'              => 'string',
				'description'       => '',
				'single'            => true,
				'sanitize_callback' => 'sugar_calendar_sanitize_location',
				'auth_callback'     => null,
				'show_in_rest'      => false,
			],
			'color' => [
				'type'              => 'string',
				'description'       => '',
				'single'            => true,
				'sanitize_callback' => 'sugar_calendar_sanitize_color',
				'auth_callback'     => null,
				'show_in_rest'      => false,
			],
		]
	);
}

/**
 * Register metadata keys & sanitization callbacks.
 *
 * Note: Calendar Color metadata is saved in Sugar_Calendar\Term_Meta_UI
 *
 * @since 2.0.0
 * @since 3.4.0 Use sugar_calendar_get_meta_data_schema() to register meta.
 *
 * @return void
 */
function sugar_calendar_register_meta_data() {

    $schema = sugar_calendar_get_meta_data_schema();

    foreach ( $schema as $key => $args ) {
        register_meta( 'sc_event', $key, $args );
    }
}

/**
 * Sanitize event audience for saving
 *
 * @since 2.0.0
 *
 * @param string $value
 */
function sugar_calendar_sanitize_audience( $value = '' ) {
	return trim( strip_tags( $value ) );
}

/**
 * Sanitize event capacity for saving
 *
 * @since 2.0.0
 *
 * @param int $value
 */
function sugar_calendar_sanitize_capacity( $value = 0 ) {
	return (int) $value;
}

/**
 * Sanitize event language for saving
 *
 * @since 2.0.0
 *
 * @param string $value
 */
function sugar_calendar_sanitize_language( $value = '' ) {
	return trim( strip_tags( $value ) );
}

/**
 * Sanitize event location for saving
 *
 * @since 2.0.0
 *
 * @param string $value
 */
function sugar_calendar_sanitize_location( $value = '' ) {
	return trim( strip_tags( $value ) );
}

/**
 * Sanitize event color for saving
 *
 * @since 2.0.0
 *
 * @param string $value
 */
function sugar_calendar_sanitize_color( $value = '' ) {
	return sugar_calendar_sanitize_hex_color( trim( strip_tags( $value ) ) );
}

/**
 * Sanitize time zone for saving
 *
 * @since 2.1.0
 *
 * @param string $value
 */
function sugar_calendar_sanitize_timezone( $value = '' ) {
	return sugar_calendar_validate_timezone( trim( strip_tags( $value ) ) );
}

/**
 * Copy event meta data from one event to another.
 *
 * @since 3.4.0
 *
 * @param int $from_event_id The ID of the event to copy from.
 * @param int $to_event_id   The ID of the event to copy to.
 *
 * @return void
 */
function sugar_calendar_copy_event_meta_data( $from_event_id = 0, $to_event_id = 0 ) {

	// Bail if no event IDs.
	if ( empty( $from_event_id ) || empty( $to_event_id ) ) {
		return;
	}

	// Get meta data schema.
	$schema = sugar_calendar_get_meta_data_schema();

	// Loop through each meta key.
	foreach ( $schema as $key => $args ) {

		// Get the meta value.
		$meta_value = get_event_meta( $from_event_id, $key, true );

		// Update the meta value.
		update_event_meta( $to_event_id, $key, $meta_value );
	}
}
