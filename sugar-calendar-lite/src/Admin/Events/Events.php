<?php

namespace Sugar_Calendar\Admin\Events;

use Sugar_Calendar\Helpers\WP;

/**
 * Events class.
 *
 * Handles anything event-related in the admin-side.
 *
 * @since 3.2.0
 */
class Events {

	/**
	 * Hooks.
	 *
	 * @since 3.2.0
	 */
	public function hooks() {

		add_action( 'wp_insert_post_empty_content', [ $this, 'validate_event_creation' ], 10, 2 );
		add_action( 'save_post_sc_event', [ $this, 'save' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'admin_notices_display_error' ] );
	}

	/**
	 * Fires once an event has been saved/updated.
	 *
	 * @since 3.2.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save( $post_id, $post ) {

		if ( $post->post_type !== 'sc_event' || $post->post_status !== 'publish' ) {
			return;
		}

		$calendars = wp_get_post_terms(
			$post_id,
			'sc_event_category',
			[
				'number' => 1,
				'fields' => 'ids',
			]
		);

		if ( ! empty( $calendars ) ) {
			return;
		}

		// Get the default calendar.
		$default_calendar = absint( sugar_calendar_get_default_calendar() );

		if ( empty( $default_calendar ) ) {
			return;
		}

		wp_set_post_terms(
			$post_id,
			[ $default_calendar ],
			'sc_event_category'
		);
	}

	/**
	 * Validate event creation. Redirect if event title is empty.
	 *
	 * @since 3.3.0
	 *
	 * @param bool  $maybe_empty Whether the post should be considered "empty".
	 * @param array $postarr     Array of post data.
	 *
	 * @return void
	 */
	public function validate_event_creation( $maybe_empty, $postarr ) {

		// Run only on post type sc_event and bail on autosave, delete, or auto-draft status.
		if (
			$postarr['post_type'] !== sugar_calendar_get_event_post_type_id()
			||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			||
			in_array( $postarr['post_status'], [ 'trash', 'auto-draft' ], true )
			||
			! empty( $postarr['post_title'] )
		) {
			return;
		}

		// Determine the redirect URL based on the screen context.
		if ( $this->is_from_create_event_screen() ) {
			$redirect_url = admin_url( sprintf( 'post-new.php?post_type=%s', sugar_calendar_get_event_post_type_id() ) );
		} elseif ( $this->is_from_edit_event_screen() ) {
			$redirect_url = get_edit_post_link( $postarr['ID'], null );
		} else {
			return; // No valid context, do not redirect.
		}

		// Append the error message and redirect.
		$redirect_url = add_query_arg(
			[
				'sc_error' => 'empty_title',
				'_wpnonce' => wp_create_nonce( 'sc_empty_title_nonce' ),
			],
			$redirect_url
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Output error notices.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	public function admin_notices_display_error() {

		// Check if there is an error and Validate nonce.
		if (
			! isset( $_GET['sc_error'] )
			||
			! isset( $_GET['_wpnonce'] )
			||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sc_empty_title_nonce' )
		) {
			return;
		}

		// Check if the error is empty_title.
		if ( $_GET['sc_error'] === 'empty_title' ) {

			// Add an error notice.
			WP::add_admin_notice(
				__( 'Event name is required.', 'sugar-calendar-lite' ),
				WP::ADMIN_NOTICE_ERROR
			);
		}

		WP::display_admin_notices();
	}

	/**
	 * Check referer and return true if coming from edit event screen.
	 *
	 * @since 3.3.0
	 *
	 * @return bool
	 */
	public function is_from_edit_event_screen() {

		// Get params from referer.
		$referer        = site_url( wp_get_referer() );
		$referer_params = wp_parse_args( wp_parse_url( $referer, PHP_URL_QUERY ) );

		// Check if the referer is from edit event screen.
		if (
			isset( $referer_params['post'] )
			&&
			get_post_type( $referer_params['post'] ) === sugar_calendar_get_event_post_type_id()
			&&
			isset( $referer_params['action'] )
			&&
			$referer_params['action'] === 'edit'
		) {
			return true;
		}

		return false;
	}

	/**
	 * Check referer and return true if coming from create event screen.
	 *
	 * @since 3.3.0
	 *
	 * @return bool
	 */
	public function is_from_create_event_screen() {

		// Get params from referer.
		$referer        = site_url( wp_get_referer() );
		$referer_params = wp_parse_args( wp_parse_url( $referer, PHP_URL_QUERY ) );

		// Check if the referer is from create event screen.
		if (
			isset( $referer_params['post_type'] )
			&&
			$referer_params['post_type'] === sugar_calendar_get_event_post_type_id()
			&&
			strpos( $referer, 'post-new.php' ) !== false
		) {
			return true;
		}

		return false;
	}
}
