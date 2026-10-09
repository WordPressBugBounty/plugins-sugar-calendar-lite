<?php

namespace Sugar_Calendar;

use Sugar_Calendar\Helpers\Helpers;

/**
 * Subscribing to the Sugar Calendar newsletter.
 *
 * @since 3.15.0
 */
class Newsletter {

	/**
	 * Subscription endpoint URL.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const ENDPOINT = 'https://events.sugarcalendarapi.com/newsletter/v1/subscribe';

	/**
	 * Submit an email address to the newsletter.
	 *
	 * Only a 2xx carrying `success: true` counts: the endpoint can answer 200 with `success: false`.
	 *
	 * @since 3.15.0
	 *
	 * @param string $email Email address to subscribe.
	 *
	 * @return bool Whether the newsletter accepted the address.
	 */
	public function subscribe( $email ) {

		$endpoint = defined( 'SC_NEWSLETTER_URL' ) ? SC_NEWSLETTER_URL : self::ENDPOINT;

		$response = wp_remote_post(
			$endpoint,
			[
				'user-agent' => Helpers::get_default_user_agent(),
				'body'       => [
					'email'        => base64_encode( $email ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'license_type' => sugar_calendar()->get_license_type(),
				],
			]
		);

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) || ( $body['success'] ?? null ) !== true ) {
			return false;
		}

		/**
		 * Fires after the newsletter accepted an email address.
		 *
		 * @since 3.15.0
		 *
		 * @param string $email The accepted email address.
		 */
		do_action( 'sugar_calendar_newsletter_subscribe_after', $email );

		return true;
	}
}
