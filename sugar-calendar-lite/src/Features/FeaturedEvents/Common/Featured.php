<?php

namespace Sugar_Calendar\Features\FeaturedEvents\Common;

/**
 * Read model and query predicates for the Featured Events feature.
 *
 * Consumed by both admin and front-end surfaces, so nothing here may assume one.
 * Markup lives in Markup; the admin list filter lives with the admin page that owns it.
 *
 * @since 3.14.0
 */
class Featured {

	/**
	 * Event meta key holding the featured flag.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const META_KEY = 'featured';

	/**
	 * Get the meta_query that restricts an Event_Query to featured events.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function get_meta_query() {

		return [
			[
				'key'     => self::META_KEY,
				'value'   => '',
				'compare' => '!=',
			],
		];
	}

	/**
	 * Get a correlated EXISTS fragment restricting a raw query to featured events.
	 *
	 * For the raw-SQL seams that cannot add a JOIN. `Event_Query` callers should use
	 * get_meta_query() instead. The inner alias `fm` is reserved, and the fragment opens
	 * with `AND`, so it needs a WHERE that already carries a predicate.
	 *
	 * @since 3.14.0
	 *
	 * @param string $events_alias Alias of the sc_events table in the calling query.
	 *
	 * @return string
	 */
	public static function get_exists_sql( $events_alias ) {

		global $wpdb;

		// Caller-provided identifier, so keep it to an identifier's characters.
		$events_alias = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $events_alias );

		// Fail closed: an unusable alias must not silently widen the query to every event.
		if ( empty( $events_alias ) ) {
			return ' AND 1 = 0';
		}

		return $wpdb->prepare(
			" AND EXISTS ( SELECT 1 FROM {$wpdb->prefix}sc_eventmeta fm" .
			" WHERE fm.sc_event_id = {$events_alias}.id" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- An identifier, stripped to [A-Za-z0-9_] above.
			' AND fm.meta_key = %s AND fm.meta_value != %s )',
			self::META_KEY,
			''
		);
	}

	/**
	 * Count the distinct featured events among a set of event objects.
	 *
	 * Keyed on `object_id`, not `id`: a recurring occurrence has a virtual id.
	 *
	 * Cast rather than type hinted: callers pass hook output, which may not be an array.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $events Event objects.
	 *
	 * @return int
	 */
	public static function count_featured( $events ) {

		$post_ids = array_unique( array_filter( array_map( 'absint', wp_list_pluck( (array) $events, 'object_id' ) ) ) );

		if ( empty( $post_ids ) ) {
			return 0;
		}

		return count( array_intersect( $post_ids, self::get_featured_post_ids() ) );
	}

	/**
	 * Whether an event is marked as featured.
	 *
	 * Featured is a whole-series flag. A recurring occurrence stores no meta of its own
	 * and reaches some surfaces carrying a virtual event ID, so the question is always
	 * asked of the post, never of the event row.
	 *
	 * @since 3.14.0
	 *
	 * @param object $event Event object.
	 *
	 * @return bool
	 */
	public static function is_featured( $event ) {

		$object_id = ! empty( $event->object_id ) ? absint( $event->object_id ) : 0;

		if ( empty( $object_id ) ) {
			return false;
		}

		return in_array( $object_id, self::get_featured_post_ids(), true );
	}

	/**
	 * Reduce a set of admin-table row IDs to those belonging to a featured event.
	 *
	 * The one place featured is asked by ID rather than of an event object. Sanctioned
	 * because the display gate this serves runs before any event object exists — see
	 * "ask the post, never the event row" in the feature's rule file. Callers holding an
	 * event still use is_featured().
	 *
	 * Values are returned as they arrived, never recast: core matches these IDs with a
	 * strict in_array(), so a changed type silently matches nothing.
	 *
	 * @since 3.14.0
	 *
	 * @param array $row_ids Row IDs to reduce.
	 *
	 * @return array
	 */
	public static function filter_row_ids( $row_ids ) {

		$row_ids = (array) $row_ids;

		if ( empty( $row_ids ) ) {
			return [];
		}

		$allowed = self::query_featured_event_row_ids( $row_ids );

		/**
		 * Filter the row IDs recognised as belonging to a featured event.
		 *
		 * For rows core cannot resolve itself, added by whatever put them in the list.
		 * A subscriber that also injects rows into the admin table's own ID list must do
		 * so below priority 20, where the featured reduction runs.
		 *
		 * @since 3.14.0
		 *
		 * @param int[] $allowed Row IDs resolved so far.
		 * @param array $row_ids The full set being reduced.
		 */
		$allowed = (array) apply_filters( 'sugar_calendar_features_featured_events_common_featured_row_ids', $allowed, $row_ids );

		return array_values(
			array_filter(
				$row_ids,
				static function ( $row_id ) use ( $allowed ) {

					return in_array( (int) $row_id, $allowed, true );
				}
			)
		);
	}

	/**
	 * Get the `sc_events` rows among the given IDs that carry the featured flag.
	 *
	 * @since 3.14.0
	 *
	 * @param array $row_ids Row IDs to look up.
	 *
	 * @return int[]
	 */
	private static function query_featured_event_row_ids( $row_ids ) {

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $row_ids ), '%d' ) );

		// The lone-array form of prepare() is deliberate: $wpdb unwraps it, but the
		// placeholder sniff counts it as a single replacement.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT e.id FROM {$wpdb->prefix}sc_events e
				INNER JOIN {$wpdb->prefix}sc_eventmeta m ON m.sc_event_id = e.id
				WHERE m.meta_key = %s AND m.meta_value != %s AND e.id IN ( {$placeholders} )",
				array_merge( [ self::META_KEY, '' ], $row_ids )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return array_map( 'absint', (array) $results );
	}

	/**
	 * Get the post IDs of every featured event, memoized per request.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	private static function get_featured_post_ids() {

		static $post_ids = null;

		if ( $post_ids !== null ) {
			return $post_ids;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT e.object_id FROM {$wpdb->prefix}sc_events e
				INNER JOIN {$wpdb->prefix}sc_eventmeta m ON m.sc_event_id = e.id
				WHERE m.meta_key = %s AND m.meta_value != %s",
				self::META_KEY,
				''
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$post_ids = array_map( 'absint', (array) $results );

		return $post_ids;
	}
}
