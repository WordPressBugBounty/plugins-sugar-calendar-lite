<?php

namespace Sugar_Calendar\Emails\Modern;

/**
 * Compatibility adapter for the original Modern ticket renderer.
 *
 * @since 3.14.0
 */
class TicketEmail {

	/**
	 * Render a complete single-ticket document.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $context      Stable single-item context.
	 * @param array  $styling      Effective styling values.
	 * @param string $message_html Render-ready message fragment.
	 *
	 * @return string
	 */
	public static function render( array $context, array $styling, string $message_html ): string {

		return SingleEventEmail::render( $context, $styling, $message_html );
	}
}
