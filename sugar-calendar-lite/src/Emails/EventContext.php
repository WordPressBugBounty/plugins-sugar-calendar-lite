<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Event;
use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;

/**
 * Resolve the event values shared by every Modern email context.
 *
 * @since 3.14.0
 */
class EventContext {

	/**
	 * Resolve one event into renderable values.
	 *
	 * @since 3.14.0
	 *
	 * @param Event  $event         Event.
	 * @param int    $occurrence_id Occurrence id.
	 * @param object $source        Optional ticket or order row.
	 *
	 * @return array|null
	 */
	public static function from_event( $event, int $occurrence_id = 0, $source = null ): ?array {

		if ( empty( $event ) || empty( $event->id ) ) {
			return null;
		}

		$sc_event_id = (int) $event->id;
		$event       = self::resolve( $event, $source );

		if ( $event === null ) {
			return null;
		}

		return [
			'event'    => self::event_values( $event, $sc_event_id, $occurrence_id ),
			'location' => self::location_values( $event ),
			'site'     => [
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url(),
			],
		];
	}

	/**
	 * Resolve one event into administrator email values.
	 *
	 * @since 3.14.0
	 *
	 * @param Event  $event         Event.
	 * @param int    $occurrence_id Occurrence id.
	 * @param object $source        Optional ticket or order row.
	 *
	 * @return array|null
	 */
	public static function from_event_for_admin( $event, int $occurrence_id = 0, $source = null ): ?array {

		if ( empty( $event ) || empty( $event->id ) ) {
			return null;
		}

		$sc_event_id = (int) $event->id;
		$event       = self::resolve( $event, $source );

		if ( $event === null ) {
			return null;
		}

		$values              = self::event_values( $event, $sc_event_id, $occurrence_id, false );
		$values['end_date']  = $event->format_date( sc_get_date_format(), $event->end );
		$values['end_time']  = $event->is_all_day()
			? __( 'All-day', 'sugar-calendar-lite' )
			: $event->format_date( sc_get_time_format(), $event->end );
		$values['available'] = true;

		return [
			'event'    => $values,
			'location' => self::location_values( $event ),
			'site'     => [
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url(),
			],
		];
	}

	/**
	 * Resolve the event object shared by context and item enrichment.
	 *
	 * @since 3.14.0
	 *
	 * @param Event  $event  Event.
	 * @param object $source Optional ticket or order row.
	 *
	 * @return Event|null
	 */
	public static function resolve( $event, $source = null ) {

		if ( empty( $event ) || empty( $event->id ) ) {
			return null;
		}

		if ( is_object( $source ) ) {
			/** This filter is documented in includes/common/Features/EventTicketing/includes/common/functions.php */
			$event = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				'sc_et_receipt_email_tag_event_date_event', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the established public ticketing filter for existing integrations.
				$event,
				self::source_type( $source ),
				$source
			);
		}

		if ( empty( $event ) || empty( $event->id ) ) {
			return null;
		}

