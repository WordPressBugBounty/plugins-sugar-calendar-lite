<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Event;
use Throwable;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_setting;
use function Sugar_Calendar\AddOn\Ticketing\Shortcodes\invalid_receipt_link_response;
use function Sugar_Calendar\AddOn\Ticketing\Shortcodes\render_receipt_body;

/**
 * Issue and resolve read-only receipt preview links.
 *
 * @since 3.14.0
 */
final class PreviewReceipt {

	/**
	 * Receipt preview query argument.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const QUERY_ARG = 'sc_et_preview_receipt';

	/**
	 * Dedicated transient prefix for receipt previews.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'sugar_calendar_email_preview_receipt_'; // phpcs:ignore PHPCompatibility.Classes.NewConstVisibility.Found -- Sugar Calendar requires PHP 7.4+.

	/**
	 * Issue a read-only preview URL for an order.
	 *
	 * @since 3.14.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return string
	 */
	public static function url( int $order_id ): string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- Issuance validates each prerequisite and cleans up failed final URLs.

		if ( $order_id <= 0 ) {
			return '';
		}

		$receipt_page = absint( get_setting( 'receipt_page' ) );

		if ( $receipt_page <= 0 ) {
			return '';
		}

		$base_url = get_permalink( $receipt_page );

		if ( ! is_string( $base_url ) || $base_url === '' ) {
			return '';
		}

		try {
			$token = bin2hex( random_bytes( 32 ) );
		} catch ( Throwable $e ) {
			return '';
		}

		/** This filter is documented in includes/common/functions.php */
		$ttl = (int) apply_filters( 'sc_et_receipt_link_ttl', 72 * HOUR_IN_SECONDS, $order_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the established public ticketing filter for existing integrations.
		$key = self::transient_key( $token );

		if ( ! set_transient( $key, $order_id, max( 1, $ttl ) ) ) {
			return '';
		}

		$url = add_query_arg( [ self::QUERY_ARG => $token ], $base_url );

		if ( ! is_string( $url ) || $url === '' ) {
			delete_transient( $key );

			return '';
		}

		return $url;
	}

	/**
	 * Resolve a receipt preview token without consuming it.
	 *
	 * @since 3.14.0
	 *
	 * @param string $token Receipt preview token.
	 *
	 * @return int
	 */
	public static function resolve( string $token ): int {

		if ( preg_match( '/^[0-9a-f]{64}$/Di', $token ) !== 1 ) {
			return 0;
		}

		$order_id = get_transient( self::transient_key( strtolower( $token ) ) );

		if ( ! is_int( $order_id ) && ! ( is_string( $order_id ) && ctype_digit( $order_id ) ) ) {
			return 0;
		}

		$order_id = (int) $order_id;

		return $order_id > 0 ? $order_id : 0;
	}

	/**
	 * Build the canonical transient key without storing the raw token.
	 *
	 * @since 3.14.0
	 *
	 * @param string $token Receipt preview token.
	 *
	 * @return string
	 */
	public static function transient_key( string $token ): string {

		return self::TRANSIENT_PREFIX . hash( 'sha256', strtolower( $token ) );
	}

	/**
	 * Render a read-only receipt preview.
	 *
	 * @since 3.14.0
	 *
	 * @param string $token Receipt preview token.
	 *
	 * @return string
	 */
	public static function render( string $token ): string {

		$order_id = self::resolve( $token );

		if ( $order_id <= 0 ) {
			return invalid_receipt_link_response();
		}

		$order = get_order( $order_id );

		if ( empty( $order ) || empty( $order->id ) ) {
			return invalid_receipt_link_response();
		}

		/** This filter is documented in includes/common/shortcodes.php */
		$event = apply_filters( 'sc_et_receipt_shortcode_event', sugar_calendar_get_event( $order->event_id ), $order ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the established public ticketing filter for existing integrations.

		if ( ! $event instanceof Event || empty( $event->id ) ) {
			return invalid_receipt_link_response();
		}

		return render_receipt_body( $order, $event, true );
	}
}
