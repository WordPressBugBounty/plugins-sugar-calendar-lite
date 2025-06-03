<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Tools\Exporter;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;

/**
 * Calendar Export Tools tab.
 *
 * @since 3.3.0
 */
class ToolsExportTab extends Tools {

	/**
	 * Export nonce action.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const EXPORT_NONCE_ACTION = 'sc_admin_tools_export_nonce';

	/**
	 * Need pro keys.
	 *
	 * @since 3.6.0
	 * @since 3.7.0 Add speakers.
	 *
	 * @var array
	 */
	const NEED_PRO_KEYS = [
		'_venues',
		'_speakers',
	];

	/**
	 * Register Export tab hooks.
	 *
	 * @since 3.3.0
	 */
	public function hooks() {

		parent::hooks();

		add_action( 'admin_init', [ $this, 'handle_export' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 3.3.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script(
			'sugar-calendar-admin-exporter',
			SC_PLUGIN_ASSETS_URL . 'admin/js/sc-admin-exporter' . WP::asset_min() . '.js',
			[ 'jquery' ],
			Helpers::get_asset_version(),
			true
		);
	}

	/**
	 * Export the JSON file to the browser.
	 *
	 * @since 3.3.0
	 */
	public function handle_export() {

		if ( ! isset( $_POST['sc_admin_tools_export'] ) ) {
			return;
		}

		if ( ! check_admin_referer( self::EXPORT_NONCE_ACTION, 'sc_admin_tools_export_nonce' ) ) {
			wp_nonce_ays(
				esc_html__( 'Invalid request.', 'sugar-calendar-lite' )
			);
			die();
		}

		if (
			empty( $_POST['sc_admin_tools_export_data'] ) ||
			! is_array( $_POST['sc_admin_tools_export_data'] )
		) {
			WP::add_admin_notice(
				esc_html__( 'Please select the data you want to export.', 'sugar-calendar-lite' ),
				WP::ADMIN_NOTICE_ERROR,
				true
			);

			WP::display_admin_notices();

			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$exporter = new Exporter( $_POST['sc_admin_tools_export_data'] );

		$export = $exporter->export();

		Helpers::set_time_limit();
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=sugar-calendar-export-' . current_time( 'm-d-Y' ) . '.json' );
		header( 'Expires: 0' );

		// Clean any output before writing the JSON.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		echo wp_json_encode( $export );
		exit;
	}

	/**
	 * Page tab slug.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'export';
	}

	/**
	 * Page label.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Export', 'sugar-calendar-lite' );
	}

	/**
	 * Output setting fields.
	 *
	 * @since 3.3.0
	 * @since 3.6.0 Add lite venues education.
	 */
	protected function display_tab() {

		UI::heading(
			[
				'title' => esc_html__( 'Export', 'sugar-calendar-lite' ),
			]
		);

		/**
		 * Filter for export modules.
		 *
		 * @since 3.6.0
		 * @since 3.7.0 Add speakers.
		 *
		 * @param array $data_checkboxes Array of data checkboxes.
		 */
		$data_checkboxes = apply_filters(
			'sugar_calendar_admin_pages_tools_export_tab_checkboxes',
			[
				'events'        => __( 'Events', 'sugar-calendar-lite' ),
				'custom_fields' => __( 'Custom Fields', 'sugar-calendar-lite' ),
				'calendars'     => __( 'Calendars', 'sugar-calendar-lite' ),
				'orders'        => __( 'Tickets, Orders and Attendees', 'sugar-calendar-lite' ),
				'tags'          => TagsHelpers::get_tags_taxonomy_labels( 'name' ),
				'_venues'       => __( 'Venues', 'sugar-calendar-lite' ),
				'_speakers'     => __( 'Speakers', 'sugar-calendar-lite' ),
			]
		);
		?>
		<p>
			<?php esc_html_e( 'Select the Sugar Calendar data that you would like to export.', 'sugar-calendar-lite' ); ?>
		</p>
		<form id="sc-admin-tools-export-form" method="post">
			<input type="hidden" name="sc_admin_tools_export_nonce" value="<?php echo esc_attr( wp_create_nonce( self::EXPORT_NONCE_ACTION ) ); ?>" />
			<div class="sc-admin-tools-form-content">
				<ul>
				<?php
				foreach ( $data_checkboxes as $key => $label ) {

					// If the key is in the NEED_PRO_KEYS array, add the need-pro class.
					$export_item_css_class = in_array( $key, self::NEED_PRO_KEYS, true )
						? 'sc-admin-tools-disabled need-pro'
						: '';
					?>
					<li
						id="sc-admin-tools-export-context-<?php echo esc_attr( $key ); ?>"
						class="<?php echo esc_attr( $export_item_css_class ); ?>"
					>
						<label>
							<input
								<?php checked( $key, 'events' ); ?>
								id="sc-admin-tools-export-checkbox-<?php echo esc_attr( $key ); ?>"
								name="sc_admin_tools_export_data[]"
								type="checkbox"
								value="<?php echo esc_attr( $key ); ?>"
							>
							<?php echo esc_html( $label ); ?>
						</label>
					</li>
					<?php
				}
				?>
				</ul>
			</div>
			<div class="sc-admin-tools-divider"></div>
			<button class="sugar-calendar-btn sugar-calendar-btn-primary sugar-calendar-btn-md" type="submit" name="sc_admin_tools_export">
				<?php esc_html_e( 'Export', 'sugar-calendar-lite' ); ?>
			</button>
		</form>
		<?php
	}
}
