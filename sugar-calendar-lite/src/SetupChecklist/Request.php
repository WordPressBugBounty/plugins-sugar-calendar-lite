<?php

namespace Sugar_Calendar\SetupChecklist;

use WP_Error;

/**
 * Read and validate checklist POST fields after Ajax::handle() authorizes the request.
 *
 * @since 3.15.0
 */
class Request {

	/**
	 * Reject POST fields outside the closed task and item contracts.
	 *
	 * @since 3.15.0
	 *
	 * @param string $task Validated task name.
	 *
	 * @return true|WP_Error
	 */
	public function validate_post_fields( $task ) {

		$action = $this->post_key( 'action' );

		if ( is_wp_error( $action ) || $action !== 'sugar_calendar_setup_checklist' ) {
			return new WP_Error( 'checklist_invalid_action', __( 'The checklist request contains invalid data.', 'sugar-calendar-lite' ) );
		}

		$allowed_by_task = [
			'item_action'      => [ 'item' ],
			'pages'            => [ 'page', 'search' ],
			'refresh'          => [],
			'dismiss'          => [],
			'archive_observed' => [],
			'growth_tool'      => [ 'key' ],
		];
		$allowed         = array_merge( [ 'action', 'task', 'nonce' ], $allowed_by_task[ $task ] );

		if ( $task === 'item_action' ) {
			$item_id = $this->post_key( 'item' );

			if ( ! is_wp_error( $item_id ) ) {
				$allowed = array_merge( $allowed, Config::get_item_input( $item_id ) );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Ajax::handle() verifies the endpoint nonce before this request-shape check.
		$unexpected = array_diff( array_keys( $_POST ), $allowed );

		return empty( $unexpected )
			? true
			: new WP_Error( 'checklist_unexpected_field', __( 'The checklist request contains unexpected data.', 'sugar-calendar-lite' ) );
	}

	/**
	 * Read a scalar POST string without warning-producing coercion.
	 *
	 * @since 3.15.0
	 *
	 * @param string $name Field name.
	 *
	 * @return string|WP_Error
	 */
	public function post_string( $name ) {

		if ( ! isset( $_POST[ $name ] ) || ! is_string( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Ajax::handle() verifies the endpoint nonce before dispatch.
			return new WP_Error( 'checklist_invalid_' . $name, __( 'The checklist request contains invalid data.', 'sugar-calendar-lite' ) );
		}

		return sanitize_text_field( wp_unslash( $_POST[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Ajax::handle() verifies the endpoint nonce before dispatch.
	}

	/**
	 * Read a sanitized key from POST.
	 *
	 * @since 3.15.0
	 *
	 * @param string $name Field name.
	 *
	 * @return string|WP_Error
	 */
	public function post_key( $name ) {

		$value = $this->post_string( $name );

		if ( is_wp_error( $value ) ) {
			return $value;
		}

		$key = sanitize_key( $value );

		return $key !== '' && $key === $value
			? $key
			: new WP_Error( 'checklist_invalid_' . $name, __( 'The checklist request contains invalid data.', 'sugar-calendar-lite' ) );
	}

	/**
	 * Read a strictly positive integer from POST.
	 *
	 * @since 3.15.0
	 *
	 * @param string $name Field name.
	 *
	 * @return int|WP_Error
	 */
	public function post_positive_int( $name ) {

		$value = $this->post_string( $name );

		if ( is_wp_error( $value ) || ! preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			return new WP_Error( 'checklist_invalid_' . $name, __( 'The checklist request contains invalid data.', 'sugar-calendar-lite' ) );
		}

		$integer = (int) $value;

		return (string) $integer === $value
			? $integer
			: new WP_Error( 'checklist_invalid_' . $name, __( 'The checklist request contains invalid data.', 'sugar-calendar-lite' ) );
	}

	/**
	 * Read the validated page-search parameters.
	 *
	 * @since 3.15.0
	 *
	 * @return array|WP_Error
	 */
	public function page_search() {

		$search = isset( $_POST['search'] ) ? $this->post_string( 'search' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Ajax::handle() verifies the endpoint nonce before dispatch.

		if ( is_wp_error( $search ) ) {
			return $search;
		}

		if ( $this->string_length( $search ) > 100 ) {
			return new WP_Error( 'checklist_page_search_too_long', __( 'Page search is limited to 100 characters.', 'sugar-calendar-lite' ) );
		}

		$page = $this->post_positive_int( 'page' );

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		return [
			'search' => $search,
			'page'   => $page,
		];
	}

	/**
	 * Count characters without requiring a multibyte extension.
	 *
	 * @since 3.15.0
	 *
	 * @param string $value Text value.
	 *
	 * @return int
	 */
	private function string_length( $value ) {

		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
