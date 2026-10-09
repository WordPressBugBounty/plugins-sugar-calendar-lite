<?php
/**
 * Data access for the WordPress Abilities API integration.
 *
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Sugar_Calendar\Event_Query;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;
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
	 * Event_Query as `object_id__in`. Public so `RegistrationScope` can tell a cut-short
	 * lookup from a small one.
	 *
	 * @since 3.12.0
	 *
	 * @var int
	 */
	const CALENDAR_FILTER_POST_ID_CAP = 5000;

	/**
	 * Maximum number of a user's own event post IDs `resolve_own_post_ids()`
	 * will scope a non-public-status query to. Kept as its own constant
	 * rather than reusing `CALENDAR_FILTER_POST_ID_CAP` — that one is a
	 * performance guard on calendar-filter results, this one is a security-
	 * scoping cap; the two happen to share a value today but tuning one
	 * shouldn't silently change the other.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	private const OWN_EVENTS_SCOPE_CAP = 5000;

	/**
	 * Maximum number of event ids `resolve_search_title_content_ids()` and
	 * `resolve_search_location_ids()` will each resolve before search-events unions and
	 * paginates them. A search term matching more than this many events is not a
	 * realistic search-events use case.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private const SEARCH_ID_CAP = 5000;

	/**
	 * Per-request memoization for resolve_own_post_ids(), keyed by user ID.
	 *
	 * @since 3.13.0
	 *
	 * @var array<int, int[]>
	 */
	private $own_post_ids_cache = [];

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
	 * Fetch tag terms. Mirrors get_calendars() — the taxonomy is fixed, callers
	 * pass the rest of the standard get_terms() args.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args Arguments for get_terms(), without `taxonomy`.
	 *
	 * @return WP_Term[]|WP_Error
	 */
	public function get_tags( array $args ) {

		$args['taxonomy'] = TagsHelpers::get_tags_taxonomy_id();

		return get_terms( $args );
	}

	/**
	 * Count all calendar terms matching the filters, ignoring pagination.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args get_terms() arguments (without `taxonomy`).
	 *
	 * @return int
	 */
	public function count_calendars( array $args ): int {

		return $this->count_terms( $args, sugar_calendar_get_calendar_taxonomy_id() );
	}

	/**
	 * Count all tag terms matching the filters, ignoring pagination.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args get_terms() arguments (without `taxonomy`).
	 *
	 * @return int
	 */
	public function count_tags( array $args ): int {

		return $this->count_terms( $args, TagsHelpers::get_tags_taxonomy_id() );
	}

	/**
	 * Count matching terms in one taxonomy.
	 *
	 * `number`/`offset` are dropped so the total describes the whole result set
	 * rather than the page that was returned.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $args     get_terms() arguments (without `taxonomy`).
	 * @param string $taxonomy Taxonomy to count in.
	 *
	 * @return int
	 */
	private function count_terms( array $args, string $taxonomy ): int {

		unset( $args['number'], $args['offset'], $args['orderby'], $args['order'] );

		$args['taxonomy'] = $taxonomy;
		$args['fields']   = 'count';

		$count = get_terms( $args );

		return is_wp_error( $count ) ? 0 : (int) $count;
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

		// 'any' means every status but trash, as in WP_Query: a trashed event is not one to edit.
		if ( 'any' !== $status ) {
			$query_args['status'] = $status;
		} else {
			$query_args['status__not_in'] = [ 'trash' ];
		}

		// Non-public statuses (and 'any', which includes them) are only safe
		// to show site-wide to a user who can edit others' events — mirrors
		// how WP_REST_Posts_Controller scopes this same post type's REST
		// collection endpoint (a non-privileged requester's status filter is
		// implicitly narrowed to their own authored posts, not rejected
		// outright). Without this, any Contributor could pass status=any/
		// draft/pending/private and read every other author's unpublished
		// event content through this ability.
		if ( 'publish' !== $status && ! current_user_can( 'edit_others_events' ) ) {
			$own_ids = $this->resolve_own_post_ids( get_current_user_id() );

			// Guaranteed-empty match rather than an unrestricted query —
			// Event_Query's handling of a genuinely empty object_id__in is
			// untested territory; a nonexistent ID is the safe, deliberate
			// way to force zero rows (same reasoning GetEventStats already
			// applies for an empty calendar-filter result).
			$query_args['object_id__in'] = ! empty( $own_ids ) ? $own_ids : [ 0 ];
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
	 * Resolve the pair of ids an ability may be given to one Sugar Calendar
	 * event id, or 0 when neither names an event.
	 *
	 * An event carries two ids: the Sugar Calendar event id these abilities
	 * take, and the WP post id its editor URL carries. The post id is the one
	 * a user can see and paste, so both are accepted. An explicit event id
	 * wins when both arrive: it is the canonical one, and preferring it keeps
	 * a contradictory pair from silently editing whichever event the lookup
	 * happened to reach.
	 *
	 * No `object_subtype` is passed, so a recurring event's parent post
	 * resolves as well as a regular one, and no status filter, so a draft the
	 * user is editing resolves too. This only maps one id to the other, every
	 * caller still runs its own per-event capability gate on the result.
	 *
	 * @since 3.15.0
	 *
	 * @param int $event_id Sugar Calendar event id, or 0.
	 * @param int $post_id  WP post id, or 0.
	 *
	 * @return int Sugar Calendar event id, or 0 when nothing resolves.
	 */
	public function resolve_event_id( int $event_id, int $post_id ): int {

		if ( $event_id > 0 ) {
			return $event_id;
		}

		/**
		 * Filters the WP post id an ability looks up before it's resolved.
		 *
		 * A recurring series' editor URL carries a virtual occurrence id; a Pro callback
		 * maps it to the parent post id here, so every ability that accepts `post_id`
		 * resolves it the same way.
		 *
		 * @since 3.15.0
		 *
		 * @param int $post_id WP post id, or 0.
		 */
		$post_id = (int) apply_filters( 'sugar_calendar_integrations_abilities_event_repository_post_id', $post_id );

		if ( $post_id <= 0 ) {
			return 0;
		}

		$query = $this->get_event_query(
			[
				'object_id' => $post_id,
				'number'    => 1,
			]
		);
		$event = $query->items[0] ?? null;

		return $event ? absint( $event->id ) : 0;
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
	 * Resolve a tag term ID to a list of event post IDs (capped). Mirrors
	 * `resolve_calendar_post_ids()` for the tags taxonomy.
	 *
	 * @since 3.15.0
	 *
	 * @param int $tag_id Term ID.
	 *
	 * @return int[] Post IDs, or empty array if the term has no events.
	 */
	public function resolve_tag_post_ids( int $tag_id ): array {

		$ids = get_objects_in_term( $tag_id, TagsHelpers::get_tags_taxonomy_id() );

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
	 * Sugar Calendar event ids an Event_Query matches, across every status and subtype.
	 *
	 * Ids only, so no `Event` is built per row and no post, meta or term cache is primed.
	 *
	 * @since 3.15.0
	 *
	 * @param array $query_args Event_Query args, for example `object_id__in` or `venue_id`.
	 * @param int   $cap        Most ids to return.
	 *
	 * @return int[]
	 */
	public function resolve_event_ids( array $query_args, int $cap ): array {

		$query_args['number'] = $cap;
		$query_args['offset'] = 0;
		$query_args['fields'] = 'ids';

		$query = $this->get_event_query( $query_args );

		return array_map( 'intval', (array) ( $query->items ?? [] ) );
	}

	/**
	 * Event ids matching a search term by title or content, ignoring pagination and
	 * order (search-events unions this with `resolve_search_location_ids()` and applies
	 * both itself). Base filters (status, dates, `object_id__in`) ride `$query_args` so
	 * a non-privileged caller's scoping still applies to this half of the match.
	 *
	 * @since 3.15.0
	 *
	 * @param string $search     Search term.
	 * @param array  $query_args Base Event_Query args from `build_query_args()`.
	 *
	 * @return int[]
	 */
	public function resolve_search_title_content_ids( string $search, array $query_args ): array {

		$query_args['search']         = $search;
		$query_args['search_columns'] = [ 'title', 'content' ];
		$query_args['number']         = self::SEARCH_ID_CAP;
		$query_args['offset']         = 0;

		unset( $query_args['orderby'], $query_args['order'] );

		$query = $this->get_event_query( $query_args );

		return array_map( 'intval', wp_list_pluck( (array) ( $query->items ?? [] ), 'id' ) );
	}

	/**
	 * Event ids whose `location` event meta matches a search term.
	 *
	 * Run through `Event_Query` with the same base args as the title/content half, and a
	 * `meta_query` LIKE on `location`, rather than a raw `sc_eventmeta` query: the raw query
	 * capped at `SEARCH_ID_CAP` before any status/date/ownership scoping applied, so on a busy
	 * site the cap could fill entirely with rows a non-privileged caller cannot see, crowding
	 * out matches the caller was actually allowed to find (architecture review).
	 *
	 * @since 3.15.0
	 *
	 * @param string $search     Search term.
	 * @param array  $query_args Base Event_Query args from `build_query_args()`.
	 *
	 * @return int[]
	 */
	public function resolve_search_location_ids( string $search, array $query_args ): array {

		$query_args['meta_query'] = [
			[
				'key'     => 'location',
				'value'   => $search,
				'compare' => 'LIKE',
			],
		];
		$query_args['number']     = self::SEARCH_ID_CAP;
		$query_args['offset']     = 0;

		unset( $query_args['orderby'], $query_args['order'] );

		$query = $this->get_event_query( $query_args );

		return array_map( 'intval', wp_list_pluck( (array) ( $query->items ?? [] ), 'id' ) );
	}

	/**
	 * Resolve a user ID to the list of event post IDs they authored (capped).
	 * Used to scope a non-public-status query down to "your own events" for a
	 * caller who lacks `edit_others_events` — see `build_query_args()`.
	 *
	 * @since 3.13.0
	 *
	 * @param int $user_id WP user ID.
	 *
	 * @return int[] Post IDs, or empty array if the user has authored none.
	 */
	public function resolve_own_post_ids( int $user_id ): array {

		if ( $user_id <= 0 ) {
			return [];
		}

		// Memoized on this instance so a request that calls this more than
		// once doesn't re-run the query. This EventRepository instance is
		// shared by every ability in a single request (see
		// Abilities::build_abilities()), but is NOT shared across separate
		// requests — an AI/MCP client polling repeatedly still re-queries
		// each request. A cross-request cache would need invalidation on
		// publish/delete of the user's own events, which is out of scope here.
		if ( isset( $this->own_post_ids_cache[ $user_id ] ) ) {
			return $this->own_post_ids_cache[ $user_id ];
		}

		$ids = get_posts(
			[
				'post_type'      => sugar_calendar_get_event_post_type_id(),
				'author'         => $user_id,
				// 'any' excludes 'trash' — add it explicitly so a caller
				// requesting status=trash can still see their own trashed
				// events, not an empty result.
				'post_status'    => [ 'any', 'trash' ],
				'posts_per_page' => self::OWN_EVENTS_SCOPE_CAP,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		$this->own_post_ids_cache[ $user_id ] = array_values( array_filter( array_map( 'absint', $ids ) ) );

		return $this->own_post_ids_cache[ $user_id ];
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

		return $this->resolve_term_map( $post_ids, sugar_calendar_get_calendar_taxonomy_id() );
	}

	/**
	 * Tag terms for a set of event posts, keyed by post_id. Same shape as resolve_calendar_map().
	 *
	 * @since 3.15.0
	 *
	 * @param int[] $post_ids WP post IDs.
	 *
	 * @return array<int, WP_Term[]>
	 */
	public function resolve_tag_map( array $post_ids ): array {

		return $this->resolve_term_map( $post_ids, TagsHelpers::get_tags_taxonomy_id() );
	}

	/**
	 * One taxonomy's terms for a set of event posts, keyed by post_id, in one query.
	 *
	 * @since 3.15.0
	 *
	 * @param int[]  $post_ids WP post IDs.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array<int, WP_Term[]>
	 */
	private function resolve_term_map( array $post_ids, string $taxonomy ): array {

		$post_ids = array_filter( array_map( 'absint', $post_ids ) );

		if ( empty( $post_ids ) ) {
			return [];
		}

		$terms = wp_get_object_terms(
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
	 * Fetch a single tag term by id or slug.
	 *
	 * @since 3.14.0
	 *
	 * @param int    $id   Term id, or 0 to look up by slug.
	 * @param string $slug Term slug, used when $id is 0.
	 *
	 * @return WP_Term|null
	 */
	public function get_tag( int $id, string $slug ) {

		$taxonomy = TagsHelpers::get_tags_taxonomy_id();
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
