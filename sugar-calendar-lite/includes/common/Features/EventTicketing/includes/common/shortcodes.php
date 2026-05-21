<?php
namespace Sugar_Calendar\AddOn\Ticketing\Shortcodes;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
use Sugar_Calendar\AddOn\Ticketing\Settings as Settings;
use Sugar_Calendar\AddOn\Ticketing\Database\Order_Query;

/**
 * Outputs the content for the [sc_event_tickets_receipt] shortcode.
 *
 * Branches by URL shape:
 *
 *   ?order=<uuid>       → Tier 1: full receipt rendered by render_full_receipt_by_uuid().
 *   ?order_id=… & email → Legacy path during commit 2; replaced by Tier 2 resend page in commit 3.
 *   anything else       → Generic "Invalid receipt link." error (no enumeration distinction).
 *
 * @since 1.0.0
 * @since 3.11.1 Tier-1 uuid branch added.
 *
 * @return string
 */
function receipt_shortcode() {

	// Tier 1 — canonical uuid URL.
	if ( ! empty( $_GET['order'] ) ) {
		return render_full_receipt_by_uuid(
			sanitize_text_field( wp_unslash( $_GET['order'] ) ),
			isset( $_GET['sce'] ) ? sanitize_text_field( wp_unslash( $_GET['sce'] ) ) : ''
		);
	}

	// Tier 2 — legacy URL falls through to the "request a fresh link" page.
	if ( ! empty( $_GET['order_id'] ) && ! empty( $_GET['email'] ) ) {
		return render_legacy_resend_page(
			absint( $_GET['order_id'] ),
			sanitize_text_field( wp_unslash( $_GET['email'] ) )
		);
	}

	return invalid_receipt_link_response();
}

/**
 * Tier 1 renderer — fetch the order by uuid + sce and render the full receipt.
 *
 * The uuid identifies the order; the sce is the time-bounded secret stored
 * via a 72h transient. If either is missing/malformed/unknown the response
 * is the generic "Invalid receipt link." error. If the uuid is valid but
 * the sce is missing/expired, the request falls through to the auto-resend
 * branch: a fresh email is dispatched (gated by the existing 5-min
 * rate-limit transient) and the user sees the generic "link has been sent"
 * page.
 *
 * @since 3.11.1
 *
 * @param string $uuid Sanitized $_GET['order'] value (urn:uuid: prefix optional).
 * @param string $sce  Sanitized $_GET['sce'] value, or '' if not present.
 * @return string
 */
