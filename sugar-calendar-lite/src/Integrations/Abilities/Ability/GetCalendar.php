<?php
/**
 * The sc-events/get-calendar ability.
 *
 * @package Sugar_Calendar
 * @subpackage Integrations\Abilities\Ability
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractAbility;
use WP_Error;
use WP_Term;

/**
 * Get one calendar by ID or slug (exactly one). Requires `manage_event_calendars`.
 *
 * @since 3.12.0
 */
class GetCalendar extends AbstractAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'get-calendar';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'Get Calendar', 'sugar-calendar-lite' );
	}

	/**
	 * Human-readable ability description.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return esc_html__( 'Get one calendar by ID or slug.', 'sugar-calendar-lite' );
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
				'id'   => [ 'type' => 'integer', 'minimum' => 1 ],
				'slug' => [ 'type' => 'string', 'minLength' => 1 ],
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
			'manage_event_calendars',
			esc_html__( 'You do not have permission to view calendars.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Requires exactly one of `id` or `slug`.
	 *
	 * @since 3.12.0
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

		$term = $this->events->get_calendar( $id, $slug );

		if ( ! ( $term instanceof WP_Term ) ) {
			return new WP_Error(
				'sc_events_not_found',
				esc_html__( 'Calendar not found.', 'sugar-calendar-lite' ),
				[ 'status' => 404 ]
			);
		}

		return $this->formatter->calendar( $term );
	}
}
