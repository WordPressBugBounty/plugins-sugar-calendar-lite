<?php

namespace Sugar_Calendar\Admin\Dashboard;

/**
 * Whether a widget renders, and in which variant.
 *
 * @since 3.18.0
 */
class WidgetState {

	/**
	 * Whether the widget renders.
	 *
	 * @since 3.18.0
	 *
	 * @var bool
	 */
	private $visible;

	/**
	 * Template variant, added to the card's state class.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	private $variant;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param bool   $visible Whether the widget renders.
	 * @param string $variant Template variant.
	 */
	public function __construct( bool $visible, string $variant = '' ) {

		$this->visible = $visible;
		$this->variant = $variant;
	}

	/**
	 * Whether the widget renders.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function is_visible(): bool {

		return $this->visible;
	}

	/**
	 * Template variant.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_variant(): string {

		return $this->variant;
	}
}
