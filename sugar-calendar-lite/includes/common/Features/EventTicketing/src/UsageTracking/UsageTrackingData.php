<?php

namespace Sugar_Calendar\AddOn\Ticketing\UsageTracking;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Settings;
use Sugar_Calendar\UsageTracking\Counts;

/**
 * Event Ticketing install-state keys for the usage-tracking snapshot.
 *
 * @since 3.15.0
 */
class UsageTrackingData {

	/**
	 * Register hooks.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_usage_tracking_get_data', [ $this, 'get_data' ] );
	}

	/**
	 * Add the ticketing keys.
	 *
	 * @since 3.15.0
	 *
	 * @param array $data Usage tracking data.
	 *
	 * @return array
	 */
	public function get_data( $data ) {

		$data['sugar_calendar_ticketing_events_enabled']    = Counts::events_with_truthy_meta( 'tickets' );
		$data['sugar_calendar_ticketing_stripe_connected']  = (bool) Functions\stripe_is_connected();
		$data['sugar_calendar_ticketing_sandbox_mode']      = (bool) Settings\get_setting( 'sandbox', false );
		$data['sugar_calendar_ticketing_currency']          = sanitize_text_field( (string) Settings\get_setting( 'currency', '' ) );
		$data['sugar_calendar_ticketing_woocommerce_used']  = ! empty( Settings\get_setting( 'woocommerce_ticket_product', '' ) );
		$data['sugar_calendar_ticketing_total_orders']      = Counts::rows( 'sc_orders', 'status = %s', [ 'paid' ] );
		$data['sugar_calendar_ticketing_total_tickets']     = Counts::rows( 'sc_tickets' );
		$data['sugar_calendar_ticketing_total_attendees']   = Counts::rows( 'sc_attendees' );
		$data['sugar_calendar_ticketing_total_discounts']   = Counts::rows( 'sc_discounts' );
		$data['sugar_calendar_ticketing_emails_customized'] = $this->emails_customized();
		$data['sugar_calendar_tickets_last_sold_at']        = $this->tickets_last_sold_at();

		return $data;
	}

	/**
	 * GMT Unix creation time of the newest paid or refunded order, 0 when none.
	 *
	 * Checkout never writes date_paid, so date_created is the only usable column.
	 *
	 * @since 3.17.0
	 *
	 * @return int
	 */
	private function tickets_last_sold_at() {

		global $wpdb;

		$created = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT MAX( date_created ) FROM {$wpdb->prefix}sc_orders WHERE status IN ( 'paid', 'refunded' )" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $created ? (int) strtotime( $created . ' UTC' ) : 0;
	}

	/**
	 * Whether any receipt/ticket subject or message is stored AND differs from every shipped default.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function emails_customized() {

		$settings = get_option( 'sc_et_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		$defaults = [
			'receipt_subject' => [ Settings\get_default_receipt_subject() ],
			'receipt_message' => [ Settings\get_default_receipt_message(), Settings\get_default_modern_receipt_message() ],
			'ticket_subject'  => [ Settings\get_default_ticket_subject() ],
			'ticket_message'  => [ Settings\get_default_ticket_message(), Settings\get_default_modern_ticket_message() ],
		];

		foreach ( $defaults as $key => $candidates ) {

			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}

			$stored = $this->normalize( $settings[ $key ] );

			foreach ( $candidates as $candidate ) {
				if ( $stored === $this->normalize( $candidate ) ) {
					continue 2;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Normalize copy for comparison: string-cast, unify line endings, trim.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Stored or default copy.
	 *
	 * @return string
	 */
	private function normalize( $value ) {

		return trim( preg_replace( '/\R/', "\n", Settings\normalize_email_copy_value( $value ) ) );
	}
}
