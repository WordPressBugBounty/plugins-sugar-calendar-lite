<?php
/**
 * Who may call the Gia AI routes.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;

/**
 * AccessPolicy — the permission callbacks behind `sugar-calendar/ai/v1`.
 *
 * @since 3.15.0
 */
class AccessPolicy {

	/**
	 * Session persistence, read to find the funnel a continuation runs in.
	 *
	 * @since 3.15.0
	 *
	 * @var SessionStore
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param SessionStore|null $store Session store.
	 */
	public function __construct( ?SessionStore $store = null ) {

		$this->store = $store ?? new SessionStore();
	}

	/**
	 * Guards for the routes that spend a relay round, /turn and /usage: signed
	 * in, toggle on, relay ready, and one funnel's capability.
	 *
	 * @since 3.15.0
	 *
	 * @return true|WP_Error
	 */
	public function can_access() {

		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'sc_ai_forbidden', __( 'You must be logged in.', 'sugar-calendar-lite' ), [ 'status' => rest_authorization_required_code() ] );
		}

		if ( ! Availability::is_enabled() ) {
			return new WP_Error( 'sc_ai_disabled', __( 'The assistant is turned off.', 'sugar-calendar-lite' ), [ 'status' => 403 ] );
		}

		if ( ! Availability::relay_ready() ) {
			return new WP_Error( 'sc_ai_unavailable', __( 'The assistant is not available on this site.', 'sugar-calendar-lite' ), [ 'status' => 503 ] );
		}

		return $this->has_funnel_capability();
	}

	/**
	 * Guards for the routes that only touch local state: /session and /stop.
	 *
	 * Clearing or stopping a session must not depend on the relay or the
	 * site-wide toggle. Behind the full guard, an admin turning the assistant
	 * off during a turn left that user unable to clear it, and the stale
	 * `in_progress` status then refused every prompt once the toggle came back.
	 *
	 * @since 3.15.0
	 *
	 * @return true|WP_Error
	 */
	public function can_manage_session() {

		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'sc_ai_forbidden', __( 'You must be logged in.', 'sugar-calendar-lite' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return $this->has_funnel_capability();
	}

	/**
	 * Guard for /turn: the base guards plus the capability of the resolved funnel.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public function can_turn( WP_REST_Request $request ) {

		$base = $this->can_access();

		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$funnel = (string) $request->get_param( 'funnel' );

		if ( $funnel === '' ) {
			$session = $this->store->get( get_current_user_id() );
			$funnel  = $session !== null ? $session->funnel() : '';
		}

		// No funnel resolved yet: the handler decides (400). The base guard
		// already proved the user can run at least one funnel.
		if ( $funnel === '' ) {
			return true;
		}

		if ( ! current_user_can( Availability::funnel_capability( $funnel ) ) ) {
			return new WP_Error( 'sc_ai_forbidden', __( 'You cannot use the assistant here.', 'sugar-calendar-lite' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Whether the user passes at least one funnel's capability.
	 *
	 * @since 3.15.0
	 *
	 * @return true|WP_Error
	 */
	private function has_funnel_capability() {

		if ( Availability::user_can_use() ) {
			return true;
		}

		return new WP_Error( 'sc_ai_forbidden', __( 'You cannot use the assistant.', 'sugar-calendar-lite' ), [ 'status' => 403 ] );
	}
}
