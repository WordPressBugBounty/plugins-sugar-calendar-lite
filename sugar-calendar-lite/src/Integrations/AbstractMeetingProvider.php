<?php

namespace Sugar_Calendar\Integrations;

use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;

/**
 * Shared shape of an online meeting provider: connection check, meta footprint.
 *
 * @since 3.16.0
 * @since 3.18.0 No credits service: the relay refuses a create the account cannot pay for.
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
	 * Credits service. Nothing in the plugin reads it: the relay refuses a create the account
	 * cannot pay for.
	 *
	 * @since 3.16.0
	 * @deprecated 3.18.0
	 *
	 * @var CreditsService|null
	 */
	protected $credits;

	/**
	 * Constructor, kept so a provider written against 3.16.0 still loads: it calls
	 * parent::__construct( $credits ) and may read $this->credits. Providers need no credits service,
	 * and passing one raises a deprecation notice before the removal does.
	 *
	 * @since 3.16.0
	 * @deprecated 3.18.0
	 *
	 * @param CreditsService|null $credits Credits service.
	 */
	public function __construct( ?CreditsService $credits = null ) {

		if ( $credits !== null ) {
			_deprecated_argument( __METHOD__, '3.18.0', 'Meeting providers need no credits service: the relay refuses a create the account cannot pay for.' );
		}

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
	 * Available when an active connection exists.
	 *
	 * Credits do not gate it: the relay refuses a create the account cannot pay for.
	 *
	 * @since 3.16.0
	 * @since 3.18.0 No longer reads the stored credits.
	 *
	 * @return bool
	 */
	public function is_available(): bool {

		return $this->is_connected();
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
