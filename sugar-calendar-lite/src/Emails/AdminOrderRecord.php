<?php

namespace Sugar_Calendar\Emails;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;

/**
 * Resolve one ticket-sale administrator record.
 *
 * @since 3.14.0
 */
class AdminOrderRecord {

	/**
	 * Subject Smart Tags.
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private const SUBJECT_TAGS = [
		'order_time',
		'transaction_id',
		'order_admin_url',
		'ticket_count',
		'event_end_date',
		'event_location',
	];

	/**
	 * New-record Smart Tags.
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private const NEW_TAGS = [
		'order_time',
		'transaction_id',
		'order_admin_url',
		'ticket_count',
		'admin_tickets_info',
		'event_end_date',
		'event_location',
	];

	/**
	 * All Smart Tags tracked by this record.
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private const TRACKED_TAGS = [
		'name',
		'email',
		'order_id',
		'order_amount',
		'order_date',
		'receipt_url',
		'event_id',
		'event_title',
		'event_url',
		'event_date',
		'event_start_time',
		'event_end_time',
		'order_time',
		'transaction_id',
		'order_admin_url',
		'ticket_count',
		'admin_tickets_info',
		'event_end_date',
		'event_location',
	];

	/**
	 * Get tags allowed in subjects.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function subject_tags(): array {

		return self::SUBJECT_TAGS;
	}

	/**
	 * Get tags available to new records.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function new_tags(): array {

		return self::NEW_TAGS;
	}

	/**
	 * Determine whether a tag belongs to this record.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag Tag slug.
	 *
	 * @return bool
	 */
	public static function tracks_tag( string $tag ): bool {

		return in_array( $tag, self::TRACKED_TAGS, true );
	}

	/**
	 * Determine whether content uses a new-record tag.
	 *
	 * @since 3.14.0
	 *
	 * @param string $content Record content.
	 *
	 * @return bool
	 */
	public static function needs_record( string $content ): bool {

		return self::contains_tag( $content, self::NEW_TAGS );
	}

	/**
	 * Determine whether content uses the receipt tag.
	 *
	 * @since 3.14.0
	 *
	 * @param string $content Record content.
	 *
	 * @return bool
	 */
	public static function contains_receipt( string $content ): bool {

		return false !== strpos( $content, '{receipt_url}' );
	}

	/**
	 * Resolve whether an order's stored event is available.
	 *
	 * @since 3.14.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array|null
	 */
	public static function source_state( int $order_id ): ?array {

		$order = get_order( $order_id );

		if ( ! is_object( $order ) || empty( $order->id ) ) {
			return null;
		}

		$event = sugar_calendar_get_event( ! empty( $order->event_id ) ? (int) $order->event_id : 0 );

		return [
			'event_available' => is_object( $event ) && ! empty( $event->id ),
		];
	}

	/**
	 * Get optional tags for an event state.
	 *
	 * @since 3.14.0
	 *
	 * @param bool $event_available Whether the event is available.
	 *
	 * @return array
	 */
	public static function optional_tags( bool $event_available ): array {

		$tags = [ 'order_date', 'order_time', 'transaction_id', 'event_location', 'receipt_url' ];

		if ( ! $event_available ) {
			$tags = array_merge( $tags, [ 'event_date', 'event_start_time', 'event_end_date', 'event_end_time', 'event_url' ] );
		}

		return $tags;
	}

	/**
	 * Resolve a saved order into administrator render configuration.
	 *
	 * @since 3.14.0
	 *
	 * @param int  $order_id        Order id.
	 * @param bool $preview         Whether this is a preview.
	 * @param bool $include_receipt Whether to include a receipt URL.
	 *
	 * @return array|null
	 */
	public static function render_config( int $order_id, bool $preview = false, bool $include_receipt = false ): ?array {

		$context = OrderContext::from_order_for_admin( $order_id, $preview, $include_receipt );

		return is_array( $context ) ? self::render_config_from_context( $context, false ) : null;
	}

	/**
	 * Resolve an order context into administrator render configuration.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context   Administrator order context.
	 * @param bool  $fictional Whether this is a no-database preview.
	 *
	 * @return array|null
	 */
	public static function render_config_from_context( array $context, bool $fictional = false ): ?array {

		if ( ! self::valid_context( $context ) ) {
			return null;
		}

		$event_available = ! empty( $context['event']['available'] );
		$admin_values    = self::values( $context );
		$prepared_values = [
			'receipt_url' => AdminNotificationText::value( $context['order']['receipt_url'] ?? '' ),
		];

		if ( $fictional ) {
			$prepared_values = array_merge( self::fictional_existing_values( $context ), $prepared_values );
		}

		return [
			'admin_tag_values'    => array_intersect_key( $admin_values, array_flip( self::NEW_TAGS ) ),
			'prepared_tag_values' => $prepared_values,
			'optional_smart_tags' => self::optional_tags( $event_available ),
			'event_available'     => $event_available,
			'all_day'             => ! empty( $context['event']['all_day'] ),
			'fictional'           => $fictional,
		];
	}

