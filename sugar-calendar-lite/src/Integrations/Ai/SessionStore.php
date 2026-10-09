<?php
/**
 * Per-user persistence and locking for Gia AI chat sessions.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Stores one session per user in a transient and serializes stepping with a
 * per-user option lock.
 *
 * @since 3.15.0
 */
class SessionStore {

	const SESSION_PREFIX = 'sc_ai_session_';
	const LOCK_PREFIX    = 'sc_ai_lock_';
	const CLEARED_PREFIX = 'sc_ai_cleared_';
	const STOP_PREFIX    = 'sc_ai_stop_';

	/**
	 * Session lifetime, refreshed on every save.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const TTL = DAY_IN_SECONDS;

	/**
	 * A lock older than this is treated as abandoned.
	 *
	 * Sized off the worst-case hold rather than the transport timeout alone: a
	 * step spends up to `Chat::TIMEOUT` (90s) on the relay round and then runs
	 * up to `Chat::MAX_TOOL_CALLS_PER_ROUND` abilities against the database, so
	 * a hold well past 120s is reachable.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const LOCK_TTL = 300;

	/**
	 * How long a Clear stays on record, past the relay's 90s transport timeout
	 * so a step that started before the Clear still sees it.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const CLEARED_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Largest serialized session written to the store, in bytes.
	 *
	 * A memcached-backed object cache refuses an item over 1 MB and
	 * `set_transient()` reports nothing when the write is dropped, so the
	 * payload is held to half of that: the upstream caps multiply (100
	 * transcript entries against `Chat::MAX_TOOL_RESULT_LENGTH`) and a lost
	 * session is indistinguishable from an eviction.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_PAYLOAD_BYTES = 512 * KB_IN_BYTES;

	/**
	 * Sanitizer reused across every read, so a poll of `get()` doesn't build a fresh
	 * one (and re-resolve stored links) each time. Lazy: several callers do `new SessionStore()`.
	 *
	 * @since 3.15.0
	 *
	 * @var AnswerSanitizer|null
	 */
	private $sanitizer;

	/**
	 * Load a user's session, or null.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 *
	 * @return Session|null
	 */
	public function get( int $user_id ) {

		$data = get_transient( self::SESSION_PREFIX . $user_id );

		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( $this->sanitizer === null ) {
			$this->sanitizer = new AnswerSanitizer();
		}

		return Session::from_array( $data, $this->sanitizer );
	}

	/**
	 * Store a user's session, refreshing the TTL.
	 *
	 * @since 3.15.0
	 *
	 * @param int     $user_id User id.
	 * @param Session $session Session.
	 *
	 * @return void
	 */
	public function save( int $user_id, Session $session ) {

		set_transient( self::SESSION_PREFIX . $user_id, $this->bound_payload( $session->to_array() ), self::TTL );
	}

	/**
	 * Drop the oldest transcript entries until the payload fits the ceiling.
	 *
	 * Only the transcript is trimmed: the wire history has its own cap in
	 * `TurnState::trim_history()`, and dropping from it behind the relay's back
	 * would change what the next round sends.
	 *
	 * @since 3.15.0
	 *
	 * @param array $data Serialized session.
	 *
	 * @return array
	 */
	private function bound_payload( array $data ): array {

		$size = strlen( (string) maybe_serialize( $data ) );

		while ( $size > self::MAX_PAYLOAD_BYTES && ! empty( $data['transcript'] ) ) {
			array_shift( $data['transcript'] );

			$size = strlen( (string) maybe_serialize( $data ) );
		}

		return $data;
	}

	/**
	 * Delete a user's session, with no removal on record.
	 *
	 * Private on purpose: it is `clear()`'s second half, and a caller reaching
	 * for the obvious-sounding name would skip the marker `clear()` exists for.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 *
	 * @return void
	 */
	private function delete( int $user_id ) {

		delete_transient( self::SESSION_PREFIX . $user_id );
	}

