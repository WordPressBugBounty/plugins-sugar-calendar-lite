<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\AddOn\Ticketing\Emails;

/**
 * The Modern document route.
 *
 * Runs the ordered preflight (html, template, context, styling), the fragment
 * pipeline, `Renderer::render_for_delivery()`, and the filtered document.
 * Returns null at every preflight the Modern document cannot pass, and the
 * facade falls back to `LegacyRoute`.
 *
 * @since 3.14.0
 */
class ModernRoute {

	/**
	 * Render the Modern document, or null when Modern does not apply.
	 *
	 * @since 3.14.0
	 *
	 * @param RenderRequest      $request The render's inputs.
	 * @param TicketingSmartTags $tags    The render's tag registry, already set up.
	 * @param string             $subject Final parsed subject.
	 *
	 * @return RenderedEmail|null
	 */
	public function render( RenderRequest $request, TicketingSmartTags $tags, string $subject ): ?RenderedEmail { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded -- Modern eligibility remains one ordered preflight.

		// A caller that explicitly asked for plain text cannot use Modern.
		if ( $request->html() === false ) {
			return null;
		}

		if ( $request->template() !== Styling::TEMPLATE_MODERN ) {
			return null;
		}

		$context = $request->has( 'modern_context' )
			? $request->config( 'modern_context' )
			: $this->context_for( $request );

		if ( ! is_array( $context ) ) {
			return null;
		}

		// EVERY preflight above has now passed, so this delivery genuinely
		// produces a Modern document and Modern copy is the correct fallback.
		// Resolving it any earlier would leak the short Modern body into a
		// legacy wrapper whenever the template, context, or renderer said no.
		$message = $request->message_for( true );

		// `template()` above is the authoritative route, and it can disagree
		// with a second styling read (`sugar_calendar_emails_styling` runs once
		// per read, and a stateful callback may answer differently). Pin the
		// template so the renderer cannot silently refuse a document the copy
		// was already resolved for; every other styling value stays as read.
		$styling = $request->has( 'modern_styling' )
			? $request->config( 'modern_styling' )
			: Styling::get();

		if ( ! is_array( $styling ) || empty( $styling ) ) {
			return null;
		}

		$styling['template'] = Styling::TEMPLATE_MODERN;

		// Sanitize the stored message BEFORE substituting tags, so tag output
		// (`{tickets}` emits markup) is not run through kses. Format only this
		// fragment; whole-document wpautop() would corrupt the table layout.
		//
		// Order messages can contain `{tickets}`, which emits block markup and
		// therefore depends on substitution happening before wpautop().
		$message_html = wpautop(
			make_clickable( $tags->parse( wp_kses_post( $message ) ) ),
			false
		);

		$calendar_action = $request->has( 'calendar_action' )
			? ( is_array( $request->config( 'calendar_action' ) ) ? $request->config( 'calendar_action' ) : null )
			: ( $request->email_id() === Renderer::TICKET_ATTENDEE ? CalendarAction::from_context( $context ) : null );
		$calendar_url    = $request->has( 'calendar_url' )
			? ( is_string( $request->config( 'calendar_url' ) ) ? $request->config( 'calendar_url' ) : '' )
			: ( isset( $calendar_action['url'] ) && is_string( $calendar_action['url'] ) ? $calendar_action['url'] : '' );
		$rendered        = Renderer::render_for_delivery(
			$request->email_id(),
			$context,
			$message_html,
			$styling,
			[
				'calendar_action' => $calendar_action,
				'calendar_url'    => $calendar_url,
				'parse_footer'    => static function ( $footer ) use ( $tags ) {

					return $tags->parse( $footer );
				},
			]
		);

		if ( ! is_array( $rendered ) ) {
			return null;
		}

		$document = $this->filter_document( $rendered['document'], $request->sender(), $tags );
		$document = make_clickable( $document );
		$document = str_replace( '&#038;', '&amp;', $document );

		return new RenderedEmail(
			$subject,
			$document,
			$request->content_type_for( $request->html() ),
			isset( $rendered['calendar_action'] ) && is_array( $rendered['calendar_action'] ) ? $rendered['calendar_action'] : null,
			$request->html()
		);
	}

	/**
	 * Resolve the context owned by the notification id.
	 *
	 * @since 3.14.0
	 *
	 * @param RenderRequest $request The render's inputs.
	 *
	 * @return array|null
	 */
	private function context_for( RenderRequest $request ) {

		// The WPForms blank-line rule conflicts with this PSR-2 switch rule.
		// phpcs:disable PSR2.ControlStructures.SwitchDeclaration.BodyOnNextLineCASE, PSR2.ControlStructures.SwitchDeclaration.BodyOnNextLineDEFAULT

		switch ( $request->email_id() ) {
			case Renderer::TICKET_ATTENDEE:

				return TicketContext::from_ticket( (int) $request->object_id() );

			case Renderer::ORDER_RECEIPT:

				return OrderContext::from_order( (int) $request->object_id() );

			default:

				return null;
		}
		// phpcs:enable PSR2.ControlStructures.SwitchDeclaration.BodyOnNextLineCASE, PSR2.ControlStructures.SwitchDeclaration.BodyOnNextLineDEFAULT
	}

	/**
	 * Filter a Modern document without parsing recipient data a second time.
	 *
	 * Copy tags have already been resolved before context is rendered. Protect
	 * any remaining tag-shaped text while allowing message filters to add tags
	 * of their own. Restore literal values only after that final parsing pass.
	 *
	 * @since 3.14.0
	 *
	 * @param string             $document Rendered Modern document.
	 * @param Emails             $sender   The sender every `sc_email_message` callback receives.
	 * @param TicketingSmartTags $tags     The render's tag registry.
	 *
	 * @return string
	 */
	private function filter_document( string $document, Emails $sender, TicketingSmartTags $tags ): string {

		$positions = [];
		$prefix    = 'SC_EMAIL_LITERAL_' . wp_generate_password( 32, false, false ) . '_';
		$document  = preg_replace_callback(
			'/{([A-z0-9\-_]+)}/s',
			static function ( $matches ) use ( &$positions, $prefix ) {

				$token               = $prefix . count( $positions ) . '_END';
				$positions[ $token ] = $matches[0];

				return $token;
			},
			$document
		);

		/** This filter is documented in sugar-calendar/src/Emails/LegacyRoute.php */
		$document = apply_filters( 'sc_email_message', $document, $sender ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return strtr( $tags->parse( $document ), $positions );
	}
}
