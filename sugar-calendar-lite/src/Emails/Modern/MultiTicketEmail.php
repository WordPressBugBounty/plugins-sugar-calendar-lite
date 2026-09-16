<?php

namespace Sugar_Calendar\Emails\Modern;

/**
 * Modern layout for an order containing multiple tickets.
 *
 * @since 3.14.0
 */
class MultiTicketEmail {

	/**
	 * Render a complete order document without resolving data.
	 *
	 * @since 3.14.0
	 *
	 * @param array         $context      Query-complete order context.
	 * @param array         $styling      Effective styling values.
	 * @param string        $message_html Render-ready message fragment.
	 * @param callable|null $parse_footer Optional trusted footer copy parser.
	 *
	 * @return string
	 */
	public static function render( array $context, array $styling, string $message_html, ?callable $parse_footer = null ): string { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.typeDeclarationFound -- Sugar Calendar requires PHP 7.4+.

		$accent = $styling['colors']['accent'];
		$body   = self::heading_row()
			. self::order_details( $context, $accent )
			. self::tickets( $context['tickets'], $accent )
			. Template::injection_row()
			. self::footer_note( $context['site'], $accent, $parse_footer );

		return Template::render( $message_html, $body, $styling );
	}

	/**
	 * Render the purchaser's confirmation heading.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	private static function heading_row(): string {

		// Template::DIVIDER is a top border; this row opens the card, so the
		// hairline has to hang from its bottom edge instead.
		return sprintf(
			'<tr><td align="center" style="padding:24px;border-bottom:2px dashed rgba(141, 141, 141, 0.15);">'
			. '<div style="padding:0 0 12px 0;"><img src="%1$s" width="28" height="32" alt=""'
			. ' style="padding:13px 15px;border-radius:50%%;display:block;margin:0 auto;border:3px solid #16A34A;outline:none;text-decoration:none;" /></div>'
			. '<div style="font-family:%2$s;font-size:24px;font-weight:bold;line-height:1.4;color:#1a1a1a;">%3$s</div>'
			. '</td></tr>',
			esc_url( plugins_url( 'assets/images/emails/icon-confirmed.png', SC_PLUGIN_FILE ) ),
			Template::FONT_STACK,
			esc_html__( 'Your Ticket Purchase Receipt', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Render the two-column order grid and the full-width event row.
	 *
	 * An empty value drops its own cell, so a free order has no Transaction ID
	 * and the remaining cells close up.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $context Context.
	 * @param string $accent  Accent color.
	 *
	 * @return string
	 */
	private static function order_details( array $context, string $accent ): string {

		$purchaser = $context['purchaser'];
		$order     = $context['order'];
		$values    = [
			[ __( 'Order#', 'sugar-calendar-lite' ), (string) $order['id'] ],
			[ __( 'Purchaser', 'sugar-calendar-lite' ), $purchaser['name'] ],
			[ __( 'Email', 'sugar-calendar-lite' ), $purchaser['email'] ],
			[ __( 'Purchase Date', 'sugar-calendar-lite' ), $order['date'] ],
			[ $order['amount_paid']['label'], $order['amount_paid']['value'] ],
			[ __( 'Transaction ID', 'sugar-calendar-lite' ), $order['transaction_id'] ],
		];
		$cells     = [];

		foreach ( $values as $value ) {
			if ( (string) $value[1] !== '' ) {
				$cells[] = self::detail_cell( $value[0], esc_html( $value[1] ) );
			}
		}

		$rows = '';

		foreach ( array_chunk( $cells, 2 ) as $pair ) {
			$rows .= sprintf(
				'<tr><td class="sc-email-grid-cell" width="50%%" valign="top" style="padding:0 6px 24px 0;">%1$s</td>'
				. '<td class="sc-email-grid-cell" width="50%%" valign="top" style="padding:0 0 24px 6px;">%2$s</td></tr>',
				$pair[0],
				$pair[1] ?? ''
			);
		}

		return sprintf(
			'<tr><td style="padding:24px;"><table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0">'
			. '%1$s<tr><td colspan="2" style="padding:0;">%2$s</td></tr></table></td></tr>',
			$rows,
			self::detail_cell( __( 'Event', 'sugar-calendar-lite' ), self::event_value( $context['event'], $accent ) )
		);
	}

	/**
	 * Render one labelled value.
	 *
	 * @since 3.14.0
	 *
	 * @param string $label      Label.
	 * @param string $value_html Escaped value markup.
	 *
	 * @return string
	 */
	private static function detail_cell( string $label, string $value_html ): string {

		return sprintf(
			'<div style="font-family:%1$s;font-size:12px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#aaaaaa;">%2$s</div>'
			. '<div style="padding:8px 0 0 0;font-family:%1$s;font-size:14px;line-height:1.4;color:#1a1a1a;word-break:break-word;">%3$s</div>',
			Template::FONT_STACK,
			esc_html( $label ),
			$value_html
		);
	}

	/**
	 * Render the event title link followed by its start.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $event  Event values.
	 * @param string $accent Accent color.
	 *
	 * @return string
	 */
	private static function event_value( array $event, string $accent ): string {

		$parts = [];
		$url   = esc_url( (string) $event['url'] );
		$title = esc_html( $event['title'] );

		if ( $title !== '' ) {
			$parts[] = $url === ''
				? $title
				: sprintf( '<a href="%1$s" style="color:%2$s;text-decoration:underline;">%3$s</a>', $url, esc_attr( $accent ), $title );
		}

		$start = self::event_start( $event );

		if ( $start !== '' ) {
			$parts[] = esc_html( $start );
		}

		return implode( ' | ', $parts );
	}

