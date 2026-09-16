<?php

namespace Sugar_Calendar;

/**
 * Resolves who receives an admin notification email.
 *
 * Exists so the `sugar_calendar_admin_notification_recipient` filter is applied
 * in ONE place. Three senders need it — core's ticket-sale notification and
 * sc-rsvp's two RSVP notifications — and three copies of an `apply_filters()`
 * call is three chances to drift on the default, on the `$email_id` argument, or
 * on what an empty return means. An earlier draft had exactly that: core bailed
 * on empty while the RSVP override passed `''` straight to `wp_mail()`.
 *
 * Lives beside `EmailStatus` and `EmailPlainText` for the same reason they do:
 * send paths run on front-end, REST and WP-CLI requests where nothing under
 * `Admin\\` should load.
 *
 * @since 3.14.0
 */
class AdminNotificationRecipient {

	/**
	 * Resolve the recipient for one admin notification.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Registry email id, e.g. `sc_rsvp_going_email_to_admin`.
	 *
	 * @return string Recipient address, or '' to suppress the send.
	 */
	public static function resolve( string $email_id ): string {

		/**
		 * Filters the recipient of an admin notification email.
		 *
		 * Defaults to the WordPress site administration address. Use this to route
		 * notifications to a shared inbox without changing the site's admin email,
		 * or return an empty string to suppress one entirely.
		 *
		 * @since 3.14.0
		 *
		 * @param string $recipient Recipient address.
		 * @param string $email_id  Registry email id.
		 */
		return (string) apply_filters(
			'sugar_calendar_admin_notification_recipient',
			get_option( 'admin_email' ),
			$email_id
		);
	}
}
