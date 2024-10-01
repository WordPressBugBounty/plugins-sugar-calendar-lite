<?php
namespace Sugar_Calendar\AddOn\Ticketing\Metadata;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Register meta data keys & sanitization callbacks
 *
 * @since 1.0.0
 */
function register_meta_data() {

	// Enable Tickets
	register_meta( 'sc_event', 'tickets', array(
		'type'              => 'boolean',
		'description'       => '',
		'single'            => true,
		'sanitize_callback' => '__return_true',
		'auth_callback'     => null,
		'show_in_rest'      => false,
	) );

	// Ticket Price
	register_meta( 'sc_event', 'ticket_price', array(
		'type'              => 'number',
		'description'       => '',
		'single'            => true,
		'sanitize_callback' => 'Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\sanitize_amount',
		'auth_callback'     => null,
		'show_in_rest'      => true,
	) );

	// Ticket Quantity
	register_meta( 'sc_event', 'ticket_quantity', array(
		'type'              => 'integer',
		'description'       => '',
		'single'            => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => null,
		'show_in_rest'      => false,
	) );
}

/**
 * Add our ticket meta data to the save routine
 *
 * @since 1.0.0
 *
 * @param array $event_data
 *
 * @return array
 */
function save_meta_data( $event_data = array() ) {

	// Enable
	$event_data['tickets'] = ! empty( $_POST['enable_tickets'] )
		? 1
		: 0;

	// Price
	$event_data['ticket_price'] = ! empty( $_POST['ticket_price'] )
		? sanitize_text_field( $_POST['ticket_price'] )
		: '';

	// Quantity
	$event_data['ticket_quantity'] = ! empty( $_POST['ticket_quantity'] )
		? absint( $_POST['ticket_quantity'] )
		: '';

	// Return event metadata array
	return $event_data;
}