		return $event;
	}

	/**
	 * Deterministic event values for previews.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function fictional(): array {

		$sample_start = strtotime( '2026-09-22 19:00:00' );

		return [
			'event'    => [
				'sc_event_id'   => 1,
				'object_id'     => 1,
				'occurrence_id' => 0,
				'title'         => __( 'Disco Jam: Big Event 2026', 'sugar-calendar-lite' ),
				'url'           => home_url( '/events/disco-jam-big-event-2026/' ),
				'image_url'     => plugins_url( 'assets/images/emails/preview-featured-image.png', SC_PLUGIN_FILE ),
				'date'          => date_i18n( sc_get_date_format(), $sample_start ),
				'month'         => date_i18n( 'M', $sample_start ),
				'day'           => date_i18n( 'j', $sample_start ),
				'time'          => date_i18n( sc_get_time_format(), $sample_start ),
				'all_day'       => false,
			],
			'location' => [
				'name'      => __( 'Central Park', 'sugar-calendar-lite' ),
				'venue_url' => home_url( '/venues/central-park/' ),
				'address'   => __( '41st Street, New York, NY 10001', 'sugar-calendar-lite' ),
				'map_url'   => 'https://www.google.com/maps/search/?api=1&query=41st%20Street%2C%20New%20York%2C%20NY%2010001',
			],
			'site'     => [
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url(),
			],
		];
	}

	/**
	 * Deterministic administrator event values for previews.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function fictional_for_admin(): array {

		$values = self::fictional();

		$values['event']['end_date'] = __( 'September 22, 2026', 'sugar-calendar-lite' );
		$values['event']['end_time'] = __( '10:00 pm', 'sugar-calendar-lite' );
		$values['event']['available'] = true;

		return $values;
	}

	/**
	 * Build unavailable administrator event values.
	 *
	 * @since 3.14.0
	 *
	 * @param int $event_id Event id.
	 *
	 * @return array
	 */
	public static function unavailable( int $event_id ): array {

		return [
			'event'    => [
				'sc_event_id'   => $event_id,
				'object_id'     => 0,
				'occurrence_id' => 0,
				'title'         => '',
				'url'           => '',
				'image_url'     => '',
				'date'          => '',
				'month'         => '',
				'day'           => '',
				'time'          => '',
				'end_date'      => '',
				'end_time'      => '',
				'all_day'       => false,
				'available'     => false,
			],
			'location' => [
				'name'      => '',
				'venue_url' => '',
				'address'   => '',
				'map_url'   => '',
			],
			'site'     => [
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url(),
			],
		];
	}

	/**
	 * Resolve administrator location values without Event Ticketing filters.
	 *
	 * @since 3.14.0
	 *
	 * @param Event $event Event.
	 *
	 * @return array
	 */
	public static function location_for_admin( $event ): array {

		if ( empty( $event ) || empty( $event->id ) ) {
			return [
				'name'      => '',
				'venue_url' => '',
				'address'   => '',
				'map_url'   => '',
			];
		}

		return self::location_values( $event );
	}

	/**
	 * Event and date display values.
	 *
	 * @since 3.14.0
	 *
	 * @param Event $event         Resolved display event.
	 * @param int   $sc_event_id   Original Sugar Calendar event id.
	 * @param int   $occurrence_id Occurrence id.
	 * @param bool  $filter_url    Whether to apply the final Event Ticketing URL filter.
	 *
	 * @return array
	 */
	private static function event_values( $event, $sc_event_id, $occurrence_id, $filter_url = true ) {

		$all_day = (bool) $event->is_all_day();
		$time    = $all_day
			? __( 'All-day', 'sugar-calendar-lite' )
			: $event->format_date( sc_get_time_format(), $event->start );

		$image_url = get_the_post_thumbnail_url( $event->object_id, 'large' );
		$url       = (string) get_permalink( $event->object_id );

		if ( $filter_url ) {
			/** This filter is documented in includes/common/Features/EventTicketing/includes/common/functions.php */
			$filtered_url = apply_filters( 'sc_et_receipt_email_tag_event_url', $url, $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the established public ticketing filter for existing integrations.
			$url          = is_string( $filtered_url ) ? $filtered_url : $url;
		}

		return [
			'sc_event_id'   => (int) $sc_event_id,
			'object_id'     => (int) $event->object_id,
			'occurrence_id' => (int) $occurrence_id,
			'title'         => (string) $event->title,
			'url'           => $url,
			'image_url'     => is_string( $image_url ) ? $image_url : '',
			'date'          => $event->format_date( sc_get_date_format(), $event->start ),
			'month'         => $event->format_date( 'M', $event->start ),
			'day'           => $event->format_date( 'j', $event->start ),
			'time'          => $time,
			'all_day'       => $all_day,
		];
	}

	/**
	 * Location values with venue and map links kept separate.
	 *
	 * @since 3.14.0
	 *
	 * @param Event $event Event.
	 *
	 * @return array
	 */
	private static function location_values( $event ) {

		$defaults = [
			'name'      => '',
			'venue_url' => '',
			'address'   => (string) $event->location,
			'map_url'   => '',
		];

		/**
		 * Filters the location values rendered in a Modern email.
		 *
		 * @since 3.14.0
		 *
		 * @param array $location Location values.
		 * @param Event $event    Event.
		 */
		$location = apply_filters( 'sugar_calendar_emails_location', $defaults, $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( ! is_array( $location ) ) {
			$location = $defaults;
		}

		$location = array_merge( $defaults, array_intersect_key( $location, $defaults ) );
		$address  = (string) $location['address'];

		$location['map_url'] = $address !== '' && ! empty( Sugar_Calendar_Helpers::get_google_maps_api_key() )
			? self::map_url( $address )
			: '';

		return $location;
	}

	/**
	 * Build a Google Maps search URL.
	 *
	 * @since 3.14.0
	 *
	 * @param string $address Address.
	 *
	 * @return string
	 */
	private static function map_url( $address ) {

		return add_query_arg(
			[
				'api'   => 1,
				'query' => rawurlencode( $address ),
			],
			'https://www.google.com/maps/search/'
		);
	}

	/**
	 * Infer the existing recurrence filter's source label.
	 *
	 * @since 3.14.0
	 *
	 * @param object $source Ticket or order row.
	 *
	 * @return string
	 */
	private static function source_type( $source ) {

		return property_exists( $source, 'ticket_type_id' ) ? 'ticket' : 'order';
	}
}
