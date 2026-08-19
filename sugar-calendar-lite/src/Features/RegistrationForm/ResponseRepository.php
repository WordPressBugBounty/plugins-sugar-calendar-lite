<?php

namespace Sugar_Calendar\Features\RegistrationForm;

use WP_Error;

/**
 * Thin $wpdb wrapper around wp_sc_registration_responses.
 *
 * One row per respondent; answers are a JSON map keyed by schema field id.
 * attendee_key identifies the respondent within its context ('main' for the
 * ticketing purchaser, 'a{n}' for an attendee row); attendee_id is NULL both
 * for the purchaser and for an anonymous attendee, which is why attendee_key
 * exists (master design §4, Track B §3.6).
 *
 * @since 3.13.0
 */
class ResponseRepository {

	/**
	 * Fully-qualified table name.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public static function table_name() {

		global $wpdb;

		return $wpdb->prefix . 'sc_registration_responses';
	}

	/**
	 * Whether the responses table has been created yet.
	 *
	 * Needed because migrations run on `admin_init`, so a front-end, cron, or
	 * WP-CLI write can reach a table that isn't there yet. Compares against the
	 * full required version, not `> 0`: a partially-migrated table would
	 * otherwise fail per-statement on a missing column instead of failing
	 * closed here with a clear cause.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	private static function table_is_installed() {

		return (int) get_option( ResponsesTableMigration::OPTION_NAME, 0 ) >= ResponsesTableMigration::VERSION;
	}

	/**
	 * Insert a response row.
	 *
	 * @since 3.13.0
	 *
	 * @param array $data Response data — event_id, context ('order'|'rsvp'),
	 *                    context_id, answers (array); optional attendee_id, status.
	 *
	 * @return int|WP_Error Inserted id.
	 */
	public static function insert( array $data ) {

		if ( ! self::table_is_installed() ) {
			return new WP_Error(
				'registration_response_table_missing',
				'The registration responses table is missing or not fully migrated.'
			);
		}

		global $wpdb;

		$now = current_time( 'mysql', true );

		// attendee_key, attendee_id and token hold sentinel forms ('' / 0) here,
		// not null; the NULLIF() pair in the statement below maps them back to SQL NULL.
		$row = [
			'event_id'     => (int) $data['event_id'],
			'context'      => (string) $data['context'],
			'context_id'   => (int) $data['context_id'],
			'attendee_key' => isset( $data['attendee_key'] ) ? (string) $data['attendee_key'] : '',
			'attendee_id'  => isset( $data['attendee_id'] ) ? (int) $data['attendee_id'] : 0,
			'token'        => isset( $data['token'] ) ? (string) $data['token'] : '',
			'answers'      => wp_json_encode( (array) ( $data['answers'] ?? [] ) ),
			'status'       => (string) ( $data['status'] ?? 'complete' ),
			'created_at'   => $now,
			'updated_at'   => $now,
		];

		$table = self::table_name();

		/*
		 * An UPSERT, not a plain insert: (context, context_id, attendee_key) is
		 * unique since migration v3, so a race between two concurrent submissions
		 * updates the winner's row instead of failing the loser silently.
		 *
		 * NULLIF is load-bearing — $wpdb->prepare() renders a PHP null as '' / 0
		 * for %s/%d, which would turn a genuinely absent key into a non-NULL value
		 * and break the unique index's NULL semantics.
		 *
		 * VALUES() rather than the row-alias form, since the alias syntax needs
		 * MySQL 8.0.19+ and WordPress still supports 5.7/MariaDB 10.3.
		 * `id = LAST_INSERT_ID(id)` keeps $wpdb->insert_id correct on the update
		 * path. `created_at` and `token` are both deliberately absent from the
		 * update list, so a re-submission keeps the row's original creation time
		 * and doesn't rotate a credential a visitor may already have open.
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is not user input, and the interpolation sits inside a multi-line string where a per-line ignore cannot reach the reported line.
		$ok = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom wp_sc_registration_responses table.
			$wpdb->prepare(
				"INSERT INTO {$table}
					(event_id, context, context_id, attendee_key, attendee_id, token, answers, status, created_at, updated_at)
				VALUES (%d, %s, %d, NULLIF(%s, ''), NULLIF(%d, 0), NULLIF(%s, ''), %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE
					id = LAST_INSERT_ID(id),
					attendee_id = VALUES(attendee_id),
					answers = VALUES(answers),
					status = VALUES(status),
					updated_at = VALUES(updated_at)",
				$row['event_id'],
				$row['context'],
				$row['context_id'],
				$row['attendee_key'],
				$row['attendee_id'],
				$row['token'],
				$row['answers'],
				$row['status'],
				$row['created_at'],
				$row['updated_at']
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $ok === false ) {
			return new WP_Error( 'registration_response_insert_failed', $wpdb->last_error );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Replace a row's answers and mark it complete.
	 *
	 * `$attendee_id` is required, not optional: RSVP renumbers its attendee row
	 * ids on every render, so a key can come to mean a different person between
	 * submissions, and callers must re-resolve the id on every update rather
	 * than let a stale value survive by omission.
	 *
	 * @since 3.13.0
	 *
	 * @param int      $id          Row id.
	 * @param array    $answers     Sanitized answers keyed by field id.
	 * @param int|null $attendee_id Re-resolved attendee id, or null when unresolvable.
	 *
	 * @return int|WP_Error Affected row count, or WP_Error on DB failure. Zero is
	 *                      not success — a WHERE that matched nothing (a row a
	 *                      concurrent reconcile deleted) also returns 0. Callers
	 *                      must distinguish 0 from > 0.
	 */
	public static function update_answers( $id, array $answers, $attendee_id ) {

		global $wpdb;

		$ok = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom wp_sc_registration_responses table.
			self::table_name(),
			[
				'answers'     => wp_json_encode( $answers ),
				'attendee_id' => $attendee_id === null ? null : (int) $attendee_id,
				'status'      => 'complete',
				'updated_at'  => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $id ]
		);

		if ( $ok === false ) {
			return new WP_Error( 'registration_response_update_failed', $wpdb->last_error );
		}

		return (int) $ok;
	}

