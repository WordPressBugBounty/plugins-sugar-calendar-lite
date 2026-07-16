<?php
/**
 * Data access for the WordPress Abilities API integration.
 *
 * @package Sugar_Calendar
 * @subpackage Integrations\Abilities
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Sugar_Calendar\Event_Query;
use WP_Error;
use WP_Term;

/**
 * EventRepository — all database access for the Abilities integration: event
 * queries, counts, calendar-term lookups, and calendar↔post-ID resolution.
 * Injected into every ability so abilities never touch the database directly.
 *
 * The get_event_query() and get_calendars() methods are overridable seams so a
 * future test double can subclass this without hitting the database.
 *
 * @since 3.12.0
 */
class EventRepository {

	/**
	 * Maximum number of post IDs the calendar-filter resolver will pass into
	 * Event_Query as `object_id__in`.
	 *
	 * @since 3.12.0
	 *
	 * @var int
	 */
	private const CALENDAR_FILTER_POST_ID_CAP = 5000;

	/**
	 * Construct an Event_Query. Overridable seam for test doubles.
	 *
	 * @since 3.12.0
	 *
	 * @param array $args Event_Query arguments.
	 *
	 * @return Event_Query
	 */
	public function get_event_query( array $args ) {

		return new Event_Query( $args );
	}

	/**
	 * Fetch calendar terms. Overridable seam for test doubles. The taxonomy is
	 * fixed; callers pass the rest of the standard get_terms() args.
	 *
	 * @since 3.12.0
	 *
	 * @param array $args get_terms() arguments (without `taxonomy`).
	 *
	 * @return WP_Term[]|WP_Error
	 */
	public function get_calendars( array $args ) {

		$args['taxonomy'] = sugar_calendar_get_calendar_taxonomy_id();

		return get_terms( $args );
	}

	/**
	 * Run a count query for the given Event_Query args (number/offset stripped).
	 *
	 * @since 3.12.0
	 *
	 * @param array $query_args Event_Query arguments.
	 *
	 * @return int
	 */
	public function count( array $query_args ): int {

		unset( $query_args['number'], $query_args['offset'] );
		$query_args['count'] = true;

		$count_query = $this->get_event_query( $query_args );

		if ( isset( $count_query->found_items ) ) {
			return (int) $count_query->found_items;
		}

		return is_array( $count_query->items ?? null ) ? count( $count_query->items ) : 0;
	}

	/**
	 * Build base Event_Query args from sanitized input — applies status and date
	 * filters. Returns WP_Error if a date input is unparseable.
	 *
	 * @since 3.12.0
	 *
	 * @param array $args Sanitized input.
	 *
	 * @return array|WP_Error
	 */
	public function build_query_args( array $args ) {

		$query_args = [];

		$status = sanitize_text_field( $args['status'] ?? 'publish' );

		if ( 'any' !== $status ) {
			$query_args['status'] = $status;
		}

		$start_query = [];

		if ( ! empty( $args['start_after'] ) ) {
			$after = $this->parse_date_input( (string) $args['start_after'] );

			if ( '' === $after ) {
				return new WP_Error(
					'sc_events_invalid_input',
					esc_html__( 'Invalid start_after datetime.', 'sugar-calendar-lite' ),
					[ 'status' => 400 ]
				);
			}

			$start_query['after']     = $after;
			$start_query['inclusive'] = true;
		}

		if ( ! empty( $args['start_before'] ) ) {
			$before = $this->parse_date_input( (string) $args['start_before'] );

			if ( '' === $before ) {
				return new WP_Error(
					'sc_events_invalid_input',
					esc_html__( 'Invalid start_before datetime.', 'sugar-calendar-lite' ),
					[ 'status' => 400 ]
				);
			}

			$start_query['before']    = $before;
			$start_query['inclusive'] = true;
		}

		if ( ! empty( $start_query ) ) {
			$query_args['start_query'] = $start_query;
		}

		return $query_args;
	}

	/**
	 * Resolve a calendar term ID to a list of event post IDs (capped).
	 *
	 * @since 3.12.0
	 *
	 * @param int $calendar_id Term ID.
	 *
	 * @return int[] Post IDs, or empty array if the term has no events.
	 */
	public function resolve_calendar_post_ids( int $calendar_id ): array {

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();
		$ids      = get_objects_in_term( $calendar_id, $taxonomy );

		if ( is_wp_error( $ids ) || empty( $ids ) ) {
			return [];
		}

		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( count( $ids ) > self::CALENDAR_FILTER_POST_ID_CAP ) {
			$ids = array_slice( $ids, 0, self::CALENDAR_FILTER_POST_ID_CAP );
		}

		return array_values( $ids );
	}

	/**
	 * Resolve calendar terms for a list of event post IDs in a single batched
	 * wp_get_object_terms() call.
	 *
	 * @since 3.12.0
	 *
	 * @param array $post_ids List of WP post IDs (Event::object_id values).
	 *
	 * @return array Map of [ post_id => WP_Term[] ].
	 */
	public function resolve_calendar_map( array $post_ids ): array {

		$post_ids = array_filter( array_map( 'absint', $post_ids ) );

		if ( empty( $post_ids ) ) {
			return [];
		}

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();
		$terms    = wp_get_object_terms(
			$post_ids,
			$taxonomy,
			[ 'fields' => 'all_with_object_id' ]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$map = [];

		foreach ( $terms as $term ) {

			$post_id = isset( $term->object_id ) ? (int) $term->object_id : 0;

			if ( $post_id <= 0 ) {
				continue;
			}

			if ( ! isset( $map[ $post_id ] ) ) {
				$map[ $post_id ] = [];
			}

			$map[ $post_id ][] = $term;
		}

		return $map;
	}

	/**
	 * Look up a single calendar term by ID or slug. Caller guarantees exactly
	 * one of $id / $slug is provided (XOR validated in the ability).
	 *
	 * @since 3.12.0
	 *
	 * @param int    $id   Term ID (0 when looking up by slug).
	 * @param string $slug Term slug ('' when looking up by ID).
	 *
	 * @return WP_Term|null
	 */
	public function get_calendar( int $id, string $slug ) {

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();
		$term     = $id > 0
			? get_term( $id, $taxonomy )
			: get_term_by( 'slug', $slug, $taxonomy );

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Parse an ability date input (ISO 8601 datetime or date) into a UTC
	 * `Y-m-d H:i:s` string suitable for Event_Query start_query. '' on failure.
	 *
	 * @since 3.12.0
	 *
	 * @param string $value Raw user input.
	 *
	 * @return string Parsed UTC datetime, or '' on failure.
	 */
	private function parse_date_input( string $value ): string {

		if ( '' === $value ) {
			return '';
		}

		try {
			$dt = new DateTimeImmutable( $value );

			return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			return '';
		}
	}
}
