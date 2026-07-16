<?php

namespace Sugar_Calendar\Integrations\Admin;

use Sugar_Calendar\Integrations\EventMeetingManager;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\MeetingProviderInterface;
use WP_Screen;

/**
 * Event-editor notice: the provider meeting was deleted externally.
 *
 * When a meeting is deleted at the provider (Zoom), the relay forwards a
 * meeting.deleted webhook and ZoomWebhookEventHandler::handle_meeting_deleted()
 * silently resets the event to Online=None, leaving the
 * `online_meeting_removed_notice` breadcrumb (the provider slug). This class
 * renders a dismissible admin notice on that event's editor explaining the reset,
 * and clears the breadcrumb when the admin dismisses it.
 *
 * Provider-agnostic: the display name is resolved from the capability registry by
 * the stored slug, so a future provider needs no change here.
 *
 * Rendering surface — CLASSIC editor only: this hooks `admin_notices`, which the
 * block editor emits into the page but keeps hidden, so block-editor users do not
 * see it. This is accepted scope: the breadcrumb is persistent event meta, so it
 * surfaces the next time the event is opened in the classic editor (or is cleared
 * by a re-provision). The meeting-deleted-at-provider event is low-frequency. See
 * `.claude/rules/features/integration-oauth-relay.md`.
 *
 * @since 3.12.0
 */
class MeetingRemovedNotice {

	use PrintsDismissScript;

	/**
	 * Event-meta key holding the breadcrumb (the provider slug).
	 *
	 * Must equal the literal written by ZoomWebhookEventHandler::handle_meeting_deleted().
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const META_KEY = EventMeetingManager::REMOVED_NOTICE_META_KEY;

	/**
	 * Nonce action for the dismiss AJAX request.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const DISMISS_NONCE = 'sc_dismiss_meeting_removed_notice';

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
		add_action( 'wp_ajax_sc_dismiss_meeting_removed_notice', [ $this, 'ajax_dismiss' ] );
	}

	/**
	 * Render the notice on the event editor when the breadcrumb is set.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function maybe_render() {

		$screen = get_current_screen();

		if (
			! $screen instanceof WP_Screen
			||
			$screen->base !== 'post'
			||
			$screen->post_type !== sugar_calendar_get_event_post_type_id()
		) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( $post_id === 0 ) {
			return;
		}

		$event = sugar_calendar_get_event_by_object( $post_id );

		if ( empty( $event->id ) ) {
			return;
		}

		$slug = (string) get_event_meta( $event->id, self::META_KEY, true );

		if ( $slug === '' ) {
			return;
		}

		$provider_name = $this->get_provider_name( $slug );

		$dismiss_url = add_query_arg(
			[
				'action' => 'sc_dismiss_meeting_removed_notice',
				'event'  => $post_id,
				'nonce'  => wp_create_nonce( self::DISMISS_NONCE ),
			],
			admin_url( 'admin-ajax.php' )
		);

		printf(
			'<div class="notice notice-warning is-dismissible" data-sc-meeting-removed="1" data-sc-dismiss-url="%1$s"><p>%2$s</p></div>',
			esc_url( $dismiss_url ),
			esc_html(
				sprintf(
					/* translators: %1$s: online meeting provider name (e.g. Zoom). */
					__( 'The %1$s meeting for this event was deleted in %1$s, so this event\'s online platform was reset to None. Choose a platform and create a new meeting to host online again.', 'sugar-calendar-lite' ),
					$provider_name
				)
			)
		);

		$this->print_dismiss_script( '.notice[data-sc-meeting-removed]' );
	}

	/**
	 * Clear the breadcrumb when the admin dismisses the notice.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function ajax_dismiss() {

		if ( ! check_ajax_referer( self::DISMISS_NONCE, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'bad_nonce' ], 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;

		if ( $post_id === 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		$event = sugar_calendar_get_event_by_object( $post_id );

		if ( ! empty( $event->id ) ) {
			delete_event_meta( $event->id, self::META_KEY );
		}

		wp_send_json_success();
	}

	/**
	 * Resolve the provider display name from the capability registry.
	 *
	 * @since 3.12.0
	 *
	 * @param string $slug Provider slug.
	 *
	 * @return string
	 */
	private function get_provider_name( string $slug ): string {

		$provider = IntegrationCapabilityRegistry::instance()->find( MeetingProviderInterface::class, $slug );

		if ( $provider instanceof MeetingProviderInterface ) {
			return $provider->get_display_name();
		}

		// Fallback when the capability is not registered (e.g. Lite).
		return ucfirst( $slug );
	}
}
