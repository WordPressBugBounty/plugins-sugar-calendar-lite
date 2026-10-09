<?php
/**
 * Post-write event snapshot and requested-vs-stored diff for the event write abilities.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Event;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;

/**
 * EventSnapshot — reads an event back after a write and diffs it against what was requested.
 *
 * Static, stateless. `for_event()` is the seam contributors widen with their own keys, keyed by
 * the same names the input schema uses, so `diff()` can compare field for field.
 *
 * @since 3.15.0
 */
class EventSnapshot {

	/**
	 * Datetime shapes `EventInputSchema` accepts for `start`/`end`.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const DATETIME_PATTERN = '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/';

	/**
	 * Read the stored shape of an event, keyed by ability input property name.
	 *
	 * @since 3.15.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 *
	 * @return array
	 */
	public static function for_event( int $event_id ): array {

		$event = sugar_calendar_get_event( $event_id );

		if ( empty( $event->id ) ) {
			return [];
		}

		$object_id = (int) $event->object_id;
		$calendars = self::terms( $object_id, sugar_calendar_get_calendar_taxonomy_id() );

		$tag_taxonomy = TagsHelpers::get_tags_taxonomy_id();
		$tags         = taxonomy_exists( $tag_taxonomy )
			? self::terms( $object_id, $tag_taxonomy )
			: [
				'ids'   => [],
				'names' => [],
			];

		$snapshot = self::core_keys( $event, $calendars, $tags );

		/**
		 * Filters the post-write snapshot of an event, keyed by ability input property.
		 *
		 * A contributor adds the stored value of every property it owns, in the shape the
		 * input schema accepts, so the caller can compare requested against stored field
		 * for field. Keep values small: the snapshot rides every later relay round, so
		 * read from `$event` and the event meta rather than resolving the row again.
		 *
		 * @since 3.15.0
		 *
		 * @param array $snapshot Snapshot so far.
		 * @param int   $event_id Sugar Calendar event id.
		 * @param Event $event    The stored event, already resolved.
		 */
		$snapshot = apply_filters( 'sugar_calendar_integrations_abilities_event_snapshot', $snapshot, $event_id, $event );

		return is_array( $snapshot ) ? $snapshot : [];
	}

	/**
	 * The keys core owns, before contributors widen the snapshot.
	 *
	 * Carries no description/excerpt/HTML: the snapshot rides every later relay round.
	 *
	 * @since 3.15.0
	 *
	 * @param Event $event     Stored event.
	 * @param array $calendars Output of `terms()` for the calendar taxonomy.
	 * @param array $tags      Output of `terms()` for the tag taxonomy.
	 *
	 * @return array
	 */
	private static function core_keys( Event $event, array $calendars, array $tags ): array {

		return [
			'title'           => (string) $event->title,
			'start'           => (string) $event->start,
			'end'             => (string) $event->end,
			'all_day'         => (bool) (int) $event->all_day,
			'timezone'        => (string) $event->start_tz,
			'location'        => (string) get_event_meta( (int) $event->id, 'location', true ),
			'calendar_ids'    => $calendars['ids'],
			'calendars'       => $calendars['names'],
			'tag_ids'         => $tags['ids'],
			'tags'            => $tags['names'],
			'signup'          => SignupModes::current( (int) $event->id ),
			// A provider meeting's (Zoom, etc.) join URL, distinct from `online_link`
			// (OnlineLinkContributor's Custom-Link-only property), so a caller reading
			// the snapshot doesn't mistake a provider meeting for "no link".
			'online_provider' => (string) get_event_meta( (int) $event->id, 'online_provider', true ),
			'online_join_url' => (string) get_event_meta( (int) $event->id, 'join_url', true ),
		];
	}

	/**
	 * Read an object's term ids and names in one query.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $object_id Post id.
	 * @param string $taxonomy  Taxonomy id.
	 *
	 * @return array `[ 'ids' => int[], 'names' => string[] ]`.
	 */
	private static function terms( int $object_id, string $taxonomy ): array {

		$terms = wp_get_object_terms( $object_id, $taxonomy, [ 'fields' => 'id=>name' ] );

		if ( ! is_array( $terms ) ) {
			$terms = [];
		}

		return [
			'ids'   => array_map( 'intval', array_keys( $terms ) ),
			'names' => array_map( [ self::class, 'decode_term_name' ], array_values( $terms ) ),
		];
	}

