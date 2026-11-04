<?php

namespace Sugar_Calendar\UsageTracking;

use DateTime;

/**
 * Feature "last used" keys a Lite install can report, for the usage-tracking snapshot.
 *
 * @since 3.17.0
 */
class FeatureUseData {

	/**
	 * Get the feature use keys.
	 *
	 * @since 3.17.0
	 *
	 * @return array
	 */
	public function get() {

		/**
		 * Short-circuit the Zoom last-used time of the usage-tracking snapshot.
		 *
		 * @since 3.17.0
		 *
		 * @param int|null $timestamp GMT Unix start of the latest Zoom event that has begun, null to compute it.
		 */
		$timestamp = apply_filters( 'sugar_calendar_usage_tracking_zoom_last_used_at', null ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return [
			'sugar_calendar_zoom_last_used_at' => $timestamp !== null ? (int) $timestamp : $this->zoom_last_used_at(),
		];
	}

	/**
	 * GMT Unix start of the latest published Zoom event that has begun, 0 when none.
	 *
	 * Compared in the site timezone; the winning event is converted with its own.
	 *
	 * @since 3.17.0
	 *
	 * @return int
	 */
	private function zoom_last_used_at() {

		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT e.start, e.start_tz, e.all_day FROM {$wpdb->prefix}sc_events e
				INNER JOIN {$wpdb->prefix}sc_eventmeta m ON m.sc_event_id = e.id
				WHERE m.meta_key = 'meeting_provider' AND m.meta_value = 'zoom'
				AND e.status = 'publish' AND e.start > '0000-00-00 00:00:00' AND e.start <= %s
				ORDER BY e.start DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				( new DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d H:i:s' )
			)
		);

		return $row ? EventActivityData::start_timestamp( $row ) : 0;
	}
}
