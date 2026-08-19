<?php

namespace Sugar_Calendar\Features\RegistrationForm\Email;

use Sugar_Calendar\AddOn\Ticketing\Emails;
use Sugar_Calendar\Features\RegistrationForm\Abandonment\ReminderEmail;
use Sugar_Calendar\Features\RegistrationForm\Admin\AnswersConfirmationEmailConfig;
use Sugar_Calendar\Features\RegistrationForm\Frontend\PendingRows;

/**
 * Confirms a completed registration form, and offers the edit link.
 *
 * Sent on every submit that leaves a context complete — the first answer, one
 * arriving through the abandonment reminder, and each later edit. Delivery
 * mirrors ReminderEmail: through the ticketing Emails template when that
 * feature is loaded, wp_mail() otherwise, with placeholders substituted before
 * send() since that sender applies its own tag parsing and template wrap.
 *
 * Lives under Email\ rather than beside ReminderEmail in Abandonment\: this
 * mail has nothing to do with abandonment.
 *
 * @since 3.13.0
 */
class AnswersConfirmationEmail {

	/**
	 * The `context` value identifying a ticketing order.
	 *
	 * @since 3.13.0
	 */
	const CONTEXT_ORDER = 'order';

	/**
	 * Send one confirmation.
	 *
	 * @since 3.13.0
	 *
	 * @param array $args To, name, event_id, context, context_id, token, allow_edit.
	 *
	 * @return bool Whether the mail was handed off successfully.
	 */
	public static function send( array $args ) {

		$to    = sanitize_email( isset( $args['to'] ) ? (string) $args['to'] : '' );
		$token = isset( $args['token'] ) ? (string) $args['token'] : '';

		// Shape, not merely non-empty: a malformed token would email a link that
		// resolves to nothing, which reads to the respondent as a broken site.
		if ( ! is_email( $to ) || ! PendingRows::is_valid_token( $token ) ) {
			return false;
		}

		$event_id = isset( $args['event_id'] ) ? (int) $args['event_id'] : 0;

		// Empty unless the organizer allowed editing, so the same configured message
		// degrades to a plain confirmation instead of advertising a dead link. Built
		// through the helper the reminder and the receipt link already share, so the
		// three cannot drift apart.
		$link = empty( $args['allow_edit'] ) ? '' : ReminderEmail::resume_link( $event_id, $token );

		$values = self::values( $event_id, isset( $args['name'] ) ? (string) $args['name'] : '', $link );

		$subject = strtr( AnswersConfirmationEmailConfig::get_subject(), self::plain_replacements( $values ) );
		$message = strtr( self::body( $link ), self::html_replacements( $values ) );

		return self::dispatch( $to, $subject, $message, $args );
	}

	/**
	 * The configured body, minus the edit invitation when there is no link.
	 *
	 * @since 3.13.0
	 *
	 * @param string $link Edit link, or '' when editing is not allowed.
	 *
	 * @return string
	 */
	private static function body( $link ) {

		$body = AnswersConfirmationEmailConfig::get_message();

		return $link === '' ? self::drop_edit_link_lines( $body ) : $body;
	}

	/**
	 * Removes the lines that carry {edit_link} from the body.
	 *
	 * Substituting the tag with '' would leave the sentence around it stranded
	 * ("Edit your registration here:" with nothing after it), so the invitation
	 * goes away with the link. Line-scoped rather than sentence-scoped: an owner
	 * who inlines the tag mid-paragraph loses that paragraph, which still reads
	 * better than a dead invitation.
	 *
	 * @since 3.13.0
	 *
	 * @param string $body Configured message body.
	 *
	 * @return string
	 */
	private static function drop_edit_link_lines( $body ) {

		$lines = preg_split( '/(\r\n|\r|\n)/', (string) $body );

		if ( ! is_array( $lines ) ) {
			return (string) $body;
		}

		$kept = array_filter(
			$lines,
			static function ( $line ) {

				return strpos( $line, '{edit_link}' ) === false;
			}
		);

		// Trailing/leading blank lines left by a removed line would render as an
		// empty paragraph through wpautop().
		return trim( implode( "\n", $kept ) );
	}

