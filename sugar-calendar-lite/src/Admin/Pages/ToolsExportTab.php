<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Tools\Export\ExporterService;
use Sugar_Calendar\Admin\Tools\Export\ExportRequest;
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
	 * Points at ExportRequest, which owns the request side of this form, so the
	 * rendered nonce and the verified one cannot drift. Kept here because it has
	 * been the public name since 3.3.0.
	 *
	 * @since 3.3.0
	 * @since 3.13.0 Defined by ExportRequest::NONCE_ACTION.
	 *
	 * @var string
	 */
	const EXPORT_NONCE_ACTION = ExportRequest::NONCE_ACTION;

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
	 * @since 3.13.0 Enqueue the date-range picker assets.
	 */
	public function hooks() {

		parent::hooks();

		add_action( 'admin_init', [ $this, 'handle_export' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Filter the help URL in the Tools page -> Export tab.
	 *
	 * @since 3.8.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return Helpers\Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/events/importing-and-exporting-events/#Exporting_Events',
			[
				'content' => 'Help',
				'medium'  => 'tools-export',
			]
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 3.3.0
	 * @since 3.13.0 Add flatpickr, the exporter date-range script and, on Lite,
	 *                  the Pro-feature upgrade modal.
	 */
	public function enqueue_scripts() {

		wp_enqueue_style(
			'sugar-calendar-flatpickr',
			SC_PLUGIN_ASSETS_URL . 'lib/flatpickr/flatpickr.min.css',
			[],
			Helpers::get_asset_version()
		);

		wp_enqueue_script(
			'sugar-calendar-flatpickr',
			SC_PLUGIN_ASSETS_URL . 'lib/flatpickr/flatpickr.min.js',
			[ 'jquery' ],
			Helpers::get_asset_version(),
			true
		);

		$deps      = [ 'jquery', 'sugar-calendar-flatpickr' ];
		$education = ! sugar_calendar()->is_pro();

		// Lite shows the Pro-only data types disabled and badged; clicking one opens
		// the upgrade modal, which needs jquery-confirm and the shared opener in
		// admin-education.js. Pro replaces those rows, so it needs neither.
		if ( $education ) {
			$deps[] = 'sugar-calendar-vendor-jquery-confirm';
			$deps[] = 'sugar-calendar-admin-education';

			wp_enqueue_style( 'sugar-calendar-vendor-jquery-confirm' );
			wp_enqueue_style( 'sugar-calendar-admin-education' );
			wp_enqueue_script( 'sugar-calendar-vendor-jquery-confirm' );
			wp_enqueue_script( 'sugar-calendar-admin-education' );
		}

		wp_enqueue_script(
			'sugar-calendar-admin-exporter',
			SC_PLUGIN_ASSETS_URL . 'admin/js/sc-admin-exporter' . WP::asset_min() . '.js',
			$deps,
			Helpers::get_asset_version(),
			true
		);

		if ( ! $education ) {
			return;
		}

		// Localized on this screen's own handle under its own name, deliberately not
		// added to the shared `sugar_calendar_admin_education` object: that one is
		// localized by more than one place under the same name, so whichever runs
		// last wins and the others' keys disappear.
		wp_localize_script(
			'sugar-calendar-admin-exporter',
			'sc_admin_exporter',
			[
				'education' => array_merge(
					Helpers::get_education_upgrade_modal_content(),
					[
						'feature_name' => esc_html__( 'feature', 'sugar-calendar-lite' ),
						'thank_you'    => Helpers::get_education_upgrade_thank_you_modal_content(),
					]
				),
			]
		);
	}

	/**
	 * Handle the export request and stream the file (JSON, or CSV / ZIP).
	 *
	 * @since 3.3.0
	 * @since 3.13.0 Add the capability check, the format / date-range args and
	 *                  the ExporterService dispatch.
	 */
	public function handle_export() {

		if ( ! ExportRequest::is_submitted() ) {
			return;
		}

		ExportRequest::verify();

		$request = ExportRequest::from_request();

		if ( ! $request ) {
			WP::add_admin_notice(
				esc_html__( 'Please select the data you want to export.', 'sugar-calendar-lite' ),
				WP::ADMIN_NOTICE_ERROR,
				true
			);

			WP::display_admin_notices();

			return;
		}

		$service = new ExporterService( $request->to_args() );

		// Fail loud rather than stream an empty file when the selection resolves
		// to nothing (e.g. a CSV data type that produces no table).
		if ( ! $service->has_items() ) {
			wp_die(
				esc_html__( 'There is no data to export for the current selection.', 'sugar-calendar-lite' ),
				esc_html__( 'Nothing to export', 'sugar-calendar-lite' ),
				[
					'response'  => 400,
					'back_link' => true,
				]
			);
		}

		$service->output();
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
	 * @since 3.13.0 Add the export format radios and the custom date range.
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
				<h4 class="sc-admin-tools-section-label"><?php esc_html_e( 'Select data to export', 'sugar-calendar-lite' ); ?></h4>
				<ul>
				<?php
				foreach ( $data_checkboxes as $key => $label ) {

					// Pro-only data types render disabled and badged, and carry what the
					// upgrade modal needs to name the feature. Pro swaps these keys out
					// for real ones, so this only ever matches on Lite.
					$needs_pro = in_array( $key, self::NEED_PRO_KEYS, true );
					?>
					<li
						id="sc-admin-tools-export-context-<?php echo esc_attr( $key ); ?>"
						class="<?php echo esc_attr( $needs_pro ? 'sc-admin-tools-disabled need-pro' : '' ); ?>"
						<?php if ( $needs_pro ) : ?>
							data-feat-id="<?php echo esc_attr( ltrim( $key, '_' ) ); ?>"
							data-feat-name="<?php echo esc_attr( $label ); ?>"
						<?php endif; ?>
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
			<div class="sc-admin-tools-export-type">
				<h4 class="sc-admin-tools-section-label"><?php esc_html_e( 'Export Format', 'sugar-calendar-lite' ); ?></h4>
				<?php
				UI::radio_input(
					[
						'id'      => 'sc_admin_tools_export_format',
						'name'    => 'sc_admin_tools_export_format',
						'value'   => 'json',
						'layout'  => 'vertical',
						'options' => [
							'json' => __( 'JSON', 'sugar-calendar-lite' ),
							'csv'  => __( 'CSV', 'sugar-calendar-lite' ),
						],
					],
					true
				);
				?>
			</div>
			<div class="sc-admin-tools-export-date-range">
				<h4 class="sc-admin-tools-section-label"><?php esc_html_e( 'Custom Date Range', 'sugar-calendar-lite' ); ?></h4>
				<?php
				UI::date_range_control(
					[
						'id'          => 'sc-admin-tools-export-date-range',
						'name_start'  => 'sc_admin_tools_export_date_start',
						'name_end'    => 'sc_admin_tools_export_date_end',
						'placeholder' => __( 'Select Date Range', 'sugar-calendar-lite' ),
						'aria_label'  => __( 'Custom date range', 'sugar-calendar-lite' ),
						'description' => __( 'Custom Date Range only filters Events, Tickets, Orders & Attendees. Everything else exports in full.', 'sugar-calendar-lite' ),
					],
					true
				);
				?>
			</div>
			<div class="sc-admin-tools-divider"></div>
			<button class="sugar-calendar-btn sugar-calendar-btn-primary sugar-calendar-btn-md" type="submit" name="sc_admin_tools_export">
				<?php esc_html_e( 'Export', 'sugar-calendar-lite' ); ?>
			</button>
		</form>
		<?php
	}
}
