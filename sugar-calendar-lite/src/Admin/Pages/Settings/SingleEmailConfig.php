<?php

namespace Sugar_Calendar\Admin\Pages\Settings;

use Sugar_Calendar\AddOn\Ticketing\Helpers\UI;

/**
 * Handles the individual email configuration settings.
 *
 * @since 3.7.0
 */
class SingleEmailConfig {

	/**
	 * Initialize the email config.
	 *
	 * @param string $email_config_id The configurable email ID.
	 *
	 * @since 3.7.0
	 */
	public function init( $email_config_id ) {

		$email_config_id = sanitize_key( $email_config_id );

		/**
		 * Filter the IDs of configurable emails.
		 *
		 * @since 3.7.0
		 *
		 * @param string[] $email_to_configure_ids IDs of configurable emails.
		 */
		$valid_email_config_ids = apply_filters(
			'sugar_calendar_settings_valid_email_config_ids',
			[
				'sc_et_receipt_email',
				'sc_et_ticket_email',
			]
		);

		if ( ! in_array( $email_config_id, $valid_email_config_ids, true ) ) {
			$this->display_invalid_email_config();
		} else {
			$this->display_back_to_emails();
			?>
			<div class="sugar-calendar-admin-settings-email-config-single">
				<?php
				/**
				 * Perform action for specific email configurable settings.
				 *
				 * This is used for displaying the email configurable fields.
				 *
				 * @since 3.7.0
				 */
				do_action( "sugar_calendar_admin_settings_email_config_display_{$email_config_id}" );
				?>
			</div>
			<?php
		}
	}

	/**
	 * Display the Back to Emails link.
	 *
	 * @since 3.7.0
	 */
	private function display_back_to_emails() {

		$url = add_query_arg(
			[
				'page'    => 'sugarcalendar-settings',
				'section' => 'emails',
			],
			get_admin_url( null, 'admin.php' )
		);
		?>
		<div id="sugar-calendar-settings-emails-configuration-back-to-emails">
			<a href="<?php echo esc_url( $url ); ?>"><span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to Emails', 'sugar-calendar-lite' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Display the invalid email config message.
	 *
	 * @since 3.7.0
	 */
	private function display_invalid_email_config() {

		$this->display_back_to_emails();

		UI::heading(
			[
				'title' => esc_html__( 'Invalid email configuration!', 'sugar-calendar-lite' ),
			]
		);
	}
}