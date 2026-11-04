<?php

namespace Sugar_Calendar\Admin;

/**
 * Enqueues a webpack-built JSX bundle from `assets/jsx/build/`.
 *
 * @since 3.17.0
 */
class JsxBundle {

	/**
	 * Enqueue a bundle's script, and optionally its stylesheet, under one handle.
	 *
	 * @since 3.17.0
	 *
	 * @param string $handle Script (and style) handle.
	 * @param string $entry  Build-relative entry without extension, e.g. `admin/email-notifications`.
	 * @param array  $args   {
	 *     Optional arguments.
	 *
	 *     @type string[] $deps      Extra script dependencies, after the manifest's.
	 *     @type bool     $in_footer Load the script in the footer. Default false.
	 *     @type bool     $style     Also enqueue the entry's stylesheet. Default false.
	 * }
	 *
	 * @return bool Whether the bundle was enqueued.
	 */
	public static function enqueue( $handle, $entry, $args = [] ) {

		$manifest = self::get_manifest( $entry );

		if ( $manifest === null ) {
			return false;
		}

		ReactJsxRuntime::ensure_registered();

		$args += [
			'deps'      => [],
			'in_footer' => false,
			'style'     => false,
		];

		self::enqueue_script( $handle, $entry, $manifest, $args );

		if ( $args['style'] ) {
			self::enqueue_style( $handle, $entry, $manifest['version'] );
		}

		return true;
	}

	/**
	 * Enqueue an entry's script.
	 *
	 * @since 3.17.0
	 *
	 * @param string $handle   Script handle.
	 * @param string $entry    Build-relative entry without extension.
	 * @param array  $manifest Manifest with `dependencies` and `version`.
	 * @param array  $args     Arguments as passed to `enqueue()`, defaults filled in.
	 */
	private static function enqueue_script( $handle, $entry, $manifest, $args ) {

		wp_enqueue_script(
			$handle,
			SC_PLUGIN_ASSETS_URL . 'jsx/build/' . $entry . '.js',
			array_merge( $manifest['dependencies'], $args['deps'] ),
			$manifest['version'],
			$args['in_footer']
		);
	}

	/**
	 * Enqueue an entry's stylesheet.
	 *
	 * @since 3.17.0
	 *
	 * @param string $handle  Style handle.
	 * @param string $entry   Build-relative entry without extension.
	 * @param string $version Manifest version.
	 */
	private static function enqueue_style( $handle, $entry, $version ) {

		wp_enqueue_style(
			$handle,
			SC_PLUGIN_ASSETS_URL . 'jsx/build/' . $entry . '.css',
			[],
			$version
		);
	}

	/**
	 * Read an entry's `.asset.php` manifest.
	 *
	 * The manifest comes from the JSX build and is absent on a source-only
	 * checkout, or truncated mid-build, until assets are built.
	 *
	 * @since 3.17.0
	 *
	 * @param string $entry Build-relative entry without extension.
	 *
	 * @return array|null Manifest with `dependencies` and `version`, or null when unusable.
	 */
	private static function get_manifest( $entry ) {

		$path = SC_PLUGIN_DIR . 'assets/jsx/build/' . $entry . '.asset.php';

		if ( ! file_exists( $path ) ) {
			return null;
		}

		$manifest = include $path;

		if ( ! is_array( $manifest ) || ! isset( $manifest['dependencies'], $manifest['version'] ) ) {
			return null;
		}

		return $manifest;
	}
}
