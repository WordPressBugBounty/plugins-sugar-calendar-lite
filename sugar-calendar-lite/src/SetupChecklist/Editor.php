<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Admin\Pages\EventNew;
use Sugar_Calendar\Common\Editor as CommonEditor;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\ActionResult;
use Sugar_Calendar\Helpers\WP;
use WP_Error;
use WP_Post;

/**
 * Open the native event and page editors from Setup Checklist links.
 *
 * @since 3.15.0
 */
class Editor {

	/**
	 * Query arguments carried by checklist editor links.
	 *
	 * @since 3.15.0
	 */
	private const ACTION_ARG = 'sc_setup_checklist_action';
	private const NONCE_ARG  = 'sc_setup_checklist_nonce';

	/**
	 * Calendar block identity.
	 *
	 * @since 3.15.0
	 */
	private const BLOCK_NAME = 'sugar-calendar/block';

	/**
	 * Validated page selected for this request.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private $page_id = 0;

	/**
	 * Error notice for the selected page's editor screen.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $page_error = '';

	/**
	 * Ordinary page edit URL used for recovery.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $page_edit_url = '';

	/**
	 * Register hooks.
	 *
	 * @since 3.15.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_setup_checklist_event_url', [ $this, 'get_event_url' ] );
		add_filter( 'sugar_calendar_setup_checklist_page_url', [ $this, 'get_page_url' ], 10, 2 );
		add_action( 'load-post.php', [ $this, 'prepare_page_handoff' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'admin_notices', [ $this, 'display_page_error' ] );
		add_filter( 'sugar_calendar_admin_pages_event_abstract_should_display_hand_holding', [ $this, 'force_guide_display' ] );
		add_filter( 'sugar_calendar_admin_pages_event_abstract_hand_holding', [ $this, 'force_guide_start' ], 20 );
		add_filter( 'sugar_calendar_setup_checklist_item_action', [ $this, 'filter_item_action' ], 10, 5 );
	}

	/**
	 * Claim the embed-calendar and create-event rows, whose clicks open an editor hand-off.
	 *
	 * @since 3.15.0
	 *
	 * @param array|WP_Error $result     Shared fallback result.
	 * @param string         $item_id    Canonical checklist item ID.
	 * @param string         $return_url Local admin return URL.
	 * @param array          $item       The row as presented.
	 * @param array          $input      Validated row input; `page` for embed-calendar.
	 *
	 * @return array|WP_Error
	 */
	public function filter_item_action( $result, $item_id, $return_url, $item, $input ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- filter contract supplies the URL and row before the input.

		if ( $item_id === Config::ITEM_EMBED_CALENDAR ) {
			return ActionResult::navigation( $this->get_page_handoff_url( $input['page'] ) );
		}

		if ( $item_id === Config::ITEM_CREATE_EVENT ) {
			return ActionResult::navigation( $this->get_event_handoff_url() );
		}

		return $result;
	}

