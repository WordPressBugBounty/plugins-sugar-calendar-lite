<?php

namespace Sugar_Calendar\Integrations;

use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;

/**
 * Shared shape of an online meeting provider: connection and credits checks, meta footprint.
 *
 * @since 3.16.0
 */
abstract class AbstractMeetingProvider implements MeetingProviderInterface {

	/**
	 * Event meta keys the manager clears on detach.
	 *
	 * @since 3.16.0
	 *
	 * @var string[]
	 */
	public const META_KEYS = [ 'meeting_provider', 'meeting_id', 'join_url', 'meeting_password', 'meeting_settings' ];

	/**
	 * Credits service.
	 *
	 * @since 3.16.0
	 *
	 * @var CreditsService
	 */
	protected $credits;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param CreditsService $credits Credits service.
	 */
	public function __construct( CreditsService $credits ) {

		$this->credits = $credits;
	}

	/**
	 * The provider's active connection, or an error saying why there is none.
	 *
	 * @since 3.16.0
	 *
	 * @return object|\WP_Error
	 */
	abstract protected function get_connection();

	/**
	 * Whether a usable connection exists.
	 *
	 * @since 3.16.0
	 *
	 * @return bool
	 */
	public function is_connected(): bool {

		return ! is_wp_error( $this->get_connection() );
	}

	/**
	 * Connected and credits remain. Credits are read from cache and fail open.
	 *
	 * @since 3.16.0
	 *
	 * @return bool
	 */
	public function is_available(): bool {

		return $this->is_connected() && ! $this->credits->is_out_of_credits();
	}

	/**
	 * Meta keys cleared on detach.
	 *
	 * @since 3.16.0
	 *
	 * @return string[]
	 */
	public function get_meeting_meta_keys(): array {

		return static::META_KEYS;
	}
}
