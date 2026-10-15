<?php

namespace Sugar_Calendar\Integrations\Google;

use DateTime;
use DateTimeInterface;
use DateTimeZone;

/**
 * Translates repeat rules between Sugar Calendar fields and Google RRULE lines, both ways.
 *
 * @since 3.16.0
 */
class GoogleRrule {

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
	 * @param GoogleTimezone $timezone Timezone helpers.
	 */
	public function __construct( GoogleTimezone $timezone ) {

		$this->timezone = $timezone;
	}

	/**
	 * The BYDAY / BYSETPOS / BYMONTHDAY / BYMONTH parts of the rule, each prefixed with ';'.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string
	 */
	public function rule_by_parts( $event ): string {

		$values = $this->bypart_values( isset( $event->advanced_recurring ) && is_array( $event->advanced_recurring ) ? $event->advanced_recurring : [] );
		$parts  = [
			'recurrence_byday'      => 'BYDAY',
			'recurrence_bypos'      => 'BYSETPOS',
			'recurrence_bymonthday' => 'BYMONTHDAY',
			'recurrence_bymonth'    => 'BYMONTH',
		];
		$rule   = '';

		foreach ( $parts as $key => $part ) {
			if ( $values[ $key ] !== '' && $values[ $key ] !== '0' ) {
				$rule .= ';' . $part . '=' . $values[ $key ];
			}
		}

		return $rule;
	}

	/**
	 * The BY* fields as strings, with a single positioned day folded into Google's ordinal form.
	 *
	 * @since 3.16.0
	 *
	 * @param array $adv Advanced recurring fields of the event.
	 *
	 * @return string[] Keyed by field name.
	 */
	private function bypart_values( array $adv ): array {

		$values = [];

		foreach ( [ 'recurrence_byday', 'recurrence_bypos', 'recurrence_bymonthday', 'recurrence_bymonth' ] as $key ) {
			$value          = $adv[ $key ] ?? '';
			$values[ $key ] = is_array( $value ) ? implode( ',', $value ) : (string) $value;
		}

		// Google's editor reads one day with a position only in the ordinal form (-1FR), not BYDAY=FR;BYSETPOS=-1.
		if ( (int) $values['recurrence_bypos'] !== 0 && preg_match( '/^(SU|MO|TU|WE|TH|FR|SA)$/', $values['recurrence_byday'] ) ) {
			$values['recurrence_byday'] = (int) $values['recurrence_bypos'] . $values['recurrence_byday'];
			$values['recurrence_bypos'] = '';
		}

		return $values;
	}

	/**
	 * The rule's end date as Google wants it: UTC for timed events, a plain date for all-day.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string
	 */
	public function recurrence_until( $event ): string {

		// An all-day end is a date, not an instant; converting 23:59:59 to UTC would shift it a day west of UTC.
		if ( ! empty( $event->all_day ) ) {
			return str_replace( '-', '', substr( (string) $event->recurrence_end, 0, 10 ) );
		}

		// Same zone chain as when(): the core helper skips the conversion while Time Zones are off, which would stamp local time as Z.
		$tz  = $event->recurrence_end_tz ? (string) $event->recurrence_end_tz : (string) $event->start_tz;
		$dto = new DateTime( (string) $event->recurrence_end, new DateTimeZone( $this->timezone->zone( $tz ) ) );

		return $dto->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	}

	/**
	 * Map parsed RRULE parts onto Sugar Calendar's fields, or null when Pro cannot hold them.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $rules RRULE parts keyed by name, every allowed part present.
	 * @param string $tz    Zone the series starts in.
	 *
	 * @return array|null
	 *
	 * @phpstan-ignore sugarCalendar.methodLength
	 */
	public function rule_fields( array $rules, string $tz ) {

		$freq = [
			'DAILY'   => 'daily',
			'WEEKLY'  => 'weekly',
			'MONTHLY' => 'monthly',
			'YEARLY'  => 'yearly',
		];

		if ( ! isset( $freq[ $rules['FREQ'] ] ) ) {
			return null;
		}

		$fields = [
			'recurrence'          => $freq[ $rules['FREQ'] ],
			'recurrence_interval' => max( 1, absint( $rules['INTERVAL'] ) ),
			'recurrence_count'    => absint( $rules['COUNT'] ),
		];

		$until = $this->rule_until( $rules['UNTIL'], $tz );
		$byday = $this->rule_byday( $rules['BYDAY'], $rules['BYSETPOS'] );
		$mdays = $this->rule_int_list( $rules['BYMONTHDAY'], 1, 31 );
		$month = $this->rule_int_list( $rules['BYMONTH'], 1, 12 );

		if ( $until === null || $byday === null || $mdays === null || $month === null ) {
			return null;
		}

		return array_merge(
			$fields,
			$byday,
			[
				'recurrence_end'        => $until,
				'recurrence_bymonthday' => $mdays,
				'recurrence_bymonth'    => $month,
			]
		);
	}

