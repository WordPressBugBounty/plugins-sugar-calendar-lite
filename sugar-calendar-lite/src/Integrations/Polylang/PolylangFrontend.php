<?php

namespace Sugar_Calendar\Integrations\Polylang;

use Sugar_Calendar\Event_Query;

/**
 * Polylang support for our front-end pages and blocks.
 *
 * @since 3.17.0
 */
class PolylangFrontend {

	/**
	 * Event query var holding the language a block lists.
	 *
	 * @since 3.17.0
	 *
	 * @var string
	 */
	const QUERY_VAR = 'polylang_lang';

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_block_common_localized_script_data', [ $this, 'add_language_to_ajax_url' ] );
		add_filter( 'sugar_calendar_block_calendar_loader_localized_script_data', [ $this, 'add_language_to_ajax_url' ] );
		add_filter( 'pll_filtered_rest_routes', [ $this, 'add_block_renderer_routes' ] );

		add_filter( 'sugar_calendar_helpers_upcoming_events_where', [ $this, 'add_language_to_upcoming_events_where' ], 10, 2 );

		add_filter( 'sugar_calendar_database_query_default_variables', [ $this, 'add_language_query_var' ], 10, 2 );
		add_filter( 'sugar_calendar_get_events_args', [ $this, 'set_block_query_language' ] );
		add_filter( 'sugar_calendar_event_query_parse_where_query_clauses', [ $this, 'add_language_query_clause' ], 10, 2 );

		add_filter( 'sugar_calendar_helpers_upcoming_events_tag_ids', [ $this, 'map_term_ids' ] );
		add_filter( 'sugar_calendar_block_common_abstract_block_get_tags', [ $this, 'map_term_ids' ] );
	}

	/**
	 * Send the page language with the blocks' AJAX requests.
	 *
	 * @since 3.17.0
	 *
	 * @param array $data Localized script data.
	 *
	 * @return array
	 */
	public function add_language_to_ajax_url( $data ) {

		$language = $this->get_current_language();

		if ( empty( $language ) || empty( $data['ajax_url'] ) ) {
			return $data;
		}

		$data['ajax_url'] = add_query_arg( 'lang', $language->slug, $data['ajax_url'] );

		return $data;
	}

	/**
	 * Let Polylang send the edited page's language with the block previews.
	 *
	 * @since 3.17.0
	 *
	 * @param string[] $routes REST routes Polylang filters by language.
	 *
	 * @return string[]
	 */
	public function add_block_renderer_routes( $routes ) {

		$routes['sugar_calendar_block']            = 'wp/v2/block-renderer/sugar-calendar/block';
		$routes['sugar_calendar_event_list_block'] = 'wp/v2/block-renderer/sugar-calendar/event-list-block';

		return $routes;
	}

	/**
	 * Limit a block's upcoming events to the page language.
	 *
	 * @since 3.17.0
	 *
	 * @param string $where_query WHERE clause.
	 * @param array  $args        Query arguments.
	 *
	 * @return string
	 */
	public function add_language_to_upcoming_events_where( $where_query, $args ) {

		if ( empty( $args['block_query'] ) ) {
			return $where_query;
		}

		global $wpdb;

		$condition = $this->get_language_condition( $wpdb->prefix . 'sc_events.object_id' );

		return empty( $condition ) ? $where_query : $where_query . ' AND ' . $condition;
	}

	/**
	 * Register the language query var, so the event query cache is kept per language.
	 *
	 * @since 3.17.0
	 *
	 * @param array  $defaults Default query vars.
	 * @param object $query    Query instance.
	 *
	 * @return array
	 */
	public function add_language_query_var( $defaults, $query ) {

		if ( $query instanceof Event_Query ) {
			$defaults[ self::QUERY_VAR ] = '';
		}

		return $defaults;
	}

	/**
	 * Put the page language on a block's event query.
	 *
	 * @since 3.17.0
	 *
	 * @param array $query_args Event query arguments.
	 *
	 * @return array
	 */
	public function set_block_query_language( $query_args ) {

		$language = $this->get_current_language();

		if ( ! empty( $query_args['block_query'] ) && ! empty( $language ) ) {
			$query_args[ self::QUERY_VAR ] = $language->slug;
		}

		return $query_args;
	}

	/**
	 * Limit an event query to the language in its query var.
	 *
	 * @since 3.17.0
	 *
	 * @param array       $clauses JOIN and WHERE clauses.
	 * @param Event_Query $query   Event query.
	 *
	 * @return array
	 */
	public function add_language_query_clause( $clauses, $query ) {

		if ( empty( $query->query_vars[ self::QUERY_VAR ] ) ) {
			return $clauses;
		}

		$condition = $this->get_language_condition( $query->table_alias . '.object_id', $query->query_vars[ self::QUERY_VAR ] );

		if ( ! empty( $condition ) ) {
			$clauses['where'][ self::QUERY_VAR ] = $condition;
		}

		return $clauses;
	}

	/**
	 * Swap term ids for their translation in the page language, keeping ids that have none.
	 *
	 * @since 3.17.0
	 *
	 * @param int[] $term_ids Term ids.
	 *
	 * @return int[]
	 */
	public function map_term_ids( $term_ids ) {

		return $this->map_ids( (array) $term_ids, PolylangApi::pll()->model->term );
	}

	/**
	 * Swap post ids for their translation in the page language, keeping ids that have none.
	 *
	 * @since 3.17.0
	 *
	 * @param int[] $post_ids Post ids.
	 *
	 * @return int[]
	 */
	public function map_post_ids( $post_ids ) {

		return $this->map_ids( (array) $post_ids, PolylangApi::pll()->model->post );
	}

	/**
	 * Build the SQL condition matching posts in a language, as Polylang does.
	 *
	 * Empty when events aren't translated or no language is set, so nothing is hidden.
	 *
	 * @since 3.17.0
	 *
	 * @param string $column   Column holding the event post id.
	 * @param string $language Language slug. Defaults to the page language.
	 *
	 * @return string
	 */
	protected function get_language_condition( $column, $language = '' ) {

		global $wpdb;

		$language = empty( $language ) ? $this->get_current_language() : PolylangApi::pll()->model->get_language( $language );

		if ( empty( $language ) || ! pll_is_translated_post_type( sugar_calendar_get_event_post_type_id() ) ) {
			return '';
		}

		return $wpdb->prepare(
			$column . ' IN ( SELECT object_id FROM ' . $wpdb->term_relationships . ' WHERE term_taxonomy_id = %d )', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Column and table names are code-controlled.
			(int) $language->get_tax_prop( 'language', 'term_taxonomy_id' )
		);
	}

	/**
	 * Get the page language.
	 *
	 * @since 3.17.0
	 *
	 * @return object|null Polylang language, or null when none is set.
	 */
	protected function get_current_language() {

		$language = PolylangApi::pll()->curlang ?? null;

		return is_object( $language ) ? $language : null;
	}

	/**
	 * Swap ids for their translation in the page language through a Polylang model.
	 *
	 * @since 3.17.0
	 *
	 * @param array  $ids   Ids.
	 * @param object $model Polylang post or term model.
	 *
	 * @return int[]
	 */
	private function map_ids( $ids, $model ) {

		$language = $this->get_current_language();

		if ( empty( $language ) ) {
			return $ids;
		}

		foreach ( $ids as $key => $id ) {
			$translation = (int) $model->get( (int) $id, $language );
			$ids[ $key ] = empty( $translation ) ? (int) $id : $translation;
		}

		return $ids;
	}
}
