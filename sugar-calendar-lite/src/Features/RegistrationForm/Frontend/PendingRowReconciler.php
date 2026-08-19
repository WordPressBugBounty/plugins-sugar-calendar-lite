<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

/**
 * Reconciles an RSVP's stored response rows against who is submitting it now.
 *
 * Spec §4's two-pass match: given a context's stored rows and the attendee
 * ids behind the current submission's keys, decides which respondents need a
 * pending row minted and which rows are stale. Stateless and WordPress-free.
 *
 * @since 3.13.0
 */
class PendingRowReconciler {

	/**
	 * The respondents that still need a pending row minted, and the stored rows
	 * that no longer correspond to anyone submitting this RSVP.
	 *
	 * Two passes (spec §4): pass 1 matches by attendee_id; whatever is still
	 * unmatched goes through pass 2, matched by attendee_key, since a row's
	 * attendee_id can go stale to NULL while its key still names the same
	 * attendee (see match_by_attendee_key()).
	 *
	 * @since 3.13.0
	 *
	 * @param array $existing     The RSVP's current stored rows.
	 * @param array $attendee_ids Attendee id keyed by attendee key.
	 *
	 * @return array{respondents: array[], stale_ids: int[]}
	 */
	public static function respondents_needing_rows( array $existing, array $attendee_ids ) {

		$pass_1 = self::match_by_attendee_id( $existing, $attendee_ids );

		$unmatched_attendees = self::without_keys( $attendee_ids, $pass_1['matched_keys'] );
		$unmatched_rows      = self::without_row_ids( $existing, $pass_1['matched_row_ids'] );

		$pass_2 = self::match_by_attendee_key( $unmatched_rows, $unmatched_attendees );

		$unmatched_attendees = self::without_keys( $unmatched_attendees, $pass_2['matched_keys'] );
		$unmatched_rows      = self::without_row_ids( $unmatched_rows, $pass_2['matched_row_ids'] );

		return [
			'respondents' => self::new_respondents_for( $unmatched_attendees, $existing ),
			'stale_ids'   => self::stale_row_ids( $unmatched_rows ),
		];
	}

	/**
	 * The context's existing token to reuse, or '' to mint a new one.
	 *
	 * @since 3.13.0
	 *
	 * @param array $rows The RSVP's current stored rows.
	 *
	 * @return string
	 */
	public static function context_token( array $rows ) {

		foreach ( $rows as $row ) {

			// A malformed stored token would be printed into data-token and then
			// hard-rejected by SubmitEndpoint::posted_token(); reuse only valid tokens.
			if ( PendingRows::is_valid_token( $row['token'] ?? null ) ) {
				return $row['token'];
			}
		}

		return '';
	}

	/**
	 * Pass 1, matching each current attendee to a stored row sharing its
	 * attendee_id.
	 *
	 * Rows with a NULL attendee_id are skipped (spec §4 rule 1) and left for
	 * pass 2.
	 *
	 * @since 3.13.0
	 *
	 * @param array $existing     The RSVP's current stored rows.
	 * @param array $attendee_ids Attendee id keyed by attendee key.
	 *
	 * @return array{matched_keys: string[], matched_row_ids: int[]}
	 */
	private static function match_by_attendee_id( array $existing, array $attendee_ids ) {

		$row_id_by_attendee_id = [];

		foreach ( $existing as $row ) {
			if ( $row['attendee_id'] !== null ) {
				$row_id_by_attendee_id[ $row['attendee_id'] ] = $row['id'];
			}
		}

		$matched_keys    = [];
		$matched_row_ids = [];

		foreach ( $attendee_ids as $key => $attendee_id ) {

			if ( $attendee_id === null || ! isset( $row_id_by_attendee_id[ $attendee_id ] ) ) {
				continue;
			}

			$matched_keys[]    = (string) $key;
			$matched_row_ids[] = $row_id_by_attendee_id[ $attendee_id ];
		}

		return [
			'matched_keys'    => $matched_keys,
			'matched_row_ids' => $matched_row_ids,
		];
	}

