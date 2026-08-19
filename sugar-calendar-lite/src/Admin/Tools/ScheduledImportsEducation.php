<?php

namespace Sugar_Calendar\Admin\Tools;

use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Plugin;

/**
 * Lite product education for Pro's "Scheduled Imports" section.
 *
 * Renders a believable, fully inert preview of `IcsSync\Admin::render_section()`
 * on the same hook/priority Pro uses -- dummy sources, an open add-form, and
 * an expanded import history -- dimmed and behind the shared upgrade-modal
 * click trap. `Education::hooks()` only instantiates this class when
 * `! Plugin::instance()->is_pro()`, so this renderer and the Pro one can
 * never both fire on the same request.
 *
 * The header row (heading + description + "Add Scheduled Import" button)
 * mirrors Pro's own `.sc-ics-sync-header` flex layout, so the button lands
 * top-right here exactly as it does in Pro -- it just sits inside its own
 * click-trap instead of being live. There are deliberately two separate
 * click-trap wrappers (one around the button, one around the form/table/
 * history below), not one around the whole section: the education handler
 * binds by class, so both open the same upgrade modal, and this keeps the
 * button trapped within the header row rather than needing the header row
 * itself wrapped.
 *
 * This class's own markup uses a distinct `sc-ics-sync-lite-` id prefix --
 * never Pro's unprefixed `sc-ics-sync-*` ids -- so the Pro e2e suite's
 * selectors can never accidentally match Lite output. The CSS *classes* ARE
 * shared with Pro on purpose (`sc-ics-sync-table`, `sc-ics-sync-label`,
 * `sc-ics-sync-badge`, `sc-ics-sync-action`, `sc-ics-sync-form-field`, ...) --
 * see `assets/scss/admin-tools.scss`, which is what makes this preview look
 * identical to the real thing.
 *
 * @since 3.13.0
 */
class ScheduledImportsEducation {

	/**
	 * Number of import-history rows the dummy panel shows. Matches Pro's
	 * own `IcsSync\Admin::HISTORY_ROW_LIMIT` label ("last 20 imports") so
	 * the preview doesn't advertise a number Pro doesn't actually use.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	const HISTORY_ROW_LIMIT = 20;

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_admin_pages_tools_default_importer_after', [ $this, 'render' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Render the education section.
	 *
	 * Bails silently for anyone without `manage_options` -- mirrors Pro's
	 * own gate on `render_section()`. This is a display hook, not an AJAX
	 * endpoint, so there's nothing to respond with; the page simply shows
	 * nothing for this section.
	 *
	 * @since 3.13.0
	 */
	public function render() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$description_link = esc_url(
			Helpers::get_upgrade_link(
				[
					'medium'  => 'tools-import',
					'content' => 'scheduled-imports-upgrade',
				]
			)
		);

		$button_link = esc_url(
			Helpers::get_upgrade_link(
				[
					'medium'  => 'tools-import',
					'content' => 'scheduled-imports-button',
				]
			)
		);

		?>
		<div class="sc-admin-tools-divider"></div>

		<div class="sc-ics-sync-section">

			<div class="sc-ics-sync-header">
				<div class="sc-ics-sync-header__intro">
					<?php
					UI::heading(
						[
							'title' => esc_html__( 'Scheduled Imports', 'sugar-calendar-lite' ),
							'id'    => 'scheduled-imports-heading',
							'class' => 'sugar-calendar--pro-only',
						]
					);
					?>

					<p>
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %1$s: Sugar Calendar Pro upgrade URL. */
								__(
									'Automatically pull events from an ICS feed on a recurring schedule, in addition to the one-time import above. <a target="_blank" href="%1$s">Upgrade to Sugar Calendar Pro</a>.',
									'sugar-calendar-lite'
								),
								$description_link
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
				</div>

				<div
					class="sc-admin-tools-education sce-lite-education-modal-link"
					data-feat-id="scheduled-imports"
					data-feat-name="Scheduled Imports"
				>
					<div class="sc-admin-tools-education__inert">
						<?php $this->render_add_button(); ?>
					</div>
				</div>
			</div>

			<div
				class="sc-admin-tools-education sce-lite-education-modal-link"
				data-feat-id="scheduled-imports"
				data-feat-name="Scheduled Imports"
			>
				<div class="sc-admin-tools-education__inert">
					<?php
					$this->render_form();
					$this->render_sources_table();
					$this->render_history();
					?>
				</div>
			</div>

