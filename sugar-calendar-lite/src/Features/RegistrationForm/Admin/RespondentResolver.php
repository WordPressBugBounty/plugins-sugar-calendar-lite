<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Features\RegistrationForm\Frontend\AnswerRequest;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;

/**
 * Bind a context's stored response rows to the attendees a host knows about.
 *
 * Rows are keyed by front-end POST position; attendees carry no such key. Binds
 * by attendee_id first, then positionally over what's left, mints a key for an
 * attendee with no row, and surfaces any leftover row as an orphan.
 *
 * @since 3.13.0
 */
class RespondentResolver {

	/**
	 * Resolve one context's respondents.
	 *
	 * @since 3.13.0
	 *
	 * @param string $context    Either 'order' or 'rsvp'.
	 * @param int    $context_id Order id or RSVP id.
	 * @param array  $attendees  Ordered list of [ 'attendee_id' => int,
	 *                           'host_row_id' => int ], in host order.
	 *                           attendee_id 0 marks an anonymous attendee;
	 *                           host_row_id is the ticket / guest row it came from.
	 *
	 * @return array{main: array, attendees: array[], orphans: array[]}
	 */
	public static function resolve( $context, $context_id, array $attendees ) {

		$rows = $context === 'rsvp'
			? ResponseRepository::get_for_rsvp( (int) $context_id )
			: ResponseRepository::get_for_order( (int) $context_id );

		$main  = null;
		$pool  = [];
		$taken = [];

		foreach ( $rows as $row ) {

			$key     = (string) $row['attendee_key'];
			$taken[] = $key;

			if ( $key === AnswerRequest::MAIN_KEY ) {
				$main = self::respondent( $key, $row['attendee_id'], $row, 0 );

				continue;
			}

			$pool[ $key ] = $row;
		}

		// Always a respondent, even with nothing stored: a form enabled after
		// checkout has no main row, and the host still needs blank controls. The
		// host decides whether to show it, from the schema's `collect` value.
		if ( $main === null ) {
			$main = self::respondent( AnswerRequest::MAIN_KEY, null, self::empty_row(), 0 );
		}

		// Sort by the numeric suffix, not as strings, so the positional pass below
		// consumes 'a1' before 'a3' (a string sort would put 'a10' before 'a2').
		uksort(
			$pool,
			static function ( $a, $b ) {

				return (int) substr( $a, 1 ) <=> (int) substr( $b, 1 );
			}
		);

		list( $resolved, $pool ) = self::bind_by_identity( $attendees, $pool );

		list( $resolved, $pool ) = self::bind_by_position( $attendees, $resolved, $pool );

		return [
			'main'      => $main,
			'attendees' => self::fill_gaps( $attendees, $resolved, $taken ),
			'orphans'   => self::orphans( $pool ),
		];
	}

	/**
	 * Pass 1 — bind every row that names a real attendee id.
	 *
	 * @since 3.13.0
	 *
	 * @param array $attendees The host's attendee list.
	 * @param array $pool      Unbound rows keyed by attendee key.
	 *
	 * @return array{0: array, 1: array} Respondents by position, and the remaining pool.
	 */
	private static function bind_by_identity( array $attendees, array $pool ) {

		$resolved = [];

		foreach ( $attendees as $position => $attendee ) {

			$attendee_id = (int) $attendee['attendee_id'];

			if ( $attendee_id <= 0 ) {
				continue;
			}

			foreach ( $pool as $key => $row ) {

				if ( (int) $row['attendee_id'] !== $attendee_id ) {
					continue;
				}

				$resolved[ $position ] = self::respondent( $key, $attendee_id, $row, $position, self::host_row_id( $attendee ) );

				unset( $pool[ $key ] );

				break;
			}
		}

		return [ $resolved, $pool ];
	}

	/**
	 * Pass 2 — bind id-less rows to still-unbound attendees, in order.
	 *
	 * Best effort over data that has no better key. Only rows with a NULL
	 * attendee_id are eligible: a row that names an attendee who is not on this
	 * host belongs to nobody here and becomes an orphan instead.
	 *
	 * @since 3.13.0
	 *
	 * @param array $attendees The host's attendee list.
	 * @param array $resolved  Respondents bound so far, keyed by position.
	 * @param array $pool      Remaining rows keyed by attendee key.
	 *
	 * @return array{0: array, 1: array}
	 */
	private static function bind_by_position( array $attendees, array $resolved, array $pool ) {

		foreach ( $attendees as $position => $attendee ) {

			if ( isset( $resolved[ $position ] ) ) {
				continue;
			}

			foreach ( $pool as $key => $row ) {

				if ( $row['attendee_id'] !== null ) {
					continue;
				}

				$resolved[ $position ] = self::respondent(
					$key,
					(int) $attendee['attendee_id'] > 0 ? (int) $attendee['attendee_id'] : null,
					$row,
					$position,
					self::host_row_id( $attendee )
				);

				unset( $pool[ $key ] );

				break;
			}
		}

		return [ $resolved, $pool ];
	}

