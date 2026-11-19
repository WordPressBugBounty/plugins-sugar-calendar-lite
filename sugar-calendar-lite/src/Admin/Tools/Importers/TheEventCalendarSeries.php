<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecIdLookup;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecIdMap;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;

/**
 * TEC extra dates that become their own events, and Series that become tags.
 *
 * @since 3.18.0
 */
class TheEventCalendarSeries {

	/**
	 * Get a TEC event's rules that did not become its repeat.
	 *
	 * The first rule is an extra one too when it couldn't become the repeat, such as a lone extra date.
	 *
	 * @since 3.18.0
	 *
	 * @param array       $recurrence      TEC `_EventRecurrence` data.
	 * @param array|false $recurrence_data The repeat prepare_recurrence_data() made of the first rule, if any.
	 *
	 * @return array
	 */
	public static function get_extra_rules( $recurrence, $recurrence_data ) {

		return array_slice( $recurrence['rules'] ?? [], (int) is_array( $recurrence_data ) );
	}

	/**
	 * Get the start and end of a TEC extra date.
	 *
	 * @since 3.18.0
	 *
	 * @param array $custom TEC `Date` rule's `custom` data.
	 * @param array $data   The migrated event's data, for its times and length.
	 *
	 * @return array `start_date` and `end_date`.
	 */
	public static function get_extra_date_times( $custom, $data ) {

		$date     = $custom['date']['date'] ?? '';
		$duration = strtotime( $data['end_date'] ) - strtotime( $data['start_date'] );
		$start    = $date . substr( $data['start_date'], 10 );
		$own_time = ( $custom['same-time'] ?? 'yes' ) === 'no';

		if ( $own_time && ! empty( $custom['start-time'] ) ) {
			$start = gmdate( 'Y-m-d H:i:s', strtotime( $date . ' ' . $custom['start-time'] ) );
		}

		$end = gmdate( 'Y-m-d H:i:s', strtotime( $start ) + $duration );

		// Events Calendar Pro stores `end-day` as `same-day`, which casts to 0, or as the days the date ends after it starts.
		if ( $own_time && ! empty( $custom['end-time'] ) ) {
			$end_days = (int) ( $custom['end-day'] ?? 0 );
			$end      = gmdate( 'Y-m-d H:i:s', strtotime( "{$date} {$custom['end-time']} +{$end_days} days" ) );
		}

		return [
			'start_date' => $start,
			'end_date'   => $end,
		];
	}

	/**
	 * Tag this run's events with the name of the TEC Series they share with other events.
	 *
	 * Events Calendar Pro gives every recurring event a Series of its own, so a
	 * Series becomes a tag only when it groups two or more events.
	 *
	 * @since 3.18.0
	 */
	public static function relate_migrated_series_to_events() {

		global $wpdb;

		$series  = $wpdb->prefix . 'tec_series_relationships';
		$mapping = $wpdb->prefix . TecIdMap::EVENTS_TABLE;

		// Without Events Calendar Pro there is no Series table, which simply means no Series.
		$suppress = $wpdb->suppress_errors();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$grouping_series = array_map( 'absint', (array) $wpdb->get_col( "SELECT series_post_id FROM {$series} GROUP BY series_post_id HAVING COUNT(*) > 1" ) );
		$events          = empty( $grouping_series ) ? [] : $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$mapping}.sc_event_post_id, {$wpdb->posts}.post_title AS series_title FROM {$series}
				INNER JOIN {$mapping} ON {$mapping}.tec_event_post_id = {$series}.event_post_id
				INNER JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$series}.series_post_id
				WHERE {$mapping}.sc_event_id IS NOT NULL AND {$mapping}.id > %d
				AND {$series}.series_post_id IN (" . implode( ',', array_fill( 0, count( $grouping_series ), '%d' ) ) . ')',
				array_merge( [ TecIdLookup::get_last_mapping_id( 'events' ) ], $grouping_series )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$wpdb->suppress_errors( $suppress );

		foreach ( $events as $event ) {
			wp_set_object_terms( (int) $event->sc_event_post_id, $event->series_title, TagsHelpers::get_tags_taxonomy_id(), true );
		}
	}
}