	/**
	 * Decode a stored term name's HTML entities, matching `Formatter::text()` so
	 * get-event and a write snapshot report the same `Rock & Roll`, not `Rock &amp; Roll`.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $name Raw term name.
	 *
	 * @return string
	 */
	private static function decode_term_name( $name ): string {

		return html_entity_decode( (string) $name, ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * Diff requested ability input against a stored snapshot.
	 *
	 * Only proves the half the plugin can prove: a requested key the snapshot never carries
	 * (`description`, `idempotency_key`, and the like) has nothing to compare
	 * against and is skipped.
	 *
	 * @since 3.15.0
	 *
	 * @param array $requested Ability input, as the caller sent it.
	 * @param array $snapshot  Output of `for_event()`.
	 * @param array $explained Field paths (`location`, `ticketing.quantity`, …) whose outcome a
	 *                         contributor already reported in `errors` or `assumptions`. A mismatch
	 *                         on one of these would repeat that explanation as a second,
	 *                         contradicting-sounding message, so it is skipped here.
	 *
	 * @return string[] Translated sentences, one per mismatch.
	 */
	public static function diff( array $requested, array $snapshot, array $explained = [] ): array {

		$messages = [];

		foreach ( $requested as $key => $requested_value ) {

			if ( in_array( $key, $explained, true ) ) {
				continue;
			}

			if ( ! array_key_exists( $key, $snapshot ) ) {
				continue;
			}

			$stored_value = $snapshot[ $key ];

			// An empty detail object (`ticketing: {}`) asked for nothing, so there is nothing to prove.
			if ( $requested_value === [] && self::is_detail_object( $stored_value ) ) {
				continue;
			}

			if ( self::is_detail_object( $requested_value ) && self::is_detail_object( $stored_value ) ) {
				$messages = array_merge( $messages, self::diff_detail_object( $key, $requested_value, $stored_value, $snapshot, $explained ) );

				continue;
			}

			if ( ! self::same( $requested_value, $stored_value, $snapshot, $key ) ) {
				$messages[] = self::mismatch_message( $key, $requested_value, $stored_value );
			}
		}

		return $messages;
	}

	/**
	 * Previous values of the detail-object leaves a write changed, keyed `{property}.{leaf}`.
	 *
	 * Strict comparison: both snapshots come from for_event(), so a leaf has the same cast on each
	 * side and there is nothing for same() to normalize. A detail object missing from `$before`
	 * (its signup mode was off) reports the previous value as null.
	 *
	 * @since 3.15.0
	 *
	 * @param array $requested Patch input as the caller sent it.
	 * @param array $before    Snapshot taken before the write.
	 * @param array $after     Snapshot taken after the write.
	 *
	 * @return array
	 */
	public static function changed_leaves( array $requested, array $before, array $after ): array {

		$changed = [];

		foreach ( $requested as $key => $requested_value ) {

			if ( ! self::is_detail_object( $requested_value ) ) {
				continue;
			}

			$was = isset( $before[ $key ] ) && is_array( $before[ $key ] ) ? $before[ $key ] : [];
			$now = isset( $after[ $key ] ) && is_array( $after[ $key ] ) ? $after[ $key ] : [];

			foreach ( array_keys( $requested_value ) as $leaf ) {

				$old = $was[ $leaf ] ?? null;

				if ( array_key_exists( $leaf, $now ) && $now[ $leaf ] !== $old ) {
					$changed[ $key . '.' . $leaf ] = $old;
				}
			}
		}

		return $changed;
	}

	/**
	 * Diff one level of a detail object's leaves, e.g. `ticketing.quantity`.
	 *
	 * Only the leaves the caller actually sent: a leaf the snapshot never carries has nothing to
	 * compare against.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key       Top-level input property name.
	 * @param array  $requested Requested detail object.
	 * @param array  $stored    Stored detail object.
	 * @param array  $snapshot  Full snapshot, for datetime/all-day context.
	 * @param array  $explained Field paths already explained elsewhere; see `diff()`.
	 *
	 * @return string[]
	 */
	private static function diff_detail_object( string $key, array $requested, array $stored, array $snapshot, array $explained = [] ): array {

		$messages = [];

		foreach ( $requested as $leaf => $leaf_requested ) {

			if ( in_array( $key . '.' . $leaf, $explained, true ) ) {
				continue;
			}

			if ( ! array_key_exists( $leaf, $stored ) ) {
				continue;
			}

			$leaf_stored = $stored[ $leaf ];
			$path        = $key . '.' . $leaf;

			if ( ! self::same( $leaf_requested, $leaf_stored, $snapshot, $path ) ) {
				$messages[] = self::mismatch_message( $path, $leaf_requested, $leaf_stored );
			}
		}

		return $messages;
	}

	/**
	 * Whether a value is an associative array, i.e. a detail object rather than a list.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Candidate value.
	 *
	 * @return bool
	 */
	private static function is_detail_object( $value ): bool {

		return is_array( $value ) && $value !== [] && ! self::is_list( $value );
	}

	/**
	 * Whether an array is a list (sequential integer keys from zero).
	 *
	 * @since 3.15.0
	 *
	 * @param array $value Candidate array.
	 *
	 * @return bool
	 */
	private static function is_list( array $value ): bool {

		return $value === [] || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Compare a requested value against its stored counterpart.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed  $requested Requested value.
	 * @param mixed  $stored    Stored value.
	 * @param array  $snapshot  Full snapshot, for datetime/all-day context.
	 * @param string $path      Field path being compared, e.g. `start` or `ticketing.quantity`.
	 *
	 * @return bool
	 */
	private static function same( $requested, $stored, array $snapshot, string $path ): bool {

		if ( is_bool( $requested ) || is_bool( $stored ) ) {
			return (bool) $requested === (bool) $stored;
		}

		if ( is_numeric( $requested ) && is_numeric( $stored ) ) {
			return abs( (float) $requested - (float) $stored ) < 0.005;
		}

		if ( is_array( $requested ) && is_array( $stored ) && self::is_list( $requested ) && self::is_list( $stored ) ) {
			return self::same_set( $requested, $stored );
		}

		if ( is_string( $requested ) && is_string( $stored ) ) {
			return self::same_string( $requested, $stored, $snapshot, $path );
		}

		return self::stringify( $requested ) === self::stringify( $stored );
	}

	/**
	 * Compare two lists as sets of scalars, numeric-casting where possible.
	 *
	 * @since 3.15.0
	 *
	 * @param array $a First list.
	 * @param array $b Second list.
	 *
	 * @return bool
	 */
	private static function same_set( array $a, array $b ): bool {

		$left  = self::sorted_set( $a );
		$right = self::sorted_set( $b );

		return $left === $right;
	}

	/**
	 * Sort a list for set comparison, casting numeric strings so `'3'` equals `3`.
	 *
	 * @since 3.15.0
	 *
	 * @param array $values List of scalars.
	 *
	 * @return array
	 */
	private static function sorted_set( array $values ): array {

		foreach ( $values as $index => $value ) {
			if ( is_numeric( $value ) ) {
				$values[ $index ] = (float) $value;
			}
		}

		sort( $values );

		return $values;
	}

	/**
	 * Compare two strings, canonicalizing a datetime pair and, on an all-day event, comparing
	 * only the date part of `start`/`end`.
	 *
	 * @since 3.15.0
	 *
	 * @param string $requested Requested string.
	 * @param string $stored    Stored string.
	 * @param array  $snapshot  Full snapshot, for the `all_day` flag.
	 * @param string $path      Field path being compared.
	 *
	 * @return bool
	 */
	private static function same_string( string $requested, string $stored, array $snapshot, string $path ): bool {

		$requested = trim( $requested );
		$stored    = trim( $stored );

		if ( preg_match( self::DATETIME_PATTERN, $requested ) && preg_match( self::DATETIME_PATTERN, $stored ) ) {
			$requested = self::canonical_datetime( $requested );
			$stored    = self::canonical_datetime( $stored );

			if ( ! empty( $snapshot['all_day'] ) && in_array( $path, [ 'start', 'end' ], true ) ) {
				return substr( $requested, 0, 10 ) === substr( $stored, 0, 10 );
			}
		}

		return $requested === $stored;
	}

	/**
	 * Canonicalize a datetime string to `Y-m-d H:i:s`, defaulting a bare date to midnight.
	 *
	 * @since 3.15.0
	 *
	 * @param string $value Datetime matching `DATETIME_PATTERN`.
	 *
	 * @return string
	 */
	private static function canonical_datetime( string $value ): string {

		$value = str_replace( 'T', ' ', $value );

		if ( strlen( $value ) === 10 ) {
			$value .= ' 00:00:00';
		} elseif ( strlen( $value ) === 16 ) {
			$value .= ':00';
		}

		return $value;
	}

	/**
	 * Fall-back string representation for values `same()`'s typed branches don't handle.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Value to stringify.
	 *
	 * @return string
	 */
	private static function stringify( $value ): string {

		if ( is_array( $value ) ) {
			return (string) wp_json_encode( $value );
		}

		return (string) $value;
	}

	/**
	 * Build one mismatch sentence.
	 *
	 * @since 3.15.0
	 *
	 * @param string $path            Field path, e.g. `start` or `ticketing.quantity`.
	 * @param mixed  $requested_value Requested value.
	 * @param mixed  $stored_value    Stored value.
	 *
	 * @return string
	 */
	private static function mismatch_message( string $path, $requested_value, $stored_value ): string {

		return sprintf(
			/* translators: %1$s: input field name, %2$s: the value that was requested, %3$s: the value that is stored. */
			__( '%1$s was not saved as requested: requested %2$s, stored %3$s.', 'sugar-calendar-lite' ),
			$path,
			self::display( $requested_value ),
			self::display( $stored_value )
		);
	}

	/**
	 * Render a value for a mismatch sentence.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Value to render.
	 *
	 * @return string
	 */
	private static function display( $value ): string {

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( $value === null || $value === '' || $value === [] ) {
			return __( 'nothing', 'sugar-calendar-lite' );
		}

		if ( is_array( $value ) ) {
			return (string) wp_json_encode( $value );
		}

		return (string) $value;
	}
}
