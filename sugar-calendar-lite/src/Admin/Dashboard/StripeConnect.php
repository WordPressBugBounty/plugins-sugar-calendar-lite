<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Admin\Pages\Dashboard as DashboardPage;
use Sugar_Calendar\SetupChecklist\StripeConnect as ChecklistStripeConnect;

/**
 * The Dashboard's Stripe Connect round trip, which lands back on the Dashboard.
 *
 * Booted on every admin request, since the completion arrives on Settings > Payments, where the
 * Dashboard page's hooks never run.
 *
 * @since 3.18.0
 */
class StripeConnect extends ChecklistStripeConnect {

	/**
	 * Query parameter marking a Dashboard-started connection and carrying its Test Mode flag.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	const TEST_MODE_PARAM = 'sc_dashboard_stripe_test_mode';

	/**
	 * Register the completion override.
	 *
	 * @since 3.18.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_event_ticketing_admin_settings_stripe_connect_completion', [ $this, 'filter_completion' ] );
	}

	/**
	 * Where a completion this class started lands.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	protected function get_redirect_url() {

		return DashboardPage::get_url();
	}
}
