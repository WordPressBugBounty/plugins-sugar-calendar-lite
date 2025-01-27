<?php

namespace Sugar_Calendar\Admin\Pages;

/**
 * Edit Event page.
 *
 * @since 3.0.0
 */
class EventEdit extends EventAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'post.php?action=edit';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Edit Event', 'sugar-calendar-lite' );
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_title() {

		return self::get_label();
	}
}
