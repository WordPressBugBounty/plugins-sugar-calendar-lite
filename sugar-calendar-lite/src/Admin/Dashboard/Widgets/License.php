<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;
use Sugar_Calendar\Helpers\Helpers;

/**
 * Edition and version strip.
 *
 * The Pro subclass adds the license states.
 *
 * @since 3.18.0
 */
class License extends AbstractWidget {

	/**
	 * Column.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'sidebar';

	/**
	 * Sort position.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 10;

	/**
	 * The strip and its message run edge to edge.
	 *
	 * @since 3.18.0
	 */
	const BODY_FLUSH = true;

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'license';
	}

	/**
	 * Widget title; the strip has no title row.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_title(): string {

		return '';
	}

	/**
	 * Lite has one strip.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		return new WidgetState( true, 'lite' );
	}

	/**
	 * Render the strip.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string {

		return Templates::get_html(
			'widgets/license',
			[
				'type'    => $this->get_type( $variant ),
				'icon'    => $this->get_icon( $variant ),
				'name'    => $this->get_name(),
				'version' => SC_PLUGIN_VERSION,
				'cta'     => $this->get_license_cta( $variant ),
				'notice'  => $this->get_notice( $variant ),
			]
		);
	}

	/**
	 * Edition name.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	protected function get_name(): string {

		return __( 'Sugar Calendar Events Lite', 'sugar-calendar-lite' );
	}

	/**
	 * The recipe's `data-type` for the variant.
	 *
	 * @since 3.18.0
	 *
	 * @param string $variant Variant.
	 *
	 * @return string
	 */
	protected function get_type( string $variant ): string {

		return 'lite';
	}

	/**
	 * The icon before the name, a Font Awesome icon name such as `fa6-solid--crown`; empty for none.
	 *
	 * @since 3.18.0
	 *
	 * @param string $variant Variant.
	 *
	 * @return string
	 */
	protected function get_icon( string $variant ): string {

		return '';
	}

	/**
	 * The strip's button, if any.
	 *
	 * @since 3.18.0
	 *
	 * @param string $variant Variant.
	 *
	 * @return array `label`, `url`, `style` (the `sc-button--` modifier), `target_blank`; empty for none.
	 */
	protected function get_license_cta( string $variant ): array {

		return [
			'label'        => __( 'Upgrade to Pro', 'sugar-calendar-lite' ),
			'url'          => Helpers::get_upgrade_link(
				[
					'medium'  => self::UTM_MEDIUM,
					'content' => 'license-upgrade',
				]
			),
			'style'        => 'primary',
			'target_blank' => true,
		];
	}

	/**
	 * The notice under the strip; empty for none.
	 *
	 * @since 3.18.0
	 *
	 * @param string $variant Variant.
	 *
	 * @return string
	 */
	protected function get_notice( string $variant ): string {

		return '';
	}
}
