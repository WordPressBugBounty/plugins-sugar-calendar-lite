<?php

namespace Sugar_Calendar\Features\Tags\Common;

/**
 * Tags Feature Helper Functions.
 *
 * @since     3.7.0
 */
class Helpers {

	/**
	 * Get the Tags feature object.
	 *
	 * @since 3.7.0
	 *
	 * @return object
	 */
	public static function get_tags_feature() {

		return sugar_calendar()->get_src_features()->get_loaded_features( 'sugar-calendar-tags' );
	}

	/**
	 * Get the tags taxonomy ID.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_tags_taxonomy_id() {

		/**
		 * Filters the tags taxonomy ID.
		 *
		 * @since 3.7.0
		 *
		 * @param string $taxonomy The tags taxonomy ID.
		 */
		return apply_filters( 'sugar_calendar_tags_taxonomy_id', 'sc_event_tags' );
	}

	/**
	 * Get the tags taxonomy labels.
	 *
	 * @since 3.7.0
	 *
	 * @param string $key The key of the label to get (optional).
	 *
	 * @return mixed array|string
	 */
	public static function get_tags_taxonomy_labels( $key = null ) {

		/**
		 * Filters the tags taxonomy labels.
		 *
		 * @since 3.7.0
		 *
		 * @param array $labels The tags taxonomy labels.
		 */
		$labels = apply_filters(
			'sugar_calendar_tags_taxonomy_labels',
			[

				'name'                       => esc_html__( 'Tags', 'sugar-calendar-lite' ),
				'singular_name'              => esc_html__( 'Tag', 'sugar-calendar-lite' ),
				'search_items'               => esc_html__( 'Search', 'sugar-calendar-lite' ),
				'popular_items'              => esc_html__( 'Popular Tags', 'sugar-calendar-lite' ),
				'all_items'                  => esc_html__( 'All Tags', 'sugar-calendar-lite' ),
				'parent_item'                => null,
				'parent_item_colon'          => null,
				'edit_item'                  => esc_html__( 'Edit Tag', 'sugar-calendar-lite' ),
				'view_item'                  => esc_html__( 'View Tag', 'sugar-calendar-lite' ),
				'update_item'                => esc_html__( 'Update Tag', 'sugar-calendar-lite' ),
				'add_new_item'               => esc_html__( 'Add New Tag', 'sugar-calendar-lite' ),
				'new_item_name'              => esc_html__( 'New Tag Name', 'sugar-calendar-lite' ),
				'separate_items_with_commas' => esc_html__( 'Separate tags with commas', 'sugar-calendar-lite' ),
				'add_or_remove_items'        => esc_html__( 'Add or remove tags', 'sugar-calendar-lite' ),
				'choose_from_most_used'      => esc_html__( 'Choose from the most used tags', 'sugar-calendar-lite' ),
				'no_terms'                   => esc_html__( 'No Tags', 'sugar-calendar-lite' ),
				'not_found'                  => esc_html__( 'No tags found', 'sugar-calendar-lite' ),
				'items_list_navigation'      => esc_html__( 'Tags list navigation', 'sugar-calendar-lite' ),
				'items_list'                 => esc_html__( 'Tags list', 'sugar-calendar-lite' ),
				'back_to_items'              => esc_html__( 'Back to Tags', 'sugar-calendar-lite' ),
				'manage_tags'                => esc_html__( 'Manage Tags', 'sugar-calendar-lite' ),
			]
		);

		// Return empty if key is provided but not found.
		if (
			$key !== null
			&&
			! isset( $labels[ $key ] )
		) {
			return '';
		}

		return $key === null ? $labels : $labels[ $key ];
	}

	/**
	 * Get Tags taxonomy slug.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_tags_taxonomy_slug() {

		/**
		 * Filters the tags taxonomy slug.
		 *
		 * @since 3.7.0
		 *
		 * @param string $slug The tags taxonomy slug.
		 */
		return apply_filters(
			'sugar_calendar_tags_taxonomy_slug',
			'events/tag'
		);
	}

	/**
	 * Get the tags taxonomy rewrite.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public static function get_tags_taxonomy_rewrite() {

		/**
		 * Filters the tags taxonomy rewrite.
		 *
		 * @since 3.7.0
		 *
		 * @param array $rewrite The tags taxonomy rewrite.
		 */
		return apply_filters(
			'sugar_calendar_tags_taxonomy_rewrite',
			[
				'slug'       => self::get_tags_taxonomy_slug(),
				'with_front' => false,
			]
		);
	}

	/**
	 * Filter for valid tags term IDs.
	 *
	 * @since 3.7.0
	 *
	 * @param array $term_ids The term IDs.
	 *
	 * @return array
	 */
	public static function validate_tags_term_ids( $term_ids ) {

		$valid_term_ids = [];

		// Bail if no term IDs are provided.
		if ( empty( $term_ids ) ) {
			return $valid_term_ids;
		}

		// Loop through term IDs.
		foreach ( $term_ids as $term_id ) {

			// If term ID is valid, add to valid term IDs.
			if ( self::is_valid_tags_term_id( $term_id ) ) {
				$valid_term_ids[] = intval( $term_id );
			}
		}

		return $valid_term_ids;
	}

	/**
	 * Check if term id is from the tags taxonomy.
	 *
	 * @since 3.7.0
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return bool
	 */
	public static function is_valid_tags_term_id( $term_id ) {

		// Get term.
		$term = get_term( absint( $term_id ), self::get_tags_taxonomy_id() );

		return $term && ! is_wp_error( $term );
	}
}