	/**
	 * Delete a user's session and put the removal on record.
	 *
	 * A persistent object cache cannot be assumed, so a session that has gone
	 * missing is not proof that the user cleared it: the transient can be
	 * evicted, or a `set_transient()` can fail. The marker is what tells the
	 * two apart, and only a recorded removal discards a charged relay round.
	 *
	 * `/stop` records the removal the same way when the session has already
	 * gone: the marker means "removed on purpose", not "cleared" specifically.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 *
	 * @return void
	 */
	public function clear( int $user_id ) {

		set_transient( self::CLEARED_PREFIX . $user_id, time(), self::CLEARED_TTL );

		$this->delete( $user_id );
	}

	/**
	 * Whether a Clear was recorded at or after the given time.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 * @param int $since   Unix time to compare against.
	 *
	 * @return bool
	 */
	public function cleared_since( int $user_id, int $since ): bool {

		$cleared_at = get_transient( self::CLEARED_PREFIX . $user_id );

		return is_numeric( $cleared_at ) && (int) $cleared_at >= $since;
	}

	/**
	 * Record a Stop for a turn that may not have reached the server yet.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $user_id User id.
	 * @param string $turn_id Client id of the turn to stop.
	 *
	 * @return void
	 */
	public function request_stop( int $user_id, string $turn_id ) {

		set_transient( self::STOP_PREFIX . $user_id, $turn_id, self::CLEARED_TTL );
	}

	/**
	 * Whether a Stop was recorded for this turn, consuming it.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $user_id User id.
	 * @param string $turn_id Client id of the turn.
	 *
	 * @return bool
	 */
	public function take_stop( int $user_id, string $turn_id ): bool {

		if ( $turn_id === '' || get_transient( self::STOP_PREFIX . $user_id ) !== $turn_id ) {
			return false;
		}

		delete_transient( self::STOP_PREFIX . $user_id );

		return true;
	}