	/**
	 * Compose the start date and time as the legacy Smart Tags do.
	 *
	 * @since 3.14.0
	 *
	 * @param array $event Event values.
	 *
	 * @return string
	 */
	private static function event_start( array $event ): string {

		$date = (string) $event['date'];
		$time = (string) $event['time'];

		// The context reports an all-day event's time as the "All-day" label, which
		// joined with "at" reads "September 29, 2026 at All-day".
		if ( ! empty( $event['all_day'] ) ) {
			return $date;
		}

		if ( $date === '' || $time === '' ) {
			return $date . $time;
		}

		/* translators: 1: Event start date. 2: Event start time. */
		return sprintf( __( '%1$s at %2$s', 'sugar-calendar-lite' ), $date, $time );
	}

	/**
	 * Render every ticket as one row of the Tickets list.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $tickets Ticket values.
	 * @param string $accent  Accent color.
	 *
	 * @return string
	 */
	private static function tickets( array $tickets, string $accent ): string {

		if ( empty( $tickets ) ) {
			return '';
		}

		$tickets = array_values( $tickets );
		$last    = count( $tickets ) - 1;
		$rows    = '';

		foreach ( $tickets as $index => $ticket ) {
			$rows .= self::ticket_row( $ticket, $accent, $index === $last ? '' : 'border-bottom:1px solid rgba(141, 141, 141, 0.15);' );
		}

		return sprintf(
			'<tr><td style="%1$spadding:24px;">'
			. '<div style="padding:0 0 8px 0;font-family:%2$s;font-size:12px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;color:#aaaaaa;">%3$s</div>'
			. '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0">%4$s</table>'
			. '</td></tr>',
			Template::DIVIDER,
			Template::FONT_STACK,
			esc_html__( 'Tickets', 'sugar-calendar-lite' ),
			$rows
		);
	}

	/**
	 * Render one ticket: number, attendee, and the Print and Details links.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $ticket Ticket values.
	 * @param string $accent Accent color.
	 * @param string $border Bottom border, or empty on the last row.
	 *
	 * @return string
	 */
	private static function ticket_row( array $ticket, string $accent, string $border ): string {

		$identity = [
			sprintf( '<span style="font-size:12px;font-weight:bold;color:#888888;">#%d</span>', (int) $ticket['id'] ),
		];

		if ( (string) $ticket['attendee'] !== '' ) {
			$identity[] = sprintf( '<span style="font-size:14px;font-weight:bold;color:#1e1e1e;">%s</span>', esc_html( $ticket['attendee'] ) );
		}

		if ( (string) $ticket['email'] !== '' ) {
			$identity[] = sprintf( '<span style="font-size:12px;color:#888888;">%s</span>', esc_html( $ticket['email'] ) );
		}

		$links     = [];
		$print_url = esc_url( (string) $ticket['print_url'] );
		$url       = esc_url( (string) $ticket['url'] );

		if ( $print_url !== '' ) {
			$links[] = sprintf(
				'<a href="%1$s" style="font-size:12px;color:#a7aaad;text-decoration:underline;">%2$s</a>',
				$print_url,
				esc_html__( 'Print', 'sugar-calendar-lite' )
			);
		}

		if ( $url !== '' ) {
			$links[] = sprintf(
				'<a href="%1$s" style="font-size:12px;color:%2$s;text-decoration:underline;">%3$s</a>',
				$url,
				esc_attr( $accent ),
				esc_html__( 'Details', 'sugar-calendar-lite' )
			);
		}

		return sprintf(
			'<tr><td class="sc-email-ticket" valign="middle" style="%1$spadding:12px 0;font-family:%2$s;line-height:1.4;word-break:break-word;">%3$s</td>'
			. '<td align="right" valign="middle" style="%1$spadding:12px 0 12px 16px;font-family:%2$s;line-height:1.4;white-space:nowrap;">%4$s</td></tr>',
			$border,
			Template::FONT_STACK,
			implode( '&nbsp; ', $identity ),
			implode( '&nbsp;&nbsp;&nbsp; ', $links )
		);
	}

	/**
	 * Render the fixed site footer.
	 *
	 * @since 3.14.0
	 *
	 * @param array         $site         Site values.
	 * @param string        $accent       Accent color.
	 * @param callable|null $parse_footer Optional trusted footer copy parser.
	 *
	 * @return string
	 */
	private static function footer_note( array $site, string $accent, $parse_footer = null ): string {

		$site_url  = esc_url( (string) $site['url'] );
		$site_link = $site_url === ''
			? esc_html( $site['name'] )
			: sprintf( '<a href="%1$s" style="color:%2$s;text-decoration:underline;">%3$s</a>', $site_url, esc_attr( $accent ), esc_html( $site['name'] ) );
		$default   = esc_html( __( "You're receiving this because you purchased tickets at ", 'sugar-calendar-lite' ) ) . $site_link;

		/** This filter is documented in the legacy email template. */
		$copy = wp_kses_post( wptexturize( apply_filters( 'sc_email_footer_text', $default ) ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the established public email footer filter for existing integrations.

		if ( $parse_footer !== null ) {
			$copy = $parse_footer( $copy );
		}

		return sprintf(
			'<tr><td align="center" style="%1$spadding:24px;font-family:%2$s;font-size:12px;line-height:1.4;color:#aaaaaa;">%3$s</td></tr>',
			Template::DIVIDER,
			Template::FONT_STACK,
			$copy
		);
	}
}
