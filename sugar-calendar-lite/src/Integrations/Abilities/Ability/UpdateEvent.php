<?php
/**
 * Ability: patch an existing event.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Event;
use Sugar_Calendar\Events\EventWriter;
use Sugar_Calendar\Integrations\Abilities\AbstractWriteAbility;
use Sugar_Calendar\Integrations\Abilities\EventInputSchema;
use Sugar_Calendar\Integrations\Abilities\SignupModes;

/**
 * UpdateEvent — patches one event and reports the previous value of every
 * field it touched, which is what makes the chat's "undo" this same ability
 * replayed with that map.
 *
 * @since 3.14.0
 */
class UpdateEvent extends AbstractWriteAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'update-event';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return __( 'Update Event', 'sugar-calendar-lite' );
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
			'Changes the given fields on one event and returns their previous values. Does not publish or unpublish the event.',
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

		$properties = EventInputSchema::properties( true );

		$properties['event_id'] = [
			'type'    => 'integer',
			'minimum' => 1,
		];

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'event_id' ],
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
				'edit_url'    => [ 'type' => 'string' ],
				'updated'     => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'previous'    => [ 'type' => 'object' ],
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
			],
		];
	}

	/**
	 * Per-event gate: the same capability needed to edit the event by hand.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|\WP_Error
	 */
	public function check_permission( $input = null ) {

		$args = $this->normalize_input( $input );

		return $this->check_per_event_permission(
			absint( $args['event_id'] ?? 0 ),
			__( 'You do not have permission to edit this event.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Whether a normalized patch carries anything writable.
	 *
	 * A terms bucket counts even when explicitly empty — that's a request to
	 * clear it, which is a real change `empty()` alone would miss.
	 *
	 * @since 3.14.0
	 *
	 * @param array $normalized Output of EventInputSchema::normalize().
	 *
	 * @return bool
	 */
	private function changes_something( array $normalized ): bool {

		$terms = isset( $normalized['terms'] ) ? (array) $normalized['terms'] : [];

		return ! empty( $normalized['row'] )
			|| ! empty( $normalized['meta'] )
			|| ! empty( $normalized['extra'] )
			|| array_key_exists( 'calendars', $terms )
			|| array_key_exists( 'tags', $terms );
	}

	/**
	 * Fill the date group from the stored event so a patch stays a patch.
	 *
	 * `normalize()` rewrites all five date columns together as soon as a start
	 * is present, so a start-only patch would otherwise reset the end to the
	 * start, drop both time zone columns and clear `all_day`. The fill happens
	 * here rather than in `EventInputSchema` because the schema does no
	 * database reads.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args  Patch input, without `event_id`.
	 * @param Event $event Stored event.
	 *
	 * @return array|\WP_Error
	 */
	private function fill_date_group( array $args, Event $event ) {

		$group = [ 'start', 'end', 'all_day', 'timezone' ];

		if ( ! array_intersect( $group, array_keys( $args ) ) ) {
			return $args;
		}

		$start = (string) $event->start;
		$end   = (string) $event->end;

		// Legacy rows store a zero date rather than an empty one, and the strict
		// parser refuses it. A zero start has no stand-in, so say so rather than
		// filling an unparseable value and blaming the end date for it.
		if ( ! isset( $args['start'] ) && $event->is_empty_date( $start ) ) {
			return $this->invalid_input(
				__( 'This event has no usable start date, so send a start date along with the change.', 'sugar-calendar-lite' )
			);
		}

		$stored = [
			'start'    => $start,
			'all_day'  => (bool) $event->all_day,
			'timezone' => (string) $event->start_tz,
		];

		// A zero end is left out entirely: normalize() reads an absent end as
		// "ends when it starts", which is what the zero date meant.
		if ( ! $event->is_empty_date( $end ) ) {
			$stored['end'] = $end;
		}

		foreach ( $group as $field ) {
			if ( ! isset( $args[ $field ] ) && isset( $stored[ $field ] ) ) {
				$args[ $field ] = $stored[ $field ];
			}
		}

		return $args;
	}

	/**
	 * Reject a patch whose signup detail belongs to a mode not chosen, or that would
	 * switch away from a mode that still holds data.
	 *
	 * Reads the current signup mode before the write happens, and hands it back via
	 * `$previous_signup` so the caller can report the change afterward without a
	 * second read.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $args            Patch input, without `event_id`.
	 * @param int    $event_id        Sugar Calendar event id.
	 * @param string $previous_signup Set to the mode read before the write, by reference.
	 *
	 * @return null|\WP_Error
	 */
	private function check_signup_preconditions( array $args, int $event_id, string &$previous_signup ) {

		$previous_signup = '';

		$mismatch_error = $this->signup_mismatch_error( $args );

		if ( $mismatch_error !== null ) {
			return $mismatch_error;
		}

		$requested_signup = isset( $args['signup'] ) ? (string) $args['signup'] : '';

		if ( $requested_signup === '' ) {
			return null;
		}

		$previous_signup = SignupModes::current( $event_id );

		if ( $previous_signup === $requested_signup ) {
			return null;
		}

		// Ask every declared mode about in-use, not just current()'s single winner: an event
		// carrying legacy data in more than one mode has a second mode invisible to current().
		foreach ( array_keys( SignupModes::available() ) as $mode ) {

			if ( $mode === $requested_signup ) {
				continue;
			}

			$reason = SignupModes::in_use_reason( $mode, $event_id );

			if ( $reason !== '' ) {
				return $this->signup_in_use( $reason );
			}
		}

		return null;
	}

	/**
	 * Load the target event, then check its signup preconditions against the patch.
	 *
	 * Folds the lookup and the precondition check into one call so `run()` stays a flat list
	 * of steps rather than interleaving them.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $args            Patch input, without `event_id`.
	 * @param int    $event_id        Sugar Calendar event id.
	 * @param string $previous_signup Set to the mode read before the write, by reference.
	 *
	 * @return Event|\WP_Error
	 */
	private function resolve_event( array $args, int $event_id, string &$previous_signup ) {

		$event = sugar_calendar_get_event( $event_id );

		if ( empty( $event->id ) ) {
			return $this->invalid_input( __( 'That event could not be found.', 'sugar-calendar-lite' ) );
		}

		$signup_error = $this->check_signup_preconditions( $args, $event_id, $previous_signup );

		if ( $signup_error !== null ) {
			return $signup_error;
		}

		return $event;
	}

	/**
	 * Fill the date group, normalize the patch, and reject it if nothing writable remains.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args  Patch input, without `event_id`.
	 * @param Event $event Stored event.
	 *
	 * @return array|\WP_Error
	 */
	private function build_normalized( array $args, Event $event ) {

		$filled = $this->fill_date_group( $args, $event );

		if ( is_wp_error( $filled ) ) {
			return $filled;
		}

		$normalized = EventInputSchema::normalize( $filled, true );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		if ( ! $this->changes_something( $normalized ) ) {
			return $this->invalid_input(
				__( 'Nothing in that patch could be applied. Name a field this ability can change.', 'sugar-calendar-lite' )
			);
		}

		return $normalized;
	}

	/**
	 * Normalize the patch and apply it, reporting the previous value of every
	 * field it touched.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args Normalized ability input.
	 *
	 * @return array|\WP_Error
	 */
	protected function run( array $args ) {

		$event_id = absint( $args['event_id'] ?? 0 );

		unset( $args['event_id'] );

		if ( empty( $args ) ) {
			return $this->invalid_input(
				__( 'Nothing to change. Name at least one field to update.', 'sugar-calendar-lite' )
			);
		}

		$previous_signup = '';
		$event           = $this->resolve_event( $args, $event_id, $previous_signup );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		$requested_signup = isset( $args['signup'] ) ? (string) $args['signup'] : '';

		$normalized = $this->build_normalized( $args, $event );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$writer = new EventWriter();
		$result = $writer->update( $event_id, $normalized );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// `previous` is writer-owned, but the writer never sees `signup`: it rides
		// the contributors' `extra` bucket, so the ability reports the change itself.
		if ( $requested_signup !== '' && $previous_signup !== $requested_signup ) {
			$result['previous']['signup'] = $previous_signup;
		}

		return $this->payload( $event_id, $normalized, $result );
	}

	/**
	 * Shape the ability output from the writer's result.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $event_id   Event id that was patched.
	 * @param array $normalized Output of EventInputSchema::normalize().
	 * @param array $result     Output of EventWriter::update().
	 *
	 * @return array
	 */
	private function payload( int $event_id, array $normalized, array $result ): array {

		$previous = (array) ( $result['previous'] ?? [] );
		$post_id  = (int) ( $result['post_id'] ?? 0 );

		return [
			'event_id'    => $event_id,
			'edit_url'    => EventWriter::edit_url( $post_id ),
			'updated'     => array_keys( $previous ),
			'previous'    => $previous,
			'assumptions' => array_values( (array) ( $normalized['assumptions'] ?? [] ) ),
			'applied'     => array_values( (array) ( $result['applied'] ?? [] ) ),
			'errors'      => array_values( (array) ( $result['errors'] ?? [] ) ),
		];
	}
}
