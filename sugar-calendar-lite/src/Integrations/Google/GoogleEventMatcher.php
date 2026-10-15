<?php

namespace Sugar_Calendar\Integrations\Google;

use WP_Post;

/**
 * Relates Google items to Sugar Calendar events: finds the event an item belongs to and tells which side changed.
 *
 * @since 3.16.0
 */
class GoogleEventMatcher {

	/**
	 * Link storage.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleLinkRepository
	 */
	private $links;

	/**
	 * Inbound mapper.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleItemMapper
	 */
	private $mapper;

	/**
	 * Outbound builder, for the fingerprint a push stores.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleEventBuilder
	 */
	private $builder;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleLinkRepository $links   Link storage.
	 * @param GoogleItemMapper     $mapper  Inbound mapper.
	 * @param GoogleEventBuilder   $builder Outbound builder.
	 */
	public function __construct( GoogleLinkRepository $links, GoogleItemMapper $mapper, GoogleEventBuilder $builder ) {

		$this->links   = $links;
		$this->mapper  = $mapper;
		$this->builder = $builder;
	}

	/**
	 * Find the Sugar Calendar event for a Google item: our link, then our stamp, then uuid, else null.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return object|null
	 */
	public function match( array $item, string $calendar_id ) {

		$event = $this->match_by_link( $item, $calendar_id );

		if ( $event ) {
			return $event;
		}

		$private = $item['extendedProperties']['private'] ?? [];

		if ( ! empty( $private['sugar_calendar_event_id'] ) && ( $private['sugar_calendar_site'] ?? '' ) === home_url() ) {
			$event = sugar_calendar_get_event( (int) $private['sugar_calendar_event_id'] );

			if ( ! empty( $event->id ) ) {
				return $event;
			}
		}

		$uuid = $this->mapper->normalize_uuid( (string) ( $item['iCalUID'] ?? '' ) );

		if ( $uuid === '' ) {
			return null;
		}

		$event = sugar_calendar_get_event_by( 'uuid', $uuid );

		return ! empty( $event->id ) ? $event : null;
	}

	/**
	 * The event already linked to this Google id in this calendar, or null.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return object|null
	 */
	public function match_by_link( array $item, string $calendar_id ) {

		$event_id = $this->links->find_event_by_google_id( (string) ( $item['id'] ?? '' ), $calendar_id );

		if ( $event_id === 0 ) {
			return null;
		}

		$event = sugar_calendar_get_event( $event_id );

		return ! empty( $event->id ) ? $event : null;
	}

	/**
	 * The local series and replaced date of a Google instance, or null when Sugar Calendar holds no such series.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $item        Google instance resource.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return array|null [ 'series_id' => int, 'date' => Y-m-d ].
	 */
	public function instance_target( array $item, string $calendar_id ) {

		// Lite, or a series Pro could not hold: no local series to take the date.
		if ( ! sugar_calendar()->is_pro() ) {
			return null;
		}

		$series_id = $this->links->find_event_by_google_id( (string) $item['recurringEventId'], $calendar_id );
		$series    = $series_id ? sugar_calendar_get_event( $series_id ) : null;

		if ( empty( $series->id ) || ( $this->links->get_links( $series->id )[ $calendar_id ]['rule'] ?? '' ) === 'google' ) {
			return null;
		}

		$date = $this->mapper->original_date( $item, $series );

		if ( $date === '' ) {
			return null;
		}

		return [
			'series_id' => (int) $series->id,
			'date'      => $date,
		];
	}

	/**
	 * The event's stored link for a calendar when Google's copy changed since the last sync, or null when its etag is the same.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event       Existing event.
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return array|null Stored link, [] when the event was never linked.
	 */
	public function changed_link( $event, array $item, string $calendar_id ): ?array {

		$link = $this->links->get_links( $event->id )[ $calendar_id ] ?? [];

		return (string) ( $link['etag'] ?? '' ) === (string) ( $item['etag'] ?? '' ) ? null : $link;
	}

	/**
	 * Whether Sugar Calendar's copy changed since the last sync and is newer than Google's.
	 *
	 * @since 3.16.0
	 *
	 * @param object  $event Existing event.
	 * @param WP_Post $post  Its post.
	 * @param array   $link  Stored link.
	 * @param array   $item  Google event resource.
	 *
	 * @return bool
	 */
	public function local_is_newer( $event, WP_Post $post, array $link, array $item ): bool {

		if ( $this->builder->fingerprint( $event, $post, (string) $link['event_id'] ) === (string) ( $link['hash'] ?? '' ) ) {
			return false;
		}

		$google = strtotime( (string) ( $item['updated'] ?? '' ) );

		// The column is written with gmdate() and read back without a zone.
		$local = strtotime( (string) $event->date_modified . ' UTC' );

		return $google !== false && $local !== false && $local > $google;
	}
}
