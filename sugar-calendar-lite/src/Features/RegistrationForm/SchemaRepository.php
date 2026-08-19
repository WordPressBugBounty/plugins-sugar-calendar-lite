<?php

namespace Sugar_Calendar\Features\RegistrationForm;

use WP_Error;

/**
 * Read/write API for the per-event registration form schema.
 *
 * The schema lives as JSON in event meta (wp_sc_eventmeta), keyed by the
 * Sugar Calendar event id — NOT the post id (dual-ID system).
 *
 * @since 3.13.0
 */
class SchemaRepository {

	/**
	 * Event meta key holding the schema JSON.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const META_KEY = 'registration_form';

	/**
	 * Get the stored schema for an event.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 *
	 * @return array|null Decoded schema, or null when absent/corrupt.
	 */
	public static function get( $event_id ) {

		$raw = get_event_meta( (int) $event_id, self::META_KEY, true );

		if ( ! is_string( $raw ) || $raw === '' ) {
			return null;
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Validate and persist a schema for an event.
	 *
	 * Refuses on Lite (paid-core feature — the server-side gate the education
	 * UI relies on) and on validation failure; in both cases the previously
	 * stored schema is untouched.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $event_id Sugar Calendar event id.
	 * @param array $schema   Raw schema array.
	 *
	 * @return true|WP_Error
	 */
	public static function save( $event_id, array $schema ) {

		if ( ! sugar_calendar()->is_pro() ) {
			return new WP_Error( 'registration_form_lite', 'Registration Form requires a paid plan.' );
		}

		$result = SchemaValidator::validate( $schema );

		if ( ! $result['valid'] ) {
			return new WP_Error( 'registration_form_invalid', 'Invalid registration form schema.', $result['errors'] );
		}

		$event_id = (int) $event_id;

		// update_metadata() bails on a falsy object id and returns false, so an
		// unresolved event (a brand-new one has no row yet) would silently save nothing.
		if ( $event_id <= 0 ) {
			return new WP_Error( 'registration_form_no_event', 'No Sugar Calendar event id to save the schema against.' );
		}

		update_event_meta( $event_id, self::META_KEY, wp_json_encode( $result['schema'] ) );

		// Verify by reading back: update_metadata() returns false both on failure
		// and when the value is already identical, so `=== false` would misreport
		// an unchanged save as a failure.
		$stored = get_event_meta( $event_id, self::META_KEY, true );

		if ( ! is_string( $stored ) || json_decode( $stored, true ) !== $result['schema'] ) {
			return new WP_Error( 'registration_form_save_failed', 'Could not store the registration form schema.' );
		}

		return true;
	}
}
