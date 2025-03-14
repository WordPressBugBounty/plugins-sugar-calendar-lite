<?php
/**
 * Events Admin Menu
 *
 * @package Plugins/Site/Events/Admin/Menu
 */

namespace Sugar_Calendar\Admin\Menu;

// Exit if accessed directly

defined( 'ABSPATH' ) || exit;

/**
 * Add the "Calendar" submenu
 *
 * @since 2.0.0
 */
function register_page() {

	// Get the main post type object
	$post_type = sugar_calendar_get_event_post_type_id();
	$pt_object = get_post_type_object( $post_type );

	// Add an invisible upgrades page
	add_submenu_page(
		null,
		esc_html__( 'Sugar Calendar Upgrade', 'sugar-calendar-lite' ),
		esc_html__( 'Sugar Calendar Upgrade', 'sugar-calendar-lite' ),
		'manage_options',
		'sc-upgrades',
		'Sugar_Calendar\\Admin\\Upgrades\\page'
	);

	// Labels
	$menu_name    = sugar_calendar_get_post_type_label( $post_type, 'menu_name', esc_html__( 'Calendar', 'sugar-calendar-lite' ) );
	$submenu_name = sugar_calendar_get_post_type_label( $post_type, 'submenu_name', esc_html__( 'Calendar', 'sugar-calendar-lite' ) );
	$add_new      = sugar_calendar_get_post_type_label( $post_type, 'add_new', esc_html__( 'Add New', 'sugar-calendar-lite' ) );

	// Default hooks array
	$hooks = array();

	// Main "Calendar" plugin menu page
	$hooks[] = add_menu_page(
		$menu_name,
		$menu_name,
		'read_calendar',
		'sugar-calendar',
		'Sugar_Calendar\\Admin\\Menu\\calendar_page',
		'dashicons-calendar-alt',
		2
	);

	// "Calendar" (or "All Events") submenu page
	$hooks[] = add_submenu_page(
		'sugar-calendar',
		$submenu_name,
		$submenu_name,
		'read_calendar',
		'sugar-calendar',
		'Sugar_Calendar\\Admin\\Menu\\calendar_page',
		0
	);

	// "Add New" page
	$hooks[] = add_submenu_page(
		'sugar-calendar',
		$add_new,
		$add_new,
		$pt_object->cap->create_posts,
		'post-new.php?post_type=' . $post_type,
		false,
		1
	);

	// Remove duplicates & empties
	$hooks = array_unique( array_filter( $hooks ) );

	// Bail if no hooks
	if ( empty( $hooks ) ) {
		return;
	}

	// Highlight helper
	foreach ( $hooks as $hook ) {
		add_action( "admin_head-{$hook}", 'Sugar_Calendar\\Admin\\Menu\\add_pointers' );
		add_action( "admin_head-{$hook}", 'Sugar_Calendar\\Admin\\Help\\add_calendar_tabs' );
		add_action( "admin_head-{$hook}", 'Sugar_Calendar\\Admin\\Screen\\Options\\add' );
	}
}

/**
 * Override the pointer dismiss button text, to make it clear that "Dismiss"
 * does not mean the event itself is being dismissed in same way.
 *
 * @since 2.0.0
 */
function add_pointers() {

	wp_localize_script( 'wp-pointer', 'wpPointerL10n', array(
		'dismiss' => esc_html__( 'Close', 'sugar-calendar-lite' ),
	) );
}

/**
 * This tells WordPress to highlight the Events > Calendar submenu.
 *
 * @since 2.0.0
 *
 * @global string $parent_file
 * @global array  $submenu_file
 * @global array  $pagenow
 */
function fix_menu_highlight() {

	global $parent_file, $submenu_file, $pagenow;

	// Highlight both, since they're the same thing.
	if ( sugar_calendar_admin_is_events_page() ) {

		// Always set the parent file to the main menu
		$parent_file = 'sugar-calendar';

		// Fix "Add New Event" and "Edit Event" highlights
		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			$submenu_file = 'post-new.php?post_type=sc_event';

			// Set to calendar
		} else {
			$submenu_file = 'sugar-calendar';
		}
	}
}

/**
 * This wrapper function is hooked early in the page load process, ensuring that
 * the List Table is invoked correctly so commonly used filters are fired.
 *
 * The get_list_table() function maintains a local static variable of this List
 * Table for later reuse.
 *
 * @since 2.0.15
 */
function preload_list_table() {

	get_list_table();
}

/**
 * Get the List Table object for the current view mode.
 *
 * @since     2.0.15
 *
 * @staticvar object $retval
 *
 * @return object
 */

/**
 * Output the admin calendar page
 *
 * @since 2.0.0
 */
function calendar_page() {

	// Get the list table
	$list_table = get_list_table();

	// Query for calendar content
	$list_table->prepare_items();

	// Set the help tabs
	$list_table->set_help_tabs(); ?>

    <div class="wrap">
		<?php $list_table->page_heading(); ?>

		<?php \Sugar_Calendar\Admin\Nav\display(); ?>

        <hr class="wp-header-end">

        <div id="sugar-calendar-admin-calendar-wrapper">

			<?php $list_table->views(); ?>

            <form id="posts-filter" method="get">

                <input type="hidden" name="page" value="sugar-calendar"/>

				<?php $list_table->display(); ?>

            </form>

            <div id="ajax-response"></div>
            <br class="clear">
        </div>
    </div>

	<?php
}

/**
 * Maybe empty event trash.
 *
 * Hooked onto a specific action, and empties the event trash after a series of
 * nonce and capability checks.
 *
 * @since 2.0.0
 * @since 3.6.0 Added the hook `sugar_calendar_admin_menu_maybe_empty_trash_after`.
 */
function maybe_empty_trash() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.NestingLevel.MaxExceeded

	if ( ! isset( $_REQUEST['delete_all_trashed_events'] ) ) {
		return;
	}

	check_admin_referer( 'event-actions' );

	$pt_obj = get_post_type_object( sugar_calendar_get_event_post_type_id() );

	// Bail if current user cannot trash events.
	if ( ! current_user_can( $pt_obj->cap->delete_posts ) ) {
		return;
	}

	$trashed = sugar_calendar_get_events(
		[
			'status'            => 'trash',
			'number'            => false,
			'update_item_cache' => false,
			'update_meta_cache' => false,
			'no_found_rows'     => true,
		]
	);

	if ( ! empty( $trashed ) ) {
		$post_ids = wp_filter_object_list( $trashed, [ 'object_type' => 'post' ], 'and', 'object_id' );

		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				if ( current_user_can( 'delete_post', $post_id ) ) {
					wp_delete_post( $post_id );
				}
			}
		}

		// Delete all trashed events, regardless of their object relationships.
		sugar_calendar_delete_events( [ 'status' => 'trash' ] );
	}

	/**
	 * Fires after trashed events are deleted.
	 *
	 * @since 3.6.0
	 */
	do_action( 'sugar_calendar_admin_menu_maybe_empty_trash_after' );

	wp_safe_redirect( remove_query_arg( 'delete_all_trashed_events', wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	exit();
}
