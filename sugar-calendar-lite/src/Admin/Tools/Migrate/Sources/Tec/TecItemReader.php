<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecAttendee;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecEvent;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecOrder;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecTicket;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers\EventMapper;
use Sugar_Calendar\Helpers\TimezoneNames;

/**
 * Reads one TEC item from its post, meta and term rows, without TEC loaded.
 *
 * @since 3.18.0
 */
class TecItemReader {

	/**
	 * A TEC event with the fields its meta holds.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_event_post_id The TEC event post ID.
	 *
	 * @return TecEvent|false
	 */
	public function get_event( $tec_event_post_id ) {

		$post = get_post( $tec_event_post_id );

		if ( empty( $post ) ) {
			return false;
		}

		$meta    = get_post_meta( $tec_event_post_id );
		$all_day = (bool) self::get_meta( '_EventAllDay', $meta );

		[ $start, $end ] = EventMapper::get_all_day_dates( self::get_meta( '_EventStartDate', $meta ), self::get_meta( '_EventEndDate', $meta ), $all_day );

		return new TecEvent(
			$post,
			$meta,
			[
				'start_date' => $start,
				'end_date'   => $end,
				'timezone'   => TimezoneNames::get_current( self::get_meta( '_EventTimezone', $meta ) ),
				'all_day'    => $all_day,
				'url'        => self::get_meta( '_EventURL', $meta ),
				'recurrence' => self::get_recurrence( self::get_meta( '_EventRecurrence', $meta ) ),
			]
		);
	}

