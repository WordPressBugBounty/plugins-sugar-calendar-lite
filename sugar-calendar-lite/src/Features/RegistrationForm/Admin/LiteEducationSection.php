<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Helpers\Helpers;

/**
 * The Lite product-education state of the "Registration Form" event-editor tab.
 *
 * A static picture of the Pro editor, not an app: markup mirrors the React
 * app's DOM class-for-class so it inherits the same design-system CSS, every
 * input is disabled, and a transparent overlay links out via the shared
 * education-modal JS. SchemaRepository::save() also refuses Lite writes.
 *
 * @since 3.13.0
 */
class LiteEducationSection {

	/**
	 * Feature id used by the shared upgrade-modal JS for copy + UTM medium.
	 *
	 * The `event-` prefix matches the other event-metabox education blocks
	 * (`event-venues`, `event-speakers`) that admin-event-lite.js expects.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const FEATURE_ID = 'event-registration-form';

	/**
	 * Register hooks.
	 *
	 * Only the section registration; there is no write path on Lite.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_admin_meta_box_setup_sections', [ $this, 'register_section' ] );
	}

	/**
	 * Register the Registration Form education section on the Event metabox.
	 *
	 * Mirrors the id, icon and order of the Pro section so the tab sits in the
	 * same place in both builds.
	 *
	 * @since 3.13.0
	 *
	 * @param object $metabox Event metabox instance.
	 */
	public function register_section( $metabox ) {

		$metabox->add_section(
			[
				'id'       => 'registration',
				'label'    => esc_html__( 'Registration Form', 'sugar-calendar-lite' ),
				'icon'     => 'feedback',
				'order'    => 62,
				'callback' => [ $this, 'display' ],
			]
		);
	}

	/**
	 * Metabox section callback.
	 *
	 * @since 3.13.0
	 */
	public function display() {

		$this->render(
			Helpers::get_upgrade_link(
				[
					'medium'  => 'lite-event-registration-form',
					'content' => 'Upgrade to Sugar Calendar Pro',
				]
			)
		);
	}

