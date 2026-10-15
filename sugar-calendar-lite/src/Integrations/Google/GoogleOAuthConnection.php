<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Integrations\OAuthRelay\AbstractOAuthConnection;

/**
 * Google-specific OAuth connection.
 *
 * Google access tokens expire after 1 hour, like Zoom's, so the same 10-minute
 * refresh buffer applies.
 *
 * @since 3.16.0
 */
class GoogleOAuthConnection extends AbstractOAuthConnection {

	/**
	 * Seconds before expiration to trigger refresh.
	 *
	 * @since 3.16.0
	 *
	 * @var int
	 */
	protected $expiration_buffer = 600;

	/**
	 * Whether the calendar scopes were approved on the consent screen.
	 *
	 * @since 3.16.0
	 *
	 * @return bool
	 */
	public function has_calendar_access(): bool {

		return $this->has_scopes(
			[
				'https://www.googleapis.com/auth/calendar',
				'https://www.googleapis.com/auth/calendar.events',
			]
		);
	}

	/**
	 * Whether the Google Meet scopes were approved on the consent screen.
	 *
	 * @since 3.16.0
	 *
	 * @return bool
	 */
	public function has_meet_access(): bool {

		return $this->has_scopes(
			[
				'https://www.googleapis.com/auth/meetings.space.created',
				'https://www.googleapis.com/auth/meetings.space.readonly',
			]
		);
	}

	/**
	 * Whether every wanted scope is in the space-separated list Google returned at connect.
	 *
	 * @since 3.16.0
	 *
	 * @param string[] $wanted Full scope URLs.
	 *
	 * @return bool
	 */
	protected function has_scopes( array $wanted ): bool {

		$granted = explode( ' ', $this->get_row()['scope'] ?? '' );

		foreach ( $wanted as $scope ) {
			if ( ! in_array( $scope, $granted, true ) ) {
				return false;
			}
		}

		return true;
	}
}
