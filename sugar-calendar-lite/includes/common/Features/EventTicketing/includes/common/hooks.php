<?php
/**
 * Sugar Calendar Event Hooks
 *
 * @package Plugins/Site/Events/Hooks
 */

namespace Sugar_Calendar\AddOn\Ticketing;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Init
add_action( 'init', __NAMESPACE__ . '\\Common\\email_ticket' );

// Meta data
add_filter( 'sugar_calendar_meta_data', __NAMESPACE__ . '\\Metadata\\register_meta_data' );
add_action( 'sugar_calendar_event_to_save', __NAMESPACE__ . '\\Metadata\\save_meta_data' );

// Email
add_action( 'sc_et_checkout_pre_redirect', __NAMESPACE__ . '\\Common\\Functions\\send_order_receipt_email' );

// Shortcodes
add_shortcode( 'sc_event_tickets_receipt', __NAMESPACE__ . '\\Shortcodes\\receipt_shortcode' );
add_shortcode( 'sc_event_tickets_details', __NAMESPACE__ . '\\Shortcodes\\ticket_shortcode' );
