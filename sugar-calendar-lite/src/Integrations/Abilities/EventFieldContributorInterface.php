<?php
/**
 * Contract for a feature that adds a field to the event write abilities.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * EventFieldContributorInterface — widens the event schema, writes the field, reports it back.
 *
 * Static because contributors are hook callbacks with no state of their own.
 *
 * @since 3.15.0
 */
interface EventFieldContributorInterface {

	/**
	 * Register the hooks.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public static function hooks();

	/**
	 * Widen the input schema (`sugar_calendar_integrations_abilities_event_input_schema`).
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $properties Properties so far.
	 * @param mixed $partial    Whether this is the update variant.
	 *
	 * @return mixed
	 */
	public static function add_schema( $properties, $partial );

	/**
	 * Write the field after the event row is saved (`sugar_calendar_events_event_writer_after_write`).
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $event_id   Sugar Calendar event id.
	 * @param mixed $normalized Normalized ability input.
	 * @param mixed $result     Writer result; append to `errors` to report a refusal.
	 *
	 * @return void
	 */
	public static function apply( $event_id, $normalized, &$result );

	/**
	 * Add the stored value to the post-write snapshot (`sugar_calendar_integrations_abilities_event_snapshot`).
	 *
	 * The filter also passes the resolved `Sugar_Calendar\Event` as a third argument; an
	 * implementation may declare it as an optional parameter.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $snapshot Snapshot so far.
	 * @param mixed $event_id Sugar Calendar event id.
	 *
	 * @return mixed
	 */
	public static function snapshot( $snapshot, $event_id );

	/**
	 * Label the field's change rows in the Gia chat (`sugar_calendar_ai_field_labels`).
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $labels Write property name => label.
	 *
	 * @return mixed
	 */
	public static function field_labels( $labels );
}