	/**
	 * Take the per-user step lock, stealing an abandoned one.
	 *
	 * `add_option()` is a read-then-write and can admit two callers, so the
	 * acquire is a raw `INSERT IGNORE` on the unique option name, the same
	 * precedent `WP_Upgrader::create_lock()` uses. The lock carries an owner
	 * token so a finished step can only release the lock it took, never a
	 * newer step's. Every read of the row is a prepared SELECT rather than
	 * `get_option()`: the option cache answers "no such option" for a row a
	 * previous `delete_option()` recorded in `notoptions`, and a lock decided
	 * from that read steals a live one.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 *
	 * @return string The owner token on success, '' if another caller holds it.
	 */
	public function lock( int $user_id ) {

		global $wpdb;

		$key   = self::LOCK_PREFIX . $user_id;
		$token = wp_generate_uuid4();
		$value = $this->lock_value( $token );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$acquired = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )",
				$key,
				$value
			)
		);

		$this->flush_lock_cache( $key );

		// Only `0` proves a row is already there. `false` is a query error,
		// which is neither a held lock nor an abandoned one, so refuse rather
		// than fall through to the takeover.
		if ( $acquired === false ) {
			return '';
		}

		if ( $acquired > 0 ) {
			return $token;
		}

		$previous = $this->read_lock_row( $key );

		// A row we cannot read is a lock we cannot judge, so treat it as held:
		// stealing a live one runs two steps at once and charges the relay
		// twice. `WP_Upgrader::create_lock()` refuses in the same place.
		if ( $previous === null || ! $this->lock_expired( $previous ) ) {
			return '';
		}

		return $this->steal_lock( $key, $value, $previous, $token );
	}

	/**
	 * Take over a lock whose row is older than `LOCK_TTL`.
	 *
	 * Conditional on the value the SELECT returned, so of two callers that saw
	 * the same stale lock only one is handed a token.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key      Option name.
	 * @param string $value    The new serialized lock.
	 * @param string $previous The serialized lock the takeover is replacing.
	 * @param string $token    The new owner token.
	 *
	 * @return string The owner token, or '' when another caller won the race.
	 */
	private function steal_lock( string $key, string $value, string $previous, string $token ): string {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stolen = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				$value,
				$key,
				$previous
			)
		);

		$this->flush_lock_cache( $key );

		// The new value always differs from the old one, so a matched row is an
		// affected row. No rows and a query error both mean this caller lost.
		return $stolen > 0 ? $token : '';
	}

	/**
	 * Release the per-user step lock, only when this caller still owns it.
	 *
	 * A mismatched or missing token means a newer step already took the lock
	 * over, so this caller must leave it alone.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $user_id User id.
	 * @param string $token   The token returned by lock().
	 *
	 * @return void
	 */
	public function unlock( int $user_id, string $token ) {

		$key      = self::LOCK_PREFIX . $user_id;
		$previous = $this->read_lock_row( $key );

		if ( $previous !== null && $this->lock_owner( $previous ) === $token ) {
			delete_option( $key );
			$this->flush_lock_cache( $key );
		}
	}

	/**
	 * Release the per-user step lock unconditionally, for Stop and Clear.
	 *
	 * @since 3.15.0
	 *
	 * @param int $user_id User id.
	 *
	 * @return void
	 */
	public function force_unlock( int $user_id ) {

		$key = self::LOCK_PREFIX . $user_id;

		delete_option( $key );
		$this->flush_lock_cache( $key );
	}

	/**
	 * Run a read-modify-write of the user's session under the step lock.
	 *
	 * The callback receives the owner token and its return value is passed
	 * through; the lock is released however the callback exits.
	 *
	 * @since 3.15.0
	 *
	 * @param int      $user_id  User id.
	 * @param callable $callback Receives the owner token.
	 *
	 * @return mixed|WP_Error The callback's result, or `sc_ai_busy` when the lock is held.
	 */
	public function locked( int $user_id, callable $callback ) {

		$token = $this->lock( $user_id );

		if ( $token === '' ) {
			return new WP_Error( 'sc_ai_busy', __( 'The assistant is still working.', 'sugar-calendar-lite' ), [ 'status' => 409 ] );
		}

		try {
			return $callback( $token );
		} finally {
			$this->unlock( $user_id, $token );
		}
	}

	/**
	 * The lock row as stored, or null when there is no readable row.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Option name.
	 *
	 * @return string|null
	 */
	private function read_lock_row( string $key ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $key )
		);

		return is_string( $value ) ? $value : null;
	}

	/**
	 * One lock's stored value.
	 *
	 * @since 3.15.0
	 *
	 * @param string $token Owner token.
	 *
	 * @return string
	 */
	private function lock_value( string $token ): string {

		return (string) maybe_serialize(
			[
				'token' => $token,
				'time'  => time(),
			]
		);
	}

	/**
	 * Whether a stored lock is old enough to take over. An unreadable value
	 * counts as live.
	 *
	 * @since 3.15.0
	 *
	 * @param string $raw The stored option value.
	 *
	 * @return bool
	 */
	private function lock_expired( string $raw ): bool {

		$existing = maybe_unserialize( $raw );
		$held_at  = is_array( $existing ) && isset( $existing['time'] ) ? (int) $existing['time'] : 0;

		return $held_at > 0 && ( time() - $held_at ) >= self::LOCK_TTL;
	}

	/**
	 * The owner token in a stored lock, or ''.
	 *
	 * @since 3.15.0
	 *
	 * @param string $raw The stored option value.
	 *
	 * @return string
	 */
	private function lock_owner( string $raw ): string {

		$existing = maybe_unserialize( $raw );

		return is_array( $existing ) && isset( $existing['token'] ) ? (string) $existing['token'] : '';
	}

	/**
	 * Drop the lock row from the option caches, `notoptions` included.
	 *
	 * The raw INSERT and UPDATE bypass the option API, so without this the
	 * caches keep a value, or a `delete_option()`-recorded "does not exist"
	 * entry, that no longer matches the table. This class reads the row
	 * directly, but anything else on the request that reaches for
	 * `get_option()` would trust the stale answer.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Option name.
	 *
	 * @return void
	 */
	private function flush_lock_cache( string $key ) {

		wp_cache_delete( $key, 'options' );

		$notoptions = wp_cache_get( 'notoptions', 'options' );

		if ( is_array( $notoptions ) && isset( $notoptions[ $key ] ) ) {
			unset( $notoptions[ $key ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}
}
