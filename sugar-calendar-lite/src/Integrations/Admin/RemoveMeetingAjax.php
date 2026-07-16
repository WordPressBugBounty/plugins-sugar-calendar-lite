<?php

namespace Sugar_Calendar\Integrations\Admin;

use Sugar_Calendar\Integrations\EventMeetingManager;

/**
 * Explicit "Remove" online-meeting AJAX endpoint.
 *
 * Provider-agnostic counterpart to CreateMeetingAjax. Deletes the meeting at
 * the provider, clears the meeting meta, and reverts `online_provider` to None
 * — so the editor's Online dropdown returns to None without a page reload.
 * Works identically for the Classic and Block editors (removal is decoupled
 * from the editor's own save).
 *
 * @since 3.12.0
 */
class RemoveMeetingAjax {

	/**
	 * AJAX action + nonce slug.
	 */
	const ACTION = 'sc_remove_online_meeting';

	/**
	 * @since 3.12.0
	 *
	 * @var EventMeetingManager
	 */
	private $manager;

	/**
	 * @since 3.12.0
	 *
	 * @param EventMeetingManager $manager Meeting lifecycle dispatcher.
	 */
	public function __construct( EventMeetingManager $manager ) {

		$this->manager = $manager;
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
		add_filter( 'sugar_calendar_admin_events_metaboxes_event_localize_script', [ $this, 'localize' ] );
	}

	/**
	 * Expose the action + a fresh nonce to the metabox JS.
	 *
	 * @since 3.12.0
	 *
	 * @param array $data Localized data.
	 *
	 * @return array
	 */
	public function localize( $data ) {

		$data['remove_meeting'] = [
			'action'   => self::ACTION,
			'nonce'    => wp_create_nonce( self::ACTION ),
			'icon_url' => SC_PLUGIN_ASSETS_URL . 'images/icons/exclamation-circle.svg',
		];

		return $data;
	}

	/**
	 * Handle the remove request.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function handle() {

		check_ajax_referer( self::ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_event', $post_id ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You are not allowed to do that.', 'sugar-calendar-lite' ) ] );
		}

		$event = sugar_calendar_get_event_by_object( $post_id );

		if ( empty( $event->id ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Could not load the event.', 'sugar-calendar-lite' ) ] );
		}

		// Deletes the remote meeting (orphan-tolerant), clears the meeting meta,
		// and reverts online_provider to None. Never fails the request on a
		// provider error — the event always ends in "no meeting here."
		$this->manager->detach_meeting( $event );

		wp_send_json_success( [ 'nonce' => wp_create_nonce( self::ACTION ) ] );
	}
}
