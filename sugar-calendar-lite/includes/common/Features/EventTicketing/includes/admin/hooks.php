<?php
/**
 * Sugar Calendar  Event Tickets Admin Hooks
 *
 */

namespace Sugar_Calendar\AddOn\Ticketing\Admin;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Admin page
add_action( 'admin_init', __NAMESPACE__ . '\\update', 30 );
add_action( 'admin_init', __NAMESPACE__ . '\\delete', 30 );
add_action( 'admin_init', __NAMESPACE__ . '\\resend_receipt', 30 );
add_action( 'admin_init', __NAMESPACE__ . '\\export_tickets' );
add_action( 'admin_init', __NAMESPACE__ . '\\Settings\\process_stripe_connect_completion' );
add_action( 'admin_init', __NAMESPACE__ . '\\Settings\\process_stripe_disconnect' );
add_action( 'admin_init', __NAMESPACE__ . '\\Assets\\register' );

// Admin scripts
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\Assets\\enqueue' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\Assets\\localize' );

// Admin notices
add_action( 'admin_notices', __NAMESPACE__ . '\\notices' );

// Admin settings
add_filter( 'sugar_calendar_admin_area_current_page_id', __NAMESPACE__ . '\\Settings\\admin_area_current_page_id' );
add_filter( 'sugar_calendar_admin_area_pages', __NAMESPACE__ . '\\Settings\\admin_area_pages' );
add_filter( 'sugar_calendar_settings_subsections', __NAMESPACE__ . '\\Settings\\add_subsection' );
add_filter( 'sugar_calendar_settings_sections', __NAMESPACE__ . '\\Settings\\add_section' );
add_filter( 'sugar_calendar_admin_area_handle_post', __NAMESPACE__ . '\\Settings\\handle_post' );
add_filter( 'display_post_states', __NAMESPACE__ . '\\Settings\\add_page_states', 10, 2 );

// Admin meta box
add_action( 'sugar_calendar_admin_meta_box_setup_sections', __NAMESPACE__ . '\\Metabox\\metabox' );

// Admin nav
add_action( 'sugar_calendar_admin_nav_after_items', __NAMESPACE__ . '\\Nav\\test_mode' );
add_action( 'sugar_calendar_admin_nav_after_items', __NAMESPACE__ . '\\Nav\\stripe_connect' );

// Admin ajax
add_action( 'wp_ajax_sugar_calendar_admin_area_handle_post', __NAMESPACE__ . '\\Settings\\handle_post_ajax' );