	/**
	 * RRULE UNTIL as the local end-of-day Sugar Calendar stores, or null when unparsable.
	 *
	 * @since 3.16.0
	 *
	 * @param string $until RRULE UNTIL value: Ymd, Ymd\THis\Z, floating Ymd\THis, or '' for none.
	 * @param string $tz    Zone the series starts in.
	 *
	 * @return string|null
	 */
	private function rule_until( string $until, string $tz ) {

		if ( $until === '' ) {
			return '0000-00-00 00:00:00';
		}

		// A plain date is already the local last day; only a UTC instant needs converting.
		$date = DateTime::createFromFormat( '!Ymd', $until, new DateTimeZone( $tz ) );

		if ( ! $date instanceof DateTimeInterface ) {
			$zone = substr( $until, -1 ) === 'Z' ? 'UTC' : $tz;
			$date = DateTime::createFromFormat( 'Ymd\THis', rtrim( $until, 'Z' ), new DateTimeZone( $zone ) );
		}

		if ( ! $date instanceof DateTimeInterface ) {
			return null;
		}

		return $date->setTimezone( new DateTimeZone( $tz ) )->format( 'Y-m-d 23:59:59' );
	}

	/**
	 * BYDAY (plain or ordinal form) and BYSETPOS as recurrence_byday + recurrence_bypos, or null.
	 *
	 * @since 3.16.0
	 *
	 * @param string $byday  RRULE BYDAY, e.g. MO,WE or -1FR.
	 * @param string $setpos RRULE BYSETPOS, one value at most.
	 *
	 * @return array|null
	 */
	private function rule_byday( string $byday, string $setpos ) {

		$parsed = $this->parse_byday_tokens( $byday );

		if ( $parsed === null ) {
			return null;
		}

		[ $days, $pos ] = $parsed;

		$pos = array_unique( array_merge( $pos, $setpos === '' ? [] : [ (int) $setpos ] ) );

		// Pro holds one position: 1 to 5, or -1 for last.
		if ( count( $pos ) > 1 || strpos( $setpos, ',' ) !== false ) {
			return null;
		}

		$bypos = (int) reset( $pos );

		if ( $bypos !== 0 && ! in_array( $bypos, [ -1, 1, 2, 3, 4, 5 ], true ) ) {
			return null;
		}

		return [
			'recurrence_byday' => implode( ',', array_intersect( [ 'SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA' ], $days ) ),
			'recurrence_bypos' => $bypos,
		];
	}

	/**
	 * Split BYDAY into day codes and ordinal positions, or null when Pro cannot hold the shape.
	 *
	 * @since 3.16.0
	 *
	 * @param string $byday RRULE BYDAY, e.g. MO,WE or -1FR.
	 *
	 * @return array|null [ string[] $days, int[] $pos ].
	 */
	private function parse_byday_tokens( string $byday ): ?array {

		$tokens = array_filter( explode( ',', $byday ) );
		$days   = [];
		$pos    = [];

		foreach ( $tokens as $token ) {
			if ( ! preg_match( '/^([+-]?\d+)?(SU|MO|TU|WE|TH|FR|SA)$/', $token, $match ) ) {
				return null;
			}

			$days[] = $match[2];

			if ( $match[1] !== '' ) {
				$pos[] = (int) $match[1];
			}
		}

		// Ordinal and plain days mixed (MO,2TU) is not a shape Pro holds.
		if ( $pos !== [] && count( $pos ) !== count( $tokens ) ) {
			return null;
		}

		return [ $days, $pos ];
	}

	/**
	 * A comma list of whole numbers within bounds, sorted as Pro stores it, or null.
	 *
	 * @since 3.16.0
	 *
	 * @param string $values RRULE list value.
	 * @param int    $min    Lowest allowed value.
	 * @param int    $max    Highest allowed value.
	 *
	 * @return string|null
	 */
	private function rule_int_list( string $values, int $min, int $max ) {

		if ( $values === '' ) {
			return '';
		}

		$values = explode( ',', $values );

		foreach ( $values as $value ) {
			if ( ! preg_match( '/^\d+$/', $value ) || (int) $value < $min || (int) $value > $max ) {
				return null;
			}
		}

		$values = array_unique( array_map( 'intval', $values ) );

		sort( $values );

		return implode( ',', $values );
	}
}
