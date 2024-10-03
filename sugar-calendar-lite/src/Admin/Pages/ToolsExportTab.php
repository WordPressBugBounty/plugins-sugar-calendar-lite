<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Tools\Exporter;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;

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
			SC_PLUGIN_VERSION,
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
				esc_html__( 'Invalid request.', 'sugar-calendar' )
			);
			die();
		}

		if (
			empty( $_POST['sc_admin_tools_export_data'] ) ||
			! is_array( $_POST['sc_admin_tools_export_data'] )
		) {
			WP::add_admin_notice(
				esc_html__( 'Please select the data you want to export.', 'sugar-calendar' ),
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

		return esc_html__( 'Export', 'sugar-calendar' );
	}

	/**
	 * Output setting fields.
	 *
	 * @since 3.3.0
	 */
	protected function display_tab() {

		UI::heading(
			[
				'title' => esc_html__( 'Export', 'sugar-calendar' ),
			]
		);

		$data_checkboxes = [
			'events'        => __( 'Events', 'sugar-calendar' ),
			'custom_fields' => __( 'Custom Fields', 'sugar-calendar' ),
			'calendars'     => __( 'Calendars', 'sugar-calendar' ),
			'orders'        => __( 'Tickets, Orders and Attendees', 'sugar-calendar' ),
		];
		?>
		<p>
			<?php esc_html_e( 'Select the Sugar Calendar data that you would like to export.', 'sugar-calendar' ); ?>
		</p>
		<form id="sc-admin-tools-export-form" method="post">
			<input type="hidden" name="sc_admin_tools_export_nonce" value="<?php echo esc_attr( wp_create_nonce( self::EXPORT_NONCE_ACTION ) ); ?>" />
			<div class="sc-admin-tools-form-content">
				<ul>
				<?php
				foreach ( $data_checkboxes as $key => $label ) {
					?>
					<li id="sc-admin-tools-export-context-<?php echo esc_attr( $key ); ?>">
						<label>
							<input <?php checked( $key, 'events' ); ?> id="sc-admin-tools-export-checkbox-<?php echo esc_attr( $key ); ?>" name="sc_admin_tools_export_data[]" type="checkbox" value="<?php echo esc_attr( $key ); ?>">
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
				<?php esc_html_e( 'Export', 'sugar-calendar' ); ?>
			</button>
		</form>
		<?php
	}
}
