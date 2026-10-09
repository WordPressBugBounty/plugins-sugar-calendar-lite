<?php
/**
 * Boots the Gia AI session and REST layer.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Registers the AI REST routes and clears a user's session on logout. Routes
 * are registered unconditionally; each request's availability is decided by the
 * permission callbacks.
 *
 * @since 3.15.0
 */
class Loader {

	/**
	 * Boot the integration.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	private function hooks() {

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'wp_logout', [ $this, 'clear_session_on_logout' ] );

		if ( is_admin() ) {
			( new Admin\ChatShell() )->hooks();
		}
	}

	/**
	 * Register the REST routes.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function register_routes() {

		( new RestApi() )->register_routes();
	}

	/**
	 * Delete the logging-out user's session.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id The user logging out (passed since WP 5.5).
	 *
	 * @return void
	 */
	public function clear_session_on_logout( $user_id = 0 ) {

		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( $user_id <= 0 ) {
			return;
		}

		$store = new SessionStore();

		// No stored session means no step to outrun, since a step always saves
		// before it steps, and every logout would otherwise leave a marker row
		// behind for five minutes. Residual case: a session evicted mid-step
		// and a logout in the same window, where the step still saves back.
		if ( $store->get( $user_id ) === null ) {
			return;
		}

		// Recorded, not a plain delete: a step still in flight must not write
		// the session back for a user who has logged out.
		$store->clear( $user_id );
	}
}
