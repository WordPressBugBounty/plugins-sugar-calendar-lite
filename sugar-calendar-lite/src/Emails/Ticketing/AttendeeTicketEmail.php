<?php

namespace Sugar_Calendar\Emails\Ticketing;

use Sugar_Calendar\Admin\Pages\Settings\Emails\SmartTagDropdown;
use Sugar_Calendar\Emails\TicketingSmartTags;
use Sugar_Calendar\Emails\AbstractEmail;
use Sugar_Calendar\Emails\EmailRegistry;
use Sugar_Calendar\Emails\Styling;

use function Sugar_Calendar\AddOn\Ticketing\Settings\get_ticket_message_resolution;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_ticket_subject;
use function Sugar_Calendar\AddOn\Ticketing\Settings\resolve_email_copy_for_template;

/**
 * The ticket email sent to each attendee.
 *
 * @since 3.14.0
 */
class AttendeeTicketEmail extends AbstractEmail {

	/**
	 * Email id.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const ID = 'sc_et_ticket_email';

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

		return __( 'Ticket Email To Attendee', 'sugar-calendar-lite' );
	}

	/**
	 * Row description, unescaped.
	 *
	 * @since 3.14.0
	 */
	public function description(): string {

		return __( 'Ticket confirmation and details email sent to attendees', 'sugar-calendar-lite' );
	}

	/**
	 * Card group: `EmailRegistry::GROUP_ATTENDEES` or `EmailRegistry::GROUP_ADMIN`.
	 *
	 * @since 3.14.0
	 */
	public function group(): string {

		return EmailRegistry::GROUP_ATTENDEES;
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
			'subject' => 'ticket_subject',
			'message' => 'ticket_message',
		];
	}

	/**
	 * `LAYOUT_SINGLE`, `LAYOUT_MULTI`, or null when no Modern document exists.
	 *
	 * @since 3.14.0
	 */
	public function layout(): ?string { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		return self::LAYOUT_SINGLE;
	}

	/**
	 * Smart tags in the editor dropdown's shape: `[ group => [ [ 'value', 'label' ], … ] ]`.
	 *
	 * @since 3.14.0
	 */
	public function smart_tags(): array {

		return SmartTagDropdown::format( TicketingSmartTags::definitions_for( self::ID ), [ 'ticket', 'event', 'attendee' ] );
	}

	/**
	 * Edit-screen field spec, or null when no edit screen exists.
	 *
	 * @since 3.14.0
	 */
	public function editor(): ?array { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		return [
			'subject_id'          => 'sc_et_ticket_email_subject',
			'subject_value'       => get_ticket_subject(),
			'subject_placeholder' => esc_html__( 'Your event ticket!', 'sugar-calendar-lite' ),
			'subject_description' => esc_html__( 'The subject line used when emailing a ticket to an attendee.', 'sugar-calendar-lite' ),
			'message_editor_id'   => 'sc_et_settings_ticket_message',
			'message_value'       => resolve_email_copy_for_template( get_ticket_message_resolution(), Styling::get()['template'] ),
			'message_label'       => esc_html__( 'Ticket Email Message', 'sugar-calendar-lite' ),
		];
	}
}
