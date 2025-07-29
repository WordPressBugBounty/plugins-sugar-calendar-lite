<?php
/**
 * Place code that is commonly available at all times in here
 */

namespace Sugar_Calendar\AddOn\Ticketing\Common;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Process request to email a ticket to an attendee.
 *
 * @since 1.0
 * @since 3.8.0 Added event check.
 */
function email_ticket() {

	// Bail if not running the email_ticket action.
	if ( ! isset( $_GET['sc_et_action'] ) || $_GET['sc_et_action'] !== 'email_ticket' ) {
		return;
	}

	// Bail if no nonce.
	if ( ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}

	// Get ticket based on code or ID.
	$ticket = false;

	if ( ! empty( $_GET['ticket_code'] ) ) {
		$code   = sanitize_text_field( wp_unslash( $_GET['ticket_code'] ) );
		$ticket = Functions\get_ticket_by_code( $code );
	} elseif ( ! empty( $_GET['ticket_id'] ) ) {
		$id     = absint( $_GET['ticket_id'] );
		$ticket = Functions\get_ticket( $id );
	}

	// Bail if no valid ticket found.
	if ( empty( $ticket ) ) {
		return;
	}

	// Verify nonce.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $ticket->code ) ) {
		return;
	}

	// Check if event exists.
	$event = sugar_calendar_get_event( $ticket->event_id );

	if ( empty( $event ) ) {
		wp_safe_redirect(
			add_query_arg(
				[
					'sc-notice-id'   => 'event-missing',
					'sc-notice-type' => 'error',
				],
				wp_get_referer()
			)
		);
		exit;
	}

	// Send email and set notice type.
	$notice_type = Functions\send_ticket_email( $ticket->id ) ? 'updated' : 'error';

	// Redirect with status.
	wp_safe_redirect(
		add_query_arg(
			[
				'sc-notice-id'   => 'email-send',
				'sc-notice-type' => $notice_type,
			],
			wp_get_referer()
		)
	);
	exit;
}
