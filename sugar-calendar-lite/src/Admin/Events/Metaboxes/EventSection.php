<?php

namespace Sugar_Calendar\Admin\Events\Metaboxes;

/**
 * Event metabox section.
 *
 * @since 3.0.0
 */
class EventSection {

	/**
	 * Unique ID for this section.
	 *
	 * @since 2.0.18
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Unique label for this section.
	 *
	 * @since 2.0.18
	 *
	 * @var string
	 */
	public $label = '';

	/**
	 * Unique ID for this section.
	 *
	 * @since 2.0.18
	 *
	 * @var string
	 */
	public $icon = 'editor-help';

	/**
	 * Unique ID for this section.
	 *
	 * @since 2.0.18
	 *
	 * @var int
	 */
	public $order = 50;

	/**
	 * Unique ID for this section.
	 *
	 * @since 2.0.18
	 *
	 * @var string
	 */
	public $callback = '';

	/**
	 * Handle parameters if passed in during construction.
	 *
	 * @since 2.0.18
	 *
	 * @param array $args Constructor arguments.
	 */
	public function __construct( $args = [] ) {

		if ( ! empty( $args ) ) {
			$this->init( $args );
		}
	}

	/**
	 * Initialize the section.
	 *
	 * @since 2.0.18
	 *
	 * @param array $args Initialization arguments.
	 */
	protected function init( $args = [] ) {

		// Get default object variables.
		$defaults = get_object_vars( $this );

		// Parse the arguments.
		$r = wp_parse_args( $args, $defaults );

		// Set the object variables.
		$this->set_vars( $r );
	}

	/**
	 * Set class variables from arguments.
	 *
	 * @since 2.0.18
	 *
	 * @param array $args Variable arguments.
	 */
	protected function set_vars( $args = [] ) {

		// Bail if empty or not an array.
		if ( empty( $args ) ) {
			return;
		}

		// Cast to an array.
		if ( ! is_array( $args ) ) {
			$args = (array) $args;
		}

		// Set all properties.
		foreach ( $args as $key => $value ) {
			$this->{$key} = $value;
		}
	}
}
