<?php

namespace Sugar_Calendar\Helpers;

/**
 * Build checklist action result arrays.
 *
 * @since 3.15.0
 */
class ActionResult {

	/**
	 * Build a successful or partial action result.
	 *
	 * @since 3.15.0
	 *
	 * @param string      $status       Result status.
	 * @param string      $message      Plain message.
	 * @param string      $redirect_url Optional redirect.
	 * @param bool        $reload       Whether the checklist should reload.
	 * @param string|null $plugin_state Actual plugin state, or null to omit the key.
	 *
	 * @return array
	 */
	public static function build( $status, $message, $redirect_url, $reload, $plugin_state = null ) {

		$result = [
			'status'       => $status,
			'message'      => wp_strip_all_tags( $message ),
			'redirect_url' => $redirect_url,
			'reload'       => (bool) $reload,
		];

		if ( $plugin_state !== null ) {
			$result['plugin_state'] = $plugin_state;
		}

		return $result;
	}

	/**
	 * Wrap a resolved destination as a navigation result.
	 *
	 * @since 3.15.0
	 *
	 * @param string|WP_Error $url Destination, or the error that prevented one.
	 *
	 * @return array|WP_Error
	 */
	public static function navigation( $url ) {

		return is_wp_error( $url ) ? $url : self::build( 'success', '', $url, false );
	}
}
