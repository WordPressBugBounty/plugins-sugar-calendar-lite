<?php

namespace Sugar_Calendar\SmartTags;

/**
 * A Smart Tag whose value comes from a closure over the sender's context.
 *
 * @since 3.14.0
 */
class SmartTag implements SmartTagInterface {

	/**
	 * Slug, without braces.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $tag;

	/**
	 * Dropdown label.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Dropdown heading key.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $group;

	/**
	 * Value producer: `function ( array $context )`.
	 *
	 * @since 3.14.0
	 *
	 * @var callable
	 */
	private $resolver;

	/**
	 * Constructor.
	 *
	 * @since 3.14.0
	 *
	 * @param string   $tag      Slug, without braces.
	 * @param string   $label    Dropdown label.
	 * @param string   $group    Dropdown heading key.
	 * @param callable $resolver Value producer.
	 */
	public function __construct( string $tag, string $label, string $group, callable $resolver ) {

		$this->tag      = $tag;
		$this->label    = $label;
		$this->group    = $group;
		$this->resolver = $resolver;
	}

	/**
	 * Slug as authored, without braces.
	 *
	 * @since 3.14.0
	 */
	public function tag(): string {

		return $this->tag;
	}

	/**
	 * Editor dropdown label.
	 *
	 * @since 3.14.0
	 */
	public function label(): string {

		return $this->label;
	}

	/**
	 * Dropdown heading key.
	 *
	 * @since 3.14.0
	 */
	public function group(): string {

		return $this->group;
	}

	/**
	 * Resolve against the sender's context.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context Sender-supplied context.
	 */
	public function resolve( array $context ): ?string { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		$value = call_user_func( $this->resolver, $context );

		if ( $value === null ) {
			return null;
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
