<?php

namespace Sugar_Calendar\Admin\Events\Metaboxes;

use Sugar_Calendar\Helpers;

/**
 * Parses the event editor's date/time fields out of the current request.
 *
 * Extracted verbatim from Event::save_post() so the same rules (12/24h clock,
 * minimum-duration end sanitation, all-day end-of-day, timezone fallbacks) can
 * be reused outside the metabox-save hook — specifically by the
 * Create-Meeting AJAX endpoint, which runs in a context where the Event
 * metabox (and its save_post filter) is not registered.
 *
 * @since 3.12.0
 */
class EventDateTimeRequest {

	/**
	 * Build [ start, end, start_tz, end_tz, all_day ] from $_POST.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	public static function from_request(): array {

		$all_day  = self::prepare_all_day();
		$start    = self::prepare_date_time( 'start' );
		$end      = self::prepare_date_time( 'end' );
		$start_tz = self::prepare_timezone( 'start' );
		$end_tz   = self::prepare_timezone( 'end' );

		$start    = Helpers::sanitize_start( $start, $end, $all_day );
		$end      = self::sanitize_end( $end, $start, $all_day );
		$all_day  = Helpers::sanitize_all_day( $all_day, $start, $end );
		$start_tz = Helpers::sanitize_timezone( $start_tz, $end_tz, $all_day );
		$end_tz   = Helpers::sanitize_timezone( $end_tz, $start_tz, $all_day );

		return [
			'start'    => $start,
			'start_tz' => $start_tz,
			'end'      => $end,
			'end_tz'   => $end_tz,
			'all_day'  => $all_day,
		];
	}

	/**
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	private static function prepare_all_day() {

		return ! empty( $_POST['all_day'] ) && (bool) $_POST['all_day']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * @since 3.12.0
	 *
	 * @param string $prefix Datetime prefix ('start' | 'end').
	 *
	 * @return string MySQL datetime.
	 */
	private static function prepare_date_time( $prefix = 'start' ) {

		if ( empty( $prefix ) || ! is_string( $prefix ) ) {
			$prefix = 'start';
		}

		$prefix = sanitize_key( $prefix ) . '_';

		$now = sugar_calendar_get_request_time();

		$nt = gmdate(
			'Y-m-d H:i:s',
			gmmktime( 0, 0, 0, gmdate( 'n', $now ), gmdate( 'j', $now ), gmdate( 'Y', $now ) )
		);

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Missing
		$date = ! empty( $_POST[ $prefix . 'date' ] )
			? strtotime( sanitize_text_field( $_POST[ $prefix . 'date' ] ) )
			: strtotime( $nt );

		$hour = ! empty( $_POST[ $prefix . 'time_hour' ] )
			? sanitize_text_field( $_POST[ $prefix . 'time_hour' ] )
			: 0;

		$minutes = ! empty( $_POST[ $prefix . 'time_minute' ] )
			? sanitize_text_field( $_POST[ $prefix . 'time_minute' ] )
			: 0;

		$seconds = ! empty( $_POST[ $prefix . 'time_second' ] )
			? sanitize_text_field( $_POST[ $prefix . 'time_second' ] )
			: 0;

		if ( '12' === sugar_calendar_get_clock_type() ) {
			$am_pm = ! empty( $_POST[ $prefix . 'time_am_pm' ] )
				? sanitize_text_field( $_POST[ $prefix . 'time_am_pm' ] )
				: 'am';

			$hour = self::adjust_hour_for_meridiem( $hour, $am_pm );
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Missing

		$timestamp = gmmktime(
			intval( $hour ),
			intval( $minutes ),
			intval( $seconds ),
			gmdate( 'n', $date ),
			gmdate( 'j', $date ),
			gmdate( 'Y', $date )
		);

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * @since 3.12.0
	 *
	 * @param string $prefix Timezone prefix.
	 *
	 * @return string
	 */
	private static function prepare_timezone( $prefix = 'start' ) {

		if ( empty( $prefix ) || ! is_string( $prefix ) ) {
			$prefix = 'start';
		}

		$prefix = sanitize_key( $prefix ) . '_';
		$field  = "{$prefix}tz";

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		return ! empty( $_POST[ $field ] ) ? sanitize_text_field( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	}

	/**
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	private static function has_end() {

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		return ! (
			empty( $_POST['end_date'] )
			&& empty( $_POST['end_time_hour'] )
			&& empty( $_POST['end_time_minute'] )
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * @since 3.12.0
	 *
	 * @param int    $hour     Hour.
	 * @param string $meridiem Meridiem.
	 *
	 * @return int
	 */
	private static function adjust_hour_for_meridiem( $hour = 0, $meridiem = 'am' ) {

		$new_hour = $hour;

		if ( $meridiem === 'pm' && ( $new_hour < 12 ) ) {
			$new_hour += 12;
		} elseif ( $meridiem === 'am' && ( $new_hour >= 12 ) ) {
			$new_hour -= 12;
		}

		return (int) $new_hour;
	}

	/**
	 * @since 3.12.0
	 *
	 * @param string $end     End MySQL datetime.
	 * @param string $start   Start MySQL datetime.
	 * @param bool   $all_day All-day flag.
	 *
	 * @return string
	 */
	private static function sanitize_end( $end = '', $start = '', $all_day = false ) {

		if ( empty( $start ) || empty( $end ) || ! is_string( $start ) || ! is_string( $end ) ) {
			return $end;
		}

		$start_int = strtotime( $start );
		$end_int   = strtotime( $end );

		$has_end = self::has_end();

		if ( ( $has_end === true ) && ( $all_day === false ) ) {

			$minimum = sugar_calendar_get_minimum_event_duration();

			$end_compare = ! empty( $minimum )
				? strtotime( '+' . $minimum, $start_int )
				: $end_int;

			if ( $end_compare > $start_int ) {
				return $end;
			} elseif ( ! empty( $minimum ) ) {
				$end_int = $end_compare;
			} else {
				$has_end = false;
			}
		}

		if ( $has_end === false ) {
			$end_int = $start_int;
		}

		if ( $all_day === true ) {
			$end_int = gmmktime( 23, 59, 59, gmdate( 'n', $end_int ), gmdate( 'j', $end_int ), gmdate( 'Y', $end_int ) );
		}

		return gmdate( 'Y-m-d H:i:s', $end_int );
	}
}
