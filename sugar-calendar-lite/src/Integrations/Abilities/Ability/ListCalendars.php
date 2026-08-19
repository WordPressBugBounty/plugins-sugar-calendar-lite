<?php
/**
 * The sc-events/list-calendars ability.
 *
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractAbility;
use WP_Term;

/**
 * List all calendars (event taxonomy terms). Requires `manage_event_calendars`.
 *
 * @since 3.12.0
 */
class ListCalendars extends AbstractAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'list-calendars';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'List Calendars', 'sugar-calendar-lite' );
	}

	/**
	 * Human-readable ability description.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return esc_html__( 'List all calendars (event taxonomy terms).', 'sugar-calendar-lite' );
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
	 * @since 3.12.0
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {

		return [
			'type'       => 'object',
			'properties' => [
				'calendars' => [ 'type' => 'array' ],
				'total'     => [ 'type' => 'integer' ],
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
			'manage_event_calendars',
			esc_html__( 'You do not have permission to view calendars.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Execute the calendar listing and return the calendar collection.
	 *
	 * @since 3.12.0
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

		$terms = $this->events->get_calendars( $query_args );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [ 'calendars' => [], 'total' => 0 ];
		}

		$out = [];

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$out[] = $this->formatter->calendar( $term );
			}
		}

		return [
			'calendars' => $out,
			'total'     => count( $out ),
		];
	}
}
