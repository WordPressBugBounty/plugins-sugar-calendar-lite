<?php

namespace Sugar_Calendar\SmartTags;

/**
 * Substitute `{tag}` placeholders, once, without ever re-scanning a result.
 *
 * Two entry points because the two senders need different things. Ticketing
 * resolves lazily per matched tag, so a tag nobody authored never runs its
 * callback (`{receipt_url}` issues a live credential). RSVP needs every value
 * up front, because it prunes authored lines for empty optional tags before it
 * substitutes anything.
 *
 * @since 3.14.0
 */
class SmartTagResolver {

	/**
	 * The authored-tag grammar. One definition for every consumer.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const PATTERN = '/{([A-z0-9\-\_]+)}/s';

	/**
	 * Resolve every authored tag that the given set defines.
	 *
	 * An unknown slug, and a tag whose resolve() returns null, stay exactly as
	 * typed. A resolved value is never scanned again, so a value that happens to
	 * contain `{another_tag}` is delivered literally.
	 *
	 * @since 3.14.0
	 *
	 * @param string              $text    Authored text.
	 * @param SmartTagInterface[] $tags    Tags keyed by slug.
	 * @param array               $context Sender-supplied context.
	 *
	 * @return string
	 */
	public static function resolve( string $text, array $tags, array $context ): string {

		if ( empty( $tags ) ) {
			return $text;
		}

		$resolved = preg_replace_callback(
			self::PATTERN,
			static function ( $matches ) use ( $tags, $context ) {

				$slug = $matches[1];

				if ( ! isset( $tags[ $slug ] ) ) {
					return $matches[0];
				}

				$value = $tags[ $slug ]->resolve( $context );

				return $value === null ? $matches[0] : $value;
			},
			$text
		);

		// preg_replace_callback() returns null only on an engine error, such as a
		// backtrack-limit overrun on a very large document. Losing the body is the
		// worse outcome.
		return is_string( $resolved ) ? $resolved : $text;
	}

	/**
	 * Resolve every tag in the set, whether or not it was authored.
	 *
	 * @since 3.14.0
	 *
	 * @param SmartTagInterface[] $tags    Tags keyed by slug.
	 * @param array               $context Sender-supplied context.
	 *
	 * @return array Slug => resolved string. A declining tag yields an empty string.
	 */
	public static function values( array $tags, array $context ): array {

		$values = [];

		foreach ( $tags as $slug => $tag ) {
			$value           = $tag->resolve( $context );
			$values[ $slug ] = $value === null ? '' : $value;
		}

		return $values;
	}

	/**
	 * Replace exactly the authored `{slug}` occurrences, in one pass.
	 *
	 * Keys may be given with or without braces; a bare key is braced, so it
	 * matches `{key}` rather than that raw substring. `strtr()` never re-examines
	 * what it has already written, so a replacement containing another tag stays
	 * literal — which `str_replace()` over parallel arrays does not guarantee.
	 *
	 * @since 3.14.0
	 *
	 * @param string $text   Authored text.
	 * @param array  $values Slug (or `{slug}`) => value.
	 *
	 * @return string
	 */
	public static function replace( string $text, array $values ): string {

		$replacements = [];

		foreach ( $values as $slug => $value ) {
			if ( ! is_string( $slug ) && ! is_int( $slug ) ) {
				continue;
			}

			$slug = trim( (string) $slug, '{}' );

			if ( $slug === '' ) {
				continue;
			}

			$replacements[ '{' . $slug . '}' ] = is_scalar( $value ) ? (string) $value : '';
		}

		return empty( $replacements ) ? $text : strtr( $text, $replacements );
	}
}
