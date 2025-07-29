<?php
/**
 * Sugar Calendar Event Tickets Admin - General Functions / actions
 *
 */
namespace Sugar_Calendar\AddOn\Ticketing\Admin;

use Sugar_Calendar\AddOn\Ticketing\Export\Tickets_Export;
use Sugar_Calendar\AddOn\Ticketing\Export\Orders_Export;
use Sugar_Calendar\AddOn\Ticketing\Admin\Tickets\List_Table as Tickets_List_Table;


// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Maybe export tickets.
 *
 * @since 3.8.0
 */
function maybe_export() {

	if ( empty( $_GET['sc_et_export_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_GET['sc_et_export_nonce'], 'sc_et_export_nonce' ) ) {
		return;
	}

	$allowed_exports = [
		'sc_et_export_tickets' => 'export_tickets',
		'sc_et_export_orders'  => 'export_orders',
	];

	$current_export_type = '';

	foreach ( $allowed_exports as $export_type => $export_function ) {

		// Check if $_GET has it.
		if ( empty( $_GET[ $export_type ] ) ) {
			continue;
		}

		$current_export_type = $export_type;

		// If the value is found, break the loop.
		break;
	}

	// If current export type is not found, return.
	if (
		empty( $current_export_type )
		||
		! isset( $allowed_exports[ $current_export_type ] )
	) {
		return;
	}

	$export_function = $allowed_exports[ $current_export_type ];

	// If the export function is not found, return.
	if ( ! function_exists( __NAMESPACE__ . '\\' . $export_function ) ) {
		return;
	}

	call_user_func( __NAMESPACE__ . '\\' . $export_function );
}

/**
 * Handles a CSV export request.
 *
 * @since 1.0.0
 * @since 3.8.0 Validation moved to maybe_export().
 */
function export_tickets() {

	// Fixed args.
	$args = [ 'number' => 10000 ];

	// If search in not empty.
	if ( ! empty( $_GET['search'] ) ) {

		$search = sanitize_text_field( wp_unslash( $_GET['search'] ) );

		$search_ids = Tickets_List_Table::sc_search_tickets( $search );

		if ( ! empty( $search_ids ) ) {
			$args['id__in'] = $search_ids;
		}
	}

	// If event_id in not empty.
	if ( ! empty( $_GET['event_id'] ) ) {
		$args['event_id'] = absint( wp_unslash( $_GET['event_id'] ) );
	}

	$export = new Tickets_Export();

	$export->export( $args );
}

/**
 * Handles a CSV export request for orders.
 *
 * @since 3.8.0
 */
function export_orders() {

	$args = [ 'number' => 10000 ];

	if ( ! empty( $_GET['search'] ) ) {
		$search = sanitize_text_field( wp_unslash( $_GET['search'] ) );

		$args['search'] = $search;
	}

	if ( ! empty( $_GET['event_id'] ) ) {
		$args['event_id'] = absint( wp_unslash( $_GET['event_id'] ) );
	}

	// Set status.
	$status = ! empty( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'any';

	if ( in_array( $status, [ 'any', 'pending', 'paid', 'refunded' ], true ) ) {
		$args['not_in'] = [ 'trash' ];
	}

	if ( $status === 'any' ) {

		$args['status'] = [
			'pending',
			'paid',
			'refunded',
		];

	} else {

		$args['status'] = $status;
	}

	$export = new Orders_Export();

	$export->export( $args );
}
