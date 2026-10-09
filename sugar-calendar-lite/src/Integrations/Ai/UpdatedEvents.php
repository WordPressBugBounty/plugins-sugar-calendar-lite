<?php
/**
 * Undo ledger: previous values for events a turn has changed.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * UpdatedEvents: the undo ledger, kept separate from TurnState's round loop.
 *
 * @since 3.15.0
 */
final class UpdatedEvents {

	/**
	 * Most events `$updated_events` keeps; the relay reads no more.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_UPDATED_EVENTS = 20;

	/**
	 * Most previous values kept per updated event; the relay reads no more.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_UPDATED_FIELDS = 40;

	/**
	 * Longest string previous value kept. A longer one is skipped, never cut: a cut value would
	 * "undo" to the wrong text.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const UPDATED_VALUE_MAX_LENGTH = 1000;

	/**
	 * Most items a list previous value may carry, so a month's days still fit.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const UPDATED_LIST_MAX_ITEMS = 31;

	/**
	 * Largest list previous value kept, JSON-encoded. A bigger one (a form with many options) is
	 * skipped, since `fit_updated_events()` would otherwise drop the event's whole undo entry for it.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const UPDATED_LIST_MAX_BYTES = 4096;

	/**
	 * Budget for the whole `$updated_events` map, JSON-encoded. It rides every round and lives in
	 * the session's `state`, which SessionStore::bound_payload() never trims.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const UPDATED_EVENTS_MAX_BYTES = 16384;

	/**
	 * Events this conversation changed through update-event, in first-write order: event id =>
	 * `title`, `previous` (field => value before the conversation first changed it) and `skipped`
	 * (fields whose first value was too long or not a scalar, so a later value never stands in).
	 *
	 * Sent as `updatedEvents` every round, for routing "undo that". Server-only: the view never
	 * carries it.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $updated_events = [];

	/**
	 * Merge one successful update-event result into `$updated_events`.
	 *
	 * Keeps the earliest value per path, but a property (`recurrence`) and its own leaves
	 * (`recurrence.weekdays`) are the same undo target: whichever was recorded first wins
	 * outright, and the other is dropped rather than kept alongside it, or replaying both
	 * could restore a `recurrence: null` next to leaves from a rule that no longer exists.
	 *
	 * @since 3.15.0
	 *
	 * @param int         $event_id Sugar Calendar event id.
	 * @param string|null $title    The event's title after the write, or null when unknown.
	 * @param array       $previous The result's `previous` map.
	 *
	 * @return UpdatedEvents
	 */
	public function remember_update( int $event_id, ?string $title, array $previous ): UpdatedEvents {

		if ( $event_id <= 0 ) {
			return $this;
		}

		$entry = $this->updated_events[ $event_id ] ?? [
			'title'    => null,
			'previous' => [],
			'skipped'  => [],
		];

		foreach ( $previous as $field => $value ) {

			$field = (string) $field;

			// First seen wins: an undo writes the old value back and must not become the
			// baseline. A property and one of its own leaves (`recurrence` vs
			// `recurrence.weekdays`) are the same undo target, so whichever was recorded
			// first wins outright rather than both being kept side by side.
			if ( self::path_already_recorded( $entry, $field ) ) {
				continue;
			}

			if ( ! self::is_undo_value( $value ) ) {
				$entry['skipped'][] = $field;
			} elseif ( count( $entry['previous'] ) < self::MAX_UPDATED_FIELDS ) {
				$entry['previous'][ $field ] = $value;
			} else {
				$entry['skipped'][] = $field;
			}
		}

		if ( $title !== null ) {
			$entry['title'] = mb_substr( $title, 0, EntityMemory::MAX_ENTITY_NAME );
		}

		// Assigning an existing key keeps its place, so the order stays first-write order.
		$this->updated_events[ $event_id ] = $entry;

		return $this->fit_updated_events();
	}

	/**
	 * Whether a path is already covered by an earlier `remember_update()` write: itself,
	 * or the property/leaf counterpart of the same undo target.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $entry Event entry so far (`previous`, `skipped`).
	 * @param string $field Dotted leaf path or bare property.
	 *
	 * @return bool
	 */
	private static function path_already_recorded( array $entry, string $field ): bool {

		if ( array_key_exists( $field, $entry['previous'] ) || in_array( $field, $entry['skipped'], true ) ) {
			return true;
		}

		$dot = strpos( $field, '.' );

		if ( $dot !== false ) {
			return array_key_exists( substr( $field, 0, $dot ), $entry['previous'] );
		}

		return self::has_leaf( $entry['previous'], $field );
	}

