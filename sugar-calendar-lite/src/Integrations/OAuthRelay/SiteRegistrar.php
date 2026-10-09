<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use Sugar_Calendar\Vendor\ProductApi\Auth\AuthOptions;
use Sugar_Calendar\Vendor\ProductApi\Auth\SiteRegistration;
use Sugar_Calendar\Vendor\ProductApi\Options;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;
use Throwable;
use WP_Error;

/**
 * Registers the site with the Product API, which mints the credentials every relay call is signed with.
 *
 * Shared by the Zoom Connect click, the Lite usage-tracking opt-in and SiteRegistrationTask.
 *
 * @since 3.15.0
 */
class SiteRegistrar {

	/**
	 * Register the site unless it already is.
	 *
	 * @since 3.15.0
	 *
	 * @param bool $bypass_rate_limit Clear the client's five-a-day limit first. Only for an admin's own click.
	 *
	 * @return true|WP_Error
	 */
	public function register( $bypass_rate_limit = false ) {

		try {
			$registration = ProductApi::get( SiteRegistration::class );

			$this->maybe_reset_stale_registration( $registration );

			if ( $registration->is_registered() ) {
				return true;
			}

			if ( $bypass_rate_limit ) {
				ProductApi::get( Options::class )->delete_transient( 'rate_limit_auth_register_site' );
			}

			$result = $registration->register();
		} catch ( Throwable $e ) {
			return new WP_Error( 'relay_unavailable', $e->getMessage() );
		}

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Whether the site holds credentials for its current URL.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public function is_registered() {

		try {
			return ProductApi::get( SiteRegistration::class )->is_registered();
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Reset a stale site registration when the site URL has changed.
	 *
	 * When the site URL changes, the relay still associates the old domain
	 * with the existing installation ID. Clearing the stored credentials and
	 * generating a fresh installation ID forces a clean re-registration with
	 * the new domain. Ported from Bookings
	 * IntegrationsController::maybe_reset_stale_registration(); moved here from
	 * Admin\Pages\Integrations\Zoom.
	 *
	 * @since 3.15.0
	 *
	 * @param SiteRegistration $registration SiteRegistration instance.
	 *
	 * @return void
	 */
	private function maybe_reset_stale_registration( $registration ) {

		// Already registered with the current URL — nothing to do.
		if ( $registration->is_registered() ) {
			return;
		}

		$auth_options = ProductApi::get(
			AuthOptions::class
		);

		// No credentials stored — first-time registration, no reset needed.
		if ( empty( $auth_options->get( 'site_id' ) ) ) {
			return;
		}

		// Credentials exist but the current URL isn't registered → domain
		// changed. Clear auth credentials so register() starts fresh.
		$auth_options->update(
			[
				'site_id'          => '',
				'signing_secret'   => '',
				'verification_key' => '',
				'site_urls'        => [],
			]
		);

		// Generate a new installation ID so the relay creates a fresh site
		// entry instead of returning stale credentials for the old domain.
		$options = ProductApi::get(
			Options::class
		);

		$options->update(
			[
				'wp_installation_id' => substr( wp_hash( wp_generate_uuid4() ), 0, 30 ),
			]
		);

		// Clear the rate limit so the fresh registration isn't blocked by
		// previous attempts.
		$options->delete_transient( 'rate_limit_auth_register_site' );
	}
}