	/**
	 * Pass 2, matching each attendee pass 1 left unmatched to a row pass 1 left
	 * unmatched, sharing its attendee_key.
	 *
	 * Only rows with a NULL attendee_id are eligible: attendee_key is a
	 * per-render positional slot, not a durable identity, so matching a row
	 * that still carries an attendee_id here could misattribute a departed
	 * guest's answers to whoever now occupies their freed slot.
	 *
	 * @since 3.13.0
	 *
	 * @param array $unmatched_rows      Stored rows pass 1 did not match.
	 * @param array $unmatched_attendees Attendee id keyed by attendee key, for
	 *                                   attendees pass 1 did not match.
	 *
	 * @return array{matched_keys: string[], matched_row_ids: int[]}
	 */
	private static function match_by_attendee_key( array $unmatched_rows, array $unmatched_attendees ) {

		$row_id_by_key = [];

		foreach ( $unmatched_rows as $row ) {
			if ( $row['attendee_key'] !== null && $row['attendee_id'] === null ) {
				$row_id_by_key[ $row['attendee_key'] ] = $row['id'];
			}
		}

		$matched_keys    = [];
		$matched_row_ids = [];

		foreach ( $unmatched_attendees as $key => $attendee_id ) {

			$key = (string) $key;

			if ( ! isset( $row_id_by_key[ $key ] ) ) {
				continue;
			}

			$matched_keys[]    = $key;
			$matched_row_ids[] = $row_id_by_key[ $key ];
		}

		return [
			'matched_keys'    => $matched_keys,
			'matched_row_ids' => $matched_row_ids,
		];
	}

	/**
	 * An attendee-id map with the given keys removed.
	 *
	 * @since 3.13.0
	 *
	 * @param array    $attendee_ids Attendee id keyed by attendee key.
	 * @param string[] $keys         Keys to remove.
	 *
	 * @return array
	 */
	private static function without_keys( array $attendee_ids, array $keys ) {

		return array_diff_key( $attendee_ids, array_flip( $keys ) );
	}

	/**
	 * A row list with the given ids removed.
	 *
	 * @since 3.13.0
	 *
	 * @param array $rows Stored rows.
	 * @param int[] $ids  Row ids to remove.
	 *
	 * @return array
	 */
	private static function without_row_ids( array $rows, array $ids ) {

		$ids = array_flip( $ids );

		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $ids ) {

					return ! isset( $ids[ $row['id'] ] );
				}
			)
		);
	}

	/**
	 * New respondents to mint for attendees neither pass matched (spec §4 rule 4).
	 *
	 * The new key is not the attendee's own submission key, which could already
	 * belong to a kept row (attendee_key is unique per context); counting up
	 * from the highest stored numeric key guarantees a fresh one.
	 *
	 * @since 3.13.0
	 *
	 * @param array $unmatched_attendees Attendee id keyed by attendee key, for
	 *                                   attendees neither pass matched.
	 * @param array $existing            The RSVP's current stored rows, to
	 *                                   derive the next free numeric key from.
	 *
	 * @return array[] Each entry: [ 'attendee_key' => string, 'attendee_id' => int|null ].
	 */
	private static function new_respondents_for( array $unmatched_attendees, array $existing ) {

		$next_key = 1 + array_reduce(
			$existing,
			static function ( $max, $row ) {

				if ( $row['attendee_key'] !== null && preg_match( '/^a(\d+)$/', $row['attendee_key'], $matches ) ) {
					return max( $max, (int) $matches[1] );
				}

				return $max;
			},
			0
		);

		$respondents = [];

		foreach ( $unmatched_attendees as $key => $attendee_id ) {

			$new_key = (string) $key === AnswerRequest::MAIN_KEY ? AnswerRequest::MAIN_KEY : 'a' . ( $next_key++ );

			$respondents[] = [
				'attendee_key' => $new_key,
				'attendee_id'  => $attendee_id,
			];
		}

		return $respondents;
	}

	/**
	 * Row ids to delete, from rows neither pass matched (spec §4 rule 5).
	 *
	 * Only pending rows: they hold no answer, so deleting one costs nothing.
	 * An answered row is left alone, since "unmatched" is not proof of
	 * "departed" (a narrowed schema, or a failed identity resolution, can also
	 * leave a still-present respondent unmatched). Genuine departures are
	 * handled elsewhere, via Cleanup::delete_rsvp_attendee_responses() on the
	 * sugar_calendar_rsvp_additional_attendees_deleted signal.
	 *
	 * @since 3.13.0
	 *
	 * @param array $unmatched_rows Stored rows neither pass matched.
	 *
	 * @return int[]
	 */
	private static function stale_row_ids( array $unmatched_rows ) {

		$stale_ids = [];

		foreach ( $unmatched_rows as $row ) {

			if ( $row['status'] === 'pending' ) {
				$stale_ids[] = $row['id'];
			}
		}

		return $stale_ids;
	}
}
