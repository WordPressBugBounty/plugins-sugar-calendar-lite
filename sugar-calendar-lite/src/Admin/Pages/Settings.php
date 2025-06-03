<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Admin\Pages\Settings\EmailsConfigTab;
use Sugar_Calendar\Admin\PageTabAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Plugin;
use function Sugar_Calendar\Admin\Settings\get_sections;
use function Sugar_Calendar\Admin\Settings\get_subsection;
use function Sugar_Calendar\Admin\Settings\get_subsections;

/**
 * Settings tab. Handles any settings screen
 * that's not handled by a dedicated tab class.
 *
 * @since 3.0.0
 */
class Settings extends PageTabAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugarcalendar-settings';
	}

	/**
	 * Page tab slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		if ( ! isset( $_GET['section'] ) ) {
			return null;
		}

		return sanitize_key( $_GET['section'] );
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Settings', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 10;
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'init' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Initialize page.
	 *
	 * @since 3.0.0
	 * @since 3.7.0 Added Emails configuration section.
	 *
	 * @return void
	 */
	public function init() {

		$section_id = static::get_tab_slug();
		$sections   = array_keys( $this->get_tabs() );

		if ( ! in_array( $section_id, $sections ) ) {
			wp_safe_redirect( Plugin::instance()->get_admin()->get_page_url( 'settings_general' ) );
			exit;
		}

		if ( $section_id === 'emails' ) {
			( new EmailsConfigTab() )->hooks();
		}
	}

	/**
	 * Display page.
	 *
	 * @since 3.0.0
	 */
	public function display() {
		?>
		<div id="sugar-calendar-settings" class="wrap sugar-calendar-admin-wrap">

			<?php UI::tabs( $this->get_tabs(), static::get_tab_slug() ); ?>

			<div class="sugar-calendar-admin-content">
				<h1 class="screen-reader-text"><?php esc_html_e( 'Settings', 'sugar-calendar-lite' ); ?></h1>
				<form class="sugar-calendar-admin-content__settings-form" method="post" action="">

					<?php $this->display_tab( static::get_tab_slug() ); ?>

					<p class="submit">
						<?php
						UI::button(
							[
								'text' => apply_filters(
									'sugar_calendar_admin_settings_save_btn_label',
									esc_html__( 'Save Settings', 'sugar-calendar-lite' ),
									static::get_slug()
								),
							]
						);
						?>
					</p>

					<?php wp_nonce_field( Area::SLUG ); ?>

				</form>

				<?php
				// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName
				do_action( 'sugar_calendar_admin_page_after' );
				?>

			</div>
		</div>
		<?php
	}

	/**
	 * Return the list of tabs for this page.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	protected function get_tabs() {

		static $sections = null;

		if ( $sections === null ) {
			$tabs = [
				'settings_general',
				'settings_feeds',
				'settings_maps',
				'settings_misc',
			];

			/**
			 * Filter Settings page tabs.
			 *
			 * @since 3.0.0
			 *
			 * @param array $tabs Array of tabs.
			 */
			$tabs = apply_filters( 'sugar_calendar_admin_pages_settings_get_tabs', $tabs );

			// Map tab ids to their classes.
			$tabs = array_map( fn( $tab ) => Plugin::instance()->get_admin()->get_page( $tab ), $tabs );

			// Convert tabs to a format legacy navigation understands.
			$tabs = array_reduce(
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

			// Add priority to legacy tabs.
			$legacy_tabs = get_sections();

			foreach ( $legacy_tabs as $tab_id => $tab_data ) {
				$legacy_tabs[ $tab_id ]['priority'] = 20;
			}

			// Append "new" sections to legacy ones.
			$sections = array_merge( $tabs, $legacy_tabs );

			if ( ! empty( $sections['zapier'] ) ) {
				$sections['zapier']['priority'] = 60;
			}

			// Sort tabs by priority.
			uasort( $sections, fn( $a, $b ) => $a['priority'] <= $b['priority'] ? -1 : 1 );
		}

		return $sections;
	}

	/**
	 * Display a tab's content.
	 *
	 * @since 3.0.0
	 *
	 * @param string $section The tab's slug.
	 */
	protected function display_tab( $section = '' ) {

		$subsections = get_subsections( $section );

		foreach ( $subsections as $subsection_id => $subsection ) {
			$subsection = get_subsection( $section, $subsection_id );
			$func       = $subsection['func'] ?? '';

			if ( is_callable( $func ) || function_exists( $func ) ) {
				call_user_func( $func );
			}
		}
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-settings' );
		wp_enqueue_script( 'sugar-calendar-admin-settings' );

		wp_localize_script(
			'sugar-calendar-admin-settings',
			'sugar_calendar_admin_settings',
			[
				'ajax_url' => Plugin::instance()->get_admin()->ajax_url(),
			]
		);
	}
}
