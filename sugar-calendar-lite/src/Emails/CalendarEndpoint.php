<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Ics\EventData;
use Sugar_Calendar\Ics\Renderer as IcsRenderer;
use Throwable;
use WP_Post;

/**
 * Serve single-event ICS downloads without rewrite rules.
 *
 * @since 3.14.0
 */
final class CalendarEndpoint {

	/**
	 * Register hooks.
	 *
	 * @since 3.14.0
	 */
	public function hooks(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound -- Exact public contract requires void.

		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_action( 'template_redirect', [ $this, 'template_redirect' ], 20 );

		if ( ! sugar_calendar()->is_pro() ) {
			add_filter( 'sugar_calendar_emails_calendar_url', [ $this, 'email_url' ], 20, 2 );
		}
	}

	/**
	 * Register the ICS query variable.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $query_vars Public query variables, as left by earlier callbacks.
	 *
	 * @return array
	 */
	public function query_vars( $query_vars ): array {

		// A typed parameter would turn another plugin's bad return into our fatal.
		$query_vars = is_array( $query_vars ) ? $query_vars : [];

		$query_vars[] = 'ics';

		return $query_vars;
	}

	/**
	 * Supply the Lite single-event calendar URL.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $url     URL supplied by an earlier callback.
	 * @param array $context Email context.
	 *
	 * @return string
	 */
	public function email_url( $url, array $context ): string {

		if ( ! is_string( $url ) ) {
			return '';
		}

		if ( $url !== '' ) {
			return $url;
		}

		if ( ! empty( $context['event']['occurrence_id'] ) ) {
			return '';
		}

		return self::url( $context );
	}

	/**
	 * Build a single-event calendar URL.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context Email context.
	 *
	 * @return string
	 */
	public static function url( array $context ): string {

		$object_id = isset( $context['event']['object_id'] )
			? (int) $context['event']['object_id']
			: 0;
		$permalink = $object_id > 0 ? get_post_permalink( $object_id ) : '';

		if ( ! is_string( $permalink ) || $permalink === '' ) {
			return '';
		}

		return add_query_arg(
			[
				'ics'      => '1',
				'download' => '1',
			],
			$permalink
		);
	}

	/**
	 * Build the current single-event ICS response.
	 *
	 * @since 3.14.0
	 *
	 * @return array|null
	 */
	public static function response(): ?array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Exact public contract and boundary validation are intentional.

		$post_type = sugar_calendar_get_event_post_type_id();

		if ( get_query_var( 'ics', false ) === false || ! is_singular( $post_type ) ) {
			return null;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$event = sugar_calendar_get_event_by_object(
			(int) $post->ID,
			'post',
			[ 'object_subtype' => $post_type ]
		);

		if (
			! is_object( $event )
			|| ! isset( $event->id, $event->object_id, $event->object_subtype )
			|| (int) $event->id <= 0
			|| (int) $event->object_id !== (int) $post->ID
			|| $event->object_subtype !== $post_type
			|| ! EventData::is_public( $event )
		) {
			return null;
		}

		$had_post      = array_key_exists( 'post', $GLOBALS );
		$previous_post = $GLOBALS['post'] ?? null;

		try {
			// Post-aware content restrictions must evaluate the event being exported.
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Scoped to this export and restored in finally.
			$calendar        = EventData::calendar( $event, true );

			if ( ! isset( $calendar['events'][0] ) || ! is_array( $calendar['events'][0] ) ) {
				return null;
			}

			$contents = ( new IcsRenderer( $calendar ) )->render( false );
		} catch ( Throwable $error ) {
			// A public GET must never 500; fall through to the normal event page.
			error_log( '[SC ICS] single-event render failed: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return null;
		} finally {
			if ( $had_post ) {
				$GLOBALS['post'] = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the exact original context.
			} else {
				unset( $GLOBALS['post'] );
			}
		}

		$download = ! empty( $_GET['download'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only response mode.

		return [
			'contents'     => $contents,
			'filename'     => EventData::filename( $calendar['events'][0] ),
			'content_type' => $download ? 'text/calendar; charset=UTF-8' : 'text/plain; charset=UTF-8',
			'disposition'  => $download ? 'attachment' : 'inline',
		];
	}

	/**
	 * Output the current single-event ICS response.
	 *
	 * @since 3.14.0
	 */
	public function template_redirect(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound -- Exact public contract requires void.

		$response = self::response();

		if ( $response === null ) {
			return;
		}

		$filename = sanitize_file_name( $response['filename'] );

		nocache_headers();
		header( 'Content-Type: ' . $response['content_type'] );
		header( 'Content-Disposition: ' . $response['disposition'] . '; filename="' . $filename . '"' );

		echo $response['contents']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already serialized ICS.
		exit;
	}
}
