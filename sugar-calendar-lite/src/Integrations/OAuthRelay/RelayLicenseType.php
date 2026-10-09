<?php
/**
 * Resolves the relay route tier for this install.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\OAuthRelay;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Vendor\ProductApi\Context;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;
use Throwable;

/**
 * RelayLicenseType: the `{license_type}` segment every relay route takes.
 *
 * One home for the rule; three private copies had already drifted on the
 * unconfigured-container case.
 *
 * @since 3.15.0
 */
class RelayLicenseType {

	/**
	 * `pro` only when Pro is active and the license is valid, else `lite`.
	 *
	 * Falls back to `lite` when the ProductApi container is unavailable.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public static function get(): string {

		try {
			$context = ProductApi::get( Context::class );
		} catch ( Throwable $e ) {
			return 'lite';
		}

		return ( $context->is_pro() && $context->is_license_valid() ) ? 'pro' : 'lite';
	}
}
