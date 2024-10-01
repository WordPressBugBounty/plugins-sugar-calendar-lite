<?php

namespace Sugar_Calendar\Common;

/**
 * Class Utils.
 *
 * @since 3.0.0
 */
class Utils {

	/**
	 * Unhook WP action that was hooked by a legacy class.
	 *
	 * We use this method to unhook filter or actions that was hooked using a class
	 * without public access.
	 *
	 * @since 3.0.0
	 *
	 * @param string $filter_to_unhook   The filter where the function was hooked.
	 * @param string $legacy_class       The fully qualified name of the class that hooked.
	 * @param string $function_to_unhook Function/method name to unhook.
	 *
	 * @return bool|null
	 */
	public static function unhook_action( $filter_to_unhook, $legacy_class, $function_to_unhook ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks, Generic.Metrics.NestingLevel.MaxExceeded

		global $wp_filter;

		foreach ( $wp_filter as $filter_name => $filter ) {

			if ( $filter_name !== $filter_to_unhook ) {
				continue;
			}

			foreach ( $filter as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {

					if (
						! is_array( $callback ) ||
						! array_key_exists( 'function', $callback ) ||
						! is_array( $callback['function'] ) ||
						! $callback['function'][0] instanceof $legacy_class ||
						$callback['function'][1] !== $function_to_unhook
					) {
						continue;
					}

					return remove_action( $filter_name, [ $callback['function'][0], $callback['function'][1] ], $priority );
				}
			}
		}

		return null;
	}
}
