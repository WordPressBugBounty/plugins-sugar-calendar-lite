<?php
/**
 * Ability: create a draft event.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Events\EventWriter;
use Sugar_Calendar\Integrations\Abilities\AbstractWriteAbility;
use Sugar_Calendar\Integrations\Abilities\EventInputSchema;
use Sugar_Calendar\Integrations\Abilities\EventSnapshot;

/**
 * CreateEvent — creates the event as a draft. There is deliberately no status
 * property: publishing stays a human action in the editor.
 *
 * @since 3.14.0
 */
class CreateEvent extends AbstractWriteAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'create-event';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return __( 'Create Event', 'sugar-calendar-lite' );
	}

	/**
	 * Human-readable ability description.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return __(
			'Creates a draft event with the given title, dates, calendars and location. The draft is not published.',
			'sugar-calendar-lite'
		);
	}

	/**
	 * Input JSON-schema for this ability.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {

		$properties = EventInputSchema::properties();

		$properties['idempotency_key'] = [
			'type'      => 'string',
			'maxLength' => 64,
		];

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'title', 'start' ],
			'properties'           => $properties,
		];
	}

	/**
	 * Output JSON-schema for this ability.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {

		return [
			'type'       => 'object',
			'properties' => [
				'event_id'    => [ 'type' => 'integer' ],
				'post_id'     => [ 'type' => 'integer' ],
				'edit_url'    => [ 'type' => 'string' ],
				'created'     => [ 'type' => 'boolean' ],
				'assumptions' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'applied'     => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'errors'      => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'event'       => [ 'type' => 'object' ],
			],
		];
	}

	/**
	 * Requires the `create_events` capability.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|\WP_Error
	 */
	public function check_permission( $input = null ) {

		return $this->require_cap(
			'create_events',
			__( 'You do not have permission to create events.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Normalize the input and create the draft event, replaying an earlier
	 * result when the idempotency key was already used.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args Normalized ability input.
	 *
	 * @return array|\WP_Error
	 */
	protected function run( array $args ) {

		$mismatch_error = $this->signup_mismatch_error( $args );

		if ( $mismatch_error !== null ) {
			return $mismatch_error;
		}

		$key      = isset( $args['idempotency_key'] ) ? (string) $args['idempotency_key'] : '';
		$existing = $this->idempotency_hit( $key );

		if ( $existing > 0 ) {
			return $this->replay( $existing );
		}

		// Not part of EventInputSchema's own properties; drop it before
		// normalize() so it doesn't land in $normalized['extra'].
		unset( $args['idempotency_key'] );

		// What the diff compares the stored event against, kept under its own
		// name so a later refactor can't quietly widen it past the raw request.
		$requested = $args;

		$normalized = EventInputSchema::normalize( $args );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$writer = new EventWriter();
		$result = $writer->create( $normalized );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->remember_idempotency( $key, (int) $result['event_id'] );

		$snapshot    = EventSnapshot::for_event( (int) $result['event_id'] );
		// Field paths a contributor already explained in errors/assumptions this
		// write, so the diff below does not add a second, contradicting line.
		$explained   = array_values( array_unique( (array) ( $result['explained'] ?? [] ) ) );
		$errors      = array_merge( self::collect( $normalized, $result, 'errors' ), EventSnapshot::diff( $requested, $snapshot, $explained ) );
		$assumptions = self::collect( $normalized, $result, 'assumptions' );

		return [
			'event_id'    => (int) $result['event_id'],
			'post_id'     => (int) $result['post_id'],
			'edit_url'    => EventWriter::edit_url( (int) $result['post_id'] ),
			'created'     => true,
			'assumptions' => array_values( $assumptions ),
			'applied'     => array_values( (array) ( $result['applied'] ?? [] ) ),
			'errors'      => array_values( $errors ),
			'event'       => $snapshot,
		];
	}

	/**
	 * Return the event a previous call with the same idempotency key produced.
	 *
	 * @since 3.14.0
	 *
	 * @param int $event_id Event id from the stored key.
	 *
	 * @return array|\WP_Error
	 */
	private function replay( int $event_id ) {

		$event = sugar_calendar_get_event( $event_id );

		if ( empty( $event->id ) ) {
			return $this->write_failed(
				__( 'The event from the earlier attempt is no longer available.', 'sugar-calendar-lite' )
			);
		}

		$post_id = (int) $event->object_id;

		if ( $post_id <= 0 ) {
			return $this->write_failed(
				__( 'The event from the earlier attempt has no post attached, so it cannot be opened.', 'sugar-calendar-lite' )
			);
		}

		$per_event = $this->check_per_event_permission(
			$event_id,
			__( 'You do not have permission to view that event.', 'sugar-calendar-lite' )
		);

		if ( is_wp_error( $per_event ) ) {
			return $per_event;
		}

		return [
			'event_id'    => $event_id,
			'post_id'     => $post_id,
			'edit_url'    => EventWriter::edit_url( $post_id ),
			'created'     => false,
			'assumptions' => [],
			'applied'     => [],
			'errors'      => [
				__(
					'That idempotency key was already used, so the event from the first call is returned unchanged. Send a new key to apply different input.',
					'sugar-calendar-lite'
				),
			],
			'event'       => EventSnapshot::for_event( $event_id ),
		];
	}
}
