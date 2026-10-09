<?php
/**
 * Per-user "clicked Rate Plugin" flag for the Gia AI End card.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * PluginRatedFlag — once set, the End card stops asking the user to rate the plugin.
 *
 * @since 3.15.0
 */
class PluginRatedFlag {

	/**
	 * User meta holding when the user clicked Rate Plugin on the End card.
	 *
	 * @since 3.15.0
	 */
	const META_KEY = 'sugar_calendar_ai_plugin_rated';

	/**
	 * Whether the user already clicked Rate Plugin.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 *
	 * @return bool
	 */
	public static function is_set( int $user_id ): bool {

		return (bool) get_user_meta( $user_id, self::META_KEY, true );
	}

	/**
	 * Record that the user clicked Rate Plugin. The first call wins; later calls change nothing.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 *
	 * @return void
	 */
	public static function mark( int $user_id ) {

		if ( self::is_set( $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, self::META_KEY, time() );

		/**
		 * A user clicked Rate Plugin on the Gia AI End card for the first time.
		 *
		 * @since 3.15.0
		 *
		 * @param int $user_id User id.
		 */
		do_action( 'sugar_calendar_ai_plugin_rated', $user_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
