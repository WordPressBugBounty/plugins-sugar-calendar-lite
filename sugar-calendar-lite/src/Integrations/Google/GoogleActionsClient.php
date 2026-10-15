<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Integrations\OAuthRelay\AbstractIntegrationActionsOAuthClient;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionInterface;

/**
 * Google actions client.
 *
 * Provider-specific relay calls for Google Calendar.
 *
 * @since 3.16.0
 */
class GoogleActionsClient extends AbstractIntegrationActionsOAuthClient {

	/**
	 * List the connected account's writable calendars via the relay.
	 *
	 * @since 3.16.0
	 *
	 * @param OAuthConnectionInterface $connection OAuth connection.
	 *
	 * @return array|\WP_Error List of [ 'id' => string, 'name' => string ] entries, or error.
	 */
	public function list_calendars( OAuthConnectionInterface $connection ) {

		if ( $this->is_test_mode() ) {
			/**
			 * Filter to stub the Google list-calendars response in E2E tests.
			 *
			 * @since 3.16.0
			 *
			 * @param mixed                    $response   Default null; return an array to short-circuit, WP_Error to fail.
			 * @param OAuthConnectionInterface $connection OAuth connection.
			 */
			return apply_filters( 'sc_google_test_list_calendars_response', null, $connection ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WPForms.PHP.ValidateHooks.InvalidHookName -- Internal test-mode hook seam (E2E only).
		}

		$response = $this->get( '/google/calendars', $connection )->send();
		$result   = $this->handle_response( $response );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->calendars_from_response( $result );
	}

	/**
	 * Turn the relay's calendar list into id + name pairs.
	 *
	 * @since 3.16.0
	 *
	 * @param mixed $result Decoded relay response.
	 *
	 * @return array List of [ 'id' => string, 'name' => string ] entries.
	 */
	private function calendars_from_response( $result ): array {

		if ( ! is_array( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
			return [];
		}

		$calendars = [];

		foreach ( $result['items'] as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}

			// The user's own rename of a shared calendar wins over the owner's title.
			$name = ! empty( $item['summaryOverride'] ) ? $item['summaryOverride'] : ( $item['summary'] ?? $item['id'] );

			$calendars[] = [
				'id'   => (string) $item['id'],
				'name' => (string) $name,
			];
		}

		return $calendars;
	}

	/**
	 * Create a Google Calendar event via the relay.
	 *
	 * @since 3.16.0
	 *
	 * @param OAuthConnectionInterface $connection  OAuth connection.
	 * @param string                   $calendar_id Google calendar id.
	 * @param array                    $body        Google event resource body.
	 *
	 * @return array|\WP_Error Google event resource or error.
	 */
	public function create_event( OAuthConnectionInterface $connection, string $calendar_id, array $body ) {

		if ( $this->is_test_mode() ) {
			/**
			 * Filter to stub the Google create-event response in tests.
			 *
			 * @since 3.16.0
			 *
			 * @param mixed                    $response    Default null; array to short-circuit, WP_Error to fail.
			 * @param string                   $calendar_id The calendar id the production code passed.
			 * @param array                    $body        The event body the production code built.
			 * @param OAuthConnectionInterface $connection  OAuth connection.
			 */
			return apply_filters( 'sc_google_test_create_event_response', null, $calendar_id, $body, $connection ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WPForms.PHP.ValidateHooks.InvalidHookName -- Internal test-mode hook seam.
		}

		$response = $this->post( '/google/calendars/' . $this->calendar_segment( $calendar_id ) . '/events', $connection )
			->json( [ 'params' => [ 'body' => $body ] ] )
			->timeout( 30 )
			->send();

		return $this->handle_response( $response );
	}

	/**
	 * Create a Google Meet space via the relay.
	 *
	 * @since 3.16.0
	 *
	 * @param OAuthConnectionInterface $connection OAuth connection.
	 * @param array                    $config     Space config (accessType).
	 *
	 * @return array|\WP_Error Google Space resource or error.
	 */
	public function create_meet_space( OAuthConnectionInterface $connection, array $config ) {

		if ( $this->is_test_mode() ) {
			/**
			 * Filter to stub the Google Meet create-space response in tests.
			 *
			 * @since 3.16.0
			 *
			 * @param mixed                    $response   Default null; array to short-circuit, WP_Error to fail.
			 * @param array                    $config     The space config the production code built.
			 * @param OAuthConnectionInterface $connection OAuth connection.
			 */
			return apply_filters( 'sc_google_test_create_meet_space_response', null, $config, $connection ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WPForms.PHP.ValidateHooks.InvalidHookName -- Internal test-mode hook seam.
		}

		$response = $this->post( '/google/meet/spaces', $connection )
			->json( [ 'params' => [ 'body' => [ 'config' => $config ] ] ] )
			->timeout( 30 )
			->send();

		return $this->handle_response( $response );
	}

	/**
	 * Update a Google Calendar event via the relay (PATCH).
	 *
	 * @since 3.16.0
	 *
	 * @param OAuthConnectionInterface $connection  OAuth connection.
	 * @param string                   $calendar_id Google calendar id.
	 * @param string                   $event_id    Google event id.
	 * @param array                    $body        Google event resource body.
	 *
	 * @return array|\WP_Error Google event resource, or error (code `not_found` when the copy is gone).
	 */
	public function update_event( OAuthConnectionInterface $connection, string $calendar_id, string $event_id, array $body ) {

		if ( $this->is_test_mode() ) {
			/**
			 * Filter to stub the Google update-event response in tests.
			 *
			 * @since 3.16.0
			 *
			 * @param mixed                    $response    Default null; array to short-circuit, WP_Error to fail.
			 * @param string                   $calendar_id The calendar id the production code passed.
			 * @param string                   $event_id    The event id the production code passed.
			 * @param array                    $body        The event body the production code built.
			 * @param OAuthConnectionInterface $connection  OAuth connection.
			 */
			return apply_filters( 'sc_google_test_update_event_response', null, $calendar_id, $event_id, $body, $connection ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WPForms.PHP.ValidateHooks.InvalidHookName -- Internal test-mode hook seam.
		}

		$response = $this->patch( '/google/calendars/' . $this->calendar_segment( $calendar_id ) . '/events/' . rawurlencode( $event_id ), $connection )
			->json( [ 'params' => [ 'body' => $body ] ] )
			->timeout( 30 )
			->send();

		return $this->handle_response( $response );
	}

	/**
	 * Delete several Google Calendar events in one relay call.
	 *
	 * @since 3.16.0
	 *
	 * @param OAuthConnectionInterface $connection OAuth connection.
	 * @param array                    $pairs      List of [ 'calendarId' => string, 'eventId' => string ].
	 *
	 * @return true|\WP_Error
	 */
	public function delete_events( OAuthConnectionInterface $connection, array $pairs ) {

		if ( $this->is_test_mode() ) {
			/**
			 * Filter to stub the Google batch-delete response in tests.
			 *
			 * @since 3.16.0
			 *
			 * @param mixed                    $response   Default null; true to short-circuit success, WP_Error to fail.
			 * @param array                    $pairs      The calendar/event id pairs the production code passed.
			 * @param OAuthConnectionInterface $connection OAuth connection.
			 */
			return apply_filters( 'sc_google_test_delete_events_response', null, $pairs, $connection ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WPForms.PHP.ValidateHooks.InvalidHookName -- Internal test-mode hook seam.
		}

		$response = $this->post( '/google/events/batch-delete', $connection )
			->json( [ 'params' => [ 'events' => array_values( $pairs ) ] ] )
			->timeout( 30 )
			->send();

		$result = $this->handle_response( $response );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * List events, or changes since a sync token, via the relay.
	 *
	 * @since 3.16.0
	 *
	 * @param OAuthConnectionInterface $connection  OAuth connection.
	 * @param string                   $calendar_id Google calendar id.
	 * @param array                    $query       Google list query (syncToken, pageToken, timeMin, maxResults, showDeleted, singleEvents).
	 *
	 * @return array|\WP_Error Google list body, or error (code `full_sync_required` when the token is dead).
	 */
	public function list_events( OAuthConnectionInterface $connection, string $calendar_id, array $query ) {

		if ( $this->is_test_mode() ) {
			/**
			 * Filter to stub the Google list-events response in tests.
			 *
			 * @since 3.16.0
			 *
			 * @param mixed                    $response    Default null; array to short-circuit, WP_Error to fail.
			 * @param string                   $calendar_id The calendar id the production code passed.
			 * @param array                    $query       The query the production code built.
			 * @param OAuthConnectionInterface $connection  OAuth connection.
			 */
			return apply_filters( 'sc_google_test_list_events_response', null, $calendar_id, $query, $connection ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WPForms.PHP.ValidateHooks.InvalidHookName -- Internal test-mode hook seam.
		}

		$response = $this->get( '/google/calendars/' . $this->calendar_segment( $calendar_id ) . '/events', $connection )
			->query( $query )
			->timeout( 30 )
			->send();

		return $this->handle_response( $response );
	}

	/**
	 * List one page of changes: everything since a sync token, or upcoming events on a first full sync.
	 *
	 * @since 3.16.0
	 *
	 * @param OAuthConnectionInterface $connection  OAuth connection.
	 * @param string                   $calendar_id Google calendar id.
	 * @param string                   $sync_token  Stored sync token, '' before the first full sync finished.
	 * @param string                   $page_token  Stored page token, '' on a first page.
	 *
	 * @return array|\WP_Error Google list body, or error (code `full_sync_required` when the token is dead).
	 */
	public function list_changes( OAuthConnectionInterface $connection, string $calendar_id, string $sync_token, string $page_token ) {

		/**
		 * Filter the number of Google events fetched per background run.
		 *
		 * @since 3.16.0
		 *
		 * @param int    $size        Events per page. Default 250.
		 * @param string $calendar_id Google calendar id.
		 */
		$chunk = max( 1, min( 2500, (int) apply_filters( 'sugar_calendar_google_pull_chunk_size', 250, $calendar_id ) ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		$query = [
			'maxResults'   => $chunk,
			'showDeleted'  => 'true',
			'singleEvents' => 'false',
		];

		// Google wants every other parameter unchanged next to pageToken, so the base is rebuilt the same way.
		if ( $sync_token === '' ) {
			$query['timeMin'] = gmdate( 'Y-m-d\TH:i:s\Z' );
		} else {
			$query['syncToken'] = $sync_token;
		}

		if ( $page_token !== '' ) {
			$query['pageToken'] = $page_token;
		}

		return $this->list_events( $connection, $calendar_id, $query );
	}

	/**
	 * Open a Google push channel on a calendar via the relay.
	 *
	 * @since 3.16.0
	 *
	 * @param OAuthConnectionInterface $connection       OAuth connection; its app id and refresh token go in the body.
	 * @param string                   $calendar_id      Google calendar id.
	 * @param string                   $site_webhook_url This site's webhook receiver URL.
	 *
	 * @return array|\WP_Error [ 'channel_id', 'resource_id', 'expiration' ] or error.
	 */
	public function watch_calendar( OAuthConnectionInterface $connection, string $calendar_id, string $site_webhook_url ) {

		$body = [
			'site_webhook_url' => $site_webhook_url,
			'oauth_app_id'     => $connection->get_app_id(),
			'refresh_token'    => $connection->get_refresh_token(),
		];

		if ( $this->is_test_mode() ) {
			/**
			 * Filter to stub the Google watch response in tests.
			 *
			 * @since 3.16.0
			 *
			 * @param mixed                    $response    Default null; array to short-circuit, WP_Error to fail.
			 * @param string                   $calendar_id The calendar id the production code passed.
			 * @param array                    $body        The body the production code built.
			 * @param OAuthConnectionInterface $connection  OAuth connection.
			 */
			return apply_filters( 'sc_google_test_watch_calendar_response', null, $calendar_id, $body, $connection ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WPForms.PHP.ValidateHooks.InvalidHookName -- Internal test-mode hook seam.
		}

		$response = $this->post( '/google/calendars/' . $this->calendar_segment( $calendar_id ) . '/watch', $connection )
			->json( [ 'params' => [ 'body' => $body ] ] )
			->timeout( 30 )
			->send();

		return $this->handle_response( $response );
	}

	/**
	 * Close the Google push channel on a calendar via the relay.
	 *
	 * @since 3.16.0
	 *
	 * @param OAuthConnectionInterface $connection  OAuth connection.
	 * @param string                   $calendar_id Google calendar id.
	 *
	 * @return true|\WP_Error
	 */
	public function stop_watch( OAuthConnectionInterface $connection, string $calendar_id ) {

		if ( $this->is_test_mode() ) {
			/**
			 * Filter to stub the Google stop-watch response in tests.
			 *
			 * @since 3.16.0
			 *
			 * @param mixed                    $response    Default null; true to short-circuit success, WP_Error to fail.
			 * @param string                   $calendar_id The calendar id the production code passed.
			 * @param OAuthConnectionInterface $connection  OAuth connection.
			 */
			return apply_filters( 'sc_google_test_stop_watch_response', null, $calendar_id, $connection ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WPForms.PHP.ValidateHooks.InvalidHookName -- Internal test-mode hook seam.
		}

		$response = $this->delete( '/google/calendars/' . $this->calendar_segment( $calendar_id ) . '/watch', $connection )
			->timeout( 30 )
			->send();

		$result = $this->handle_response( $response );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * A calendar id as a relay path segment, with `@` left literal.
	 *
	 * A layer in front of the relay decodes `%40` before the HMAC check, so the signed path no longer matches
	 * and the call 401s; `@` is legal in a path segment, so it reads the same encoded or not.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return string
	 */
	private function calendar_segment( string $calendar_id ): string {

		return str_replace( '%40', '@', rawurlencode( $calendar_id ) );
	}
}
