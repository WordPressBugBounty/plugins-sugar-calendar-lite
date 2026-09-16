<?php

namespace Sugar_Calendar\Emails;

/**
 * Prepare safe, readable values for plain-text administrator records.
 *
 * @since 3.14.0
 */
class AdminNotificationText {

	/**
	 * Sanitize one scalar value for an inline record.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return string
	 */
	public static function value( $value ): string {

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $value );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return trim( (string) $value );
	}

	/**
	 * Sanitize a scalar value while preserving readable line breaks.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return string
	 */
	public static function block( $value ): string {

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/\r\n?/', "\n", $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $value );

		$lines = explode( "\n", (string) $value );

		foreach ( $lines as &$line ) {
			$line = str_replace( "\xC2\xA0", ' ', $line );
			$line = preg_replace( '/[\t ]+/u', ' ', $line );
			$line = trim( (string) $line );
		}
		unset( $line );

		$value = implode( "\n", $lines );
		$value = preg_replace( '/\n{3,}/', "\n\n", $value );

		return trim( (string) $value );
	}

	/**
	 * Sanitize and join a location's name and address.
	 *
	 * @since 3.14.0
	 *
	 * @param array $location Location values.
	 *
	 * @return string
	 */
	public static function location( array $location ): string {

		$parts = [];

		foreach ( [ 'name', 'address' ] as $key ) {
			$value = self::value( $location[ $key ] ?? '' );

			if ( '' !== $value && ! in_array( $value, $parts, true ) ) {
				$parts[] = $value;
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Remove authored lines for empty optional Smart Tags.
	 *
	 * @since 3.14.0
	 *
	 * @param string $body         Record body.
	 * @param array  $values       Resolved tag values.
	 * @param array  $optional_tags Optional bare tag slugs.
	 *
	 * @return string
	 */
	public static function prune_empty_lines( string $body, array $values, array $optional_tags ): string {

		$lines = preg_split( '/\R/u', $body );

		if ( ! is_array( $lines ) ) {
			return $body;
		}

		$kept = [];

		foreach ( $lines as $line ) {
			$remove = false;

			foreach ( $optional_tags as $tag ) {
				if ( ! is_scalar( $tag ) ) {
					continue;
				}

				$slug = (string) $tag;

				if (
					'' !== $slug
					&& array_key_exists( $slug, $values )
					&& '' === $values[ $slug ]
					&& false !== strpos( $line, '{' . $slug . '}' )
				) {
					$remove = true;
					break;
				}
			}

			if ( ! $remove ) {
				$kept[] = $line;
			}
		}

		$body = implode( "\n", $kept );
		$body = preg_replace( '/\n{3,}/', "\n\n", $body );

		return trim( (string) $body );
	}
}
