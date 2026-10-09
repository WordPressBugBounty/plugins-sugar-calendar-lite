<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Helpers\ActionResult;
use Sugar_Calendar\Newsletter;
use Sugar_Calendar\UsageTracking\OptIn;
use WP_Error;

/**
 * The two consent rows' clicks: the newsletter signup and the Lite usage-tracking opt-in.
 *
 * @since 3.15.0
 */
class Signups {

	/**
	 * Per-site state.
	 *
	 * @since 3.15.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Lite tracking opt-in service.
	 *
	 * @since 3.15.0
	 *
	 * @var OptIn
	 */
	private $opt_in;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param State $state  Per-site state.
	 * @param OptIn $opt_in Lite tracking opt-in service.
	 */
	public function __construct( State $state, OptIn $opt_in ) {

		$this->state  = $state;
		$this->opt_in = $opt_in;
	}

	/**
	 * Hooks.
	 *
	 * @since 3.15.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_setup_checklist_item_action', [ $this, 'filter_item_action' ], 10, 5 );
	}

	/**
	 * Claim the newsletter and usage-tracking rows, and pass every other row through.
	 *
	 * @since 3.15.0
	 *
	 * @param array|WP_Error $result     Shared fallback result.
	 * @param string         $item_id    Canonical checklist item ID.
	 * @param string         $return_url Local admin return URL.
	 * @param array          $item       The row as presented.
	 * @param array          $input      Validated row input.
	 *
	 * @return array|WP_Error
	 */
	public function filter_item_action( $result, $item_id, $return_url, $item, $input ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- filter contract supplies the URL and row before the input.

		if ( $item_id === Config::ITEM_NEWSLETTER ) {
			return $this->subscribe_newsletter( $input['email'] );
		}

		if ( $item_id === Config::ITEM_USAGE_TRACKING ) {
			return $this->enable_usage_tracking();
		}

		return $result;
	}

	/**
	 * Submit the newsletter signup and confirm the row was recorded.
	 *
	 * The history listener swallows persistence errors, so the row is re-read here
	 * rather than reporting success for a signup that will render incomplete.
	 *
	 * @since 3.15.0
	 *
	 * @param string $email Validated email address.
	 *
	 * @return array|WP_Error
	 */
	private function subscribe_newsletter( $email ) {

		if ( ! ( new Newsletter() )->subscribe( $email ) ) {
			return new WP_Error( 'checklist_newsletter_failed', __( 'Signing up failed. Please try again later.', 'sugar-calendar-lite' ) );
		}

		if ( ! $this->state->has_history( Config::ITEM_NEWSLETTER ) ) {
			return new WP_Error( 'checklist_newsletter_not_saved', __( 'You are signed up, but the checklist could not save it. Please try again.', 'sugar-calendar-lite' ) );
		}

		return ActionResult::build( 'success', __( 'Thanks for signing up.', 'sugar-calendar-lite' ), '', true );
	}

	/**
	 * Enable Lite usage tracking from the checklist.
	 *
	 * A missing consent record is an error here, unlike the wizard: the row cannot
	 * complete without it and the user is looking at the row.
	 *
	 * @since 3.15.0
	 *
	 * @return array|WP_Error
	 */
	private function enable_usage_tracking() {

		$enabled = $this->opt_in->enable( 'checklist' );

		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		return ActionResult::build( 'success', __( 'Usage tracking enabled.', 'sugar-calendar-lite' ), '', true );
	}
}
