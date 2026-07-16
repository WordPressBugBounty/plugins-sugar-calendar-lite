<?php

namespace Sugar_Calendar\Integrations\Zoom;

use Sugar_Calendar\Integrations\OAuthRelay\AbstractIntegrationActionsOAuthClient;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionInterface;

/**
 * Zoom actions client.
 *
 * Provider-specific relay calls for Zoom meeting actions. Segment 2a
 * implements create only; update/delete land in 2b.
 *
 * @since 3.12.0
 */
class ZoomActionsClient extends AbstractIntegrationActionsOAuthClient {

	/**
	 * Create a Zoom meeting via the relay.
	 *
	 * @since 3.12.0
	 *
	 * @param OAuthConnectionInterface $connection   OAuth connection.
	 * @param array                    $meeting_data Meeting payload (topic, start_time, timezone, duration, settings).
	 *
	 * @return array|\WP_Error Zoom meeting object or error.
	 */
	public function create_meeting( OAuthConnectionInterface $connection, array $meeting_data ) {

		// E2E faithful test seam. The stub receives the REAL $meeting_data the
		// production code built and derives its response from it, so a malformed
		// payload fails the test instead of passing on a fabricated shape.
		if ( $this->is_test_mode() ) {

			/**
			 * Filter to stub the Zoom create-meeting response in E2E tests.
			 *
			 * @since 3.12.0
			 *
			 * @param mixed                    $response     Default null; return an array to short-circuit, WP_Error to fail.
			 * @param array                    $meeting_data The meeting payload the production code built.
			 * @param OAuthConnectionInterface $connection   OAuth connection.
			 */
			return apply_filters( 'sc_zoom_test_create_meeting_response', null, $meeting_data, $connection );
		}

		$response = $this->post( '/zoom/meetings', $connection )
			->json( [ 'params' => [ 'body' => $meeting_data ] ] )
			->send();

		return $this->handle_response( $response );
	}

	/**
	 * Update a Zoom meeting via the relay (PATCH).
	 *
	 * @since 3.12.0
	 *
	 * @param OAuthConnectionInterface $connection   OAuth connection.
	 * @param string                   $meeting_id   Zoom meeting id to update.
	 * @param array                    $meeting_data Meeting payload (topic, start_time, timezone, duration, settings).
	 *
	 * @return array|\WP_Error Updated meeting object or error.
	 */
	public function update_meeting( OAuthConnectionInterface $connection, string $meeting_id, array $meeting_data ) {

		// E2E faithful test seam. Receives the REAL $meeting_id + payload the
		// production code built, so a missing id or malformed payload fails the
		// test instead of passing on a fabricated shape.
		if ( $this->is_test_mode() ) {

			/**
			 * Filter to stub the Zoom update-meeting response in E2E tests.
			 *
			 * @since 3.12.0
			 *
			 * @param mixed                    $response     Default null; array to short-circuit success, WP_Error to fail.
			 * @param string                   $meeting_id   The meeting id the production code passed.
			 * @param array                    $meeting_data The meeting payload the production code built.
			 * @param OAuthConnectionInterface $connection   OAuth connection.
			 */
			return apply_filters( 'sc_zoom_test_update_meeting_response', null, $meeting_id, $meeting_data, $connection );
		}

		$response = $this->patch( '/zoom/meetings/' . rawurlencode( $meeting_id ), $connection )
			->json( [ 'params' => [ 'body' => $meeting_data ] ] )
			->send();

		return $this->handle_response( $response );
	}

	/**
	 * Delete a Zoom meeting via the relay (DELETE).
	 *
	 * @since 3.12.0
	 *
	 * @param OAuthConnectionInterface $connection OAuth connection.
	 * @param string                   $meeting_id Zoom meeting id to delete.
	 *
	 * @return true|\WP_Error True on success or error.
	 */
	public function delete_meeting( OAuthConnectionInterface $connection, string $meeting_id ) {

		// E2E faithful test seam — receives the REAL $meeting_id.
		if ( $this->is_test_mode() ) {

			/**
			 * Filter to stub the Zoom delete-meeting response in E2E tests.
			 *
			 * @since 3.12.0
			 *
			 * @param mixed                    $response   Default null; true to short-circuit success, WP_Error to fail.
			 * @param string                   $meeting_id The meeting id the production code passed.
			 * @param OAuthConnectionInterface $connection OAuth connection.
			 */
			return apply_filters( 'sc_zoom_test_delete_meeting_response', null, $meeting_id, $connection );
		}

		$response = $this->delete( '/zoom/meetings/' . rawurlencode( $meeting_id ), $connection )->send();
		$result   = $this->handle_response( $response );

		return is_wp_error( $result ) ? $result : true;
	}
}
