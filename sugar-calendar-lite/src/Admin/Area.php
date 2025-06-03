<?php

namespace Sugar_Calendar\Admin;

use Sugar_Calendar\Admin\Events\Events as AdminEvents;
use Sugar_Calendar\Admin\Events\Metaboxes;
use Sugar_Calendar\Admin\Pages\Addons;
use Sugar_Calendar\Admin\Pages\CalendarEdit;
use Sugar_Calendar\Admin\Pages\CalendarNew;
use Sugar_Calendar\Admin\Pages\Calendars;
use Sugar_Calendar\Admin\Pages\EventEdit;
use Sugar_Calendar\Admin\Pages\EventNew;
use Sugar_Calendar\Admin\Pages\Events;
use Sugar_Calendar\Admin\Pages\Rsvp;
use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Admin\Pages\SettingsFeedsTab;
use Sugar_Calendar\Admin\Pages\SettingsGeneralTab;
use Sugar_Calendar\Admin\Pages\SettingsMapsTab;
use Sugar_Calendar\Admin\Pages\SettingsMiscTab;
use Sugar_Calendar\Admin\Pages\SettingsRsvpTab;
use Sugar_Calendar\Admin\Pages\SettingsZapierTab;
use Sugar_Calendar\Admin\Pages\Tools;
use Sugar_Calendar\Admin\Pages\ToolsExportTab;
use Sugar_Calendar\Admin\Pages\ToolsImportTab;
use Sugar_Calendar\Admin\Pages\ToolsMigrateTab;
use Sugar_Calendar\Admin\Pages\Venues;
use Sugar_Calendar\Admin\Pages\Welcome;
use Sugar_Calendar\Admin\Pages\Speakers;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Plugin;
use function Sugar_Calendar\Admin\Settings\get_sections;

/**
 * Class Area registers and process all wp-admin display functionality.
 *
 * @since 3.0.0
 */
class Area {

	/**
	 * Slug of the whole admin area.
	 *
	 * @since 3.0.0
	 *
	 * @var string Admin area slug.
	 */
	const SLUG = 'sugar-calendar';

	/**
	 * Transient controlling welcome redirect.
	 *
	 * @since 3.0.0
	 *
	 * @var string Transient name.
	 */
	const TRANSIENT_REDIRECT = 'sugar_calendar_activation_redirect';

	/**
	 * Option preventing welcome redirect.
	 *
	 * @since 3.0.0
	 *
	 * @var string Transient name.
	 */
	const OPTION_REDIRECT = 'sugar_calendar_prevent_redirect';

	/**
	 * Current page instance.
	 *
	 * @since 3.0.0
	 *
	 * @var PageInterface Current page.
	 */
	protected $current_page;

	/**
	 * AJAX Handler for updating hand holding status.
	 *
	 * @since 3.7.0
	 */
	public function ajax_update_hand_holding_status() {

		if ( ! check_ajax_referer( 'sc_hand_holding_status', 'nonce' ) ) {
			wp_send_json_error( esc_html__( 'Invalid request!', 'sugar-calendar-lite' ) );
		}

		if ( empty( $_POST['status'] ) ) {
			wp_send_json_error( esc_html__( 'Status is required', 'sugar-calendar-lite' ) );
		}

		$status = sanitize_key( wp_unslash( $_POST['status'] ) );

		if ( ! in_array( $status, [ 'start', 'exit-browser', 'cancel', 'draft', 'publish', 'complete' ], true ) ) {
			wp_send_json_error( esc_html__( 'Status invalid!', 'sugar-calendar-lite' ) );
		}

		$step = ! empty( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';

		$this->update_hand_holding_status( $status, $step );

		wp_send_json_success( $status );
	}

	/**
	 * Update the hand holding status.
	 *
	 * @since 3.7.0
	 *
	 * @param string $status The new status.
	 * @param string $step   Current step.
	 */
	private function update_hand_holding_status( $status, $step = '' ) {

		$data = [];

		switch ( $status ) {
			case 'start':
				$data = [
					'start_time' => time(),
					'status'     => 'start',
				];
				break;

			default:
				$data = [
					'end_time'   => time(),
					'status'     => $status,
					'step'       => $step,
				];
				break;
		}

		if ( empty( $data ) ) {
			return;
		}

		// Get current hand holding data.
		$hand_holding_status = get_option( 'sc_hand_holding_status', [
			'status'     => '',
			'start_time' => 0,
			'end_time'   => 0,
			'step'       => '',
		] );

		$new_data = [];

		foreach ( $hand_holding_status as $key => $val ) {

			if ( array_key_exists( $key, $data ) ) {
				$new_data[ $key ] = $data[ $key ];
			} else {
				$new_data[ $key ] = $val;
			}
		}

		update_option( 'sc_hand_holding_status', $new_data );
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 * @since 3.2.0 Added the Admin\Events hooks.
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'wp_ajax_sc_hand_holding_status', [ $this, 'ajax_update_hand_holding_status' ] );

		( new AdminEvents() )->hooks();

		// Populate the menu. Hooked on highest priority
		// to ensure addons register menu items first.
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );

