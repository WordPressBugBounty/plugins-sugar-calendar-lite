<?php

namespace Sugar_Calendar\Admin\Pages\Settings;

use Sugar_Calendar\Admin\Pages\Settings\Emails\EmailEditPage;
use Sugar_Calendar\Emails\EmailRegistry;
use Sugar_Calendar\Helpers\UI;

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

		if ( ! in_array( $email_config_id, self::get_valid_ids(), true ) ) {
			$this->display_invalid_email_config();
		} else {
			$this->display_back_to_emails();
			?>
			<div class="sugar-calendar-admin-settings-email-config-single">
				<?php
				$email = EmailRegistry::definition( $email_config_id );

				if ( $email !== null && $email->editor() !== null ) {
					EmailEditPage::render_email( $email );
				}

				/**
				 * Perform action for specific email configurable settings.
				 *
				 * This is used for displaying the email configurable fields.
				 *
				 * @since 3.7.0
				 * @since 3.14.0 Fires after the registered editor, when one exists.
				 */
				do_action( "sugar_calendar_admin_settings_email_config_display_{$email_config_id}" );
				?>
			</div>
			<?php
		}
	}

	/**
	 * The email ids that may be reached at `?email_cfg={id}`.
	 *
	 * Derived from registered definitions with option slugs, never from listed
	 * rows. A listing row is display metadata — it puts a card in the list —
	 * and says nothing about whether anything renders that email's editor.
	 * Deriving the list from listed rows made every row added through the
	 * public `…_emails_to_configure` filter routable, so a third-party row with
	 * no renderer answered with an accepted-but-empty panel instead of the
	 * invalid-configuration notice.
	 *
	 * Add-ons that render through the display action add their ids here, in
	 * the same change that registers their display callback.
	 *
	 * Public because the save path needs the same answer: an edit-screen save must
	 * only accept an `email_cfg` this screen would actually route to.
	 *
	 * @since 3.14.0
	 *
	 * @return string[]
	 */
	public static function get_valid_ids() {

		/**
		 * Filter the IDs of configurable emails.
		 *
		 * @since 3.7.0
		 *
		 * @param string[] $email_to_configure_ids IDs of configurable emails.
		 */
		return (array) apply_filters(
			'sugar_calendar_settings_valid_email_config_ids',
			EmailRegistry::editable_ids()
		);
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