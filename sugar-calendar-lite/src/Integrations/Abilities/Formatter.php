<?php
/**
 * Output shaping for the WordPress Abilities API integration.
 *
 * @package Sugar_Calendar
 * @subpackage Integrations\Abilities
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Sugar_Calendar\Event;
use WP_Term;

/**
 * Formatter — pure transforms from Sugar Calendar objects into the canonical
 * array shapes the abilities return. No database access; callers supply any
 * resolved calendar map.
 *
 * @since 3.12.0
 */
class Formatter {

	/**
	 * Render a calendar (sc_event_category term) into the canonical calendar shape.
	 *
	 * @since 3.12.0
	 *
	 * @param WP_Term $term Calendar term.
	 *
	 * @return array { id: int, slug: string, name: string, count: int }
	 */
	public function calendar( WP_Term $term ): array {

		return [
			'id'    => $term->term_id,
			'slug'  => $term->slug,
			'name'  => $term->name,
			'count' => $term->count,
		];
	}

	/**
	 * Normalize a stored datetime + timezone into an ISO 8601 UTC string.
	 * Empty / zero-date / unparseable inputs return ''.
	 *
	 * @since 3.12.0
	 *
	 * @param string $datetime Stored datetime, e.g. '2026-05-15 14:00:00'.
	 * @param string $tz_name  IANA timezone name. Empty falls back to UTC.
	 *
	 * @return string ISO 8601 UTC ('YYYY-MM-DDTHH:MM:SSZ') or ''.
	 */
	public function iso8601( string $datetime, string $tz_name ): string {

		if ( empty( $datetime ) || strpos( $datetime, '0000-00-00' ) === 0 ) {
			return '';
		}

		try {
			$tz = new DateTimeZone( '' !== $tz_name ? $tz_name : 'UTC' );
			$dt = new DateTimeImmutable( $datetime, $tz );

			return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Render a Sugar_Calendar\Event into the canonical event shape.
	 *
	 * The caller supplies the resolved calendar map (post_id => WP_Term[]); this
	 * method performs no database lookups. When the map has no entry for the
	 * event's object_id, `calendars` is an empty array.
	 *
	 * @since 3.12.0
	 *
	 * @param Event $event        The event to format.
	 * @param array $calendar_map Calendar lookup keyed by post_id.
	 *
	 * @return array
	 */
	public function event( Event $event, array $calendar_map = [] ): array {

		$object_id = (int) $event->object_id;
		$terms     = $calendar_map[ $object_id ] ?? [];

		$calendars = [];

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$calendars[] = $this->calendar( $term );
			}
		}

		$recurrence_end    = ! empty( $event->recurrence_end ) && strpos( $event->recurrence_end, '0000-00-00' ) !== 0
			? $this->iso8601( $event->recurrence_end, $event->recurrence_end_tz )
			: '';
		$recurrence_end_tz = '' !== $recurrence_end ? $event->recurrence_end_tz : null;

		return [
			'id'                  => (int) $event->id,
			'object_id'           => $object_id,
			'title'               => $event->title,
			'content'             => $event->content,
			'status'              => $event->status,
			'url'                 => $object_id > 0 ? (string) get_permalink( $object_id ) : '',
			'start'               => $this->iso8601( $event->start, $event->start_tz ),
			'end'                 => $this->iso8601( $event->end, $event->end_tz ),
			'start_tz'            => $event->start_tz,
			'end_tz'              => $event->end_tz,
			'all_day'             => (bool) $event->all_day,
			'recurrence'          => '' !== $event->recurrence ? $event->recurrence : null,
			'recurrence_interval' => (int) $event->recurrence_interval > 0 ? (int) $event->recurrence_interval : null,
			'recurrence_count'    => (int) $event->recurrence_count > 0 ? (int) $event->recurrence_count : null,
			'recurrence_end'      => '' !== $recurrence_end ? $recurrence_end : null,
			'recurrence_end_tz'   => $recurrence_end_tz,
			'date_created'        => $this->iso8601( $event->date_created, '' ),
			'date_modified'       => $this->iso8601( $event->date_modified, '' ),
			'calendars'           => $calendars,
		];
	}

	/**
	 * Format a list of events against a pre-resolved calendar map.
	 *
	 * @since 3.12.0
	 *
	 * @param Event[] $events       Event objects.
	 * @param array   $calendar_map Calendar lookup keyed by post_id.
	 *
	 * @return array
	 */
	public function events( array $events, array $calendar_map ): array {

		$out = [];

		foreach ( $events as $event ) {
			$out[] = $this->event( $event, $calendar_map );
		}

		return $out;
	}
}
