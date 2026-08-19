<?php

namespace Sugar_Calendar\Integrations\Zoom;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use WP_Error;

/**
 * Maps an SCE event to a Zoom meeting model.
 *
 * Sole owner of the SCE-event → Zoom-payload translation and the "which Zoom
 * meeting variant does this event need" decision. Extracted from ZoomIntegration
 * so that class stays glue (capability, connection, credits, result shaping).
 *
 * Three meeting "kinds":
 *  - '2' scheduled       — non-recurring.
 *  - '8' fixed recurring — recurring AND the pattern maps to Zoom's recurrence
 *                          schema (daily/weekly-single-day/monthly-single-day,
 *                          bounded <= 50, interval within Zoom's per-type cap).
 *  - '3' no-fixed-time   — recurring but NOT mappable (multi-weekday, nth-weekday,
 *                          day-of-month list, yearly, unbounded, > 50, over-cap).
 *                          One meeting id/join URL for the whole series, no
 *                          recurrence object and no start_time.
 *
 * @since 3.13.0
 */
class ZoomMeetingBuilder {

	/**
	 * SCE recurrence slug → Zoom recurrence.type (1=daily, 2=weekly, 3=monthly).
	 *
	 * @since 3.13.0
	 *
	 * @var array<string,int>
	 */
	private const RECURRENCE_TYPE_MAP = [
		'daily'   => 1,
		'weekly'  => 2,
		'monthly' => 3,
	];

	/**
	 * Zoom recurrence.type → maximum repeat_interval Zoom allows.
	 *
	 * @since 3.13.0
	 *
	 * @var array<int,int>
	 */
	private const INTERVAL_CAP = [
		1 => 90,
		2 => 12,
		3 => 3,
	];

	/**
	 * Zoom's maximum number of occurrences for a fixed-time recurring meeting.
	 *
	 * @since 3.13.0
	 */
	private const MAX_OCCURRENCES = 50;

	/**
	 * iCal weekday codes indexed by PHP date('w') (0=Sun..6=Sat).
	 *
	 * Used to compare a single advanced-recurrence weekday selection against the
	 * weekday Zoom would actually derive from the meeting start.
	 *
	 * @since 3.13.0
	 *
	 * @var array<int,string>
	 */
	private const WEEKDAY_CODES = [ 'SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA' ];

	/**
	 * Non-recurring scheduled meeting.
	 *
	 * @since 3.13.0
	 */
	private const KIND_SCHEDULED = '2';

	/**
	 * Recurring meeting whose pattern maps to Zoom's fixed-time recurrence schema.
	 *
	 * @since 3.13.0
	 */
	private const KIND_RECURRING_FIXED = '8';

	/**
	 * Recurring meeting whose pattern does not map to Zoom's fixed-time schema.
	 *
	 * @since 3.13.0
	 */
	private const KIND_RECURRING_NO_FIXED_TIME = '3';

	/**
	 * Build the create/update payload for the event's resolved kind.
	 *
	 * Sends local time + explicit timezone (SCE events can be floating-tz).
	 * Duration falls back to 60 minutes when the event has no positive span.
	 * Kind 3 carries neither start_time nor recurrence — Zoom stops modeling the
	 * schedule, which is what lifts every kind-8 limitation.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array|WP_Error Payload array, or WP_Error when a start-time-bearing
	 *                        kind has no resolvable start.
	 */
	public function build( $event ) {

		$kind     = $this->resolve_kind( $event );
		$settings = $this->build_settings( $event );

		// Kind 3: topic + settings only. No start_time, no duration, no recurrence.
		if ( $kind === self::KIND_RECURRING_NO_FIXED_TIME ) {
			return [
				'topic'    => $event->title,
				'type'     => 3,
				'settings' => $settings,
			];
		}

		// Kinds 2 and 8 need a real start time.
		$start_dto = $this->resolve_start( $event );

		if ( ! $start_dto instanceof DateTimeInterface ) {
			return new WP_Error( 'zoom_invalid_start', esc_html__( 'The event has no valid start date and time for the meeting.', 'sugar-calendar-lite' ) );
		}

		$data = [
			'topic'      => $event->title,
			'type'       => $kind === self::KIND_RECURRING_FIXED ? 8 : 2,
			'start_time' => $start_dto->format( 'Y-m-d\TH:i:s' ),
			'timezone'   => $event->start_tz ? $event->start_tz : sugar_calendar_get_timezone(),
			'duration'   => $this->duration( $event ),
			'settings'   => $settings,
		];

		if ( $kind === self::KIND_RECURRING_FIXED ) {
			// recurrence is a TOP-LEVEL meeting-body field (sibling of type/settings).
			$data['recurrence'] = $this->build_recurrence_object( $event, $start_dto );
		}

		return $data;
	}

