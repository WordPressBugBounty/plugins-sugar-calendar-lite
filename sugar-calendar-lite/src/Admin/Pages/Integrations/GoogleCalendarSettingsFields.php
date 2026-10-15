<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Integrations\Google\GoogleOAuthConnection;
use Sugar_Calendar\Integrations\Google\GoogleSyncMapper;

/**
 * HTML of the Google Calendar settings rows: the calendar and sync rows, or their locked stand-ins.
 *
 * @since 3.16.0
 */
class GoogleCalendarSettingsFields {

	/**
	 * The data the rows show.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleCalendarSettingsForm
	 */
	private $form;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleCalendarSettingsForm $form The data the rows show.
	 */
	public function __construct( GoogleCalendarSettingsForm $form ) {

		$this->form = $form;
	}

	/**
	 * The calendar and sync rows, or their locked stand-ins.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleOAuthConnection $connection The connected account.
	 *
	 * @return void
	 */
	public function render( GoogleOAuthConnection $connection ) {

		if ( ! $connection->has_calendar_access() ) {
			$this->render_locked_row( 'google_calendar_id', __( 'Sync Events On', 'sugar-calendar-lite' ), __( 'Calendar permissions not approved', 'sugar-calendar-lite' ) );
			$this->render_locked_row( 'google_sync_direction', __( 'Sync Settings', 'sugar-calendar-lite' ), __( 'Calendar permissions not approved', 'sugar-calendar-lite' ) );
		} elseif ( GoogleSyncMapper::is_custom() ) {
			$this->render_locked_row( 'google_calendar_id', __( 'Sync Events On', 'sugar-calendar-lite' ), __( 'Settings overridden by custom mapping', 'sugar-calendar-lite' ) );
			$this->render_locked_row( 'google_sync_direction', __( 'Sync Settings', 'sugar-calendar-lite' ), __( 'Settings overridden by custom mapping', 'sugar-calendar-lite' ) );
		} else {
			$this->render_calendar_row();
			$this->render_sync_row();
		}
	}

	/**
	 * Render the "Sync Events On" row: calendar select and help text.
	 *
	 * Built by hand because the shared select helper cannot emit the help text
	 * inside the field column; naming and escaping mirror UI::select_input().
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	private function render_calendar_row() {

		[ $options, $selected ] = $this->form->calendar_choices();

		$field_class = 'sugar-calendar-google-calendar__calendar-field';

		// The calendar is mandatory: an empty control is an error on load and after a rejected save alike.
		if ( $selected === '' ) {
			$field_class .= ' sugar-calendar-google-calendar__calendar-field--error';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $field_class ); ?>">
			<?php $this->render_calendar_select( $options, $selected ); ?>
			<p id="sugar-calendar-google-calendar-desc" class="desc sugar-calendar-google-calendar__calendar-desc">
				<?php esc_html_e( 'Select the calendar on which new events will be added', 'sugar-calendar-lite' ); ?>
			</p>
		</div>
		<?php
		UI::field_wrapper(
			[
				'label' => __( 'Sync Events On', 'sugar-calendar-lite' ),
				'id'    => 'google_calendar_id',
			],
			ob_get_clean()
		);
	}

	/**
	 * Print the calendar select.
	 *
	 * @since 3.16.0
	 *
	 * @param string[] $options  Option labels keyed by value.
	 * @param string   $selected Selected value.
	 *
	 * @return void
	 */
	private function render_calendar_select( array $options, string $selected ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$autofocus = isset( $_GET['sc_notice'] ) && sanitize_key( $_GET['sc_notice'] ) === 'google_connected';

		?>
		<select
			name="sugar-calendar[google_calendar_id]"
			id="sugar-calendar-setting-google_calendar_id"
			class="sugar-calendar-google-calendar__calendar-select"
			<?php echo $autofocus ? 'autofocus' : ''; ?>
			<?php echo $selected === '' ? 'aria-invalid="true"' : ''; ?>
			aria-describedby="sugar-calendar-google-calendar-desc"
		>
			<?php $this->render_calendar_options( $options, $selected ); ?>
		</select>
		<?php
	}

	/**
	 * Print the calendar dropdown options, with a line that can't be picked between the fixed choices and the calendars.
	 *
	 * @since 3.16.0
	 *
	 * @param string[] $options  Option labels keyed by value.
	 * @param string   $selected Selected value.
	 *
	 * @return void
	 */
	private function render_calendar_options( array $options, string $selected ) {

		foreach ( $options as $option_value => $option_label ) {
			// "Select Calendar" is only a prompt; once something is chosen it would just be a way to un-choose.
			if ( $option_value === '' && $selected !== '' ) {
				continue;
			}

			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $option_value ),
				selected( $option_value, $selected, false ),
				esc_html( $option_label )
			);

