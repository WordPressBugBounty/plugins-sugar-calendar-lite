<?php
/**
 * Ability: create an event calendar.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractWriteAbility;
use WP_Error;
use WP_Term;

/**
 * CreateCalendar — creates a calendar term, or returns the existing one when
 * the name already exists, so a repeated "Create new" click cannot duplicate.
 *
 * @since 3.14.0
 */
class CreateCalendar extends AbstractWriteAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'create-calendar';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return __( 'Create Calendar', 'sugar-calendar-lite' );
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
			'Creates an event calendar. Returns the existing calendar when one already has that name.',
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

		$properties = [
			'name' => [
				'type'      => 'string',
				'minLength' => 1,
				'maxLength' => 200,
			],
		];

		// A flat taxonomy (e.g. tags) has no term hierarchy for `parent` to place a term in.
		// Ordering dependency: this reads runtime taxonomy state, so the taxonomy must
		// already be registered (SCE does it on `init:9`) before `wp_abilities_api_init`
		// runs. Verified true today; if that order ever flips, `parent` would vanish from
		// the schema while `execute()` still accepts it.
		if ( is_taxonomy_hierarchical( $this->taxonomy() ) ) {
			$properties['parent'] = [
				'type'    => 'integer',
				'minimum' => 0,
				'default' => 0,
			];
		}

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'name' ],
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
				'term_id' => [ 'type' => 'integer' ],
				'name'    => [ 'type' => 'string' ],
				'created' => [ 'type' => 'boolean' ],
			],
		];
	}

	/**
	 * The taxonomy this ability writes to.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function taxonomy(): string {

		return sugar_calendar_get_calendar_taxonomy_id();
	}

	/**
	 * Requires `edit_terms` on the calendar taxonomy.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|\WP_Error
	 */
	public function check_permission( $input = null ) {

		$taxonomy = get_taxonomy( $this->taxonomy() );

		if ( ! $taxonomy ) {
			return $this->forbidden(
				__( 'That taxonomy is not available on this site.', 'sugar-calendar-lite' )
			);
		}

		return $this->require_cap( $taxonomy->cap->edit_terms, $this->denied_message() );
	}

	/**
	 * Message shown when the caller may not create terms in this taxonomy.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function denied_message(): string {

		return __( 'You do not have permission to create calendars.', 'sugar-calendar-lite' );
	}

	/**
	 * Create the term, or return the existing one with that name.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args Normalized ability input.
	 *
	 * @return array|\WP_Error
	 */
	protected function run( array $args ) {

		$name = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';

		if ( $name === '' ) {
			return $this->invalid_input( __( 'The name cannot be empty.', 'sugar-calendar-lite' ) );
		}

		$taxonomy = $this->taxonomy();
		$parent   = 0;

		if ( is_taxonomy_hierarchical( $taxonomy ) && isset( $args['parent'] ) ) {
			$parent = absint( $args['parent'] );
		}

		if ( $parent > 0 ) {
			// wp_insert_term()'s own guard is term_exists( (int) $parent ) with no taxonomy
			// argument, so a term id from an unrelated taxonomy passes silently.
			$parent_term = get_term( $parent, $taxonomy );

			if ( ! ( $parent_term instanceof WP_Term ) ) {
				return $this->invalid_input(
					__( 'The parent must be an existing term in this same taxonomy.', 'sugar-calendar-lite' )
				);
			}
		}

		$existing = $this->find_existing_term( $name, $taxonomy, $parent );

		if ( $existing instanceof WP_Term ) {
			return [
				'term_id' => (int) $existing->term_id,
				'name'    => $existing->name,
				'created' => false,
			];
		}

		$term = wp_insert_term( $name, $taxonomy, [ 'parent' => $parent ] );

		if ( is_wp_error( $term ) ) {
			$collision = $this->term_from_insert_error( $term, $taxonomy );

			if ( $collision instanceof WP_Term ) {
				return [
					'term_id' => (int) $collision->term_id,
					'name'    => $collision->name,
					'created' => false,
				];
			}

			return $this->write_failed( $term->get_error_message() );
		}

		return [
			'term_id' => (int) $term['term_id'],
			'name'    => $name,
			'created' => true,
		];
	}

	/**
	 * Recover the colliding term from a `wp_insert_term()` duplicate error.
	 *
	 * `find_existing_term()` can miss a term the insert then refuses: names are
	 * stored kses-encoded (`Rock &amp; Roll`) while `WP_Term_Query`'s `name`
	 * needle is only `sanitize_text_field()`ed. Two concurrent calls collide the
	 * same way. Either case is an existing term, not a write failure.
	 *
	 * @since 3.14.0
	 *
	 * @param WP_Error $error    Error returned by wp_insert_term().
	 * @param string   $taxonomy Taxonomy id.
	 *
	 * @return WP_Term|null
	 */
	private function term_from_insert_error( WP_Error $error, string $taxonomy ) {

		if ( $error->get_error_code() !== 'term_exists' ) {
			return null;
		}

		$term_id = (int) $error->get_error_data( 'term_exists' );
		$term    = $term_id > 0 ? get_term( $term_id, $taxonomy ) : null;

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Find a term with this name, scoped to the parent for a hierarchical
	 * taxonomy.
	 *
	 * `get_term_by( 'name', … )` alone ignores hierarchy, so "create Music
	 * under Concerts" would return an unrelated top-level "Music" — a
	 * hierarchical taxonomy allows the same name under different parents.
	 *
	 * @since 3.14.0
	 *
	 * @param string $name      Term name.
	 * @param string $taxonomy  Taxonomy id.
	 * @param int    $parent_id Requested parent term id, 0 for top level.
	 *
	 * @return WP_Term|null
	 */
	private function find_existing_term( string $name, string $taxonomy, int $parent_id ) {

		$args = [
			'taxonomy'   => $taxonomy,
			'name'       => $name,
			'hide_empty' => false,
			'number'     => 1,
		];

		// A flat taxonomy (tags) has no parent column to scope by.
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$args['parent'] = $parent_id;
		}

		$terms = get_terms( $args );

		return ( is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof WP_Term ) ? $terms[0] : null;
	}
}