	/**
	 * The current sync signature: which kind the event needs now + a hash of the
	 * fields THAT kind actually sends (so a change Zoom would not see never fires
	 * a relay call).
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array{kind:string,fingerprint:string}
	 */
	public function signature( $event ): array {

		$kind  = $this->resolve_kind( $event );
		$parts = [ (string) $event->title ];

		// Kinds 2 and 8 send the fixed time block; kind 3 does not.
		if ( $kind === self::KIND_SCHEDULED || $kind === self::KIND_RECURRING_FIXED ) {
			$parts[] = (string) $event->start;
			$parts[] = (string) $event->end;
			$parts[] = (string) $event->start_tz;
			$parts[] = (string) $event->end_tz;
			$parts[] = (string) $event->all_day;
		}

		// Only kind 8 sends the recurrence object.
		if ( $kind === self::KIND_RECURRING_FIXED ) {
			$parts[] = (string) $event->recurrence;
			$parts[] = (string) $event->recurrence_interval;
			$parts[] = (string) $event->recurrence_count;
			$parts[] = (string) $event->recurrence_end;
			$parts[] = (string) $event->recurrence_end_tz;
		}

		return [
			'kind'        => $kind,
			'fingerprint' => md5( implode( '|', $parts ) ),
		];
	}

	/**
	 * Resolve the meeting kind for an event.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string '2' | '3' | '8'.
	 */
	public function resolve_kind( $event ): string {

		if ( empty( $event->recurrence ) ) {
			return self::KIND_SCHEDULED;
		}

		return $this->is_mappable( $event ) ? self::KIND_RECURRING_FIXED : self::KIND_RECURRING_NO_FIXED_TIME;
	}

	/**
	 * Whether a recurring event maps to Zoom's fixed-time recurrence schema.
	 *
	 * The sole validator: every condition that used to return a WP_Error now
	 * returns false here (→ kind 3 fallback). Public so it is unit-testable in
	 * isolation.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object (recurrence != '').
	 *
	 * @return bool
	 */
	public function is_mappable( $event ): bool {

		$type = self::RECURRENCE_TYPE_MAP[ $event->recurrence ] ?? 0;

		if ( $type === 0 ) {
			return false; // unknown / yearly-by-slug.
		}

		// Advanced patterns Zoom's type-8 schema cannot express (read defensively:
		// absent on Lite / Advanced Recurring off).
		$adv = ( isset( $event->advanced_recurring ) && is_array( $event->advanced_recurring ) )
			? $event->advanced_recurring
			: [];

		// Yearly (a specific month) — not one of daily/weekly/monthly.
		if ( $this->csv_count( $adv['recurrence_bymonth'] ?? '' ) > 0 ) {
			return false;
		}

		// Nth-weekday ("3rd Tuesday").
		if ( ! empty( $adv['recurrence_bypos'] ?? '' ) ) {
			return false;
		}

		// Zoom's type-8 weekly/monthly schema derives the recurring weekday and
		// day-of-month from the meeting start, so it can only express a single
		// selection that matches the start. More than one selection — or a single
		// one that disagrees with the start day — is not representable and would
		// otherwise run the series on the wrong day, so it falls back to kind 3.
		if ( ! $this->advanced_selection_matches_start( $event, $adv ) ) {
			return false;
		}

		// Interval within Zoom's per-type cap.
		$interval = max( 1, (int) $event->recurrence_interval );

		if ( $interval > self::INTERVAL_CAP[ $type ] ) {
			return false;
		}

		return $this->is_bounded_within_cap( $event, $type, $interval );
	}

