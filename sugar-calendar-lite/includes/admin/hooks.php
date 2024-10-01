<?php
/**
 * Sugar Calendar Admin Hooks
 *
 * @package Plugins/Site/Events/Admin/Hooks
 */

namespace Sugar_Calendar\Admin;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Admin assets
add_action( 'admin_init', __NAMESPACE__ . '\\Assets\\register' );

// Admin screen options
add_action( 'admin_init', __NAMESPACE__ . '\\Screen\\Options\\save' );
add_action( 'admin_init', __NAMESPACE__ . '\\Screen\\Options\\reset' );

// Admin upgrades
add_action( 'admin_notices', __NAMESPACE__ . '\\Upgrades\\notices' );

// Admin meta box Save
add_filter( 'sugar_calendar_event_to_save', __NAMESPACE__ . '\\Editor\\Meta\\add_location_to_save' );
add_filter( 'sugar_calendar_event_to_save', __NAMESPACE__ . '\\Editor\\Meta\\add_color_to_save' );

// Admin meta box filter
add_filter( 'get_user_option_meta-box-order_sc_event', __NAMESPACE__ . '\\Editor\\Meta\\noop_user_option' );

// Admin New/Edit
add_action( 'edit_form_after_title', __NAMESPACE__ . '\\Editor\\above' );
add_action( 'edit_form_after_editor', __NAMESPACE__ . '\\Editor\\below' );

// Admin Messages
add_filter( 'post_updated_messages', __NAMESPACE__ . '\\Posts\\updated_messages' );

// Admin title
add_filter( 'admin_title', 'sugar_calendar_admin_title', 10, 2 );

// Admin Scripts
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\Assets\\enqueue' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\Assets\\localize' );

// Admin Event taxonomy tab override
//add_action( 'admin_notices', __NAMESPACE__ . '\\Nav\\taxonomy_tabs', 10, 1 );

// Admin remove quick/bulk edit admin screen
add_action( 'admin_print_footer_scripts', __NAMESPACE__ . '\\Posts\\hide_quick_bulk_edit' );

// Admin Screen Options
add_action( 'sugar_calendar_screen_options', __NAMESPACE__ . '\\Screen\\Options\\preferences' );

// Admin Post Type Redirect
add_action( 'load-edit.php', __NAMESPACE__ . '\\Posts\\redirect_old_post_type' );

// Get the page ID
$sc_admin_page = sugar_calendar_get_admin_page_id();

// Page ID specific actions
add_action( "load-{$sc_admin_page}", __NAMESPACE__ . '\\Menu\\maybe_empty_trash' );

// Global cleanup
unset( $sc_admin_page );