	/**
	 * The placeholder map.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id Sugar Calendar event id.
	 * @param string $name     Recipient display name.
	 * @param string $link     Edit link, or '' when editing is not allowed.
	 *
	 * @return array<string,string>
	 */
	private static function values( $event_id, $name, $link ) {

		$event = sugar_calendar_get_event( (int) $event_id );

		$title = isset( $event->title ) ? (string) $event->title : '';
		$start = isset( $event->start ) ? (string) $event->start : '';

		// date_i18n()'s explicit-timestamp branch reverses strtotime() back to the
		// wall-clock string before formatting, so `start` round-trips as the same
		// floating day on every site timezone.
		$date = $start === ''
			? ''
			: date_i18n( get_option( 'date_format' ), strtotime( $start ) );

		return [
			'{attendee_name}' => $name,
			'{event_title}'   => $title,
			'{event_date}'    => $date,
			'{edit_link}'     => $link,
			'{site_name}'     => (string) get_bloginfo( 'name' ),
		];
	}

	/**
	 * The placeholder map for the HTML body.
	 *
	 * Escaped here, at the point of interpolation: names and event titles are
	 * attacker-influenced strings landing in HTML.
	 *
	 * @since 3.13.0
	 *
	 * @param array<string,string> $values Raw placeholder values.
	 *
	 * @return array<string,string>
	 */
	private static function html_replacements( array $values ) {

		$map = [];

		foreach ( $values as $tag => $value ) {
			$map[ $tag ] = $tag === '{edit_link}' ? esc_url( $value ) : esc_html( $value );
		}

		return $map;
	}

	/**
	 * The placeholder map for the subject line.
	 *
	 * A subject is a mail header rather than HTML, so it gets
	 * sanitize_text_field() instead of esc_html(), which would put the literal
	 * entity in the inbox.
	 *
	 * @since 3.13.0
	 *
	 * @param array<string,string> $values Raw placeholder values.
	 *
	 * @return array<string,string>
	 */
	private static function plain_replacements( array $values ) {

		$map = [];

		foreach ( $values as $tag => $value ) {
			$map[ $tag ] = sanitize_text_field( $value );
		}

		return $map;
	}

	/**
	 * Hand the composed message to the best available sender.
	 *
	 * @since 3.13.0
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $message Body.
	 * @param array  $args    The original send() args.
	 *
	 * @return bool
	 */
	private static function dispatch( $to, $subject, $message, array $args ) {

		$emails = self::emails();

		if ( $emails === null ) {
			return (bool) wp_mail(
				$to,
				$subject,
				wpautop( $message ),
				[ 'Content-Type: text/html; charset=UTF-8' ]
			);
		}

		// Gives the ticketing tags something to resolve against when an owner has
		// customised the body with {receipt_url} or {tickets}. An RSVP context has
		// no equivalent object, so those tags there just resolve empty.
		if ( (string) ( $args['context'] ?? '' ) === self::CONTEXT_ORDER ) {
			$emails->object_type = 'order';
			$emails->object_id   = isset( $args['context_id'] ) ? (int) $args['context_id'] : 0;
		}

		// Not build_email() first: send() applies it.
		return (bool) $emails->send( $to, $subject, $message );
	}

	/**
	 * The ticketing Emails instance, or null when that feature is not loaded.
	 *
	 * @since 3.13.0
	 *
	 * @return Emails|null
	 */
	private static function emails() {

		$features = sugar_calendar()->get_common_features();

		if ( empty( $features ) || empty( $features->get_feature( 'EventTicketing' ) ) ) {
			return null;
		}

		if ( ! class_exists( Emails::class ) ) {
			return null;
		}

		return new Emails();
	}
}