function render_full_receipt_by_uuid( $uuid, $sce = '' ) {

	// Defensive uuid format validation — accepts both BerlinDB's `urn:uuid:<v4>`
	// form and the URL-friendly bare <v4>. Anything else returns the same
	// generic error a missing-order would produce.
	if ( ! preg_match( '/^(urn:uuid:)?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid ) ) {
		return invalid_receipt_link_response();
	}

	// Normalize for the BerlinDB lookup: the DB column always stores the
	// `urn:uuid:` prefix, but the URL omits it.
	$uuid_db     = ( 0 === stripos( $uuid, 'urn:uuid:' ) ) ? $uuid : 'urn:uuid:' . $uuid;
	$uuid_pretty = preg_replace( '/^urn:uuid:/i', '', $uuid_db );

	$query = new Order_Query();
	$order = $query->get_item_by( 'uuid', $uuid_db );

	if ( empty( $order ) ) {
		return invalid_receipt_link_response();
	}

	// sce format gate. Missing or malformed sce → treat as expired and
	// auto-resend (without leaking whether the order exists vs. the sce
	// is the wrong shape — both paths converge on render_link_sent_page).
	$sce_valid = (bool) preg_match( '/^[0-9a-f]{12}$/i', $sce );

	if ( $sce_valid ) {
		$stored = get_transient( "sc_et_receipt_{$uuid_pretty}_{$sce}" );
		if ( false !== $stored && (int) $stored === (int) $order->id ) {

			/** This filter is documented in includes/common/shortcodes.php */
			$event = apply_filters(
				'sc_et_receipt_shortcode_event',
				sugar_calendar_get_event( $order->event_id ),
				$order
			);

			return render_receipt_body( $order, $event );
		}
	}

	// Expired or missing sce → dispatch a fresh email (rate-limited) and
	// render the generic link-sent page. The dispatch is fire-and-forget
	// from the caller's perspective; rate-limit hits are silently swallowed.
	Functions\maybe_send_fresh_receipt_email( $order );

	return render_link_sent_page( $order );
}

/**
 * Generic "invalid receipt link" response.
 *
 * Used by every Tier-1 and Tier-2 failure mode so the response body is
 * byte-identical regardless of which gate rejected the request. Prevents the
 * receipt page from being used as an order/email enumeration oracle.
 *
 * @since 3.11.1
 *
 * @return string
 */
function invalid_receipt_link_response() {
	return '<div class="sc-et-error alert alert-danger" role="alert">'
		. esc_html__( 'Invalid receipt link.', 'sugar-calendar-lite' )
		. '</div>';
}

/**
 * Tier 2 renderer — auto-dispatch a fresh receipt email on visit.
 *
 * Reached when a customer holds an old confirmation email whose URL uses the
 * legacy `?order_id=…&email=…` shape. We validate the (order_id, email) pair
 * with a constant-time compare; on match, an email containing the new
 * `?order=<uuid>&sce=<sce>` URL is dispatched to the order's stored address
 * (rate-limited by `maybe_send_fresh_receipt_email()`), and the shared
 * "link sent" panel is rendered. Mismatch returns the same generic
 * `invalid_receipt_link_response()` as every other failure mode — so the page
 * cannot be used as an enumeration oracle.
 *
 * No form, no button. The rate-limit transient `sc_et_resend_<order_id>`
 * (default 5 min, filterable via `sc_et_resend_receipt_rate_limit`) bounds
 * the dispatch surface so a drive-by GET against a known (order_id, email)
 * pair produces at most one email per window.
 *
 * @since 3.11.1
 *
 * @param int    $order_id The unsanitized $_GET['order_id'] (already absint'd by caller).
 * @param string $email    The unsanitized $_GET['email'] (already sanitize_text_field'd by caller).
 * @return string
 */
function render_legacy_resend_page( $order_id, $email ) {

	$order = Functions\get_order( $order_id );

	if ( empty( $order ) || ! hash_equals( (string) $order->email, (string) $email ) ) {
		return invalid_receipt_link_response();
	}

	Functions\maybe_send_fresh_receipt_email( $order );

	return render_link_sent_page( $order );
}

/**
 * Mask an email address for display: keep the first character of the local part,
 * the @, and the first character of the domain; replace the rest with `***`.
 *
 * Examples:
 *   victim@example.test → v***@e***.test
 *   a@b.c               → a***@b***.c
 *
 * @since 3.11.1
 *
 * @param string $email Raw email address.
 * @return string
 */
function mask_email_address( $email ) {

	$at = strrpos( (string) $email, '@' );
	if ( false === $at || 0 === $at || $at === strlen( $email ) - 1 ) {
		return '***';
	}

	$local  = substr( $email, 0, $at );
	$domain = substr( $email, $at + 1 );
	$dot    = strrpos( $domain, '.' );

	$local_masked  = substr( $local, 0, 1 ) . '***';
	$domain_masked = ( false === $dot )
		? substr( $domain, 0, 1 ) . '***'
		: substr( $domain, 0, 1 ) . '***' . substr( $domain, $dot );

	return $local_masked . '@' . $domain_masked;
}

/**
 * Shared "we've sent a fresh receipt link" confirmation panel.
 *
 * Rendered by:
 *   - the Tier-1 expired-sce branch in render_full_receipt_by_uuid(), and
 *   - the post-submit confirmation path of render_legacy_resend_page().
 *
 * Renders only the masked email + a fixed generic copy. No order data, no
 * codes, no transaction_id, no nonce URLs. The response is identical whether
 * an email was just dispatched or the rate-limit suppressed the dispatch —
 * the caller is responsible for the rate-limit decision; this function is
 * pure rendering.
 *
 * @since 3.11.1
 *
 * @param object $order Order object (already verified by the caller).
 * @return string
 */
function render_link_sent_page( $order ) {

	ob_start();
	?>
	<div id="sc-event-ticketing-receipt-resend">
		<div class="alert alert-success" role="alert">
			<?php esc_html_e( 'If the order exists, a fresh receipt link has been sent.', 'sugar-calendar-lite' ); ?>
		</div>
		<p><?php esc_html_e( 'Receipt access has been updated.', 'sugar-calendar-lite' ); ?></p>
		<p>
			<?php
			printf(
				/* translators: %s: masked email address. */
				esc_html__( 'Please check %s for the latest receipt link.', 'sugar-calendar-lite' ),
				'<strong>' . esc_html( mask_email_address( $order->email ) ) . '</strong>'
			);
			?>
		</p>
	</div>
	<?php
	$html = ob_get_clean();

	/**
	 * Filter the rendered "fresh receipt link sent" confirmation panel.
	 *
	 * Shown to the customer when (a) they hit a Tier 1 uuid URL whose `sce` is
	 * missing/malformed/expired, or (b) they hit a Tier 2 legacy URL with a
	 * matching (order_id, email). The auto-dispatched email is sent BEFORE this
	 * filter runs (via maybe_send_fresh_receipt_email()), so $order represents
	 * the order whose receipt link was just refreshed.
	 *
	 * Site owners can return any string — wrap with branded markup, replace the
	 * panel entirely, or inject additional context.
	 *
	 * @since 3.11.1
	 *
	 * @param string $html  Rendered HTML for the confirmation panel.
	 * @param object $order Order object the panel is rendered for.
	 */
	return apply_filters( 'sc_et_receipt_link_sent_page', $html, $order );
}

/**
 * Render the receipt markup for a resolved order + event.
 *
 * Shared by Tier 1 (uuid lookup) and (during commit 2) the legacy
 * (order_id, email) path. After commit 3 only Tier 1 calls this.
 *
 * @since 3.11.1
 *
 * @param \Sugar_Calendar\AddOn\Ticketing\Database\Order $order The order object.
 * @param \Sugar_Calendar\Event                          $event The event object.
 * @return string
 */
function render_receipt_body( $order, $event ) {

	$start_date = $event->format_date( sc_get_date_format(), $event->start );
	$start_time = $event->format_date( sc_get_time_format(), $event->start );

	if ( ! empty( $_GET['sc-notice'] ) && ( 'email-sent' === $_GET['sc-notice'] ) ) {
		echo '<div class="alert alert-success" role="alert">' . esc_html__( 'Ticket emailed successfully.', 'sugar-calendar-lite' ) . '</div>';
	}

	$page = Settings\get_setting( 'ticket_page' );
	$link = get_permalink( $page );
	$home = home_url();

	// TODO: Replace table markup
	ob_start(); ?>

	<div id="sc-event-ticketing-ticket-details">
		<table>
			<tr>
				<th><?php esc_html_e( 'Order #', 'sugar-calendar-lite' ); ?></th>
				<th><?php esc_html_e( 'Purchaser', 'sugar-calendar-lite' ); ?></th>
				<th><?php esc_html_e( 'Date', 'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<td><?php echo esc_html( $order->id ); ?></td>
				<td><?php echo esc_html( $order->first_name . ' ' . $order->last_name ) . ' (' . esc_html( $order->email ) . ')'; ?></td>
				<td><?php echo esc_html( $order->date_created ); ?></td>
			</tr>
			<tr>
				<th colspan="3"><?php esc_html_e( 'Amount', 'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<td colspan="3"><?php echo Functions\currency_filter( $order->total ); ?></td>
			</tr>
			<tr>
				<th colspan="3"><?php esc_html_e( 'Transaction ID', 'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<td colspan="3"><?php echo esc_html( $order->transaction_id ); ?></td>
			</tr>
			<tr>
				<th colspan="3"><?php esc_html_e( 'Status', 'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<td colspan="3"><?php echo Functions\order_status_label( $order->status ); ?></td>
			</tr>
			<tr>
				<th colspan="3"><?php esc_html_e( 'Location', 'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<td colspan="3"><?php echo esc_html( get_event_meta( $event->id, 'location', true ) ); ?></td>
			</tr>
			<tr>
				<th colspan="3"><?php esc_html_e( 'Event', 'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<td><?php echo esc_html( $event->title ); ?></td>
				<td><?php printf( esc_html__( '%s at %s', 'sugar-calendar-lite' ), $start_date, $start_time ); ?></td>
				<td>
					<?php
					/** This filter is documented in includes/common/shortcodes.php */
					$url = apply_filters(
						'sc_et_receipt_shortcode_event_url',
						get_permalink( $event->object_id ),
						$event,
						$order
					);
					?>
					<a href="<?php echo esc_url( $url ); ?>">
						<?php esc_html_e( 'View event details', 'sugar-calendar-lite' ); ?>
					</a>
				</td>
			</tr>
			<tr>
				<th colspan="3"><?php esc_html_e( 'Tickets', 'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<td colspan="3">
					<ul>
						<?php foreach ( Functions\get_order_tickets( $order->id ) as $ticket ) : ?>
							<li>
								<?php $attendee = Functions\get_attendee( $ticket->attendee_id ); ?>
								<div class="sc-event-ticketing-ticket-id"><?php printf( esc_html__( 'Ticket ID: %d', 'sugar-calendar-lite' ), $ticket->id ); ?></div>
								<div class="sc-event-ticketing-ticket-code"><?php printf( esc_html__( 'Ticket Code: %s', 'sugar-calendar-lite' ), $ticket->code ); ?></div>

								<?php if ( ! empty( $attendee ) ) : ?>

									<div class="sc-event-ticketing-attendee"><?php printf( esc_html__( 'For: %s', 'sugar-calendar-lite' ), esc_html( $attendee->first_name . ' ' . $attendee->last_name ) . ', ' . esc_html( $attendee->email ) ); ?></div>
									<a href="<?php echo wp_nonce_url( add_query_arg( array( 'sc_et_action' => 'email_ticket', 'ticket_code' => $ticket->code ), $home ), $ticket->code ); ?>"><?php esc_html_e( 'Send via Email', 'sugar-calendar-lite' ); ?></a>
									&nbsp;|&nbsp;

								<?php endif; ?>

								<a href="<?php echo wp_nonce_url( add_query_arg( array( 'sc_et_action' => 'print', 'ticket_code' => $ticket->code ), $home ), $ticket->code ); ?>" target="_blank"><?php esc_html_e( 'Print', 'sugar-calendar-lite' ); ?></a>
								&nbsp;|&nbsp;<a href="<?php echo esc_url( add_query_arg( array( 'order_id' => $order->id, 'ticket_code' => $ticket->code ), $link ) ); ?>"><?php esc_html_e( 'View', 'sugar-calendar-lite' ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		</table>
	</div>

	<?php

	return apply_filters( 'sc_event_tickets_ticket_shortcode_output', ob_get_clean() );
}

/**
 * Outputs the content for the [sc_event_tickets_details] shortcode.
 *
 * @since 1.0.0
 *
 * @return string
 */
function ticket_shortcode() {

	if ( empty( $_GET['order_id'] ) ) {
		return '<div class="sc-et-error alert alert-danger" role="alert">' . esc_html__( 'No order ID was specified.', 'sugar-calendar-lite' ) . '</div>';
	}

	if ( empty( $_GET['ticket_code'] ) ) {
		return '<div class="sc-et-error alert alert-danger" role="alert">' . esc_html__( 'No ticket code was specified.', 'sugar-calendar-lite' ) . '</div>';
	}

	$order_id    = absint( $_GET['order_id'] );
	$ticket_code = sanitize_text_field( $_GET['ticket_code'] );

	$order  = Functions\get_order( $order_id );
	$ticket = Functions\get_ticket_by_code( $ticket_code );

	if (
		empty( $order )
		|| empty( $ticket )
		|| (int) $ticket->order_id !== $order_id
	) {
		$error_html = '<div class="sc-et-error alert alert-danger" role="alert">' . esc_html__( 'Invalid ticket or order.', 'sugar-calendar-lite' ) . '</div>';

		/**
		 * Filters the "invalid ticket or order" error markup returned by the
		 * [sc_event_tickets_details] shortcode when the ticket/order pair fails
		 * validation (missing ticket, missing order, or `ticket_code` does not
		 * belong to `order_id`).
		 *
		 * Both `$ticket` and `$order` may be null — handlers must null-check
		 * before dereferencing. Returning a leaky value (e.g., var_dump'ing
		 * `$order`) would re-introduce the IDOR; treat this filter as a UI hook,
		 * not a debugging hook.
		 *
		 * @since 3.11.1
		 *
		 * @param string                                               $error_html Rendered error HTML.
		 * @param \Sugar_Calendar\AddOn\Ticketing\Database\Order|null  $order      Order loaded from $_GET['order_id'], or null.
		 * @param \Sugar_Calendar\AddOn\Ticketing\Database\Ticket|null $ticket     Ticket loaded from $_GET['ticket_code'], or null.
		 * @param int                                                  $order_id   The absint'd $_GET['order_id'].
		 */
		return apply_filters( 'sc_et_ticket_shortcode_invalid_html', $error_html, $order, $ticket, $order_id );
	}

	/**
	 * Filters the event object for the ticket shortcode.
	 *
	 * @since 3.6.0
	 *
	 * @param \Sugar_Calendar\Event                           $event The event object.
	 * @param \Sugar_Calendar\AddOn\Ticketing\Database\Ticket $ticket The ticket object.
	 */
	$event = apply_filters(
		'sc_et_ticket_shortcode_event',
		sugar_calendar_get_event( $ticket->event_id ),
		$ticket
	);

	$start_date = $event->format_date( sc_get_date_format(), $event->start );
	$start_time = $event->format_date( sc_get_time_format(), $event->start );

	$attendee = ! empty( $ticket->attendee_id )
		? Functions\get_attendee( $ticket->attendee_id )
		: false;

	// TODO: Replace table markup
	ob_start(); ?>

	<div id="sc-event-ticketing-ticket-details">
		<h3><?php echo $event->title; ?></h3>
		<h4><?php printf( esc_html__( '%s at %s', 'sugar-calendar-lite' ), $start_date, $start_time ); ?></h4>
		<table>
			<tr>
				<th><?php esc_html_e( 'Ticket #',  'sugar-calendar-lite' ); ?></th>
				<th><?php esc_html_e( 'Purchaser', 'sugar-calendar-lite' ); ?></th>
				<th><?php esc_html_e( 'Code',      'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<td><?php echo esc_html( $ticket->id ); ?></td>
				<td><?php echo esc_html( $order->first_name . ' ' . $order->last_name ); ?></td>
				<td><?php echo esc_html( $ticket->code ); ?></td>
			</tr>
			<tr>
				<th colspan="3"><?php esc_html_e( 'Location', 'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<td colspan="3"><?php echo get_event_meta( $event->id, 'location', true ); ?></td>
			</tr>
			<?php if ( ! empty( $attendee ) ) : ?>
				<tr>
					<th colspan="3"><?php esc_html_e( 'Attendee', 'sugar-calendar-lite' ); ?></th>
				</tr>
				<tr>
					<td colspan="3"><?php echo esc_html( $attendee->first_name . ' ' . $attendee->last_name ); ?></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th colspan="3"><?php esc_html_e( 'Event Details', 'sugar-calendar-lite' ); ?></th>
			</tr>
			<tr>
				<?php
				/**
				 * Filters the event URL for the ticket shortcode.
				 *
				 * @since 3.6.0
				 *
				 * @param string                                          $url   The event URL.
				 * @param \Sugar_Calendar\Event                           $event  The event object.
				 * @param \Sugar_Calendar\AddOn\Ticketing\Database\Ticket $ticket The ticket object.
				 */
				$url = apply_filters(
					'sc_et_ticket_shortcode_event_url',
					get_permalink( $event->object_id ),
					$event,
					$ticket
				);
				?>
				<td colspan="3"><a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View event details', 'sugar-calendar-lite' ); ?></a></td>
			</tr>
		</table>
	</div>

	<?php

	return apply_filters( 'sc_event_tickets_ticket_shortcode_output', ob_get_clean() );
}
