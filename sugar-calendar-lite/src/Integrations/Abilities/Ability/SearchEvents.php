<?php
/**
 * The sc-events/search-events ability.
 *
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractAbility;
use WP_Error;

/**
 * Full-text search over event titles and content. Requires `edit_posts`.
 *
 * @since 3.12.0
 */
class SearchEvents extends AbstractAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'search-events';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'Search Events', 'sugar-calendar-lite' );
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
			'Full-text search over event titles and content. Returns parent event records; recurring occurrences are not expanded in v1.',
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
			'required'             => [ 'search' ],
			'properties'           => [
				'search' => [ 'type' => 'string', 'minLength' => 1 ],
				'number' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				'offset' => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
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
	 * @return true|WP_Error
	 */
	public function check_permission( $input = null ) {

		return $this->require_cap(
			'edit_posts',
			esc_html__( 'You do not have permission to view events.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Execute the full-text search and return the event collection.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {

		$args   = $this->normalize_input( $input );
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );

		if ( '' === $search ) {
			return new WP_Error(
				'sc_events_invalid_input',
				esc_html__( 'Search query is required.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		$query_args = [
			'search'         => $search,
			'search_columns' => [ 'title', 'content' ],
			'status'         => 'publish',
			'number'         => absint( $args['number'] ?? 20 ),
			'offset'         => absint( $args['offset'] ?? 0 ),
			'orderby'        => 'start',
			'order'          => 'DESC',
		];

		$query = $this->events->get_event_query( $query_args );
		$total = $this->events->count( $query_args );
		$items = $query->items ?? [];

		return [
			'events' => $this->format_collection( $items ),
			'total'  => $total,
		];
	}
}
