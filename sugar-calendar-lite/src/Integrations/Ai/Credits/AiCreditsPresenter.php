<?php
/**
 * Presenter for the AI usage card.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai\Credits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsPresenter;

/**
 * Same card maths as the integration presenter; the headings say AI.
 *
 * Lite is lifetime even though the relay sends a `resets_at` for it —
 * `AiUsageService` drops that field for a lite plan, so `is_monthly()` here
 * needs no override.
 *
 * @since 3.15.0
 */
class AiCreditsPresenter extends CreditsPresenter {

	/**
	 * Segment palette — purple progression, matching the card's purple bar.
	 * The first two slots are the legend colors in Figma node 11686:138168.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const PALETTE = [
		'#7A30E2',
		'#B885FF',
		'#5A1DB0',
		'#9459EB',
		'#CFB1FF',
		'#48168F',
		'#8843E8',
		'#C29AFF',
		'#6B26C9',
		'#A874F3',
	];

	/**
	 * Build from the usage service.
	 *
	 * @since 3.15.0
	 *
	 * @param AiUsageService $service Usage service.
	 *
	 * @return self
	 */
	public static function from_usage_service( AiUsageService $service ): self {

		return new self( $service->formatted() );
	}

	/**
	 * Credit categories the relay reports usage for. Anything it sends that is
	 * not listed falls back to the humanized slug.
	 *
	 * @since 3.15.0
	 *
	 * @param string $slug Usage slug.
	 *
	 * @return string
	 */
	protected function segment_name( string $slug ): string {

		$labels = [
			'event_generator' => __( 'Event Generator', 'sugar-calendar-lite' ),
			'image_generator' => __( 'Image Generator', 'sugar-calendar-lite' ),
		];

		return $labels[ $slug ] ?? ucwords( str_replace( '_', ' ', $slug ) );
	}

	/**
	 * Card heading.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function heading(): string {

		if ( $this->is_monthly() ) {
			return sprintf(
				/* translators: %s - usage period month and year, e.g. "December 2026". */
				__( 'AI Usage for %s', 'sugar-calendar-lite' ),
				$this->usage_period_label()
			);
		}

		return __( 'Lifetime AI Usage', 'sugar-calendar-lite' );
	}
}
