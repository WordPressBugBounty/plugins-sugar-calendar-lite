<?php

namespace Sugar_Calendar\Emails\Ticketing;

use Sugar_Calendar\Admin\Pages\Settings\Emails\SmartTagDropdown;
use Sugar_Calendar\Emails\TicketingSmartTags;
use Sugar_Calendar\Emails\AbstractEmail;
use Sugar_Calendar\Emails\EmailRegistry;

use function Sugar_Calendar\AddOn\Ticketing\Settings\get_ticket_sale_admin_message;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_ticket_sale_admin_subject;

/**
 * The ticket-sale notification sent to the site administrator.
 *
 * @since 3.14.0
 */
class TicketSaleAdminEmail extends AbstractEmail {

	/**
	 * Email id.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const ID = 'sc_et_ticket_sale_email_to_admin';

	/**
	 * Registry id, also the `email_cfg` route and the `EmailStatus` key.
	 *
	 * @since 3.14.0
	 */
	public function id(): string {

		return self::ID;
	}

	/**
	 * Row title, unescaped.
	 *
	 * @since 3.14.0
	 */
	public function title(): string {

		return __( 'Ticket Sale Email To Admin', 'sugar-calendar-lite' );
	}

	/**
	 * Row description, unescaped.
	 *
	 * @since 3.14.0
	 */
	public function description(): string {

		return __( 'Notification email to admin when a new ticket is sold', 'sugar-calendar-lite' );
	}

	/**
	 * Card group: `EmailRegistry::GROUP_ATTENDEES` or `EmailRegistry::GROUP_ADMIN`.
	 *
	 * @since 3.14.0
	 */
	public function group(): string {

		return EmailRegistry::GROUP_ADMIN;
	}

	/**
	 * Whether the sender reads `EmailStatus` on its automatic path.
	 *
	 * @since 3.14.0
	 */
	public function is_status_managed(): bool {

		return true;
	}

	/**
	 * Whether a `sugar_calendar_emails_preview_document` provider renders this email.
	 *
	 * @since 3.14.0
	 */
	public function is_previewable(): bool {

		return true;
	}

	/**
	 * POST names and preview state keys: `[ 'subject' => key, 'message' => key ]`, or `[]`.
	 *
	 * @since 3.14.0
	 */
	public function option_slugs(): array {

		return [
			'subject' => 'ticket_sale_admin_subject',
			'message' => 'ticket_sale_admin_message',
		];
	}

	/**
	 * Smart tags in the editor dropdown's shape: `[ group => [ [ 'value', 'label' ], … ] ]`.
	 *
	 * @since 3.14.0
	 */
	public function smart_tags(): array {

		// `{tickets}` emits `<ul><li>` markup; this notification is plain text,
		// so the editor must not offer it, even though delivery still resolves it.
		return SmartTagDropdown::format( TicketingSmartTags::definitions_for( self::ID ), [ 'order', 'event' ], [ 'tickets' ] );
	}

	/**
	 * Edit-screen field spec, or null when no edit screen exists.
	 *
	 * @since 3.14.0
	 */
	public function editor(): ?array { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		return [
			'subject_id'          => 'sc_et_ticket_sale_email_to_admin_subject',
			'subject_value'       => get_ticket_sale_admin_subject(),
			'subject_placeholder' => esc_html__( 'New ticket sale', 'sugar-calendar-lite' ),
			'subject_description' => esc_html__( 'The subject line of the notification sent to the site administrator.', 'sugar-calendar-lite' ),
			'message_editor_id'   => 'sc_et_settings_ticket_sale_admin_message',
			'message_value'       => get_ticket_sale_admin_message(),
			'message_label'       => esc_html__( 'Email Message', 'sugar-calendar-lite' ),
		];
	}
}
