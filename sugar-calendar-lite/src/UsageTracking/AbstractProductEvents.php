<?php

namespace Sugar_Calendar\UsageTracking;

use Sugar_Calendar\Vendor\ProductApi\Events\Event;
use Sugar_Calendar\Vendor\ProductApi\Events\EventsManager;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;
use Throwable;

/**
 * Shared plumbing for Product API event trackers.
 *
 * Owns the guards, the import marker, the shutdown queue and the tracker
 * access every Product Events consumer needs. Concrete trackers register
 * their hooks in hooks().
 *
 * @since 3.12.0
 */
abstract class AbstractProductEvents {

	/**
	 * Event context that carries the acting user's identity.
	 *
	 * @since 3.15.0
	 */
	const CONTEXT_USER = 'user';

	/**
	 * Event context sent without any user identity.
	 *
	 * @since 3.15.0
	 */
	const CONTEXT_SYSTEM = 'system';

	/**
	 * Whether an importer runs in this request.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $importing = false;

	/**
	 * Callbacks to run on shutdown.
	 *
	 * @since 3.15.0
	 *
	 * @var callable[]
	 */
	private $deferred = [];

	/**
	 * Initialize product events tracking.
	 *
	 * @since 3.12.0
	 * @since 3.15.0 Listens for the importer marker.
	 */
	public function init() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_action( 'sugar_calendar_importer_started', [ $this, 'mark_importing' ] );

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	abstract protected function hooks();

	/**
	 * Mark the request as an import, so creation events are not reported.
	 *
	 * @since 3.15.0
	 */
	public function mark_importing() {

		$this->importing = true;
	}

	/**
	 * Run the callbacks queued by defer().
	 *
	 * Hooked at priority 5: the client registers its flush at 10 on the first
	 * track(), and WordPress runs a priority added mid-hook only if it is later.
	 *
	 * @since 3.15.0
	 */
	public function run_deferred() {

		$deferred       = $this->deferred;
		$this->deferred = [];

		foreach ( $deferred as $callback ) {
			try {
				$callback();
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}
	}

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
	 * Whether an event may be sent without a logged-in editor (system context).
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	protected function is_allowed_without_user() {

		// Don't track during cron or CLI.
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}

		return $this->is_enabled();
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

		if ( ! $this->is_allowed_without_user() ) {
			return false;
		}

		// Only track from admin dashboard or REST API (Gutenberg).
		$is_admin_context = is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );

		if ( ! $is_admin_context ) {
			return false;
		}

		// Ensure user is logged in and has capability to manage content.
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Whether a creation in this request should be reported.
	 *
	 * The user guard waits for send time, so a guest's front-end submission
	 * still gets noted.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	protected function can_track_creation() {

		return ! $this->importing && $this->is_allowed_without_user();
	}

	/**
	 * Queue a callback for shutdown, once every save in the request has landed.
	 *
	 * @since 3.15.0
	 *
	 * @param callable $callback Callback to run.
	 */
	protected function defer( callable $callback ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if ( empty( $this->deferred ) ) {
			add_action( 'shutdown', [ $this, 'run_deferred' ], 5 );
		}

		$this->deferred[] = $callback;
	}

	/**
	 * Track event.
	 *
	 * @since 3.12.0
	 * @since 3.15.0 Adds `edition` and accepts a context.
	 *
	 * @param string $event_name Event name.
	 * @param array  $event_data Event data.
	 * @param string $context    CONTEXT_USER or CONTEXT_SYSTEM.
	 */
	protected function track_event( $event_name, $event_data, $context = self::CONTEXT_USER ) {

		$allowed = $context === self::CONTEXT_SYSTEM
			? $this->is_allowed_without_user()
			: $this->is_allowed();

		if ( ! $allowed ) {
			return;
		}

		try {
			$events_manager = ProductApi::get( EventsManager::class );
		} catch ( Throwable $e ) {
			return;
		}

		$event_data['edition'] = sugar_calendar()->is_pro() ? 'pro' : 'lite';

		$event = new Event( $event_name, $event_data );

		if ( $context === self::CONTEXT_SYSTEM ) {
			$event->context( self::CONTEXT_SYSTEM );
		}

		$events_manager->get_tracker()->track( $event );
	}
}