	/**
	 * Give every attendee with no row a minted key and an empty answer set.
	 *
	 * @since 3.13.0
	 *
	 * @param array    $attendees The host's attendee list.
	 * @param array    $resolved  Respondents bound so far, keyed by position.
	 * @param string[] $taken     Every key already present in this context.
	 *
	 * @return array[] One respondent per attendee, in host order.
	 */
	private static function fill_gaps( array $attendees, array $resolved, array $taken ) {

		$out = [];

		foreach ( $attendees as $position => $attendee ) {

			if ( isset( $resolved[ $position ] ) ) {
				$out[]   = $resolved[ $position ];
				$taken[] = $resolved[ $position ]['attendee_key'];

				continue;
			}

			$key     = self::mint_key( $taken );
			$taken[] = $key;

			// Built through respondent(), like every other path, so the shape has
			// exactly one definition.
			$out[] = self::respondent(
				$key,
				(int) $attendee['attendee_id'] > 0 ? (int) $attendee['attendee_id'] : null,
				self::empty_row(),
				$position,
				self::host_row_id( $attendee )
			);
		}

		return $out;
	}

	/**
	 * A row-shaped array for a respondent that has nothing stored yet.
	 *
	 * `id => 0` is what respondent() reads as "no row" and turns into a null
	 * row_id, which is how the save path knows to insert rather than update.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private static function empty_row() {

		return [
			'id'          => 0,
			'attendee_id' => null,
			'status'      => '',
			'answers'     => [],
		];
	}

	/**
	 * The host row id an attendee entry came from, if the host supplied one.
	 *
	 * @since 3.13.0
	 *
	 * @param array $attendee One entry of the host's attendee list.
	 *
	 * @return int Zero when the host tracks no row id.
	 */
	private static function host_row_id( array $attendee ) {

		return isset( $attendee['host_row_id'] ) ? (int) $attendee['host_row_id'] : 0;
	}

	/**
	 * The lowest 'a{n}' key not already in use, starting at 1.
	 *
	 * Never a0: the RSVP front end reserves it for its hidden template row, and a
	 * row minted under that key is one before-checkout could never have created.
	 *
	 * @since 3.13.0
	 *
	 * @param string[] $taken Keys already in use.
	 *
	 * @return string
	 */
	private static function mint_key( array $taken ) {

		$n = 1;

		while ( in_array( 'a' . $n, $taken, true ) ) {
			++$n;
		}

		return 'a' . $n;
	}

	/**
	 * Rows left unbound: their attendee is gone.
	 *
	 * @since 3.13.0
	 *
	 * @param array $pool Remaining rows keyed by attendee key.
	 *
	 * @return array[]
	 */
	private static function orphans( array $pool ) {

		$out      = [];
		$position = 0;

		foreach ( $pool as $key => $row ) {
			$respondent              = self::respondent( $key, $row['attendee_id'], $row, $position );
			$respondent['is_orphan'] = true;

			$out[] = $respondent;

			++$position;
		}

		return $out;
	}

	/**
	 * Shape one respondent.
	 *
	 * @since 3.13.0
	 *
	 * @param string   $key         Attendee key.
	 * @param int|null $attendee_id Attendee id, or null.
	 * @param array    $row         The stored row, or empty_row() when none exists.
	 * @param int      $position    Zero-based position.
	 * @param int      $host_row_id Ticket / guest row this respondent came from.
	 *
	 * @return array
	 */
	private static function respondent( $key, $attendee_id, array $row, $position, $host_row_id = 0 ) {

		return [
			'attendee_key' => (string) $key,
			'attendee_id'  => $attendee_id === null ? null : (int) $attendee_id,
			'host_row_id'  => (int) $host_row_id,
			'row_id'       => (int) $row['id'] > 0 ? (int) $row['id'] : null,
			'position'     => (int) $position,
			'status'       => (string) $row['status'],
			'answers'      => (array) $row['answers'],
			'is_orphan'    => false,
		];
	}
}
