<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecReader;
use Sugar_Calendar\Admin\Tools\Migrate\StepResult;

/**
 * A TEC taxonomy whose terms become Sugar Calendar terms.
 *
 * @since 3.18.0
 */
abstract class TermStep extends TecStep {

	/**
	 * The Sugar Calendar taxonomy the terms go to.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	abstract protected function get_taxonomy();

	/**
	 * The taxonomy whose existing terms decide whether a new term needs a unique slug.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	abstract protected function get_slug_taxonomy();

	/**
	 * Record a migrated term and run what follows it.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item       The batch item.
	 * @param array  $term       The TEC term.
	 * @param int    $sc_term_id The new term's ID.
	 *
	 * @return bool Whether the mapping row was saved.
	 */
	abstract protected function save( $item, $term, $sc_term_id );

	/**
	 * The TEC terms not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	protected function query( $count_only ) {

		return $this->reader->get_unmigrated_terms( $this->get_entity(), $count_only, $count_only ? 0 : $this->get_limit() );
	}

	/**
	 * The number of terms one request reads.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	abstract protected function get_limit();

	/**
	 * The source ID of one batch item.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return int
	 */
	public function get_item_id( $item ) {

		return (int) $item->term_id;
	}

	/**
	 * Copy one TEC term into the Sugar Calendar taxonomy.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return StepResult
	 */
	public function migrate( $item ) {

		$term = $this->items->get_term( (int) $item->term_id );

		if ( ! is_array( $term ) ) {
			return StepResult::not_migrated();
		}

		$sc_term = wp_insert_term(
			$term['name'],
			$this->get_taxonomy(),
			[
				'name'        => $term['name'],
				'description' => $term['description'],
				// A name already in use gets a unique slug; otherwise WordPress keeps TEC's.
				'slug'        => term_exists( $term['name'], $this->get_slug_taxonomy() )
					? wp_unique_term_slug( $term['slug'], (object) [ 'taxonomy' => $this->get_slug_taxonomy() ] )
					: $term['slug'],
			]
		);

		if ( is_wp_error( $sc_term ) ) {
			return StepResult::not_migrated();
		}

		return $this->save( $item, $term, (int) $sc_term['term_id'] ) ? StepResult::migrated() : StepResult::not_migrated();
	}

	/**
	 * Give the events this run migrated the terms their TEC events had.
	 *
	 * @since 3.18.0
	 */
	protected function link_to_events() {

		$mapped = [];

		foreach ( $this->lookup->get_new_events() as $event ) {
			$sc_term_ids = $this->get_sc_term_ids( $event->tec_event_post_id, $mapped );

			if ( ! empty( $sc_term_ids ) ) {
				wp_set_object_terms( $event->sc_event_post_id, $sc_term_ids, $this->get_taxonomy() );
			}
		}
	}

	/**
	 * The migrated terms of a TEC event.
	 *
	 * @since 3.18.0
	 *
	 * @param int   $tec_event_post_id The TEC event post ID.
	 * @param array $mapped            The Sugar Calendar term ID found so far per TEC term ID, filled here.
	 *
	 * @return int[]
	 */
	private function get_sc_term_ids( $tec_event_post_id, array &$mapped ) {

		$sc_term_ids = [];

		foreach ( $this->items->get_post_term_ids( $tec_event_post_id, TecReader::TERMS[ $this->get_entity() ]['taxonomy'] ) as $tec_term_id ) {
			if ( ! isset( $mapped[ $tec_term_id ] ) ) {
				$mapped[ $tec_term_id ] = $this->lookup->get_sc_term_id( $this->get_entity(), $tec_term_id );
			}

			$sc_term_id = $mapped[ $tec_term_id ];

			if ( ! empty( $sc_term_id ) ) {
				$sc_term_ids[] = (int) $sc_term_id;
			}
		}

		return $sc_term_ids;
	}
}
