<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Admin\Pages\SetupChecklist as SetupChecklistPage;
use Sugar_Calendar\Helpers\ActionResult;

use function Sugar_Calendar\AddOn\Ticketing\Admin\Settings\get_section_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_stripe_connect_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\is_sandbox;

/**
 * The checklist's Stripe Connect round trip: the connect URL and the completion override.
 *
 * Both halves build the return URL through one method because the broker
 * rejects a completion whose customer-site URL differs from the one sent at init.
 *
 * @since 3.15.0
 */
class StripeConnect {

	/**
	 * Query parameter marking a checklist-started connection and carrying its Test Mode flag.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const TEST_MODE_PARAM = 'sc_checklist_stripe_test_mode';

	/**
	 * Register the completion override.
	 *
	 * @since 3.15.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_event_ticketing_admin_settings_stripe_connect_completion', [ $this, 'filter_completion' ] );
		add_filter( 'sugar_calendar_setup_checklist_item_action', [ $this, 'filter_item_action' ], 10, 2 );
	}

	/**
	 * Claim the connect-stripe row, whose click starts the Stripe Connect round trip.
	 *
	 * @since 3.15.0
	 *
	 * @param array|WP_Error $result  Shared fallback result.
	 * @param string         $item_id Canonical checklist item ID.
	 *
	 * @return array|WP_Error
	 */
	public function filter_item_action( $result, $item_id ) {

		return $item_id === Config::ITEM_CONNECT_STRIPE ? ActionResult::navigation( $this->get_connect_url() ) : $result;
	}

	/**
	 * The Stripe Connect URL, returning to the checklist-aware payments completion.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function get_connect_url() {

		/**
		 * Filters whether checklist Stripe connections force Test Mode.
		 *
		 * Lets a checklist Stripe round trip complete against test credentials without
		 * changing the site's payment mode. CompletionDetector reads the same filter.
		 *
		 * @since 3.15.0
		 *
		 * @param bool $test_mode Whether to force Test Mode. Default false.
		 */
		$test_mode = (bool) apply_filters( 'sugar_calendar_setup_checklist_stripe_test_mode', false ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.

		$is_sandbox = $test_mode || is_sandbox();

		return get_stripe_connect_url( $is_sandbox, $this->get_return_url( $is_sandbox, wp_create_nonce( 'sc-stripe-connect-completion' ) ) );
	}

	/**
	 * Point a checklist-started completion back at the checklist.
	 *
	 * @since 3.15.0
	 *
	 * @param array $args Completion context: `is_sandbox`, `redirect` and `customer_site_url`.
	 *
	 * @return array
	 */
	public function filter_completion( $args ) {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the completion handler verified the nonce before applying this filter.
		$flag  = isset( $_GET[ self::TEST_MODE_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::TEST_MODE_PARAM ] ) ) : '';
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		// phpcs:enable

		if ( ! in_array( $flag, [ '0', '1' ], true ) ) {
			return $args;
		}

		$args['is_sandbox']        = $flag === '1';
		$args['redirect']          = SetupChecklistPage::get_url();
		$args['customer_site_url'] = $this->get_return_url( $args['is_sandbox'], $nonce );

		return $args;
	}

	/**
	 * The payments URL carrying the completion nonce and the checklist's Test Mode flag.
	 *
	 * @since 3.15.0
	 *
	 * @param bool   $is_sandbox Whether the connection runs in Test Mode.
	 * @param string $nonce      Completion nonce: minted at init, echoed from the request at completion.
	 *
	 * @return string
	 */
	private function get_return_url( $is_sandbox, $nonce ) {

		return add_query_arg(
			[
				'_wpnonce'            => $nonce,
				self::TEST_MODE_PARAM => (int) $is_sandbox,
			],
			get_section_url( [ 'section' => 'payments' ] )
		);
	}
}
