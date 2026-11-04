<?php

namespace Sugar_Calendar\Integrations\Polylang;

use PLL_Base;

/**
 * Answers about Polylang's state, read through its API.
 *
 * @since 3.17.0
 */
class PolylangApi {

	/**
	 * Get Polylang's main object.
	 *
	 * @since 3.17.0
	 *
	 * @return PLL_Base
	 */
	public static function pll() {

		return PLL();
	}

	/**
	 * Whether the current user may copy changes to a post's translations.
	 *
	 * @since 3.17.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	public static function can_sync_translations( $post_id ) {

		return self::pll()->model->post->current_user_can_synchronize( (int) $post_id );
	}

	/**
	 * Whether Polylang's "Custom fields" synchronization is on.
	 *
	 * @since 3.17.0
	 *
	 * @return bool
	 */
	public static function is_custom_fields_sync_on() {

		$options = self::pll()->options ?? [];

		return in_array( 'post_meta', (array) ( $options['sync'] ?? [] ), true );
	}

	/**
	 * Get the slug of Polylang's default language.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	public static function get_default_language() {

		return (string) pll_default_language();
	}
}
