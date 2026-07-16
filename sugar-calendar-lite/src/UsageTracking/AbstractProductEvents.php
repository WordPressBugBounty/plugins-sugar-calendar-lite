<?php

namespace Sugar_Calendar\UsageTracking;

use Sugar_Calendar\Vendor\ProductApi\Events\Event;
use Sugar_Calendar\Vendor\ProductApi\Events\EventsManager;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;
use Throwable;

/**
 * Shared plumbing for Product API event trackers.
 *
 * Owns the enabled/context guards and the tracker access every
 * Product Events consumer needs. Concrete trackers register their
 * hooks in hooks().
 *
 * @since 3.12.0
 */
abstract class AbstractProductEvents {

	/**
	 * Initialize product events tracking.
	 *
	 * @since 3.12.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	abstract protected function hooks();

	/**
	 * Whether Product Events tracking is enabled.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	protected function is_enabled() {

		/**
		 * Whether product events tracking is enabled.
		 *
		 * @since 3.11.0
		 *
		 * @param bool $enabled Whether product events tracking is enabled.
		 */
		return (bool) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_usage_tracking_is_product_events_enabled',
			sugar_calendar()->get_usage_tracking()->is_enabled()
		);
	}

	/**
	 * Whether Product Events tracking is allowed in the current context.
	 *
	 * Only allows tracking from admin dashboard requests by users
	 * with appropriate capabilities when tracking is enabled.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	protected function is_allowed() {

		// Don't track during cron or CLI.
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}

		// Only track from admin dashboard or REST API (Gutenberg).
		$is_admin_context = is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );

		if ( ! $is_admin_context ) {
			return false;
		}

		// Ensure user is logged in and has capability to manage content.
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		return $this->is_enabled();
	}

	/**
	 * Track event.
	 *
	 * @since 3.12.0
	 *
	 * @param string $event_name Event name.
	 * @param array  $event_data Event data.
	 */
	protected function track_event( $event_name, $event_data ) {

		// Safety check in case a handler forgets to call is_allowed().
		if ( ! $this->is_allowed() ) {
			return;
		}

		try {
			$events_manager = ProductApi::get( EventsManager::class );
		} catch ( Throwable $e ) {
			return;
		}

		$events_manager->get_tracker()->track( new Event( $event_name, $event_data ) );
	}
}
