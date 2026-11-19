<?php

namespace Sugar_Calendar\UsageTracking;

use DateTime;
use Sugar_Calendar\Emails\Styling;
use Sugar_Calendar\Integrations\Google\GoogleLinkRepository;

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

		return array_merge(
			[
				'sugar_calendar_zoom_last_used_at'     => $timestamp !== null ? (int) $timestamp : $this->meeting_last_used_at( 'zoom' ),
				'sugar_calendar_featured_events_count' => $this->featured_events_count(),
			],
			$this->email_styling_keys(),
			$this->google_keys()
		);
	}

	/**
	 * The Google Calendar sync and Google Meet keys.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	private function google_keys() {

		/**
		 * Short-circuit the Google Meet last-used time of the usage-tracking snapshot.
		 *
		 * @since 3.18.0
		 *
		 * @param int|null $timestamp GMT Unix start of the latest Google Meet event that has begun, null to compute it.
		 */
		$meet = apply_filters( 'sugar_calendar_usage_tracking_google_meet_last_used_at', null ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return [
			'sugar_calendar_google_calendar_synced_events' => $this->google_synced_events(),
			'sugar_calendar_google_calendar_last_used_at'  => max( 0, (int) get_option( GoogleLinkRepository::LAST_STORED_OPTION, 0 ) ),
			'sugar_calendar_google_meet_last_used_at'      => $meet !== null ? (int) $meet : $this->meeting_last_used_at( 'google' ),
		];
	}

	/**
	 * Number of distinct existing events linked to Google Calendar.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	private function google_synced_events() {

		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT e.id) FROM {$wpdb->prefix}sc_events e
				INNER JOIN {$wpdb->prefix}sc_eventmeta m ON m.sc_event_id = e.id
				WHERE m.meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				GoogleLinkRepository::GOOGLE_ID_META_KEY
			)
		);
	}

	/**
	 * Number of distinct published events flagged as featured.
	 *
	 * The flag lives on the parent row, so a recurring series counts once. Mirrors the Events list filter.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	private function featured_events_count() {

		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT COUNT(DISTINCT e.object_id) FROM {$wpdb->prefix}sc_events e
			INNER JOIN {$wpdb->prefix}sc_eventmeta m ON m.sc_event_id = e.id
			WHERE m.meta_key = 'featured' AND m.meta_value != '' AND e.status = 'publish'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * The email template, header image and colour keys, read through the Styling accessor.
	 *
	 * The accessor keeps colour case as stored, so colours compare case-insensitively. Anything that is not the Modern template is reported as plain text, the legacy `default` included.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	private function email_styling_keys() {

		$styling  = Styling::get();
		$defaults = Styling::defaults();

		return [
			'sugar_calendar_settings_emails_template'     => $styling['template'] === Styling::TEMPLATE_MODERN ? Styling::TEMPLATE_MODERN : Styling::TEMPLATE_PLAIN_TEXT,
			'sugar_calendar_settings_emails_header_image' => (int) $styling['header_image_id'] > 0,
			'sugar_calendar_settings_emails_colors_customized' => array_map( 'strtolower', $styling['colors'] ) !== array_map( 'strtolower', $defaults['colors'] ),
		];
	}

	/**
	 * GMT Unix start of the latest published event of a meeting provider that has begun, 0 when none.
	 *
	 * Compared in the site timezone; the winning event is converted with its own.
	 *
	 * @since 3.17.0
	 * @since 3.18.0 Takes the meeting provider.
	 *
	 * @param string $provider Meeting provider slug.
	 *
	 * @return int
	 */
	private function meeting_last_used_at( $provider ) {

		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT e.start, e.start_tz, e.all_day FROM {$wpdb->prefix}sc_events e
				INNER JOIN {$wpdb->prefix}sc_eventmeta m ON m.sc_event_id = e.id
				WHERE m.meta_key = 'meeting_provider' AND m.meta_value = %s
				AND e.status = 'publish' AND e.start > '0000-00-00 00:00:00' AND e.start <= %s
				ORDER BY e.start DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$provider,
				( new DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d H:i:s' )
			)
		);

		return $row ? EventActivityData::start_timestamp( $row ) : 0;
	}
}
