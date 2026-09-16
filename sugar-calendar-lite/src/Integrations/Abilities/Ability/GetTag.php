<?php
/**
 * The sc-events/get-tag ability.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractAbility;
use Sugar_Calendar\Integrations\Abilities\Formatter;
use WP_Error;
use WP_Term;

/**
 * Fetch one event tag by id or slug. Requires `manage_event_calendars` — the
 * capability the tag taxonomy itself registers as its `manage_terms`.
 *
 * @since 3.14.0
 */
class GetTag extends AbstractAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'get-tag';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'Get tag', 'sugar-calendar-lite' );
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
			'Fetch a single event tag by id or slug.',
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
				'id'   => [ 'type' => 'integer', 'minimum' => 1 ],
				'slug' => [ 'type' => 'string' ],
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

		return Formatter::term_schema();
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
	 * Requires exactly one of `id` or `slug`.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {

		$args     = $this->normalize_input( $input );
		$id       = absint( $args['id'] ?? 0 );
		$slug     = sanitize_text_field( (string) ( $args['slug'] ?? '' ) );
		$has_id   = $id > 0;
		$has_slug = '' !== $slug;

		if ( $has_id === $has_slug ) {
			return new WP_Error(
				'sc_events_invalid_input',
				esc_html__( 'Provide exactly one of id or slug.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		$term = $this->events->get_tag( $id, $slug );

		if ( ! ( $term instanceof WP_Term ) ) {
			return new WP_Error(
				'sc_events_not_found',
				esc_html__( 'Tag not found.', 'sugar-calendar-lite' ),
				[ 'status' => 404 ]
			);
		}

		return $this->formatter->tag( $term );
	}
}
