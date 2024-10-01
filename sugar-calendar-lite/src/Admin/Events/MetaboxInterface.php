<?php

namespace Sugar_Calendar\Admin\Events;

/**
 * Metabox interface.
 *
 * @since 3.0.0
 */
interface MetaboxInterface {

	/**
	 * Metabox ID.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Metabox title.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_title();

	/**
	 * Metabox screen.
	 *
	 * @since 3.0.0
	 *
	 * @return string|array|WP_Screen
	 */
	public function get_screen();

	/**
	 * Metabox context.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_context();

	/**
	 * Metabox priority.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_priority();

	/**
	 * Display the metabox.
	 *
	 * @since 3.0.0
	 */
	public function display();
}