	/**
	 * Echo the education markup.
	 *
	 * The upgrade URL is injected rather than resolved here, since Helpers
	 * reaches through Plugin::instance(), which can't boot under the unit-test
	 * bootstrap that pins this markup.
	 *
	 * @since 3.13.0
	 *
	 * @param string $upgrade_url Fully-formed, UTM-tagged upgrade URL.
	 */
	public function render( $upgrade_url ) {

		$strings = EditorStrings::all();

		?>
		<div class="sugar-calendar-metabox__field-row">
			<div id="sc-registration-form-education" class="sc-registration-form sc-registration-form--education">
				<div class="sc-registration-form__education-mock" aria-hidden="true">
					<div class="sc-settings-field" data-type="toggle">
						<div class="sc-settings-field__label">
							<span class="sc-field-label"><?php echo esc_html( $strings['section_label'] ); ?></span>
						</div>
						<div class="sc-settings-field__field">
							<div class="sc-settings-field__row sc-registration-form__enable">
								<span class="sc-toggle-row">
									<input type="checkbox" class="sc-toggle" checked disabled />
									<span class="sc-toggle-label"><?php echo esc_html( $strings['toggle_on'] ); ?></span>
								</span>
							</div>
							<div class="sc-settings-field__description sc-field-description">
								<span><?php echo esc_html( $strings['toggle_helper'] ); ?></span>
							</div>
							<div class="sc-registration-form__fields">
								<?php $this->render_field_card( $strings ); ?>
							</div>
							<div class="sc-registration-form__add-field-row">
								<span class="sc-button sc-button--text sc-registration-form__add-field">
									<span class="sc-button__icon sc:icon-[fa6-solid--plus]"></span>
									<?php echo esc_html( $strings['add_new_field'] ); ?>
								</span>
							</div>
						</div>
					</div>
					<hr class="sc-registration-form__divider" />
					<div class="sc-registration-form__settings">
						<?php
						$this->render_radio_row(
							$strings['when_label'],
							$strings['when_helper'],
							[ $strings['before_checkout'], $strings['after_checkout'] ]
						);

						$this->render_radio_row(
							$strings['collect_label'],
							$strings['collect_helper'],
							[ $strings['main_attendee'], $strings['each_attendee'] ]
						);

						// Not a v1 capability; an inert OFF toggle in the dimmed mock.
						$this->render_toggle_row(
							__( 'Edit after submission', 'sugar-calendar-lite' ),
							__( 'Allow attendees to edit forms post-submission', 'sugar-calendar-lite' ),
							$strings['toggle_off']
						);
						?>
					</div>
				</div>
				<span
					class="sc-registration-form__education-overlay sce-lite-education-modal-link"
					role="button"
					tabindex="0"
					data-feat-id="<?php echo esc_attr( self::FEATURE_ID ); ?>"
					data-feat-name="<?php esc_attr_e( 'Registration Forms', 'sugar-calendar-lite' ); ?>"
					aria-label="<?php esc_attr_e( 'Registration Forms is a Pro feature. Upgrade to unlock it.', 'sugar-calendar-lite' ); ?>"
				></span>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--upgrade">
			<p class="desc">
				<?php
				echo wp_kses(
					sprintf( /* translators: %1$s - upgrade URL; %2$s - link text; %3$s - trailing sentence. */
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
						esc_url( $upgrade_url ),
						esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
						esc_html__( 'to get access to custom registration forms + much more', 'sugar-calendar-lite' )
					),
					[
						'a' => [
							'href'   => [],
							'rel'    => [],
							'target' => [],
						],
					]
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the single inert Text Field card.
	 *
	 * @since 3.13.0
	 *
	 * @param array $strings Editor copy.
	 */
	private function render_field_card( $strings ) {

		?>
		<div class="sc-form-editor-block" data-question-type="short_text">
			<div class="sc-form-editor-block__header">
				<div class="sc-form-editor-block__type">
					<span class="sc-form-editor-block__type-trigger sc-field-label">
						<?php echo esc_html( $strings['type_short_text'] ); ?>
						<span class="sc:icon-[fa6-solid--chevron-down] sc-form-editor-block__type-chevron"></span>
					</span>
				</div>
				<div class="sc-form-editor-block__actions">
					<span class="sc-form-editor-block__action sc-form-editor-block__duplicate">
						<span class="sc:icon-[fa6-solid--copy]"></span>
					</span>
					<span class="sc-form-editor-block__action sc-form-editor-block__delete">
						<span class="sc:icon-[fa6-solid--trash]"></span>
					</span>
				</div>
			</div>
			<div class="sc-form-editor-block__body">
				<div class="sc-form-editor-block__question">
					<input
						type="text"
						class="sc-input sc-field-text"
						placeholder="<?php echo esc_attr( $strings['question_placeholder'] ); ?>"
						disabled
					/>
				</div>
				<span class="sc-checkbox-row">
					<input type="checkbox" class="sc-checkbox sc:checked:before:icon-src-[fa6-solid--check]" checked disabled />
					<span class="sc-field-text"><?php echo esc_html( $strings['required'] ); ?></span>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one inert settings row carrying a single OFF toggle.
	 *
	 * @since 3.13.0
	 *
	 * @param string $label  Row label.
	 * @param string $helper Row helper text.
	 * @param string $state  Toggle state label (e.g. "Off").
	 */
	private function render_toggle_row( $label, $helper, $state ) {

		?>
		<div class="sc-settings-field" data-type="toggle">
			<div class="sc-settings-field__label">
				<span class="sc-field-label"><?php echo esc_html( $label ); ?></span>
			</div>
			<div class="sc-settings-field__field">
				<div class="sc-settings-field__row">
					<span class="sc-toggle-row">
						<input type="checkbox" class="sc-toggle" disabled />
						<span class="sc-toggle-label"><?php echo esc_html( $state ); ?></span>
					</span>
				</div>
				<div class="sc-settings-field__description sc-field-description">
					<span><?php echo esc_html( $helper ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one inert settings row of radios (first option pre-selected).
	 *
	 * Radios, since the Pro editor's `show` setting holds a single value.
	 *
	 * @since 3.13.0
	 *
	 * @param string   $label   Row label.
	 * @param string   $helper  Row helper text.
	 * @param string[] $options Option labels; the first renders as selected.
	 */
	private function render_radio_row( $label, $helper, $options ) {

		?>
		<div class="sc-settings-field" data-type="radio">
			<div class="sc-settings-field__label">
				<span class="sc-field-label"><?php echo esc_html( $label ); ?></span>
			</div>
			<div class="sc-settings-field__field">
				<div class="sc-settings-field__row">
					<div class="sc-settings-field__control">
						<div class="sc-settings-field__radio-group">
							<?php foreach ( $options as $index => $option ) : ?>
								<span class="sc-radio-row">
									<input type="radio" class="sc-radio"<?php checked( $index, 0 ); ?> disabled />
									<span class="sc-field-text"><?php echo esc_html( $option ); ?></span>
								</span>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="sc-settings-field__description sc-field-description">
					<span><?php echo esc_html( $helper ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}
}
