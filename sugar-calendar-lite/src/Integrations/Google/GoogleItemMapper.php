<?php

namespace Sugar_Calendar\Integrations\Google;

use DateTime;
use DateTimeInterface;

/**
 * Maps a Google event item to Sugar Calendar event fields. No HTTP, no options.
 *
 * @since 3.16.0
 */
class GoogleItemMapper {

	/**
	 * The nine recurrence fields of an event that does not repeat.
	 *
	 * @since 3.16.0
	 *
	 * @var array
	 */
	const NO_RECURRENCE = [
		'recurrence'            => '',
		'recurrence_interval'   => 0,
		'recurrence_count'      => 0,
		'recurrence_end'        => '0000-00-00 00:00:00',
		'recurrence_end_tz'     => '',
		'recurrence_byday'      => '',
		'recurrence_bymonthday' => '',
		'recurrence_bypos'      => 0,
		'recurrence_bymonth'    => '',
	];

	/**
	 * Repeat-rule codec.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleRrule
	 */
	private $rrule;

	/**
	 * Timezone helpers.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleTimezone
	 */
	private $timezone;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleRrule    $rrule    Repeat-rule codec.
	 * @param GoogleTimezone $timezone Timezone helpers.
	 */
	public function __construct( GoogleRrule $rrule, GoogleTimezone $timezone ) {

		$this->rrule    = $rrule;
		$this->timezone = $timezone;
	}

	/**
	 * Map a Google event resource to the field set Sugar Calendar stores. Pure.
	 *
	 * @since 3.16.0
	 *
	 * @param array $item Google event resource.
	 *
	 * @return array
	 */
	public function to_event_data( array $item ): array {

		$start = $item['start'] ?? [];
		$end   = $item['end'] ?? [];

		return array_merge(
			$this->text_fields( $item ),
			$this->recurrence_fields( $item ),
			[ 'google_series' => ! empty( $item['recurrence'] ) ],
			! empty( $start['date'] ) ? $this->all_day_times( $start, $end ) : $this->timed_times( $start, $end )
		);
	}

