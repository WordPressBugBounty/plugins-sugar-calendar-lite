<?php

namespace Sugar_Calendar;

/**
 * HTML → plain text for outgoing email bodies.
 *
 * The admin notification emails are authored in a TinyMCE editor and sent as
 * plain text. Converting between the two
 * is not `wp_strip_all_tags()` on its own: that wraps `strip_tags()`, which
 * substitutes NOTHING for what it removes, so `<p>a</p><p>b</p>` arrives as
 * `ab`. Block-level tags must become newlines FIRST.
 *
 * Lives at the top of `src/` rather than under `Admin\` for the same reason
 * `EmailStatus` does: send paths run on front-end, REST and WP-CLI requests
 * where nothing under `Admin\Pages` should be loaded. `sc-rsvp` calls it too,
 * which its `>= 3.14.0` core requirement already covers. That minimum moved up
 * from 3.13.0 when the complete Modern email API landed: the released 3.13.0
 * tree does not contain this class.
 *
 * @since 3.14.0
 */
class EmailPlainText {

	/**
	 * Convert an HTML email body to plain text.
	 *
	 * @since 3.14.0
	 *
	 * @param string $html HTML body, typically from a wp_editor() field.
	 *
	 * @return string
	 */
	public static function from_html( $html ) {

		// FIRST, not last. `wp_kses_post()` leaves `&lt;script&gt;` alone on save
		// (it is text, not a tag) and TinyMCE encodes a typed `<` that way — so
		// decoding AFTER the strip below hands back live markup the strip already
		// walked past. Decoding first means the strip sees those tags and removes
		// them. Cost, accepted: a literal `Price &lt; 50` decodes to `Price < 50`
		// and `strip_tags()` then eats from the `<` onward. Rare in email copy.
		$text = html_entity_decode( (string) $html, ENT_QUOTES, 'UTF-8' );

		// Newlines BEFORE stripping — see the class docblock. Reordering these
		// two groups silently collapses every paragraph in every admin email.
		$text = preg_replace( '#<br\s*/?>#i', "\n", $text );
		$text = preg_replace( '#</p\s*>#i', "\n\n", $text );
		$text = preg_replace( '#</(?:h[1-6]|div|li|tr)\s*>#i', "\n", $text );

		// Runs after the </li> rule above, so a list item gets its marker and
		// its newline from two different rules.
		$text = preg_replace( '#<li[^>]*>#i', '- ', $text );

		// Removes what is left, including <script>/<style> bodies.
		$text = wp_strip_all_tags( $text );

		// A decoded &nbsp; is U+00A0, which reads as a stray byte in a plain-text
		// client. Must run AFTER the decode above.
		$text = str_replace( "\xc2\xa0", ' ', $text );

		// wpautop-authored HTML yields runs of blank lines; two means "new
		// paragraph" and more means nothing.
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}
}
