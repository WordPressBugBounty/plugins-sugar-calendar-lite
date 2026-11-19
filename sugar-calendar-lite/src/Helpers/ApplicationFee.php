<?php

namespace Sugar_Calendar\Helpers;

use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * The application fee Stripe checkout adds to a payment.
 *
 * @since 3.18.0
 */
class ApplicationFee {

	/**
	 * The fee, in percent, that Stripe checkout charges while is_charged().
	 *
	 * @since 3.18.0
	 */
	const PERCENT = 3;

	/**
	 * Whether Stripe checkout adds the fee to a payment.
	 *
	 * Shared with the Dashboard's fee card, so the card shows only while the fee is charged.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public static function is_charged() {

		return ! BaseHelpers::is_license_valid() || ! sugar_calendar()->is_pro() || BaseHelpers::is_application_fee_supported();
	}
}