	/**
	 * Map a confirmed Google item for writing, or null when the import skips it.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 * @param bool   $full_sync   Whether the item belongs to the first full sync.
	 * @param bool   $instance    Whether the item is one date of a series.
	 *
	 * @return array|null
	 */
	public function pulled_event_data( array $item, string $calendar_id, bool $full_sync, bool $instance ): ?array {

		$data = $this->to_event_data( $item );

		// The series' uuid would repeat it in the ICS feed.
		if ( $instance ) {
			$data['uuid'] = '';
		}

		if ( $data['start_date'] === '' || ( $full_sync && $this->is_beyond_window( $data['start_date'], $data['start_tz'], $calendar_id ) ) ) {
			return null;
		}

		/**
		 * Filter the mapped event data before it is written.
		 *
		 * @since 3.16.0
		 *
		 * @param array  $data        Mapped fields: title, content, start_date, end_date, start_tz, end_tz, all_day, uuid, meet_code,
		 *                            recurrence, recurrence_interval, recurrence_count, recurrence_end, recurrence_end_tz,
		 *                            recurrence_byday, recurrence_bymonthday, recurrence_bypos, recurrence_bymonth, google_series.
		 * @param array  $item        Google event resource.
		 * @param string $calendar_id Google calendar id.
		 */
		return (array) apply_filters( 'sugar_calendar_google_pull_event_data', $data, $item, $calendar_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Whether a start date lies beyond the first-sync window.
	 *
	 * @since 3.16.0
	 *
	 * @param string $start_date  Y-m-d H:i:s wall time.
	 * @param string $tz          Zone, '' for all-day.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return bool
	 */
	private function is_beyond_window( string $start_date, string $tz, string $calendar_id ): bool {

		/**
		 * Filter how far ahead the first sync imports, in days. 0 imports nothing, -1 means no limit.
		 *
		 * @since 3.16.0
		 *
		 * @param int    $days        Default 0.
		 * @param string $calendar_id Google calendar id.
		 */
		$days = (int) apply_filters( 'sugar_calendar_google_pull_window_days', 0, $calendar_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( $days < 0 ) {
			return false;
		}

		// Nothing at all, not even an event already under way.
		if ( $days === 0 ) {
			return true;
		}

		$start = sugar_calendar_get_datetime_object( $start_date, $tz !== '' ? $tz : sugar_calendar_get_timezone() );

		return $start && $start->getTimestamp() > time() + $days * DAY_IN_SECONDS;
	}

	/**
	 * Title, description, uuid and Meet code.
	 *
	 * @since 3.16.0
	 *
	 * @param array $item Google event resource.
	 *
	 * @return array
	 */
	private function text_fields( array $item ): array {

		$title = trim( (string) ( $item['summary'] ?? '' ) );

		if ( $title === '' ) {
			// wp_insert_post() refuses an empty title + content; same string the admin table shows.
			$title = esc_html__( '(No title)', 'sugar-calendar-lite' );
		}

		preg_match( '#^https://meet\.google\.com/([a-z]{3}-[a-z]{4}-[a-z]{3})$#', (string) ( $item['hangoutLink'] ?? '' ), $meet );

		return [
			'title'     => $title,
			'content'   => wp_kses_post( (string) ( $item['description'] ?? '' ) ),
			'uuid'      => $this->normalize_uuid( (string) ( $item['iCalUID'] ?? '' ) ),
			'meet_code' => $meet[1] ?? '',
		];
	}

	/**
	 * Start and end of an all-day item.
	 *
	 * @since 3.16.0
	 *
	 * @param array $start Google start object.
	 * @param array $end   Google end object.
	 *
	 * @return array
	 */
	private function all_day_times( $start, $end ): array {

		$end_date = DateTime::createFromFormat( 'Y-m-d', (string) ( $end['date'] ?? $start['date'] ) );

		// Google's all-day end is exclusive; the mirror of build().
		if ( $end_date instanceof DateTimeInterface ) {
			$end_date->modify( '-1 day' );
		}

		return [
			'start_date' => (string) $start['date'] . ' 00:00:00',
			'end_date'   => ( $end_date ? $end_date->format( 'Y-m-d' ) : (string) $start['date'] ) . ' 23:59:59',
			'start_tz'   => '',
			'end_tz'     => '',
			'all_day'    => true,
		];
	}

	/**
	 * Start, end and zones of a timed item.
	 *
	 * @since 3.16.0
	 *
	 * @param array $start Google start object.
	 * @param array $end   Google end object.
	 *
	 * @return array
	 */
	private function timed_times( $start, $end ): array {

		$tz = $this->timezone->incoming_zone( (string) ( $start['timeZone'] ?? '' ) );

		// Google's pair is kept whatever the Time Zones setting says, matching the ICS importer.
		$end_tz = $this->timezone->incoming_zone( (string) ( $end['timeZone'] ?? '' ), $tz );

		return [
			'start_date' => $this->timezone->wall_time( (string) ( $start['dateTime'] ?? '' ), $tz ),
			'end_date'   => $this->timezone->wall_time( (string) ( $end['dateTime'] ?? '' ), $end_tz ),
			'start_tz'   => $tz,
			'end_tz'     => $end_tz,
			'all_day'    => false,
		];
	}

	/**
	 * The nine recurrence fields from the item's RRULE; all empty on Lite or when Pro cannot hold the rule.
	 *
	 * @since 3.16.0
	 *
	 * @param array $item Google event resource.
	 *
	 * @return array
	 */
	private function recurrence_fields( array $item ): array {

		$empty = self::NO_RECURRENCE;
		$lines = array_map( 'strval', (array) ( $item['recurrence'] ?? [] ) );
		$rrule = array_values( preg_grep( '/^RRULE:/', $lines ) );
		$other = preg_grep( '/^(RDATE|EXRULE)[:;]/', $lines );

		if ( ! sugar_calendar()->is_pro() || count( $rrule ) !== 1 || ! empty( $other ) ) {
			return $empty;
		}

		parse_str( str_replace( ';', '&', substr( $rrule[0], 6 ) ), $rules );

		// Google adds WKST to every rule it writes; Pro has no week-start setting, so it is accepted and never read.
		$allowed = [ 'FREQ', 'INTERVAL', 'COUNT', 'UNTIL', 'BYDAY', 'BYSETPOS', 'BYMONTHDAY', 'BYMONTH', 'WKST' ];

		if ( array_diff( array_keys( $rules ), $allowed ) !== [] ) {
			return $empty;
		}

		$start  = $item['start'] ?? [];
		$tz     = ! empty( $start['date'] ) ? $this->timezone->zone( '' ) : $this->timezone->incoming_zone( (string) ( $start['timeZone'] ?? '' ) );
		$fields = $this->rrule->rule_fields( array_map( 'strval', array_merge( array_fill_keys( $allowed, '' ), $rules ) ), $tz );

		return $fields === null ? $empty : array_merge( $empty, $fields );
	}

	/**
	 * The date a Google instance replaces, Y-m-d in the series' zone, or '' when unparsable.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $item   Google instance resource.
	 * @param object $series The series event.
	 *
	 * @return string
	 */
	public function original_date( array $item, $series ): string {

		$original = $item['originalStartTime'] ?? [];

		if ( ! empty( $original['date'] ) ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $original['date'] ) ? (string) $original['date'] : '';
		}

		$wall = $this->timezone->wall_time( (string) ( $original['dateTime'] ?? '' ), $this->timezone->zone( (string) $series->start_tz ) );

		return $wall !== '' ? substr( $wall, 0, 10 ) : '';
	}

	/**
	 * The iCalUID as the iCalendar importer stores it, so the two match.
	 *
	 * @since 3.16.0
	 *
	 * @param string $ical_uid Google iCalUID.
	 *
	 * @return string
	 */
	public function normalize_uuid( string $ical_uid ): string {

		return substr( esc_html( $ical_uid ), 0, 100 );
	}
}
