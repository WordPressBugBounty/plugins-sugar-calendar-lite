<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers;

/**
 * Sugar Calendar importer.
 *
 * @since 3.6.0
 */
class SugarCalendarIcs extends Importer {

	/**
	 * Admin hooks.
	 *
	 * @since 3.6.0
	 */
	public function admin_hooks() {

		// Add importer display.
		add_action( 'sugar_calendar_admin_pages_tools_default_importer_after', [ $this, 'display' ] );
	}

	/**
	 * Get the importer title.
	 *
	 * @since 3.6.0
	 *
	 * @return string
	 */
	public function get_title() {

		return esc_html__( 'iCalendar (ICS) Import', 'sugar-calendar-lite' );
	}

	/**
	 * Display the ICS importer.
	 *
	 * @since 3.6.0
	 */
	public function display() {

		$current_locale  = sanitize_title( get_locale() );
		$description_utm = esc_url(
			Helpers\Helpers::get_utm_url(
				'https://sugarcalendar.com/lite-upgrade/',
				[
					'source'   => 'WordPress',
					'medium'   => 'tools-import',
					'campaign' => 'liteplugin',
					'locale'   => $current_locale,
					'content'  => 'ics-import-upgrade',
				]
			)
		);
		$button_utm      = esc_url(
			Helpers\Helpers::get_utm_url(
				'https://sugarcalendar.com/lite-upgrade/',
				[
					'source'   => 'WordPress',
					'medium'   => 'tools-import',
					'campaign' => 'liteplugin',
					'locale'   => $current_locale,
					'content'  => 'ics-button',
				]
			)
		);

		UI::heading(
			[
				'title' => esc_html( $this->get_title() ),
				'id'    => 'sc-admin-tools-ics-import-heading',
				'class' => 'sugar-calendar--pro-only',
			]
		);

		?>
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: Sugar Calendar Pro pricing page URL. */
					__(
						'Import events from your Apple, Google, Microsoft, or other calendars, by pasting your ICS URL below. <a target="_blank" href="%1$s">Upgrade to Sugar Calendar Pro.</a>.',
						'sugar-calendar-lite'
					),
					$description_utm
				),
				[
					'a' => [
						'href'   => [],
						'target' => [],
					],
				]
			);
			?>
		</p>
		<div id="sc-admin-tools-import-form-ics" class="sc-admin-tools-education">
			<div class="sc-admin-tools-form-content">
				<div id="sugar-calendar-setting-row-sc-admin-tools-ics-import-url" class="sugar-calendar-setting-row sugar-calendar-clear sugar-calendar-setting-row-text">
					<span class="sugar-calendar-setting-field">
						<input
							type="text"
							name="sugar-calendar[sc-admin-tools-ics-import-url]"
							value=""
							id="sugar-calendar-setting-sc-admin-tools-ics-import-url"
							placeholder="<?php esc_attr_e( 'Enter an ICS URL', 'sugar-calendar-lite' ); ?>"
							disabled
						>
					</span>
				</div>
			</div>
			<a
				href="<?php echo esc_url( $button_utm ); ?>"
				class="sugar-calendar-btn sugar-calendar-btn-primary sugar-calendar-btn-lg"
			>
				<?php esc_html_e( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.6.0
	 *
	 * @param int[] $total_number_to_import Optional. The total number to import per context.
	 */
	public function run( $total_number_to_import = [] ) {}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.6.0
	 */
	public function get_slug() {

		return 'sugar-calendar-ics';
	}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.6.0
	 */
	public function is_ajax() {

		return false;
	}
}
