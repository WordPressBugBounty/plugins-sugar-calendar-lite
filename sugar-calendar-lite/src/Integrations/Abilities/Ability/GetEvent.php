<?php
/**
 * The sc-events/get-event ability.
 *
 * @package Sugar_Calendar
 * @subpackage Integrations\Abilities\Ability
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractAbility;

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

		return [ 'type' => 'object' ];
	}

	/**
	 * Per-event permission check. Resolves the SC event ID to a WP post ID
	 * before current_user_can( 'read_event', $post_id ). Missing IDs are
	 * forbidden by design.
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

		if ( ! $object_id || ! current_user_can( 'read_event', $object_id ) ) {
			return $this->forbidden(
				esc_html__( 'You do not have permission to view this event.', 'sugar-calendar-lite' )
			);
		}

		return true;
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
			return $this->forbidden(
				esc_html__( 'You do not have permission to view this event.', 'sugar-calendar-lite' )
			);
		}

		$map = $this->events->resolve_calendar_map( [ (int) $event->object_id ] );

		return $this->formatter->event( $event, $map );
	}
}