		</div>

		<a
			href="<?php echo esc_url( $button_link ); ?>"
			class="sugar-calendar-btn sugar-calendar-btn-primary sugar-calendar-btn-lg sc-ics-sync-lite-cta"
		>
			<?php esc_html_e( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ); ?>
		</a>
		<?php
	}

	/**
	 * Render the (disabled) "Add Scheduled Import" button.
	 *
	 * @since 3.13.0
	 */
	private function render_add_button() {
		?>
		<button
			type="button"
			class="sugar-calendar-btn sugar-calendar-btn-secondary sugar-calendar-btn-md sc-ics-sync-add-btn"
			disabled
		>
			<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
			<?php esc_html_e( 'Add Scheduled Import', 'sugar-calendar-lite' ); ?>
		</button>
		<?php
	}

	/**
	 * Render the (disabled, pre-filled) add-form. Field values match the
	 * design's requirement that the form be legible/filled, not a blank
	 * template -- frequency/calendar/status/deleted-events reuse Pro's own
	 * option labels verbatim (`IcsSync\Admin::render_form()`) so this
	 * preview never invents copy Pro doesn't actually ship.
	 *
	 * @since 3.13.0
	 */
	private function render_form() {
		?>
		<div id="sc-ics-sync-lite-form" class="sc-ics-sync-form">

			<h3 class="sc-ics-sync-form-title"><?php esc_html_e( 'Add Scheduled Import', 'sugar-calendar-lite' ); ?></h3>

			<div class="sc-ics-sync-form-field">
				<label for="sc-ics-sync-lite-url" class="sc-ics-sync-form-field__label"><?php esc_html_e( 'ICS feed URL', 'sugar-calendar-lite' ); ?></label>
				<div class="sc-ics-sync-form-field__control">
					<input
						type="text"
						id="sc-ics-sync-lite-url"
						value="https://example.com/calendar.ics"
						disabled
					/>
				</div>
			</div>

			<div class="sc-ics-sync-form-field">
				<label for="sc-ics-sync-lite-frequency" class="sc-ics-sync-form-field__label"><?php esc_html_e( 'Import frequency', 'sugar-calendar-lite' ); ?></label>
				<div class="sc-ics-sync-form-field__control">
					<select id="sc-ics-sync-lite-frequency" disabled>
						<option><?php esc_html_e( 'Hourly', 'sugar-calendar-lite' ); ?></option>
						<option selected="selected"><?php esc_html_e( 'Daily', 'sugar-calendar-lite' ); ?></option>
						<option><?php esc_html_e( 'Weekly', 'sugar-calendar-lite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="sc-ics-sync-form-field">
				<label for="sc-ics-sync-lite-calendar" class="sc-ics-sync-form-field__label"><?php esc_html_e( 'Target calendar', 'sugar-calendar-lite' ); ?></label>
				<div class="sc-ics-sync-form-field__control">
					<select id="sc-ics-sync-lite-calendar" disabled>
						<option selected="selected"><?php esc_html_e( 'Default Calendar', 'sugar-calendar-lite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="sc-ics-sync-form-field">
				<label for="sc-ics-sync-lite-status" class="sc-ics-sync-form-field__label"><?php esc_html_e( 'Event status', 'sugar-calendar-lite' ); ?></label>
				<div class="sc-ics-sync-form-field__control">
					<select id="sc-ics-sync-lite-status" disabled>
						<option selected="selected"><?php esc_html_e( 'Published', 'sugar-calendar-lite' ); ?></option>
						<option><?php esc_html_e( 'Draft', 'sugar-calendar-lite' ); ?></option>
						<option><?php esc_html_e( 'Pending', 'sugar-calendar-lite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="sc-ics-sync-form-field">
				<label for="sc-ics-sync-lite-deleted-events" class="sc-ics-sync-form-field__label"><?php esc_html_e( 'When events are removed from the feed', 'sugar-calendar-lite' ); ?></label>
				<div class="sc-ics-sync-form-field__control">
					<select id="sc-ics-sync-lite-deleted-events" disabled>
						<option selected="selected"><?php esc_html_e( 'Leave them alone', 'sugar-calendar-lite' ); ?></option>
						<option><?php esc_html_e( 'Move them to Draft', 'sugar-calendar-lite' ); ?></option>
						<option><?php esc_html_e( 'Move them to Trash', 'sugar-calendar-lite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="sc-ics-sync-form-actions">
				<span class="sc-ics-sync-form-actions__spacer" aria-hidden="true"></span>
				<button type="button" class="sugar-calendar-btn sugar-calendar-btn-primary sugar-calendar-btn-md" disabled>
					<?php esc_html_e( 'Save & schedule', 'sugar-calendar-lite' ); ?>
				</button>
				<button type="button" class="sugar-calendar-btn sugar-calendar-btn-tertiary sugar-calendar-btn-md sc-ics-sync-cancel-btn" disabled>
					<?php esc_html_e( 'Cancel', 'sugar-calendar-lite' ); ?>
				</button>
			</div>

		</div>
		<?php
	}

	/**
	 * Render the dummy sources table (three rows, per the design).
	 *
	 * @since 3.13.0
	 */
	private function render_sources_table() {
		?>
		<table id="sc-ics-sync-lite-sources" class="widefat sc-ics-sync-table">
			<colgroup>
				<col style="width: 32.2%;" />
				<col style="width: 8.5%;" />
				<col style="width: 14.4%;" />
				<col style="width: 9.3%;" />
				<col style="width: 16.1%;" />
				<col style="width: 19.5%;" />
			</colgroup>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Source', 'sugar-calendar-lite' ); ?></th>
					<th><?php esc_html_e( 'Frequency', 'sugar-calendar-lite' ); ?></th>
					<th><?php esc_html_e( 'Last run', 'sugar-calendar-lite' ); ?></th>
					<th><?php esc_html_e( 'Next run', 'sugar-calendar-lite' ); ?></th>
					<th><?php esc_html_e( 'Last result', 'sugar-calendar-lite' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'sugar-calendar-lite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( self::get_dummy_sources() as $source ) {
					$this->render_source_row( $source );
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render one dummy source row.
	 *
	 * @since 3.13.0
	 *
	 * @param array $source Dummy source data. See `get_dummy_sources()`.
	 */
	private function render_source_row( array $source ) {
		?>
		<tr>
			<td>
				<div class="sc-ics-sync-source-cell">
					<div class="sc-ics-sync-source-url"><?php echo esc_html( $source['url'] ); ?></div>
					<span class="sc-ics-sync-label sc-ics-sync-label--grey sc-ics-sync-source-calendar"><?php echo esc_html( $source['calendar'] ); ?></span>
				</div>
			</td>
			<td><?php echo esc_html( $source['frequency'] ); ?></td>
			<td>
				<div class="sc-ics-sync-last-run">
					<span class="sc-ics-sync-last-run-time"><?php echo esc_html( $source['last_run'] ); ?></span>
					<span class="sc-ics-sync-badge sc-ics-sync-badge--<?php echo esc_attr( $source['status'] ); ?>"><?php echo esc_html( $source['status_label'] ); ?></span>
				</div>
			</td>
			<td><?php echo esc_html( $source['next_run'] ); ?></td>
			<td>
				<?php if ( $source['status'] === 'error' ) : ?>
					<span class="sc-ics-sync-last-result-error"><?php echo esc_html( $source['last_result'] ); ?></span>
				<?php else : ?>
					<?php echo esc_html( $source['last_result'] ); ?>
				<?php endif; ?>
			</td>
			<td>
				<div class="sc-ics-sync-actions">
					<button type="button" class="sc-ics-sync-action" disabled><?php esc_html_e( 'Run Now', 'sugar-calendar-lite' ); ?></button>
					<button type="button" class="sc-ics-sync-action" disabled><?php esc_html_e( 'Edit', 'sugar-calendar-lite' ); ?></button>
					<button type="button" class="sc-ics-sync-action" disabled><?php esc_html_e( 'Pause', 'sugar-calendar-lite' ); ?></button>
					<button type="button" class="sc-ics-sync-action sc-ics-sync-action--danger" disabled><?php esc_html_e( 'Delete', 'sugar-calendar-lite' ); ?></button>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the expanded import-history panel (three rows, per the design).
	 *
	 * @since 3.13.0
	 */
	private function render_history() {
		?>
		<details id="sc-ics-sync-lite-history" class="sc-ics-sync-history" open="open">
			<summary>
				<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: %d: maximum number of import-history rows the real panel shows. */
					esc_html__( 'Import history (last %d imports)', 'sugar-calendar-lite' ),
					(int) self::HISTORY_ROW_LIMIT
				);
				?>
			</summary>

			<table class="widefat striped sc-ics-sync-table">
				<colgroup>
					<col style="width: 33.9%;" />
					<col style="width: 9.3%;" />
					<col style="width: 12.7%;" />
					<col style="width: 6.8%;" />
					<col style="width: 6.8%;" />
					<col style="width: 6.8%;" />
					<col style="width: 6.8%;" />
					<col style="width: 16.9%;" />
				</colgroup>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source', 'sugar-calendar-lite' ); ?></th>
						<th><?php esc_html_e( 'Trigger', 'sugar-calendar-lite' ); ?></th>
						<th><?php esc_html_e( 'When', 'sugar-calendar-lite' ); ?></th>
						<th><?php esc_html_e( 'Created', 'sugar-calendar-lite' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'sugar-calendar-lite' ); ?></th>
						<th><?php esc_html_e( 'Skipped', 'sugar-calendar-lite' ); ?></th>
						<th><?php esc_html_e( 'Failed', 'sugar-calendar-lite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'sugar-calendar-lite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( self::get_dummy_history() as $run ) {
						$this->render_history_row( $run );
					}
					?>
				</tbody>
			</table>
		</details>
		<?php
	}

	/**
	 * Render one dummy history row, plus its error-detail sub-row when the
	 * run failed -- mirrors Pro's own `render_history_row()` shape.
	 *
	 * @since 3.13.0
	 *
	 * @param array $run Dummy history record. See `get_dummy_history()`.
	 */
	private function render_history_row( array $run ) {

		$trigger_class = $run['trigger'] === 'manual' ? 'sc-ics-sync-label--blue' : 'sc-ics-sync-label--grey';
		?>
		<tr>
			<td class="sc-ics-sync-history-source"><?php echo esc_html( $run['source_url'] ); ?></td>
			<td>
				<span class="sc-ics-sync-label <?php echo esc_attr( $trigger_class ); ?>">
					<?php echo esc_html( $run['trigger_label'] ); ?>
				</span>
			</td>
			<td><?php echo esc_html( $run['when'] ); ?></td>
			<td><?php echo esc_html( $run['created'] ); ?></td>
			<td><?php echo esc_html( $run['updated'] ); ?></td>
			<td><?php echo esc_html( $run['skipped'] ); ?></td>
			<td class="<?php echo esc_attr( $run['failed'] > 0 ? 'sc-ics-sync-history-failed' : '' ); ?>"><?php echo esc_html( $run['failed'] ); ?></td>
			<td><span class="sc-ics-sync-badge sc-ics-sync-badge--<?php echo esc_attr( $run['status'] ); ?>"><?php echo esc_html( $run['status_label'] ); ?></span></td>
		</tr>
		<?php if ( $run['status'] === 'error' && ! empty( $run['error_message'] ) ) : ?>
			<tr class="sc-ics-sync-history-error-detail">
				<td colspan="8"><?php echo esc_html( $run['error_message'] ); ?></td>
			</tr>
		<?php endif; ?>
		<?php
	}

	/**
	 * Dummy sources shown in the preview table -- from the approved design
	 * (Hourly/Daily/Weekly, the third in an error state).
	 *
	 * @since 3.13.0
	 *
	 * @return array[]
	 */
	private static function get_dummy_sources() {

		return [
			[
				'url'          => 'calendar.google.com/…/citylibrary…/basic.ics',
				'calendar'     => __( 'Library Events', 'sugar-calendar-lite' ),
				'frequency'    => __( 'Hourly', 'sugar-calendar-lite' ),
				'last_run'     => __( '12 minutes ago', 'sugar-calendar-lite' ),
				'next_run'     => __( 'in 48 minutes', 'sugar-calendar-lite' ),
				'status'       => 'success',
				'status_label' => __( 'Success', 'sugar-calendar-lite' ),
				'last_result'  => __( '12 created · 3 updated · 41 skipped', 'sugar-calendar-lite' ),
			],
			[
				'url'          => 'meetup.com/downtown-tech-collective/events/ical',
				'calendar'     => __( 'Tech Meetup', 'sugar-calendar-lite' ),
				'frequency'    => __( 'Daily', 'sugar-calendar-lite' ),
				'last_run'     => __( '3 hours ago', 'sugar-calendar-lite' ),
				'next_run'     => __( 'in 21 hours', 'sugar-calendar-lite' ),
				'status'       => 'success',
				'status_label' => __( 'Success', 'sugar-calendar-lite' ),
				'last_result'  => __( '2 created · 1 updated · 18 skipped', 'sugar-calendar-lite' ),
			],
			[
				'url'          => 'events.cityartscouncil.org/feed/calendar.ics',
				'calendar'     => __( 'Arts Council', 'sugar-calendar-lite' ),
				'frequency'    => __( 'Weekly', 'sugar-calendar-lite' ),
				'last_run'     => __( '2 days ago', 'sugar-calendar-lite' ),
				'next_run'     => __( 'in 5 days', 'sugar-calendar-lite' ),
				'status'       => 'error',
				'status_label' => __( 'Error', 'sugar-calendar-lite' ),
				'last_result'  => __( 'Feed URL returned 404', 'sugar-calendar-lite' ),
			],
		];
	}

	/**
	 * Dummy import-history rows shown in the expanded panel -- the same
	 * three feeds as `get_dummy_sources()`, with a mix of Scheduled/Manual
	 * trigger chips per the design.
	 *
	 * @since 3.13.0
	 *
	 * @return array[]
	 */
	private static function get_dummy_history() {

		return [
			[
				'source_url'    => 'calendar.google.com/…/citylibrary…/basic.ics',
				'trigger'       => 'scheduled',
				'trigger_label' => __( 'Scheduled', 'sugar-calendar-lite' ),
				'when'          => __( '12 minutes ago', 'sugar-calendar-lite' ),
				'created'       => 12,
				'updated'       => 3,
				'skipped'       => 41,
				'failed'        => 0,
				'status'        => 'success',
				'status_label'  => __( 'Success', 'sugar-calendar-lite' ),
			],
			[
				'source_url'    => 'meetup.com/downtown-tech-collective/events/ical',
				'trigger'       => 'manual',
				'trigger_label' => __( 'Manual', 'sugar-calendar-lite' ),
				'when'          => __( '3 hours ago', 'sugar-calendar-lite' ),
				'created'       => 2,
				'updated'       => 1,
				'skipped'       => 18,
				'failed'        => 0,
				'status'        => 'success',
				'status_label'  => __( 'Success', 'sugar-calendar-lite' ),
			],
			[
				'source_url'    => 'events.cityartscouncil.org/feed/calendar.ics',
				'trigger'       => 'scheduled',
				'trigger_label' => __( 'Scheduled', 'sugar-calendar-lite' ),
				'when'          => __( '2 days ago', 'sugar-calendar-lite' ),
				'created'       => 0,
				'updated'       => 0,
				'skipped'       => 0,
				'failed'        => 0,
				'status'        => 'error',
				'status_label'  => __( 'Error', 'sugar-calendar-lite' ),
				'error_message' => __( 'Feed URL returned 404', 'sugar-calendar-lite' ),
			],
		];
	}

	/**
	 * Enqueue the shared education style/script on the Tools page only, and
	 * localize just the modal keys `admin-education.js`'s click handler
	 * needs -- same convention as `EventAbstract::enqueue_assets()` /
	 * `SettingsFeedsTab::enqueue_assets()`.
	 *
	 * @since 3.13.0
	 *
	 * @param string $hook Hook suffix for the current admin page.
	 */
	public function enqueue_assets( $hook ) {

		if ( $hook !== 'events_page_sc-tools' ) {
			return;
		}

		wp_enqueue_style( 'sugar-calendar-admin-education' );
		wp_enqueue_script( 'sugar-calendar-admin-education' );

		wp_localize_script(
			'sugar-calendar-admin-education',
			'sugar_calendar_admin_education',
			[
				// `wp_localize_script()` prints a second `var` declaration rather
				// than merging, and this one runs after `Education::enqueue_assets()`
				// on the same handle -- so `ajax_url` has to be repeated here or the
				// Lite notice bar's dismiss AJAX posts to `undefined` on this page.
				'ajax_url'                              => Plugin::instance()->get_admin()->ajax_url(),
				'sce_admin_upgrade_modal_title_default' => esc_html__( 'Upgrade to Pro', 'sugar-calendar-lite' ),
				'sce_admin_upgrade_modal_content'       => BaseHelpers::get_education_upgrade_modal_content(),
				'sce_admin_upgrade_thank_you_modal'     => BaseHelpers::get_education_upgrade_thank_you_modal_content(),
				'sce_admin_upgrade_modal_feature_name'  => esc_html__( 'feature', 'sugar-calendar-lite' ),
			]
		);
	}
}
