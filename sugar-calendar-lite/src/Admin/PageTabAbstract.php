<?php

namespace Sugar_Calendar\Admin;

use Sugar_Calendar\Helpers\WP;

/**
 * Page Tab abstract.
 *
 * @since 3.0.0
 */
abstract class PageTabAbstract extends PageAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return '';
	}

	/**
	 * Page tab slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return '';
	}

	/**
	 * Page URL.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_url() {

		return add_query_arg(
			[
				'page'    => static::get_slug(),
				'section' => static::get_tab_slug(),
			],
			WP::admin_url( 'admin.php' )
		);
	}
}
