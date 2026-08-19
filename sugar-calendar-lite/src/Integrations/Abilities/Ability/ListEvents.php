<?php
/**
 * The sc-events/list-events ability.
 *
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractAbility;

/**
 * List events with filters and pagination. Requires `edit_posts`.
 *
 * @since 3.12.0
 */
class ListEvents extends AbstractAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'list-events';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'List Events', 'sugar-calendar-lite' );
	}

	/**
	 * Human-readable ability description.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return esc_html__(
			'List events with filters and pagination. Returns parent event records; recurring occurrences are not expanded in v1.',
			'sugar-calendar-lite'
		);
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
			'properties'           => [
				'status'       => [
					'type'    => 'string',
					'enum'    => [ 'publish', 'draft', 'pending', 'future', 'private', 'trash', 'any' ],
					'default' => 'publish',
				],
				'calendar_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
				'start_after'  => [ 'type' => 'string', 'format' => 'date-time' ],
				'start_before' => [ 'type' => 'string', 'format' => 'date-time' ],
				'orderby'      => [
					'type'    => 'string',
					'enum'    => [ 'start', 'end', 'title', 'date_created', 'date_modified' ],
					'default' => 'start',
				],
				'order'        => [
					'type'    => 'string',
					'enum'    => [ 'ASC', 'DESC' ],
					'default' => 'ASC',
				],
				'number'       => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				'offset'       => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
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

		return [
			'type'       => 'object',
			'properties' => [
				'events' => [ 'type' => 'array' ],
				'total'  => [ 'type' => 'integer' ],
			],
		];
	}

	/**
	 * Capability check for this ability.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|\WP_Error
	 */
	public function check_permission( $input = null ) {

		return $this->require_cap(
			'edit_posts',
			esc_html__( 'You do not have permission to view events.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Execute the list-events query and return the event collection.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {

		$args       = $this->normalize_input( $input );
		$query_args = $this->events->build_query_args( $args );

		if ( is_wp_error( $query_args ) ) {
			return $query_args;
		}

		if ( ! empty( $args['calendar_id'] ) ) {
			$post_ids = $this->events->resolve_calendar_post_ids( absint( $args['calendar_id'] ) );

			if ( ! empty( $query_args['object_id__in'] ) ) {
				// build_query_args() already restricted object_id__in to the
				// caller's own events (a non-privileged requester asked for a
				// non-public status) — intersect rather than overwrite, or
				// combining calendar_id with status would bypass that gate.
				$post_ids = array_intersect( $post_ids, $query_args['object_id__in'] );
			}

			if ( empty( $post_ids ) ) {
				return [ 'events' => [], 'total' => 0 ];
			}

			$query_args['object_id__in'] = array_values( $post_ids );
		}

		$query_args['number']  = absint( $args['number'] ?? 20 );
		$query_args['offset']  = absint( $args['offset'] ?? 0 );
		$query_args['orderby'] = sanitize_key( $args['orderby'] ?? 'start' );
		$query_args['order']   = strtoupper( sanitize_text_field( $args['order'] ?? 'ASC' ) ) === 'DESC' ? 'DESC' : 'ASC';

		$query = $this->events->get_event_query( $query_args );
		$total = $this->events->count( $query_args );
		$items = $query->items ?? [];

		return [
			'events' => $this->format_collection( $items ),
			'total'  => $total,
		];
	}
}
