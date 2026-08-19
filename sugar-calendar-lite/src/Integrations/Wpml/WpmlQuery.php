<?php

namespace Sugar_Calendar\Integrations\Wpml;

use WP_Term;

/**
 * WPML Query Integration.
 *
 * Handles remapping event queries to original (default-language) posts.
 *
 * @since 3.12.0
 */
class WpmlQuery {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		// Centralize WPML object remap for event lookups.
		add_filter( 'sugar_calendar_get_events_args', [ $this, 'filter_get_events_args_wpml' ], 5 );

		// Scope the admin events list tables to the current admin language.
		add_filter( 'sugar_calendar_admin_events_tables_base_query_args', [ $this, 'filter_admin_events_query_args_wpml' ], 5 );

		// Expand term IDs to include translations before WP_Tax_Query is created.
		add_filter( 'sugar_calendar_taxonomy_relationships_query_args', [ $this, 'expand_taxonomy_query_args' ], 10, 2 );

		// Also hook into SQL generation to add missing original term_taxonomy_ids.
		add_filter( 'sugar_calendar_taxonomy_relationships_query_clauses', [ $this, 'expand_taxonomy_sql_clauses' ], 10, 3 );

		// WPML support for Event List block upcoming events query.
		add_filter( 'sugar_calendar_helpers_upcoming_events_calendar_ids', [ self::class, 'expand_term_ids_with_originals' ], 10, 3 );
		add_filter( 'sugar_calendar_helpers_upcoming_events_tag_ids', [ self::class, 'expand_term_ids_with_originals' ], 10, 3 );
	}

	/**
	 * Remap event query args to the original (default-language) post when querying
	 * by post object on translated pages. This ensures callers of
	 * sugar_calendar_get_event_by_object() transparently receive the canonical
	 * Sugar Calendar event row.
	 *
	 * @since 3.12.0
	 *
	 * @param array $args Event query args.
	 *
	 * @return array
	 */
	public function filter_get_events_args_wpml( $args ) {

		// Require a post object lookup with a concrete object_id.
		if ( empty( $args['object_id'] ) || ( $args['object_type'] ?? 'post' ) !== 'post' ) {
			return $args;
		}

		// Ensure we are resolving SC events.
		if ( ( $args['object_subtype'] ?? '' ) !== sugar_calendar_get_event_post_type_id() ) {
			return $args;
		}

		$object_id = absint( $args['object_id'] );

		$mapped = Wpml::get_original_post_id( $object_id, sugar_calendar_get_event_post_type_id() );

		if ( ! empty( $mapped ) && $mapped !== $object_id ) {
			$args['object_id'] = $mapped;
		}

		return $args;
	}

	/**
	 * Scope the admin events list tables to the current admin language.
	 *
	 * The events tables query the custom events table via Event_Query, which
	 * never passes through WPML's WP_Query language filter, so every event shows
	 * in every language. This restricts the query to events that have a
	 * translation in the current language — matching how WPML filters standard
	 * post list tables (e.g. Venues).
	 *
	 * @since 3.12.0
	 *
	 * @param array $args Event_Query args.
	 *
	 * @return array
	 */
	public function filter_admin_events_query_args_wpml( $args ) {

		$allowed_ids = Wpml::get_admin_language_scoped_event_ids();

		// Null means no scoping applies (default language or WPML inactive).
		if ( is_null( $allowed_ids ) ) {
			return $args;
		}

		// Restrict to translated events. The 0 sentinel forces an empty result
		// set when nothing is translated, instead of falling back to all events.
		$args['object_id__in'] = ! empty( $allowed_ids )
			? $allowed_ids
			: [ 0 ];

		return $args;
	}

	/**
	 * Expand taxonomy query arguments to include translated terms.
	 *
	 * This filter runs BEFORE WP_Tax_Query is created, allowing us to modify
	 * the term IDs to include original language terms. This is cleaner than
	 * modifying the SQL strings after they're generated.
	 *
	 * @since 3.12.0
	 *
	 * @param array        $args  Taxonomy query arguments.
	 * @param object|Query $query Event_Query object.
	 *
	 * @return array Modified taxonomy query arguments.
	 */
	public function expand_taxonomy_query_args( $args, $query ) {

		if ( empty( $args ) ) {
			return $args;
		}

		// Loop through each taxonomy query and expand term IDs.
		foreach ( $args as &$tax_query_arg ) {

			// Skip if no terms specified.
			if ( empty( $tax_query_arg['terms'] ) ) {
				continue;
			}

			// Skip if operator is NOT EXISTS or EXISTS (no terms to expand).
			if ( isset( $tax_query_arg['operator'] ) && in_array( $tax_query_arg['operator'], [ 'NOT EXISTS', 'EXISTS' ], true ) ) {
				continue;
			}

			// Expand term IDs to include originals.
			$terms = (array) $tax_query_arg['terms'];

			$expanded_terms = $this->expand_term_ids_with_originals( $terms );

			$tax_query_arg['terms'] = $expanded_terms;
		}

		return $args;
	}

	/**
	 * Expand taxonomy SQL clauses to include original language terms.
	 *
	 * This runs AFTER WP_Tax_Query generates SQL, because WP_Tax_Query uses get_terms()
	 * internally which gets filtered by WPML. So we need to expand the SQL directly.
	 *
	 * @since 3.12.0
	 *
	 * @param array  $sql_clauses Array with 'join' and 'where' keys.
	 * @param object $tax_query   The WP_Tax_Query object.
	 * @param object $query       The Event_Query object.
	 *
	 * @return array Modified SQL clauses.
	 */
	public function expand_taxonomy_sql_clauses( $sql_clauses, $tax_query, $query ) {

		if ( empty( $sql_clauses['where'] ) ) {
			return $sql_clauses;
		}

		// WPML-specific: WP_Tax_Query normally resolves terms via get_terms(), which WPML
		// filters down to the current language and drops the original-language term IDs.
		// Resolve each clause's terms (slug, name, or term_id) to term_taxonomy_ids in the
		// current language, add their original-language equivalents, then pin the resolved
		// IDs and disable include_children so get_sql() uses them verbatim instead of
		// re-resolving through the WPML-filtered get_terms() path (which, for a slug clause,
		// would coerce the unresolved slug to 0 and match nothing).
		foreach ( $tax_query->queries as $i => $clause ) {

			if ( ! is_array( $clause ) || empty( $clause['taxonomy'] ) || empty( $clause['terms'] ) ) {
				continue;
			}

			$field = isset( $clause['field'] ) ? $clause['field'] : 'term_id';

			$ttids = self::expand_term_ids_with_originals(
				$this->resolve_terms_to_term_taxonomy_ids( (array) $clause['terms'], $clause['taxonomy'], $field )
			);

			if ( empty( $ttids ) ) {
				continue;
			}

			$tax_query->queries[ $i ]['field']            = 'term_taxonomy_id';
			$tax_query->queries[ $i ]['terms']            = $ttids;
			$tax_query->queries[ $i ]['include_children'] = false;
		}

		// IMPORTANT: drop cached resolution so get_sql() recomputes from modified clauses.
		$tax_query->queried_terms = [];

		// Now regenerate.
		return $tax_query->get_sql( 'sc_e', 'object_id' );
	}

	/**
	 * Resolve taxonomy query terms to term_taxonomy_ids in the current language.
	 *
	 * WP_Tax_Query clauses can express their terms as slugs, names, or term IDs. WPML
	 * resolves the slug/name/ID to the term in the current language, which is what we
	 * want before adding the original-language equivalents in expand_taxonomy_sql_clauses().
	 *
	 * @since 3.12.0
	 *
	 * @param array  $terms    Term identifiers (slugs, names, or term IDs).
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $field    Field the terms are expressed in ('slug', 'name',
	 *                         'term_id', or 'term_taxonomy_id').
	 *
	 * @return int[] Resolved term_taxonomy_ids. Unresolvable terms are skipped.
	 */
	private function resolve_terms_to_term_taxonomy_ids( $terms, $taxonomy, $field ) {

		// Already term_taxonomy_ids: cast and return.
		if ( $field === 'term_taxonomy_id' ) {
			return array_map( 'intval', $terms );
		}

		// Map the WP_Tax_Query field name to a get_term_by() field.
		$by = in_array( $field, [ 'id', 'term_id' ], true ) ? 'id' : $field;

		$ttids = [];

		foreach ( $terms as $term ) {

			$term_obj = get_term_by( $by, $term, $taxonomy );

			if ( $term_obj instanceof WP_Term ) {
				$ttids[] = (int) $term_obj->term_taxonomy_id;
			}
		}

		return $ttids;
	}

	/**
	 * Expand term IDs to include original language terms.
	 *
	 * For each term ID or term_taxonomy_id, check if it's a translation. If so, add the original
	 * ID to the list. This allows queries using translated IDs to also find posts tagged
	 * with the original IDs.
	 *
	 * Note: We don't need to modify the JOIN clause for standard queries because
	 * Sugar Calendar events are always stored with original post IDs in wp_sc_events.
	 * The term expansion is sufficient.
	 *
	 * @since 3.12.0
	 *
	 * @param array $ids        Array of term IDs or term taxonomy IDs.
	 * @param array $args       Optional. Additional arguments (unused, but needed for hook signature).
	 * @param array $attributes Optional. Block attributes (unused, but needed for hook signature).
	 *
	 * @return array Expanded array including original IDs.
	 */
	public static function expand_term_ids_with_originals( $ids, $args = [], $attributes = [] ) {

		// If WPML is not active, return IDs as-is.
		if ( ! Wpml::is_wpml_active() ) {
			return $ids;
		}

		if ( empty( $ids ) ) {
			return $ids;
		}

		$expanded = array_merge( $ids, self::get_original_ids_for_translations( $ids, 'tax_' ) );

		return array_unique( $expanded );
	}

	/**
	 * Expand post IDs to include original language posts.
	 *
	 * For each post ID (venues or speakers), check if it's a translation. If so, add the original
	 * post ID to the list. This allows queries using translated post IDs to
	 * also find events associated with the original post IDs.
	 *
	 * @since 3.12.0
	 *
	 * @param array $post_ids   Array of post IDs.
	 * @param array $args       Optional. Additional arguments (unused, but needed for hook signature).
	 * @param array $attributes Optional. Block attributes (unused, but needed for hook signature).
	 *
	 * @return array Expanded array including original post IDs.
	 */
	public static function expand_post_ids_with_originals( $post_ids, $args = [], $attributes = [] ) {

		// If WPML is not active, return post IDs as-is.
		if ( ! Wpml::is_wpml_active() ) {
			return $post_ids;
		}

		if ( empty( $post_ids ) ) {
			return $post_ids;
		}

		$expanded = array_merge( $post_ids, self::get_original_ids_for_translations( $post_ids, 'post_' ) );

		return array_unique( $expanded );
	}

	/**
	 * Find the original (default-language) element IDs for a set of translated IDs.
	 *
	 * Shared query backing expand_term_ids_with_originals() and
	 * expand_post_ids_with_originals(). For each translated element in $ids, looks up
	 * the original element in the same WPML translation group (trid), i.e. the row
	 * whose source_language_code IS NULL.
	 *
	 * @since 3.12.0
	 *
	 * @param array  $ids                 Array of element IDs (term taxonomy IDs or post IDs).
	 * @param string $element_type_prefix WPML element_type prefix to match, e.g. 'tax_' or 'post_'.
	 *
	 * @return array Array of original element IDs (integers). Empty if WPML's table is missing.
	 */
	private static function get_original_ids_for_translations( $ids, $element_type_prefix ) {

		global $wpdb;

		// Normalize to integers and bail when empty. This keeps the shared helper
		// self-safe regardless of caller: a non-numeric value can't reach the query,
		// and the IN() list below can never collapse to `IN ()` (a SQL syntax error).
		$ids = array_map( 'intval', (array) $ids );

		if ( empty( $ids ) ) {
			return [];
		}

		// Check if WPML translations table exists.
		$table_name = $wpdb->prefix . 'icl_translations';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query against WPML tables.
			return [];
		}

		// One %d placeholder per ID so the IN() list is fully prepared.
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// Find each translation's original (source_language_code IS NULL) within the same trid.
		// The IN() list holds a dynamic number of %d placeholders, all bound through prepare();
		// phpcs can't statically verify the generated placeholder string, hence the disable below.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT t2.element_id AS original_id
			FROM {$wpdb->prefix}icl_translations t1
			INNER JOIN {$wpdb->prefix}icl_translations t2
				ON t1.trid = t2.trid
				AND t2.source_language_code IS NULL
			WHERE t1.element_id IN ({$placeholders})
				AND t1.element_type LIKE %s
				AND t1.source_language_code IS NOT NULL",
			array_merge( $ids, [ $wpdb->esc_like( $element_type_prefix ) . '%' ] )
		);

		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query against WPML tables.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', wp_list_pluck( $results, 'original_id' ) );
	}
}
