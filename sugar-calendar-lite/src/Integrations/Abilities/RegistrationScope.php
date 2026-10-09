<?php
/**
 * Which events a registration-stats call counts.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_Term;

/**
 * Turns the stats ability's filters into one set of Sugar Calendar event ids.
 *
 * Several ids in one filter widen it, several filters narrow each other, and every set is
 * de-duplicated, so an event counts once however many of the ids it matches.
 *
 * @since 3.15.0
 */
class RegistrationScope {

	/**
	 * Most events one filter may match; a filter at this size is refused.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const EVENT_CAP = 5000;

	/**
	 * Most ids one filter takes.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const IDS_CAP = 100;

	/**
	 * Event data access.
	 *
	 * @since 3.15.0
	 *
	 * @var EventRepository
	 */
	private $events;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param EventRepository $events Event data access.
	 */
	public function __construct( EventRepository $events ) {

		$this->events = $events;
	}

	/**
	 * The filters, keyed by input property.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function filters(): array {

		$core = [
			'calendar_ids' => [
				'key'       => 'calendars',
				/* translators: %d - calendar ID. */
				'not_found' => esc_html__( 'No calendar with ID %d.', 'sugar-calendar-lite' ),
				'resolve'   => [ $this, 'resolve_calendar' ],
			],
			'tag_ids'      => [
				'key'       => 'tags',
				/* translators: %d - tag ID. */
				'not_found' => esc_html__( 'No tag with ID %d.', 'sugar-calendar-lite' ),
				'resolve'   => [ $this, 'resolve_tag' ],
			],
			'event_ids'    => [
				'key'       => 'events',
				/* translators: %d - event ID. */
				'not_found' => esc_html__( 'No event with ID %d.', 'sugar-calendar-lite' ),
				'resolve'   => [ $this, 'resolve_event' ],
			],
		];

		/**
		 * Filters the registration-stats scope filters.
		 *
		 * Hook it before `wp_abilities_api_init`: the ability's input schema is built from
		 * this list when it registers.
		 *
		 * @since 3.15.0
		 *
		 * @param array           $filters Input property (ending in `_ids`) => [
		 *                                 'key'       => output key in `scope.filters`,
		 *                                 'not_found' => sprintf format with one %d for the id,
		 *                                 'resolve'   => callable( int $id ): array{ name: string, event_ids: int[] }|WP_Error|null,
		 *                                 ].
		 * @param EventRepository $events  Event data access, for resolvers that query events.
		 */
		$filters = apply_filters( 'sugar_calendar_integrations_abilities_registration_scope_filters', [], $this->events );

		// Seeded with core's own keys so a contributor can't overwrite one of them either.
		$used_keys = wp_list_pluck( $core, 'key' );
		$clean     = [];

		foreach ( is_array( $filters ) ? $filters : [] as $property => $filter ) {

			if (
				! is_string( $property )
				|| ! preg_match( '/^[a-z_]+_ids$/', $property )
				|| empty( $filter['key'] )
				|| empty( $filter['not_found'] )
				|| ! isset( $filter['resolve'] )
				|| ! is_callable( $filter['resolve'] )
			) {
				continue;
			}

			$key = sanitize_key( (string) $filter['key'] );

			if ( $key === '' || in_array( $key, $used_keys, true ) ) {
				continue;
			}

			$used_keys[]        = $key;
			$clean[ $property ] = [
				'key'       => $key,
				'not_found' => (string) $filter['not_found'],
				'resolve'   => $filter['resolve'],
			];
		}

		// Core's own filters come first and win a name clash.
		return array_merge( $core, array_diff_key( $clean, $core ) );
	}

	/**
	 * Input schema properties, one id list per filter.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function properties(): array {

		$list = [
			'type'     => 'array',
			'items'    => [
				'type'    => 'integer',
				'minimum' => 1,
			],
			'minItems' => 1,
			'maxItems' => self::IDS_CAP,
		];

		return array_fill_keys( array_keys( $this->filters() ), $list );
	}

	/**
	 * Resolve the filters in an ability input.
	 *
	 * @since 3.15.0
	 *
	 * @param array $args Ability input.
	 *
	 * @return array|WP_Error { filters: array, event_ids: int[]|null }, null event_ids meaning site-wide.
	 */
	public function resolve( array $args ) {

		$applied = [];
		$sets    = [];

		foreach ( $this->filters() as $property => $filter ) {

			if ( empty( $args[ $property ] ) ) {
				continue;
			}

			// WP_Ability::execute() validates but doesn't coerce, so a comma string can arrive here.
			$ids = wp_parse_id_list( $args[ $property ] );

			if ( empty( $ids ) ) {
				continue;
			}

			$matched = $this->resolve_filter( $filter, $ids );

			if ( is_wp_error( $matched ) ) {
				return $matched;
			}

			$applied[ $filter['key'] ] = $matched['named'];
			$sets[]                    = $matched['event_ids'];
		}

		if ( empty( $sets ) ) {
			return [
				'filters'   => [],
				'event_ids' => null,
			];
		}

		$event_ids = array_shift( $sets );

		foreach ( $sets as $set ) {
			$event_ids = array_intersect( $event_ids, $set );
		}

		return [
			'filters'   => $applied,
			'event_ids' => array_values( $event_ids ),
		];
	}

	/**
	 * Resolve one filter's ids to what they name and the union of their events.
	 *
	 * @since 3.15.0
	 *
	 * @param array $filter Filter entry.
	 * @param int[] $ids    Requested ids, already parsed by `resolve()`.
	 *
	 * @return array|WP_Error { named: array, event_ids: int[] }
	 */
	private function resolve_filter( array $filter, array $ids ) {

		$named = [];
		$union = [];

		foreach ( $ids as $id ) {

			$match = call_user_func( $filter['resolve'], $id );

			if ( is_wp_error( $match ) ) {
				return $match;
			}

			// One unknown id refuses the call: dropping it would shrink the total under the other ids' names.
			if ( ! is_array( $match ) ) {
				return new WP_Error( 'sc_events_not_found', sprintf( $filter['not_found'], $id ), [ 'status' => 404 ] );
			}

			$named[] = [
				'id'   => $id,
				'name' => (string) ( $match['name'] ?? '' ),
			];
			$union   = array_values( array_unique( array_filter( array_merge( $union, array_map( 'absint', (array) ( $match['event_ids'] ?? [] ) ) ) ) ) );

			// Refuse before resolving the remaining ids.
			if ( count( $union ) >= self::EVENT_CAP ) {
				return self::too_many();
			}
		}

		return [
			'named'     => $named,
			'event_ids' => $union,
		];
	}

	/**
	 * The refusal for a filter that matches too many events to count in full.
	 *
	 * @since 3.15.0
	 *
	 * @return WP_Error
	 */
	public static function too_many(): WP_Error {

		return new WP_Error(
			'sc_events_invalid_input',
			sprintf(
				/* translators: %s - the most events one filter can match, for example 5,000. */
				esc_html__( 'This filter matches %s or more events, which is more than one call can count. Adding another filter does not help. Ask site-wide, or use a narrower filter instead of this one.', 'sugar-calendar-lite' ),
				number_format_i18n( self::EVENT_CAP )
			),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Events in one calendar.
	 *
	 * @since 3.15.0
	 *
	 * @param int $id Calendar term id.
	 *
	 * @return array|WP_Error|null
	 */
	public function resolve_calendar( int $id ) {

		$term = $this->events->get_calendar( $id, '' );

		return $term ? $this->term_events( $term, $this->events->resolve_calendar_post_ids( $id ) ) : null;
	}

	/**
	 * Events with one tag.
	 *
	 * @since 3.15.0
	 *
	 * @param int $id Tag term id.
	 *
	 * @return array|WP_Error|null
	 */
	public function resolve_tag( int $id ) {

		$term = $this->events->get_tag( $id, '' );

		return $term ? $this->term_events( $term, $this->events->resolve_tag_post_ids( $id ) ) : null;
	}

	/**
	 * One event. A recurring series' parent id stands for every date, since orders and RSVPs
	 * store the parent id.
	 *
	 * Answers like an unknown id when the event's post is gone or the caller can't edit it.
	 *
	 * @since 3.15.0
	 *
	 * @param int $id Sugar Calendar event id.
	 *
	 * @return array|null
	 */
	public function resolve_event( int $id ) {

		$event = sugar_calendar_get_event( $id );

		if ( empty( $event ) || empty( $event->id ) ) {
			return null;
		}

		$post = get_post( (int) $event->object_id );

		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return null;
		}

		return [
			'name'      => (string) $event->title,
			'event_ids' => [ absint( $event->id ) ],
		];
	}

	/**
	 * A term's name and event ids.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_Term $term     Calendar or tag.
	 * @param int[]   $post_ids The term's event post ids.
	 *
	 * @return array|WP_Error
	 */
	private function term_events( WP_Term $term, array $post_ids ) {

		// The post lookup slices at this size without saying so.
		if ( count( $post_ids ) >= EventRepository::CALENDAR_FILTER_POST_ID_CAP ) {
			return self::too_many();
		}

		return [
			'name'      => Formatter::text( $term->name ),
			'event_ids' => empty( $post_ids ) ? [] : $this->events->resolve_event_ids( [ 'object_id__in' => $post_ids ], self::EVENT_CAP ),
		];
	}
}
