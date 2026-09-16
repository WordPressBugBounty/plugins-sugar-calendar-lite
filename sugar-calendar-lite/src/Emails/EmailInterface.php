<?php

namespace Sugar_Calendar\Emails;

/**
 * One configurable email: identity, listing metadata, and settings-screen behaviour.
 *
 * `title()` and `description()` return raw translated strings; the consumer
 * escapes once at output, the same rule the group labels follow.
 *
 * @since 3.14.0
 */
interface EmailInterface {

	/**
	 * Modern layout for one ticket or one RSVP.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const LAYOUT_SINGLE = 'single';

	/**
	 * Modern layout for a multi-ticket order.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const LAYOUT_MULTI = 'multi';

	/**
	 * Registry id, also the `email_cfg` route and the `EmailStatus` key.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Row title, unescaped.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function title(): string;

	/**
	 * Row description, unescaped.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function description(): string;

	/**
	 * Card group: `EmailRegistry::GROUP_ATTENDEES` or `EmailRegistry::GROUP_ADMIN`.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function group(): string;

	/**
	 * Whether the servicing add-on is not active, so the row shows an upgrade prompt.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_pro_only(): bool;

	/**
	 * Whether a sender exists for this email.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_sendable(): bool;

	/**
	 * Whether the sender reads `EmailStatus` on its automatic path.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_status_managed(): bool;

	/**
	 * Whether a `sugar_calendar_emails_preview_document` provider renders this email.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_previewable(): bool;

	/**
	 * POST names and preview state keys: `[ 'subject' => key, 'message' => key ]`, or `[]`.
	 *
	 * Non-empty makes the id routable to an edit screen.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public function option_slugs(): array;

	/**
	 * `LAYOUT_SINGLE`, `LAYOUT_MULTI`, or null when no Modern document exists.
	 *
	 * @since 3.14.0
	 *
	 * @return string|null
	 */
	public function layout(): ?string; // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

	/**
	 * Smart tags in the editor dropdown's shape: `[ group => [ [ 'value', 'label' ], … ] ]`.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public function smart_tags(): array;

	/**
	 * Edit-screen field spec, or null when no edit screen exists.
	 *
	 * Keys: `subject_id`, `subject_value`, `subject_placeholder`,
	 * `subject_description`, `message_editor_id`, `message_value`,
	 * `message_label`. The two ids are DOM ids pinned by E2E.
	 *
	 * @since 3.14.0
	 *
	 * @return array|null
	 */
	public function editor(): ?array; // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.
}
