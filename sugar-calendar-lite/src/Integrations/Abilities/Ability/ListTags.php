<?php
/**
 * The sc-events/list-tags ability.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractAbility;
use Sugar_Calendar\Integrations\Abilities\Formatter;
use WP_Term;

/**
 * List event tags. Requires `manage_event_calendars` — the capability the tag
 * taxonomy itself registers as its `manage_terms`.
 *
 * @since 3.14.0
 */
class ListTags extends AbstractAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'list-tags';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'List tags', 'sugar-calendar-lite' );
	}

	/**
	 * Ability description shown to callers.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return esc_html__(
			'List event tags, optionally filtered by a search term.',
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

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'search'     => [ 'type' => 'string' ],
				'number'     => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 200, 'default' => 100 ],
				'orderby'    => [
					'type'    => 'string',
					'enum'    => [ 'name', 'count', 'term_id' ],
					'default' => 'name',
				],
				'hide_empty' => [ 'type' => 'boolean', 'default' => false ],
			],
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

		return Formatter::collection_schema( 'tags', Formatter::term_schema() );
	}

	/**
	 * Capability check for this ability.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission( $input = null ) {

		return $this->require_cap(
			'manage_event_calendars',
			esc_html__( 'You do not have permission to view tags.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Return the matching tags.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array
	 */
	public function execute( $input = null ): array {

		$args = $this->normalize_input( $input );

		$query_args = [
			'number'     => absint( $args['number'] ?? 100 ),
			'orderby'    => sanitize_key( $args['orderby'] ?? 'name' ),
			'hide_empty' => ! empty( $args['hide_empty'] ),
		];

		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );

		if ( $search !== '' ) {
			$query_args['search'] = $search;
		}

		$terms = $this->events->get_tags( $query_args );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [ 'tags' => [], 'total' => 0 ];
		}

		$out = [];

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$out[] = $this->formatter->tag( $term );
			}
		}

		return [
			'tags'  => $out,
			'total' => $this->events->count_tags( $query_args ),
		];
	}
}
