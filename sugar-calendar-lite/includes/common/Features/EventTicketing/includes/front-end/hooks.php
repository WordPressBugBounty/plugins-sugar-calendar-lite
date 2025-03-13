<?php
/**
 * Sugar Calendar Event Hooks
 *
 * @package Plugins/Site/Events/Hooks
 */
namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Details.
add_action( 'sc_after_event_content', __NAMESPACE__ . '\\Single\\display', 10, 2 );

// Modal
add_action( 'wp_footer', __NAMESPACE__ . '\\Modal\\display', 10, 2 );

// Print
add_action( 'template_redirect', __NAMESPACE__ . '\\Print_View\\output', 10 );

// Assets
add_action( 'init',               __NAMESPACE__ . '\\Assets\\register' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\Assets\\enqueue'  );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\Assets\\localize' );
