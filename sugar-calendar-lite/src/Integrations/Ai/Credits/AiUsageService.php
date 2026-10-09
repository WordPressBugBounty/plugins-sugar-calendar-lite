<?php
/**
 * AI credit usage, cached per tier, in the shape the usage card renders.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai\Credits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Integrations\Ai\Chat;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsPayload;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\UsageBreakdown;
use Sugar_Calendar\Integrations\OAuthRelay\RelayLicenseType;
use Throwable;
use WP_Error;

/**
 * One cache shared by `GET /usage` and the License Usage card.
 *
 * @since 3.15.0
 */
class AiUsageService {

	const CACHE_TTL       = 5 * MINUTE_IN_SECONDS;
	const ERROR_CACHE_TTL = MINUTE_IN_SECONDS;

	/**
	 * Palette index per credit category, kept apart from the integration map so
	 * the two card's colors cannot shift each other.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const COLOR_MAP_OPTION = 'sugar_calendar_ai_credit_colors';

	/**
	 * License levels with no plan above them that grants more AI credits.
	 *
	 * The relay gives Agency and Elite the same 5,000 AI credits.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const TOP_PLANS = [ 'agency', 'elite' ];

	/**
	 * Relay client.
	 *
	 * @since 3.15.0
	 *
	 * @var Chat
	 */
	private $chat;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param Chat|null $chat Relay client.
	 */
	public function __construct( ?Chat $chat = null ) {

		$this->chat = $chat ?? new Chat();
	}

	/**
	 * Per-tier cache key.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public static function cache_key(): string {

		return 'sc_ai_usage_' . RelayLicenseType::get();
	}

	/**
	 * The cached payload, or null when there is none.
	 *
	 * @since 3.15.0
	 *
	 * @return array|null
	 */
	public function cached(): ?array {

		$cached = get_transient( self::cache_key() );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Cached payload, else one relay call. Failures are cached briefly too, so a
	 * down relay is not re-hit on every render.
	 *
	 * @since 3.15.0
	 *
	 * @return array `{ plan, credits, license_type }` or `{ error: { code, status } }`.
	 */
	public function fetch(): array {

		$cached = $this->cached();

		if ( $cached !== null ) {
			return $cached;
		}

		try {
			$usage = $this->chat->usage();
		} catch ( Throwable $e ) {
			$usage = new WP_Error( 'sc_ai_usage_exception', '', [ 'status' => 500 ] );
		}

		if ( is_wp_error( $usage ) ) {
			$data  = $usage->get_error_data();
			$error = [
				'code'   => $usage->get_error_code(),
				'status' => isset( $data['status'] ) ? (int) $data['status'] : 502,
			];

			set_transient( self::cache_key(), [ 'error' => $error ], self::ERROR_CACHE_TTL );

			return [ 'error' => $error ];
		}

		$plan    = isset( $usage['plan'] ) ? (string) $usage['plan'] : '';
		$credits = isset( $usage['credits'] ) && is_array( $usage['credits'] ) ? $usage['credits'] : null;

		// Lite's AI credit grant is lifetime even though the relay sends a
		// `resets_at`; dropping it here, once, keeps every reader of this cache
		// (the settings card, the REST /usage response) from re-deriving that rule.
		if ( $credits !== null && self::normalize_plan( $plan ) === 'lite' ) {
			unset( $credits['resets_at'] );
		}

		$out = [
			'plan'         => $plan,
			'credits'      => $credits,
			// Per-category spend, keyed by category slug, when the relay reports it.
			'usage'        => isset( $usage['usage'] ) && is_array( $usage['usage'] ) ? $usage['usage'] : [],
			'license_type' => RelayLicenseType::get(),
		];

		set_transient( self::cache_key(), $out, self::CACHE_TTL );

		return $out;
	}

	/**
	 * Usage in `CreditsService::format_response()` shape, or null when unavailable.
	 *
	 * `usage` carries the relay's per-category spend when it sends one, which is
	 * what the card's legend renders; without it the card is just the bar.
	 *
	 * @since 3.15.0
	 *
	 * @return array|null
	 */
	public function formatted(): ?array {

		$payload = $this->fetch();

		if ( isset( $payload['error'] ) || empty( $payload['credits'] ) || ! is_array( $payload['credits'] ) ) {
			return null;
		}

		$plan    = self::normalize_plan( $payload['plan'] ?? 'lite' );
		$credits = $payload['credits'];

		// Defensive: a transient cached before this rule shipped can still carry
		// `resets_at` for a lite plan. fetch() is the normal source of the drop.
		if ( $plan === 'lite' ) {
			unset( $credits['resets_at'] );
		}

		$out = [ 'plan' => $plan ] + CreditsPayload::format( $credits );

		$out['show_upgrade'] = self::upgrade() !== null;
		// Per-category breakdown, when the relay sends one; an absent or
		// empty map just leaves the card without a legend.
		$out['usage'] = UsageBreakdown::rows( $payload['usage'] ?? [], $out['total'], self::COLOR_MAP_OPTION );

		return $out;
	}

	/**
	 * Sanitize a relay plan slug, defaulting to `lite` when empty.
	 *
	 * @since 3.15.0
	 *
	 * @param string $plan Raw plan slug.
	 *
	 * @return string
	 */
	private static function normalize_plan( string $plan ): string {

		$slug = sanitize_key( $plan );

		return $slug !== '' ? $slug : 'lite';
	}

	/**
	 * Where a user can get more AI credits, or null when no plan grants more.
	 *
	 * Reads the plugin's own license level, not the relay's `plan`, which does not
	 * always match it.
	 *
	 * @since 3.15.0
	 *
	 * @param array $utm UTM parameters for `Helpers::get_utm_url()`.
	 *
	 * @return array|null `{ url, label }`.
	 */
	public static function upgrade( array $utm = [] ): ?array {

		if ( ! sugar_calendar()->is_pro() ) {
			return [
				'url'   => Helpers::get_upgrade_link( $utm ),
				'label' => __( 'Upgrade to Pro', 'sugar-calendar-lite' ),
			];
		}

		if ( RelayLicenseType::get() === 'pro' && in_array( sugar_calendar()->get_license_type(), self::TOP_PLANS, true ) ) {
			return null;
		}

		return [
			'url'   => Helpers::get_utm_url( 'https://sugarcalendar.com/account/licenses/', $utm ),
			'label' => __( 'Upgrade for more', 'sugar-calendar-lite' ),
		];
	}
}
