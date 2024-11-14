<?php
namespace Sugar_Calendar\AddOn\Ticketing\Metadata;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Meta schema.
 *
 * @since 3.4.0
 *
 * @return array
 */
function schema() {

	return [
		'tickets' => [
			'type'              => 'boolean',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\sanitize_boolean',
			'auth_callback'     => null,
			'show_in_rest'      => false,
		],
		'ticket_price' => [
			'type'              => 'number',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\sanitize_amount',
			'auth_callback'     => null,
			'show_in_rest'      => true,
		],
		'ticket_quantity' => [
			'type'              => 'integer',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => null,
			'show_in_rest'      => false,
		],
	];
}

/**
 * Register meta data keys & sanitization callbacks.
 *
 * @since 1.0.0
 * @since 3.4.0 Use schema() to define meta keys.
 *
 * @param array $schema
 *
 * @return array
 */
function register_meta_data( $schema = [] ) {

	return array_merge( $schema, schema() );
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
