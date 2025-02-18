<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Admin\Pages\VenuesAbstract;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers;
use WP_Post;

/**
 * Abstract Event page.
 *
 * @since 3.0.0
 */
abstract class EventAbstract extends PageAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	abstract public static function get_slug();

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	abstract public static function get_label();

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 1;
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

		return static::get_slug();
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 * @since 3.5.1 Add loading of compatibility hooks.
	 */
	public function hooks() {

		add_filter( 'screen_options_show_screen', '__return_false' );
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );
		add_filter( 'enter_title_here', [ $this, 'get_title_field_placeholder' ], 10, 2 );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		// Load compatibility hooks.
		Helpers::load_compatibility_hooks();
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

		if ( ! in_array( $pagenow, [ 'post-new.php', 'post.php' ], true ) ) {
			return;
		}
		?>
        <div class="sugar-calendar-admin-subheader">
            <h4><?php echo esc_html( static::get_label() ); ?></h4>
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
	 * Localized data to be used in admin-event.js.
	 *
	 * @since 3.3.0
	 *
	 * @return array
	 */
	public function get_localized_scripts() {

		return [
			'notice_title_required' => esc_html__( 'Event name is required', 'sugar-calendar-lite' ),
		];
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
			SC_PLUGIN_VERSION
		);

		// Enqueue venue styles.
		VenuesAbstract::enqueue_assets();

		wp_enqueue_script(
			'sugar-calendar-admin-event',
			SC_PLUGIN_ASSETS_URL . 'js/admin-event' . WP::asset_min() . '.js',
			[],
			SC_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-event',
			'sugar_calendar_admin_event_vars',
			$this->get_localized_scripts()
		);
	}
}
