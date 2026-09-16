<?php

namespace Sugar_Calendar\Emails\Modern;

/**
 * Generic Modern layout for one item attached to one event.
 *
 * @since 3.14.0
 */
class SingleEventEmail {

	/**
	 * Render a complete single-item document.
	 *
	 * @since 3.14.0
	 *
	 * @param array         $context      Stable single-item context.
	 * @param array         $styling      Effective styling values.
	 * @param string        $message_html Render-ready message fragment.
	 * @param string|null   $calendar_url Optional supplied calendar URL. Null uses the legacy filter.
	 * @param callable|null $parse_footer Optional trusted footer copy parser.
	 *
	 * @return string
	 */
	public static function render( array $context, array $styling, string $message_html, ?string $calendar_url = null, ?callable $parse_footer = null ): string { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.typeDeclarationFound -- Sugar Calendar requires PHP 7.4+.

		$accent = $styling['colors']['accent'];
		$body   = self::heading_row( $context )
			. self::event_image( $context['event'] )
			. self::event_info( $context, $accent, $calendar_url )
			. self::detail_rows( $context['details'], $context['item_link'], $accent )
			. Template::injection_row()
			. self::footer_note( $context['footer'], $context['site'], $accent, $parse_footer );

		return Template::render( $message_html, $body, $styling );
	}

	/**
	 * Render the optional confirmation heading above the event image.
	 *
	 * `heading` is the one optional context key. `TicketEmail::render()` is a
	 * compatibility adapter still fed the pre-heading shape, and an additive row
	 * must not fail those callers.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context Context.
	 *
	 * @return string
	 */
	private static function heading_row( $context ) {

		$heading = isset( $context['heading'] ) && is_array( $context['heading'] ) ? $context['heading'] : [];
		$text    = isset( $heading['text'] ) ? (string) $heading['text'] : '';

		if ( $text === '' ) {
			return '';
		}

		// Template::DIVIDER is a top border; this row opens the card, so the
		// hairline has to hang from its bottom edge instead.
		return sprintf(
			'<tr><td align="center" style="padding:24px;border-bottom:2px dashed rgba(141, 141, 141, 0.15);">%1$s'
			. '<div style="font-family:%2$s;font-size:24px;font-weight:bold;line-height:1.4;color:#1a1a1a;">%3$s</div>'
			. '</td></tr>',
			self::heading_icon( $heading ),
			Template::FONT_STACK,
			esc_html( $text )
		);
	}

	/**
	 * Render the heading icon, or nothing.
	 *
	 * @since 3.14.0
	 *
	 * @param array $heading Heading values.
	 *
	 * @return string
	 */
	private static function heading_icon( $heading ) {

		$icon = isset( $heading['icon'] ) ? basename( (string) $heading['icon'] ) : '';

		if ( $icon === '' ) {
			return '';
		}

		return sprintf(
			'<div style="padding:0 0 12px 0;"><img src="%1$s" width="28" height="32" alt=""'
			. ' style="padding:13px 15px;border-radius:50%%;display:block;margin:0 auto;border:3px solid #16A34A;outline:none;text-decoration:none;" /></div>',
			esc_url( plugins_url( 'assets/images/emails/' . $icon, SC_PLUGIN_FILE ) )
		);
	}

	/**
	 * Render the featured image when its URL is safe.
	 *
	 * @since 3.14.0
	 *
	 * @param array $event Event values.
	 *
	 * @return string
	 */
	private static function event_image( $event ) {

		$image_url = esc_url( (string) $event['image_url'] );

		if ( $image_url === '' ) {
			return '';
		}

		return sprintf(
			'<tr><td style="padding:24px 24px 0 24px;">'
			. '<img class="sc-email-event-image" src="%1$s" width="502" alt=""'
			. ' style="display:block;border:0;outline:none;text-decoration:none;'
			. 'width:100%%;max-width:502px;height:auto;border-radius:12px;" />'
			. '</td></tr>',
			$image_url
		);
	}

