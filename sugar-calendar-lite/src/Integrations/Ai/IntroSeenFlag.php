<?php
/**
 * Per-user "opened the chat" flag for the Gia AI bubble's introduction.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * IntroSeenFlag — once set, the bubble stops introducing Gia to the user.
 *
 * @since 3.15.0
 */
class IntroSeenFlag {

	/**
	 * User meta holding when the user first opened the chat.
	 *
	 * @since 3.15.0
	 */
	const META_KEY = 'sugar_calendar_ai_intro_seen';

	/**
	 * Whether the user has opened the chat.
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
	 * Record that the user opened the chat. The first call wins; later calls change nothing.
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
		 * A user opened the Gia AI chat for the first time.
		 *
		 * @since 3.15.0
		 *
		 * @param int $user_id User id.
		 */
		do_action( 'sugar_calendar_ai_first_opened', $user_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