		add_filter( 'parent_file', [ $this, 'force_parent_menu' ] );

		// Handle different menu highlighting scenarios.
		add_filter( 'submenu_file', [ $this, 'force_sub_menu' ] );

		// Initialize current page. We run this at high priority
		// so that page classes can still hook onto `admin_init`.
		add_action( 'admin_init', [ $this, 'init' ], 0 );

		// Maybe redirect to welcome screen.
		add_action( 'admin_init', [ $this, 'maybe_redirect_welcome' ], PHP_INT_MAX );

		// Handle POST requests.
		add_action( 'admin_init', [ $this, 'handle_post' ] );

		// Handle AJAX requests.
		add_action( 'wp_ajax_' . self::SLUG, [ $this, 'handle_ajax' ] );

		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Admin body class.
		add_filter( 'admin_body_class', [ $this, 'body_class' ] );

		// Admin header.
		add_action( 'in_admin_header', [ $this, 'display_admin_header' ] );

		// Remove unrelated notices.
		add_action( 'admin_print_scripts', [ $this, 'hide_unrelated_notices' ] );

		( new Education() )->hooks();

		// Metaboxes.
		( new Metaboxes() )->hooks();

		// Product education.
		if ( ! Plugin::instance()->is_pro() ) {
			add_filter( 'sugar_calendar_admin_area_pages', [ $this, 'product_education_get_pages' ] );
			add_filter( 'sugar_calendar_admin_area_current_page_id', [ $this, 'product_education_current_page_id' ] );
			add_filter( 'sugar_calendar_admin_pages_settings_get_tabs', [ $this, 'product_education_settings_page_tabs' ] );
		}

		// Ticketing and Zapier legacy addons settings compatibility.
		add_filter(
			'sugar_calendar_admin_area_current_page_id',
			function ( $page_id ) {

				$legacy_tabs = get_sections();

				// Return catch-all Settings page id if ticketing or Zapier legacy addons
				// have registered their own settings screen.
				if ( isset( $legacy_tabs['tickets'] ) && $page_id === 'settings_tickets' ) {
					$page_id = 'settings';
				}

				if ( isset( $legacy_tabs['zapier'] ) && $page_id === 'settings_zapier' ) {
					$page_id = 'settings';
				}

				return $page_id;
			}
		);

