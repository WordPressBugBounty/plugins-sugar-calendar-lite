<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;
use Sugar_Calendar\Admin\SetupChecklist\PromotionsRenderer;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\SetupChecklist\Config;

/**
 * "Take Your Events to the Next Level", Lite only.
 *
 * @since 3.18.0
 */
class ProUpsell extends AbstractWidget {

	/**
	 * Column.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'main';

	/**
	 * Sort position.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 40;

	/**
	 * The eight of the Setup Checklist's eleven upsell cards it lists.
	 *
	 * @since 3.18.0
	 */
	const FEATURE_KEYS = [
		'recurring-events',
		'event-ticketing',
		'rsvp',
		'speakers',
		'venues',
		'calendar-feeds',
		'priority-support',
		'zapier',
	];

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'pro_upsell';
	}

	/**
	 * Widget title.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __( 'Take Your Events to the Next Level', 'sugar-calendar-lite' );
	}

	/**
	 * Visible on Lite only.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		return new WidgetState( ! $access->is_pro(), 'data' );
	}

	/**
	 * The Lite discount beside the title.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_head_aside( string $variant, array $data, AccessContext $access ): string {

		return sprintf(
			'<div class="sc:flex sc:min-h-[22px] sc:items-center sc:gap-[7px] sc-reset">' .
				'<img class="sc:shrink-0 sc-reset" src="%2$s" width="16" height="16" alt="">' .
				'<div class="sc:font-[family-name:var(--sc-typography-font-family-sans)] sc:text-[length:var(--sc-typography-font-size-14,14px)] sc:font-medium sc:leading-[var(--sc-typography-line-height-20,20px)] sc:text-[var(--sc-text-tertiary,#787c82)] sc-reset">%1$s</div>' .
			'</div>',
			PromotionsRenderer::get_discount_html( 'sc:text-[var(--sc-text-success,#008a20)]' ),
			esc_url( SC_PLUGIN_ASSETS_URL . 'images/setup-checklist/discount-badge.svg' )
		);
	}

	/**
	 * Render the features.
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

		return Templates::get_html( 'widgets/pro-upsell', [ 'features' => $this->get_features() ] );
	}

	/**
	 * Render the footer with the Upgrade button.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_footer( string $variant, array $data, AccessContext $access ): string {

		return sprintf(
			'<footer class="sc:self-stretch sc:pl-[var(--sc-spacing-30,30px)] sc:pr-[var(--sc-spacing-30,30px)] sc:pt-[var(--sc-spacing-20,20px)] sc:pb-[var(--sc-spacing-20,20px)] sc:bg-[var(--sc-surface-background-light,#f7f7f7)] sc:inline-flex sc:justify-between sc:items-center sc:gap-[var(--sc-spacing-15,15px)] sc:overflow-hidden sc:max-phone:flex-col sc:max-phone:items-start sc-reset">' .
				'<div class="sc:flex sc:items-center sc:gap-1 sc-reset">' .
					'<p class="sc:text-[var(--sc-text-primary,#2c3338)] sc:text-[15px] sc:font-normal sc:leading-[22.50px] sc-reset">%1$s</p>' .
				'</div>' .
				'<div class="sc:flex sc:justify-end sc:items-center sc:gap-[var(--sc-spacing-10,10px)] sc-reset">' .
					'<a class="sc-button sc-button--primary sc-reset-deep sc:text-[14px] sc:font-[500] sc:tracking-[0px] sc:leading-[16.94px]" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s<span class="sc:icon-[fa6-solid--arrow-right] sc-button__icon" aria-hidden="true"></span></a>' .
				'</div>' .
			'</footer>',
			esc_html__( 'Plus dozens of other powerful features to help your events succeed.', 'sugar-calendar-lite' ),
			esc_url(
				Helpers::get_upgrade_link(
					[
						'medium'  => self::UTM_MEDIUM,
						'content' => 'pro-upsell',
					]
				)
			),
			esc_html__( 'Upgrade to Pro', 'sugar-calendar-lite' )
		);
	}

	/**
	 * The listed upsell cards, in the checklist's order.
	 *
	 * @since 3.18.0
	 *
	 * @return array Cards: `key`, `title`, `description` and `icon`.
	 */
	private function get_features(): array {

		return array_values(
			array_filter(
				Config::get_upsell_cards(),
				static function ( $card ) {

					return in_array( $card['key'], self::FEATURE_KEYS, true );
				}
			)
		);
	}
}
