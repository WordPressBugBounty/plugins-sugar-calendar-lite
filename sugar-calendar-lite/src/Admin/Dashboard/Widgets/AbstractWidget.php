<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;

/**
 * Widget contract and the shared card shell.
 *
 * @since 3.18.0
 */
abstract class AbstractWidget {

	/**
	 * Column: `main` or `sidebar`.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'main';

	/**
	 * Default sort position within the column.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 10;

	/**
	 * Whether the body drops its padding (`data-flush` on the card's `__body`).
	 *
	 * @since 3.18.0
	 */
	const BODY_FLUSH = false;

	/**
	 * UTM medium on every link the Dashboard sends to sugarcalendar.com.
	 *
	 * @since 3.18.0
	 */
	const UTM_MEDIUM = 'dashboard';

	/**
	 * The settings each user's choice is remembered for, and their values; the first is the default.
	 *
	 * Remembered UI state only (`UserPreferences::SETTINGS_META_KEY`); layout choices belong in screen options.
	 *
	 * @since 3.18.0
	 */
	const SETTINGS = [];

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	abstract public function get_id(): string;

	/**
	 * Widget title. Empty renders no title row unless a head is supplied.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	abstract public function get_title(): string;

	/**
	 * Widget state for the given access context.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	abstract public function get_state( AccessContext $access ): WidgetState;

	/**
	 * Render the widget body.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data from `Stats::get_widget_data()`.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	abstract protected function render_body( string $variant, array $data, AccessContext $access ): string;

	/**
	 * Render the widget head in place of the plain title. Empty keeps the title.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_head( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return '';
	}

	/**
	 * Render the trailing edge of the head, beside the title.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_head_aside( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return '';
	}

	/**
	 * Render the widget footer. Empty hides it.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_footer( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return '';
	}

	/**
	 * Extra classes for the card root.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return array
	 */
	protected function get_extra_classes( string $variant, array $data, AccessContext $access ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return [];
	}

	/**
	 * Extra attributes for the card root, as `name => value`.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return array
	 */
	protected function get_root_attributes( string $variant, array $data, AccessContext $access ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return [];
	}

	/**
	 * Whether the body drops its padding. Override when it depends on the state.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return bool
	 */
	protected function is_body_flush( string $variant, array $data, AccessContext $access ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default; the signature is the widget contract.

		return static::BODY_FLUSH;
	}

	/**
	 * Render the widget into the card shell.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	public function render( string $variant, array $data, AccessContext $access ): string {

		return Templates::get_html(
			'widget',
			[
				'id'            => $this->get_id(),
				'variant'       => $variant,
				'extra_classes' => $this->get_extra_classes( $variant, $data, $access ),
				'attributes'    => $this->get_root_attributes( $variant, $data, $access ),
				'title'         => $this->get_title(),
				'head'          => $this->render_head( $variant, $data, $access ),
				'head_aside'    => $this->render_head_aside( $variant, $data, $access ),
				'body'          => $this->render_body( $variant, $data, $access ),
				'flush'         => $this->is_body_flush( $variant, $data, $access ),
				'footer'        => $this->render_footer( $variant, $data, $access ),
			]
		);
	}

	/**
	 * Column this widget renders in.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_column(): string {

		return static::COLUMN;
	}

	/**
	 * The user's remembered settings, each falling back to its default.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return array Setting => value, for each of `SETTINGS`.
	 */
	public function get_settings( AccessContext $access ): array {

		$defaults = array_map(
			static function ( $values ) {

				return $values[0];
			},
			static::SETTINGS
		);

		return array_merge( $defaults, $this->sanitize_settings( $access->get_widget_settings( $this->get_id() ) ) );
	}

	/**
	 * Keep only the settings this widget remembers, with a value it offers.
	 *
	 * @since 3.18.0
	 *
	 * @param array $settings Settings to check.
	 *
	 * @return array
	 */
	public function sanitize_settings( array $settings ): array {

		$allowed = static::SETTINGS;

		return array_filter(
			array_intersect_key( $settings, $allowed ),
			static function ( $value, $key ) use ( $allowed ) {

				return in_array( $value, $allowed[ $key ], true );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}
}
