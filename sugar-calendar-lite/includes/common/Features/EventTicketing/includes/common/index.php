<?php
/**
 * Place code that is commonly available at all times in here
 */

namespace Sugar_Calendar\AddOn\Ticketing\Common;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Process request to email a ticket to an attendee
 *
 * @since 1.0
 *
 * @return int
 */
function email_ticket() {

	// Bail if not the correct action
	if ( ! isset( $_GET['sc_et_action'] ) || ( 'email_ticket' !== $_GET['sc_et_action'] ) ) {
		return;
	}

	// Bail if no nonce
	if ( ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}

	// Default to no ticket
	$ticket = false;

	// Get ticket by code
	if ( ! empty( $_GET['ticket_code'] ) ) {
		$code   = sanitize_text_field( $_GET['ticket_code'] );
		$ticket = Functions\get_ticket_by_code( $code );

		// Get ticket by ID
	} elseif ( ! empty( $_GET['ticket_id'] ) ) {
		$id     = absint( $_GET['ticket_id'] );
		$ticket = Functions\get_ticket( $id );
	}

	// Bail if no ticket with that code
	if ( empty( $ticket ) ) {
		return;
	}

	// Bail if nonce fails
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], $ticket->code ) ) {
		return;
	}

	// Send the ticket email
	$notice_type = Functions\send_ticket_email( $ticket->id )
		? 'updated'
		: 'error';

	// Setup URL
	$url = add_query_arg(
		[
			'sc-notice-id'   => 'email-send',
			'sc-notice-type' => $notice_type,
		],
		wp_get_referer()
	);

	// Redirect
	wp_safe_redirect( $url );
	exit;
}
