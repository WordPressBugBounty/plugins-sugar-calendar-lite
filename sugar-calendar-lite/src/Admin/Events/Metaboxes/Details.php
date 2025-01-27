<?php

namespace Sugar_Calendar\Admin\Events\Metaboxes;

use Sugar_Calendar\Admin\Events\MetaboxInterface;

/**
 * Details metabox.
 *
 * @since 3.0.0
 */
class Details implements MetaboxInterface {

	/**
	 * Metabox ID.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_id() {

		return 'sugar_calendar_details';
	}

	/**
	 * Metabox title.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_title() {

		return esc_html__( 'Details', 'sugar-calendar-lite' );
	}

	/**
	 * Metabox screen.
	 *
	 * @since 3.0.0
	 *
	 * @return string|array|WP_Screen
	 */
	public function get_screen() {

		return sugar_calendar_get_event_post_type_id();
	}

	/**
	 * Metabox context.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_context() {

		return 'normal';
	}

	/**
	 * Metabox priority.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_priority() {

		return 'high';
	}

	/**
	 * Display the metabox.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Post $post Current post.
	 */
	public function display( $post = null ) {

		wp_editor( $post->post_content, 'post_content' );
	}
}
