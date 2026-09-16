<?php

namespace Sugar_Calendar\SmartTags;

/**
 * One `{tag}` placeholder: what it is called, how it is labelled, and how it resolves.
 *
 * Channel-agnostic on purpose. A tag knows nothing about email; the sender
 * hands it whatever context that sender already built.
 *
 * @since 3.14.0
 */
interface SmartTagInterface {

	/**
	 * Slug as authored, without braces.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function tag(): string;

	/**
	 * Editor dropdown label, in the owner's text domain.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Dropdown heading key: `order`, `event`, `ticket`, `attendee` or `rsvp`.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function group(): string;

	/**
	 * Resolve against the sender's context. Null leaves `{tag}` as typed.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context Sender-supplied context.
	 *
	 * @return string|null
	 */
	public function resolve( array $context ): ?string; // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.
}
