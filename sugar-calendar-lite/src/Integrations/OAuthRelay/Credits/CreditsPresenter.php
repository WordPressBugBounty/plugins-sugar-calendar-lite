<?php

namespace Sugar_Calendar\Integrations\OAuthRelay\Credits;

use Sugar_Calendar\Integrations\IntegrationCapabilityInterface;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;

/**
 * View-ready credits values for the License & Usage tab, the Integrations
 * indicator, and the out-of-credits notice. Owns number formatting, percentage,
 * period token, per-integration segments, and stable per-integration colors.
 *
 * All getter return values are RAW (unescaped); callers must escape at the
 * point of output. The segments() array fields (name/slug/color) are likewise raw.
 *
 * @since 3.12.0
 */
class CreditsPresenter {

	/**
	 * Segment palette — navy → blue progression (10 slots).
	 *
	 * @since 3.12.0
	 *
	 * @var string[]
	 */
	const PALETTE = [
		'#0F314D',
		'#14446B',
		'#1A5789',
		'#206AA7',
		'#267DC5',
		'#3A92D9',
		'#58A2DF',
		'#76B3E5',
		'#94C4EB',
		'#B2D4F0',
	];

	/**
	 * Formatted credits (or null when unavailable).
	 *
	 * @since 3.12.0
	 *
	 * @var array|null
	 */
	private $credits;

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 *
	 * @param array|null $credits Formatted credits from CreditsService.
	 */
	public function __construct( $credits ) {

		$this->credits = is_array( $credits ) ? $credits : null;
	}

	/**
	 * Build from the service's fresh read (warms cache + fires the alert).
	 *
	 * @since 3.12.0
	 *
	 * @param CreditsService $service Credits service.
	 *
	 * @return self
	 */
	public static function from_service( CreditsService $service ): self {

		return new self( $service->get_credits() );
	}

	/**
	 * Whether there is credit data to render.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function has_data(): bool {

		return $this->credits !== null;
	}

	/**
	 * Whether usage has hit the limit.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_maxed(): bool {

		return $this->has_data() && ! empty( $this->credits['is_maxed'] );
	}

	/**
	 * Monthly (Pro/licensed) when a reset date is present.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_monthly(): bool {

		return $this->has_data() && ! empty( $this->credits['resets_at'] );
	}

	/**
	 * Credits used so far.
	 *
	 * @since 3.12.0
	 *
	 * @return int
	 */
	public function used(): int {

		return $this->has_data() ? (int) $this->credits['used'] : 0;
	}

	/**
	 * Total credit limit.
	 *
	 * @since 3.12.0
	 *
	 * @return int
	 */
	public function limit(): int {

		return $this->has_data() ? (int) $this->credits['total'] : 0;
	}

	/**
	 * Usage percentage of the total limit.
	 *
	 * @since 3.12.0
	 *
	 * @return int
	 */
	public function percentage(): int {

		return $this->has_data() ? (int) $this->credits['percentage'] : 0;
	}

	/**
	 * Used credits, i18n-formatted for display.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function used_display(): string {

		return number_format_i18n( $this->used() );
	}

	/**
	 * Total credit limit, i18n-formatted for display.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function limit_display(): string {

		return number_format_i18n( $this->limit() );
	}

	/**
	 * Upgrade CTA visibility (hidden on the top tier).
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function show_upgrade(): bool {

		return $this->has_data() && ! empty( $this->credits['show_upgrade'] );
	}

	/**
	 * Section heading. Monthly: "Usage for {Month} {Year}"; lifetime: "Lifetime Usage".
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function heading(): string {

		if ( $this->is_monthly() ) {
			return sprintf(
				/* translators: %s - usage period month and year, e.g. "December 2026". */
				__( 'Usage for %s', 'sugar-calendar-lite' ),
				$this->usage_period_label()
			);
		}

		return __( 'Lifetime Usage', 'sugar-calendar-lite' );
	}

	/**
	 * The usage period label — the month/year ending on the day before the reset.
	 *
	 * The relay's `resets_at` is the start of the next period (the 1st), so the
	 * period being shown ends the day before. Falls back to the current month.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function usage_period_label(): string {

		if ( $this->has_data() && ! empty( $this->credits['resets_at'] ) ) {
			$reset_time = strtotime( $this->credits['resets_at'] );

			if ( $reset_time !== false ) {
				return wp_date( 'F Y', strtotime( '-1 day', $reset_time ) );
			}
		}

		return wp_date( 'F Y' );
	}

	/**
	 * Reset line. Pro: "Resets in N days" / "Resets today"; Lite: "".
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function reset_text(): string {

		if ( ! $this->is_monthly() ) {
			return '';
		}

		$resets = strtotime( $this->credits['resets_at'] );

		if ( $resets === false ) {
			return '';
		}

		$days = (int) ceil( ( $resets - time() ) / DAY_IN_SECONDS );

		if ( $days <= 0 ) {
			return __( 'Resets today', 'sugar-calendar-lite' );
		}

		return sprintf(
			/* translators: %d - number of days until the credit reset. */
			_n( 'Resets in %d day', 'Resets in %d days', $days, 'sugar-calendar-lite' ),
			$days
		);
	}

	/**
	 * Dismissal/dedup period token: the reset date, or 'lifetime' for Lite.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function period_token(): string {

		if ( $this->has_data() && ! empty( $this->credits['resets_at'] ) ) {
			return (string) $this->credits['resets_at'];
		}

		return 'lifetime';
	}

	/**
	 * Per-integration segments for the bar + legend, with stable colors.
	 *
	 * @since 3.12.0
	 *
	 * @return array[] Each: slug, name, count, percentage, color, index.
	 */
	public function segments(): array {

		if ( ! $this->has_data() || empty( $this->credits['usage'] ) ) {
			return [];
		}

		$total    = (int) $this->credits['total'];
		$registry = IntegrationCapabilityRegistry::instance();

		$segments = [];

		foreach ( $this->credits['usage'] as $row ) {
			$slug       = $row['slug'];
			$index      = (int) ( $row['index'] ?? 0 );
			$capability = $registry->find( IntegrationCapabilityInterface::class, $slug );

			$segments[] = [
				'slug'       => $slug,
				'name'       => $capability ? $capability->get_display_name() : ucwords( str_replace( '_', ' ', $slug ) ),
				'count'      => (int) $row['count'],
				'percentage' => $total > 0 ? ( (int) $row['count'] / $total ) * 100 : 0,
				'color'      => self::PALETTE[ $index % count( self::PALETTE ) ],
				'index'      => $index,
			];
		}

		// Stable order by color index.
		usort(
			$segments,
			static function ( $a, $b ) {

				return $a['index'] <=> $b['index'];
			}
		);

		return $segments;
	}

	/**
	 * Display-formatted percentage for one segments() row (rounded to 1 decimal,
	 * trailing .0 dropped).
	 *
	 * Reuses the segment's own `percentage` field rather than recomputing it from
	 * count/total — segments() already does that division once; redoing it here
	 * risked the two drifting apart on rounding.
	 *
	 * @since 3.12.0
	 *
	 * @param array $segment One row from segments().
	 *
	 * @return string
	 */
	public function segment_percentage_display( array $segment ): string {

		$percentage = round( (float) ( $segment['percentage'] ?? 0 ), 1 );
		$decimals   = fmod( $percentage, 1.0 ) === 0.0 ? 0 : 1;

		return number_format_i18n( $percentage, $decimals );
	}
}
