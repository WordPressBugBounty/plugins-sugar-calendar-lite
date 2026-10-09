<?php
/**
 * The Events page link behind an Ask answer's "Show me" button.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Sugar_Calendar\Admin\Pages\Events as EventsPage;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelper;
use Sugar_Calendar\Integrations\Abilities\Ability\GetRegistrationStats;
use WP_Term;

/**
 * Turns a `link_cta` block's query type and filters into an Events page URL.
 *
 * Fails closed: a value the page can't express drops the link, since a page missing a
 * filter would show more than the answer covered.
 *
 * @since 3.15.0
 */
class EventsPageLink {

	/**
	 * Query types the relay may send.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const QUERY_TYPES = [ 'events', 'registrations' ];

	/**
	 * Filter keys the Events page can honour.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const FILTER_KEYS = [ 'calendar_id', 'tag_ids', 'start', 'end', 'search', 'status' ];

	/**
	 * Statuses the Events page filters by, as in `Base::get_supported_post_stati()`.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'publish', 'future', 'draft', 'pending', 'private', 'hidden', 'trash' ];

	/**
	 * Most tag ids one link takes.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_TAGS = 100;

	/**
	 * Memoized site timezone.
	 *
	 * @since 3.15.0
	 *
	 * @var DateTimeZone|null
	 */
	private $zone = null;

	/**
	 * Build the Events page URL for a `link_cta` block.
	 *
	 * @since 3.15.0
	 *
	 * @param string|null $query_type Query type, `events` or `registrations`.
	 * @param array|null  $filters    Filter pairs, each `{ key, value }` strings.
	 *
	 * @return string|null Null when the page can't show what the answer covered.
	 */
	public function url( ?string $query_type, ?array $filters ): ?string {

		if ( ! in_array( $query_type, self::QUERY_TYPES, true ) ) {
			return null;
		}

		$values = $this->values( $filters ?? [] );
		$scope  = $values === null ? null : $this->scope( $values, $query_type );
		$view   = $scope === null ? null : $this->view( $query_type, $values, $scope );

		if ( $view === null ) {
			return null;
		}

		// add_query_arg() doesn't encode, and a search can hold `&` or spaces.
		$args = array_map( 'rawurlencode', array_map( 'strval', array_merge( $view, $scope ) ) );

		return add_query_arg( $args, EventsPage::get_url() );
	}

	/**
	 * The filters as key => value, or null when any pair is unusable.
	 *
	 * @since 3.15.0
	 *
	 * @param array $filters Relay filter pairs.
	 *
	 * @return array|null
	 */
	private function values( array $filters ): ?array {

		$values = [];

		foreach ( $filters as $filter ) {
			$pair = $this->pair( $filter );

			// An unknown, repeated or loose value means the page would show more than the answer covered.
			if ( $pair === null || isset( $values[ $pair[0] ] ) || ! $this->is_usable_date( $pair[0], $pair[1] ) ) {
				return null;
			}

			$values[ $pair[0] ] = $pair[1];
		}

		return $values;
	}