	/**
	 * Render event identity, location, and available actions.
	 *
	 * @since 3.14.0
	 *
	 * @param array       $context      Context.
	 * @param string      $accent       Accent color.
	 * @param string|null $calendar_url Optional supplied calendar URL.
	 *
	 * @return string
	 */
	private static function event_info( $context, $accent, $calendar_url ) {

		$buttons    = self::button_row( $context, $accent, $calendar_url );
		$button_row = $buttons === ''
			? ''
			: '<tr><td colspan="2" style="padding:24px 0 0 0;">' . $buttons . '</td></tr>';

		return sprintf(
			'<tr><td style="padding:24px;">'
			. '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0">'
			. '<tr><td width="64" valign="top" style="padding:0 12px 0 0;">%1$s</td>'
			. '<td valign="middle" style="padding:0;">%2$s</td></tr>%3$s'
			. '</table></td></tr>',
			self::date_block( $context['event'], $accent ),
			self::event_description( $context, $accent ),
			$button_row
		);
	}

	/**
	 * Render the date block.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $event  Event values.
	 * @param string $accent Accent color.
	 *
	 * @return string
	 */
	private static function date_block( $event, $accent ) {

		return sprintf(
			'<table role="presentation" width="64" cellpadding="0" cellspacing="0" border="0"'
			. ' style="width:64px;border:1.5px solid rgba(141, 141, 141, 0.15);border-radius:10px;">'
			. '<tr><td align="center" style="padding:6px 0 0 2.16px;font-family:%1$s;font-size:12px;'
			. 'font-weight:bold;letter-spacing:2.16px;text-transform:uppercase;color:%2$s;">%3$s</td></tr>'
			. '<tr><td align="center" style="padding:4px 0 0 0;font-family:%1$s;font-size:24px;'
			. 'font-weight:bold;letter-spacing:-0.48px;line-height:1;color:#1a1411;">%4$s</td></tr>'
			. '<tr><td align="center" style="padding:4px 0 6px 0;font-family:%1$s;font-size:12px;'
			. 'font-weight:bold;color:#7a7a7a;">%5$s</td></tr></table>',
			Template::FONT_STACK,
			esc_attr( $accent ),
			esc_html( $event['month'] ),
			esc_html( $event['day'] ),
			esc_html( $event['time'] )
		);
	}

	/**
	 * Render the event title and location.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $context Context.
	 * @param string $accent  Accent color.
	 *
	 * @return string
	 */
	private static function event_description( $context, $accent ) {

		$url   = esc_url( (string) $context['event']['url'] );
		$title = $url === ''
			? sprintf(
				'<span style="font-family:%1$s;font-size:24px;font-weight:bold;line-height:1.2;color:#1a1a1a;">%2$s</span>',
				Template::FONT_STACK,
				esc_html( $context['event']['title'] )
			)
			: sprintf(
				'<a href="%1$s" style="font-family:%2$s;font-size:24px;font-weight:bold;line-height:1.2;'
				. 'color:#1a1a1a;text-decoration:none;">%3$s</a>',
				$url,
				Template::FONT_STACK,
				esc_html( $context['event']['title'] )
			);

		$location = self::location_line( $context['location'], $accent );

		return $location === '' ? $title : $title . '<div style="padding:3px 0 0 0;">' . $location . '</div>';
	}

	/**
	 * Render venue, address, and the separate map action.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $location Location values.
	 * @param string $accent   Accent color.
	 *
	 * @return string
	 */
	private static function location_line( $location, $accent ) {

		$parts     = [];
		$venue_url = esc_url( (string) $location['venue_url'] );
		$map_url   = esc_url( (string) $location['map_url'] );

		if ( (string) $location['name'] !== '' ) {
			$parts[] = $venue_url === ''
				? sprintf( '<span style="color:%1$s;font-weight:bold;">%2$s</span>', esc_attr( $accent ), esc_html( $location['name'] ) )
				: sprintf(
					'<a href="%1$s" style="color:%2$s;font-weight:bold;text-decoration:underline;">%3$s</a>',
					$venue_url,
					esc_attr( $accent ),
					esc_html( $location['name'] )
				);
		}

		if ( (string) $location['address'] !== '' ) {
			$parts[] = esc_html( $location['address'] );
		}

		if ( $map_url !== '' ) {
			$parts[] = sprintf(
				'<a href="%1$s" style="color:%2$s;text-decoration:underline;">%3$s</a>',
				$map_url,
				esc_attr( $accent ),
				esc_html__( 'Show on map', 'sugar-calendar-lite' )
			);
		}

		if ( empty( $parts ) ) {
			return '';
		}

		return sprintf(
			'<span style="font-family:%1$s;font-size:14px;line-height:1.4;color:#7a7a7a;">%2$s</span>',
			Template::FONT_STACK,
			implode( ' &middot; ', $parts )
		);
	}

