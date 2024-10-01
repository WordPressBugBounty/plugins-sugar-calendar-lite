<?php

namespace Sugar_Calendar\Admin;

use Sugar_Calendar\Admin\Events\Events as AdminEvents;
use Sugar_Calendar\Admin\Events\Metaboxes;
use Sugar_Calendar\Admin\Pages\CalendarEdit;
use Sugar_Calendar\Admin\Pages\CalendarNew;
use Sugar_Calendar\Admin\Pages\Calendars;
use Sugar_Calendar\Admin\Pages\EventEdit;
use Sugar_Calendar\Admin\Pages\EventNew;
use Sugar_Calendar\Admin\Pages\Events;
use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Admin\Pages\SettingsFeedsTab;
use Sugar_Calendar\Admin\Pages\SettingsGeneralTab;
use Sugar_Calendar\Admin\Pages\SettingsMapsTab;
use Sugar_Calendar\Admin\Pages\SettingsMiscTab;
use Sugar_Calendar\Admin\Pages\SettingsZapierTab;
use Sugar_Calendar\Admin\Pages\Welcome;
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
	 * Register hooks.
	 *
	 * @since 3.0.0
	 * @since 3.2.0 Added the Admin\Events hooks.
	 *
	 * @return void
	 */
	public function hooks() {

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
	}

	/**
	 * Add admin area menu items.
	 *
	 * @since 3.0.0
	 * @since 3.0.1 Apply filter on Sugar Calendar Menu capability.
	 *
	 * @return void
	 */
	public function admin_menu() {

		add_menu_page(
			esc_html__( 'Sugar Calendar', 'sugar-calendar' ),
			esc_html__( 'Sugar Calendar', 'sugar-calendar' ),
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
			Settings::get_title(),
			Settings::get_title(),
			Settings::get_capability(),
			Settings::get_slug(),
			[ $this, 'display' ],
			Settings::get_priority()
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
				esc_html__( 'Upgrade to Pro', 'sugar-calendar' ),
				esc_html__( 'Upgrade to Pro', 'sugar-calendar' ),
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

					case 'sc-settings':
						$page_id = 'settings';
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
				}

				// Settings page tabs.
				if ( $page_id === 'settings' ) {

					$section = $_GET['section'] ?? 'general'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

					switch ( $section ) {
						case 'general':
							$page_id = 'settings_general';
							break;

						case 'feeds':
							$page_id = 'settings_feeds';
							break;

						case 'maps':
							$page_id = 'settings_maps';
							break;

						case 'misc':
							$page_id = 'settings_misc';
							break;
					}
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
	 * Get the list of registered page classes.
	 *
	 * @since 3.0.0
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
			'settings'         => Settings::class,
			'settings_general' => SettingsGeneralTab::class,
			'settings_feeds'   => SettingsFeedsTab::class,
			'settings_maps'    => SettingsMapsTab::class,
			'settings_misc'    => SettingsMiscTab::class,
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

		wp_safe_redirect( Welcome::get_url() );
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
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_assets( $hook ) {

		wp_enqueue_style(
			'sugar-calendar-admin-menu',
			SC_PLUGIN_ASSETS_URL . 'css/admin-menu' . WP::asset_min() . '.css',
			[],
			SC_PLUGIN_VERSION
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
			SC_PLUGIN_VERSION
		);

		// Admin assets.
		wp_register_style(
			'sugar-calendar-admin-settings',
			SC_PLUGIN_ASSETS_URL . 'css/admin-settings' . WP::asset_min() . '.css',
			[],
			SC_PLUGIN_VERSION
		);

		wp_register_style(
			'sugar-calendar-admin-education',
			SC_PLUGIN_ASSETS_URL . 'css/admin-education' . WP::asset_min() . '.css',
			[ 'sugar-calendar-vendor-lity' ],
			SC_PLUGIN_VERSION
		);

		wp_register_script(
			'sugar-calendar-admin-education',
			SC_PLUGIN_ASSETS_URL . 'js/admin-education' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-lity' ],
			SC_PLUGIN_VERSION,
			true
		);

		wp_register_style(
			'sugar-calendar-admin-event-meta-box',
			SC_PLUGIN_ASSETS_URL . 'css/admin-event-metabox' . WP::asset_min() . '.css',
			[],
			SC_PLUGIN_VERSION
		);

		wp_register_script(
			'sugar-calendar-admin-event-meta-box',
			SC_PLUGIN_ASSETS_URL . 'js/admin-event-metabox' . WP::asset_min() . '.js',
			[ 'jquery', 'jquery-ui-datepicker', 'sugar-calendar-vendor-choices' ],
			SC_PLUGIN_VERSION,
			true
		);

		wp_register_style(
			'sugar-calendar-admin-calendar',
			SC_PLUGIN_ASSETS_URL . 'css/admin-calendar' . WP::asset_min() . '.css',
			[ 'wp-color-picker' ],
			SC_PLUGIN_VERSION
		);

		wp_register_script(
			'sugar-calendar-admin-calendar',
			SC_PLUGIN_ASSETS_URL . 'js/admin-calendar' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-choices', 'wp-color-picker' ],
			SC_PLUGIN_VERSION,
			true
		);

		wp_register_script(
			'sugar-calendar-admin-settings',
			SC_PLUGIN_ASSETS_URL . 'js/admin-settings' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-choices' ],
			SC_PLUGIN_VERSION,
			true
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

		if ( $page_id === 'settings' && isset( $_GET['section'] ) ) {

			if (
				! is_plugin_active( 'sc-zapier/sc-zapier.php' )
				&& ( $_GET['section'] === 'zapier' )
			) {

				$page_id = 'settings_zapier';
			}
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
}