		$this->handle_rsvp_pages();
	}

	/**
	 * Add admin area menu items.
	 *
	 * @since 3.0.0
	 * @since 3.0.1 Apply filter on Sugar Calendar Menu capability.
	 * @since 3.3.0 Added 'Tools' submenu.
	 * @since 3.5.0 Added 'Venues' submenu.
	 * @since 3.7.0 Added 'RSVP' submenu.
	 *
	 * @return void
	 */
	public function admin_menu() {

		add_menu_page(
			esc_html__( 'Sugar Calendar', 'sugar-calendar-lite' ),
			esc_html__( 'Sugar Calendar', 'sugar-calendar-lite' ),
			/**
			 * Filters the capability required to view the Sugar Calendar menu.
			 *
			 * @since 3.0.1
			 *
			 * @param string $capability Capability required to view the Sugar Calendar menu.
			 */
			apply_filters( 'sugar_calendar_admin_area_capability', 'edit_events' ),
			self::SLUG,
			[ $this, 'display' ],
			'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTciIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNyAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0zLjYwNjI3IDAuNzg1MjQxQzMuNjA2MjcgMC41ODIwMDggMy42OTg2NSAwLjM3ODc3NCAzLjg0NjQ1IDAuMjMwOTY4QzQuMDEyNzMgMC4wODMxNjI0IDQuMjE1OTcgLTAuMDA5MjE2MzEgNC40Mzc2OCAtMC4wMDkyMTYzMUM0LjY1OTM5IC0wLjAwOTIxNjMxIDQuODgxMDkgMC4wODMxNjI0IDUuMDI4OSAwLjIzMDk2OEM1LjE3NjcxIDAuMzc4Nzc0IDUuMjY5MDkgMC41NjM1MzIgNS4yNjkwOSAwLjc4NTI0MUgxMC43NzQ5QzEwLjc3NDkgMC41ODIwMDggMTAuODY3MiAwLjM3ODc3NCAxMS4wMTUgMC4yMzA5NjhDMTEuMTYyOCAwLjA4MzE2MjQgMTEuMzg0NiAtMC4wMDkyMTYzMSAxMS42MDYzIC0wLjAwOTIxNjMxQzExLjgyOCAtMC4wMDkyMTYzMSAxMi4wNDk3IDAuMDgzMTYyNCAxMi4xOTc1IDAuMjMwOTY4QzEyLjM0NTMgMC4zNzg3NzQgMTIuNDM3NyAwLjU2MzUzMiAxMi40Mzc3IDAuNzg1MjQxSDEyLjcxNDhDMTQuNTQzOSAwLjc4NTI0MSAxNi4wMjIgMi4yNjMzIDE2LjAyMiA0LjA5MjRWMTIuNjY1MUMxNi4wMjIgMTQuNDk0MiAxNC41NDM5IDE1Ljk3MjMgMTIuNzE0OCAxNS45NzIzSDMuMzI5MTNDMS41MDAwMyAxNS45NzIzIDAuMDIxOTcyNyAxNC40OTQyIDAuMDIxOTcyNyAxMi42NjUxVjQuMDkyNEMwLjAyMTk3MjcgMi4yNjMzIDEuNTAwMDMgMC43ODUyNDEgMy4zMjkxMyAwLjc4NTI0MUgzLjYwNjI3Wk0xMy45ODk2IDExLjg4OTJDMTMuOTg5NiAxMi40NjE5IDEzLjc2NzkgMTIuOTk3NyAxMy4zNjE1IDEzLjQwNDJDMTIuOTU1IDEzLjgxMDYgMTIuNDE5MiAxNC4wMzI0IDExLjg0NjUgMTQuMDMyNEg0LjE5NzQ5QzMuMDE1MDQgMTQuMDMyNCAyLjA1NDMxIDEzLjA3MTYgMi4wNTQzMSAxMS44ODkyVjExLjUwMTJDMi4wNTQzMSAxMS41MDEyIDIuMTA5NzMgMTEuMzcxOCAyLjE2NTE2IDExLjM3MThIOS45MjQ5N0MxMC40MDUzIDExLjM3MTggMTAuNzc0OSAxMS4wMDIzIDEwLjc3NDkgMTAuNTIyQzEwLjc3NDkgMTAuMDYwMSAxMC4zODY5IDkuNjcyMDggOS45MjQ5NyA5LjY3MjA4SDMuMDE1MDRDMi43NTYzOCA5LjY3MjA4IDIuNTE2MiA5LjU3OTcgMi4zMzE0NCA5LjM5NDk0QzIuMTQ2NjggOS4yMTAxOCAyLjA1NDMxIDguOTcgMi4wNTQzMSA4LjcyOTgxVjQuMjQwMjFDMi4wNTQzMSAzLjgzMzc0IDIuMjIwNTkgMy40NDU3NSAyLjQ5NzcyIDMuMTY4NjFDMi43NzQ4NiAyLjg5MTQ4IDMuMTYyODUgMi43MjUxOSAzLjU2OTMyIDIuNzI1MTlDMy41NjkzMiAyLjcyNTE5IDMuNTY5MzIgMi43MjUxOSAzLjU4Nzc5IDIuNzI1MTlDMy41ODc3OSAyLjcyNTE5IDMuNTg3NzkgMi43MjUxOSAzLjU4Nzc5IDIuNzQzNjdDMy41ODc3OSAzLjE1MDE0IDMuOTIwMzYgMy40ODI3IDQuMzI2ODIgMy40ODI3SDQuNTMwMDZDNC45MzY1MiAzLjQ4MjcgNS4yNjkwOSAzLjE1MDE0IDUuMjY5MDkgMi43NDM2N0M1LjI2OTA5IDIuNzQzNjcgNS4yNjkwOSAyLjcwNjcyIDUuMzA2MDQgMi43MDY3MkgxMC43Mzc5QzEwLjczNzkgMi43MDY3MiAxMC43NzQ5IDIuNzA2NzIgMTAuNzc0OSAyLjc0MzY3QzEwLjc3NDkgMy4xNTAxNCAxMS4xMDc0IDMuNDgyNyAxMS41MTM5IDMuNDgyN0gxMS43MTcxQzEyLjEyMzYgMy40ODI3IDEyLjQ1NjIgMy4xNTAxNCAxMi40NTYyIDIuNzQzNjdWMi43MjUxOUMxMi40NTYyIDIuNzI1MTkgMTIuNDU2MiAyLjcyNTE5IDEyLjQ3NDYgMi43MjUxOUMxMy4zMjQ1IDIuNzI1MTkgMTMuOTg5NiAzLjQwODggMTMuOTg5NiA0LjI0MDIxVjUuMjU2MzdDMTMuOTg5NiA1LjI1NjM3IDEzLjkzNDIgNS4zODU3IDEzLjg3ODggNS4zODU3SDYuMTM3NDVDNS42NzU1NSA1LjM4NTcgNS4yODc1NiA1Ljc1NTIyIDUuMjg3NTYgNi4yMzU1OUM1LjI4NzU2IDYuNjk3NDggNS42NzU1NSA3LjA4NTQ3IDYuMTM3NDUgNy4wODU0N0gxMy4wNDc0QzEzLjMwNiA3LjA4NTQ3IDEzLjU0NjIgNy4xNzc4NSAxMy43MzEgNy4zNjI2MUMxMy45MTU3IDcuNTQ3MzcgMTQuMDA4MSA3Ljc4NzU1IDE0LjAwODEgOC4wMjc3M1YxMS44ODkySDEzLjk4OTZaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K',
			50
		);

		add_submenu_page(
			self::SLUG,
			Events::get_title(),
			Events::get_title(),
			Events::get_capability(),
			Events::get_slug(),
			[ $this, 'display' ],
			Events::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			EventNew::get_title(),
			EventNew::get_title(),
			EventNew::get_capability(),
			EventNew::get_slug(),
			'',
			EventNew::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			Calendars::get_title(),
			Calendars::get_title(),
			Calendars::get_capability(),
			Calendars::get_slug(),
			'',
			Calendars::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			CalendarNew::get_title(),
			CalendarNew::get_title(),
			CalendarNew::get_capability(),
			CalendarNew::get_slug(),
			[ $this, 'display' ],
			CalendarNew::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			CalendarEdit::get_title(),
			CalendarEdit::get_title(),
			CalendarEdit::get_capability(),
			CalendarEdit::get_slug(),
			[ $this, 'display' ],
			CalendarEdit::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			Venues::get_title(),
			Venues::get_title(),
			Venues::get_capability(),
			Venues::get_slug(),
			sugar_calendar()->is_pro() ? '' : [ $this, 'display' ],
			Venues::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			Speakers::get_title(),
			Speakers::get_title(),
			Speakers::get_capability(),
			Speakers::get_slug(),
			sugar_calendar()->is_pro() ? '' : [ $this, 'display' ],
			Speakers::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			Rsvp::get_title(),
			Rsvp::get_title(),
			Rsvp::get_capability(),
			Rsvp::get_slug(),
			[ $this, 'display' ],
			Rsvp::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			Settings::get_title(),
			Settings::get_title(),
			Settings::get_capability(),
			Settings::get_slug(),
			[ $this, 'display' ],
			Settings::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			Tools::get_title(),
			Tools::get_title(),
			Tools::get_capability(),
			Tools::get_slug(),
			[ $this, 'display' ],
			Tools::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			Addons::get_title(),
			Addons::get_title(),
			Addons::get_capability(),
			Addons::get_slug(),
			[ $this, 'display' ],
			Addons::get_priority()
		);

		add_submenu_page(
			self::SLUG,
			Welcome::get_title(),
			Welcome::get_title(),
			Welcome::get_capability(),
			Welcome::get_slug(),
			[ $this, 'display' ],
			Welcome::get_priority()
		);

		if ( ! Plugin::instance()->is_pro() ) {
			add_submenu_page(
				self::SLUG,
				esc_html__( 'Upgrade to Pro', 'sugar-calendar-lite' ),
				esc_html__( 'Upgrade to Pro', 'sugar-calendar-lite' ),
				'manage_options',
				esc_url(
					Helpers::get_upgrade_link(
						[
							'medium'  => 'admin-menu',
							'content' => 'Upgrade to Pro',
						]
					)
				)
			);
		}

		/**
		 * Action to add a new submenu page.
		 *
		 * @since 3.7.0
		 *
		 * @param Area $area Admin area instance.
		 */
		do_action( 'sugar_calendar_admin_area_add_submenu_page', $this );
	}

	/**
	 * Force the selection of our parent menu item,
	 * when applicable.
	 *
	 * @since 3.0.0
	 *
	 * @param string $parent_file Parent menu item.
	 *
	 * @return mixed|string
	 */
	public function force_parent_menu( $parent_file ) {

		if ( ! $this->is_page() ) {
			return $parent_file;
		}

		return self::SLUG;
	}

	/**
	 * Force the selection of a child menu item,
	 * when applicable.
	 *
	 * @since 3.0.0
	 *
	 * @param string $submenu_file Child menu item.
	 *
	 * @return mixed|string|null
	 */
	public function force_sub_menu( $submenu_file ) {

		// Remove hidden pages submenus.
		foreach ( $this->get_pages() as $page ) {

			if ( ! $page::has_menu_item() ) {
				remove_submenu_page( self::SLUG, $page::get_slug() );
			}
		}

		$current_page = $this->get_page( $this->get_current_page_id() );

		// Bail if it's an unrelated page.
		if ( $current_page === null ) {
			return $submenu_file;
		}

		// Force alternative menu highlight.
		if ( $current_page::highlight_menu_item() !== null ) {
			return $current_page::highlight_menu_item();
		}

		return $submenu_file;
	}

	/**
	 * Get the current page identifier.
	 *
	 * @since 3.0.0
	 * @since 3.3.0 Added support for Tools page and its tabs.
	 * @since 3.5.0 Added support for Venues page.
	 * @since 3.7.0 Added support for RSVP page.
	 *
	 * @return string|null
	 */
	private function get_current_page_id() { //phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded,Generic.Metrics.NestingLevel.MaxExceeded

		global $pagenow, $typenow, $taxnow;

		$page_id = null;

		if ( ! WP::is_doing_ajax() ) {

			if (
				$pagenow === 'post-new.php'
				&& $typenow === sugar_calendar_get_event_post_type_id()
			) {
				// New event page.
				$page_id = 'event_new';
			}

			if (
				$pagenow === 'post.php'
				&& isset( $_GET['post'] )
				&& get_post_type( $_GET['post'] ) === sugar_calendar_get_event_post_type_id() // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			) {
				// Edit event page.
				$page_id = 'event_edit';
			}

			if (
				$pagenow === 'edit-tags.php'
				&& $taxnow === sugar_calendar_get_calendar_taxonomy_id()
			) {
				// Calendars page.
				$page_id = 'calendars';
			}

			if ( isset( $_GET['page'] ) ) {

				// Custom plugin pages.
				switch ( $_GET['page'] ) {
					case Welcome::get_slug():
						$page_id = 'welcome';
						break;

					case Settings::get_slug():
						$page_id = $this->get_settings_page_id();
						break;

					case Events::get_slug():
						$page_id = 'events';
						break;

					case CalendarNew::get_slug():
						$page_id = 'calendar_new';
						break;

					case CalendarEdit::get_slug():
						$page_id = 'calendar_edit';
						break;

					case Tools::get_slug():
						$page_id = $this->get_tools_page_id();
						break;

					case Venues::get_slug():
						$page_id = 'venues';
						break;

					case Speakers::get_slug():
						$page_id = 'speakers';
						break;

					case Rsvp::get_slug():
						$page_id = 'rsvp';
						break;

					case Addons::get_slug():
						$page_id = 'addons';
						break;
				}
			}

		} elseif ( WP::is_doing_ajax() && isset( $_REQUEST['page_id'] ) ) {

			// Ajax requests.
			$page_id = sanitize_key( $_REQUEST['page_id'] );
		}

		/**
		 * Filters the current page id.
		 *
		 * @since 3.0.0
		 *
		 * @param string|null $page_id Current page id.
		 */
		$page_id = apply_filters( 'sugar_calendar_admin_area_current_page_id', $page_id );

		return $page_id;
	}

	/**
	 * Get the settings page id/tab.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	private function get_settings_page_id() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		if ( empty( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'settings_general';
		}

		// phpcs:disable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		switch ( $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			case 'general':
				return 'settings_general';

			case 'feeds':
				return 'settings_feeds';

			case 'maps':
				return 'settings_maps';

			case 'misc':
				return 'settings_misc';

			default:
				return 'settings';
		}
		// phpcs:enable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement
	}

	/**
	 * Get the tools page id/tab.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	private function get_tools_page_id() {

		if ( empty( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'tools_import';
		}

		// phpcs:disable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		switch ( $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			case 'import':
				return 'tools_import';

			case 'export':
				return 'tools_export';

			case 'migrate':
				return 'tools_migrate';

			default:
				return 'tools';
		}
		// phpcs:enable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement
	}

	/**
	 * Get the list of registered page classes.
	 *
	 * @since 3.0.0
	 * @since 3.5.0 Added support for Venues page.
	 * @since 3.7.0 Added support for RSVP page.
	 *
	 * @return PageInterface[]
	 */
	private function get_pages() {

		$pages = [
			'welcome'          => Welcome::class,
			'events'           => Events::class,
			'event_new'        => EventNew::class,
			'event_edit'       => EventEdit::class,
			'calendars'        => Calendars::class,
			'calendar_new'     => CalendarNew::class,
			'calendar_edit'    => CalendarEdit::class,
			'tags'             => Tags::class,
			'settings'         => Settings::class,
			'settings_general' => SettingsGeneralTab::class,
			'settings_feeds'   => SettingsFeedsTab::class,
			'settings_maps'    => SettingsMapsTab::class,
			'settings_misc'    => SettingsMiscTab::class,
			'tools'            => Tools::class,
			'tools_import'     => ToolsImportTab::class,
			'tools_export'     => ToolsExportTab::class,
			'tools_migrate'    => ToolsMigrateTab::class,
			'venues'           => Venues::class,
			'speakers'         => Speakers::class,
			'rsvp'             => Rsvp::class,
			'addons'           => Addons::class,
		];

		/**
		 * Filter the list of registered page classes.
		 *
		 * @since 3.0.0
		 *
		 * @param PageInterface[] $pages Page classes.
		 */
		$pages = apply_filters( 'sugar_calendar_admin_area_pages', $pages );

		return $pages;
	}

	/**
	 * Check if the current page matches the provided id.
	 *
	 * @since 3.0.0
	 *
	 * @param string $id Page id.
	 *
	 * @return bool
	 */
	public function is_page( $id = null ) {

		if ( $id === null ) {
			return $this->get_current_page_id() !== null;
		}

		return $id === $this->get_current_page_id();
	}

	/**
	 * Get a page class by its id.
	 *
	 * @since 3.0.0
	 *
	 * @param string $id Page id.
	 *
	 * @return PageInterface|null
	 */
	public function get_page( $id ) {

		$pages = $this->get_pages();

		return $pages[ $id ] ?? null;
	}

	/**
	 * Get a page URL by its id.
	 *
	 * @since 3.0.0
	 *
	 * @param string $id Page id.
	 *
	 * @return string|null
	 */
	public function get_page_url( $id = 'settings' ) {

		$pages = $this->get_pages();
		$page  = $pages[ $id ] ?? null;

		return $page::get_url() ?? null;
	}

	/**
	 * Builds an AJAX url for the current page.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function ajax_url() {

		return add_query_arg(
			[
				'_wpnonce' => wp_create_nonce( self::SLUG ),
				'action'   => self::SLUG,
				'page_id'  => $this->get_current_page_id(),
			],
			WP::admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Initialize the current page.
	 *
	 * @since 3.0.0
	 */
	public function init() {

		$page = $this->get_page( $this->get_current_page_id() );

		// Bail if the page doesn't exist.
		if ( $page === null ) {
			return;
		}

		$this->current_page = new $page();

		$this->current_page->hooks();
	}

	/**
	 * Welcome screen redirect.
	 *
	 * This function checks if a new install just occurred. If so,
	 * then we redirect the user to the appropriate page.
	 *
	 * @since 3.0.0
	 */
	public function maybe_redirect_welcome() {

		// Check if we should consider redirection.
		if ( ! get_transient( self::TRANSIENT_REDIRECT ) ) {
			return;
		}

		// If we are redirecting, clear the transient, so it only happens once.
		delete_transient( self::TRANSIENT_REDIRECT );

		// Check option to disable welcome redirect.
		if ( get_option( self::OPTION_REDIRECT, false ) ) {
			return;
		}

		// Only do this for single site installs.
		if ( isset( $_GET['activate-multi'] ) || is_network_admin() ) { // WPCS: CSRF ok.
			return;
		}

		wp_safe_redirect( sugar_calendar()->get_setup_wizard()->get_url() );
		exit;
	}

	/**
	 * Display the current page.
	 *
	 * @since 3.0.0
	 */
	public function display() {

		// Bail if the page doesn't exist.
		if ( $this->current_page === null ) {
			return;
		}

		$this->current_page->display();
	}

	/**
	 * Handle POST requests.
	 *
	 * @since 3.0.0
	 */
	public function handle_post() {

		// Bail if it's an AJAX request.
		if ( WP::is_doing_ajax() ) {
			return;
		}

		// Bail if the page doesn't exist.
		if ( $this->current_page === null ) {
			return;
		}

		// Bail if post data is empty.
		if ( empty( $_POST ) ) {
			return;
		}

		// Bail if we're not submitting a form.
		if ( ! isset( $_POST[ self::SLUG . '-submit' ] ) ) {
			return;
		}

		// Bail if request checks fail.
		if ( ! check_admin_referer( self::SLUG ) ) {
			return;
		}

		// Handle legacy settings.
		$this->handle_legacy_settings_post();

		$post_data = $_POST[ self::SLUG ] ?? []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Let the current page handle the request.
		$this->current_page->handle_post( $post_data );

		add_action( 'admin_notices', [ $this, 'display_admin_notices' ], 5 );

		// Let 3rd party code handle the request.
		do_action( 'sugar_calendar_admin_area_handle_post', $post_data );
	}

	/**
	 * Display admin notices.
	 *
	 * @since 3.1.2
	 */
	public function display_admin_notices() {

		WP::display_admin_notices();
	}

	/**
	 * Handle post data coming from legacy settings screen.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function handle_legacy_settings_post() {

		$settings = array_filter(
			get_registered_settings(),
			fn( $key ) => ( strpos( $key, 'sc_' ) === 0 ),
			ARRAY_FILTER_USE_KEY
		);

		foreach ( $settings as $setting => $args ) {
			$value = $args['default'] ?? false;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$value = $_POST[ $setting ] ?? $value;

			update_option( $setting, $value );
		}
	}

	/**
	 * Handle AJAX requests.
	 *
	 * @since 3.0.0
	 */
	public function handle_ajax() {

		// Bail if the page doesn't exist.
		if ( $this->current_page === null ) {
			wp_send_json_error();
		}

		// Bail if request can't be trusted.
		if ( ! check_ajax_referer( self::SLUG ) ) {
			wp_send_json_error();
		}

		$task = null;

		if ( isset( $_REQUEST['task'] ) ) {
			$task = wp_unslash( sanitize_key( $_REQUEST['task'] ) );
		}

		// Bail if no task is defined.
		if ( empty( $task ) ) {
			wp_send_json_error();
		}

		/**
		 * Fire a plugin-wide ajax action.
		 *
		 * @since 3.0.0
		 *
		 * @param string $task Task slug.
		 */
		do_action( 'sugar_calendar_ajax', $task ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		/**
		 * Fire a task-specific ajax action.
		 *
		 * @since 3.0.0
		 */
		do_action( "sugar_calendar_ajax_{$task}" ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Add the plugin body class.
	 *
	 * @since 3.0.0
	 *
	 * @param string $class Body class.
	 *
	 * @return string
	 */
	public function body_class( $class = '' ) {

		// Bail if not in an admin page.
		if ( $this->current_page === null ) {
			return $class;
		}

		$class .= ' sugar-calendar';

		if ( Plugin::instance()->is_pro() ) {
			$class .= " {$class}-pro";
		} else {
			$class .= " {$class}-lite";
		}

		return $class;
	}

	/**
	 * Display the admin header.
	 *
	 * @since 3.0.0
	 */
	public function display_admin_header() {

		// Bail if not in an admin page.
		if ( $this->current_page === null ) {
			return;
		}

		UI::header();
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 * @since 3.3.0 Add wp-date script as a dependency for admin-event-meta-box script.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_assets( $hook ) {

		wp_enqueue_style(
			'sugar-calendar-admin-menu',
			SC_PLUGIN_ASSETS_URL . 'css/admin-menu' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		// Dependencies.
		wp_register_style(
			'sugar-calendar-vendor-jquery-confirm',
			SC_PLUGIN_ASSETS_URL . 'lib/jquery-confirm/jquery-confirm.min.css',
			[],
			'3.3.4'
		);

		wp_register_script(
			'sugar-calendar-vendor-jquery-confirm',
			SC_PLUGIN_ASSETS_URL . 'lib/jquery-confirm/jquery-confirm.min.js',
			[ 'jquery' ],
			'3.3.4'
		);

		wp_register_script(
			'sugar-calendar-vendor-choices',
			SC_PLUGIN_ASSETS_URL . 'lib/choices.min.js',
			[],
			'9.0.1'
		);

		wp_register_style(
			'sugar-calendar-vendor-lity',
			SC_PLUGIN_ASSETS_URL . 'lib/lity/lity.min.css',
			[],
			'3.0.0'
		);

		wp_register_script(
			'sugar-calendar-vendor-lity',
			SC_PLUGIN_ASSETS_URL . 'lib/lity/lity.min.js',
			[],
			'3.0.0'
		);

		wp_register_style(
			'sugar-calendar-admin-confirm',
			SC_PLUGIN_ASSETS_URL . 'css/admin-alerts' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		// Admin assets.
		wp_register_style(
			'sugar-calendar-admin-settings',
			SC_PLUGIN_ASSETS_URL . 'css/admin-settings' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		wp_register_style(
			'sugar-calendar-admin-education',
			SC_PLUGIN_ASSETS_URL . 'css/admin-education' . WP::asset_min() . '.css',
			[ 'sugar-calendar-vendor-lity' ],
			BaseHelpers::get_asset_version()
		);

		wp_register_script(
			'sugar-calendar-admin-education',
			SC_PLUGIN_ASSETS_URL . 'js/admin-education' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-lity' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_register_style(
			'sugar-calendar-admin-event-meta-box',
			SC_PLUGIN_ASSETS_URL . 'css/admin-event-metabox' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		wp_register_script(
			'sugar-calendar-admin-event-meta-box',
			SC_PLUGIN_ASSETS_URL . 'js/admin-event-metabox' . WP::asset_min() . '.js',
			[
				'jquery',
				'jquery-ui-datepicker',
				'sugar-calendar-vendor-choices',
				'wp-date',
				'wp-i18n',
				'jquery-ui-autocomplete',
			],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_register_style(
			'sugar-calendar-admin-calendar',
			SC_PLUGIN_ASSETS_URL . 'css/admin-calendar' . WP::asset_min() . '.css',
			[ 'wp-color-picker' ],
			BaseHelpers::get_asset_version()
		);

		wp_register_script(
			'sugar-calendar-admin-calendar',
			SC_PLUGIN_ASSETS_URL . 'js/admin-calendar' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-choices', 'wp-color-picker' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_register_script(
			'sugar-calendar-admin-settings',
			SC_PLUGIN_ASSETS_URL . 'js/admin-settings' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-choices' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_enqueue_script(
			'sugar-calendar-admin-common',
			SC_PLUGIN_ASSETS_URL . 'admin/js/common' . WP::asset_min() . '.js',
			[ 'jquery' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-common',
			'sugar_calendar_admin_common',
			[
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'tags_slug' => Helpers::get_tags_slug(),
			]
		);

		// Bail if not in an admin page.
		if ( $this->current_page === null ) {
			return;
		}

		/**
		 * Fires after enqueue plugin assets.
		 *
		 * @since 3.0.0
		 *
		 * @param PageInterface $page Current page.
		 */
		do_action( 'sugar_calendar_admin_area_enqueue_assets', $this->current_page );
	}

	/**
	 * Remove 3rd party admin notices.
	 *
	 * @since 3.0.0
	 */
	public function hide_unrelated_notices() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded

		// Bail if not in an admin page.
		if ( $this->current_page === null ) {
			return;
		}

		$this->remove_unrelated_actions( 'user_admin_notices' );
		$this->remove_unrelated_actions( 'admin_notices' );
		$this->remove_unrelated_actions( 'all_admin_notices' );
		$this->remove_unrelated_actions( 'network_admin_notices' );
	}

	/**
	 * Remove 3rd party notices based on the provided action hook.
	 *
	 * @since 3.0.0
	 *
	 * @param string $action The name of the action.
	 */
	private function remove_unrelated_actions( $action ) {

		global $wp_filter;

		if ( empty( $wp_filter[ $action ]->callbacks ) || ! is_array( $wp_filter[ $action ]->callbacks ) ) {
			return;
		}

		foreach ( $wp_filter[ $action ]->callbacks as $priority => $hooks ) {
			foreach ( $hooks as $name => $arr ) {
				if (
					( // Cover object method callback case.
						is_array( $arr['function'] ) &&
						isset( $arr['function'][0] ) &&
						is_object( $arr['function'][0] ) &&
						strpos( strtolower( get_class( $arr['function'][0] ) ), 'sugar_calendar' ) !== false
					) ||
					( // Cover class static method callback case.
						! empty( $name ) &&
						strpos( strtolower( $name ), 'sugar_calendar' ) !== false
					)
				) {
					continue;
				}

				unset( $wp_filter[ $action ]->callbacks[ $priority ][ $name ] );
			}
		}
	}

	/**
	 * Register product education pages.
	 *
	 * @since 3.0.0
	 * @since 3.1.0 Remove SC Event Ticketing product education pages.
	 *
	 * @param array $pages Array of pages.
	 *
	 * @return array
	 */
	public function product_education_get_pages( $pages ) {

		if ( ! is_plugin_active( 'sc-zapier/sc-zapier.php' ) ) {
			$pages['settings_zapier'] = SettingsZapierTab::class;
		}

		return $pages;
	}

	/**
	 * Register product education page ids.
	 *
	 * @since 3.0.0
	 * @since 3.1.0 Remove SC Event Ticketing product education pages.
	 *
	 * @param string $page_id Current page id.
	 *
	 * @return null|string
	 */
	public function product_education_current_page_id( $page_id ) {

		if (
			$page_id !== 'settings' ||
			! isset( $_GET['section'] )
		) {
			return $page_id;
		}

		if (
			! is_plugin_active( 'sc-zapier/sc-zapier.php' )
			&& ( $_GET['section'] === 'zapier' )
		) {

			$page_id = 'settings_zapier';
		}

		return $page_id;
	}

	/**
	 * Register Settings page tab ids.
	 *
	 * @since 3.0.0
	 * @since 3.1.0 Remove SC Event Ticketing product education pages.
	 *
	 * @param array $tabs Array of tabs.
	 *
	 * @return array
	 */
	public function product_education_settings_page_tabs( $tabs ) {

		if ( ! is_plugin_active( 'sc-zapier/sc-zapier.php' ) ) {
			$tabs[] = 'settings_zapier';
		}

		return $tabs;
	}

	/**
	 * Whether we are on a Sugar Calendar admin page.
	 *
	 * @since 3.3.0
	 *
	 * @return bool
	 */
	public function is_sc_admin_page() {

		return ! empty( $this->current_page ) && $this->current_page instanceof PageInterface;
	}

	/**
	 * Handle the RSVP pages integration for both lite and pro
	 * but without the RSVP add-on.
	 *
	 * @since 3.7.0
	 */
	private function handle_rsvp_pages() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_filter( 'sugar_calendar_admin_area_pages', [ $this, 'product_education_get_rsvp' ] );
		add_filter( 'sugar_calendar_admin_area_current_page_id', [ $this, 'product_education_current_rsvp_page_id' ] );
		add_filter( 'sugar_calendar_admin_pages_settings_get_tabs', [ $this, 'product_education_settings_rsvp_page_tab' ] );
	}

	/**
	 * Register the RSVP education page.
	 *
	 * @since 3.7.0
	 *
	 * @param array $pages Registered pages.
	 *
	 * @return array
	 */
	public function product_education_get_rsvp( $pages ) {

		if ( ! sugar_calendar()->is_rsvp_addon_active() ) {
			$pages['settings_rsvp'] = SettingsRsvpTab::class;
		}

		return $pages;
	}

	/**
	 * Register the RSVP page ID.
	 *
	 * @since 3.7.0
	 *
	 * @param string $page_id Current page id.
	 *
	 * @return null|string
	 */
	public function product_education_current_rsvp_page_id( $page_id ) {

		if (
			$page_id === 'settings' &&
			isset( $_GET['section'] ) &&
			$_GET['section'] === 'rsvp' &&
			! sugar_calendar()->is_rsvp_addon_active()
		) {
			$page_id = 'settings_rsvp';
		}

		return $page_id;
	}

	/**
	 * Register Settings the RSVP page tab id.
	 *
	 * @since 3.7.0
	 *
	 * @param array $tabs Array of tabs.
	 *
	 * @return array
	 */
	public function product_education_settings_rsvp_page_tab( $tabs ) {

		if ( ! sugar_calendar()->is_rsvp_addon_active() ) {
			$tabs[] = 'settings_rsvp';
		}

		return $tabs;
	}
}
