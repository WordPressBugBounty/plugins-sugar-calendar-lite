<?php

namespace Sugar_Calendar\Integrations\Google;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use WP_Post;

/**
 * Builds the Google Calendar event body for a Sugar Calendar event. No HTTP, no options.
 *
 * @since 3.16.0
 */
class GoogleEventBuilder {

	/**
	 * Link storage.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleLinkRepository
	 */
	private $links;

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
	 * Description formatter.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleDescription
	 */
	private $description;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleLinkRepository $links       Link storage.
	 * @param GoogleRrule          $rrule       Repeat-rule codec.
	 * @param GoogleTimezone       $timezone    Timezone helpers.
	 * @param GoogleDescription    $description Description formatter.
	 */
	public function __construct( GoogleLinkRepository $links, GoogleRrule $rrule, GoogleTimezone $timezone, GoogleDescription $description ) {

		$this->links       = $links;
		$this->rrule       = $rrule;
		$this->timezone    = $timezone;
		$this->description = $description;
	}

	/**
	 * Skipped dates per event and Google series id, for this request.
	 *
	 * @since 3.16.0
	 *
	 * @var array
	 */
	private $skipped = [];

	/**
	 * Forget the skipped dates cached during this request, for when a series changed after they were read.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function forget_skipped() {

		$this->skipped = [];
	}

	/**
	 * Whether the event has what Google requires: a title and a real start date.
	 *
	 * The relay rejects a body missing either, so the manager skips the push instead of
	 * retrying a failing call on every save.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return bool
	 */
	public function is_pushable( $event ): bool {

		return trim( (string) $event->title ) !== '' && ! $event->is_empty_date( (string) $event->start );
	}

	/**
	 * Build the Google event resource body.
	 *
	 * Local wall time plus an explicit IANA zone, as Zoom does; Google accepts dateTime
	 * without an offset when timeZone is given.
	 *
	 * @since 3.16.0
	 *
	 * @param object  $event     SCE Event object.
	 * @param WP_Post $post      The event's post.
	 * @param string  $series_id The copy's Google id in the target calendar, '' on create.
	 *
	 * @return array
	 */
	public function build( $event, WP_Post $post, string $series_id = '' ): array {

		$body = [
			'summary'            => (string) $event->title,
			'description'        => $this->description->from_post( $post ),
			'status'             => 'confirmed',
			'extendedProperties' => [
				'private' => [
					'sugar_calendar_event_id' => (string) $event->id,
					'sugar_calendar_site'     => home_url(),
				],
			],
			'conferenceData'     => $this->conference_data( $event ),
		];

		$body  = array_merge( $body, $this->when( $event ) );
		$rules = $this->recurrence_rules( $event, $series_id );

		// An absent key keeps a rule Google owns; the manager clears one we pushed.
		if ( $rules !== [] ) {
			$body['recurrence'] = $rules;
		}

		return $body;
	}

	/**
	 * Fingerprint of the fields build() sends, so an unrelated save fires no relay call.
	 *
	 * @since 3.16.0
	 *
	 * @param object  $event     SCE Event object.
	 * @param WP_Post $post      The event's post.
	 * @param string  $series_id The copy's Google id in the target calendar, '' on create.
	 *
	 * @return string
	 */
	public function fingerprint( $event, WP_Post $post, string $series_id = '' ): string {

		$parts = [
			(string) $event->title,
			$this->description->from_post( $post ),
			(string) $event->start,
			(string) $event->end,
			(string) $event->start_tz,
			(string) $event->end_tz,
			(string) $event->all_day,
		];

		$meet_link = $this->meet_link( $event );

		if ( $meet_link !== '' ) {
			$parts[] = $meet_link;
		}

		$rules = $this->recurrence_rules( $event, $series_id );

		if ( $rules !== [] ) {
			$parts[] = implode( "\n", $rules );
		}

		return md5( implode( '|', $parts ) );
	}

