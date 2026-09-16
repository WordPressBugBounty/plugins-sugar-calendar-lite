<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\AddOn\Ticketing\Emails as LegacyEmails;
use Sugar_Calendar\Emails\Ticketing\AttendeeTicketEmail;
use Sugar_Calendar\Emails\Ticketing\OrderReceiptEmail;
use Sugar_Calendar\Emails\Ticketing\TicketSaleAdminEmail;
use Sugar_Calendar\SmartTags\SmartTagResolver;
use WP_Error;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_email_tag_tickets;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_receipt_message_resolution;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_receipt_subject;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_ticket_message_resolution;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_ticket_sale_admin_message;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_ticket_sale_admin_subject;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_ticket_subject;
use function Sugar_Calendar\AddOn\Ticketing\Settings\resolve_email_copy_for_template;

/**
 * Build deterministic preview documents without creating business records.
 *
 * @since 3.14.0
 */
class PreviewDocument {

	const ERROR_MISSING_RENDERER = 'sugar_calendar_email_preview_renderer_unavailable';

	const ERROR_INVALID_SOURCE = 'sugar_calendar_email_preview_invalid_source';

	/**
	 * Internal flag removed before the public document filter runs.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private const PRESERVE_LINKS = '_sugar_calendar_preview_preserve_links'; // phpcs:ignore PHPCompatibility.Classes.NewConstVisibility.Found -- Sugar Calendar requires PHP 7.4+.

	/**
	 * Notification-owned subject and message fields, for every previewable email.
	 *
	 * Built from registered definitions, never the filtered listing. The pair
	 * guard stays because third-party objects feed it and
	 * Preview::sanitize_state() indexes POST state by these keys.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function field_map() {

		$fields = [];

		foreach ( EmailRegistry::definitions() as $email_id => $email ) {
			if ( ! $email->is_previewable() ) {
				continue;
			}

			$pair = $email->option_slugs();

			if (
				empty( $pair['subject'] )
				|| empty( $pair['message'] )
				|| ! is_string( $pair['subject'] )
				|| ! is_string( $pair['message'] )
				|| sanitize_key( $pair['subject'] ) !== $pair['subject']
				|| sanitize_key( $pair['message'] ) !== $pair['message']
			) {
				continue;
			}

			$fields[ $email_id ] = [
				'subject' => $pair['subject'],
				'message' => $pair['message'],
			];
		}

		return $fields;
	}

	/**
	 * Whether a safe field mapping exists for an editor.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Notification id.
	 *
	 * @return bool
	 */
	public static function supports( $email_id ) {

		return isset( self::field_map()[ $email_id ] );
	}