	/**
	 * Whether the recurrence is bounded (count or end date) AND within Zoom's
	 * 50-occurrence cap.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event    SCE Event object.
	 * @param int    $type     Zoom recurrence type.
	 * @param int    $interval Repeat interval (already capped).
	 *
	 * @return bool
	 */
	private function is_bounded_within_cap( $event, int $type, int $interval ): bool {

		$count = (int) $event->recurrence_count;

		if ( $count > 0 ) {
			return $count <= self::MAX_OCCURRENCES;
		}

		if ( ! $this->is_real_end_date( (string) $event->recurrence_end ) ) {
			return false; // unbounded.
		}

		$start = $this->resolve_start( $event );

		if ( ! $start instanceof DateTimeInterface ) {
			return false;
		}

		$tz  = $this->resolve_recurrence_end_tz( $event );
		$end = sugar_calendar_get_datetime_object( $event->recurrence_end, $tz, 'UTC' );

		if ( ! $end instanceof DateTimeInterface ) {
			return false;
		}

		$start_utc = ( new DateTimeImmutable( '@' . $start->getTimestamp() ) )->setTimezone( new DateTimeZone( 'UTC' ) );

		return $this->estimate_recurrence_occurrences( $start_utc, $end, $type, $interval ) <= self::MAX_OCCURRENCES;
	}