			if ( $option_value === GoogleCalendar::SYNC_OFF_VALUE && count( $options ) > 2 ) {
				echo '<option disabled>──────────</option>';
			}
		}
	}

	/**
	 * Render a locked row with one option saying why; it has no name, so a save never posts it.
	 *
	 * @since 3.16.0
	 *
	 * @param string $id    Setting id, as the normal row uses.
	 * @param string $label Row label.
	 * @param string $text  The single option's text.
	 *
	 * @return void
	 */
	private function render_locked_row( string $id, string $label, string $text ) {

		ob_start();
		?>
		<div class="sugar-calendar-google-calendar__calendar-field">
			<select id="<?php echo esc_attr( 'sugar-calendar-setting-' . $id ); ?>" disabled>
				<option><?php echo esc_html( $text ); ?></option>
			</select>
		</div>
		<?php
		UI::field_wrapper(
			[
				'label' => $label,
				'id'    => $id,
				'class' => 'sugar-calendar-google-calendar__sync-row--locked',
			],
			ob_get_clean()
		);
	}

	/**
	 * Translated direction labels, keyed by the values in GoogleCalendarSettingsForm::SYNC_DIRECTIONS.
	 *
	 * @since 3.16.0
	 *
	 * @return string[]
	 */
	private function get_sync_direction_labels(): array {

		return [
			'both'         => __( 'Sync both ways', 'sugar-calendar-lite' ),
			'sc_to_google' => __( 'Sync from Sugar Calendar to Google Calendar', 'sugar-calendar-lite' ),
			'google_to_sc' => __( 'Sync from Google Calendar to Sugar Calendar', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Render the "Sync Settings" row.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	private function render_sync_row() {

		// "Don't Sync Events" saved: nothing syncs, so the row is locked; the page JS keeps it in step and adds the tooltip.
		$locked = $this->form->is_sync_locked();

		ob_start();
		?>
		<div class="sugar-calendar-google-calendar__calendar-field">
			<select
				name="sugar-calendar[google_sync_direction]"
				id="sugar-calendar-setting-google_sync_direction"
				<?php disabled( $locked ); ?>
			>
				<?php $this->render_sync_options(); ?>
			</select>
			<p class="desc sugar-calendar-google-calendar__calendar-desc">
				<?php esc_html_e( 'Select how you want the sync between Sugar Calendar and Google to function', 'sugar-calendar-lite' ); ?>
			</p>
			<?php $this->render_lite_recurring_note(); ?>
		</div>
		<?php
		UI::field_wrapper(
			[
				'label' => __( 'Sync Settings', 'sugar-calendar-lite' ),
				'id'    => 'google_sync_direction',
				'class' => $locked ? 'sugar-calendar-google-calendar__sync-row--locked' : '',
			],
			ob_get_clean()
		);
	}

	/**
	 * Tell a Lite site that a pulled recurring Google event keeps only its first date, as the importers do.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	private function render_lite_recurring_note() {

		if ( sugar_calendar()->is_pro() || $this->form->is_sync_locked() || ! GoogleSyncMapper::allows_pull( $this->form->sync_direction() ) ) {
			return;
		}

		?>
		<p class="desc sugar-calendar-google-calendar__calendar-desc">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: Sugar Calendar Pro upgrade URL. */
					__( 'Sugar Calendar Lite saves a recurring Google event as a single event on its first date. <a href="%s" target="_blank" rel="noopener noreferrer">Upgrade to Sugar Calendar Pro</a> to sync every date in the series.', 'sugar-calendar-lite' ),
					esc_url( $this->get_upgrade_url() )
				),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			);
			?>
		</p>
		<?php
	}

	/**
	 * Upgrade link for the Lite recurring note.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	private function get_upgrade_url(): string {

		return Helpers::get_utm_url(
			'https://sugarcalendar.com/lite-upgrade/',
			[
				'medium'  => 'integrations-google-calendar',
				'content' => 'recurring-events-upgrade',
			]
		);
	}

	/**
	 * Print the sync direction options.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	private function render_sync_options() {

		$selected = $this->form->sync_direction();

		foreach ( $this->get_sync_direction_labels() as $option_value => $option_label ) :
			?>
			<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $option_value, $selected ); ?>><?php echo esc_html( $option_label ); ?></option>
			<?php
		endforeach;
	}
}
