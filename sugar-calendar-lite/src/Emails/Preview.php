<?php

namespace Sugar_Calendar\Emails;

use WP_Error;

/**
 * Secure one-time email preview service.
 *
 * @since 3.14.0
 */
class Preview {

	const ACTION = 'sc_email_preview';

	const CREATE_ACTION = 'sugar_calendar_email_preview_create';

	const TOKEN_TTL = 300;

	const LOCK_TTL = 30;

	const CACHE_GROUP = 'sugar_calendar_email_preview';

	/**
	 * Register globally reachable service routes.
	 *
	 * @since 3.14.0
	 *
	 * @return void
	 */
	public static function hooks() {

		add_action( 'wp_ajax_' . self::CREATE_ACTION, [ self::class, 'create' ] );
		add_action( 'admin_init', [ self::class, 'maybe_render' ], 5 );
	}

	/**
	 * Create a sanitized, current-user-scoped one-time preview.
	 *
	 * @since 3.14.0
	 *
	 * @return void
	 */
	public static function create() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- Request validation and the two intentionally distinct field-ownership routes share one endpoint.

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You are not allowed to preview this email.', 'sugar-calendar-lite' ) ], 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::CREATE_ACTION ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'The preview request is invalid.', 'sugar-calendar-lite' ) ], 403 );
		}

		$route    = isset( $_POST['route'] ) ? sanitize_key( wp_unslash( $_POST['route'] ) ) : '';
		$email_id = isset( $_POST['email_id'] ) ? sanitize_key( wp_unslash( $_POST['email_id'] ) ) : '';

		if ( ! in_array( $route, [ 'main', 'editor' ], true ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'The preview request is invalid.', 'sugar-calendar-lite' ) ], 400 );
		}

		if ( $route === 'main' ) {
			$email_id = Renderer::TICKET_ATTENDEE;
		} elseif ( ! PreviewDocument::supports( $email_id ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'The preview request is invalid.', 'sugar-calendar-lite' ) ], 400 );
		}

		$state = self::sanitize_state( $route, $email_id, self::posted_state() );
		$token = bin2hex( random_bytes( 32 ) );
		$user  = get_current_user_id();

		set_transient(
			self::transient_key( $user, $token ),
			[
				'user_id'  => $user,
				'route'    => $route,
				'email_id' => $email_id,
				'state'    => $state,
			],
			self::TOKEN_TTL
		);

		wp_send_json_success( [ 'url' => self::url( $token ) ] );
	}

	/**
	 * Build a read URL containing a session-bound token nonce.
	 *
	 * @since 3.14.0
	 *
	 * @param string $token One-time token.
	 *
	 * @return string
	 */
	public static function url( $token = '' ) {

		return add_query_arg(
			[
				'sc_action' => self::ACTION,
				'token'     => $token,
				'_wpnonce'  => wp_create_nonce( self::read_nonce_action( $token ) ),
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Resolve the current read request without emitting output.
	 *
	 * @since 3.14.0
	 *
	 * @return array { status, content_type, body }.
	 */
	public static function response() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- Security, one-time consumption, and MIME normalization form one response boundary.

		// This gate deliberately precedes token sanitization, hashing, and every
		// transient operation. Unauthorized requests cannot probe or consume state.
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::error_response( 403 );
		}

		$result = self::consume_authorized();

		if ( is_wp_error( $result ) ) {
			$error_code = $result->get_error_code();

			if ( $error_code === 'sugar_calendar_email_preview_forbidden' ) {
				return self::error_response( 403 );
			}

			if ( $error_code === PreviewDocument::ERROR_INVALID_SOURCE ) {
				$error_data = $result->get_error_data();
				$source_type = is_array( $error_data ) ? ( $error_data['source_type'] ?? '' ) : '';

				if ( $source_type === 'ticket' ) {
					$message = __( 'The selected ticket could not be used for this email preview.', 'sugar-calendar-lite' );
				} elseif ( $source_type === 'rsvp' ) {
					$message = __( 'The selected RSVP could not be used for this email preview.', 'sugar-calendar-lite' );
				} else {
					$message = __( 'The selected order could not be used for this email preview.', 'sugar-calendar-lite' );
				}

				return self::unprocessable_response( $message );
			}

			if ( $error_code !== PreviewDocument::ERROR_MISSING_RENDERER ) {
				return self::error_response( 500 );
			}

			return self::unprocessable_response(
				__( 'A preview renderer is not available for this email.', 'sugar-calendar-lite' )
			);
		}

		if ( $result['content_type'] === 'text/plain' ) {
			$body = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'
				. esc_html( $result['subject'] )
				. '</title></head><body><pre>'
				. esc_html( $result['document'] )
				. '</pre></body></html>';
		} else {
			$body = $result['document'];
		}

		return [
			'status'       => 200,
			'content_type' => 'text/html',
			'body'         => $body,
		];
	}

	/**
	 * Serve a preview read request.
	 *
	 * @since 3.14.0
	 *
	 * @return void
	 */
	public static function maybe_render() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only; response() performs the token-specific verification.
		$action = isset( $_GET['sc_action'] ) ? sanitize_key( wp_unslash( $_GET['sc_action'] ) ) : '';

		if ( $action !== self::ACTION ) {
			return;
		}

		$response = self::response();

		status_header( $response['status'] );
		nocache_headers();

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=UTF-8' );
		}

		// PreviewDocument and the escaped plain/error wrappers own all escaping.
		echo $response['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}

	/**
	 * Compatibility helper for callers that need the representative document.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public static function render() {

		$result = PreviewDocument::render(
			[
				'route'    => 'main',
				'email_id' => Renderer::TICKET_ATTENDEE,
				'state'    => [],
			]
		);

		return is_array( $result ) ? $result['document'] : '';
	}

	/**
	 * Read and consume state after the capability check.
	 *
	 * @since 3.14.0
	 *
	 * @return array|WP_Error
	 */
	private static function consume_authorized() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- Validation, atomic locking, one-time deletion, and finally cleanup form one consume boundary.

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
			return self::forbidden();
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::read_nonce_action( $token ) ) ) {
			return self::forbidden();
		}

		$user_id = get_current_user_id();
		$lock    = self::acquire_lock( $user_id, $token );

		if ( $lock === false ) {
			return self::forbidden();
		}

		try {
			$key   = self::transient_key( $user_id, $token );
			$state = get_transient( $key );

			if ( ! is_array( $state ) ) {
				return self::forbidden();
			}

			delete_transient( $key );

			return PreviewDocument::render( $state );
		} finally {
			self::release_lock( $lock );
		}
	}

	/**
	 * Get the posted form state in either service or serialized-form shape.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	private static function posted_state() {

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The private caller verifies the create nonce first; each owned field is sanitized by sanitize_state().
		if ( isset( $_POST['state'] ) && is_array( $_POST['state'] ) ) {
			return wp_unslash( $_POST['state'] );
		}

		if ( isset( $_POST['sugar-calendar'] ) && is_array( $_POST['sugar-calendar'] ) ) {
			return wp_unslash( $_POST['sugar-calendar'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return [];
	}

	/**
	 * Sanitize only fields owned by the selected preview surface.
	 *
	 * @since 3.14.0
	 *
	 * @param string $route    Preview route.
	 * @param string $email_id Notification id.
	 * @param array  $state    Raw form state.
	 *
	 * @return array
	 */
	private static function sanitize_state( $route, $email_id, array $state ) {

		$sanitized = [];

		if ( $route === 'main' ) {
			foreach ( Styling::setting_names() as $key ) {
				if ( array_key_exists( $key, $state ) ) {
					$sanitized[ $key ] = Styling::sanitize_setting( $key, $state[ $key ] );
				}
			}

			return $sanitized;
		}

		$fields = PreviewDocument::field_map()[ $email_id ];

		if ( array_key_exists( $fields['subject'], $state ) ) {
			$sanitized[ $fields['subject'] ] = sanitize_text_field( $state[ $fields['subject'] ] );
		}

		if ( array_key_exists( $fields['message'], $state ) ) {
			$sanitized[ $fields['message'] ] = wp_kses_post( $state[ $fields['message'] ] );
		}

		return $sanitized;
	}

	/**
	 * Build a current-user-scoped transient key without storing the raw token.
	 *
	 * @since 3.14.0
	 *
	 * @param int    $user_id Current user id.
	 * @param string $token   One-time token.
	 *
	 * @return string
	 */
	private static function transient_key( $user_id, $token ) {

		return 'sugar_calendar_email_preview_' . (int) $user_id . '_' . hash( 'sha256', $token );
	}

	/**
	 * Acquire an atomic, short-lived consume mutex.
	 *
	 * @since 3.14.0
	 *
	 * @param int    $user_id Current user id.
	 * @param string $token   One-time token.
	 *
	 * @return array|false Lock handle, or false when another consume owns it.
	 */
	private static function acquire_lock( $user_id, $token ) {

		$key = self::lock_key( $user_id, $token );

		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $key, 1, self::CACHE_GROUP, self::LOCK_TTL )
				? [
					'backend' => 'cache',
					'key'     => $key,
				]
				: false;
		}

		$expires = time() + self::LOCK_TTL;

		if ( add_option( $key, $expires, '', false ) ) {
			return [
				'backend' => 'option',
				'key'     => $key,
			];
		}

		$current = get_option( $key, false );

		if ( $current === false || (int) $current > time() || ! self::delete_expired_lock( $key, $current ) ) {
			return false;
		}

		return add_option( $key, $expires, '', false )
			? [
				'backend' => 'option',
				'key'     => $key,
			]
			: false;
	}

	/**
	 * Delete only the exact expired option value observed by this request.
	 *
	 * @since 3.14.0
	 *
	 * @param string $key     Lock option name.
	 * @param mixed  $current Observed expiry value.
	 *
	 * @return bool
	 */
	private static function delete_expired_lock( $key, $current ) {

		global $wpdb;

		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Compare-and-delete is required so a stale-lock contender cannot delete a newly acquired mutex.
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$key,
				maybe_serialize( $current )
			)
		);

		if ( $deleted ) {
			wp_cache_delete( $key, 'options' );
		}

		return $deleted === 1;
	}

	/**
	 * Release a consume mutex on every render/error/exception path.
	 *
	 * @since 3.14.0
	 *
	 * @param array $lock Lock handle.
	 *
	 * @return void
	 */
	private static function release_lock( array $lock ) {

		if ( $lock['backend'] === 'cache' ) {
			wp_cache_delete( $lock['key'], self::CACHE_GROUP );

			return;
		}

		delete_option( $lock['key'] );
	}

	/**
	 * Build the current-user/token consume mutex key.
	 *
	 * @since 3.14.0
	 *
	 * @param int    $user_id Current user id.
	 * @param string $token   One-time token.
	 *
	 * @return string
	 */
	private static function lock_key( $user_id, $token ) {

		return 'sugar_calendar_email_preview_lock_' . (int) $user_id . '_' . hash( 'sha256', $token );
	}

	/**
	 * Build the nonce action bound to one preview token.
	 *
	 * @since 3.14.0
	 *
	 * @param string $token One-time token.
	 *
	 * @return string
	 */
	private static function read_nonce_action( $token ) {

		return self::ACTION . '_' . $token;
	}

	/**
	 * Build the private forbidden result.
	 *
	 * @since 3.14.0
	 *
	 * @return WP_Error
	 */
	private static function forbidden() {

		return new WP_Error( 'sugar_calendar_email_preview_forbidden', '', [ 'status' => 403 ] );
	}

	/**
	 * Build an intentionally empty public error response.
	 *
	 * @since 3.14.0
	 *
	 * @param int $status HTTP status.
	 *
	 * @return array
	 */
	private static function error_response( $status ) {

		return [
			'status'       => $status,
			'content_type' => 'text/html',
			'body'         => '',
		];
	}

	/**
	 * Build a safe public response for an unprocessable preview.
	 *
	 * @since 3.14.0
	 *
	 * @param string $message Fixed localized public message.
	 *
	 * @return array
	 */
	private static function unprocessable_response( $message ) {

		return [
			'status'       => 422,
			'content_type' => 'text/html',
			'body'         => '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . esc_html( $message ) . '</body></html>',
		];
	}
}
