<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Emails\Modern\SingleEventEmail;
use Sugar_Calendar\Emails\Modern\MultiTicketEmail;
use Sugar_Calendar\Emails\Ticketing\AttendeeTicketEmail;
use Sugar_Calendar\Emails\Ticketing\OrderReceiptEmail;

/**
 * Public, Lite-safe entry point for Modern email documents.
 *
 * @since 3.14.0
 */
class Renderer {

	const TICKET_ATTENDEE = AttendeeTicketEmail::ID;

	const ORDER_RECEIPT = OrderReceiptEmail::ID;

	/**
	 * Whether a notification id has an approved Modern layout.
	 *
	 * Reads the registered definition, never the filtered listing: this runs on
	 * front-end sends and the listing filter is an admin seam.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Notification id.
	 *
	 * @return bool
	 */
	public static function supports( string $email_id ): bool {

		$email = EmailRegistry::definition( $email_id );

		return $email !== null && $email->layout() !== null;
	}

	/**
	 * Render a Modern document when the id and styling permit it.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id     Notification id.
	 * @param array  $context      Renderer context.
	 * @param string $message_html Render-ready message fragment.
	 * @param array  $styling      Optional effective styling override.
	 *
	 * @return string|null
	 */
	public static function render( string $email_id, array $context, string $message_html, array $styling = [] ): ?string {

		$result = self::render_for_delivery( $email_id, $context, $message_html, $styling );

		return $result === null ? null : $result['document'];
	}

	/**
	 * Render a Modern delivery document and its supplied calendar action.
	 * A present `calendar_action` option suppresses legacy URL resolution.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id     Notification id.
	 * @param array  $context      Renderer context.
	 * @param string $message_html Render-ready message fragment.
	 * @param array  $styling      Optional effective styling override.
	 * @param array  $options      Optional `calendar_action` (array|null), `calendar_url` (string),
	 *                            and `parse_footer` (callable) for trusted footer copy.
	 *
	 * @return array|null `document` string and `calendar_action` array|null, or null.
	 */
	public static function render_for_delivery( // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded -- The ordered eligibility and action selection form one public boundary.
		string $email_id,
		array $context,
		string $message_html,
		array $styling = [],
		array $options = []
	): ?array { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		$email = EmailRegistry::definition( $email_id );

		if ( $email === null || $email->layout() === null ) {
			return null;
		}

		$is_single = $email->layout() === EmailInterface::LAYOUT_SINGLE;

		if ( ! $is_single && ! self::is_multi_context( $context ) ) {
			return null;
		}

		$styling = empty( $styling )
			? Styling::get()
			: Styling::normalize( $styling );

		if ( $styling['template'] !== Styling::TEMPLATE_MODERN ) {
			return null;
		}

		$parse_footer = isset( $options['parse_footer'] ) && is_callable( $options['parse_footer'] )
			? $options['parse_footer']
			: null;

		if ( ! $is_single ) {
			return [
				'document'        => MultiTicketEmail::render( $context, $styling, $message_html, $parse_footer ),
				'calendar_action' => null,
			];
		}

		$has_action = array_key_exists( 'calendar_action', $options );
		$action     = $has_action && is_array( $options['calendar_action'] ) ? $options['calendar_action'] : null;

		if ( array_key_exists( 'calendar_url', $options ) ) {
			$calendar_url = is_string( $options['calendar_url'] ) ? $options['calendar_url'] : '';
		} elseif ( $action !== null ) {
			$calendar_url = isset( $action['url'] ) && is_string( $action['url'] ) ? $action['url'] : '';
		} else {
			$calendar_url = $has_action ? '' : null;
		}

		return [
			'document'        => SingleEventEmail::render( $context, $styling, $message_html, $calendar_url, $parse_footer ),
			'calendar_action' => $action,
		];
	}

	/**
	 * Whether a context satisfies the complete multi-ticket renderer contract.
	 *
	 * Empty strings remain valid for optional display values, but every renderer
	 * key and nested value type must be stable before crossing the public facade.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context Renderer context.
	 *
	 * @return bool
	 */
	private static function is_multi_context( array $context ): bool {

		foreach ( [ 'event', 'location', 'site', 'purchaser', 'order', 'tickets' ] as $key ) {
			if ( ! isset( $context[ $key ] ) || ! is_array( $context[ $key ] ) ) {
				return false;
			}
		}

		if (
			! self::has_integer_values( $context['event'], [ 'sc_event_id', 'object_id', 'occurrence_id' ] )
			|| ! self::has_string_values( $context['event'], [ 'title', 'url', 'image_url', 'date', 'month', 'day', 'time' ] )
			|| ! self::has_string_values( $context['location'], [ 'name', 'venue_url', 'address', 'map_url' ] )
			|| ! self::has_string_values( $context['site'], [ 'name', 'url' ] )
			|| ! self::has_string_values( $context['purchaser'], [ 'name', 'email' ] )
			|| ! self::has_integer_values( $context['order'], [ 'id' ] )
			|| ! self::has_string_values( $context['order'], [ 'date', 'transaction_id', 'receipt_url' ] )
			|| ! isset( $context['order']['amount_paid'] )
			|| ! is_array( $context['order']['amount_paid'] )
			|| ! self::has_string_values( $context['order']['amount_paid'], [ 'label', 'value' ] )
		) {
			return false;
		}

		foreach ( $context['tickets'] as $ticket ) {
			if (
				! is_array( $ticket )
				|| ! self::has_integer_values( $ticket, [ 'id' ] )
				|| ! self::has_string_values( $ticket, [ 'attendee', 'email', 'type', 'code', 'cost', 'url', 'print_url' ] )
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether all named values exist and are strings.
	 *
	 * @since 3.14.0
	 *
	 * @param array $values Values.
	 * @param array $keys   Required keys.
	 *
	 * @return bool
	 */
	private static function has_string_values( array $values, array $keys ): bool {

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $values ) || ! is_string( $values[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether all named values exist and are integers.
	 *
	 * @since 3.14.0
	 *
	 * @param array $values Values.
	 * @param array $keys   Required keys.
	 *
	 * @return bool
	 */
	private static function has_integer_values( array $values, array $keys ): bool {

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $values ) || ! is_int( $values[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}
}
