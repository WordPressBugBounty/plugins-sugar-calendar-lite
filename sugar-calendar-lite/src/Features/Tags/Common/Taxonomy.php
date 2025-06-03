<?php

namespace Sugar_Calendar\Features\Tags\Common;

use Sugar_Calendar\Features\Tags\Common\Helpers;

/**
 * Tags taxonomy class.
 *
 * @since 3.7.0
 */
class Taxonomy {

	/**
	 * Register hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		add_action( 'init', [ $this, 'register_taxonomy' ] );
		add_action( 'init', [ $this, 'prioritize_sc_event_tags_rewrite_rules' ], 100 );

		add_filter( 'sugar_calendar_query_vars_contains_taxonomies', [ $this, 'add_tags_taxonomies' ], 10 );
		add_filter( 'sugar_calendar_join_by_taxonomy_term_args', [ $this, 'join_by_taxonomy_term_args' ], 10, 2 );
	}

	/**
	 * Register the taxonomy.
	 *
	 * @since 3.7.0
	 */
	public function register_taxonomy() {

		// Labels.
		$labels = Helpers::get_tags_taxonomy_labels();

		// Rewrite rules.
		$rewrite = Helpers::get_tags_taxonomy_rewrite();

		// Capabilities - same as calendars.
		$caps = [
			'manage_terms' => 'manage_event_calendars',
			'edit_terms'   => 'edit_event_calendars',
			'delete_terms' => 'delete_event_calendars',
			'assign_terms' => 'assign_event_calendars',
		];

		// Arguments.
		$args = [
			'labels'                => $labels,
			'rewrite'               => $rewrite,
			'capabilities'          => $caps,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => Helpers::get_tags_taxonomy_id(),
			'show_tagcloud'         => true,
			'hierarchical'          => false,
			'show_in_nav_menus'     => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_rest'          => true,
			'show_admin_column'     => true,
			'meta_box_cb'           => 'post_tags_meta_box',
		];

		// Register.
		register_taxonomy(
			Helpers::get_tags_taxonomy_id(),
			sugar_calendar_get_event_post_type_id(),
			$args
		);
	}

	/**
	 * Prioritize the SC Events Tag taxononmy rewrite rules.
	 *
	 * @since 3.7.0
	 */
	public function prioritize_sc_event_tags_rewrite_rules() {

		add_rewrite_rule(
			'^' . Helpers::get_tags_taxonomy_slug() . '/([^/]+)/?$',
			'index.php?' . Helpers::get_tags_taxonomy_id() . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Add tags taxonomy to the supported taxonomies list.
	 *
	 * @since 3.7.0
	 *
	 * @param array $supported_taxonomies The list of supported taxonomies.
	 *
	 * @return array The modified list of supported taxonomies.
	 */
	public function add_tags_taxonomies( $supported_taxonomies ) {

		$supported_taxonomies[] = Helpers::get_tags_taxonomy_id();

		return $supported_taxonomies;
	}

	/**
	 * Filter the arguments for the taxonomy query.
	 *
	 * @since 3.7.0
	 *
	 * @param array        $args  The query arguments.
	 * @param object|Query $query The query object.
	 *
	 * @return array The modified query arguments.
	 */
	public function join_by_taxonomy_term_args( $args, $query ) {

		// Add the taxonomy and terms to the query.
		if ( ! empty( $query->query_vars['sc_event_tags'] ) ) {

			// Normalize into array.
			$event_tags = is_string( $query->query_vars['sc_event_tags'] )
				? explode( ',', $query->query_vars['sc_event_tags'] )
				: $query->query_vars['sc_event_tags'];

			$args[] = [
				'taxonomy' => Helpers::get_tags_taxonomy_id(),
				'terms'    => $event_tags,
			];
		}

		return $args;
	}
}
