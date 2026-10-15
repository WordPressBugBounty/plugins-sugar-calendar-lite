<?php

namespace Sugar_Calendar\UsageTracking;

use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\OnlineMeeting;
use Sugar_Calendar\Options;

/**
 * Sugar Calendar install-state keys for the usage-tracking snapshot.
 *
 * Every value is an option read or one cheap indexed count; add-ons and Pro
 * contribute their own keys via the `sugar_calendar_usage_tracking_get_data`
 * filter instead of here.
 *
 * @since 3.15.0
 */
class SnapshotData {

	/**
	 * Recurrence meta that makes a pattern "advanced".
	 *
	 * Weekly weekdays live in `recurrence_byday`, so that key is not one of them.
	 *
	 * @since 3.15.0
	 */
	const ADVANCED_RECURRENCE_META_KEYS = [ 'recurrence_bymonthday', 'recurrence_bymonth', 'recurrence_bypos' ];

	/**
	 * Get the Sugar Calendar specific snapshot keys.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function get() {

		$events = sugar_calendar_get_event_counts();

		return [
			'sugar_calendar_version'                      => SC_PLUGIN_VERSION,
			'sugar_calendar_activated'                    => get_option( 'sugar_calendar_activated_time', 0 ),
			'sugar_calendar_source'                       => sanitize_title( get_option( 'sugar_calendar_source', '' ) ),
			'sugar_calendar_settings_general_editor'      => sanitize_title( Options::get( 'editor_type', 'classic' ) ),
			'sugar_calendar_settings_general_custom_fields' => (bool) Options::get( 'custom_fields', false ),
			'sugar_calendar_settings_general_start_of_week' => (int) Options::get( 'start_of_week' ),
			'sugar_calendar_settings_general_date_format' => sanitize_text_field( (string) Options::get( 'date_format' ) ),
			'sugar_calendar_settings_general_time_format' => sanitize_text_field( (string) Options::get( 'time_format' ) ),
			'sugar_calendar_settings_general_day_color_style' => sanitize_key( Options::get( 'day_color_style', 'none' ) ),
			'sugar_calendar_settings_general_timezone_type' => sanitize_key( Options::get( 'timezone_type', 'off' ) ),
			'sugar_calendar_settings_general_timezone_convert' => (bool) Options::get( 'timezone_convert', false ),
			'sugar_calendar_settings_general_number_of_events' => (int) Options::get( 'number_of_events', 30 ),
			'sugar_calendar_settings_general_default_calendar_set' => ! empty( Options::get( 'default_calendar' ) ),
			'sugar_calendar_settings_misc_hide_announcements' => (bool) Options::get( 'hide_announcements', false ),
			'sugar_calendar_total_events'                 => ! empty( $events['total'] ) ? intval( $events['total'] ) : 0,
			'sugar_calendar_total_calendars'              => intval( wp_count_terms( sugar_calendar_get_calendar_taxonomy_id() ) ),
			'sugar_calendar_total_tags'                   => intval( wp_count_terms( TagsHelpers::get_tags_taxonomy_id() ) ),
			'sugar_calendar_total_recurring_events'       => Counts::events_where( "recurrence != ''" ),
			'sugar_calendar_recurring_advanced_used'      => $this->has_advanced_recurrence(),
			'sugar_calendar_integrations_google_maps_connected' => trim( (string) Options::get( 'maps_google_api_key', '' ) ) !== '',
			'sugar_calendar_integrations_zoom_status'     => $this->get_integration_status( 'zoom' ),
			'sugar_calendar_events_with_online_meeting_zoom' => Counts::events_with_meta( 'meeting_provider', 'zoom' ),
			'sugar_calendar_integrations_google_status'   => $this->get_integration_status( 'google' ),
			'sugar_calendar_events_with_online_meeting_google' => Counts::events_with_meta( 'meeting_provider', 'google' ),
			'sugar_calendar_settings_integrations_google_calendar' => $this->get_google_calendar_choice(),
			'sugar_calendar_settings_integrations_google_sync_direction' => sanitize_key( Options::get( 'google_sync_direction', 'both' ) ),
			'sugar_calendar_events_with_online_meeting_custom' => Counts::events_with_meta( 'online_provider', OnlineMeeting::PROVIDER_CUSTOM ),
			'sugar_calendar_tec_migration_completed'      => $this->is_tec_migration_completed(),
		];
	}

	/**
	 * Whether any event uses a month-day, month or position recurrence rule (Pro Advanced Recurring meta).
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function has_advanced_recurrence() {

		global $wpdb;

		$keys = "'" . implode( "','", array_map( 'esc_sql', self::ADVANCED_RECURRENCE_META_KEYS ) ) . "'";

		$found = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT 1 FROM {$wpdb->prefix}sc_eventmeta WHERE meta_key IN ( {$keys} ) AND meta_value NOT IN ( '', '0' ) LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return ! empty( $found );
	}

	/**
	 * What the Google "Sync Events On" dropdown holds: a calendar, "Don't Sync Events", or nothing yet.
	 *
	 * @since 3.16.0
	 *
	 * @return string One of set|off|none.
	 */
	private function get_google_calendar_choice() {

		if ( trim( (string) Options::get( 'google_calendar_id', '' ) ) !== '' ) {
			return 'set';
		}

		return Options::get( 'google_calendar_sync_enabled', true ) ? 'none' : 'off';
	}

	/**
	 * A relay integration's connection status as stored site-wide, or 'not_connected' when no row exists.
	 *
	 * @since 3.15.0
	 * @since 3.16.0 Takes the provider; was get_zoom_status().
	 *
	 * @param string $provider Provider slug on the connection row.
	 *
	 * @return string One of not_connected|active|auth_error.
	 */
	private function get_integration_status( $provider ) {

		$row = OAuthConnectionModel::find_by_provider( $provider );

		if ( empty( $row['status'] ) ) {
			return 'not_connected';
		}

		return sanitize_key( $row['status'] );
	}

	/**
	 * Whether The Events Calendar migration finished (option holds a date, not 'in_progress').
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function is_tec_migration_completed() {

		$value = get_option( TheEventCalendar::SC_TEC_MIGRATION_OPTION_KEY, '' );

		return ! empty( $value ) && $value !== 'in_progress';
	}
}
