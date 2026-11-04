<?php

namespace Sugar_Calendar\UsageTracking;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use WP_Post;

/**
 * Event activity keys for the usage-tracking snapshot.
 *
 * @since 3.17.0
 */
class EventActivityData {

	/**
	 * Option holding the first event publish time (GMT Unix), written once.
	 *
	 * @since 3.17.0
	 */
	const FIRST_PUBLISH_OPTION = 'sugar_calendar_first_event_published_at';

	/**
	 * Days ahead that count as upcoming.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	const UPCOMING_DAYS = 90;

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_action( 'transition_post_status', [ $this, 'maybe_record_first_publish' ], 10, 3 );
	}

	/**
	 * Get the event activity keys.
	 *
	 * @since 3.17.0
	 *
	 * @return array
	 */
	public function get() {

		$now = new DateTimeImmutable( 'now', wp_timezone() );

		return array_merge(
			[
				'sugar_calendar_published_events_count'   => Counts::events_where( 'status = %s', [ 'publish' ] ),
				'sugar_calendar_last_event_modified'      => $this->get_last_modified(),
				'sugar_calendar_first_event_published_at' => $this->get_first_published_at(),
			],
			$this->get_occurrence_keys( $now )
		);
	}

	/**
	 * Get the last, next and upcoming-count keys, from Pro when it answers.
	 *
	 * @since 3.17.0
	 *
	 * @param DateTimeImmutable $now Now in the site timezone.
	 *
	 * @return array
	 */
	private function get_occurrence_keys( $now ) {

		/**
		 * Short-circuit the occurrence keys of the usage-tracking snapshot.
		 *
		 * @since 3.17.0
		 *
		 * @param array|null        $keys Keys for the last occurrence, next occurrence and upcoming count, null to compute them.
		 * @param DateTimeImmutable $now  Now in the site timezone.
		 */
		$keys = apply_filters( 'sugar_calendar_usage_tracking_occurrence_keys', null, $now ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		// A callback answers only these three keys, as ints, so it cannot add or retype other snapshot keys.
		if ( is_array( $keys ) ) {
			$defaults = [
				'sugar_calendar_last_occurrence_date'  => 0,
				'sugar_calendar_next_occurrence_date'  => 0,
				'sugar_calendar_upcoming_events_count' => 0,
			];

			return array_map( 'intval', array_intersect_key( array_merge( $defaults, $keys ), $defaults ) );
		}

		return [
			'sugar_calendar_last_occurrence_date'  => $this->get_last_started( $now ),
			'sugar_calendar_next_occurrence_date'  => $this->get_next_start( $now ),
			'sugar_calendar_upcoming_events_count' => $this->count_upcoming( $now ),
		];
	}

	/**
	 * Stamp the first publish time when an event goes live and the option is still missing.
	 *
	 * @since 3.17.0
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function maybe_record_first_publish( $new_status, $old_status, $post ) {

		if ( $new_status !== 'publish' || $old_status === 'publish' || ! in_array( $post->post_type, [ 'sc_event', 'sc_recurring_event' ], true ) ) {
			return;
		}

		if ( get_option( self::FIRST_PUBLISH_OPTION, false ) !== false ) {
			return;
		}

		// The post being published may carry an imported, backdated date: only events published before it count.
		$earliest = $this->get_earliest_published( $post->ID );

		add_option( self::FIRST_PUBLISH_OPTION, $earliest > 0 ? $earliest : time() );
	}

	/**
	 * Get the first publish time, stored once the site has a published event and never changed afterwards.
	 *
	 * @since 3.17.0
	 *
	 * @return int GMT Unix timestamp, 0 while nothing has been published.
	 */
	private function get_first_published_at() {

		$stored = get_option( self::FIRST_PUBLISH_OPTION, false );

		if ( $stored !== false ) {
			return (int) $stored;
		}

		$earliest = $this->get_earliest_published( 0 );

		if ( $earliest > 0 ) {
			add_option( self::FIRST_PUBLISH_OPTION, $earliest );
		}

		return $earliest;
	}

	/**
	 * Get the newest modified time over published events.
	 *
	 * @since 3.17.0
	 *
	 * @return int GMT Unix timestamp, 0 when there are none.
	 */
	private function get_last_modified() {

		global $wpdb;

		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT MAX( post_modified_gmt ) FROM {$wpdb->posts}
			WHERE post_type IN ( 'sc_event', 'sc_recurring_event' ) AND post_status = 'publish'
			AND post_modified_gmt > '0000-00-00 00:00:00'
			LIMIT 1"
		);

		return $value ? (int) strtotime( $value . ' UTC' ) : 0;
	}

