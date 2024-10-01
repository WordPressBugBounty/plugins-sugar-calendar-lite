<?php
/**
 * Sugar Calendar Event Tickets Admin - General Functions / actions
 *
 */
namespace Sugar_Calendar\AddOn\Ticketing\Admin;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Handles a CSV export request
 *
 * @since 1.0.0
 */
function export_tickets() {

	// Bail if not exporting
	if ( empty( $_GET['sc_et_export_tickets'] ) || empty( $_GET['sc_et_export_nonce'] ) ) {
		return;
	}

	// Bail if nonce check fails
	if ( ! wp_verify_nonce( $_GET['sc_et_export_nonce'], 'sc_et_export_nonce' ) ) {
		return;
	}

	// User capabilities are checked inside of the Tickets_Export class

	$args   = array( 'number' => 10000 );
	$search = ! empty( $_GET['s'] )
		? sanitize_text_field( $_GET['s'] )
		: '';

	if ( false !== strpos( $search, 'event:' ) ) {

		$search = str_replace( 'event:', '', $search );

		if ( is_numeric( $search ) ) {

			$event = sugar_calendar_get_event( $search );

			if ( empty( $event ) ) {
				// See if an event with a matching post ID exists
				$event = sugar_calendar_get_event_by_object( $search );
			}

		} else {

			// Search for an event by the title
			$event = sugar_calendar_get_event_by( 'title', $search );
		}

		if ( ! empty( $event ) ) {
			$args['event_id'] = $event->id;
			$search = '';
		}
	}

	$args['search'] = $search;

	if ( ! empty( $_GET['event_id'] ) && empty( $args['event_id'] ) ) {
		$args['event_id'] = absint( $_GET['event_id'] );
	}

	$export = new \Sugar_Calendar\AddOn\Ticketing\Export\Tickets_Export();
	$export->export( $args );
}
