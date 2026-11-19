<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps;

use Sugar_Calendar\Admin\Tools\Migrate\FinishableStep;
use Sugar_Calendar\Helpers\Helpers;
use WP_Term;

/**
 * TEC categories, which become calendars.
 *
 * @since 3.18.0
 */
class CategoriesStep extends TermStep implements FinishableStep {

	/**
	 * The kind of item, used as the progress key.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_entity() {

		return 'categories';
	}

	/**
	 * The number of categories one request migrates.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_batch_size() {

		/**
		 * Filter the number of TEC categories to import per iteration.
		 *
		 * @since 3.6.0
		 *
		 * @param int $limit The number of TEC categories to import per iteration.
		 */
		return apply_filters( 'sc_import_tec_categories_limit', 100 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Established public hook; renaming would break backward compatibility.
	}

	/**
	 * The number of categories one request reads.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	protected function get_limit() {

		return $this->get_batch_size();
	}

	/**
	 * Calendars are the Sugar Calendar taxonomy.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	protected function get_taxonomy() {

		return sugar_calendar_get_calendar_taxonomy_id();
	}

	/**
	 * Calendars check their name against the calendars.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	protected function get_slug_taxonomy() {

		return sugar_calendar_get_calendar_taxonomy_id();
	}

	/**
	 * Give the calendar a color and record it.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item       The batch item.
	 * @param array  $term       The TEC category.
	 * @param int    $sc_term_id The new calendar's ID.
	 *
	 * @return bool
	 */
	protected function save( $item, $term, $sc_term_id ) {

		update_term_meta( $sc_term_id, 'color', Helpers::generate_random_hex_color() );

		return $this->id_map->save(
            'categories',
            (int) $item->term_id,
            [
				'tec_category_parent_id' => (int) $item->parent,
				'sc_category_id'         => $sc_term_id,
            ]
        ) !== false;
	}

	/**
	 * Nest the new calendars as their categories were, then give the events their calendars.
	 *
	 * @since 3.18.0
	 */
	public function finish() {

		foreach ( $this->lookup->get_new_categories() as $category ) {
			$this->set_parent( $category );
		}

		$this->link_to_events();
	}

	/**
	 * Put a new calendar under the calendar its TEC parent became.
	 *
	 * @since 3.18.0
	 *
	 * @param object $category The category mapping row.
	 */
	private function set_parent( $category ) {

		if ( $category->tec_category_parent_id === 0 ) {
			return;
		}

		$parent = get_term(
			(int) $this->lookup->get_sc_term_id( 'categories', $category->tec_category_parent_id ),
			sugar_calendar_get_calendar_taxonomy_id()
		);

		if ( $parent instanceof WP_Term ) {
			wp_update_term( $category->sc_category_id, sugar_calendar_get_calendar_taxonomy_id(), [ 'parent' => $parent->term_id ] );
		}
	}
}
