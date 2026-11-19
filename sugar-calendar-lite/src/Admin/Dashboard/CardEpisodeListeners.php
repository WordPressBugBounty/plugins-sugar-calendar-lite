<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Admin\Dashboard\Widgets\Activity;

/**
 * Ends the fee and Reconnect cards' episodes when Stripe is connected or disconnected away from the
 * Dashboard: on Settings > Payments, or through the Setup Wizard's REST route. The Pro listener
 * extends it, so each edition boots one.
 *
 * @since 3.18.0
 */
class CardEpisodeListeners {

	/**
	 * Register hooks.
	 *
	 * @since 3.18.0
	 */
	public function hooks(): void {

		add_action( 'add_option_sc_stripe_connect_account_id', [ $this, 'end_payment_episodes' ] );
		add_action( 'update_option_sc_stripe_connect_account_id', [ $this, 'end_payment_episodes' ] );
	}

	/**
	 * End the payment cards' episodes that no longer apply. Connect and Disconnect write the account
	 * after the keys, so the new connection state is already readable here.
	 *
	 * @since 3.18.0
	 */
	public function end_payment_episodes(): void {

		$activity = $this->create_registry()->get_widget( 'activity' );

		if ( $activity instanceof Activity ) {
			$activity->end_card_episodes( [ 'application_fee', 'reconnect_payments' ] );
		}
	}

	/**
	 * The registry the listeners read the Activity widget from; the Pro listener's holds the Pro widgets.
	 *
	 * @since 3.18.0
	 *
	 * @return WidgetRegistry
	 */
	protected function create_registry(): WidgetRegistry {

		return new WidgetRegistry();
	}
}
