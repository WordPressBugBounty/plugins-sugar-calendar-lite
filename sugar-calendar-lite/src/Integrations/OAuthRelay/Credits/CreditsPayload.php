<?php
/**
 * Shared credits-maths formatting for the AI and integration credit services.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\OAuthRelay\Credits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Formats a raw `credits` sub-array into the total/used/remaining/reset/percentage
 * fields both `AiUsageService::formatted()` and `CreditsService::format_response()`
 * compute identically. Callers add their own `plan` and `show_upgrade`, which differ
 * between the two services.
 *
 * @since 3.15.0
 */
class CreditsPayload {

	/**
	 * Format the shared credits fields.
	 *
	 * @since 3.15.0
	 *
	 * @param array $credits Raw `credits` sub-array from the relay response.
	 *
	 * @return array `{ total, used, remaining, resets_at, reset_date, percentage, is_maxed }`.
	 */
	public static function format( array $credits ): array {

		$total     = (int) ( $credits['total'] ?? 0 );
		$used      = (int) ( $credits['used'] ?? 0 );
		$remaining = isset( $credits['remaining'] ) ? (int) $credits['remaining'] : max( 0, $total - $used );
		$resets_at = ( ! empty( $credits['resets_at'] ) && strtotime( $credits['resets_at'] ) !== false )
			? sanitize_text_field( $credits['resets_at'] )
			: null;

		return [
			'total'      => $total,
			'used'       => $used,
			'remaining'  => $remaining,
			'resets_at'  => $resets_at,
			'reset_date' => $resets_at !== null ? self::format_reset_date( $resets_at ) : '',
			'percentage' => $total > 0 ? min( (int) round( ( $used / $total ) * 100 ), 100 ) : 0,
			'is_maxed'   => $remaining <= 0 && $total > 0,
		];
	}

	/**
	 * The reset date in the site's format, or '' when unparseable.
	 *
	 * @since 3.15.0
	 *
	 * @param string $resets_at ISO 8601 reset timestamp.
	 *
	 * @return string
	 */
	private static function format_reset_date( string $resets_at ): string {

		$timestamp = strtotime( $resets_at );

		if ( $timestamp === false ) {
			return '';
		}

		$formatted = wp_date( get_option( 'date_format' ), $timestamp );

		return $formatted !== false ? $formatted : '';
	}
}
