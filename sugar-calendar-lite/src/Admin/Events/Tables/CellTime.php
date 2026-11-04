<?php

namespace Sugar_Calendar\Admin\Events\Tables;

use DateTimeInterface;
use DateTimeZone;

/**
 * Event times in the frame the admin hour grid is built in.
 *
 * @since 3.17.0
 */
class CellTime {

	/**
	 * Resolved cell zones, keyed by the view zone, the floating state and PHP's default zone.
	 *
	 * @since 3.17.0
	 *
	 * @var DateTimeZone[]
	 */
	private static $zones = [];

	/**
	 * Get an event's start or end as a wall-clock timestamp in the cells' time zone.
	 *
	 * Hour cells are wall-clock timestamps in that zone, and is_item_for_cell()
	 * places an event by converting it there; the raw stored time skips it everywhere.
	 *
	 * @since 3.17.0
	 *
	 * @param object $item     Event object.
	 * @param string $key      Either `start` or `end`.
	 * @param string $timezone Time zone the cells are built in, from Base::get_timezone().
	 *
	 * @return int
	 */
	public static function timestamp( $item, $key, $timezone ) {

		$dto = $item->{$key . '_dto'} ?? null;

		if ( ! $dto instanceof DateTimeInterface ) {
			return (int) strtotime( $item->{$key} );
		}

		return $dto->getTimestamp() + self::get_zone( (string) $timezone )->getOffset( $dto );
	}

	/**
	 * Get the zone the cells are built in.
	 *
	 * Resolved once per key: validating a zone walks the full zone list, and the
	 * grid asks for every event in every cell.
	 *
	 * @since 3.17.0
	 *
	 * @param string $timezone View time zone.
	 *
	 * @return DateTimeZone
	 */
	private static function get_zone( $timezone ) {

		$default = date_default_timezone_get();
		$key     = $timezone . '|' . (int) sugar_calendar_is_timezone_floating() . '|' . $default;

		if ( ! isset( self::$zones[ $key ] ) ) {
			$zone = sugar_calendar_get_timezone_object( $timezone );

			// A floating view builds its cells with new DateTime(), so in PHP's default zone.
			self::$zones[ $key ] = ( $zone instanceof DateTimeZone ) ? $zone : new DateTimeZone( $default );
		}

		return self::$zones[ $key ];
	}
}
