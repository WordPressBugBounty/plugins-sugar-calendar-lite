<?php

namespace Sugar_Calendar\Helpers;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\currency_filter;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\format_amount;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_decimal_count;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\sanitize_amount;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_setting;

/**
 * Formats amounts with the store's currency settings: symbol, position, separators and decimals.
 *
 * The store settings are the ticketing feature's, which core loads in both editions. Print the
 * result with `wp_kses_post()`: a currency filter can return markup.
 *
 * @since 3.18.0
 */
class Money {

	/**
	 * Format an amount with the store's decimal places, e.g. `$180.00`.
	 *
	 * @since 3.18.0
	 *
	 * @param float $amount Amount in major units.
	 *
	 * @return string
	 */
	public static function format( float $amount ): string {

		return self::with_currency( $amount, true );
	}

	/**
	 * Format an amount rounded to whole units, e.g. `$27,035`.
	 *
	 * @since 3.18.0
	 *
	 * @param float $amount Amount in major units.
	 *
	 * @return string
	 */
	public static function format_whole( float $amount ): string {

		return self::with_currency( $amount, false );
	}

	/**
	 * The store's money format, for amounts formatted in the browser as `format()` does.
	 *
	 * The symbol is decoded: the browser draws it as text, not HTML.
	 *
	 * @since 3.18.0
	 *
	 * @return array `prefix` and `suffix` (the symbol on either side), `decimals`, and the `decimal` and `thousands` separators.
	 */
	public static function get_format(): array {

		// As in with_currency(): currency_filter() puts the symbol around sanitize_amount()'s number.
		$symbol = explode( (string) sanitize_amount( 1 ), self::get_currency_text( 1 ), 2 );

		// format() runs the per-amount decimals filter; the browser formats every point with one count. The
		// sample has cents, so a snippet that drops them only on whole amounts cannot round a point that has some.
		/** This filter is documented in includes/common/Features/EventTicketing/includes/common/functions.php */
		$decimals = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sc_et_format_amount_decimals', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the established public ticketing filter for existing integrations.
			get_decimal_count(),
			1.5
		);

		return [
			'prefix'    => $symbol[0],
			'suffix'    => $symbol[1] ?? '',
			'decimals'  => (int) $decimals,
			'decimal'   => (string) get_setting( 'decimal_separator', '.' ),
			'thousands' => (string) get_setting( 'thousands_separator', ',' ),
		];
	}

	/**
	 * Format the number and add the store's currency symbol.
	 *
	 * @since 3.18.0
	 *
	 * @param float $amount   Amount in major units.
	 * @param bool  $decimals Whether to show the store's decimal places.
	 *
	 * @return string
	 */
	private static function with_currency( float $amount, bool $decimals ): string {

		// currency_filter() strips thousands separators (via sanitize_amount()), so put the
		// formatted number back; the symbol and its position still come from the store.
		return str_replace(
			sanitize_amount( $amount ),
			format_amount( $amount, $decimals ),
			self::get_currency_text( $amount )
		);
	}

	/**
	 * The amount with the store's symbol, as currency_filter() places it, with the symbol decoded.
	 *
	 * Decoded before anything searches it: a numeric entity can hold the amount's digits, as KRW's
	 * `&#8361;` holds `1` and `8361`.
	 *
	 * @since 3.18.0
	 *
	 * @param float $amount Amount in major units.
	 *
	 * @return string
	 */
	private static function get_currency_text( float $amount ): string {

		return html_entity_decode( (string) currency_filter( $amount ), ENT_QUOTES, 'UTF-8' );
	}
}
