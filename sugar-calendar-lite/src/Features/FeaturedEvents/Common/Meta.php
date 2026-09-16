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