	/**
	 * Whether any dotted leaf of a property is already recorded.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $previous Recorded previous values so far.
	 * @param string $property Top-level property, for example `recurrence`.
	 *
	 * @return bool
	 */
	private static function has_leaf( array $previous, string $property ): bool {

		$prefix = $property . '.';

		foreach ( array_keys( $previous ) as $key ) {
			if ( strpos( $key, $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a previous value can travel in `updatedEvents`.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Previous value.
	 *
	 * @return bool
	 */
	private static function is_undo_value( $value ): bool {

		if ( ! is_array( $value ) ) {
			return self::is_undo_scalar( $value );
		}

		// A short list undoes as a whole: plain values (weekdays, calendar ids), or flat records such
		// as a registration form's fields. An object on its own never does.
		if ( count( $value ) > self::UPDATED_LIST_MAX_ITEMS || $value !== array_values( $value ) ) {
			return false;
		}

		if ( strlen( (string) wp_json_encode( $value ) ) > self::UPDATED_LIST_MAX_BYTES ) {
			return false;
		}

		foreach ( $value as $item ) {

			if ( ! self::is_undo_item( $item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether one list item can travel: a plain value, or a record of plain values and plain lists.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $item List item.
	 *
	 * @return bool
	 */
	private static function is_undo_item( $item ): bool {

		if ( ! is_array( $item ) ) {
			return self::is_undo_scalar( $item );
		}

		if ( count( $item ) > self::UPDATED_LIST_MAX_ITEMS ) {
			return false;
		}

		foreach ( $item as $leaf ) {

			// A record's option list is the one nesting allowed.
			$ok = is_array( $leaf ) ? self::is_plain_list( $leaf ) : self::is_undo_scalar( $leaf );

			if ( ! $ok ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a value is a short list of plain values.
	 *
	 * @since 3.15.0
	 *
	 * @param array $values Values.
	 *
	 * @return bool
	 */
	private static function is_plain_list( array $values ): bool {

		if ( count( $values ) > self::UPDATED_LIST_MAX_ITEMS || $values !== array_values( $values ) ) {
			return false;
		}

		foreach ( $values as $value ) {

			if ( ! self::is_undo_scalar( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a plain value can travel: null, a scalar, or a string within the length cap.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	private static function is_undo_scalar( $value ): bool {

		if ( is_string( $value ) ) {
			return mb_strlen( $value ) <= self::UPDATED_VALUE_MAX_LENGTH;
		}

		return $value === null || is_scalar( $value );
	}

	/**
	 * Drop the oldest updated events until the map fits both caps.
	 *
	 * @since 3.15.0
	 *
	 * @return UpdatedEvents
	 */
	private function fit_updated_events(): UpdatedEvents {

		while ( true ) {
			$count = count( $this->updated_events );
			$bytes = $count > 0 ? strlen( (string) wp_json_encode( $this->updated_events ) ) : 0;

			if ( $count <= self::MAX_UPDATED_EVENTS && $bytes <= self::UPDATED_EVENTS_MAX_BYTES ) {
				break;
			}

			// Not array_shift(): it renumbers the event-id keys.
			unset( $this->updated_events[ array_key_first( $this->updated_events ) ] );
		}

		return $this;
	}

	/**
	 * Events this conversation changed, event id => `title`, `previous`, `skipped`.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function updated_events(): array {

		return $this->updated_events;
	}

	/**
	 * Serialize for storage between requests.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function to_array(): array {

		return [
			'updated_events' => $this->updated_events,
		];
	}

	/**
	 * Rebuild from a TurnState payload's `updated_events` key.
	 *
	 * @since 3.15.0
	 *
	 * @param array $data Serialized state.
	 *
	 * @return UpdatedEvents
	 */
	public static function from_array( array $data ): UpdatedEvents {

		$ledger = new self();

		$ledger->updated_events = isset( $data['updated_events'] ) && is_array( $data['updated_events'] ) ? $data['updated_events'] : [];

		return $ledger;
	}
}
