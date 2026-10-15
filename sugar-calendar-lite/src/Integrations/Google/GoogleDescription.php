<?php

namespace Sugar_Calendar\Integrations\Google;

use WP_Post;

/**
 * Turns an event's content into the HTML Google Calendar renders in a description.
 *
 * @since 3.16.0
 */
class GoogleDescription {

	/**
	 * Tags Google Calendar renders and keeps through its own editor, checked against a live calendar.
	 *
	 * @since 3.16.0
	 *
	 * @var array
	 */
	const TAGS = [
		'a'          => [ 'href' => true ],
		'strong'     => [],
		'b'          => [],
		'em'         => [],
		'i'          => [],
		'u'          => [],
		'br'         => [],
		'p'          => [],
		'h1'         => [],
		'h2'         => [],
		'h3'         => [],
		'h4'         => [],
		'h5'         => [],
		'h6'         => [],
		'ol'         => [],
		'ul'         => [],
		'li'         => [],
		'blockquote' => [],
		'pre'        => [],
		'table'      => [],
		'thead'      => [],
		'tbody'      => [],
		'tr'         => [],
		'th'         => [],
		'td'         => [],
	];

	/**
	 * Wrappers Google turns into a line break: the tag goes, the text stays on its own line.
	 *
	 * @since 3.16.0
	 *
	 * @var array
	 */
	const UNWRAP_TAGS = [ 'div', 'figure', 'figcaption' ];

	/**
	 * Longest description Google takes. Observed, not documented, so it stays under the reported 8192.
	 *
	 * @since 3.16.0
	 *
	 * @var int
	 */
	const MAX_LENGTH = 8000;

	/**
	 * Description for the Google copy. Password-protected events send none, as the ICS export does.
	 *
	 * @since 3.16.0
	 *
	 * @param WP_Post $post The event's post.
	 *
	 * @return string
	 */
	public function from_post( WP_Post $post ): string {

		if ( $post->post_password !== '' ) {
			return '';
		}

		$html = $this->render( (string) $post->post_content );
		$html = $this->map_tags( $html );
		$html = wp_kses( $html, $this->tags() );
		$html = (string) preg_replace( '#<(strong|b|em|i|u|a)\b[^>]*>\s*</\1>#i', '', $html );
		$html = $this->line_breaks( $html );

		if ( mb_strlen( $html ) > self::MAX_LENGTH ) {
			$html = wp_kses( force_balance_tags( mb_substr( $html, 0, self::MAX_LENGTH ) ), $this->tags() );
		}

		return $html;
	}

	/**
	 * Blocks and shortcodes rendered as the front end would. Printed output is thrown away.
	 *
	 * @since 3.16.0
	 *
	 * @param string $content Raw post content.
	 *
	 * @return string
	 */
	private function render( string $content ): string {

		ob_start();
		$html = do_shortcode( do_blocks( $content ) ); // phpcs:ignore WPForms.Formatting.EmptyLineAfterAssigmentVariables.AddEmptyLine
		ob_end_clean();

		// Scripts, styles and comments go whole; wp_kses() would keep their text.
		return (string) preg_replace( [ '@<(script|style)[^>]*?>.*?</\\1>@si', '/<!--.*?-->/s' ], '', $html );
	}

	/**
	 * Rewrite WordPress markup into what Google renders.
	 *
	 * @since 3.16.0
	 *
	 * @param string $html Rendered content.
	 *
	 * @return string
	 */
	private function map_tags( string $html ): string {

		$map = [
			// WordPress underlines with a styled span; Google drops the style.
			'#<span[^>]*text-decoration:\s*underline[^>]*>(.*?)</span>#is' => '<u>$1</u>',
		];

		foreach ( self::UNWRAP_TAGS as $tag ) {
			$map[ '#<' . $tag . '\b[^>]*>#i' ]   = '';
			$map[ '#\s*</' . $tag . '>\s*#i' ] = "\n";
		}

		/**
		 * Filter the rewrites applied to an event description before it is sent to Google.
		 *
		 * @since 3.16.0
		 *
		 * @param array  $map  Regex pattern => replacement, applied in order.
		 * @param string $html Rendered content.
		 */
		$map = (array) apply_filters( 'sugar_calendar_google_description_map', $map, $html ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return (string) preg_replace( array_keys( $map ), array_values( $map ), $html );
	}

	/**
	 * Tags allowed in the description, in wp_kses() form.
	 *
	 * @since 3.16.0
	 *
	 * @return array
	 */
	private function tags(): array {

		/**
		 * Filter the tags kept in an event description sent to Google.
		 *
		 * @since 3.16.0
		 *
		 * @param array $tags Allowed tags and attributes, in wp_kses() form.
		 */
		return (array) apply_filters( 'sugar_calendar_google_description_tags', self::TAGS ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Newlines between tags are markup whitespace and go; the rest become line breaks, at most two in a row.
	 *
	 * @since 3.16.0
	 *
	 * @param string $html Sanitized content.
	 *
	 * @return string
	 */
	private function line_breaks( string $html ): string {

		// The classic editor saves \r\n; a stray \r would be a second line break in Google.
		$html = str_replace( [ "\r\n", "\r" ], "\n", $html );
		$html = (string) preg_replace( '#>\s*\n\s*<#', '><', trim( $html ) );
		$html = str_replace( "\n", '<br>', $html );

		// Lists lay out their own lines.
		$html = (string) preg_replace( [ '#(?:<br>)+(</?(?:ul|ol|li)>)#', '#(<(?:ul|ol|li)>|</li>)(?:<br>)+#', '#(</(?:ul|ol)>)(?:<br>)+#' ], [ '$1', '$1', '$1<br>' ], $html );
		$html = (string) preg_replace( '#(?:<br>){3,}#', '<br><br>', $html );

		return (string) preg_replace( '#^(?:<br>)+|(?:<br>)+$#', '', $html );
	}
}