	/**
	 * Get the earliest publish time over events that are already published.
	 *
	 * @since 3.17.0
	 *
	 * @param int $exclude_post_id Post left out of the lookup, 0 for none.
	 *
	 * @return int GMT Unix timestamp, 0 when there are none.
	 */
	private function get_earliest_published( $exclude_post_id ) {

		global $wpdb;

		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT MIN( post_date_gmt ) FROM {$wpdb->posts}
				WHERE post_type IN ( 'sc_event', 'sc_recurring_event' ) AND post_status = 'publish'
				AND post_date_gmt > '0000-00-00 00:00:00' AND ID != %d
				LIMIT 1",
				$exclude_post_id
			)
		);

		return $value ? (int) strtotime( $value . ' UTC' ) : 0;
	}

	/**
	 * Get the start of the latest event that has begun, counting each event once.
	 *
	 * @since 3.17.0
	 *
	 * @param DateTimeImmutable $now Now in the site timezone.
	 *
	 * @return int GMT Unix timestamp, 0 when there is none.
	 */
	private function get_last_started( $now ) {

		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT start, start_tz, all_day FROM {$wpdb->prefix}sc_events
				WHERE object_subtype = 'sc_event' AND status = 'publish'
				AND start > '0000-00-00 00:00:00' AND start <= %s
				ORDER BY start DESC LIMIT 1",
				$now->format( 'Y-m-d H:i:s' )
			)
		);

		return $row ? self::start_timestamp( $row ) : 0;
	}

	/**
	 * Get the start of the next event still to come, counting each event once.
	 *
	 * @since 3.17.0
	 *
	 * @param DateTimeImmutable $now Now in the site timezone.
	 *
	 * @return int GMT Unix timestamp, 0 when there is none.
	 */
	private function get_next_start( $now ) {

		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT start, start_tz, all_day FROM {$wpdb->prefix}sc_events
				WHERE object_subtype = 'sc_event' AND status = 'publish'
				AND start > %s
				ORDER BY start ASC LIMIT 1",
				$now->format( 'Y-m-d H:i:s' )
			)
		);

		return $row ? self::start_timestamp( $row ) : 0;
	}

	/**
	 * Count events starting after now and up to the upcoming window from now.
	 *
	 * @since 3.17.0
	 *
	 * @param DateTimeImmutable $now Now in the site timezone.
	 *
	 * @return int
	 */
	private function count_upcoming( $now ) {

		return Counts::events_where(
			"object_subtype = 'sc_event' AND status = 'publish' AND start > %s AND start <= %s",
			[
				$now->format( 'Y-m-d H:i:s' ),
				$now->modify( '+' . self::UPCOMING_DAYS . ' days' )->format( 'Y-m-d H:i:s' ),
			]
		);
	}

	/**
	 * Convert an event row's wall-clock start to a GMT timestamp.
	 *
	 * All-day, floating and unreadable timezones use the site timezone.
	 *
	 * @since 3.17.0
	 *
	 * @param object $row Row with start, start_tz and all_day.
	 *
	 * @return int
	 */
	public static function start_timestamp( $row ) {

		$timezone = wp_timezone();

		if ( empty( $row->all_day ) && sugar_calendar_get_timezone_type() !== 'off' ) {
			$zone = self::normalize_timezone( (string) $row->start_tz );

			if ( $zone !== '' ) {
				try {
					$timezone = new DateTimeZone( $zone );
				} catch ( Exception $e ) {
					$timezone = wp_timezone();
				}
			}
		}

		return ( new DateTimeImmutable( $row->start, $timezone ) )->getTimestamp();
	}

	/**
	 * Map a stored timezone to an identifier PHP understands, manual UTC offsets included.
	 *
	 * Not sugar_calendar_get_timezone_object(): it bails to floating when the default zone is empty.
	 *
	 * @since 3.17.0
	 *
	 * @param string $timezone Stored start_tz.
	 *
	 * @return string Empty when floating or invalid.
	 */
	private static function normalize_timezone( $timezone ) {

		$zone = sugar_calendar_validate_timezone(
			$timezone,
			[
				'allow_utc'    => true,
				'allow_manual' => true,
				'allow_empty'  => true,
			]
		);

		return sugar_calendar_is_manual_timezone_offset( $zone )
			? sugar_calendar_get_manual_timezone_offset_id( $zone )
			: $zone;
	}
}
