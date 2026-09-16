<?php

namespace Sugar_Calendar\Emails;

/**
 * Default answers for the optional half of EmailInterface.
 *
 * The three boolean defaults are the released listing filter's normalization
 * defaults, so an object that says nothing means what a filter row that says
 * nothing means. Status management and preview are opt-in.
 *
 * @since 3.14.0
 */
abstract class AbstractEmail implements EmailInterface {

	/**
	 * Not an upsell row.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_pro_only(): bool {

		return false;
	}

	/**
	 * A sender exists.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_sendable(): bool {

		return true;
	}

	/**
	 * Opt in only in the release where the sender reads EmailStatus.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_status_managed(): bool {

		return false;
	}

	/**
	 * Opt in only when a preview document provider exists.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_previewable(): bool {

		return false;
	}

	/**
	 * No Modern document.
	 *
	 * @since 3.14.0
	 *
	 * @return string|null
	 */
	public function layout(): ?string { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		return null;
	}

	/**
	 * No smart tags.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public function smart_tags(): array {

		return [];
	}

	/**
	 * No edit screen.
	 *
	 * @since 3.14.0
	 *
	 * @return array|null
	 */
	public function editor(): ?array { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		return null;
	}
}
