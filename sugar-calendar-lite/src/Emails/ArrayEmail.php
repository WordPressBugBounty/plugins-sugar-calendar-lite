<?php

namespace Sugar_Calendar\Emails;

/**
 * An email as the listing filter sees it: metadata from a normalized row,
 * behaviour delegated to the registered definition when one exists.
 *
 * Covers a third-party filter row, an old add-on build that only edits flags,
 * a core upsell row, and a registered object whose row the filter changed.
 *
 * @since 3.14.0
 */
class ArrayEmail implements EmailInterface {

	/**
	 * Email id.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Normalized listing row.
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private $row;

	/**
	 * Registered definition, or null for a row with no object behind it.
	 *
	 * @since 3.14.0
	 *
	 * @var EmailInterface|null
	 */
	private $definition;

	/**
	 * Constructor.
	 *
	 * @since 3.14.0
	 *
	 * @param string              $id         Email id.
	 * @param array               $row        Row already passed through EmailRegistry normalization.
	 * @param EmailInterface|null $definition Registered definition, if any.
	 */
	public function __construct( string $id, array $row, ?EmailInterface $definition = null ) { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.typeDeclarationFound -- Sugar Calendar requires PHP 7.4+.

		$this->id         = $id;
		$this->row        = $row;
		$this->definition = $definition;
	}

	/**
	 * Registry id, also the `email_cfg` route and the `EmailStatus` key.
	 *
	 * @since 3.14.0
	 */
	public function id(): string {

		return $this->id;
	}

	/**
	 * Row title, unescaped.
	 *
	 * @since 3.14.0
	 */
	public function title(): string {

		return (string) $this->row['title'];
	}

	/**
	 * Row description, unescaped.
	 *
	 * @since 3.14.0
	 */
	public function description(): string {

		return (string) $this->row['description'];
	}

	/**
	 * Card group: `EmailRegistry::GROUP_ATTENDEES` or `EmailRegistry::GROUP_ADMIN`.
	 *
	 * @since 3.14.0
	 */
	public function group(): string {

		return (string) $this->row['group'];
	}

	/**
	 * Whether the servicing add-on is not active, so the row shows an upgrade prompt.
	 *
	 * @since 3.14.0
	 */
	public function is_pro_only(): bool {

		return ! empty( $this->row['pro_only'] );
	}

	/**
	 * Whether a sender exists for this email.
	 *
	 * @since 3.14.0
	 */
	public function is_sendable(): bool {

		return ! empty( $this->row['sendable'] );
	}

	/**
	 * Whether the sender reads `EmailStatus` on its automatic path.
	 *
	 * @since 3.14.0
	 */
	public function is_status_managed(): bool {

		return ! empty( $this->row['status_managed'] );
	}

	/**
	 * Whether a `sugar_calendar_emails_preview_document` provider renders this email.
	 *
	 * @since 3.14.0
	 */
	public function is_previewable(): bool {

		return $this->definition !== null && $this->definition->is_previewable();
	}

	/**
	 * POST names and preview state keys: `[ 'subject' => key, 'message' => key ]`, or `[]`.
	 *
	 * @since 3.14.0
	 */
	public function option_slugs(): array {

		return $this->definition === null ? [] : $this->definition->option_slugs();
	}

	/**
	 * `LAYOUT_SINGLE`, `LAYOUT_MULTI`, or null when no Modern document exists.
	 *
	 * @since 3.14.0
	 */
	public function layout(): ?string { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		return $this->definition === null ? null : $this->definition->layout();
	}

	/**
	 * Smart tags in the editor dropdown's shape: `[ group => [ [ 'value', 'label' ], … ] ]`.
	 *
	 * @since 3.14.0
	 */
	public function smart_tags(): array {

		return $this->definition === null ? [] : $this->definition->smart_tags();
	}

	/**
	 * Edit-screen field spec, or null when no edit screen exists.
	 *
	 * @since 3.14.0
	 */
	public function editor(): ?array { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		return $this->definition === null ? null : $this->definition->editor();
	}
}