	/**
	 * Check the complete context shape without dereferencing malformed values.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context Administrator order context.
	 *
	 * @return bool
	 */
	private static function valid_context( array $context ): bool {

		foreach ( [ 'event', 'location', 'purchaser', 'order', 'tickets' ] as $key ) {
			if ( ! array_key_exists( $key, $context ) || ! is_array( $context[ $key ] ) ) {
				return false;
			}
		}

		if ( ! array_key_exists( 'amount_paid', $context['order'] ) || ! is_array( $context['order']['amount_paid'] ) ) {
			return false;
		}

		if ( ! self::has_safe_id( $context['order'], 'id' ) || ! self::has_safe_id( $context['event'], 'sc_event_id' ) ) {
			return false;
		}

		if ( ! self::valid_tickets( $context['tickets'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check the shape and identifiers of every ticket row.
	 *
	 * @since 3.14.0
	 *
	 * @param array $tickets Ticket records.
	 *
	 * @return bool
	 */
	private static function valid_tickets( array $tickets ): bool {

		foreach ( $tickets as $ticket ) {
			if ( ! is_array( $ticket ) || ! self::has_safe_id( $ticket, 'id' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check that an optional identifier can be safely cast.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $values Values to inspect.
	 * @param string $key    Identifier key.
	 *
	 * @return bool
	 */
	private static function has_safe_id( array $values, string $key ): bool {

		return ! array_key_exists( $key, $values ) || is_scalar( $values[ $key ] );
	}

	/**
	 * Build values for the new administrator tags.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context Administrator order context.
	 *
	 * @return array
	 */
	private static function values( array $context ): array {

		return [
			'order_time'         => AdminNotificationText::value( $context['order']['time'] ?? '' ),
			'transaction_id'     => AdminNotificationText::value( $context['order']['transaction_id'] ?? '' ),
			'order_admin_url'    => AdminNotificationText::value( $context['order']['admin_url'] ?? '' ),
			'ticket_count'       => (string) count( $context['tickets'] ),
			'admin_tickets_info' => self::tickets( $context['tickets'] ),
			'event_end_date'     => AdminNotificationText::value( $context['event']['end_date'] ?? '' ),
			'event_location'     => AdminNotificationText::location( $context['location'] ),
		];
	}

	/**
	 * Build existing atomic values for a fictional preview.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context Administrator order context.
	 *
	 * @return array
	 */
	private static function fictional_existing_values( array $context ): array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded -- Explicit flat mapping keeps each released preview value visible.

		$all_day = ! empty( $context['event']['all_day'] );
		$time    = $all_day ? __( 'All-day', 'sugar-calendar-lite' ) : ( $context['event']['time'] ?? '' );

		return [
			'name'             => AdminNotificationText::value( $context['purchaser']['name'] ?? '' ),
			'email'            => AdminNotificationText::value( $context['purchaser']['email'] ?? '' ),
			'order_id'         => (string) (int) ( $context['order']['id'] ?? 0 ),
			'order_amount'     => AdminNotificationText::value( $context['order']['amount_paid']['value'] ?? '' ),
			'order_date'       => AdminNotificationText::value( $context['order']['date_only'] ?? '' ),
			'event_id'         => (string) (int) ( $context['event']['sc_event_id'] ?? 0 ),
			'event_title'      => AdminNotificationText::value( $context['event']['title'] ?? '' ),
			'event_url'        => AdminNotificationText::value( $context['event']['url'] ?? '' ),
			'event_date'       => AdminNotificationText::value( $context['event']['date'] ?? '' ),
			'event_start_time' => AdminNotificationText::value( $time ),
			'event_end_time'   => AdminNotificationText::value( $all_day ? $time : ( $context['event']['end_time'] ?? '' ) ),
		];
	}

	/**
	 * Format all ticket records as plain text.
	 *
	 * @since 3.14.0
	 *
	 * @param array $tickets Ticket records.
	 *
	 * @return string
	 */
	private static function tickets( array $tickets ): string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- Explicit formatter preserves ticket field order and omission rules.

		if ( empty( $tickets ) ) {
			return __( 'No ticket records were found.', 'sugar-calendar-lite' );
		}

		$records = [];

		foreach ( array_values( $tickets ) as $index => $ticket ) {
			$type = AdminNotificationText::value( $ticket['type'] ?? '' );

			if ( $type === '' ) {
				$type = __( 'Ticket', 'sugar-calendar-lite' );
			}

			/* translators: 1: Ticket number. 2: Ticket type. */
			$lines = [ sprintf( __( '%1$d. %2$s', 'sugar-calendar-lite' ), $index + 1, $type ) ];

			$labels = [
				'id'       => __( 'Ticket ID', 'sugar-calendar-lite' ),
				'code'     => __( 'Code', 'sugar-calendar-lite' ),
				'attendee' => __( 'Attendee', 'sugar-calendar-lite' ),
				'email'    => __( 'Attendee email', 'sugar-calendar-lite' ),
			];

			/* translators: 1: Ticket field label. 2: Ticket field value. */
			$detail_format = __( '%1$s: %2$s', 'sugar-calendar-lite' );
			$lines[]       = '   ' . sprintf( $detail_format, $labels['id'], (string) (int) ( $ticket['id'] ?? 0 ) );

			foreach ( [ 'code', 'attendee', 'email' ] as $key ) {
				$value = AdminNotificationText::value( $ticket[ $key ] ?? '' );

				if ( $value !== '' ) {
					$lines[] = '   ' . sprintf( $detail_format, $labels[ $key ], $value );
				}
			}

			$records[] = implode( "\n", $lines );
		}

		return implode( "\n\n", $records );
	}

	/**
	 * Find an exact Smart Tag in content.
	 *
	 * @since 3.14.0
	 *
	 * @param string $content Content to inspect.
	 * @param array  $tags    Known tag slugs.
	 *
	 * @return bool
	 */
	private static function contains_tag( string $content, array $tags ): bool {

		foreach ( $tags as $tag ) {
			if ( false !== strpos( $content, '{' . $tag . '}' ) ) {
				return true;
			}
		}

		return false;
	}
}