	/**
	 * Return the native event URL, or the Editor's nonce-bound guide URL when it filters one in.
	 *
	 * @since 3.15.0
	 *
	 * @return string|WP_Error
	 */
	public function get_event_handoff_url() {

		$native_url = admin_url( EventNew::get_slug() );

		/**
		 * Filters the event editor URL opened from the checklist.
		 *
		 * @since 3.15.0
		 *
		 * @param string $url Native event creation URL.
		 */
		$url = apply_filters( 'sugar_calendar_setup_checklist_event_url', $native_url ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- the checklist Editor filters in its nonce-bound guide URL.

		return $this->validate_admin_handoff_url( $url, $native_url );
	}

	/**
	 * Return the validated editor URL for a selected page.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_Post $page Editable saved page.
	 *
	 * @return string|WP_Error
	 */
	public function get_page_handoff_url( WP_Post $page ) {

		$native_url = $this->get_native_page_url( $page->ID );

		/**
		 * Filters the page editor URL opened from the checklist.
		 *
		 * @since 3.15.0
		 *
		 * @param string $url     Native page edit URL.
		 * @param int    $page_id Validated page ID.
		 */
		$url = apply_filters( 'sugar_calendar_setup_checklist_page_url', $native_url, $page->ID ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- the checklist Editor filters in its nonce-bound page handoff URL.

		return $this->validate_admin_handoff_url( $url, $native_url );
	}

	/**
	 * Show the classic guide for a validated checklist launch.
	 *
	 * @since 3.15.0
	 *
	 * @param bool $display Whether the guide is shown.
	 *
	 * @return bool
	 */
	public function force_guide_display( $display ) {

		return self::is_guide_launch() ? true : $display;
	}

	/**
	 * Start the classic guide from its first step for a validated checklist launch.
	 *
	 * Runs after Pro's data filter so the relaunch wins over a stored status.
	 *
	 * @since 3.15.0
	 *
	 * @param array $data Hand holding JS data.
	 *
	 * @return array
	 */
	public function force_guide_start( $data ) {

		if ( self::is_guide_launch() ) {
			$data['status'] = 'start';
		}

		return $data;
	}

	/**
	 * Add a validated guide launch to the native classic event URL.
	 *
	 * @since 3.15.0
	 *
	 * @param string|WP_Error $url Native event creation URL.
	 *
	 * @return string|WP_Error
	 */
	public function get_event_url( $url ) {

		if ( is_wp_error( $url ) || CommonEditor\current() === 'block' ) {
			return $url;
		}

		if ( ! is_string( $url ) || $url === '' ) {
			return $this->handoff_error();
		}

		if ( ! self::can_create_event() || ! self::has_active_checklist() ) {
			return $this->handoff_error();
		}

		return add_query_arg(
			[
				self::ACTION_ARG => Config::ITEM_CREATE_EVENT,
				self::NONCE_ARG  => wp_create_nonce( self::get_nonce_action( Config::ITEM_CREATE_EVENT ) ),
			],
			$url
		);
	}

	/**
	 * Add a page-bound launch to a validated native edit URL.
	 *
	 * @since 3.15.0
	 *
	 * @param string|WP_Error $url     Native page edit URL.
	 * @param int             $post_id Selected page ID.
	 *
	 * @return string|WP_Error
	 */
	public function get_page_url( $url, $post_id ) {

		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$post_id = absint( $post_id );
		$page    = $this->get_editable_page( $post_id );

		if ( ! is_string( $url ) || $url === '' || is_wp_error( $page ) || ! self::has_active_checklist() ) {
			return $this->handoff_error();
		}

		return add_query_arg(
			[
				self::ACTION_ARG => Config::ITEM_EMBED_CALENDAR,
				self::NONCE_ARG  => wp_create_nonce( self::get_nonce_action( Config::ITEM_EMBED_CALENDAR, $post_id ) ),
			],
			$url
		);
	}

	/**
	 * Whether this request is an authorized classic checklist guide launch.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public static function is_guide_launch() {

		global $pagenow;

		if (
			CommonEditor\current() === 'block' ||
			$pagenow !== 'post-new.php' ||
			self::get_query_string( self::ACTION_ARG ) !== Config::ITEM_CREATE_EVENT ||
			self::get_query_string( 'post_type' ) !== sugar_calendar_get_event_post_type_id() ||
			! self::can_create_event() ||
			! self::has_active_checklist()
		) {
			return false;
		}

		$nonce = self::get_query_string( self::NONCE_ARG );

		return $nonce !== '' && wp_verify_nonce( $nonce, self::get_nonce_action( Config::ITEM_CREATE_EVENT ) ) !== false;
	}

	/**
	 * Validate the selected page after core resolves its editor screen.
	 *
	 * @since 3.15.0
	 */
	public function prepare_page_handoff() {

		if ( self::get_query_string( self::ACTION_ARG ) !== Config::ITEM_EMBED_CALENDAR ) {
			return;
		}

		$post_id = self::get_query_int( 'post' );
		$page    = get_post( $post_id );

		if ( $page instanceof WP_Post && current_user_can( 'edit_post', $page->ID ) ) {
			$this->page_edit_url = $this->get_native_page_url( $page->ID );
		}

		if ( ! $this->is_authorized_page_request( $post_id, $page ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! $screen->is_block_editor() || ! use_block_editor_for_post( $page ) ) {
			$this->page_error = __( 'This page does not use the block editor, so Sugar Calendar did not add the calendar block.', 'sugar-calendar-lite' );

			return;
		}

		if ( function_exists( 'wp_check_post_lock' ) && wp_check_post_lock( $page ) ) {
			$this->page_error = __( 'This page is being edited by another user, so Sugar Calendar did not add the calendar block.', 'sugar-calendar-lite' );

			return;
		}

		$this->page_id = $page->ID;
	}

	/**
	 * Enqueue the block-insertion script only in the validated page's block editor.
	 *
	 * @since 3.15.0
	 */
	public function enqueue_editor_assets() {

		global $post;

		if (
			$this->page_id < 1 ||
			! $post instanceof WP_Post ||
			$post->ID !== $this->page_id ||
			$post->post_type !== 'page' ||
			! use_block_editor_for_post( $post )
		) {
			return;
		}

		wp_enqueue_script(
			'sugar-calendar-admin-setup-checklist-editor',
			SC_PLUGIN_ASSETS_URL . 'js/admin-setup-checklist-editor' . WP::asset_min() . '.js',
			[ 'wp-data', 'wp-blocks', 'wp-dom-ready', 'wp-notices' ],
			Helpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-setup-checklist-editor',
			'sugar_calendar_admin_setup_checklist_editor',
			[
				'page_id'       => $post->ID,
				'block_name'    => self::BLOCK_NAME,
				'error_text'    => __( 'Sugar Calendar could not add the calendar block. Open this page normally and try again.', 'sugar-calendar-lite' ),
				'recovery_text' => __( 'Open the page editor', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * Show the error notice with a plain edit link when the block could not be added.
	 *
	 * @since 3.15.0
	 */
	public function display_page_error() {

		if ( $this->page_error === '' || $this->page_edit_url === '' ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<?php echo esc_html( $this->page_error ); ?>
				<a href="<?php echo esc_url( $this->page_edit_url ); ?>"><?php esc_html_e( 'Open the page editor', 'sugar-calendar-lite' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Whether the page editor request carries a valid checklist link.
	 *
	 * @since 3.15.0
	 *
	 * @param int           $post_id Requested page ID.
	 * @param WP_Post|mixed $page    Requested post object.
	 *
	 * @return bool
	 */
	private function is_authorized_page_request( $post_id, $page ) {

		if (
			self::get_query_string( 'action' ) !== 'edit' ||
			! $page instanceof WP_Post ||
			$page->ID !== $post_id ||
			$page->post_type !== 'page' ||
			! in_array( $page->post_status, Config::SAVED_POST_STATUSES, true ) ||
			! current_user_can( 'manage_options' ) ||
			! current_user_can( 'edit_post', $page->ID ) ||
			! self::has_active_checklist()
		) {
			return false;
		}

		$nonce = self::get_query_string( self::NONCE_ARG );

		return $nonce !== '' && wp_verify_nonce( $nonce, self::get_nonce_action( Config::ITEM_EMBED_CALENDAR, $page->ID ) ) !== false;
	}

	/**
	 * Return an editable saved block-editor page.
	 *
	 * @since 3.15.0
	 *
	 * @param int $post_id Page ID.
	 *
	 * @return WP_Post|WP_Error
	 */
	private function get_editable_page( $post_id ) {

		$page = get_post( $post_id );

		if (
			! $page instanceof WP_Post ||
			$page->post_type !== 'page' ||
			! in_array( $page->post_status, Config::SAVED_POST_STATUSES, true ) ||
			! current_user_can( 'manage_options' ) ||
			! current_user_can( 'edit_post', $page->ID ) ||
			! use_block_editor_for_post( $page )
		) {
			return $this->handoff_error();
		}

		return $page;
	}

	/**
	 * Whether the current user can create and edit events.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private static function can_create_event() {

		$post_type = get_post_type_object( sugar_calendar_get_event_post_type_id() );

		return $post_type &&
			current_user_can( 'manage_options' ) &&
			current_user_can( $post_type->cap->create_posts ) &&
			current_user_can( $post_type->cap->edit_posts );
	}

	/**
	 * Whether the checklist is still undismissed, read uncached.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private static function has_active_checklist() {

		// A failed read is a WP_Error, not null, so it refuses the hand-off.
		return sugar_calendar()->get_setup_checklist()->get_state()->get_fresh_dismissal() === null;
	}

	/**
	 * Build a nonce action bound to its selected page when applicable.
	 *
	 * @since 3.15.0
	 *
	 * @param string $action  Checklist item action.
	 * @param int    $post_id Optional page ID.
	 *
	 * @return string
	 */
	private static function get_nonce_action( $action, $post_id = 0 ) {

		return 'sugar_calendar_setup_checklist_' . $action . '_' . absint( $post_id );
	}

	/**
	 * Read a scalar query value.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Query key.
	 *
	 * @return string
	 */
	private static function get_query_string( $key ) {

		// The receiving methods verify the action-bound nonce after reading it.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	/**
	 * Read a positive scalar query ID.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Query key.
	 *
	 * @return int
	 */
	private static function get_query_int( $key ) {

		return absint( self::get_query_string( $key ) );
	}

	/**
	 * Return the native page edit URL without checklist arguments.
	 *
	 * @since 3.15.0
	 *
	 * @param int $post_id Page ID.
	 *
	 * @return string
	 */
	private function get_native_page_url( $post_id ) {

		return add_query_arg(
			[
				'post'   => absint( $post_id ),
				'action' => 'edit',
			],
			admin_url( 'post.php' )
		);
	}

	/**
	 * Reject external or non-admin URLs returned by the editor URL filters.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed  $url        Filtered editor URL.
	 * @param string $native_url Native editor URL whose path must be retained.
	 *
	 * @return string|WP_Error
	 */
	private function validate_admin_handoff_url( $url, $native_url ) {

		if ( is_wp_error( $url ) ) {
			return $url;
		}

		if ( ! is_string( $url ) || $url === '' ) {
			return $this->handoff_error();
		}

		$target = wp_parse_url( $url );
		$admin  = wp_parse_url( admin_url() );
		$native = wp_parse_url( $native_url );

		if (
			! $this->is_same_admin_origin( $target, $admin ) ||
			! is_array( $native ) ||
			( $target['path'] ?? '' ) !== ( $native['path'] ?? '' )
		) {
			return new WP_Error( 'checklist_editor_handoff_invalid', __( 'The editor link is invalid. Refresh the page and try again.', 'sugar-calendar-lite' ) );
		}

		return $url;
	}

	/**
	 * Whether a parsed URL points inside this site's admin directory.
	 *
	 * @since 3.15.0
	 *
	 * @param array|false $target Target URL parts.
	 * @param array|false $admin  Admin URL parts.
	 *
	 * @return bool
	 */
	private function is_same_admin_origin( $target, $admin ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- every comparison is part of the same URL boundary.

		return is_array( $target ) &&
			is_array( $admin ) &&
			( $target['scheme'] ?? '' ) === ( $admin['scheme'] ?? '' ) &&
			( $target['host'] ?? '' ) === ( $admin['host'] ?? '' ) &&
			(int) ( $target['port'] ?? 0 ) === (int) ( $admin['port'] ?? 0 ) &&
			strpos( $target['path'] ?? '', $admin['path'] ?? '' ) === 0;
	}

	/**
	 * Build the generic editor-link error.
	 *
	 * @since 3.15.0
	 *
	 * @return WP_Error
	 */
	private function handoff_error() {

		return new WP_Error( 'checklist_editor_handoff_failed', __( 'The editor could not be opened. Refresh the page and try again.', 'sugar-calendar-lite' ) );
	}
}