	/**
	 * Render core, add-on, or third-party preview content.
	 *
	 * @since 3.14.0
	 *
	 * @param array $preview Sanitized preview state.
	 *
	 * @return array|WP_Error
	 */
	public static function render( array $preview ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- The preview owns one exact, exception-safe mail guard.

		$preview = self::normalize_preview( $preview );
		$guard   = static function () {
			return false;
		};

		add_filter( 'pre_wp_mail', $guard, PHP_INT_MAX );

		try {
			$email_id       = $preview['email_id'];
			$preserve_links = false;
			$document       = null;

			if ( self::is_core_email( $email_id ) ) {
				/**
				 * Filters the saved object used to build a first-party email preview.
				 *
				 * Return integer zero for fictional data, a positive ticket ID for the
				 * attendee email, or a positive order ID for either order email. Every
				 * other type or value is invalid.
				 *
				 * @since 3.14.0
				 *
				 * @param int    $object_id Default zero for fictional preview data.
				 * @param string $email_id  First-party notification id.
				 * @param array  $preview   Complete normalized preview state.
				 */
				$object_id = apply_filters( 'sugar_calendar_emails_preview_object_id', 0, $email_id, $preview ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

				if ( ! is_int( $object_id ) || $object_id < 0 ) {
					$document = self::invalid_source( $email_id );
				} elseif ( $object_id === 0 ) {
					$document = self::fictional_document( $email_id, $preview['state'] );
				} else {
					$document = self::real_document( $email_id, $object_id, $preview['state'] );
				}

				if ( is_array( $document ) && array_key_exists( self::PRESERVE_LINKS, $document ) ) {
					$preserve_links = $document[ self::PRESERVE_LINKS ] === true;

					unset( $document[ self::PRESERVE_LINKS ] );
				}
			}

			/**
			 * Filters a complete preview document.
			 *
			 * @since 3.14.0
			 *
			 * @param array|string|null $document Preview document.
			 * @param string            $email_id Notification id.
			 * @param array             $preview  Complete normalized preview state.
			 */
			$document = apply_filters( 'sugar_calendar_emails_preview_document', $document, $email_id, $preview ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			return self::validate_document( $document, $preserve_links );
		} finally {
			remove_filter( 'pre_wp_mail', $guard, PHP_INT_MAX );
		}
	}

	/**
	 * Render one of core's three fictional documents.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Notification id.
	 * @param array  $state    Sanitized state.
	 *
	 * @return array|null
	 */
	private static function fictional_document( $email_id, array $state ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- LegacyEmails registers send hooks in its constructor; previews remove those exact callbacks in finally.

		if ( $email_id === TicketSaleAdminEmail::ID ) {
			list( $subject, $message ) = self::core_copy( $email_id, $state, Styling::LEGACY_TEMPLATE );
			$emails                    = new LegacyEmails( Styling::LEGACY_TEMPLATE );

			try {
				$emails->email_id          = $email_id;
				$emails->object_id         = 0;
				$emails->object_type       = 'order';
				$emails->legacy_plain_text = true;
				$rendered                  = $emails->render(
					$subject,
					$message,
					[ 'admin_order_record' => OrderContext::fictional_for_admin() ]
				);

				return [
					'document'     => $rendered['message'],
					'content_type' => $rendered['content_type'],
					'subject'      => $rendered['subject'],
				];
			} finally {
				remove_action( 'sc_email_send_before', [ $emails, 'send_before' ] );
				remove_action( 'sc_email_send_after', [ $emails, 'send_after' ] );
			}
		}

		// Styling FIRST: the preview must resolve its copy against the same
		// single styling snapshot it renders with, exactly as a delivery resolves
		// its copy against the route it commits to. Reading it twice could show
		// one body and render another.
		$styling = self::styling( $state );

		list( $subject, $message ) = self::core_copy( $email_id, $state, $styling['template'] );
		$context                   = $email_id === Renderer::TICKET_ATTENDEE ? TicketContext::fictional() : OrderContext::fictional();
		$subject                   = self::replace_core_tags( $subject, $email_id, $context, true );
		$message                   = self::replace_core_tags( $message, $email_id, $context, false );

		$message_html = wpautop( make_clickable( wp_kses_post( $message ) ), false );
		$result       = Renderer::render_for_delivery(
			$email_id,
			$context,
			$message_html,
			$styling,
			[
				'calendar_action' => null,
				'calendar_url'    => home_url( '/?sc_email_calendar_preview=1' ),
			]
		);
		$document     = is_array( $result ) && array_key_exists( 'document', $result ) ? $result['document'] : null;

		if ( is_string( $document ) && $document !== '' ) {
			return [
				'document'     => self::replace_title( $document, $subject ),
				'content_type' => 'text/html',
				'subject'      => $subject,
			];
		}

		$emails = new LegacyEmails();

		try {
			$emails->heading = $email_id === Renderer::TICKET_ATTENDEE
				? esc_html__( 'Event Ticket', 'sugar-calendar-lite' )
				: esc_html__( 'Order Receipt', 'sugar-calendar-lite' );
			$document        = $emails->text_to_html( $emails->build_email( $message ) );
		} finally {
			remove_action( 'sc_email_send_before', [ $emails, 'send_before' ] );
			remove_action( 'sc_email_send_after', [ $emails, 'send_after' ] );
		}

		return [
			'document'     => self::replace_title( $document, $subject ),
			'content_type' => 'text/html',
			'subject'      => $subject,
		];
	}

	/**
	 * Render a first-party document from one saved ticket or order.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id  Notification id.
	 * @param int    $object_id Ticket or order id.
	 * @param array  $state     Sanitized state.
	 *
	 * @return array|WP_Error
	 */
	private static function real_document( $email_id, $object_id, array $state ) {

		return $email_id === TicketSaleAdminEmail::ID
			? self::admin_document_for_object( $email_id, $object_id, $state )
			: self::attendee_document_for_object( $email_id, $object_id, $state );
	}

	/**
	 * Render the plain-text ticket-sale admin record from one saved order.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id  Notification id.
	 * @param int    $object_id Order id.
	 * @param array  $state     Sanitized state.
	 *
	 * @return array|WP_Error
	 */
	private static function admin_document_for_object( $email_id, $object_id, array $state ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- LegacyEmails registers send hooks in its constructor; the preview removes those exact callbacks in finally.

		$order = get_order( $object_id );

		if ( empty( $order ) || empty( $order->id ) ) {
			return self::invalid_source( $email_id );
		}

		$emails = new LegacyEmails( Styling::LEGACY_TEMPLATE );

		try {
			$emails->email_id          = $email_id;
			$emails->object_id         = $object_id;
			$emails->object_type       = 'order';
			$emails->legacy_plain_text = true;

			list( $subject, $message ) = self::real_copy( $email_id, $state );
			$rendered                  = $emails->render(
				$subject,
				$message,
				[
					'admin_order_record' => true,
					'admin_order_preview' => true,
				]
			);

			return [
				'document'     => $rendered['message'],
				'content_type' => $rendered['content_type'],
				'subject'      => $rendered['subject'],
			];
		} finally {
			remove_action( 'sc_email_send_before', [ $emails, 'send_before' ] );
			remove_action( 'sc_email_send_after', [ $emails, 'send_after' ] );
		}
	}

	/**
	 * Render the attendee ticket or order receipt document from one saved record.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id  Notification id.
	 * @param int    $object_id Ticket or order id.
	 * @param array  $state     Sanitized state.
	 *
	 * @return array|WP_Error
	 */
	private static function attendee_document_for_object( $email_id, $object_id, array $state ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks, Generic.Metrics.CyclomaticComplexity.TooHigh -- LegacyEmails registers send hooks in its constructor; the preview removes those exact callbacks in finally.

		$ticket_url       = home_url( '/#sc-email-preview-ticket' );
		$receipt_url      = home_url( '/#sc-email-preview-receipt' );
		$registration_url = home_url( '/#sc-email-preview-registration' );
		$context          = $email_id === Renderer::TICKET_ATTENDEE
			? TicketContext::from_ticket( $object_id )
			: OrderContext::from_order_for_preview( $object_id );

		if ( ! is_array( $context ) ) {
			return self::invalid_source( $email_id );
		}

		$object_type = 'order';
		$heading     = '';
		$overrides   = [
			'ticket_url'  => $ticket_url,
			'receipt_url' => $receipt_url,
			'tickets'     => '',
		];
		$event_id    = isset( $context['event']['sc_event_id'] ) ? (int) $context['event']['sc_event_id'] : 0;

		if ( $email_id === Renderer::TICKET_ATTENDEE ) {
			$context['item_link']['url'] = $ticket_url;
			$object_type                 = 'ticket';
			$heading                     = esc_html__( 'Event Ticket', 'sugar-calendar-lite' );
		} else {
			foreach ( $context['tickets'] as $key => $ticket ) {
				$context['tickets'][ $key ]['url'] = $ticket_url;
			}

			$overrides['receipt_url'] = $context['order']['receipt_url'];
			$overrides['tickets']     = self::neutralize_links( get_email_tag_tickets( $object_id ), $ticket_url );

			if ( $email_id === Renderer::ORDER_RECEIPT ) {
				$heading = esc_html__( 'Order Receipt', 'sugar-calendar-lite' );
			}
		}

		$enrichment = new ContentEnrichment();
		$emails     = null;

		try {
			$styling  = self::real_styling( $state );
			$template = $styling['template'] === Styling::TEMPLATE_MODERN
				? Styling::TEMPLATE_MODERN
				: Styling::LEGACY_TEMPLATE;

			$emails                    = new LegacyEmails( $template );
			$emails->email_id          = $email_id;
			$emails->object_id         = $object_id;
			$emails->object_type       = $object_type;
			$emails->heading           = $heading;
			$emails->legacy_plain_text = false;

			list( $subject, $message ) = self::real_copy( $email_id, $state );

			$config = [
				'modern_context'      => $context,
				'modern_styling'      => $styling,
				'smart_tag_overrides' => $overrides,
				'content_enrichment'  => $enrichment,
			];

			if ( $email_id === Renderer::TICKET_ATTENDEE ) {
				$config['calendar_action'] = null;
				$config['calendar_url']    = home_url( '/?sc_email_calendar_preview=1' );

				$enrichment->install_ticket( $event_id );
			} elseif ( $email_id === Renderer::ORDER_RECEIPT ) {
				$enrichment->install_receipt( $object_id, $event_id, $registration_url );
			}

			$rendered = $emails->render( $subject, $message, $config );
			$document = $rendered['content_type'] === 'text/html'
				? self::replace_title( $rendered['message'], $rendered['subject'] )
				: $rendered['message'];

			return [
				'document'           => $document,
				'content_type'       => $rendered['content_type'],
				'subject'            => $rendered['subject'],
				self::PRESERVE_LINKS => true,
			];
		} finally {
			$enrichment->remove();

			if ( $emails !== null ) {
				remove_action( 'sc_email_send_before', [ $emails, 'send_before' ] );
				remove_action( 'sc_email_send_after', [ $emails, 'send_after' ] );
			}
		}
	}

	/**
	 * Resolve saved copy while preserving route-sensitive message provenance.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Notification id.
	 * @param array  $state    Sanitized state.
	 *
	 * @return array
	 */
	private static function real_copy( $email_id, array $state ) {

		$map = self::field_map()[ $email_id ];

		switch ( $email_id ) {
			case Renderer::ORDER_RECEIPT:
				$subject = get_receipt_subject();
				$message = get_receipt_message_resolution();
				break;

			case TicketSaleAdminEmail::ID:
				$subject = get_ticket_sale_admin_subject();
				$message = get_ticket_sale_admin_message();
				break;

			default:
				$subject = get_ticket_subject();
				$message = get_ticket_message_resolution();
		}

		if ( array_key_exists( $map['subject'], $state ) ) {
			$subject = $state[ $map['subject'] ];
		}

		if ( array_key_exists( $map['message'], $state ) ) {
			$message = (string) $state[ $map['message'] ];
		}

		return [ (string) $subject, $message ];
	}

	/**
	 * Resolve saved or unsaved copy for a core notification.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Notification id.
	 * @param array  $state    Sanitized state.
	 * @param string $template The exact template this preview renders with.
	 *
	 * @return array
	 */
	private static function core_copy( $email_id, array $state, $template ) {

		$map = self::field_map()[ $email_id ];

		// Each resolution already applied `sc_et_get_setting` once. The selector
		// only picks its route-appropriate candidate; it applies no second filter.
		// The admin notification has one candidate, so it needs no selection.
		switch ( $email_id ) {
			case Renderer::ORDER_RECEIPT:
				$subject = get_receipt_subject();
				$message = resolve_email_copy_for_template( get_receipt_message_resolution(), $template );
				break;

			case TicketSaleAdminEmail::ID:
				$subject = get_ticket_sale_admin_subject();
				$message = get_ticket_sale_admin_message();
				break;

			default:
				$subject = get_ticket_subject();
				$message = resolve_email_copy_for_template( get_ticket_message_resolution(), $template );
		}

		if ( array_key_exists( $map['subject'], $state ) ) {
			$subject = $state[ $map['subject'] ];
		}

		if ( array_key_exists( $map['message'], $state ) ) {
			$message = $state[ $map['message'] ];
		}

		return [ (string) $subject, (string) $message ];
	}

	/**
	 * Merge unsaved styling into the effective normalized settings.
	 *
	 * @since 3.14.0
	 *
	 * @param array $state Sanitized state.
	 *
	 * @return array
	 */
	private static function styling( array $state ) {

		$settings = get_option( 'sc_et_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		foreach ( Styling::setting_names() as $key ) {
			if ( array_key_exists( $key, $state ) ) {
				$settings[ $key ] = $state[ $key ];
			}
		}

		return Styling::from_settings( $settings );
	}

	/**
	 * Resolve a filter-aware styling snapshot for a real preview.
	 *
	 * @since 3.14.0
	 *
	 * @param array $state Sanitized state.
	 *
	 * @return array
	 */
	private static function real_styling( array $state ) {

		$settings = get_option( 'sc_et_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		foreach ( Styling::setting_names() as $key ) {
			if ( array_key_exists( $key, $state ) ) {
				$settings[ $key ] = Styling::sanitize_setting( $key, $state[ $key ] );
			}
		}

		return Styling::resolve( $settings );
	}

	/**
	 * Normalize the structural preview wrapper before any extension receives it.
	 *
	 * @since 3.14.0
	 *
	 * @param array $preview Preview wrapper.
	 *
	 * @return array
	 */
	private static function normalize_preview( array $preview ) {

		$route    = isset( $preview['route'] ) && is_scalar( $preview['route'] ) ? sanitize_key( (string) $preview['route'] ) : '';
		$email_id = isset( $preview['email_id'] ) && is_scalar( $preview['email_id'] ) ? sanitize_key( (string) $preview['email_id'] ) : '';
		$state    = isset( $preview['state'] ) && is_array( $preview['state'] ) ? $preview['state'] : [];

		return [
			'route'    => $route,
			'email_id' => $email_id,
			'state'    => $state,
		];
	}

	/**
	 * Whether the notification is owned by core's preview renderer.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Notification id.
	 *
	 * @return bool
	 */
	private static function is_core_email( $email_id ) {

		return in_array(
			$email_id,
			[ AttendeeTicketEmail::ID, OrderReceiptEmail::ID, TicketSaleAdminEmail::ID ],
			true
		);
	}

	/**
	 * Build a safe error for a rejected ticket or order source.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Notification id.
	 *
	 * @return WP_Error
	 */
	private static function invalid_source( $email_id ) {

		$source_type = $email_id === Renderer::TICKET_ATTENDEE ? 'ticket' : 'order';

		return new WP_Error(
			self::ERROR_INVALID_SOURCE,
			esc_html__( 'The selected source could not be used for this email preview.', 'sugar-calendar-lite' ),
			[
				'status'      => 422,
				'email_id'    => $email_id,
				'source_type' => $source_type,
			]
		);
	}

	/**
	 * Validate a provider result and apply its branch's final link policy.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $document       Preview provider result.
	 * @param bool  $preserve_links Whether the selected real core document owns its links.
	 *
	 * @return array|WP_Error
	 */
	private static function validate_document( $document, $preserve_links ) {

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		if ( is_string( $document ) && trim( $document ) !== '' ) {
			return [
				'document'     => $preserve_links ? $document : self::neutralize_links( $document ),
				'content_type' => 'text/html',
				'subject'      => '',
			];
		}

		if (
			is_array( $document )
			&& isset( $document['document'], $document['content_type'], $document['subject'] )
			&& is_string( $document['document'] )
			&& trim( $document['document'] ) !== ''
			&& in_array( $document['content_type'], [ 'text/html', 'text/plain' ], true )
			&& is_string( $document['subject'] )
		) {
			if ( ! $preserve_links && $document['content_type'] === 'text/html' ) {
				$document['document'] = self::neutralize_links( $document['document'] );
			}

			return $document;
		}

		return new WP_Error(
			self::ERROR_MISSING_RENDERER,
			esc_html__( 'A preview renderer is not available for this email.', 'sugar-calendar-lite' ),
			[ 'status' => 422 ]
		);
	}

	/**
	 * Replace core Smart Tags with deterministic fictional values.
	 *
	 * @since 3.14.0
	 *
	 * @param string $value      Copy to resolve.
	 * @param string $email_id   Notification id.
	 * @param array  $context    Fictional renderer context.
	 * @param bool   $is_subject Whether the copy is a subject.
	 *
	 * @return string
	 */
	private static function replace_core_tags( $value, $email_id, array $context, $is_subject ) {

		$event = $context['event'];
		$tags  = [
			'{event_id}'         => (string) $event['sc_event_id'],
			'{event_title}'      => $event['title'],
			'{event_url}'        => $event['url'],
			'{event_date}'       => __( 'September 22, 2026', 'sugar-calendar-lite' ),
			'{event_start_time}' => $event['time'],
			'{event_end_time}'   => __( '10:00 pm', 'sugar-calendar-lite' ),
		];

		if ( $email_id === Renderer::TICKET_ATTENDEE ) {
			$tags += [
				'{ticket_id}'      => '101',
				'{ticket_code}'    => 'TKT-ABCDE-12345',
				'{ticket_url}'     => $context['item_link']['url'],
				'{attendee_name}'  => __( 'Jane Doe', 'sugar-calendar-lite' ),
				'{attendee_email}' => 'jane@example.com',
			];
		} else {
			$ticket_lines = [];

			foreach ( $context['tickets'] as $ticket ) {
				$ticket_lines[] = '<li>' . esc_html( $ticket['attendee'] . ': ' . $ticket['code'] ) . '</li>';
			}

			$tags += [
				'{name}'         => $context['purchaser']['name'],
				'{email}'        => $context['purchaser']['email'],
				'{order_id}'     => (string) $context['order']['id'],
				'{order_amount}' => $context['order']['amount_paid']['value'],
				'{order_date}'   => $context['order']['date'],
				'{receipt_url}'  => $context['order']['receipt_url'],
				'{tickets}'      => '<ul>' . implode( '', $ticket_lines ) . '</ul>',
			];
		}

		if ( $is_subject ) {
			unset( $tags['{tickets}'], $tags['{receipt_url}'] );
		}

		return SmartTagResolver::replace( (string) $value, $tags );
	}

	/**
	 * Point every anchor at the selected safe destination.
	 *
	 * Preview links are built from fictional data, so their destinations are
	 * wrong by construction; a `#` href keeps the rendered layout identical
	 * without navigating anywhere.
	 *
	 * @since 3.14.0
	 *
	 * @param string $document    Complete HTML document.
	 * @param string $destination Replacement destination.
	 *
	 * @return string
	 */
	private static function neutralize_links( $document, $destination = '#' ) {

		return preg_replace_callback(
			'#(<a\b[^>]*\bhref\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s>]+)#i',
			static function ( $matches ) use ( $destination ) {

				return $matches[1] . '"' . esc_attr( $destination ) . '"';
			},
			$document
		);
	}

	/**
	 * Replace only the preview document title.
	 *
	 * @since 3.14.0
	 *
	 * @param string $document Complete HTML document.
	 * @param string $subject  Resolved subject.
	 *
	 * @return string|null
	 */
	private static function replace_title( $document, $subject ) {

		return preg_replace_callback(
			'#<title>.*?</title>#is',
			static function () use ( $subject ) {

				return '<title>' . esc_html( $subject ) . '</title>';
			},
			$document,
			1
		);
	}
}
