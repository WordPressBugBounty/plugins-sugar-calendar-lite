<?php
/**
 * Event meta comparisons.
 *
 * @since 3.18.0
 */

namespace Sugar_Calendar\Events;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Compares data about to be saved with an event's stored meta.
 *
 * @since 3.18.0
 */
class EventMeta {

	/**
	 * Whether saving this data changes any of the event's registered meta.
	 *
	 * Values are unslashed and sanitized first, as update_metadata() does, so a
	 * posted list (`[ 'WE', 'MO' ]`) matches the stored string (`MO,WE`).
	 *
	 * @since 3.18.0
	 *
	 * @param int   $event_id Event ID.
	 * @param array $data     Data passed to sugar_calendar_update_event().
	 *
	 * @return bool
	 */
	public static function changed( $event_id = 0, $data = [] ) {

		foreach ( array_intersect_key( (array) $data, get_registered_meta_keys( 'sc_event' ) ) as $key => $value ) {

			// An empty value deletes the key. Read raw: get_event_meta() returns a missing key's registered default.
			$new = self::normalize( empty( $value ) ? '' : sanitize_meta( $key, wp_unslash( $value ), 'sc_event' ) );
			$old = self::normalize( get_metadata_raw( 'sc_event', $event_id, $key, true ) );

			if ( $new !== $old ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A meta value as the string it is stored as.
	 *
	 * @since 3.18.0
	 *
	 * @param mixed $value Meta value.
	 *
	 * @return string
	 */
	private static function normalize( $value ) {

		return is_scalar( $value ) || is_null( $value ) ? (string) $value : maybe_serialize( $value );
	}
}
