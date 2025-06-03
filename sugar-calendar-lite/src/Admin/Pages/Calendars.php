<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use WP_Term;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Calendars page.
 *
 * @since 3.0.0
 */
class Calendars extends PageAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();

		return "edit-tags.php?taxonomy={$taxonomy}";
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Calendars', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 2;
	}

	/**
	 * Which menu item to highlight
	 * if the page doesn't appear in dashboard menu.
	 *
	 * @since 3.0.0
	 *
	 * @return null|string;
	 */
	public static function highlight_menu_item() {

		return static::get_slug();
	}

	/**
	 * Page capability.
	 *
	 * @since 3.0.1
	 *
	 * @return string
	 */
	public static function get_capability() {

		/**
		 * Filters the capability required to view the calendars page.
		 *
		 * @since 3.0.1
		 *
		 * @param string $capability Capability required to view the calendars page.
		 */
		return apply_filters( 'sugar_calendar_admin_pages_calendars_get_capability', 'edit_events' );
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 * @since 3.4.0 Add term_updated_messages filter.
	 */
	public function hooks() {

		add_filter( 'screen_options_show_screen', '__return_false' );
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );
		add_filter( 'tag_row_actions', [ $this, 'row_actions' ], 10, 2 );
		add_filter( 'get_edit_term_link', [ $this, 'get_edit_term_link' ], 10, 4 );
		add_filter( 'redirect_term_location', [ $this, 'redirect_after_save' ], 10, 2 );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		// Change the count column label.
		add_filter( 'manage_edit-sc_event_category_columns', [ $this, 'change_count_column_label' ] );

		// Set Calendar updated messages.
		add_filter( 'term_updated_messages', [ $this, 'get_calendar_updated_messages' ] );
	}

	/**
	 * Get list of calendar update messages.
	 *
	 * @since 3.4.0
	 *
	 * @param array $messages Map of messages.
	 *
	 * @return array
	 */
	public function get_calendar_updated_messages( $messages ) {

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();

		$messages[ $taxonomy ] = CalendarAbstract::get_calendar_updated_messages();

		return $messages;
	}

	/**
	 * Display the subheader.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function display_admin_subheader() {

		?>
        <div class="sugar-calendar-admin-subheader">
            <h4><?php esc_html_e( 'Calendars', 'sugar-calendar-lite' ); ?></h4>

			<?php
			UI::button(
				[
					'text'  => esc_html__( 'Add Calendar', 'sugar-calendar-lite' ),
					'size'  => 'sm',
					'class' => 'sugar-calendar-btn-new-item',
					'link'  => CalendarNew::get_url(),
				]
			);
			?>
        </div>

		<?php
		/**
		 * Runs before the page content is displayed.
		 *
		 * @since 3.0.0
		 */
		do_action( 'sugar_calendar_admin_page_before' ); //phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		?>
		<?php
	}

	/**
	 * Filter calendar table row actions.
	 *
	 * @since 3.0.0
	 *
	 * @param array   $actions List of actions.
	 * @param WP_Term $tag     Current term.
	 *
	 * @return array
	 */
	public function row_actions( $actions, $tag ) {

		if ( $tag->taxonomy !== sugar_calendar_get_calendar_taxonomy_id() ) {
			return $actions;
		}

		$actions = array_intersect_key(
			$actions,
			array_flip( [ 'edit', 'delete', 'view' ] )
		);

		return $actions;
	}

	/**
	 * Filter the edit link for calendar entries.
	 *
	 * @since 3.0.0
	 *
	 * @param string $location    Current link location.
	 * @param int    $term_id     Term ID.
	 * @param string $taxonomy    Taxonomy name.
	 * @param string $object_type Object type.
	 *
	 * @return mixed|string
	 */
	public function get_edit_term_link( $location, $term_id, $taxonomy, $object_type ) {

		if ( $taxonomy === sugar_calendar_get_calendar_taxonomy_id() ) {
			$location = add_query_arg( 'calendar_id', $term_id, CalendarEdit::get_url() );
		}

		return $location;
	}

	/**
	 * Filter the redirect location after a calendar create/update request.
	 *
	 * @since 3.0.0
	 * @since 3.4.0 Breaks down the method into smaller parts.
	 *
	 * @param string  $location Redirect location.
	 * @param WP_Term $taxonomy Current term.
	 *
	 * @return string
	 */
	public function redirect_after_save( $location, $taxonomy ) {

		if (
			! $this->is_valid_taxonomy( $taxonomy )
			||
			! $this->is_valid_request_action()
		) {
			return $location;
		}

		// Defined in edit-tags.php.
		global $ret;

		if ( $this->is_successful_creation( $ret ) ) {

			$location = $this->get_after_success_redirect_url( $location, $ret['term_id'] );

		} elseif ( is_wp_error( $ret ) ) {

			$location = $this->add_preserved_submitted_values( $location );
		}

		return $location;
	}

	/**
	 * Check if the taxonomy is valid.
	 *
	 * @since 3.4.0
	 *
	 * @param WP_Taxonomy $taxonomy Taxonomy object.
	 *
	 * @return boolean
	 */
	private function is_valid_taxonomy( $taxonomy ) {

		return $taxonomy->name === sugar_calendar_get_calendar_taxonomy_id();
	}

	/**
	 * Check if the request action is valid.
	 *
	 * @since 3.4.0
	 *
	 * @return boolean
	 */
	private function is_valid_request_action() {

		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $action === 'add-tag';
	}

	/**
	 * Check if the term was successfully created.
	 *
	 * @since 3.4.0
	 *
	 * @param array|WP_Error $ret Return value from the term creation function.
	 *
	 * @return boolean
	 */
	private function is_successful_creation( $ret ) {

		return $ret && ! is_wp_error( $ret );
	}

	/**
	 * Get redirect URL after successful Calendar creation.
	 *
	 * @since 3.4.0
	 *
	 * @return string
	 */
	private function get_after_success_redirect_url() {

		$calendar_list_url = admin_url( 'edit-tags.php' );

		// Add success message.
		return add_query_arg(
			[
				'taxonomy' => sugar_calendar_get_calendar_taxonomy_id(),
				'message'  => 1,
			],
			$calendar_list_url
		);
	}

	/**
	 * Preserve submitted values after a failed term creation.
	 *
	 * @since 3.4.0
	 *
	 * @param string $location Redirect location.
	 *
	 * @return string
	 */
	private function add_preserved_submitted_values( $location ) {

		return add_query_arg(
			[
				'preserved' => [
					// phpcs:disable WordPress.Security.NonceVerification.Recommended
					'slug'        => isset( $_REQUEST['slug'] ) ? sanitize_title( wp_unslash( $_REQUEST['slug'] ) ) : null,
					'parent'      => isset( $_REQUEST['parent'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['parent'] ) ) : null,
					'description' => isset( $_REQUEST['description'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['description'] ) ) : null,
					'term-color'  => isset( $_REQUEST['term-color'] ) ? rawurlencode( sanitize_text_field( wp_unslash( $_REQUEST['term-color'] ) ) ) : null,
					// phpcs:enable WordPress.Security.NonceVerification.Recommended
				],
			],
			$location
		);
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-admin-calendars',
			SC_PLUGIN_ASSETS_URL . 'css/admin-calendars' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);
	}

	/**
	 * Change the count column label in the calendar list table.
	 *
	 * @since 3.0.0
	 *
	 * @param array $columns Table columns.
	 *
	 * @return mixed
	 */
	public function change_count_column_label( $columns ) {

		$columns['posts'] = esc_html__( 'Events', 'sugar-calendar-lite' );

		return $columns;
	}
}
