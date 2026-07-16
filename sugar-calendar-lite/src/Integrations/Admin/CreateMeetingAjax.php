<?php

namespace Sugar_Calendar\Integrations\Admin;

use Sugar_Calendar\Admin\Events\Metaboxes\EventDateTimeRequest;
use Sugar_Calendar\Admin\Pages\SettingsIntegrationsTab;
use Sugar_Calendar\Integrations\EventMeetingManager;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\MeetingProviderInterface;

/**
 * Explicit "Create Zoom Meeting" AJAX endpoint.
 *
 * Provider-agnostic. Persists the editor's current title + date/time +
 * online_provider against the (already-existing) auto-draft, promotes it to a
 * real draft, then provisions the meeting via EventMeetingManager and returns
 * the rendered meeting card. Works identically for the Classic and Block
 * editors because creation is decoupled from the editor's own save.
 *
 * @since 3.12.0
 */
class CreateMeetingAjax {

	/**
	 * AJAX action + nonce slug.
	 */
	const ACTION = 'sc_create_online_meeting';

	/**
	 * @since 3.12.0
	 *
	 * @var IntegrationCapabilityRegistry
	 */
	private $registry;

	/**
	 * @since 3.12.0
	 *
	 * @var EventMeetingManager
	 */
	private $manager;

	/**
	 * @since 3.12.0
	 *
	 * @param IntegrationCapabilityRegistry $registry Capability registry.
	 * @param EventMeetingManager           $manager  Meeting lifecycle dispatcher.
	 */
	public function __construct( IntegrationCapabilityRegistry $registry, EventMeetingManager $manager ) {

		$this->registry = $registry;
		$this->manager  = $manager;
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

		$data['create_meeting'] = [
			'action' => self::ACTION,
			'nonce'  => wp_create_nonce( self::ACTION ),
		];

		return $data;
	}

	/**
	 * Handle the create request.
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

		$slug     = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$provider = $slug !== '' ? $this->registry->find( MeetingProviderInterface::class, $slug ) : null;

		if ( ! $provider ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Unknown online platform.', 'sugar-calendar-lite' ) ] );
		}

		// Recurring events cannot get an online meeting yet. Enforce it here, before
		// any post/event-row mutation: the editor JS lock is not a security boundary
		// (a user can disable JS or hand-craft this request), and rejecting early
		// also avoids the duplicate-event-row side effect of the lookup below.
		if ( $this->is_recurring( $post_id ) ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s - online meeting provider name (e.g. Zoom). */
						esc_html__( '%s is not supported for recurring events yet.', 'sugar-calendar-lite' ),
						$provider->get_display_name()
					),
				]
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

		if ( $title === '' ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Add a title and start date/time before creating the meeting.', 'sugar-calendar-lite' ) ] );
		}

		// Promote a brand-new auto-draft to a real draft; never demote an
		// already-published event (only set status when it is auto-draft).
		$update = [
			'ID'         => $post_id,
			'post_title' => $title,
		];

		if ( get_post_status( $post_id ) === 'auto-draft' ) {
			$update['post_status'] = 'draft';
		}

		wp_update_post( $update );

		$event = sugar_calendar_get_event_by_object( $post_id );

		// online_visibility: same default logic as OnlineMeetingSection::save() —
		// defaults to 'attendees' when not posted (radio is only rendered after the
		// meeting exists, so on a first-create it is always absent).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$raw_visibility = isset( $_POST['online_visibility'] ) ? sanitize_key( wp_unslash( $_POST['online_visibility'] ) ) : '';
		$visibility     = in_array( $raw_visibility, [ 'everyone', 'attendees' ], true ) ? $raw_visibility : 'attendees';

		// Persist title + date/time + online_provider + online_visibility (the
		// minimal coherent save). These are registered meta keys so they persist
		// from this array; other typed fields persist on the user's later Publish.
		$to_save = array_merge(
			EventDateTimeRequest::from_request(),
			[
				'object_id'          => $post_id,
				'object_type'        => ! empty( $event->object_type ) ? $event->object_type : 'post',
				'object_subtype'     => get_post_type( $post_id ),
				'title'              => $title,
				'content'            => get_post_field( 'post_content', $post_id ),
				'status'             => get_post_status( $post_id ),
				'online_provider'    => $slug,
				'online_visibility'  => $visibility,
			]
		);

		// The SC event row may not exist yet (auto-draft posts have no row until
		// the first metabox save). Create it when missing, update otherwise.
		if ( ! empty( $event->id ) ) {
			sugar_calendar_update_event( $event->id, $to_save, $event );
		} else {
			$new_id = sugar_calendar_add_event( $to_save );
			if ( ! $new_id ) {
				wp_send_json_error( [ 'message' => esc_html__( 'Could not create the event record.', 'sugar-calendar-lite' ) ] );
			}
		}

		$event = sugar_calendar_get_event_by_object( $post_id );

		if ( empty( $event->start ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Add a title and start date/time before creating the meeting.', 'sugar-calendar-lite' ) ] );
		}

		$result = $this->manager->provision_meeting( $provider, $event );

		if ( is_wp_error( $result ) ) {
			$error = [ 'message' => $result->get_error_message() ];

			// A missing provider connection is recoverable straight from the
			// integration's settings screen — surface a link so the user can
			// connect without hunting for the page. Provider-agnostic: matches
			// any "{slug}_no_connection" error code (e.g. zoom_no_connection).
			if ( strpos( $result->get_error_code(), 'no_connection' ) !== false ) {
				$error['settings_url']  = SettingsIntegrationsTab::get_integration_url( $slug );
				$error['settings_text'] = sprintf(
					/* translators: %s - online meeting provider name (e.g. Zoom). */
					esc_html__( 'Connect %s', 'sugar-calendar-lite' ),
					$provider->get_display_name()
				);
			}

			wp_send_json_error( $error );
		}

		// Re-fetch so the renderer reads the freshly-written meeting meta.
		$event = sugar_calendar_get_event_by_object( $post_id );

		wp_send_json_success(
			[
				'card_html' => ( new OnlineMeetingSection() )->render_details_html( $event ),
				'post_id'   => $post_id,
				'nonce'     => wp_create_nonce( self::ACTION ),
			]
		);
	}

	/**
	 * Whether the event is recurring.
	 *
	 * The persisted `sc_recurring_event` post type is the tamper-proof signal for
	 * an already-recurring event (a user cannot fake it through this request); the
	 * posted `recurrence` field mirrors the editor's current — possibly unsaved —
	 * selection, where '0'/'' means "Never". Either one being recurring blocks the
	 * create. Reading `$event->recurrence` is deliberately NOT used: the lookup
	 * here defaults to the `sc_event` subtype and resolves an empty event for a
	 * recurring parent, so its recurrence is never populated.
	 *
	 * @since 3.12.0
	 *
	 * @param int $post_id Event post id.
	 *
	 * @return bool
	 */
	private function is_recurring( $post_id ) {

		if ( get_post_type( $post_id ) === 'sc_recurring_event' ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handle().
		$posted = isset( $_POST['recurrence'] ) ? sanitize_key( wp_unslash( $_POST['recurrence'] ) ) : '';

		return $posted !== '' && $posted !== '0';
	}
}
