<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Abstract Event page.
 *
 * @since 3.5.0
 */
abstract class VenuesAbstract extends PageAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	abstract public static function get_slug();

	/**
	 * Page label.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	abstract public static function get_label();

	/**
	 * Register page hooks.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function hooks() {

		// Load assets.
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		// Admin subheader.
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );

		// Remove "Screen Options".
		add_filter( 'screen_options_show_screen', '__return_false' );
	}

	/**
	 * Display admin subheader.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function display_admin_subheader() {
		?>
			<div class="sugar-calendar-admin-subheader">
				<h4><?php echo esc_html( static::get_label() ); ?></h4>
			</div>
		<?php
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public static function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-admin-venues',
			SC_PLUGIN_ASSETS_URL . 'css/admin-venues' . WP::asset_min() . '.css',
			[ 'sugar-calendar-admin-settings', 'sugar-calendar-admin-education' ],
			BaseHelpers::get_asset_version()
		);
	}

	/**
	 * Add unique body class to venue edit page.
	 *
	 * @since 3.5.0
	 *
	 * @param string $classes Body classes.
	 *
	 * @return string
	 */
	public function add_venue_edit_body_class( $classes = '' ) {

		$classes .= ' sugar-calendar-venue';

		return $classes;
	}
}
