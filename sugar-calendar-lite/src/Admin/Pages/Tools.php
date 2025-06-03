<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageTabAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Plugin;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Tools Page.
 *
 * @since 3.3.0
 */
class Tools extends PageTabAbstract {

	/**
	 * Register page hooks.
	 *
	 * @since 3.3.0
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'init' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Initialize page.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	public function init() {

		$section_id = static::get_tab_slug();
		$sections   = array_keys( $this->get_tabs() );

		if ( ! in_array( $section_id, $sections, true ) ) {
			wp_safe_redirect( Plugin::instance()->get_admin()->get_page_url( 'tools_import' ) );
			exit;
		}
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_register_script(
			'sugar-calendar-admin-importers',
			SC_PLUGIN_ASSETS_URL . 'admin/js/sc-admin-importers' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-jquery-confirm' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_enqueue_style( 'sugar-calendar-admin-settings' );
		wp_enqueue_style(
			'sugar-calendar-admin-tools',
			SC_PLUGIN_ASSETS_URL . 'css/admin-tools' . WP::asset_min() . '.css',
			[
				'sugar-calendar-vendor-jquery-confirm',
			],
			BaseHelpers::get_asset_version()
		);
	}

	/**
	 * Page slug.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sc-tools';
	}

	/**
	 * Page tab slug.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		if ( ! isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		return sanitize_key( $_GET['section'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Page label.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Tools', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.3.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 10;
	}

	/**
	 * Display page.
	 *
	 * @since 3.3.0
	 */
	public function display() {
		?>
		<div id="sugar-calendar-tools" class="wrap sugar-calendar-admin-wrap">

			<?php UI::tabs( $this->get_tabs(), static::get_tab_slug() ); ?>

			<div class="sugar-calendar-admin-content">
				<h1 class="screen-reader-text"><?php esc_html_e( 'Tools', 'sugar-calendar-lite' ); ?></h1>

				<?php static::display_tab(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Return the list of tabs for this page.
	 *
	 * @since 3.3.0
	 *
	 * @return array
	 */
	protected function get_tabs() {

		static $sections = null;

		if ( $sections === null ) {
			$tabs = [
				'tools_import',
				'tools_export',
			];

			if ( ToolsMigrateTab::is_migration_possible() ) {
				$tabs[] = 'tools_migrate';
			}

			/**
			 * Filter Tools page tabs.
			 *
			 * @since 3.3.0
			 *
			 * @param array $tabs Array of tabs.
			 */
			$tabs = apply_filters( 'sugar_calendar_admin_pages_tools_get_tabs', $tabs );

			// Map tab ids to their classes.
			$tabs = array_map( fn( $tab ) => Plugin::instance()->get_admin()->get_page( $tab ), $tabs );

			// Convert tabs to a format legacy navigation understands.
			$sections = array_reduce(
				$tabs,
				function ( $tabs, $tab ) {

					$tabs[ $tab::get_tab_slug() ] = [
						'name'     => $tab::get_label(),
						'url'      => $tab::get_url(),
						'priority' => $tab::get_priority(),
					];

					return $tabs;
				},
				[]
			);

			// Sort tabs by priority.
			uasort( $sections, fn( $a, $b ) => $a['priority'] <= $b['priority'] ? -1 : 1 );
		}

		return $sections;
	}

	/**
	 * Output the importer's tab.
	 *
	 * @since 3.3.0
	 */
	protected function display_importer_tab() {

		$importer = $this->get_importer();

		if ( $importer === false ) {
			esc_html_e( 'Importer not found.', 'sugar-calendar-lite' );

			return;
		}

		UI::heading(
			[
				'title' => esc_html( $importer->get_title() ),
			]
		);

		$importer->display();
	}

	/**
	 * Get the importer.
	 *
	 * @since 3.3.0
	 *
	 * @return false|Importers\ImporterInterface
	 */
	protected function get_importer() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$importer = empty( $_GET['importer'] ) ? 'sugar-calendar' : sanitize_text_field( wp_unslash( $_GET['importer'] ) );

		if ( ! array_key_exists( $importer, sugar_calendar()->get_importers()->get_loaded_importers() ) ) {
			return false;
		}

		return sugar_calendar()->get_importers()->get_loaded_importers()[ $importer ];
	}
}