	/**
	 * The event's repeat rule as Google's recurrence lines, or [] for a one-off.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event     SCE Event object.
	 * @param string $series_id The copy's Google id in the target calendar, '' on create.
	 *
	 * @return array
	 */
	public function recurrence_rules( $event, string $series_id = '' ): array {

		$freq = strtoupper( (string) $event->recurrence );

		if ( ! in_array( $freq, [ 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY' ], true ) ) {
			return [];
		}

		$rule = 'RRULE:FREQ=' . $freq . ';INTERVAL=' . max( 1, (int) $event->recurrence_interval );

		if ( (int) $event->recurrence_count > 0 ) {
			$rule .= ';COUNT=' . (int) $event->recurrence_count;
		} elseif ( ! $event->is_empty_date( (string) $event->recurrence_end ) ) {
			$rule .= ';UNTIL=' . $this->rrule->recurrence_until( $event );
		}

		return array_merge( [ $rule . $this->rrule->rule_by_parts( $event ) ], $this->exdate_lines( $event, $series_id ) );
	}

	/**
	 * One EXDATE line per skipped date, rendered like the start when() sends.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event     SCE Event object.
	 * @param string $series_id The copy's Google id in the target calendar, '' on create.
	 *
	 * @return array
	 */
	private function exdate_lines( $event, string $series_id ): array {

		$start = DateTime::createFromFormat( 'Y-m-d H:i:s', (string) $event->start );

		if ( ! $start instanceof DateTimeInterface ) {
			return [];
		}

		$lines = [];

		foreach ( $this->skipped_dates( $event, $series_id ) as $date ) {
			$ymd = str_replace( '-', '', $date );

			$lines[] = ! empty( $event->all_day )
				? 'EXDATE;VALUE=DATE:' . $ymd
				: 'EXDATE;TZID=' . $this->timezone->zone( (string) $event->start_tz ) . ':' . $ymd . 'T' . $start->format( 'His' );
		}

		return $lines;
	}

	/**
	 * The dates a series skips, as Y-m-d in its own zone, minus the dates Google moved.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event     SCE Event object.
	 * @param string $series_id The copy's Google id in the target calendar, '' on create.
	 *
	 * @return string[]
	 */
	private function skipped_dates( $event, string $series_id ): array {

		$key = $event->id . '|' . $series_id;

		if ( isset( $this->skipped[ $key ] ) ) {
			return $this->skipped[ $key ];
		}

		/**
		 * Filter the dates a recurring series skips, as Y-m-d in the series' own zone.
		 *
		 * @since 3.16.0
		 *
		 * @param string[] $dates Skipped dates. Default empty.
		 * @param object   $event Series event.
		 */
		$dates = (array) apply_filters( 'sugar_calendar_google_series_skipped_dates', [], $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		$dates = preg_grep( '/^\d{4}-\d{2}-\d{2}$/', array_map( 'strval', $dates ) );

		// Google already holds moved dates; an EXDATE would cancel them.
		$dates = array_unique( array_diff( $dates, $this->moved_dates( $event, $series_id ) ) );

		sort( $dates );

		$this->skipped[ $key ] = $dates;

		return $dates;
	}

	/**
	 * The series dates Google holds as moved instances, as Y-m-d in the series' zone.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event     SCE Event object.
	 * @param string $series_id The copy's Google id in the target calendar, '' on create.
	 *
	 * @return string[]
	 */
	private function moved_dates( $event, string $series_id ): array {

		$moved = [];

		foreach ( $series_id !== '' ? $this->links->moved_instance_dates( $series_id ) : [] as $stamp ) {
			$moved[] = $this->instance_stamp_date( $stamp, $event );
		}

		return $moved;
	}

	/**
	 * The series-local Y-m-d of an instance id suffix: Ymd (all-day) or a UTC Ymd\THis\Z stamp.
	 *
	 * @since 3.16.0
	 *
	 * @param string $stamp  Instance id suffix.
	 * @param object $series The series event.
	 *
	 * @return string
	 */
	private function instance_stamp_date( string $stamp, $series ): string {

		if ( preg_match( '/^\d{8}$/', $stamp ) ) {
			return substr( $stamp, 0, 4 ) . '-' . substr( $stamp, 4, 2 ) . '-' . substr( $stamp, 6, 2 );
		}

		// A UTC instant: converted, since the local date can differ (Tokyo 08:00 is the previous day in UTC).
		$date = DateTime::createFromFormat( 'Ymd\THis', rtrim( $stamp, 'Z' ), new DateTimeZone( 'UTC' ) );

		if ( ! $date instanceof DateTimeInterface ) {
			return '';
		}

		return $date->setTimezone( new DateTimeZone( $this->timezone->zone( (string) $series->start_tz ) ) )->format( 'Y-m-d' );
	}

	/**
	 * The event's Meet join link, or '' when its meeting is not a Google Meet.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string
	 */
	private function meet_link( $event ): string {

		if ( empty( $event->id ) || (string) get_event_meta( $event->id, 'meeting_provider', true ) !== 'google' ) {
			return '';
		}

		return (string) get_event_meta( $event->id, 'join_url', true );
	}

	/**
	 * Google conference data for the event's Meet room; null strips any Meet from the Google copy.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array|null
	 */
	private function conference_data( $event ) {

		$link = $this->meet_link( $event );

		if ( $link === '' ) {
			return null;
		}

		$code = (string) get_event_meta( $event->id, 'meeting_id', true );

		return [
			'conferenceSolution' => [ 'key' => [ 'type' => 'hangoutsMeet' ] ],
			'conferenceId'       => $code,
			'entryPoints'        => [
				[
					'entryPointType' => 'video',
					'uri'            => $link,
					'label'          => preg_replace( '#^https?://#', '', $link ),
				],
			],
		];
	}

	/**
	 * The start / end block: date for all-day (end exclusive), dateTime + timeZone otherwise.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array
	 */
	private function when( $event ): array {

		$start = DateTime::createFromFormat( 'Y-m-d H:i:s', (string) $event->start );
		$end   = DateTime::createFromFormat( 'Y-m-d H:i:s', (string) $event->end );

		if ( ! $start instanceof DateTimeInterface ) {
			return [];
		}

		if ( ! empty( $event->all_day ) ) {
			return $this->all_day_when( $start, $end );
		}

		return $this->timed_when( $event, $start, $end );
	}

	/**
	 * The all-day start / end block; Google's end date is exclusive.
	 *
	 * @since 3.16.0
	 *
	 * @param DateTime       $start Event start.
	 * @param DateTime|false $end   Event end, false when unparsable.
	 *
	 * @return array
	 */
	private function all_day_when( DateTime $start, $end ): array {

		$end_date = ( $end instanceof DateTimeInterface && $end >= $start ) ? clone $end : clone $start;

		// Google's end.date is exclusive: a one-day event ends the next day.
		$end_date->modify( '+1 day' );

		return [
			'start' => [ 'date' => $start->format( 'Y-m-d' ) ],
			'end'   => [ 'date' => $end_date->format( 'Y-m-d' ) ],
		];
	}

	/**
	 * The timed start / end block, with zones; a missing or backwards end becomes start + 60 minutes.
	 *
	 * @since 3.16.0
	 *
	 * @param object         $event SCE Event object.
	 * @param DateTime       $start Event start.
	 * @param DateTime|false $end   Event end, false when unparsable.
	 *
	 * @return array
	 */
	private function timed_when( $event, DateTime $start, $end ): array {

		if ( ! $end instanceof DateTimeInterface || $end <= $start ) {
			$end = ( clone $start )->modify( '+60 minutes' );
		}

		$start_tz = $this->timezone->zone( (string) $event->start_tz );
		$end_tz   = $event->end_tz ? $this->timezone->zone( (string) $event->end_tz ) : $start_tz;

		return [
			'start' => [
				'dateTime' => $start->format( 'Y-m-d\TH:i:s' ),
				'timeZone' => $start_tz,
			],
			'end'   => [
				'dateTime' => $end->format( 'Y-m-d\TH:i:s' ),
				'timeZone' => $end_tz,
			],
		];
	}
}
