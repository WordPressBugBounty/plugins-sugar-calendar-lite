<?php

namespace Sugar_Calendar\Integrations\Polylang;

/**
 * Polylang language scoping for our event queries.
 *
 * @since 3.17.0
 */
class PolylangQuery {

	/**
	 * Polylang's language taxonomy, also used as our Event_Query var.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const LANGUAGE_TAXONOMY = 'language';

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_admin_events_tables_base_query_args', [ $this, 'filter_events_by_admin_language' ] );
		add_filter( 'sugar_calendar_query_vars_contains_taxonomies', [ $this, 'add_language_taxonomy' ] );
		add_filter( 'sugar_calendar_join_by_taxonomy_term_args', [ $this, 'join_by_language' ], 10, 2 );
	}

	/**
	 * Limit the Events list to the language picked in the admin bar.
	 *
	 * @since 3.17.0
	 *
	 * @param array $args Event_Query arguments.
	 *
	 * @return array
	 */
	public function filter_events_by_admin_language( $args ) {

		$term_taxonomy_id = $this->get_admin_language_term_taxonomy_id();

		if ( $term_taxonomy_id ) {
			$args[ self::LANGUAGE_TAXONOMY ] = $term_taxonomy_id;
		}

		return $args;
	}

	/**
	 * Let our language query var reach the taxonomy JOIN.
	 *
	 * @since 3.17.0
	 *
	 * @param array $taxonomies Taxonomies the event query joins by.
	 *
	 * @return array
	 */
	public function add_language_taxonomy( $taxonomies ) {

		$taxonomies[] = self::LANGUAGE_TAXONOMY;

		return $taxonomies;
	}

	/**
	 * Join the event query by the language term it asks for.
	 *
	 * @since 3.17.0
	 *
	 * @param array  $args  Taxonomy query arguments.
	 * @param object $query Event_Query object.
	 *
	 * @return array
	 */
	public function join_by_language( $args, $query ) {

		if ( empty( $query->query_vars[ self::LANGUAGE_TAXONOMY ] ) ) {
			return $args;
		}

		$args[] = [
			'taxonomy' => self::LANGUAGE_TAXONOMY,
			'field'    => 'term_taxonomy_id',
			'terms'    => (int) $query->query_vars[ self::LANGUAGE_TAXONOMY ],
		];

		return $args;
	}

	/**
	 * Get the term taxonomy ID of the language picked in the admin bar.
	 *
	 * @since 3.17.0
	 *
	 * @return int 0 when all languages are shown.
	 */
	protected function get_admin_language_term_taxonomy_id() {

		$language = PolylangApi::pll()->filter_lang ?? null;

		if ( empty( $language->slug ) ) {
			return 0;
		}

		return (int) $language->get_tax_prop( self::LANGUAGE_TAXONOMY, 'term_taxonomy_id' );
	}
}
