<?php

namespace Sugar_Calendar\Features\RegistrationForm\Abandonment;

use Sugar_Calendar\AddOn\Ticketing\Emails;
use Sugar_Calendar\Features\RegistrationForm\Admin\ReminderEmailConfig;
use Sugar_Calendar\Features\RegistrationForm\Frontend\PendingRows;
use Sugar_Calendar\Features\RegistrationForm\Frontend\TokenResume;

/**
 * Composes and delivers one abandonment reminder.
 *
 * Delivery goes through the ticketing Emails sender so the reminder carries the
 * same template chrome as other attendee-facing email. Placeholders are
 * substituted before send() since Emails::send() applies its own tag parsing
 * and template wrap. An RSVP-only site (no ticketing feature) falls back to
 * wp_mail() and loses only the header/footer.
 *
 * @since 3.13.0
 */
class ReminderEmail {

	/**
	 * The `context` value identifying a ticketing order.
	 *
	 * @since 3.13.0
	 */
	const CONTEXT_ORDER = 'order';

	/**
	 * Send one reminder.
	 *
	 * @since 3.13.0
	 *
	 * @param array $args To, name, event_id, context, context_id, token.
	 *
	 * @return bool Whether the mail was handed off successfully.
	 */
	public static function send( array $args ) {

		$to    = sanitize_email( self::arg_string( $args, 'to' ) );
		$token = self::arg_string( $args, 'token' );

		// A malformed token would deliver a link TokenResume::posted_token() refuses.
		// Returning false here leaves the context unstamped, so the reminder retries
		// on a later sweep rather than being suppressed forever over one bad row.
		if ( ! is_email( $to ) || ! PendingRows::is_valid_token( $token ) ) {
			return false;
		}

		$event_id = self::arg_int( $args, 'event_id' );
		$link     = self::resume_link( $event_id, $token );

		if ( $link === '' ) {
			return false;
		}

		$values = self::values( $event_id, self::arg_string( $args, 'name' ), $link );

		// A subject is a mail header, not HTML: esc_html() would ship a literal
		// "R&amp;D Kickoff" to the inbox, so it gets its own replacement map.
		$subject = strtr( ReminderEmailConfig::get_subject(), self::plain_replacements( $values ) );
		$message = strtr( ReminderEmailConfig::get_message(), self::html_replacements( $values ) );

		return self::dispatch( $to, $subject, $message, $args );
	}

	/**
	 * Reads a string key out of the send() args, defaulting to ''.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $args Arguments passed to send().
	 * @param string $key  Key to read.
	 *
	 * @return string
	 */
	private static function arg_string( array $args, $key ) {

		return isset( $args[ $key ] ) ? (string) $args[ $key ] : '';
	}

	/**
	 * Reads an int key out of the send() args, defaulting to 0.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $args Arguments passed to send().
	 * @param string $key  Key to read.
	 *
	 * @return int
	 */
	private static function arg_int( array $args, $key ) {

		return isset( $args[ $key ] ) ? (int) $args[ $key ] : 0;
	}

	/**
	 * The event-page URL that reopens the form.
	 *
	 * The post id is resolved from the SC event rather than trusted from the
	 * caller, since an order's event id and a rendered occurrence are not
	 * guaranteed to agree.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id Sugar Calendar event id.
	 * @param string $token    The context's token.
	 *
	 * @return string Empty when the event cannot be resolved.
	 */
	public static function resume_link( $event_id, $token ) {

		$event = sugar_calendar_get_event( (int) $event_id );

		$post_id = isset( $event->object_id ) ? (int) $event->object_id : 0;

		if ( $post_id <= 0 ) {
			return '';
		}

		$permalink = get_permalink( $post_id );

		if ( empty( $permalink ) ) {
			return '';
		}

		return add_query_arg( TokenResume::QUERY_ARG, (string) $token, $permalink );
	}

	/**
	 * The placeholder map.
	 *
	 * Every value is escaped here, at the point of interpolation: attendee names
	 * and event titles are attacker-influenced strings landing in HTML.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id Sugar Calendar event id.
	 * @param string $name     Recipient display name.
	 * @param string $link     Resume link.
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
			'{resume_link}'   => $link,
			'{site_name}'     => (string) get_bloginfo( 'name' ),
		];
	}

	/**
	 * The placeholder map for the HTML body.
	 *
	 * Escaped here, at the point of interpolation: attendee names and event titles
	 * are attacker-influenced strings landing in HTML.
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
			$map[ $tag ] = $tag === '{resume_link}' ? esc_url( $value ) : esc_html( $value );
		}

		return $map;
	}

	/**
	 * The placeholder map for the subject line.
	 *
	 * A subject is a mail header rather than HTML, so it gets sanitize_text_field()
	 * instead of esc_html(), which would put the literal entity in the inbox.
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

		// Not build_email() first: send() applies it. See the class docblock.
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