	/**
	 * Whether a `start`/`end` value is empty or an ISO date, refusing what
	 * `DateTimeImmutable` would otherwise parse loosely (`tomorrow`, `+1 week`).
	 *
	 * @since 3.15.0
	 *
	 * @param string $key   Filter key.
	 * @param string $value Filter value.
	 *
	 * @return bool
	 */
	private function is_usable_date( string $key, string $value ): bool {

		if ( ! in_array( $key, [ 'start', 'end' ], true ) || $value === '' ) {
			return true;
		}

		// A whole ISO value only, and a real day: `2026-02-30` would roll over to March.
		return (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/', $value, $m )
			&& checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * One filter as [ key, value ], or null when it isn't a known string pair.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $filter One relay filter.
	 *
	 * @return array|null
	 */
	private function pair( $filter ): ?array {

		if ( ! is_array( $filter ) || ! isset( $filter['key'], $filter['value'] ) || ! is_string( $filter['key'] ) || ! is_string( $filter['value'] ) ) {
			return null;
		}

		return in_array( $filter['key'], self::FILTER_KEYS, true ) ? [ $filter['key'], trim( $filter['value'] ) ] : null;
	}

	/**
	 * The page's scope parameters, or null when one of them can't be honoured.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $values     Filter values by key.
	 * @param string $query_type Query type, `events` or `registrations`.
	 *
	 * @return array|null
	 */
	private function scope( array $values, string $query_type ): ?array {

		$parts = [
			$this->calendar_arg( $values['calendar_id'] ?? '' ),
			$this->tags_arg( $values['tag_ids'] ?? '' ),
			$this->status_arg( $this->default_status( $values, $query_type ) ),
			$this->search_arg( $values['search'] ?? '' ),
		];

		return in_array( null, $parts, true ) ? null : array_merge( ...$parts );
	}

	/**
	 * The status value to filter by: what the answer sent, or, for `events`,
	 * `publish` (list-events lists published events unless it is given a status).
	 *
	 * @since 3.15.0
	 *
	 * @param array  $values     Filter values by key.
	 * @param string $query_type Query type, `events` or `registrations`.
	 *
	 * @return string
	 */
	private function default_status( array $values, string $query_type ): string {

		$status = $values['status'] ?? '';

		return $status === '' && $query_type === 'events' ? 'publish' : $status;
	}

	/**
	 * The calendar filter: one term id, sent to the page as its slug.
	 *
	 * @since 3.15.0
	 *
	 * @param string $id Calendar term id.
	 *
	 * @return array|null
	 */
	private function calendar_arg( string $id ): ?array {

		if ( $id === '' ) {
			return [];
		}

		// One id only: the page's calendar filter takes a single slug.
		$term = ctype_digit( $id ) ? get_term( (int) $id, sugar_calendar_get_calendar_taxonomy_id() ) : null;

		if ( ! ( $term instanceof WP_Term ) ) {
			return null;
		}

		// The page reads the slug through sanitize_key(), which strips a percent-encoded
		// (non-ASCII) slug's octets and would open an empty list.
		return sanitize_key( $term->slug ) === $term->slug ? [ 'sc_event_category' => $term->slug ] : null;
	}

	/**
	 * The tag filter: comma-separated term ids, all of which must exist.
	 *
	 * @since 3.15.0
	 *
	 * @param string $ids Tag term ids.
	 *
	 * @return array|null
	 */
	private function tags_arg( string $ids ): ?array {

		if ( $ids === '' ) {
			return [];
		}

		$list = array_map( 'trim', explode( ',', $ids ) );

		if ( count( $list ) > self::MAX_TAGS || count( array_filter( $list, 'ctype_digit' ) ) !== count( $list ) ) {
			return null;
		}

		$list  = array_values( array_unique( array_map( 'intval', $list ) ) );
		$found = get_terms(
			[
				'taxonomy'   => TagsHelper::get_tags_taxonomy_id(),
				'include'    => $list,
				'fields'     => 'ids',
				'hide_empty' => false,
			]
		);

		// Dropping an unknown tag would widen the page past the answer.
		return is_array( $found ) && count( $found ) === count( $list ) ? [ 'sc_event_tags' => implode( ',', $list ) ] : null;
	}

	/**
	 * The status filter.
	 *
	 * @since 3.15.0
	 *
	 * @param string $status Event status, or `any`.
	 *
	 * @return array|null
	 */
	private function status_arg( string $status ): ?array {

		if ( $status === '' || $status === 'any' ) {
			return [];
		}

		return in_array( $status, self::STATUSES, true ) ? [ 'status' => $status ] : null;
	}

	/**
	 * The search filter.
	 *
	 * @since 3.15.0
	 *
	 * @param string $search Search text.
	 *
	 * @return array
	 */
	private function search_arg( string $search ): array {

		$search = sanitize_text_field( $search );

		return $search === '' ? [] : [ 's' => $search ];
	}

	/**
	 * The view parameters for the answer's dates.
	 *
	 * @since 3.15.0
	 *
	 * @param string $query_type Query type, `events` or `registrations`.
	 * @param array  $values     Filter values by key.
	 * @param array  $scope      Scope parameters already resolved.
	 *
	 * @return array|null
	 */
	private function view( string $query_type, array $values, array $scope ): ?array {

		$start = $values['start'] ?? '';
		$end   = $values['end'] ?? '';

		if ( $query_type === 'events' ) {
			return $this->events_view( $start, $end );
		}

		// A site-wide stats answer has no events in scope to show.
		if ( empty( $scope['sc_event_category'] ) && empty( $scope['sc_event_tags'] ) ) {
			return null;
		}

		return $this->registrations_view( $start, $end );
	}

	/**
	 * A stats answer's view: the list for the years its window touches.
	 *
	 * @since 3.15.0
	 *
	 * @param string $start Window start.
	 * @param string $end   Window end.
	 *
	 * @return array|null
	 */
	private function registrations_view( string $start, string $end ): ?array {

		// Missing bounds default the way get-registration-stats defaults them.
		$range = $this->parse_range(
			$start !== '' ? $start : GetRegistrationStats::DEFAULT_START,
			$end !== '' ? $end : 'now',
			new DateTimeZone( 'UTC' )
		);

		return $range ? $this->list_view( $range[0], $range[1] ) : null;
	}

	/**
	 * An events answer's view: the smallest one that holds its range.
	 *
	 * List-events matches an offset-less value against events' wall-clock start,
	 * so it is site time here, unlike the registrations window above.
	 *
	 * @since 3.15.0
	 *
	 * @param string $start Range start.
	 * @param string $end   Range end.
	 *
	 * @return array|null
	 */
	private function events_view( string $start, string $end ): ?array {

		if ( $start === '' || $end === '' ) {
			return $this->open_view( $start !== '' ? $start : $end );
		}

		$range = $this->parse_range( $start, $end, $this->zone() );

		return $range ? $this->grid_view( $range[0], $range[1] ) : null;
	}

	/**
	 * The list for the year of the one bound given, or of today.
	 *
	 * @since 3.15.0
	 *
	 * @param string $bound The given bound, or ''.
	 *
	 * @return array|null
	 */
	private function open_view( string $bound ): ?array {

		$date = $this->parse_date( $bound !== '' ? $bound : 'now', $this->zone() );

		return $date ? $this->list_view( $date, $date ) : null;
	}

	/**
	 * Day, week or month when the range fits one, otherwise the list.
	 *
	 * @since 3.15.0
	 *
	 * @param DateTimeImmutable $from Range start, site time.
	 * @param DateTimeImmutable $to   Range end, site time, inclusive.
	 *
	 * @return array
	 */
	private function grid_view( DateTimeImmutable $from, DateTimeImmutable $to ): array {

		if ( $from->format( 'Y-m-d' ) === $to->format( 'Y-m-d' ) ) {
			return $this->date_view( 'day', $from );
		}

		if ( $to < $this->week_start( $from )->modify( '+7 days' ) ) {
			return $this->date_view( 'week', $from );
		}

		if ( $from->format( 'Y-m' ) === $to->format( 'Y-m' ) ) {
			return $this->date_view( 'month', $from->modify( 'first day of this month' ) );
		}

		return $this->list_view( $from, $to );
	}

	/**
	 * The first day of the week holding a date.
	 *
	 * @since 3.15.0
	 *
	 * @param DateTimeImmutable $date Site time.
	 *
	 * @return DateTimeImmutable
	 */
	private function week_start( DateTimeImmutable $date ): DateTimeImmutable {

		// The preference the Events page's week view starts on (Base::get_start_of_week()).
		$first = (int) sugar_calendar_get_user_preference( 'start_of_week', '1' );
		$back  = ( (int) $date->format( 'w' ) - $first + 7 ) % 7;

		return $date->setTime( 0, 0 )->modify( "-{$back} days" );
	}

	/**
	 * Parameters for a day, week or month view.
	 *
	 * @since 3.15.0
	 *
	 * @param string            $mode View mode, `day`, `week` or `month`.
	 * @param DateTimeImmutable $date The date the view opens on.
	 *
	 * @return array
	 */
	private function date_view( string $mode, DateTimeImmutable $date ): array {

		return [
			'mode' => $mode,
			'cy'   => (int) $date->format( 'Y' ),
			'cm'   => (int) $date->format( 'n' ),
			'cd'   => (int) $date->format( 'j' ),
		];
	}

	/**
	 * Parameters for the list view over whole years.
	 *
	 * @since 3.15.0
	 *
	 * @param DateTimeImmutable $from Range start.
	 * @param DateTimeImmutable $to   Range end.
	 *
	 * @return array
	 */
	private function list_view( DateTimeImmutable $from, DateTimeImmutable $to ): array {

		return [
			'mode'    => 'list',
			'cystart' => (int) $from->format( 'Y' ),
			'cy'      => (int) $to->format( 'Y' ),
		];
	}

	/**
	 * Parse a range, or null when a bound is invalid or the range runs backwards.
	 *
	 * @since 3.15.0
	 *
	 * @param string       $start        Range start.
	 * @param string       $end          Range end.
	 * @param DateTimeZone $default_zone Zone an offset-less value is read in.
	 *
	 * @return DateTimeImmutable[]|null
	 */
	private function parse_range( string $start, string $end, DateTimeZone $default_zone ): ?array {

		$from = $this->parse_date( $start, $default_zone );
		$to   = $this->parse_date( $end, $default_zone );

		if ( ! $from || ! $to || $from > $to ) {
			return null;
		}

		// An end at midnight is exclusive: "Sep 1 to Oct 1 00:00" is September.
		if ( $to > $from && $to->format( 'H:i:s' ) === '00:00:00' ) {
			$to = $to->modify( '-1 second' );
		}

		return [ $from, $to ];
	}

	/**
	 * Parse one date into site time. An offset in the value still wins over
	 * `$default_zone`.
	 *
	 * @since 3.15.0
	 *
	 * @param string       $value        Date-time.
	 * @param DateTimeZone $default_zone Zone an offset-less value is read in.
	 *
	 * @return DateTimeImmutable|null
	 */
	private function parse_date( string $value, DateTimeZone $default_zone ): ?DateTimeImmutable {

		try {
			return ( new DateTimeImmutable( $value, $default_zone ) )->setTimezone( $this->zone() );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * The timezone the Events page shows dates in.
	 *
	 * @since 3.15.0
	 *
	 * @return DateTimeZone
	 */
	private function zone(): DateTimeZone {

		if ( $this->zone !== null ) {
			return $this->zone;
		}

		try {
			$zone = sugar_calendar_get_timezone_object( sugar_calendar_get_timezone() );

			$this->zone = $zone instanceof DateTimeZone ? $zone : wp_timezone();
		} catch ( Exception $e ) {
			$this->zone = wp_timezone();
		}

		return $this->zone;
	}
}
