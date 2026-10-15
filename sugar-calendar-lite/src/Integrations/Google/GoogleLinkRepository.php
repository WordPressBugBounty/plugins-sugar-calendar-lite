<?php

namespace Sugar_Calendar\Integrations\Google;

/**
 * Stores which Google event matches which Sugar Calendar event, per calendar.
 *
 * @since 3.16.0
 */
class GoogleLinkRepository {

	/**
	 * Event-meta key: array keyed by Google calendar id of [ event_id, etag, hash ].
	 *
	 * Written directly and never registered: the registered-meta copy helper would carry
	 * the link onto duplicates.
	 *
	 * @since 3.16.0
	 *
	 * @var string
	 */
	const META_KEY = 'google_calendar_sync';

	/**
	 * Flat event-meta key, one row per link, holding the plain Google event id for reverse lookup.
	 *
	 * @since 3.16.0
	 *
	 * @var string
	 */
	const GOOGLE_ID_META_KEY = 'google_event_id';

	/**
	 * Stored links for an event, keyed by Google calendar id.
	 *
	 * @since 3.16.0
	 *
	 * @param int $event_id SC event id.
	 *
	 * @return array
	 */
	public function get_links( $event_id ): array {

		$links = get_event_meta( $event_id, self::META_KEY, true );

		if ( ! is_array( $links ) ) {
			return [];
		}

		return array_map( [ $this, 'make_link' ], array_filter( $links, 'is_array' ) );
	}

	/**
	 * Return a link with every key present: event_id, etag, hash, updated, rule.
	 *
	 * @since 3.16.0
	 *
	 * @param array $args Values by key. Unknown keys are dropped, missing keys become ''.
	 *
	 * @return array
	 */
	public function make_link( array $args ): array {

		$defaults = [
			'event_id' => '',
			'etag'     => '',
			'hash'     => '',
			'updated'  => '',
			'rule'     => '',
		];

		return array_merge( $defaults, array_intersect_key( $args, $defaults ) );
	}

	/**
	 * The SC event holding this Google event id in this calendar, or 0.
	 *
	 * Google event ids are unique per calendar, not per account, so the calendar confirms the match.
	 *
	 * @since 3.16.0
	 *
	 * @param string $google_event_id Google event id.
	 * @param string $calendar_id     Google calendar id.
	 *
	 * @return int
	 */
	public function find_event_by_google_id( string $google_event_id, string $calendar_id ): int {

		global $wpdb;

		if ( $google_event_id === '' ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT sc_event_id FROM {$wpdb->prefix}sc_eventmeta WHERE meta_key = %s AND meta_value = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::GOOGLE_ID_META_KEY,
				$google_event_id
			)
		);

		foreach ( (array) $ids as $id ) {
			$links  = $this->get_links( (int) $id );
			$linked = (string) ( $links[ $calendar_id ]['event_id'] ?? '' );

			if ( $linked === $google_event_id ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/**
	 * The instance-id suffixes of the moved dates held by untrashed events under this Google series id.
	 *
	 * Series ids are unique per calendar, so the prefix already names the calendar.
	 *
	 * @since 3.16.0
	 *
	 * @param string $series_id Google series id.
	 *
	 * @return string[] Suffixes: Ymd (all-day) or Ymd\THis\Z (UTC).
	 */
	public function moved_instance_dates( string $series_id ): array {

		global $wpdb;

		if ( $series_id === '' ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.meta_value FROM {$wpdb->prefix}sc_eventmeta m INNER JOIN {$wpdb->prefix}sc_events e ON e.id = m.sc_event_id WHERE m.meta_key = %s AND m.meta_value LIKE %s AND e.status != 'trash'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::GOOGLE_ID_META_KEY,
				$wpdb->esc_like( $series_id . '_' ) . '%'
			)
		);

		$suffixes = [];

		foreach ( (array) $ids as $id ) {
			$suffix = substr( (string) $id, strlen( $series_id ) + 1 );

			// Google's own split series are <id>_R...; only date stamps are moved instances.
			if ( preg_match( '/^\d{8}(T\d{6}Z)?$/', $suffix ) ) {
				$suffixes[] = $suffix;
			}
		}

		return array_values( array_unique( $suffixes ) );
	}

	/**
	 * Write one link entry.
	 * Idempotent: the flat google_event_id row is added once and replaced when the id changes.
	 *
	 * @since 3.16.0
	 *
	 * @param int    $event_id    SC event id.
	 * @param string $calendar_id Google calendar id.
	 * @param array  $link        See make_link(): 'event_id', 'etag', 'hash', plus 'updated' (Google's last-modified
	 *                            stamp; empty or missing keeps the stored one) and 'rule' (who owns the repeat rule:
	 *                            'pushed', 'google' or ''; missing or null keeps the stored one).
	 *
	 * @return void
	 */
	public function store_link( $event_id, string $calendar_id, array $link ) {

		$links  = $this->get_links( $event_id );
		$stored = $this->make_link( $links[ $calendar_id ] ?? [] );
		$old    = (string) $stored['event_id'];

		// An empty 'updated' and a missing 'rule' keep the stored values.
		$link['updated'] = empty( $link['updated'] ) ? $stored['updated'] : (string) $link['updated'];
		$link['rule']    = (string) ( $link['rule'] ?? $stored['rule'] );

		$links[ $calendar_id ] = $this->make_link( $link );
		$google_event_id       = (string) $links[ $calendar_id ]['event_id'];

		update_event_meta( $event_id, self::META_KEY, $links );

		// The flat row is an index: written once per Google id, replaced when the id changes.
		if ( $old !== '' && $old !== $google_event_id ) {
			delete_event_meta( $event_id, self::GOOGLE_ID_META_KEY, $old );
		}

		$flat = (array) get_event_meta( $event_id, self::GOOGLE_ID_META_KEY, false );

		if ( ! in_array( $google_event_id, $flat, true ) ) {
			add_event_meta( $event_id, self::GOOGLE_ID_META_KEY, $google_event_id );
		}
	}

	/**
	 * Drop one link entry.
	 *
	 * @since 3.16.0
	 *
	 * @param int    $event_id    SC event id.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return void
	 */
	public function remove_link( $event_id, string $calendar_id ) {

		$links = $this->get_links( $event_id );
		$old   = (string) ( $links[ $calendar_id ]['event_id'] ?? '' );

		unset( $links[ $calendar_id ] );

		if ( $old !== '' ) {
			delete_event_meta( $event_id, self::GOOGLE_ID_META_KEY, $old );
		}

		if ( $links === [] ) {
			delete_event_meta( $event_id, self::META_KEY );

			return;
		}

		update_event_meta( $event_id, self::META_KEY, $links );
	}
}
