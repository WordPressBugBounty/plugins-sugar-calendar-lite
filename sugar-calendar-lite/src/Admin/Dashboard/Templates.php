<?php

namespace Sugar_Calendar\Admin\Dashboard;

/**
 * Renders the Dashboard templates.
 *
 * Not `Block\Common\Template`, which only serves the block directories.
 *
 * @since 3.18.0
 */
class Templates {

	/**
	 * Render a template to a string.
	 *
	 * @since 3.18.0
	 *
	 * @param string $name Template path under `templates/`, without `.php`.
	 * @param array  $args Variables the template receives by name.
	 *
	 * @return string
	 */
	public static function get_html( string $name, array $args = [] ): string {

		$name = ltrim( $name, '/' );

		if ( $name === '' || strpos( $name, '..' ) !== false ) {
			return '';
		}

		$path = __DIR__ . '/templates/' . $name . '.php';

		if ( ! is_readable( $path ) ) {
			return '';
		}

		// The closure's scope holds only the template args, so an arg such as `name` is
		// never skipped by EXTR_SKIP for colliding with this method's own variables.
		$render = static function ( $__file, $__args ) {

			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- The template contract is named variables.
			extract( $__args, EXTR_SKIP );

			ob_start();

			require $__file;

			return (string) ob_get_clean();
		};

		return $render( $path, $args );
	}
}
