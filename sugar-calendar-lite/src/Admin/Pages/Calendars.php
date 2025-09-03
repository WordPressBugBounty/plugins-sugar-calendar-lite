<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helper;
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
	 * @since 3.8.2 Add custom "Events" column and renderer; remove default count column.
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_helpers_ui_help_url', [ $this, 'help_url' ] );

		add_filter( 'screen_options_show_screen', '__return_true' );
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );
		add_filter( 'tag_row_actions', [ $this, 'row_actions' ], 10, 2 );
		add_filter( 'get_edit_term_link', [ $this, 'get_edit_term_link' ], 10, 4 );
		add_filter( 'redirect_term_location', [ $this, 'redirect_after_save' ], 10, 2 );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		// Change the count column label.
		add_filter( 'manage_edit-sc_event_category_columns', [ $this, 'change_count_column_label' ] );

		// Add custom "Events" column to the Calendars list table.
		add_filter( 'manage_edit-sc_event_category_columns', [ $this, 'add_event_column' ] );

		// Render content for custom taxonomy columns.
		add_filter( 'manage_sc_event_category_custom_column', [ $this, 'render_event_column' ], 10, 3 );

		// Set Calendar updated messages.
		add_filter( 'term_updated_messages', [ $this, 'get_calendar_updated_messages' ] );
	}

	/**
	 * Filter the help URL in the calendars list page.
	 *
	 * @since 3.8.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return BaseHelpers\Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/organizing-events-with-calendars/',
			[
				'content' => 'Help',
				'medium'  => 'calendars-list',
			]
		);
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

		// Display search reset when searching calendars from taxonomy list screen.
		if (
			! empty( $_GET['s'] )
			&&
			isset( $_GET['taxonomy'] )
			&&
			sugar_calendar_get_calendar_taxonomy_id() === sanitize_key( wp_unslash( $_GET['taxonomy'] ) )
		) {

			$search_term = sanitize_text_field( wp_unslash( $_GET['s'] ) );
			$results     = get_terms(
				[
					'taxonomy'   => sugar_calendar_get_calendar_taxonomy_id(),
					'hide_empty' => false,
					'fields'     => 'ids',
					'search'     => $search_term,
				]
			);

			$total_count = is_wp_error( $results ) ? 0 : count( (array) $results );

			Helper::display_search_reset(
				$total_count,
				'calendar',
				'calendars',
				__( 'Calendars', 'sugar-calendar-lite' ),
				admin_url( 'edit-tags.php?taxonomy=' . sugar_calendar_get_calendar_taxonomy_id() )
			);
		}
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
	 * @since 3.8.2 Enqueue styles and scripts for search reset.
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-admin-calendars',
			SC_PLUGIN_ASSETS_URL . 'css/admin-calendars' . WP::asset_min() . '.css',
			[ 'sugar-calendar-admin-fontawesome' ],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_script(
			'sugar-calendar-admin-calendars',
			SC_PLUGIN_ASSETS_URL . 'js/admin-calendars' . WP::asset_min() . '.js',
			[ 'jquery' ],
			BaseHelpers::get_asset_version()
		);

		wp_localize_script(
			'sugar-calendar-admin-calendars',
			'sugarCalendarAdminCalendars',
			[
				'searchCalendarsPlaceholder' => sugar_calendar_get_calendar_taxonomy_labels( 'search_items' ),
				'searchCalendarsSubmit'      => sugar_calendar_get_calendar_taxonomy_labels( 'search_submit' ),
			]
		);
	}

	/**
	 * Change the count column label in the calendar list table.
	 *
	 * @since 3.0.0
	 * @since 3.8.2 Remove default taxonomy count column; use custom Events column instead.
	 *
	 * @param array $columns Table columns.
	 *
	 * @return mixed
	 */
	public function change_count_column_label( $columns ) {

		// Remove the default count column.
		if ( isset( $columns['posts'] ) ) {
			unset( $columns['posts'] );
		}

		return $columns;
	}

	/**
	 * Add custom column "Event2" (slug: event) to Calendars list table.
	 *
	 * @since 3.8.2
	 *
	 * @param array $columns Table columns.
	 *
	 * @return array
	 */
	public function add_event_column( $columns ) {

		$insertion = [ 'event' => esc_html__( 'Events', 'sugar-calendar-lite' ) ];

		// Insert after the Slug column when possible.
		if ( isset( $columns['slug'] ) ) {
			$new = [];

			foreach ( $columns as $key => $label ) {
				$new[ $key ] = $label;

				if ( $key === 'slug' ) {
					$new = array_merge( $new, $insertion );
				}
			}

			return $new;
		}

		// Fallback: append at the end.
		return array_merge( $columns, $insertion );
	}

	/**
	 * Render content for custom taxonomy columns.
	 *
	 * Currently renders a link with the term's Events count in the "Event2" column.
	 * This duplicates the Count column temporarily until the Count column is removed.
	 *
	 * @since 3.8.2
	 *
	 * @param string $content The custom column output. Default empty.
	 * @param string $column  Name of the column being displayed.
	 * @param int    $term_id Term ID.
	 *
	 * @return string
	 */
	public function render_event_column( $content, $column, $term_id ) { // phpcs:ignore WPForms.NamingConventions.ValidFunctionName.MethodNameInvalid

		if ( $column === 'event' ) {
			$term  = get_term( $term_id );
			$count = $term && ! is_wp_error( $term ) ? (int) $term->count : 0;

			$url = sugar_calendar_get_admin_url(
				[
					sugar_calendar_get_calendar_taxonomy_id() => $term ? $term->slug : '',
				]
			);

			$content = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				number_format_i18n( $count )
			);
		}

		return $content;
	}
}
