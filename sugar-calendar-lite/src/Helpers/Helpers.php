<?php

namespace Sugar_Calendar\Helpers;

use Sugar_Calendar\Plugin;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;

/**
 * Class with all the misc helper functions that don't belong elsewhere.
 *
 * @since 3.0.0
 */
class Helpers {

	/**
	 * Get the default user agent.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_default_user_agent() {

		$license_type = Plugin::instance()->get_license_type();

		return 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . '; SugarCalendar/' . $license_type . '-' . SC_PLUGIN_VERSION;
	}

	/**
	 * Get UTM URL.
	 *
	 * @since 3.0.0
	 *
	 * @param string       $url Base url.
	 * @param array|string $utm Array of UTM params, or if string provided - utm_content URL parameter.
	 *
	 * @return string
	 */
	public static function get_utm_url( $url, $utm ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Defaults.
		$source   = 'WordPress';
		$medium   = 'plugin-settings';
		$campaign = Plugin::instance()->is_pro() ? 'plugin' : 'liteplugin';
		$content  = 'general';
		$locale   = get_user_locale();

		if ( is_array( $utm ) ) {
			if ( isset( $utm['source'] ) ) {
				$source = $utm['source'];
			}
			if ( isset( $utm['medium'] ) ) {
				$medium = $utm['medium'];
			}
			if ( isset( $utm['campaign'] ) ) {
				$campaign = $utm['campaign'];
			}
			if ( isset( $utm['content'] ) ) {
				$content = $utm['content'];
			}
			if ( isset( $utm['locale'] ) ) {
				$locale = $utm['locale'];
			}
		} elseif ( is_string( $utm ) ) {
			$content = $utm;
		}

		$query_args = [
			'utm_source'   => esc_attr( rawurlencode( $source ) ),
			'utm_medium'   => esc_attr( rawurlencode( $medium ) ),
			'utm_campaign' => esc_attr( rawurlencode( $campaign ) ),
			'utm_locale'   => esc_attr( sanitize_key( $locale ) ),
		];

		if ( ! empty( $content ) ) {
			$query_args['utm_content'] = esc_attr( rawurlencode( $content ) );
		}

		return add_query_arg( $query_args, $url );
	}

	/**
	 * Upgrade link used within the various admin pages.
	 *
	 * @since 3.0.0
	 *
	 * @param array|string $utm Array of UTM params, or if string provided - utm_content URL parameter.
	 *
	 * @return string
	 */
	public static function get_upgrade_link( $utm ) {

		$url = self::get_utm_url( 'https://sugarcalendar.com/lite-upgrade/', $utm );

		/**
		 * Filters upgrade link.
		 *
		 * @since 3.0.0
		 *
		 * @param string $url Upgrade link.
		 */
		return apply_filters( 'sugar_calendar_get_upgrade_link', $url ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Import Plugin_Upgrader class from core.
	 *
	 * @since 3.0.0
	 */
	public static function include_plugin_upgrader() {

		/** \WP_Upgrader class */
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		/** \Plugin_Upgrader class */
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
	}

	/**
	 * Whether the current request is a WP CLI request.
	 *
	 * @since 3.0.0
	 */
	public static function is_wp_cli() {

		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Generate a random hex color code.
	 *
	 * @since 3.6.0
	 *
	 * @return string Random hex color code with leading #.
	 */
	public static function generate_random_hex_color() {

		return sprintf( '#%06X', wp_rand( 0, 0xFFFFFF ) );
	}

	/**
	 * Get tags slug.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_tags_slug() {

		return TagsHelpers::get_tags_taxonomy_id();
	}

	/**
	 * Get comma-separated string from an array of words.
	 *
	 * @since 3.7.0
	 *
	 * @param array $words Words array.
	 * @param bool  $sep   Separator of the last element.
	 *
	 * @return string
	 */
	public static function array_string_list( $words, $sep = true ) {

		$separator = $sep ?
			__( 'and', 'sugar-calendar-lite' ) :
			__( 'or', 'sugar-calendar-lite' );

		$last  = array_slice( $words, - 1 );
		$first = implode( ', ', array_slice( $words, 0, - 1 ) );
		$both  = array_filter( array_merge( [ $first ], $last ) );

		return implode( ' ' . $separator . ' ', $both );
	}
}
