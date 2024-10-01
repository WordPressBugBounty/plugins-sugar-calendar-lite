<?php

namespace Sugar_Calendar\Helpers;

/**
 * Class WP provides WordPress shortcuts.
 *
 * @since 3.0.0
 */
class WP {

	/**
	 * The "queue" of notices.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	protected static $admin_notices = [];

	/**
	 * CSS class for a success notice.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	const ADMIN_NOTICE_SUCCESS = 'notice-success';

	/**
	 * CSS class for an error notice.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	const ADMIN_NOTICE_ERROR = 'notice-error';

	/**
	 * CSS class for an info notice.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	const ADMIN_NOTICE_INFO = 'notice-info';

	/**
	 * CSS class for a warning notice.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	const ADMIN_NOTICE_WARNING = 'notice-warning';

	/**
	 * Get the postfix for assets files - ".min" or empty.
	 * ".min" if in production mode.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function asset_min() {

		$min = '.min';

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$min = '';
		}

		return $min;
	}

	/**
	 * True if WP is processing an AJAX call.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public static function is_doing_ajax() {

		if ( function_exists( 'wp_doing_ajax' ) ) {
			return wp_doing_ajax();
		}

		return ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	/**
	 * True if I am in the Admin Panel, not doing AJAX.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public static function in_wp_admin() {

		return ( is_admin() && ! self::is_doing_ajax() );
	}

	/**
	 * Add a notice to the "queue of notices".
	 *
	 * @since 3.0.0
	 *
	 * @param string $message        Message text (HTML is OK).
	 * @param string $class          Display class (severity).
	 * @param bool   $is_dismissible Whether the message should be dismissible.
	 */
	public static function add_admin_notice( $message, $class = self::ADMIN_NOTICE_INFO, $is_dismissible = true ) {

		self::$admin_notices[] = [
			'message'        => $message,
			'class'          => $class,
			'is_dismissible' => (bool) $is_dismissible,
		];
	}

	/**
	 * Display all notices.
	 *
	 * @since 3.0.0
	 */
	public static function display_admin_notices() {

		foreach ( (array) self::$admin_notices as $notice ) :
			$dismissible = $notice['is_dismissible'] ? 'is-dismissible' : '';
			?>

            <div class="notice sugar-calendar-notice <?php echo esc_attr( $notice['class'] ); ?> notice <?php echo esc_attr( $dismissible ); ?>">
                <p>
					<?php echo wp_kses_post( $notice['message'] ); ?>
                </p>
            </div>

		<?php
		endforeach;
	}

	/**
	 * Wrapper for the WP `admin_url` method that should be used in the plugin.
	 *
	 * We can filter into it, to maybe call `network_admin_url` for multisite support.
	 *
	 * @since 3.0.0
	 *
	 * @param string $path   Optional path relative to the admin URL.
	 * @param string $scheme The scheme to use. Default is 'admin', which obeys force_ssl_admin() and is_ssl().
	 *                       'http' or 'https' can be passed to force those schemes.
	 *
	 * @return string Admin URL link with optional path appended.
	 */
	public static function admin_url( $path = '', $scheme = 'admin' ) {

		/**
		 * Filter the admin url.
		 *
		 * @since 3.0.0
		 *
		 * @param string $url    Admin url.
		 * @param string $path   Optional path relative to the admin URL.
		 * @param string $scheme The scheme to use. Default is 'admin', which obeys force_ssl_admin() and is_ssl().
		 *                       'http' or 'https' can be passed to force those schemes.
		 */
		return apply_filters( 'sugar_calendar_admin_url', admin_url( $path, $scheme ), $path, $scheme ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
