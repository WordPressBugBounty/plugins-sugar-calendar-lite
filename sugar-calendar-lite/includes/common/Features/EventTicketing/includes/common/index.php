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

	// Rate-limited, so the plain link cannot be used to sidestep the gate the
	// ajax path honours. A blocked send reports as an error, as a failure would.
	$result = Functions\maybe_send_ticket_email( $ticket->id );

	$notice_type = $result['sent'] ? 'updated' : 'error';

	// Redirect with status. `ticket_code` carries the recipient through so the
	// receipt page can name who the email went to; the admin notice ignores it.
	wp_safe_redirect(
		add_query_arg(
			[
				'sc-notice-id'   => 'email-send',
				'sc-notice-type' => $notice_type,
				'ticket_code'    => $ticket->code,
			],
			wp_get_referer()
		)
	);
	exit;
}

/**
 * Email a ticket to its attendee without leaving the receipt.
 *
 * Verifies the same per-ticket nonce the link carries, so a request has to have
 * been served the row it acts on. Registered for logged-out visitors too: the
 * receipt is reached from an email, not from an account.
 *
 * @since 3.14.0
 */
function ajax_email_ticket() {

	$code  = isset( $_POST['ticket_code'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_code'] ) ) : '';
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	if ( empty( $code ) || ! wp_verify_nonce( $nonce, $code ) ) {
		wp_send_json_error();
	}

	$ticket = Functions\get_ticket_by_code( $code );

	if ( empty( $ticket ) ) {
		wp_send_json_error();
	}

	// A deleted event would fail deeper in, inside the email template.
	if ( empty( sugar_calendar_get_event( $ticket->event_id ) ) ) {
		wp_send_json_error();
	}

	$result = Functions\maybe_send_ticket_email( $ticket->id );

	if ( ! $result['sent'] ) {
		wp_send_json_error( [ 'retry_after' => $result['retry_after'] ] );
	}

	wp_send_json_success( [ 'retry_after' => $result['retry_after'] ] );
}
