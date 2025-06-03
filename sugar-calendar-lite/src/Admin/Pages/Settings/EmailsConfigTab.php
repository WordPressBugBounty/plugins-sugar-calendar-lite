<?php

namespace Sugar_Calendar\Admin\Pages\Settings;

use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\AddOn\Ticketing\Settings as TicketingSettings;

/**
 * Emails Config Tab.
 *
 * @since 3.7.0
 */
class EmailsConfigTab {

	/**
	 * Hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		add_action( 'sc_et_settings_emails_section_bottom', [ $this, 'configure_emails_section' ] );
		add_action( 'sugar_calendar_admin_settings_email_config_display_sc_et_receipt_email', [ $this, 'display_email_config_sc_et_receipt_email' ] );
		add_action( 'sugar_calendar_admin_settings_email_config_display_sc_et_ticket_email', [ $this, 'display_email_config_sc_et_ticket_email' ] );
	}

	/**
	 * Configure Emails section.
	 * 
	 * @since 3.7.0
	 */
	public function configure_emails_section() {

		UI::heading(
			[
				'title' => esc_html__( 'Configure Emails', 'sugar-calendar-lite' ),
			]
		);

		$emails_to_configure = [
			'sc_et_receipt_email'                 => [
				'title'       => __( 'Order Receipt Email To Attendee', 'sugar-calendar-lite' ),
				'description' => __( 'The full message included in the emailed order receipts', 'sugar-calendar-lite' ),
			],
			'sc_et_ticket_email'                  => [
				'title'       => __( 'Ticket Receipt Email To Purchaser', 'sugar-calendar-lite' ),
				'description' => __( 'The message sent when emailing a ticket to an attendee', 'sugar-calendar-lite' ),
			],
			'sc_rsvp_going_email_to_attendee'     => [
				'title'       => __( 'RSVP “Going” Email To Attendee', 'sugar-calendar-lite' ),
				'description' => __( 'Confirmation email to attendees who RSVP as "going."', 'sugar-calendar-lite' ),
				'pro_only'    => true,
			],
			'sc_rsvp_not_going_email_to_attendee' => [
				'title'       => __( 'RSVP “Not Going” Email To Attendee', 'sugar-calendar-lite' ),
				'description' => __( 'Confirmation email to attendees who RSVP as "not going."', 'sugar-calendar-lite' ),
				'pro_only'    => true,
			],
		];

		/**
		 * Filters the emails to configure.
		 *
		 * @since 3.7.0
		 *
		 * @param string[] $emails_to_configure {
		 *     Array containing the emails that can be configured.
		 *
		 *     @type string $title       Title of the email configuration.
		 *     @type string $description Description of the email configuration.
		 * }
		 */
		$emails_to_configure = apply_filters(
			'sugar_calendar_admin_settings_emails_tab_emails_to_configure',
			$emails_to_configure
		);

		$admin_url = add_query_arg(
			[
				'page' => 'sugarcalendar-settings',
			],
			get_admin_url( null, 'admin.php' )
		);
		?>

		<div id="sugar-calendar-settings-emails-configuration-lists">
			<?php

			foreach ( $emails_to_configure as $id => $email_config ) {
				$email_config_url = add_query_arg(
					[
						'section'   => 'emails',
						'email_cfg' => $id,
					],
					$admin_url
				);
				?>
				<div class="sugar-calendar-settings-emails-configuration-lists__block">
					<div class="sugar-calendar-settings-emails-configuration-lists__block__body">
						<div class="sugar-calendar-settings-emails-configuration-lists__block__body__title">
							<?php echo esc_html( $email_config['title'] ); ?>
						</div>
						<div class="sugar-calendar-settings-emails-configuration-lists__block__body__desc">
							<?php echo esc_html( $email_config['description'] ); ?>
						</div>
					</div>
					<div class="sugar-calendar-settings-emails-configuration-lists__block__edit">
						<?php
						if ( ! empty( $email_config['pro_only'] ) && $email_config['pro_only'] ) {
							printf(
								'<span class="sugar-calendar__badge__pro-only">PRO</span>'
							);
						} else {
							printf(
								'<a href="%1$s"><span class="dashicons dashicons-edit"></span></a>',
								esc_url( $email_config_url )
							);
						}
						?>
					</div>
				</div>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Display the email config settings for the Receipt Email.
	 *
	 * @since 1.0.0
	 */
	public function display_email_config_sc_et_receipt_email() {

		UI::heading(
			[
				'title' => esc_html__( 'Order Receipt Email', 'sugar-calendar-lite' ),
			]
		);

		// Order Receipt Subject.
		$subject = TicketingSettings\get_setting( 'receipt_subject' );

		UI::text_input(
			[
				'id'          => 'sc_et_receipt_email_subject',
				'name'        => 'receipt_subject',
				'value'       => $subject,
				'placeholder' => esc_html__( 'Ticket Purchase Receipt', 'sugar-calendar-lite' ),
				'label'       => esc_html__( 'Order Receipt Subject', 'sugar-calendar-lite' ),
				'description' => esc_html__( 'The subject line of emailed order receipts.', 'sugar-calendar-lite' ),
			]
		);

		// Order Receipt Message.
		UI::field_wrapper(
			[
				'label' => esc_html__( 'Order Receipt Message', 'sugar-calendar-lite' ),
				'id'    => 'receipt-message',
			],
			$this->display_receipt_message_editor()
		);
	}

	/**
	 * Display the email config settings for the Ticket Email.
	 *
	 * @since 1.0.0
	 */
	public function display_email_config_sc_et_ticket_email() {

		// Ticket Receipt Email.
		UI::heading(
			[
				'title' => esc_html__( 'Ticket Receipt Email', 'sugar-calendar-lite' ),
			]
		);

		// Ticket Email Subject.
		$t_subject = TicketingSettings\get_setting( 'ticket_subject' );

		UI::text_input(
			[
				'id'          => 'sc_et_ticket_email_subject',
				'name'        => 'ticket_subject',
				'value'       => $t_subject,
				'placeholder' => esc_html__( 'Ticket Email Subject', 'sugar-calendar-lite' ),
				'label'       => esc_html__( 'Ticket Email Subject', 'sugar-calendar-lite' ),
				'description' => esc_html__( 'The subject line used when emailing a ticket to an attendee.', 'sugar-calendar-lite' ),
			]
		);

		// Ticket Email Message.
		UI::field_wrapper(
			[
				'label' => esc_html__( 'Ticket Email Message', 'sugar-calendar-lite' ),
				'id'    => 'ticket-message',
			],
			$this->display_ticket_email_message_editor(),
		);
	}

	/**
	 * Display the receipt message editor.
	 *
	 * @since 1.0.0
	 */
	private function display_receipt_message_editor() {

		$message = TicketingSettings\get_setting( 'receipt_message' );

		ob_start();

		wp_editor(
			stripslashes( $message ),
			'sc_et_settings_receipt_message',
			[
				'textarea_name' => 'sugar-calendar[receipt_message]'
			]
		);
		?>

		<p class="desc">
			<?php esc_html_e( 'The full message included in the emailed order receipts. The following dynamic placeholders can be used:', 'sugar-calendar-lite' ); ?>
		</p>
		<dl class="sc-et-email-tags-list">
		<?php
		echo $this->get_emails_tags_list( 'order' );
		echo $this->get_emails_tags_list( 'event' );
		?>
		</dl>
		<?php
		return ob_get_clean();
	}

	/**
	 * Display the Ticket Email Message editor.
	 *
	 * @since 1.0.0
	 */
	private function display_ticket_email_message_editor() {

		$t_message = TicketingSettings\get_setting( 'ticket_message' );

		ob_start();

		wp_editor(
			stripslashes( $t_message ),
			'sc_et_settings_ticket_message',
			[
				'textarea_name' => 'sugar-calendar[ticket_message]'
			]
		);
		?>
		<p class="description">
			<?php esc_html_e( 'The message sent when emailing a ticket to an attendee. The following dynamic placeholders can be used:', 'sugar-calendar-lite' ); ?>
		</p>
		<dl class="sc-et-email-tags-list">
			<?php
			echo $this->get_emails_tags_list( 'ticket' );
			echo $this->get_emails_tags_list( 'event' );
			echo $this->get_emails_tags_list( 'attendee' );
			?>
		</dl>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get a formatted HTML list of all available tags
	 *
	 * @since 1.0.0
	 *
	 * @param $object_type The tag type to return; order, ticket, event or attendee
	 *
	 * @return string $list HTML formatted list
	 */
	private function get_emails_tags_list( $object_type = 'order' ) {

		$list       = '';
		$emails     = new \Sugar_Calendar\AddOn\Ticketing\Emails();
		$email_tags = $emails->get_tags( $object_type );

		if ( count( $email_tags ) > 0 ) :
			foreach ( $email_tags as $email_tag ) : ?>
			<dt>
				<code>{<?php echo $email_tag['tag']; ?>}</code>
			</dt>
			<dd>
				<?php echo $email_tag['description']; ?>
			</dd>
			<?php endforeach;
		endif;

		return $list;
	}
}