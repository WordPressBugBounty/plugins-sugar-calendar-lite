<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\AddOn\Ticketing\Emails;
use Sugar_Calendar\EmailPlainText;

/**
 * The legacy template route: HTML wrapper or plain text.
 *
 * Owns the single legacy `sc_email_message` application (in wrap_document()),
 * the plain-text conversion, the admin finishing step, and text_to_html().
 * Its two statics also back the facade's public build_email() and text_to_html().
 *
 * @since 3.14.0
 */
class LegacyRoute {

	/**
	 * Render the legacy document.
	 *
	 * @since 3.14.0
	 *
	 * @param RenderRequest              $request The render's inputs.
	 * @param TicketingSmartTags         $tags    The render's tag registry, already set up.
	 * @param AdminOrderTagResolver|null $admin   The admin resolver, when this is the admin email.
	 * @param string                     $subject Final parsed subject (may carry admin position tokens).
	 *
	 * @return RenderedEmail
	 */
	public function render( RenderRequest $request, TicketingSmartTags $tags, ?AdminOrderTagResolver $admin, string $subject ): RenderedEmail {

		$html    = $request->legacy_plain_text() ? false : $request->html();
		$message = $request->message_for( false );

		if ( $request->legacy_plain_text() ) {
			$message = EmailPlainText::from_html( $message );
		}

		// wrap_document() owns the legacy sc_email_message application.
		$message = self::wrap_document( $message, $request->heading(), $html, $request->sender() );

		if ( $admin !== null && $admin->is_active() ) {
			$finished = $admin->finish( $subject, $message );
			$subject  = $finished['subject'];
			$message  = $finished['body'];
		} else {
			$message = $tags->parse( $message );
		}

		$message = self::text_to_html( $message, $html === true );

		if ( $request->legacy_plain_text() ) {
			$message = html_entity_decode( $message, ENT_QUOTES, 'UTF-8' );
		}

		return new RenderedEmail( $subject, $message, $request->content_type_for( $html ), null, $html );
	}

	/**
	 * Wrap a message in the legacy template, or strip tags for plain text.
	 *
	 * @since 3.14.0
	 *
	 * @param string $message The email message.
	 * @param string $heading Header text for the template.
	 * @param mixed  $html    Whether to send as HTML (false means plain text).
	 * @param Emails $sender  The sender every `sc_email_message` callback receives.
	 *
	 * @return string
	 */
	public static function wrap_document( $message, string $heading, $html, Emails $sender ) {

		if ( $html === false ) {
			/**
			 * Filters the message contents of the current email.
			 *
			 * @since 1.0.0
			 *
			 * @param string $message Email message contents.
			 * @param Emails $sender  Email sender instance.
			 */
			return apply_filters( 'sc_email_message', wp_strip_all_tags( $message ), $sender ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		}

		ob_start();

		include SC_CORE_ET_PLUGIN_DIR . 'includes/templates/email.php';

		$body = ob_get_clean();

		$message = str_replace( '{email}', $message, $body );
		$message = str_replace( '{heading}', $heading, $message );

		/** This filter is documented in sugar-calendar/src/Emails/LegacyRoute.php */
		return apply_filters( 'sc_email_message', $message, $sender ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Convert text to formatted HTML: line breaks into <p> and <br/>, bare URLs into links.
	 *
	 * @since 3.14.0
	 *
	 * @param string $message The message.
	 * @param bool   $html    Whether the message is HTML.
	 *
	 * @return string
	 */
	public static function text_to_html( $message, bool $html ) {

		if ( $html ) {
			$message = wpautop( make_clickable( $message ), false );
			$message = str_replace( '&#038;', '&amp;', $message );
		}

		return $message;
	}
}
