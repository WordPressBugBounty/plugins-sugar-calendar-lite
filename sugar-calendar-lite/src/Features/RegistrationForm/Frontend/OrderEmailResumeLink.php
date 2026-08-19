<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\Abandonment\ReminderEmail;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;

/**
 * Puts the resume link into the order receipt email.
 *
 * The receipt page only shows the form to the browser that checked out
 * (CheckoutSession), which leaves the buyer who opens their receipt on another
 * device with no way in. This is that way in, and it is the credential-bearing
 * one: the link carries the pending rows' token, not the receipt URL.
 *
 * Installed as a one-shot sc_email_message filter, exactly like the
 * online-meeting block next to it in send_order_receipt_email() — the Emails
 * class keeps object_type/object_id private with no __get, so a persistent
 * global filter could never tell which email it was looking at.
 *
 * @since 3.13.0
 */
class OrderEmailResumeLink {

	/**
	 * The HTML template's content-closing marker.
	 *
	 * Its absence means the message is plain text.
	 *
	 * @since 3.13.0
	 */
	const HTML_MARKER = '<!-- End Content -->';

	/**
	 * Install the injection for one order, if it has unanswered questions.
	 *
	 * The caller MUST call this immediately before Emails::send(): the filter
	 * removes itself on its first fire, so installing it without a guaranteed send
	 * would leak the block into whatever email came next.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 */
	public static function install( $order_id ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- A one-shot filter installed per send; a hooks() method would register it globally, which is the bug this shape avoids.

		$link = self::link_for_order( (int) $order_id );

		if ( $link === '' ) {
			return;
		}

		$callback = null;
		$callback = function ( $message ) use ( $link, &$callback ) {

			remove_filter( 'sc_email_message', $callback, 20 );

			if ( strpos( $message, self::HTML_MARKER ) !== false ) {
				return str_replace( self::HTML_MARKER, self::html( $link ) . self::HTML_MARKER, $message );
			}

			return $message . self::text( $link );
		};

		add_filter( 'sc_email_message', $callback, 20 );
	}

	/**
	 * The resume link for an order that still owes answers.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return string Empty when this order needs no link.
	 */
	private static function link_for_order( $order_id ) {

		if ( $order_id <= 0 ) {
			return '';
		}

		foreach ( ResponseRepository::get_for_order( $order_id ) as $row ) {

			if ( $row['status'] === 'complete' ) {
				continue;
			}

			$token = isset( $row['token'] ) ? (string) $row['token'] : '';

			// Shape, not merely non-empty: a malformed token would email a link that
			// resolves to nothing, which reads to the buyer as a broken site.
			if ( ! PendingRows::is_valid_token( $token ) ) {
				continue;
			}

			// One token covers every row of a context, so the first usable one is the
			// answer. Built through the same helper the reminder email uses, so the
			// two links cannot drift apart.
			return ReminderEmail::resume_link( (int) $row['event_id'], $token );
		}

		return '';
	}

	/**
	 * The HTML block.
	 *
	 * Inline styles rather than classes: this lands in an email, where the
	 * template's stylesheet is all there is.
	 *
	 * @since 3.13.0
	 *
	 * @param string $link The resume link.
	 *
	 * @return string
	 */
	private static function html( $link ) {

		return sprintf(
			'<p style="margin:24px 0 0;"><strong>%1$s</strong><br />%2$s<br /><a href="%3$s">%4$s</a></p>',
			esc_html__( 'Complete your registration', 'sugar-calendar-lite' ),
			esc_html__( 'The event organiser has a few more questions about your booking.', 'sugar-calendar-lite' ),
			esc_url( $link ),
			esc_html__( 'Answer them here', 'sugar-calendar-lite' )
		);
	}

	/**
	 * The plain-text block.
	 *
	 * @since 3.13.0
	 *
	 * @param string $link The resume link.
	 *
	 * @return string
	 */
	private static function text( $link ) {

		return sprintf(
			"\n\n%1\$s\n%2\$s\n%3\$s",
			esc_html__( 'Complete your registration', 'sugar-calendar-lite' ),
			esc_html__( 'The event organiser has a few more questions about your booking.', 'sugar-calendar-lite' ),
			esc_url_raw( $link )
		);
	}
}