	/**
	 * Flip a pending after-checkout row to complete.
	 *
	 * Deliberately narrower than update_answers(): an after-checkout row is
	 * minted from the already-persisted attendee list, so attendee_id is
	 * correct at birth and a later submission has no better information to
	 * re-derive it from. The token is untouched for the same reason.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $id      Row id.
	 * @param array $answers Sanitized answers keyed by field id.
	 *
	 * @return int|WP_Error Affected row count, or WP_Error on DB failure. Zero is
	 *                      not success — see update_answers() for why.
	 */
	public static function mark_complete( $id, array $answers ) {

		global $wpdb;

		$ok = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom wp_sc_registration_responses table.
			self::table_name(),
			[
				'answers'    => wp_json_encode( $answers ),
				'status'     => 'complete',
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $id ]
		);

		if ( $ok === false ) {
			return new WP_Error( 'registration_response_mark_complete_failed', $wpdb->last_error );
		}

		return (int) $ok;
	}

	/**
	 * Delete rows by id.
	 *
	 * The reconcile primitive: an RSVP update removes rows for attendees no
	 * longer present. An empty list is a no-op, not a wildcard — omitting the
	 * WHERE clause would empty the table.
	 *
	 * @since 3.13.0
	 *
	 * @param int[] $ids Row ids.
	 *
	 * @return bool|WP_Error True when the statement ran, WP_Error on DB failure.
	 */
	public static function delete( array $ids ) {

		global $wpdb;

		$ids = array_values(
			array_filter(
				array_map( 'absint', $ids )
			)
		);

		if ( empty( $ids ) ) {
			return true;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$ok = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom wp_sc_registration_responses table; placeholders are generated from a count, values are prepared.
			$wpdb->prepare(
				'DELETE FROM `' . self::table_name() . '` WHERE id IN (' . $placeholders . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name is built from $wpdb->prefix; placeholders are generated, not user input.
				$ids
			)
		);

		if ( $ok === false ) {
			return new WP_Error( 'registration_response_delete_failed', $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Delete every row of one context.
	 *
	 * The token lives on these rows, so this destroys the context's credential
	 * along with its answers — which is exactly why the token is stored here.
	 *
	 * @since 3.13.0
	 *
	 * @param string $context    Context, either 'order' or 'rsvp'.
	 * @param int    $context_id Context id.
	 *
	 * @return bool|WP_Error
	 */
	public static function delete_for_context( $context, $context_id ) {

		global $wpdb;

		$context    = (string) $context;
		$context_id = (int) $context_id;

		if ( $context === '' || $context_id <= 0 ) {
			return true;
		}

		if ( ! self::table_is_installed() ) {
			return true;
		}

		$table = self::table_name();
		$ok    = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; name is not user input.
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE context = %s AND context_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is not user input.
				$context,
				$context_id
			)
		);

		if ( $ok === false ) {
			return new WP_Error( 'registration_response_delete_context_failed', $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Delete every row for one event, across all contexts.
	 *
	 * `$event_id` is the Sugar Calendar event row id, NOT a post id — callers
	 * resolving from a post must go through sugar_calendar_get_event_by_object().
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 *
	 * @return bool|WP_Error
	 */
	public static function delete_for_event( $event_id ) {

		global $wpdb;

		$event_id = (int) $event_id;

		if ( $event_id <= 0 ) {
			return true;
		}

		if ( ! self::table_is_installed() ) {
			return true;
		}

		$table = self::table_name();
		$ok    = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; name is not user input.
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE event_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is not user input.
				$event_id
			)
		);

		if ( $ok === false ) {
			return new WP_Error( 'registration_response_delete_event_failed', $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Get one row by id.
	 *
	 * @since 3.13.0
	 *
	 * @param int $id Row id.
	 *
	 * @return array|null
	 */
	public static function get( $id ) {

		global $wpdb;

		$table = self::table_name();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; name is not user input.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is not user input.
			ARRAY_A
		);

		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * All rows for a ticketing order.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array[]
	 */
	public static function get_for_order( $order_id ) {

		return self::get_for_context( 'order', $order_id );
	}

	/**
	 * All rows for an RSVP.
	 *
	 * @since 3.13.0
	 *
	 * @param int $rsvp_id RSVP id.
	 *
	 * @return array[]
	 */
	public static function get_for_rsvp( $rsvp_id ) {

		return self::get_for_context( 'rsvp', $rsvp_id );
	}

	/**
	 * All rows sharing one after-checkout token, oldest first.
	 *
	 * Every row of a context carries the same token, so the caller compares
	 * against `$rows[0]['token']`. An empty needle returns nothing rather than
	 * matching the NULL/'' tokens on before-checkout rows.
	 *
	 * @since 3.13.0
	 *
	 * @param string $token The token.
	 *
	 * @return array[]
	 */
	public static function find_by_token( $token ) {

		global $wpdb;

		$token = (string) $token;

		if ( $token === '' ) {
			return [];
		}

		// SubmitEndpoint reaches this from an unauthenticated ajax action, so it can
		// arrive before the migration has run; guard the same as get_for_context().
		if ( ! self::table_is_installed() ) {
			return [];
		}

		$table = self::table_name();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; name is not user input.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE token = %s ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is not user input.
				$token
			),
			ARRAY_A
		);

		return array_map( [ self::class, 'hydrate' ], (array) $rows );
	}

	/**
	 * Contexts holding at least one stale, un-reminded pending row.
	 *
	 * Grouped by context because the reminder is per order/RSVP, not per
	 * respondent. `token`/`event_id` are identical across a context's rows by
	 * construction, so MIN() just selects them under GROUP BY. Ordered
	 * oldest-first so a backlog drains in arrival order.
	 *
	 * @since 3.13.0
	 *
	 * @param string $before MySQL datetime — rows created before this are stale.
	 * @param int    $limit  Maximum contexts to return.
	 *
	 * @return array[] Each entry: context, context_id, event_id, token, oldest_created_at.
	 */
	public static function find_stale_pending_contexts( $before, $limit ) {

		global $wpdb;

		$before = (string) $before;
		$limit  = (int) $limit;

		if ( $before === '' || $limit <= 0 ) {
			return [];
		}

		// Same guard every other reader carries: this runs from Action Scheduler,
		// which can fire on a site that has upgraded but not yet run the migration.
		if ( ! self::table_is_installed() ) {
			return [];
		}

		$table = self::table_name();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is not user input, and the interpolation sits inside a multi-line string where a per-line ignore cannot reach the reported line.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; name is not user input.
			$wpdb->prepare(
				"SELECT context, context_id, MIN(event_id) AS event_id, MIN(token) AS token, MIN(created_at) AS oldest_created_at
				FROM {$table}
				WHERE status = 'pending' AND reminder_sent_at IS NULL AND created_at < %s
				GROUP BY context, context_id
				ORDER BY oldest_created_at ASC
				LIMIT %d",
				$before,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = [];

		foreach ( (array) $rows as $row ) {
			$out[] = [
				'context'           => (string) $row['context'],
				'context_id'        => (int) $row['context_id'],
				'event_id'          => (int) $row['event_id'],
				'token'             => $row['token'] === null ? '' : (string) $row['token'],
				'oldest_created_at' => (string) $row['oldest_created_at'],
			];
		}

		return $out;
	}

	/**
	 * Stamp reminder_sent_at on every row of one context.
	 *
	 * Every row, not only the stale pending ones — leaving a completed row NULL
	 * would resurface the context the moment another pending row joined it.
	 *
	 * @since 3.13.0
	 *
	 * @param string $context    Context, either 'order' or 'rsvp'.
	 * @param int    $context_id Context id.
	 *
	 * @return bool False when nothing was stamped.
	 */
	public static function mark_reminded( $context, $context_id ) {

		global $wpdb;

		$context    = (string) $context;
		$context_id = (int) $context_id;

		if ( $context === '' || $context_id <= 0 || ! self::table_is_installed() ) {
			return false;
		}

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			self::table_name(),
			[ 'reminder_sent_at' => current_time( 'mysql', true ) ],
			[
				'context'    => $context,
				'context_id' => $context_id,
			],
			[ '%s' ],
			[ '%s', '%d' ]
		);

		return $updated !== false && (int) $updated > 0;
	}

	/**
	 * All rows for one context, oldest first.
	 *
	 * @since 3.13.0
	 *
	 * @param string $context    Context, either 'order' or 'rsvp'.
	 * @param int    $context_id Context id.
	 *
	 * @return array[]
	 */
	private static function get_for_context( $context, $context_id ) {

		global $wpdb;

		// The migration runs on admin_init, so a front-end Going RSVP or a
		// WP-CLI-created order can reach this read before it has run.
		if ( ! self::table_is_installed() ) {
			return [];
		}

		$table = self::table_name();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; name is not user input.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE context = %s AND context_id = %d ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is not user input.
				$context,
				(int) $context_id
			),
			ARRAY_A
		);

		return array_map( [ self::class, 'hydrate' ], (array) $rows );
	}

	/**
	 * Cast a raw DB row into the typed shape callers consume.
	 *
	 * @since 3.13.0
	 *
	 * @param array $row Raw row.
	 *
	 * @return array
	 */
	private static function hydrate( array $row ) {

		$answers = json_decode( (string) $row['answers'], true );

		$row['id']           = (int) $row['id'];
		$row['event_id']     = (int) $row['event_id'];
		$row['context_id']   = (int) $row['context_id'];
		$row['attendee_key'] = isset( $row['attendee_key'] ) && $row['attendee_key'] !== null
			? (string) $row['attendee_key']
			: null;
		$row['attendee_id']  = $row['attendee_id'] === null ? null : (int) $row['attendee_id'];
		$row['answers']      = is_array( $answers ) ? $answers : [];
		$row['token']        = isset( $row['token'] ) && $row['token'] !== null ? (string) $row['token'] : null;

		return $row;
	}
}
