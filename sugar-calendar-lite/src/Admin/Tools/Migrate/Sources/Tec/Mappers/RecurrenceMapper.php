<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers;

/**
 * Turns a TEC repeat rule into Sugar Calendar's repeat fields.
 *
 * @since 3.18.0
 */
class RecurrenceMapper {

	/**
	 * TEC's weekday numbers and their RRULE names.
	 *
	 * @since 3.18.0
	 *
	 * @var string[]
	 */
	const WEEKDAYS = [
		1 => 'MO',
		2 => 'TU',
		3 => 'WE',
		4 => 'TH',
		5 => 'FR',
		6 => 'SA',
		7 => 'SU',
	];

	/**
	 * TEC's ordinal words and their positions.
	 *
	 * @since 3.18.0
	 *
	 * @var string[]
	 */
	const ORDINALS = [
		-1 => 'last',
		1  => 'first',
		2  => 'second',
		3  => 'third',
		4  => 'fourth',
		5  => 'fifth',
		6  => 'sixth',
		7  => 'seventh',
	];

	/**
	 * The repeat fields of the first TEC rule, false when it is not a custom daily, weekly, monthly or yearly rule.
	 *
	 * @since 3.18.0
	 *
	 * @param array $recurrence TEC `_EventRecurrence` data.
	 *
	 * @return array|false
	 */
	public static function map( $recurrence ) {

		$rule = empty( $recurrence['rules'] ) ? false : reset( $recurrence['rules'] );
		$type = empty( $rule['custom']['type'] ) ? '' : strtolower( $rule['custom']['type'] );

		if ( ! in_array( $type, [ 'daily', 'weekly', 'monthly', 'yearly' ], true ) ) {
			return false;
		}

		$fields = [
			'recurrence'          => $type,
			'recurrence_count'    => ! empty( $rule['end-count'] ) ? $rule['end-count'] : 0,
			'recurrence_interval' => ! empty( $rule['custom']['interval'] ) ? $rule['custom']['interval'] : 0,
		];

		if ( ! empty( $rule['end'] ) ) {
			$fields['recurrence_end'] = $rule['end'];
		}

		return $fields + array_filter( self::get_by_fields( $type, $rule['custom'] ) );
	}

	/**
	 * The single dates a TEC repeat excludes, as `Y-m-d`.
	 *
	 * Only single excluded dates: TEC's exclusion patterns ("every Wednesday") are dropped.
	 *
	 * @since 3.18.0
	 *
	 * @param array $recurrence TEC `_EventRecurrence` data.
	 *
	 * @return string[]
	 */
	public static function get_excluded_dates( $recurrence ) {

		$dates      = [];
		$exclusions = $recurrence['exclusions'] ?? [];

		foreach ( $exclusions as $exclusion ) {
			if ( strtolower( $exclusion['custom']['type'] ?? '' ) === 'date' && ! empty( $exclusion['custom']['date']['date'] ) ) {
				$dates[] = $exclusion['custom']['date']['date'];
			}
		}

		return $dates;
	}

	/**
	 * The by-day, by-month-day, by-position and by-month fields of a rule.
	 *
	 * @since 3.18.0
	 *
	 * @param string $type   The repeat type.
	 * @param array  $custom The rule's `custom` part.
	 *
	 * @return array
	 */
	private static function get_by_fields( $type, $custom ) {

		if ( $type === 'weekly' ) {
			return [ 'recurrence_byday' => empty( $custom['week']['day'] ) ? false : self::to_weekdays( $custom['week']['day'] ) ];
		}

		if ( $type === 'monthly' ) {
			return self::get_by_position( $custom['month'] ?? [], true );
		}

		if ( $type === 'yearly' ) {
			return self::get_by_position( $custom['year'] ?? [], false ) + [ 'recurrence_bymonth' => empty( $custom['year']['month'] ) ? false : $custom['year']['month'] ];
		}

		return [];
	}

	/**
	 * A monthly or yearly rule's "2nd Tuesday", or its day of the month.
	 *
	 * @since 3.18.0
	 *
	 * @param array $part            The rule's `month` or `year` part.
	 * @param bool  $allow_month_day Whether a number without a weekday is a day of the month.
	 *
	 * @return array
	 */
	private static function get_by_position( $part, $allow_month_day ) {

		if ( empty( $part['number'] ) ) {
			return [];
		}

		if ( ! empty( $part['day'] ) ) {
			return [
				'recurrence_byday' => self::to_weekdays( [ $part['day'] ] ),
				'recurrence_bypos' => self::to_position( $part['number'] ),
			];
		}

		$month_day = $allow_month_day ? absint( $part['number'] ) : 0;

		return empty( $month_day ) ? [] : [ 'recurrence_bymonthday' => [ $month_day ] ];
	}

	/**
	 * TEC weekday numbers as RRULE weekday names.
	 *
	 * @since 3.18.0
	 *
	 * @param array $days TEC weekday numbers.
	 *
	 * @return string[]|false
	 */
	private static function to_weekdays( $days ) {

		$weekdays = [];

		foreach ( $days as $day ) {
			if ( ! empty( self::WEEKDAYS[ absint( $day ) ] ) ) {
				$weekdays[] = self::WEEKDAYS[ absint( $day ) ];
			}
		}

		return empty( $weekdays ) ? false : $weekdays;
	}

	/**
	 * The position an ordinal word stands for.
	 *
	 * @since 3.18.0
	 *
	 * @param string $ordinal An ordinal word, such as `second` or `last`.
	 *
	 * @return int|false
	 */
	private static function to_position( $ordinal ) {

		return array_search( strtolower( $ordinal ), self::ORDINALS, true );
	}
}
