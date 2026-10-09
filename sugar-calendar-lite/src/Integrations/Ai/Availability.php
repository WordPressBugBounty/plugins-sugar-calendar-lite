<?php
/**
 * Availability rules for the Gia AI assistant.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Options;
use Sugar_Calendar\Vendor\ProductApi\Auth\SiteRegistration;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;
use Throwable;

/**
 * Whether the assistant may run, and who may run each funnel.
 *
 * @since 3.15.0
 */
class Availability {

	/**
	 * Settings option key for the site-wide toggle.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const OPTION_KEY = 'ai_assistant_enabled';

	/**
	 * Whether the site-wide toggle is on.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {

		/**
		 * Filters whether the Gia AI assistant is available site-wide.
		 *
		 * @since 3.15.0
		 *
		 * @param bool $enabled Whether the assistant is enabled.
		 */
		return (bool) apply_filters( 'sugar_calendar_ai_assistant_enabled', (bool) Options::get( self::OPTION_KEY, true ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Whether the Abilities API is present and the relay is actually callable.
	 *
	 * A configured client is not a callable relay: HMAC signing needs the
	 * credentials minted at site registration, which an install gets from
	 * `SiteRegistrationTask` in the background, the Lite usage-tracking opt-in
	 * or an OAuth connect, and without them every turn fails at
	 * send time with `site_not_registered`. Gating here turns that into one
	 * "not available on this site" instead of a generic error per attempt, and
	 * mirrors `Credits\CreditsService`, test-mode bypass included.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public static function relay_ready(): bool {

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return false;
		}

		try {
			ProductApi::client();

			if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) {
				return true;
			}

			return ProductApi::get( SiteRegistration::class )->is_registered();
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * The funnel slugs the assistant offers.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public static function funnels(): array {

		return array_keys( self::capability_map() );
	}

	/**
	 * The funnel => capability map, filtered.
	 *
	 * Both accessors read the map through here so a funnel added by a filter is
	 * offered and gated consistently. Without that the REST `enum` built from
	 * funnels() would reject the new slug before any capability check ran.
	 *
	 * The unfiltered defaults come from `Funnels`, which is also where each
	 * funnel's chat shell copy and blocked abilities live. The read-only
	 * funnel asks for `read_calendar`, which `map_meta_cap()` currently
	 * resolves to `edit_posts` like the other two, so the gate is the same
	 * today; it is named for what the funnel does so decoupling those caps
	 * later does not silently leave a read funnel demanding edit rights.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private static function capability_map(): array {

		/**
		 * Filters the funnel => capability map for the Gia AI assistant.
		 *
		 * @since 3.15.0
		 *
		 * @param array $map Funnel slug => capability name.
		 */
		return (array) apply_filters( 'sugar_calendar_ai_funnel_capabilities', Funnels::capabilities() ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * The capability that gates a funnel, or `do_not_allow` for an unknown one.
	 *
	 * @since 3.15.0
	 *
	 * @param string $funnel Funnel slug.
	 *
	 * @return string
	 */
	public static function funnel_capability( string $funnel ): string {

		$capability = self::capability_map()[ $funnel ] ?? 'do_not_allow';

		/**
		 * Filters the capability that gates a Gia AI funnel.
		 *
		 * @since 3.15.0
		 *
		 * @param string $capability Capability name.
		 * @param string $funnel     Funnel slug.
		 */
		return (string) apply_filters( 'sugar_calendar_ai_funnel_capability', $capability, $funnel ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Whether the current user may use the assistant at all.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public static function user_can_use(): bool {

		foreach ( self::funnels() as $funnel ) {
			if ( current_user_can( self::funnel_capability( $funnel ) ) ) {
				return true;
			}
		}

		return false;
	}
}