	/**
	 * Whether the advanced weekday / day-of-month selection is expressible under
	 * Zoom's type-8 schema, which derives both from the meeting start.
	 *
	 * A pattern is expressible only when it selects at most one weekday AND at
	 * most one day-of-month, and any single selection matches the start. Zoom
	 * ignores the selection and recurs on the start's weekday / day-of-month, so
	 * a mismatch would silently run every occurrence on the wrong day — those
	 * fall back to kind 3 instead. Absent selections (Lite / plain recurrence)
	 * are always expressible.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 * @param array  $adv   Advanced-recurrence values.
	 *
	 * @return bool
	 */
	private function advanced_selection_matches_start( $event, array $adv ): bool {

		$byday      = (string) ( $adv['recurrence_byday'] ?? '' );
		$bymonthday = (string) ( $adv['recurrence_bymonthday'] ?? '' );

		// More than one weekday / day-of-month is never expressible.
		if ( $this->csv_count( $byday ) > 1 || $this->csv_count( $bymonthday ) > 1 ) {
			return false;
		}

		// No single selection to reconcile — plain recurrence maps by the start.
		if ( $byday === '' && $bymonthday === '' ) {
			return true;
		}

		$start = $this->resolve_start( $event );

		// Cannot confirm the selection matches the start → treat as unmappable.
		if ( ! $start instanceof DateTimeInterface ) {
			return false;
		}

		if (
			$byday !== ''
			&&
			strtoupper( trim( $byday ) ) !== self::WEEKDAY_CODES[ (int) $start->format( 'w' ) ]
		) {
			return false;
		}

		if ( $bymonthday !== '' && (int) $bymonthday !== (int) $start->format( 'j' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Build the Zoom `recurrence` object for a mappable (kind 8) event.
	 *
	 * Assumes is_mappable() already passed — no validation, no error return. The
	 * weekday / day-of-month are derived from the start (single-day mapping).
	 *
	 * @since 3.13.0
	 *
	 * @param object            $event     SCE Event object.
	 * @param DateTimeInterface $start_dto Resolved event start.
	 *
	 * @return array
	 */
	private function build_recurrence_object( $event, DateTimeInterface $start_dto ): array {

		$type       = self::RECURRENCE_TYPE_MAP[ $event->recurrence ];
		$recurrence = [
			'type'            => $type,
			'repeat_interval' => max( 1, (int) $event->recurrence_interval ),
		];

		if ( $type === 2 ) {
			// PHP date('w'): 0=Sun..6=Sat. Zoom weekly_days: 1=Sun..7=Sat.
			$recurrence['weekly_days'] = (string) ( (int) $start_dto->format( 'w' ) + 1 );
		} elseif ( $type === 3 ) {
			$recurrence['monthly_day'] = (int) $start_dto->format( 'j' );
		}

		return array_merge( $recurrence, $this->build_recurrence_end( $event ) );
	}

	/**
	 * Resolve the Zoom recurrence end condition (end_times or end_date_time).
	 *
	 * A positive count wins (end_times); otherwise the real end date
	 * (end_date_time, UTC Z form). is_mappable() has already guaranteed one of
	 * these holds and is within the cap, so there is no error path.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array
	 */
	private function build_recurrence_end( $event ): array {

		$count = (int) $event->recurrence_count;

		if ( $count > 0 ) {
			return [ 'end_times' => $count ];
		}

		$tz      = $this->resolve_recurrence_end_tz( $event );
		$end_dto = sugar_calendar_get_datetime_object( $event->recurrence_end, $tz, 'UTC' );

		return [ 'end_date_time' => $end_dto->format( 'Y-m-d\TH:i:s\Z' ) ];
	}

	/**
	 * Estimate how many occurrences a date-bounded recurrence yields (for the
	 * 50-occurrence cap on the end_date_time branch).
	 *
	 * Count = floor( elapsed / step ) + 1 (the start itself is occurrence #1).
	 * A guard, not an exact schedule — only needs to be right near the cap line.
	 *
	 * @since 3.13.0
	 *
	 * @param DateTimeInterface $start    Event start.
	 * @param DateTimeInterface $end      Recurrence end (inclusive bound).
	 * @param int               $type     Zoom recurrence type.
	 * @param int               $interval Repeat interval.
	 *
	 * @return int
	 */
	private function estimate_recurrence_occurrences( DateTimeInterface $start, DateTimeInterface $end, int $type, int $interval ): int {

		if ( $end <= $start ) {
			return 1;
		}

		$interval = max( 1, $interval );
		$diff     = $start->diff( $end );

		if ( $type === 1 ) {
			$steps = intdiv( (int) $diff->days, $interval );
		} elseif ( $type === 2 ) {
			$steps = intdiv( (int) $diff->days, $interval * 7 );
		} else {
			$steps = intdiv( ( $diff->y * 12 ) + $diff->m, $interval );
		}

		return $steps + 1;
	}

	/**
	 * Whether a raw recurrence-end value is a real end date.
	 *
	 * "" and the 0000-00-00 00:00:00 sentinel are NOT real dates (unbounded).
	 *
	 * @since 3.13.0
	 *
	 * @param string $end_raw Raw recurrence_end column value.
	 *
	 * @return bool
	 */
	private function is_real_end_date( string $end_raw ): bool {

		return $end_raw !== '' && $end_raw !== '0000-00-00 00:00:00' && strtotime( $end_raw ) !== false;
	}

	/**
	 * Count the comma-separated items in an advanced-recurrence value.
	 *
	 * Values arrive as comma-joined strings ("MO,WE") or '' from Pro's meta
	 * sanitizers. Tolerates an array too (defensive).
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $value Advanced-recurrence value.
	 *
	 * @return int
	 */
	private function csv_count( $value ): int {

		if ( is_array( $value ) ) {
			return count( $value );
		}

		$value = (string) $value;

		return $value === '' ? 0 : count( explode( ',', $value ) );
	}

	/**
	 * Build the Zoom meeting settings array (filterable).
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array
	 */
	private function build_settings( $event ): array {

		$settings = [ 'waiting_room' => false ];

		/**
		 * Filter the settings array sent to the Zoom create-meeting relay call.
		 *
		 * @since 3.12.0
		 *
		 * @param array  $settings Zoom meeting settings.
		 * @param object $event    SCE Event object.
		 */
		return (array) apply_filters( 'sugar_calendar_zoom_meeting_settings', $settings, $event );
	}

	/**
	 * Resolve the timezone to interpret the event's recurrence_end in.
	 *
	 * Prefers recurrence_end_tz, then start_tz, then the site timezone. The site
	 * timezone can itself be null (floating), so this is deliberately untyped —
	 * sugar_calendar_get_datetime_object() accepts a null/empty timezone.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string|null
	 */
	private function resolve_recurrence_end_tz( $event ) {

		return $event->recurrence_end_tz ? $event->recurrence_end_tz : ( $event->start_tz ? $event->start_tz : sugar_calendar_get_timezone() );
	}

	/**
	 * Resolve the event's start DateTime (prefers the attached start_dto).
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return DateTimeInterface|null
	 */
	private function resolve_start( $event ): ?DateTimeInterface {

		if ( isset( $event->start_dto ) && $event->start_dto instanceof DateTimeInterface ) {
			return $event->start_dto;
		}

		$dto = sugar_calendar_get_datetime_object( $event->start, $event->start_tz );

		return $dto instanceof DateTimeInterface ? $dto : null;
	}

	/**
	 * Meeting duration in minutes (>= 1), 60-minute fallback for a non-positive span.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return int
	 */
	private function duration( $event ): int {

		$start_ts = strtotime( $event->start );
		$end_ts   = strtotime( $event->end );

		return ( $start_ts && $end_ts && $end_ts > $start_ts )
			? (int) ceil( ( $end_ts - $start_ts ) / 60 )
			: 60;
	}
}
