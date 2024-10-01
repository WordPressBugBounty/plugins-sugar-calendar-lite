<?php

namespace Sugar_Calendar\Admin;

/**
 * Admin page interface.
 *
 * @since 3.0.0
 */
interface PageInterface {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug();

	/**
	 * Page URL.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_url();

	/**
	 * Page capability.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_capability();

	/**
	 * Page title.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_title();

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label();

	/**
	 * Whether the page appears in dashboard menu.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public static function has_menu_item();

	/**
	 * Which menu item to highlight
	 * if the page doesn't appear in dashboard menu.
	 *
	 * @since 3.0.0
	 *
	 * @return null|string;
	 */
	public static function highlight_menu_item();

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority();

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks();

	/**
	 * Display page.
	 *
	 * @since 3.0.0
	 */
	public function display();

	/**
	 * Handle POST requests.
	 *
	 * @since 3.0.0
	 *
	 * @param array $post_data Post data.
	 */
	public function handle_post( $post_data = [] );
}
