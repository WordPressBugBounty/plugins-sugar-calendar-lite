<?php
/**
 * Capacity lock for ticket checkout.
 *
 * @package Plugins/Site/Events/Ticketing
 */

namespace Sugar_Calendar\AddOn\Ticketing\Common;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Serializes a per-event (optionally per-occurrence) capacity re-check + insert with
 * a MySQL user-level named lock, so concurrent actors racing for the last slot(s)
 * cannot all pass the check before any of them commits. Shared by any feature that
 * needs capacity serialization via the $scope argument, which namespaces
 * the lock so different features never contend
 * on the same event.
 *
 * GET_LOCK / RELEASE_LOCK are MySQL *server* functions, independent of the storage
 * engine — they behave identically on MyISAM and InnoDB, unlike
 * START TRANSACTION / SELECT ... FOR UPDATE, which silently no-op on MyISAM. Since
 * Sugar Calendar ships to hosts we do not control (some restrict the engine), an
 * engine-agnostic guarantee is required.
 *
 * The lock is per-connection on a single MySQL primary; it is not replicated across
 * a Galera / multi-primary cluster or a read/write-split topology. That is a rare
 * advanced-hosting setup and is strictly no worse than today (no lock at all).
 *
 * @since 3.13.0
 */
final class CapacityLock {

	/**
	 * Object-cache group BerlinDB uses for ticket queries (prefixed runtime form).
	 *
	 * Must stay in sync with the runtime value of `Ticket_Query::$cache_group`
	 * (`'tickets'`) after the engine's `Query::set_prefix()` applies the `'sc'`
	 * prefix + `'-'`. If either the ticket query's cache group or the base prefix is
	 * ever renamed, update this constant in the same commit — a drift makes
	 * flush_ticket_cache() a silent no-op and reopens the oversell race.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const TICKET_CACHE_GROUP = 'sc-tickets';

	/**
	 * Acquire the per-event (optionally per-occurrence) capacity lock.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id      Event ID.
	 * @param int    $timeout       Seconds to wait for the lock. Must be positive — a
	 *                              negative value would mean "wait forever".
	 * @param int    $occurrence_id Optional. Occurrence ID for per-occurrence scoping.
	 *                              Default 0 (event-wide; suffix omitted for back-compat).
	 * @param string $scope         Optional. Feature namespace ('et', 'rsvp', …) so
	 *                              different features never contend on one event. Default 'et'.
	 *
	 * @return bool True only when the lock was genuinely acquired.
	 */
	public static function acquire( $event_id, $timeout = 5, $occurrence_id = 0, $scope = 'et' ) {

		global $wpdb;

		// GET_LOCK returns 1 (acquired), 0 (timed out) or NULL (error). $wpdb
		// returns column values as strings, so compare strictly against '1' and
		// treat everything else — timeout or error — as failure. Callers must fail
		// safe on false and never run the capacity check unlocked.
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::name( $event_id, $occurrence_id, $scope ), absint( $timeout ) )
		);

		return $result === '1';
	}

	/**
	 * Release the per-event (optionally per-occurrence) capacity lock.
	 *
	 * Safe to call more than once, and safe to call for a lock this session does
	 * not hold — RELEASE_LOCK returns 0/NULL in those cases, which we ignore. MySQL
	 * also auto-releases a session's locks when the connection closes, as a backstop.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id      Event ID.
	 * @param int    $occurrence_id Optional. Occurrence ID. Must match the acquire() call. Default 0.
	 * @param string $scope         Optional. Feature namespace. Must match acquire(). Default 'et'.
	 */
	public static function release( $event_id, $occurrence_id = 0, $scope = 'et' ) {

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::name( $event_id, $occurrence_id, $scope ) )
		);
	}

	/**
	 * Force the next ticket count to read from the database, not the object cache.
	 *
	 * BerlinDB caches each Ticket_Query's found_items (the count) under a key that
	 * embeds a per-cache-group `last_changed` token. That token is bumped only by
	 * the *current* process's ticket writes, so on the default non-persistent object
	 * cache a concurrent request re-reads its own pre-lock count and never sees
	 * another request's committed insert. Deleting `last_changed` forces BerlinDB to
	 * mint a fresh token on the next query → cache miss → a real SELECT COUNT. This
	 * is what makes the in-lock re-check genuinely fresh.
	 *
	 * @since 3.13.0
	 */
	public static function flush_ticket_cache() {

		wp_cache_delete( 'last_changed', self::TICKET_CACHE_GROUP );
	}

	/**
	 * Build the lock name: sc_<scope>_cap_<8hex>_<event_id>[_<occurrence_id>].
	 *
	 * Scoped to the install (DB name + table prefix) because MySQL user-level lock
	 * names are server-global. `$scope` namespaces the lock per feature; the
	 * occurrence suffix is omitted when $occurrence_id is empty so pre-existing
	 * ticketing calls keep their original names.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id      Event ID.
	 * @param int    $occurrence_id Optional. Occurrence ID. Default 0.
	 * @param string $scope         Optional. Feature namespace. Default 'et'.
	 *
	 * @return string
	 */
	private static function name( $event_id, $occurrence_id = 0, $scope = 'et' ) {

		global $wpdb;

		$scope = preg_replace( '/[^a-z0-9]/', '', (string) $scope );

		if ( $scope === '' ) {
			$scope = 'et';
		}

		$name = 'sc_' . $scope . '_cap_' . substr( md5( DB_NAME . $wpdb->prefix ), 0, 8 ) . '_' . absint( $event_id );

		// Per-occurrence suffix only when an occurrence is in play, so ticketing's
		// occurrence-less calls keep their original lock names (back-compat).
		if ( ! empty( $occurrence_id ) ) {
			$name .= '_' . absint( $occurrence_id );
		}

		return $name;
	}
}
