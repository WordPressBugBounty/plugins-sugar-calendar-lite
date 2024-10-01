<?php

namespace Sugar_Calendar\Admin;

use Sugar_Calendar\Helpers\WP;

/**
 * Page abstract.
 *
 * @since 3.0.0
 */
abstract class PageAbstract implements PageInterface {

	/**
	 * Page URL.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_url() {

		return add_query_arg( 'page', static::get_slug(), WP::admin_url( 'admin.php' ) );
	}

	/**
	 * Page capability.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_capability() {

		return 'manage_options';
	}

	/**
	 * Whether the page appears in menus.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public static function has_menu_item() {

		return true;
	}

	/**
	 * Which menu item to highlight
	 * if the page doesn't appear in dashboard menu.
	 *
	 * @since 3.0.0
	 *
	 * @return null|string;
	 */
	public static function highlight_menu_item() {

		return null;
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 20;
	}

	/**
	 * Page title.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_title() {

		return static::get_label();
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {}

	/**
	 * Display page.
	 *
	 * @since 3.0.0
	 */
	public function display() {}

	/**
	 * Handle POST requests.
	 *
	 * @since 3.0.0
	 *
	 * @param array $post_data Post data.
	 */
	public function handle_post( $post_data = [] ) {}
}
