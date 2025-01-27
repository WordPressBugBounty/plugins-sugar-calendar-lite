<?php

namespace Sugar_Calendar\Admin\Pages;

/**
 * New Event page.
 *
 * @since 3.0.0
 */
class EventNew extends EventAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		$post_type = sugar_calendar_get_event_post_type_id();

		return "post-new.php?post_type={$post_type}";
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Add New Event', 'sugar-calendar-lite' );
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_title() {

		return esc_html__( 'Add New', 'sugar-calendar-lite' );
	}

	/**
	 * Page capability.
	 *
	 * @since 3.0.1
	 *
	 * @return string
	 */
	public static function get_capability() {

		/**
		 * Filters the capability required to view the Add New Event page.
		 *
		 * @since 3.0.1
		 *
		 * @param string $capability Capability required to view the calendars page.
		 */
		return apply_filters( 'sugar_calendar_admin_pages_event_new_get_capability', 'edit_events' );
	}
}
