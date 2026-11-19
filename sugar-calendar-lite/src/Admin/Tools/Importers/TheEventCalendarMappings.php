<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecIdMap;

/**
 * The SQL that finds the TEC events not migrated yet: their derived table and the join over the event mapping rows.
 *
 * @since 3.18.0
 */
class TheEventCalendarMappings {

	/**
	 * A derived table of TEC events: `event_id` and `post_id`.
	 *
	 * Callers alias it as `{prefix}tec_events`, so queries written against TEC's table read it unchanged.
	 *
	 * Before TEC 6.0, or before it moved its data, events exist only as posts and take their negated post ID
	 * as event ID. Child posts and auto-drafts are left out.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public static function get_events_sql() {

		global $wpdb;

		if ( ! TheEventCalendarTables::exists( 'tec_events' ) ) {
			return "( SELECT -p.ID AS event_id, p.ID AS post_id FROM {$wpdb->posts} p WHERE p.post_type = 'tribe_events' AND p.post_parent = 0 AND p.post_status <> 'auto-draft' )";
		}

		// One reference to the table: MySQL can't open a temporary table twice in a query.
		return "( SELECT COALESCE( e.event_id, -p.ID ) AS event_id, p.ID AS post_id FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->prefix}tec_events e ON e.post_id = p.ID
			WHERE p.post_type = 'tribe_events' AND ( e.event_id IS NOT NULL OR ( p.post_parent = 0 AND p.post_status <> 'auto-draft' ) ) )";
	}

	/**
	 * Get the LEFT JOIN of the event mapping rows that still map a TEC event.
	 *
	 * A row matches the TEC event, or its post for an event TEC kept only as a post.
	 *
	 * @since 3.18.0
	 *
	 * @param string $tec_events The TEC events table or its alias.
	 *
	 * @return string
	 */
	public static function get_unmigrated_join( $tec_events ) {

		global $wpdb;

		$mapping = $wpdb->prefix . TecIdMap::EVENTS_TABLE;

		return " LEFT JOIN {$mapping} ON ( {$mapping}.tec_event_id IN ( {$tec_events}.event_id, -{$tec_events}.post_id )"
			. " OR {$mapping}.tec_event_post_id = {$tec_events}.post_id ) AND " . self::get_live_mapping_condition();
	}

	/**
	 * Get the SQL condition under which an event mapping row still maps its TEC event.
	 *
	 * A row whose Sugar Calendar event was deleted, or one still marked `-1` (its request died),
	 * no longer does. A failed migration (0, or NULL from earlier versions) still maps, or every
	 * batch would pick the same failing event again.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	private static function get_live_mapping_condition() {

		global $wpdb;

		$mapping   = $wpdb->prefix . TecIdMap::EVENTS_TABLE;
		$sc_events = $wpdb->prefix . 'sc_events';

		return "( {$mapping}.sc_event_id IS NULL OR {$mapping}.sc_event_id = 0"
			. " OR EXISTS ( SELECT 1 FROM {$sc_events} WHERE {$sc_events}.id = {$mapping}.sc_event_id ) )";
	}
}
