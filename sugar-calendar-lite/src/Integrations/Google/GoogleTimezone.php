<?php

namespace Sugar_Calendar\Integrations\Google;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Timezone and wall-time helpers shared by the Google event builder and mapper.
 *
 * @since 3.16.0
 */
class GoogleTimezone {

	/**
	 * Event zone, falling back to the site zone, then UTC; manual offsets are mapped because neither PHP nor Google takes "UTC+5".
	 *
	 * @since 3.16.0
	 *
	 * @param string $tz Event zone, may be empty (floating).
	 *
	 * @return string
	 */
	public function zone( string $tz ): string {

		if ( $tz === '' ) {
			$tz = (string) sugar_calendar_get_timezone();
		}

		if ( $tz === '' ) {
			return 'UTC';
		}

		return sugar_calendar_is_manual_timezone_offset( $tz ) ? $this->offset_zone( $tz ) : $tz;
	}

	/**
	 * A manual offset as a zone both PHP and Google accept: Etc/GMT for whole hours, +HH:MM otherwise.
	 *
	 * @since 3.16.0
	 *
	 * @param string $tz Manual offset such as UTC+5, UTC-3.5 or UTC+5.75.
	 *
	 * @return string
	 */
	private function offset_zone( string $tz ): string {

		$hours = (float) substr( $tz, 3 );

		// Etc/GMT signs are inverted: UTC+5 is Etc/GMT-5.
		if ( floor( $hours ) === $hours ) {
			return sprintf( 'Etc/GMT%+d', -1 * (int) $hours );
		}

		$minutes = (int) round( abs( $hours ) * 60 );

		return sprintf( '%s%02d:%02d', $hours < 0 ? '-' : '+', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	/**
	 * Google sends an offset in dateTime; Sugar Calendar stores wall time plus a zone name.
	 *
	 * @since 3.16.0
	 *
	 * @param string $rfc3339 Google dateTime.
	 * @param string $tz      IANA zone to express it in.
	 *
	 * @return string Y-m-d H:i:s, or '' when unparsable.
	 */
	public function wall_time( string $rfc3339, string $tz ): string {

		if ( $rfc3339 === '' ) {
			return '';
		}

		try {
			$dt = new DateTime( $rfc3339 );

			$dt->setTimezone( new DateTimeZone( $tz ) );

			return $dt->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Google's timeZone when Sugar Calendar accepts it, else the site zone.
	 *
	 * @since 3.16.0
	 *
	 * @param string $tz       Zone from the Google item.
	 * @param string $fallback Zone to use when Google's is missing or unknown; site zone when empty.
	 *
	 * @return string
	 */
	public function incoming_zone( string $tz, string $fallback = '' ): string {

		if ( $tz !== '' && sugar_calendar_validate_timezone( $tz ) !== '' ) {
			return $tz;
		}

		return $fallback !== '' ? $fallback : $this->zone( '' );
	}
}
