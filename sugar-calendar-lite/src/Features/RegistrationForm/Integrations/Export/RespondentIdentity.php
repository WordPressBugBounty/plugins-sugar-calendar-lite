<?php
/**
 * Name and email for exported registration responses.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Features\RegistrationForm\Integrations\Export;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Features\RegistrationForm\Frontend\AnswerRequest;
use Sugar_Calendar\Features\RegistrationForm\RespondentNaming;
use Sugar_Calendar_Rsvp\Model\RsvpAttendee;

/**
 * RespondentIdentity — resolves who answered, one query per source.
 *
 * A response row points at its respondent indirectly: the ticketing purchaser
 * lives on the order, a ticket holder in sc_attendees, an RSVP guest in the
 * add-on's attendee table. An anonymous ticket attendee (a{n} key, NULL
 * attendee_id) has no identity anywhere and stays blank.
 *
 * @since 3.14.0
 */
class RespondentIdentity {

	/**
	 * Resolve name and email for every row.
	 *
	 * @since 3.14.0
	 *
	 * @param array[] $rows Hydrated response rows (ResponseRepository shape).
	 *
	 * @return array<int, array{name: string, email: string}> Keyed by response id.
	 */
	public static function resolve( array $rows ) {

		$lookup_ids = [
			'rsvp'      => [],
			'ticket'    => [],
			'purchaser' => [],
		];

		$classifications = [];

		foreach ( $rows as $row ) {
			$classified = self::classify( $row );

			$classifications[ (int) $row['id'] ] = $classified;

			if ( isset( $lookup_ids[ $classified['source'] ] ) ) {
				$lookup_ids[ $classified['source'] ][] = $classified['lookup_id'];
			}
		}

		$blank = [
			'name'  => '',
			'email' => '',
		];

		$identities_by_source = [
			'rsvp'      => self::rsvp_attendees( $lookup_ids['rsvp'] ),
			'ticket'    => self::ticket_attendees( $lookup_ids['ticket'] ),
			'purchaser' => self::purchasers( $lookup_ids['purchaser'] ),
		];

		$out = [];

		foreach ( $classifications as $response_id => $classified ) {
			$out[ $response_id ] = $identities_by_source[ $classified['source'] ][ $classified['lookup_id'] ]
				?? $blank;
		}

		return $out;
	}

	/**
	 * Classify a row's respondent kind and the id to look it up by.
	 *
	 * Single source of truth for the two `resolve()` passes, so id-collection
	 * and identity-emission can't silently diverge on a future edit.
	 *
	 * @since 3.14.0
	 *
	 * @param array $row Hydrated response row (ResponseRepository shape).
	 *
	 * @return array{source: string, lookup_id: int} `source` is one of
	 *                                                'rsvp'|'ticket'|'purchaser'|''.
	 */
	private static function classify( array $row ) {

		if ( $row['context'] === 'rsvp' ) {
			return [
				'source'    => 'rsvp',
				'lookup_id' => (int) $row['attendee_id'],
			];
		}

		if ( $row['context'] === 'order' ) {
			if ( ! empty( $row['attendee_id'] ) ) {
				return [
					'source'    => 'ticket',
					'lookup_id' => (int) $row['attendee_id'],
				];
			}

			if ( $row['attendee_key'] === AnswerRequest::MAIN_KEY ) {
				return [
					'source'    => 'purchaser',
					'lookup_id' => (int) $row['context_id'],
				];
			}
		}

		return [
			'source'    => '',
			'lookup_id' => 0,
		];
	}

	/**
	 * Purchasers by order id.
	 *
	 * @since 3.14.0
	 *
	 * @param int[] $order_ids Order ids.
	 *
	 * @return array<int, array{name: string, email: string}>
	 */
	private static function purchasers( array $order_ids ) {

		global $wpdb;

		$out = [];

		foreach ( self::query( $wpdb->prefix . 'sc_orders', 'id, first_name, last_name, email', $order_ids ) as $order ) {
			$out[ (int) $order->id ] = [
				'name'  => RespondentNaming::purchaser( $order ),
				'email' => (string) $order->email,
			];
		}

		return $out;
	}

	/**
	 * Ticket holders by sc_attendees id.
	 *
	 * @since 3.14.0
	 *
	 * @param int[] $attendee_ids Attendee ids.
	 *
	 * @return array<int, array{name: string, email: string}>
	 */
	private static function ticket_attendees( array $attendee_ids ) {

		global $wpdb;

		$out = [];

		foreach ( self::query( $wpdb->prefix . 'sc_attendees', 'id, first_name, last_name, email', $attendee_ids ) as $attendee ) {
			$out[ (int) $attendee->id ] = [
				'name'  => trim( (string) $attendee->first_name . ' ' . (string) $attendee->last_name ),
				'email' => (string) $attendee->email,
			];
		}

		return $out;
	}

	/**
	 * RSVP guests by sc_rsvp_attendees id. Empty when sc-rsvp is not active.
	 *
	 * @since 3.14.0
	 *
	 * @param int[] $attendee_ids RSVP attendee ids.
	 *
	 * @return array<int, array{name: string, email: string}>
	 */
	private static function rsvp_attendees( array $attendee_ids ) {

		if ( ! class_exists( RsvpAttendee::class ) ) {
			return [];
		}

		$out = [];

		foreach ( self::query( RsvpAttendee::get_table_name(), 'id, name, email', $attendee_ids ) as $guest ) {
			$out[ (int) $guest->id ] = [
				'name'  => trim( (string) $guest->name ),
				'email' => (string) $guest->email,
			];
		}

		return $out;
	}

	/**
	 * One `WHERE id IN (…)` read.
	 *
	 * @since 3.14.0
	 *
	 * @param string $table   Fully prefixed table name, never from input.
	 * @param string $columns Comma-separated column list, never from input.
	 * @param int[]  $ids     Ids to fetch.
	 *
	 * @return object[]
	 */
	private static function query( $table, $columns, array $ids ) {

		global $wpdb;

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		if ( empty( $ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT {$columns} FROM {$table} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$ids
			)
		);

		return (array) $rows;
	}
}
