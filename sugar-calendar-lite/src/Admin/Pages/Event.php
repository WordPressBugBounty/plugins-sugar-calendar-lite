<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;
use WP_Post;

/**
 * Event page.
 *
 * @since 3.0.0
 */
class Event extends PageAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar-event';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Event', 'sugar-calendar-lite' );
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		add_filter( 'screen_options_show_screen', '__return_false' );
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );
		add_filter( 'enter_title_here', [ $this, 'get_title_field_placeholder' ], 10, 2 );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Display the subheader.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function display_admin_subheader() {

		global $pagenow;

		if ( ! in_array( $pagenow, [ 'post-new.php', 'post.php' ] ) ) {
			return;
		}
		?>
        <div class="sugar-calendar-admin-subheader">
            <h4>
				<?php if ( $pagenow === 'post-new.php' ) : ?>
					<?php esc_html_e( 'Add New Event', 'sugar-calendar-lite' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Edit Event', 'sugar-calendar-lite' ); ?>
				<?php endif; ?>
            </h4>
        </div>
		<?php
	}

	/**
	 * Set the placeholder text for the title field for this post type.
	 *
	 * @since 2.0.0
	 *
	 * @param string  $title The placeholder text.
	 * @param WP_Post $post  The current post.
	 *
	 * @return string The updated placeholder text.
	 */
	public function get_title_field_placeholder( $title, WP_Post $post ) {

		// Override if primary post type.
		if ( sugar_calendar_get_event_post_type_id() === $post->post_type ) {
			$title = esc_html__( 'Name this event', 'sugar-calendar-lite' );
		}

		// Return possibly modified title.
		return $title;
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-admin-event',
			SC_PLUGIN_ASSETS_URL . 'css/admin-event' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);
	}
}
