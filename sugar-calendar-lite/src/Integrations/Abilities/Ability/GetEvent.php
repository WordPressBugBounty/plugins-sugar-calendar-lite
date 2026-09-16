<?php
/**
 * The sc-events/get-event ability.
 *
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractAbility;
use Sugar_Calendar\Integrations\Abilities\Formatter;
use WP_Error;

/**
 * Get a single event by its Sugar Calendar event ID. Per-event `read_event`
 * permission; missing events are forbidden by design.
 *
 * @since 3.12.0
 */
class GetEvent extends AbstractAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'get-event';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'Get Event', 'sugar-calendar-lite' );
	}

	/**
	 * Human-readable ability description.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return esc_html__( 'Get a single event by its Sugar Calendar event ID.', 'sugar-calendar-lite' );
	}

	/**
	 * Input JSON-schema for this ability.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id' ],
			'properties'           => [
				'id' => [ 'type' => 'integer', 'minimum' => 1 ],
			],
		];
	}

	/**
	 * Output JSON-schema for this ability.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {

		return Formatter::event_schema();
	}

	/**
	 * Per-event permission check. Resolves the SC event ID to a WP post ID
	 * before current_user_can( 'read_event', $post_id ).
	 *
	 * A missing event answers 404, not 403: reporting "no permission" for an id
	 * that does not exist sends the caller looking for an access problem that
	 * isn't there. Existence is only disclosed to callers who already passed the
	 * ability's coarse gate.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input. Expected key: `id`.
	 *
	 * @return true|\WP_Error
	 */
	public function check_permission( $input = null ) {

		$args      = $this->normalize_input( $input );
		$id        = absint( $args['id'] ?? 0 );
		$query     = $id ? $this->events->get_event_query( [ 'id' => $id, 'number' => 1 ] ) : null;
		$event     = ( $query && ! empty( $query->items ) ) ? $query->items[0] : null;
		$object_id = $event ? absint( $event->object_id ) : 0;

		if ( ! $object_id ) {
			return $this->not_found();
		}

		if ( ! current_user_can( 'read_event', $object_id ) ) {
			return $this->forbidden(
				esc_html__( 'You do not have permission to view this event.', 'sugar-calendar-lite' )
			);
		}

		return true;
	}

	/**
	 * The event does not exist.
	 *
	 * @since 3.14.0
	 *
	 * @return \WP_Error
	 */
	private function not_found(): WP_Error {

		return new WP_Error(
			'sc_events_not_found',
			esc_html__( 'Event not found.', 'sugar-calendar-lite' ),
			[ 'status' => 404 ]
		);
	}

	/**
	 * Permission has already validated existence + read access, so a missing
	 * event here is defensive (race with deletion between callbacks).
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {

		$args  = $this->normalize_input( $input );
		$id    = absint( $args['id'] ?? 0 );
		$query = $this->events->get_event_query( [ 'id' => $id, 'number' => 1 ] );
		$event = $query->items[0] ?? null;

		if ( ! $event ) {
			return $this->not_found();
		}

		$map = $this->events->resolve_calendar_map( [ (int) $event->object_id ] );

		return $this->formatter->event( $event, $map );
	}
}