	/**
	 * Render zero, one, or two event actions.
	 *
	 * @since 3.14.0
	 *
	 * @param array       $context      Context.
	 * @param string      $accent       Accent color.
	 * @param string|null $calendar_url Optional supplied calendar URL.
	 *
	 * @return string
	 */
	private static function button_row( $context, $accent, $calendar_url ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- The optional legacy filter is part of the action renderer.

		$event_url = esc_url( (string) $context['event']['url'] );

		if ( $calendar_url === null ) {
			/**
			 * Filters the Add to Calendar URL for a Modern email.
			 *
			 * @since 3.14.0
			 *
			 * @param string $url     Calendar URL.
			 * @param array  $context Email context.
			 */
			$calendar_url = apply_filters( 'sugar_calendar_emails_calendar_url', '', $context ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			$calendar_url = is_string( $calendar_url ) ? $calendar_url : '';
		}

		$calendar_url = esc_url( $calendar_url );

		$buttons = [];

		if ( $event_url !== '' ) {
			$buttons[] = self::button( $event_url, __( 'View Event Details', 'sugar-calendar-lite' ), $accent, true );
		}

		if ( $calendar_url !== '' ) {
			$buttons[] = self::button( $calendar_url, __( 'Add to Calendar', 'sugar-calendar-lite' ), $accent, false );
		}

		if ( empty( $buttons ) ) {
			return '';
		}

		if ( count( $buttons ) === 1 ) {
			return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
				. '<tr><td style="padding:0;">' . $buttons[0] . '</td></tr></table>';
		}

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
			. '<td class="sc-email-btn-cell" width="50%" valign="top" style="padding:0 6px 0 0;">' . $buttons[0] . '</td>'
			. '<td class="sc-email-btn-cell sc-email-btn-cell-last" width="50%" valign="top" style="padding:0 0 0 6px;">' . $buttons[1] . '</td>'
			. '</tr></table>';
	}

	/**
	 * Render one action button.
	 *
	 * @since 3.14.0
	 *
	 * @param string $url        Safe target.
	 * @param string $label      Label.
	 * @param string $accent     Accent color.
	 * @param bool   $is_primary Filled button.
	 *
	 * @return string
	 */
	private static function button( $url, $label, $accent, $is_primary ) {

		$cell  = $is_primary
			? sprintf( 'border:1px solid transparent;background-color:%s;padding:10px 16px;', esc_attr( $accent ) )
			: sprintf( 'border:1px solid %s;padding:10px 16px;', esc_attr( $accent ) );
		$color = $is_primary ? '#ffffff' : '#1a1a1a';

		return sprintf(
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0">'
			. '<tr><td align="center" style="%1$sborder-radius:8px;">'
			. '<a href="%2$s" style="display:block;font-family:%3$s;font-size:14px;font-weight:bold;'
			. 'line-height:20px;color:%4$s;text-decoration:none;">%5$s</a></td></tr></table>',
			$cell,
			$url,
			Template::FONT_STACK,
			$color,
			esc_html( $label )
		);
	}

	/**
	 * Render non-empty details and the optional item link.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $details   Detail entries.
	 * @param array  $item_link Item link.
	 * @param string $accent    Accent color.
	 *
	 * @return string
	 */
	private static function detail_rows( $details, $item_link, $accent ) {

		$rendered = [];

		foreach ( $details as $detail ) {
			if ( (string) $detail['value'] !== '' ) {
				$rendered[] = self::detail_row( $detail );
			}
		}

		$link = self::item_link( $item_link, $accent, ! empty( $rendered ) );

		if ( empty( $rendered ) && $link === '' ) {
			return '';
		}

		return sprintf(
			'<tr><td style="%1$spadding:24px;">'
			. '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0">%2$s%3$s</table>'
			. '</td></tr>',
			Template::DIVIDER,
			implode( '<tr><td colspan="2" height="24" style="height:24px;line-height:24px;">&nbsp;</td></tr>', $rendered ),
			$link
		);
	}

	/**
	 * Render one detail row.
	 *
	 * @since 3.14.0
	 *
	 * @param array $detail Detail entry.
	 *
	 * @return string
	 */
	private static function detail_row( $detail ) {

		$icon = basename( $detail['icon'] );

		list( $icon_width, $icon_height ) = self::detail_icon_dimensions( $icon );

		return sprintf(
			'<tr><td width="64" valign="middle" style="padding:0 12px 0 0;">'
			. '<table role="presentation" width="64" height="64" cellpadding="0" cellspacing="0" border="0"'
			. ' style="width:64px;height:64px;background-color:transparent;border:1.5px solid rgba(141, 141, 141, 0.15);border-radius:10px;">'
			. '<tr><td align="center" valign="middle" style="padding:0;">'
			. '<img src="%1$s" width="%2$d" height="%3$d" alt="" style="display:block;width:%2$dpx;height:%3$dpx;'
			. 'border:0;outline:none;text-decoration:none;" />'
			. '</td></tr></table></td><td valign="middle" style="padding:0;">'
			. '<div style="font-family:%4$s;font-size:12px;font-weight:bold;letter-spacing:0.8px;'
			. 'text-transform:uppercase;color:#aaaaaa;">%5$s</div>'
			. '<div style="padding:8px 0 0 0;font-family:%4$s;font-size:18px;font-weight:500;'
			. 'line-height:1.3;color:#1a1a1a;">%6$s</div></td></tr>',
			esc_url( plugins_url( 'assets/images/emails/' . $icon, SC_PLUGIN_FILE ) ),
			$icon_width,
			$icon_height,
			Template::FONT_STACK,
			esc_html( $detail['label'] ),
			esc_html( $detail['value'] )
		);
	}

	/**
	 * Get the rendered dimensions for a detail icon.
	 *
	 * @since 3.14.0
	 *
	 * @param string $icon Icon filename.
	 *
	 * @return int[] Width and height in pixels.
	 */
	private static function detail_icon_dimensions( $icon ) {

		$dimensions = [
			'icon-attendee.png'    => [ 21, 24 ],
			'icon-ticket-type.png' => [ 27, 24 ],
			'icon-ticket-code.png' => [ 21, 24 ],
			'icon-ticket-cost.png' => [ 24, 24 ],
		];

		return isset( $dimensions[ $icon ] ) ? $dimensions[ $icon ] : [ 24, 24 ];
	}

	/**
	 * Render the item link.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $item_link Item link.
	 * @param string $accent    Accent color.
	 * @param bool   $has_rows  Whether detail rows precede the link.
	 *
	 * @return string
	 */
	private static function item_link( $item_link, $accent, $has_rows ) {

		$url = esc_url( (string) $item_link['url'] );

		if ( $url === '' || (string) $item_link['label'] === '' ) {
			return '';
		}

		return sprintf(
			'<tr><td colspan="2" style="padding:%1$s 0 0 0;">'
			. '<a href="%2$s" style="font-family:%3$s;font-size:14px;font-weight:bold;'
			. 'color:%4$s;text-decoration:underline;">%5$s</a></td></tr>',
			$has_rows ? '24px' : '0',
			$url,
			Template::FONT_STACK,
			esc_attr( $accent ),
			esc_html( $item_link['label'] )
		);
	}

	/**
	 * Render fixed localized footer fragments around the site link.
	 *
	 * @since 3.14.0
	 *
	 * @param array         $footer       Footer fragments.
	 * @param array         $site         Site values.
	 * @param string        $accent       Accent color.
	 * @param callable|null $parse_footer Optional trusted footer copy parser.
	 *
	 * @return string
	 */
	private static function footer_note( $footer, $site, $accent, $parse_footer = null ) {

		$url  = esc_url( (string) $site['url'] );
		$link = $url === ''
			? esc_html( $site['name'] )
			: sprintf(
				'<a href="%1$s" style="color:%2$s;text-decoration:underline;">%3$s</a>',
				$url,
				esc_attr( $accent ),
				esc_html( $site['name'] )
			);

		$default = esc_html( $footer['before_link'] ) . $link . esc_html( $footer['after_link'] );

		/** This filter is documented in the legacy email template. */
		$copy = wp_kses_post( wptexturize( apply_filters( 'sc_email_footer_text', $default ) ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the established public email footer filter for existing integrations.

		if ( $parse_footer !== null ) {
			$copy = $parse_footer( $copy );
		}

		return sprintf(
			'<tr><td align="center" style="%1$spadding:24px;font-family:%2$s;font-size:12px;'
			. 'line-height:1.4;color:#aaaaaa;">%3$s</td></tr>',
			Template::DIVIDER,
			Template::FONT_STACK,
			$copy
		);
	}
}
