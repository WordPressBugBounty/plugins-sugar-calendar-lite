<?php

namespace Sugar_Calendar\Admin\Tools\Export;

use Sugar_Calendar\Helpers;
use ZipArchive;
use PclZip;

/**
 * Turns a built export payload into the HTTP response.
 *
 * Everything below this class is transport: headers, buffering, ZIP packaging and
 * the temp files that packaging needs. The exporters decide *what* to ship and
 * hand it here; nothing in this class knows about events, orders or formats.
 *
 * Both entry points end in exit().
 *
 * @since 3.13.0
 */
class ExportDownloader {

	/**
	 * Download filename without its extension.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	private $base_filename;

	/**
	 * Constructor.
	 *
	 * @since 3.13.0
	 *
	 * @param string $base_filename Download filename, no extension.
	 */
	public function __construct( string $base_filename ) {

		$this->base_filename = $base_filename;
	}

	/**
	 * Stream a single-file download and exit.
	 *
	 * @since 3.13.0
	 *
	 * @param string $body File body.
	 * @param string $mime MIME type.
	 * @param string $ext  File extension (no dot).
	 */
	public function send( string $body, string $mime, string $ext ) {

		$this->prepare();

		header( 'Content-Type: ' . $mime . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $this->base_filename . '.' . $ext );
		header( 'Expires: 0' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}

	/**
	 * Stream a ZIP of the given parts and exit.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $parts Map of slug => body.
	 * @param string $ext   File extension for each part (no dot).
	 */
	public function send_archive( array $parts, string $ext ) {

		// Raise the time limit before building, but hold the response headers back
		// until the archive exists: prepare() sends them, and wp_die() cannot set a
		// status code once headers are already out.
		Helpers::set_time_limit();

		$zip_path = $this->build_archive( $parts, $ext );

		// Fail loud rather than hand back a 0-byte archive: wp_tempnam() has already
		// created the file, so an unwritable temp dir or a full disk would otherwise
		// stream an empty "successful" download with no signal that it went wrong.
		if ( $zip_path === '' || ! filesize( $zip_path ) ) {
			wp_die(
				esc_html__( 'The export archive could not be created. Please try again.', 'sugar-calendar-lite' ),
				esc_html__( 'Export failed', 'sugar-calendar-lite' ),
				[
					'response'  => 500,
					'back_link' => true,
				]
			);
		}

		$this->prepare();

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename=' . $this->base_filename . '.zip' );
		header( 'Content-Length: ' . filesize( $zip_path ) );
		header( 'Expires: 0' );

		readfile( $zip_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile

		wp_delete_file( $zip_path );

		exit;
	}

	/**
	 * Common pre-stream setup: time limit, no-cache headers, clean buffers.
	 *
	 * @since 3.13.0
	 */
	private function prepare() {

		Helpers::set_time_limit();
		nocache_headers();

		if ( ob_get_level() ) {
			ob_end_clean();
		}
	}

	/**
	 * Build a ZIP file on disk and return its path.
	 *
	 * Uses ext-zip when available, else WordPress's bundled PclZip.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $parts Map of slug => body.
	 * @param string $ext   File extension for each part (no dot).
	 *
	 * @return string Absolute path to the ZIP, or '' when it could not be written.
	 */
	private function build_archive( array $parts, string $ext ): string {

		$tmp = wp_tempnam( 'sc-export' );

		// Prefer ext-zip, but only if it actually opens AND closes the archive —
		// otherwise fall through to PclZip rather than return an empty (0-byte) ZIP.
		if ( $this->write_zip_archive( $tmp, $parts, $ext ) ) {
			return $tmp;
		}

		return $this->write_pclzip_archive( $tmp, $parts, $ext );
	}

	/**
	 * Write the parts into $path with ext-zip.
	 *
	 * @since 3.13.0
	 *
	 * @param string $path  Target archive path.
	 * @param array  $parts Map of slug => body.
	 * @param string $ext   File extension for each part (no dot).
	 *
	 * @return bool Whether the archive was written.
	 */
	private function write_zip_archive( string $path, array $parts, string $ext ): bool {

		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$zip = new ZipArchive();

		if ( $zip->open( $path, ZipArchive::OVERWRITE ) !== true ) {
			return false;
		}

		foreach ( $parts as $slug => $body ) {
			$zip->addFromString( $this->part_filename( $slug, $ext ), (string) $body );
		}

		return (bool) $zip->close();
	}

	/**
	 * Write the parts into $path with WordPress's bundled PclZip.
	 *
	 * Each part is written to a temp file first, so this is the branch where an
	 * unsanitized part slug would become a real filesystem path.
	 *
	 * @since 3.13.0
	 *
	 * @param string $path  Target archive path.
	 * @param array  $parts Map of slug => body.
	 * @param string $ext   File extension for each part (no dot).
	 *
	 * @return string Absolute path to the ZIP, or '' when it could not be written.
	 */
	private function write_pclzip_archive( string $path, array $parts, string $ext ): string {

		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$dir   = trailingslashit( get_temp_dir() ) . 'sc-export-' . wp_generate_password( 8, false );
		$files = [];

		wp_mkdir_p( $dir );

		foreach ( $parts as $slug => $body ) {
			$file = trailingslashit( $dir ) . $this->part_filename( $slug, $ext );

			file_put_contents( $file, (string) $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

			$files[] = $file;
		}

		$archive = new PclZip( $path );
		$created = $archive->create( $files, PCLZIP_OPT_REMOVE_ALL_PATH );

		array_map( 'wp_delete_file', $files );

		// Both codes must sit in ONE inline ignore: a second phpcs:ignore on the
		// same line overrides a preceding-line one, which left the rmdir sniff live
		// and failed the wp.org Plugin Check.
		@rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged

		if ( empty( $created ) ) {
			wp_delete_file( $path );

			return '';
		}

		return $path;
	}

	/**
	 * The file name to store one export part under, inside the ZIP.
	 *
	 * Slugs come from a public filter, so they are sanitized before becoming paths.
	 *
	 * @since 3.13.0
	 *
	 * @param string $slug Entity slug (the part's array key).
	 * @param string $ext  File extension (no dot).
	 *
	 * @return string
	 */
	private function part_filename( $slug, string $ext ): string {

		return sanitize_file_name( (string) $slug . '.' . $ext );
	}
}