	/**
	 * The TEC repeat rules, unserialized.
	 *
	 * @since 3.18.0
	 *
	 * @param mixed $recurrence The stored `_EventRecurrence` value.
	 *
	 * @return array
	 */
	private static function get_recurrence( $recurrence ) {

		if ( empty( $recurrence ) || ! is_serialized( $recurrence ) ) {
			return [];
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		$recurrence = unserialize( $recurrence, [ 'allowed_classes' => false ] );

		return is_array( $recurrence ) ? $recurrence : [];
	}

	/**
	 * The address of a TEC event's venue, for Lite's location field.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_event_post_id The TEC event post ID.
	 *
	 * @return string|false False when the event has no venue.
	 */
	public function get_location( $tec_event_post_id ) {

		$tec_venue_id = get_post_meta( $tec_event_post_id, '_EventVenueID', true );
		$tec_venue    = empty( $tec_venue_id ) ? null : get_post( $tec_venue_id );

		if ( empty( $tec_venue ) || $tec_venue->post_type !== 'tribe_venue' ) {
			return false;
		}

		$parts = [ $tec_venue->post_title ];
		$meta  = get_post_meta( $tec_venue_id );

		foreach ( [ '_VenueAddress', '_VenueCity', '_VenueProvince', '_VenueState', '_VenueZip', '_VenueCountry' ] as $key ) {
			$value = self::get_meta( $key, $meta );

			if ( ! empty( $value ) ) {
				$parts[] = $value;
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * One TEC term, read without the taxonomy being registered.
	 *
	 * @since 3.18.0
	 *
	 * @param int $term_id The TEC term ID.
	 *
	 * @return array|false The term in `get_term()`'s ARRAY_A shape, or false when it is gone.
	 */
	public function get_term( $term_id ) {

		global $wpdb;

		$term = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prepare(
				"SELECT t.*, tt.description, tt.parent
				FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE t.term_id = %d",
				$term_id
			),
			ARRAY_A
		);

		if ( empty( $term ) ) {
			return false;
		}

		return [
			'term_id'     => (int) $term['term_id'],
			'name'        => $term['name'],
			'slug'        => $term['slug'],
			'term_group'  => (int) $term['term_group'],
			'description' => $term['description'],
			'parent'      => (int) $term['parent'],
		];
	}

	/**
	 * The term IDs of a TEC post in one taxonomy, read without the taxonomy being registered.
	 *
	 * @since 3.18.0
	 *
	 * @param int    $post_id  The TEC post ID.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return int[]
	 */
	public function get_post_term_ids( $post_id, $taxonomy ) {

		global $wpdb;

		$term_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			$wpdb->prepare(
				"SELECT t.term_id
				FROM {$wpdb->term_relationships} AS tr
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
				WHERE tr.object_id = %d
				AND tt.taxonomy = %s",
				$post_id,
				$taxonomy
			)
		);

		return array_map( 'intval', $term_ids );
	}

	/**
	 * A TEC ticket's price and capacity.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_ticket_id The TEC ticket post ID.
	 *
	 * @return TecTicket|false
	 */
	public function get_ticket( $tec_ticket_id ) {

		$price = get_post_meta( $tec_ticket_id, '_price', true );

		if ( $price === false ) {
			return false;
		}

		return new TecTicket( $tec_ticket_id, $price, get_post_meta( $tec_ticket_id, '_tribe_ticket_capacity', true ) );
	}

	/**
	 * A TEC order's buyer, money, event and status.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_order_id The TEC order post ID.
	 *
	 * @return TecOrder|false
	 */
	public function get_order( $tec_order_id ) {

		$meta = get_post_meta( $tec_order_id );

		if ( empty( $meta ) ) {
			return false;
		}

		return new TecOrder(
			$tec_order_id,
			[
				'currency'             => self::get_meta( '_tec_tc_order_currency', $meta ),
				'events_in_order'      => self::get_meta( '_tec_tc_order_events_in_order', $meta ),
				'gateway_order_id'     => self::get_meta( '_tec_tc_order_gateway_order_id', $meta ),
				'purchaser_email'      => self::get_meta( '_tec_tc_order_purchaser_email', $meta ),
				'purchaser_first_name' => self::get_meta( '_tec_tc_order_purchaser_first_name', $meta ),
				'purchaser_last_name'  => self::get_meta( '_tec_tc_order_purchaser_last_name', $meta ),
				'purchase_time'        => get_post_time( 'Y-m-d H:i:s', false, $tec_order_id ),
				// TEC order status is derived from its post status.
				'status_slug'          => str_replace( 'tec-tc-', '', get_post_status( $tec_order_id ) ),
				'total'                => self::get_meta( '_tec_tc_order_total_value', $meta ),
			]
		);
	}

	/**
	 * The first value of a meta key in a `get_post_meta()` result.
	 *
	 * @since 3.18.0
	 *
	 * @param string $key  The meta key.
	 * @param array  $meta All of a post's meta.
	 *
	 * @return mixed False when the key is missing.
	 */
	private static function get_meta( $key, $meta ) {

		return $meta[ $key ][0] ?? false;
	}

	/**
	 * A TEC attendee's holder, order, ticket, event and RSVP answer.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_attendee_id The TEC attendee post ID.
	 *
	 * @return TecAttendee
	 */
	public function get_attendee( $tec_attendee_id ) {

		$post_parent = get_post_parent( $tec_attendee_id );

		return new TecAttendee(
			$tec_attendee_id,
			[
				'holder_email'  => self::get_text_meta( $tec_attendee_id, '_tec_tickets_commerce_email' ),
				'holder_name'   => self::get_text_meta( $tec_attendee_id, '_tec_tickets_commerce_full_name' ),
				'order_id'      => empty( $post_parent ) ? 0 : $post_parent->ID,
				'ticket_id'     => absint( get_post_meta( $tec_attendee_id, '_tec_tickets_commerce_ticket', true ) ),
				'event_post_id' => absint( get_post_meta( $tec_attendee_id, '_tec_tickets_commerce_event', true ) ),
				'rsvp_status'   => get_post_meta( $tec_attendee_id, '_tec_tickets_commerce_rsvp_status', true ),
			]
		);
	}

	/**
	 * A single post meta value, or an empty string when it is empty.
	 *
	 * @since 3.18.0
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key.
	 *
	 * @return mixed
	 */
	private static function get_text_meta( $post_id, $key ) {

		$value = get_post_meta( $post_id, $key, true );

		return empty( $value ) ? '' : $value;
	}

	/**
	 * Check if a TEC ticket is an RSVP.
	 *
	 * Since Event Tickets 5.30, an RSVP is a Tickets Commerce ticket of type `tc-rsvp`.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_ticket_id TEC ticket post ID.
	 *
	 * @return bool
	 */
	public function is_rsvp_ticket( $tec_ticket_id ) {

		return get_post_meta( $tec_ticket_id, '_type', true ) === 'tc-rsvp';
	}

	/**
	 * Check if a TEC event sells tickets: it has a ticket that is not an RSVP.
	 *
	 * A ticket without `_type` counts as a regular ticket, the way Event Tickets reads it.
	 *
	 * @since 3.18.0
	 *
	 * @param int $tec_event_post_id TEC event post ID.
	 *
	 * @return bool
	 */
	public function has_ticket_sales( $tec_event_post_id ) {

		$ticket_ids = get_posts(
			[
				'post_type'   => 'tec_tc_ticket',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => '_tec_tickets_commerce_event',
						'value' => $tec_event_post_id,
					],
					[
						'relation' => 'OR',
						[
							'key'     => '_type',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => '_type',
							'value'   => 'tc-rsvp',
							'compare' => '!=',
						],
					],
				],
			]
		);

		return ! empty( $ticket_ids );
	}

	/**
	 * Check if the site used TEC's Community Events add-on.
	 *
	 * True while the add-on is active, and after it is removed when any TEC event
	 * came in through it: the add-on saves those with `_EventOrigin` set to `community-events`.
	 * Submitted events are often pending or drafts, so every status counts.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function has_community_events() {

		if ( class_exists( 'Tribe__Events__Community__Main' ) ) {
			return true;
		}

		$event_ids = get_posts(
			[
				'post_type'   => 'tribe_events',
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
				'orderby'     => 'none',
				'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => '_EventOrigin',
						'value' => 'community-events',
					],
				],
			]
		);

		return ! empty( $event_ids );
	}
}
