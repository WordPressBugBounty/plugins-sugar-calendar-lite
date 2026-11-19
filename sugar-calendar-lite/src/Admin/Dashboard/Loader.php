<?php

namespace Sugar_Calendar\Admin\Dashboard;

/**
 * Boots the Dashboard parts that run outside the Dashboard page.
 *
 * @since 3.18.0
 */
class Loader {

	/**
	 * Register the hooks.
	 *
	 * Area calls it after registering the Setup Checklist's menu placement, which shares the
	 * `PHP_INT_MAX` priority, so the Dashboard moves to the front once every entry is placed.
	 *
	 * @since 3.18.0
	 */
	public function hooks() {

		// The completion arrives on Settings > Payments, so it cannot wait for the Dashboard page's hooks.
		( new StripeConnect() )->hooks();

		add_action( 'admin_menu', [ LandingPage::class, 'place_menu_item' ], PHP_INT_MAX );
	}
}
