<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Ics\Renderer as IcsRenderer;
use Throwable;

/**
 * Manage request-scoped email calendar attachments.
 *
 * @since 3.14.0
 */
final class CalendarAttachmentPool {

	/**
	 * Cached attachment paths and failures.
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private static $artifacts = [];

	/**
	 * Exact generated paths owned by the pool.
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private static $paths = [];

	/**
	 * Exact generated directories owned by the pool.
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private static $directories = [];

	/**
	 * Whether shutdown cleanup has been registered.
	 *
	 * @since 3.14.0
	 *
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/**
	 * Acquire a generated calendar attachment.
	 *
	 * @since 3.14.0
	 *
	 * @param array $action Validated calendar action.
	 *
	 * @return string Attachment path, or an empty string when disabled or generation fails.
	 */
	public static function acquire( array $action ): string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.CyclomaticComplexity.TooHigh -- Flat boundary validation keeps malformed actions side-effect free.

		if (
			! isset( $action['cache_key'] )
			|| ! is_string( $action['cache_key'] )
			|| $action['cache_key'] === ''
			|| ! isset( $action['filename'] )
			|| ! is_string( $action['filename'] )
			|| $action['filename'] === ''
			|| $action['filename'] !== basename( $action['filename'] )
			|| substr( $action['filename'], -4 ) !== '.ics'
			|| ! isset( $action['identity'] )
			|| ! is_array( $action['identity'] )
			|| ! isset( $action['calendar'] )
			|| ! is_array( $action['calendar'] )
			|| ! isset( $action['calendar']['events'] )
			|| ! is_array( $action['calendar']['events'] )
			|| count( $action['calendar']['events'] ) !== 1
			|| ! is_array( array_values( $action['calendar']['events'] )[0] )
		) {
			return '';
		}

		/**
		 * Filters whether a generated ICS file is attached to a Modern attendee email.
		 *
		 * @since 3.14.0
		 *
		 * @param bool  $enabled  Whether to attach the generated ICS file. Defaults to true.
		 * @param array $identity Site and event identity: site_id, event_id, and occurrence_id.
		 *                        Applied for each valid acquisition, including cached artifacts.
		 */
		$enabled = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_email_ics_attachment_enabled',
			true,
			$action['identity']
		);

		if ( ! $enabled ) {
			return '';
		}

		$key = (string) $action['cache_key'];

		if ( array_key_exists( $key, self::$artifacts ) ) {
			return self::$artifacts[ $key ];
		}

		self::$artifacts[ $key ] = '';

		self::register_shutdown();

		try {
			$contents = ( new IcsRenderer( $action['calendar'] ) )->render( false );
		} catch ( Throwable $error ) {
			self::failure( 'render_failed', $action, $error );

			return '';
		}

		if ( ! is_string( $contents ) || strpos( $contents, 'BEGIN:VCALENDAR' ) === false ) {
			self::failure( 'invalid_document', $action );

			return '';
		}

		/**
		 * Filters the temporary root directory for email ICS attachments.
		 *
		 * @since 3.14.0
		 *
		 * @param string $root     Temporary root path. A non-empty directory path is accepted.
		 * @param array  $identity Site and event identity.
		 */
		$root = apply_filters( 'sugar_calendar_email_ics_temp_directory', get_temp_dir(), $action['identity'] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if (
			! is_string( $root )
			|| $root === ''
			|| strpos( $root, "\0" ) !== false
			|| ( @file_exists( $root ) && ! @is_dir( $root ) ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A filtered root blocked by filesystem restrictions must follow the stable failure path.
		) {
			self::failure( 'invalid_temp_directory', $action );

			return '';
		}

		$directory = trailingslashit( $root ) . 'sugar-calendar-email-' . wp_generate_password( 20, false, false );

		if ( @file_exists( $directory ) || ! wp_mkdir_p( $directory ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A generated path blocked by filesystem restrictions must follow the stable failure path.
			self::failure( 'temp_directory_create_failed', $action );

			return '';
		}

		self::$directories[ $directory ] = true;

		@chmod( $directory, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.PHP.NoSilencedErrors.Discouraged -- Permission hardening is best effort for this exact owned directory.

		$path                 = trailingslashit( $directory ) . sanitize_file_name( $action['filename'] );
		self::$paths[ $path ] = true;

		$bytes = @file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- The pool writes one exact owned temporary file and handles failure.

		if ( $bytes !== strlen( $contents ) ) {
			if ( file_exists( $path ) ) {
				@unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- The pool owns this exact generated path and cleanup is best effort.
			}

			@rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- The pool owns this exact non-recursive directory and cleanup is best effort.
			self::failure( 'file_write_failed', $action );

			return '';
		}

		@chmod( $path, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.PHP.NoSilencedErrors.Discouraged -- Permission hardening is best effort for this exact owned file.

		self::$artifacts[ $key ] = $path;

		return $path;
	}

	/**
	 * Remove generated attachment artifacts owned by the pool.
	 *
	 * @since 3.14.0
	 */
	public static function cleanup(): void { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound -- Exact ownership retry semantics and public contract are intentional.

		foreach ( array_keys( self::$paths ) as $path ) {
			if ( ! file_exists( $path ) ) {
				unset( self::$paths[ $path ] );
				continue;
			}

			$deleted = @unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- The pool deletes only an exact owned path and retries failures.

			if ( $deleted || ! file_exists( $path ) ) {
				unset( self::$paths[ $path ] );
			}
		}

		foreach ( array_keys( self::$directories ) as $directory ) {
			if ( ! file_exists( $directory ) ) {
				unset( self::$directories[ $directory ] );
				continue;
			}

			$removed = @rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- The pool removes only an exact owned directory and retries failures.

			if ( $removed || ! file_exists( $directory ) ) {
				unset( self::$directories[ $directory ] );
			}
		}

		self::$artifacts = [];
	}

	/**
	 * Clean the pool without unregistering shutdown cleanup.
	 *
	 * @since 3.14.0
	 */
	public static function reset(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound -- Exact public contract requires void.

		self::cleanup();
	}

	/**
	 * Report an attachment generation failure.
	 *
	 * @since 3.14.0
	 *
	 * @param string         $code   Stable failure code.
	 * @param array          $action Validated calendar action.
	 * @param Throwable|null $error  Rendering error, when available.
	 */
	private static function failure( string $code, array $action, ?Throwable $error = null ): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.typeDeclarationFound, PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound -- Sugar Calendar requires PHP 7.4+ and this is the exact typed contract.

		/**
		 * Fires when an email ICS attachment cannot be generated.
		 *
		 * @since 3.14.0
		 *
		 * @param string         $code     Stable failure code.
		 * @param array          $identity Site and event identity.
		 * @param Throwable|null $error    Rendering error, when available.
		 */
		do_action( 'sugar_calendar_email_ics_attachment_failed', $code, $action['identity'], $error ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Register request shutdown cleanup once.
	 *
	 * @since 3.14.0
	 */
	private static function register_shutdown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound -- Exact private contract requires void.

		if ( self::$shutdown_registered ) {
			return;
		}

		register_shutdown_function( [ self::class, 'cleanup' ] );
		self::$shutdown_registered = true;
	}
}
