<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Helpers\UI;

/**
 * The abandonment reminder's Settings -> Emails entry.
 *
 * Adapted from sc-rsvp's Admin\Settings\EmailsConfig. Four hooks are required, and
 * one of them is easy to miss; see add_email_ids_to_configure().
 *
 * @since 3.13.0
 */
class ReminderEmailConfig {

	/**
	 * Email config id. Also the URL's `email_cfg` value.
	 *
	 * @since 3.13.0
	 */
	const EMAIL_ID = 'sc_registration_reminder_email';

	/**
	 * Option key for the subject.
	 *
	 * @since 3.13.0
	 */
	const OPTION_SUBJECT = 'sc_registration_reminder_email_subject';

	/**
	 * Option key for the message body.
	 *
	 * @since 3.13.0
	 */
	const OPTION_MESSAGE = 'sc_registration_reminder_email_message';

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_admin_settings_emails_tab_emails_to_configure', [ $this, 'add_emails_to_configure' ] );
		add_filter( 'sugar_calendar_settings_valid_email_config_ids', [ $this, 'add_email_ids_to_configure' ] );
		add_action( 'sugar_calendar_admin_settings_email_config_display_' . self::EMAIL_ID, [ $this, 'display_email_config' ] );
		add_action( 'sugar_calendar_admin_area_handle_post', [ $this, 'save_emails_config' ] );
	}

	/**
	 * List the reminder on the Emails tab.
	 *
	 * @since 3.13.0
	 *
	 * @param array $emails_to_configure Existing entries.
	 *
	 * @return array
	 */
	public function add_emails_to_configure( $emails_to_configure ) {

		$emails_to_configure = (array) $emails_to_configure;

		$emails_to_configure[ self::EMAIL_ID ] = [
			'title'       => __( 'Incomplete Registration Reminder', 'sugar-calendar-lite' ),
			'description' => __( 'Sent once when a registration form is left incomplete after checkout.', 'sugar-calendar-lite' ),
		];

		return $emails_to_configure;
	}

	/**
	 * Whitelist the config id.
	 *
	 * Without this, the tab entry renders but links to a page showing
	 * SingleEmailConfig's invalid-config state, the same class of silent routing
	 * failure documented for Area::get_settings_page_id().
	 *
	 * @since 3.13.0
	 *
	 * @param array $ids Existing valid ids.
	 *
	 * @return array
	 */
	public function add_email_ids_to_configure( $ids ) {

		return array_merge( (array) $ids, [ self::EMAIL_ID ] );
	}

	/**
	 * Render the subject/body editor.
	 *
	 * @since 3.13.0
	 */
	public function display_email_config() {

		UI::heading(
			[
				'title' => esc_html__( 'Incomplete Registration Reminder Email', 'sugar-calendar-lite' ),
			]
		);

		UI::text_input(
			[
				'id'          => 'sc_registration_reminder_email_subject',
				'name'        => self::OPTION_SUBJECT,
				// Deliberately not wp_kses_post(): a subject is a mail header, not HTML,
				// and UI::text_input() already escapes what it renders. The Message
				// field below is HTML and still goes through wp_kses_post() on save.
				'value'       => self::get_subject(),
				'placeholder' => esc_html__( 'Please complete your registration for {event_title}', 'sugar-calendar-lite' ),
				'label'       => esc_html__( 'Subject', 'sugar-calendar-lite' ),
			]
		);

		UI::field_wrapper(
			[
				'label' => esc_html__( 'Message', 'sugar-calendar-lite' ),
				'id'    => self::OPTION_MESSAGE,
			],
			$this->display_message_editor()
		);
	}

	/**
	 * Display the message editor.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	private function display_message_editor() {

		ob_start();

		wp_editor(
			stripslashes( self::get_message() ),
			'sc_registration_reminder_email_message',
			[
				'textarea_name' => 'sugar-calendar[' . self::OPTION_MESSAGE . ']',
			]
		);
		?>
		<div class="sc-admin__settings__emails__tags">
			<p class="description">
				<?php esc_html_e( 'Dynamic Placeholders', 'sugar-calendar-lite' ); ?>
			</p>
			<div class="sc-admin__settings__emails__tags__list">
				<?php
				foreach ( $this->get_tags_lists() as $tag => $description ) {
					?>
					<div class="sc-admin__settings__emails__tags__list__item">
						<div class="sc-admin__settings__emails__tags__list__item__tag">
							<code><?php echo esc_html( $tag ); ?></code>
						</div>
						<div class="sc-admin__settings__emails__tags__list__item__desc">
							<?php echo esc_html( $description ); ?>
						</div>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get the placeholder lists.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function get_tags_lists() {

		return [
			'{attendee_name}' => __( 'The Attendee Name', 'sugar-calendar-lite' ),
			'{event_title}'   => __( 'The Event Title', 'sugar-calendar-lite' ),
			'{event_date}'    => __( 'The Event Date', 'sugar-calendar-lite' ),
			'{resume_link}'   => __( 'The link to finish the registration form', 'sugar-calendar-lite' ),
			'{site_name}'     => __( 'The Site Name', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Persist the submitted subject and message.
	 *
	 * @since 3.13.0
	 *
	 * @param array $post_data Submitted data.
	 */
	public function save_emails_config( $post_data ) {

		if ( ! $this->is_own_email_config_request() ) {
			return;
		}

		$post_data = (array) $post_data;

		if ( isset( $post_data[ self::OPTION_SUBJECT ] ) ) {
			update_option( self::OPTION_SUBJECT, sanitize_text_field( $post_data[ self::OPTION_SUBJECT ] ), false );
		}

		if ( isset( $post_data[ self::OPTION_MESSAGE ] ) ) {
			update_option( self::OPTION_MESSAGE, wp_kses_post( $post_data[ self::OPTION_MESSAGE ] ), false );
		}
	}

	/**
	 * Whether the current request is a submission of this email's config screen.
	 *
	 * Every configurable email hooks the same `sugar_calendar_admin_area_handle_post`
	 * action, so this `email_cfg` gate is what stops one email's submission from
	 * touching another's options.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	private function is_own_email_config_request() {

		if ( empty( $_GET['page'] ) || $_GET['page'] !== 'sugarcalendar-settings' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		if ( empty( $_GET['section'] ) || $_GET['section'] !== 'emails' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		if ( empty( $_GET['email_cfg'] ) || sanitize_key( $_GET['email_cfg'] ) !== self::EMAIL_ID ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		return true;
	}

	/**
	 * The reminder's subject.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public static function get_subject() {

		$saved = get_option( self::OPTION_SUBJECT, false );

		if ( $saved !== false ) {
			return $saved;
		}

		return __( 'Please complete your registration for {event_title}', 'sugar-calendar-lite' );
	}

	/**
	 * The reminder's message body.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public static function get_message() {

		$saved = get_option( self::OPTION_MESSAGE, false );

		if ( $saved !== false ) {
			return $saved;
		}

		return __(
			"Hi {attendee_name},\n\nWe still need a few details for {event_title} on {event_date}.\n\nComplete your registration here: {resume_link}\n\nThanks,\n{site_name}",
			'sugar-calendar-lite'
		);
	}
}
