<?php
/**
 * Sugar Calendar Event Hooks
 *
 * @package Plugins/Site/Events/Hooks
 */

namespace Sugar_Calendar\AddOn\Ticketing;

use Sugar_Calendar\AttendeeTicketEmailDelivery;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Init
add_action( 'init', __NAMESPACE__ . '\\Common\\email_ticket' );

// Both variants: the receipt is reached from an email, so its visitor is usually
// logged out.
add_action( 'wp_ajax_sc_et_email_ticket', __NAMESPACE__ . '\\Common\\ajax_email_ticket' );
add_action( 'wp_ajax_nopriv_sc_et_email_ticket', __NAMESPACE__ . '\\Common\\ajax_email_ticket' );

// Meta data
add_filter( 'sugar_calendar_meta_data', __NAMESPACE__ . '\\Metadata\\register_meta_data' );
add_action( 'sugar_calendar_event_to_save', __NAMESPACE__ . '\\Metadata\\save_meta_data', 10, 2 );

// Email
add_action( 'sc_et_checkout_pre_redirect', __NAMESPACE__ . '\\Common\\Functions\\send_order_receipt_email' );

// Admin notification. Same once-per-order action as the receipt above — do NOT
// also hook sc_et_checkout_complete, which would double-send. Registered with
// the default accepted_args of 1, so the action's second argument ($order_data)
// cannot reach the function.
add_action( 'sc_et_checkout_pre_redirect', __NAMESPACE__ . '\\Common\\Functions\\send_ticket_sale_admin_email' );

// Attendee tickets. Same once-per-native-order action as the emails above.
// Keep the callback contract narrow: it needs $order_id, not $order_data.
add_action(
	'sc_et_checkout_pre_redirect',
	[ AttendeeTicketEmailDelivery::class, 'send_for_order' ]
);

// Order status transitions → per-status sc_et_order_{status} actions.
add_action( 'sc_transition_order_status', __NAMESPACE__ . '\\Common\\Functions\\trigger_order_status_changed', 10, 3 );

// Admin order: online meeting Join Link.
add_action( 'sc_et_admin_order_top', __NAMESPACE__ . '\\Common\\Functions\\render_admin_order_online_meeting' );

// Shortcodes
add_shortcode( 'sc_event_tickets_receipt', __NAMESPACE__ . '\\Shortcodes\\receipt_shortcode' );
add_shortcode( 'sc_event_tickets_details', __NAMESPACE__ . '\\Shortcodes\\ticket_shortcode' );

// Migration.
add_action( 'admin_init', __NAMESPACE__ . '\\Metadata\\maybe_migrate_ticket_limit_capacity' );
