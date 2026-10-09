<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Admin\SetupChecklist\ActionRenderer;
use Sugar_Calendar\Admin\SetupChecklist\ChecklistRenderer;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\WP;

/**
 * Setup Checklist page.
 *
 * @since 3.15.0
 */
class SetupChecklist extends PageAbstract {

	/**
	 * Id shared by the page-selector trigger, its popover and the embed button.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const PAGE_SELECTOR_ID = ActionRenderer::PAGE_SELECTOR_ID;

	/**
	 * Class the submenu link carries so admin-menu.scss can target this entry.
	 *
	 * WordPress builds the `<li>` itself, so the class goes on the `$submenu` item's fifth element.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const MENU_ITEM_CLASS = 'sugar-calendar-checklist-menu-item';

	/**
	 * Page slug.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar-setup-checklist';
	}

	/**
	 * Page label.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Setup Checklist', 'sugar-calendar-lite' );
	}

	/**
	 * Menu entry markup: the label plus the last computed progress.
	 *
	 * Kept apart from get_title() so the progress bar never reaches the browser title.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public static function get_menu_title() {

		$checklist = sugar_calendar()->get_setup_checklist();

		if ( ! current_user_can( self::get_capability() ) || $checklist->get_state()->is_dismissed() ) {
			return self::get_label();
		}

		$progress = $checklist->get_progress();
		$percent  = max( 0, min( 100, (int) $progress['percent'] ) );

		return sprintf(
			'<span class="sugar-calendar-checklist-menu-title" title="%4$s">%1$s<span class="sugar-calendar-checklist-progress" aria-hidden="true"><span class="sugar-calendar-checklist-progress__fill" style="width:%2$d%%"></span></span><span class="screen-reader-text">%3$s</span></span>',
			self::get_label(),
			$percent,
			esc_html(
				sprintf(
					/* translators: %1$d - completed steps; %2$d - total steps. */
					__( '%1$d of %2$d setup steps complete', 'sugar-calendar-lite' ),
					(int) $progress['completed_count'],
					(int) $progress['total']
				)
			),
			esc_attr(
				sprintf(
					/* translators: %d - percentage of setup steps complete. */
					__( 'Setup Checklist: %d%% complete', 'sugar-calendar-lite' ),
					$percent
				)
			)
		);
	}

	/**
	 * Settle the Setup Checklist's place in the submenu and tag its link.
	 *
	 * It follows the parent's entry, not first: core links the top-level menu to the first
	 * submenu entry. admin-menu.scss lifts it above Events with flex `order` instead.
	 *
	 * @since 3.15.0
	 */
	public static function place_menu_item() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- two small scans of $submenu; splitting them would read worse.

		global $submenu;

		if ( empty( $submenu[ Area::SLUG ] ) ) {
			return;
		}

		$items = $submenu[ Area::SLUG ];
		$ours  = null;

		foreach ( $items as $key => $item ) {
			if ( isset( $item[2] ) && $item[2] === self::get_slug() ) {
				$ours = $item;

				unset( $items[ $key ] );

				break;
			}
		}

		// Absent for anyone without the capability to see it.
		if ( $ours === null ) {
			return;
		}

		if ( sugar_calendar()->get_setup_checklist()->get_state()->is_dismissed() ) {
			$submenu[ Area::SLUG ] = array_values( $items ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			return;
		}

		$ours[4] = empty( $ours[4] )
			? self::MENU_ITEM_CLASS
			: $ours[4] . ' ' . self::MENU_ITEM_CLASS;

		$items    = array_values( $items );
		$position = 1;

		foreach ( $items as $key => $item ) {
			if ( isset( $item[2] ) && $item[2] === Area::SLUG ) {
				$position = $key + 1;

				break;
			}
		}

		array_splice( $items, $position, 0, [ $ours ] );

		$submenu[ Area::SLUG ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Redirect a dismissed checklist before admin output starts.
	 *
	 * @since 3.15.0
	 */
	public function early_init() {

		if ( ! current_user_can( self::get_capability() ) || ! $this->is_dismissed() ) {
			return;
		}

		wp_safe_redirect( SettingsGeneralTab::get_url() );
		exit;
	}

	/**
	 * Page menu priority.
	 *
	 * Markup position only; place_menu_item() sets the final slot.
	 *
	 * @since 3.15.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 1;
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.15.0
	 */
	public function hooks() {

		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.15.0
	 */
	public function enqueue_assets() {

		$data = sugar_calendar()->get_setup_checklist()->get_view_data();

		wp_enqueue_style(
			'sugar-calendar-admin-setup-checklist',
			SC_PLUGIN_ASSETS_URL . 'css/admin-setup-checklist' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_style( 'sugar-calendar-admin-confirm' );

		wp_enqueue_script(
			'sugar-calendar-admin-setup-checklist',
			SC_PLUGIN_ASSETS_URL . 'js/admin-setup-checklist' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-jquery-confirm' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-setup-checklist',
			'sugar_calendar_admin_setup_checklist',
			[
				'ajax_url'      => WP::admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'sugar_calendar_setup_checklist' ),
				'page_edit_url' => WP::admin_url( 'post.php' ),
				'snapshot'      => [
					'visible_items'   => array_values( (array) ( $data['visible_items'] ?? [] ) ),
					'completed_items' => array_values( (array) ( $data['completed_items'] ?? [] ) ),
					'is_complete'     => ! empty( $data['is_complete'] ),
					'dismissed'       => false,
				],
				'dismiss'       => [
					'title'   => esc_html__( 'Are you sure?', 'sugar-calendar-lite' ),
					'message' => esc_html__( 'This will permanently dismiss the Setup Checklist, and you will not be able to bring it back. This cannot be undone.', 'sugar-calendar-lite' ),
					'confirm' => esc_html__( 'Yes, dismiss', 'sugar-calendar-lite' ),
					'cancel'  => esc_html__( 'Cancel', 'sugar-calendar-lite' ),
				],
				'text'          => [
					'activate'       => esc_html__( 'Activate', 'sugar-calendar-lite' ),
					'activating'     => esc_html__( 'Activating...', 'sugar-calendar-lite' ),
					'edit_page'      => esc_html__( 'Edit this page normally.', 'sugar-calendar-lite' ),
					'install'        => esc_html__( 'Install', 'sugar-calendar-lite' ),
					'installed'      => esc_html__( 'Installed', 'sugar-calendar-lite' ),
					'installing'     => esc_html__( 'Installing..', 'sugar-calendar-lite' ),
					'loading_pages'  => esc_html__( 'Loading pages...', 'sugar-calendar-lite' ),
					'request_failed' => esc_html__( 'The request failed. Please try again.', 'sugar-calendar-lite' ),
				],
			]
		);
	}

	/**
	 * Display the page's title bar.
	 *
	 * @since 3.15.0
	 */
	public function display_admin_subheader() {

		?>
		<div class="sugar-calendar-admin-subheader">
			<h4><?php esc_html_e( 'Complete Sugar Calendar Setup Checklist', 'sugar-calendar-lite' ); ?></h4>
		</div>
		<?php
	}

	/**
	 * Display page.
	 *
	 * @since 3.15.0
	 */
	public function display() {

		// No dismissal check: early_init() already redirected a dismissed checklist in this request.
		if ( ! current_user_can( self::get_capability() ) ) {
			return;
		}

		/**
		 * Fires when the authorized, non-dismissed checklist page renders.
		 *
		 * @since 3.15.0
		 */
		do_action( 'sugar_calendar_setup_checklist_viewed' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.

		$data = sugar_calendar()->get_setup_checklist()->get_view_data();

		( new ChecklistRenderer() )->render( $data, self::get_label() );
	}

	/**
	 * Whether fresh site state contains a dismissal record.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function is_dismissed() {

		$dismissal = sugar_calendar()->get_setup_checklist()->get_state()->get_fresh_dismissal();

		// A failed read renders the page: this guard only decides whether to redirect.
		return ! is_wp_error( $dismissal ) && $dismissal !== null;
	}
}
