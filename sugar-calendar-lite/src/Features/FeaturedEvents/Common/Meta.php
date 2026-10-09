<?php

namespace Sugar_Calendar\Features\FeaturedEvents\Common;

/**
 * Event meta registration for the Featured Events feature.
 *
 * @since 3.14.0
 */
class Meta {

	/**
	 * Hooks.
	 *
	 * Not admin-gated: the schema is built on `init:10` of every request.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_meta_data', [ $this, 'register' ] );

		// A read after a write in the same request has to see the write, and the
		// read model memoizes for the life of the process. update_event_meta() is
		// a thin wrapper over update_metadata( 'sc_event', … ), so WordPress fires
		// these three for us.
		add_action( 'added_sc_event_meta', [ $this, 'maybe_flush' ], 10, 3 );
		add_action( 'updated_sc_event_meta', [ $this, 'maybe_flush' ], 10, 3 );
		add_action( 'deleted_sc_event_meta', [ $this, 'maybe_flush' ], 10, 3 );
	}

	/**
	 * Drop the featured read model's cache when the featured flag itself changes.
	 *
	 * Narrow on purpose: every sc_event meta write reaches this, and flushing on
	 * all of them would re-run the featured query after any unrelated save.
	 *
	 * The first argument is deliberately unused. `deleted_sc_event_meta` passes an
	 * ARRAY of meta ids where `added_` and `updated_` pass a single id, so only the
	 * meta key is safe to read across all three.
	 *
	 * @since 3.15.0
	 *
	 * @param int|int[] $meta_ids Meta id, or ids on the delete hook. Unused.
	 * @param int       $event_id Event id. Unused.
	 * @param string    $meta_key Meta key that changed.
	 */
	public function maybe_flush( $meta_ids, $event_id, $meta_key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Hook signature.

		if ( $meta_key === Featured::META_KEY ) {
			Featured::flush();
		}
	}

	/**
	 * Register the `featured` event-meta key and its sanitizer.
	 *
	 * @since 3.14.0
	 *
	 * @param array $schema Event meta-data schema (key => register_meta args).
	 *
	 * @return array
	 */
	public function register( $schema ) {

		$schema[ Featured::META_KEY ] = [
			'type'              => 'string',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => null,
			'show_in_rest'      => false,
		];

		return $schema;
	}
}
