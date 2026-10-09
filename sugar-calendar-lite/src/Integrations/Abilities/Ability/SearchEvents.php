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
use Sugar_Calendar\Integrations\Abilities\Formatter;
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
			'Full-text search over event titles, content and location. Without a status it searches every event except trashed ones for a user who can edit all events, and published events for anyone else. Returns parent event records; recurring occurrences are not expanded in v1.',
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
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'publish', 'draft', 'pending', 'future', 'private', 'trash', 'any' ],
					'description' => __( 'Which event statuses to search. Leave it out to find drafts as well as published events, so an event the user names is found whatever its status.', 'sugar-calendar-lite' ),
				],
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

		return Formatter::collection_schema( 'events', Formatter::event_schema() );
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

		// A name lookup must see drafts too: finding only the published event of two with the
		// same title is how an edit landed on the wrong one. build_query_args() would narrow a
		// non-public status to the caller's own events, so only an editor of all events gets it.
		if ( ! isset( $args['status'] ) && current_user_can( 'edit_others_events' ) ) {
			$args['status'] = 'any';
		}

		// build_query_args() owns the status gate: a non-public status is
		// narrowed to the caller's own events rather than refused, the same
		// way list-events scopes it.
		$query_args = $this->events->build_query_args( $args );

		if ( is_wp_error( $query_args ) ) {
			return $query_args;
		}

		// `location` is event meta, not an Event_Query search column, so a match on it
		// is resolved separately and unioned with the title/content match rather than
		// being expressible as one `search` call.
		$ids = array_values(
			array_unique(
				array_merge(
					$this->events->resolve_search_title_content_ids( $search, $query_args ),
					$this->events->resolve_search_location_ids( $search, $query_args )
				)
			)
		);

		if ( empty( $ids ) ) {
			return [ 'events' => [], 'total' => 0 ];
		}

		$query_args['id__in']  = $ids;
		$query_args['number']  = absint( $args['number'] ?? 20 );
		$query_args['offset']  = absint( $args['offset'] ?? 0 );
		$query_args['orderby'] = 'start';
		$query_args['order']   = 'DESC';

		$query = $this->events->get_event_query( $query_args );
		$total = $this->events->count( $query_args );
		$items = $query->items ?? [];

		return [
			'events' => $this->format_collection( $items ),
			'total'  => $total,
		];
	}
}
