<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Admin\Pages\Dashboard;
use Sugar_Calendar\Helpers\WP;

/**
 * Makes the Dashboard the page the main Sugar Calendar menu item opens.
 *
 * @since 3.18.0
 */
class LandingPage {

	/**
	 * Move the Dashboard to the front of the submenu, so the top-level menu opens it.
	 *
	 * Core links the top-level menu to the first submenu entry the user can see. A user
	 * without the page's capability never gets the entry, so Events stays first for them.
	 *
	 * @since 3.18.0
	 */
	public static function place_menu_item() {

		global $submenu;

		$items = $submenu[ Area::SLUG ] ?? [];
		$key   = WP::find_submenu_key( $items, Dashboard::get_slug() );

		if ( $key === null || ! self::is_landing_page() ) {
			return;
		}

		$ours = $items[ $key ];

		unset( $items[ $key ] );

		// Unshifting renumbers the keys, closing the gap the unset left.
		array_unshift( $items, $ours );

		$submenu[ Area::SLUG ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Whether the Dashboard is the landing page.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	private static function is_landing_page(): bool {

		/**
		 * Filters whether the main Sugar Calendar menu item opens the Dashboard.
		 *
		 * Return false to keep Events as the landing page:
		 * `add_filter( 'sugar_calendar_admin_pages_dashboard_is_landing_page', '__return_false' );`
		 *
		 * @since 3.18.0
		 *
		 * @param bool $is_landing_page Whether the Dashboard is the landing page. Default true.
		 */
		return (bool) apply_filters( 'sugar_calendar_admin_pages_dashboard_is_landing_page', true ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- documented under the Dashboard page's name.
	}
}
