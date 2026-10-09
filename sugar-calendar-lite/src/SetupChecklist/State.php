<?php

namespace Sugar_Calendar\SetupChecklist;

use WP_Error;

/**
 * Site-local checklist facts and atomic event claims.
 *
 * @since 3.15.0
 */
class State {

	/**
	 * Checklist option name.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar_setup_checklist';

	/**
	 * Read normalized state without initializing or repairing storage.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function get() {

		return $this->normalize( get_option( self::OPTION_NAME, [] ) );
	}

	/**
	 * Read normalized state directly from storage, bypassing option caches.
	 *
	 * @since 3.15.0
	 *
	 * @return array|WP_Error Defaults for an absent option, or an error if the read fails.
	 */
	public function get_fresh() {

		$serialized = $this->read_raw();

		if ( is_wp_error( $serialized ) ) {
			return $serialized;
		}

		return $this->normalize( maybe_unserialize( $serialized ) );
	}

	/**
	 * Read the dismissal record directly from storage, bypassing option caches.
	 *
	 * The read error is returned, not folded into "not dismissed", so each caller decides what a failed read means.
	 *
	 * @since 3.15.0
	 *
	 * @return array|null|WP_Error The dismissal record, null when not dismissed, or the read error.
	 */
	public function get_fresh_dismissal() {

		$state = $this->get_fresh();

		return is_wp_error( $state ) ? $state : $state['dismissal'];
	}

	/**
	 * Whether an item has verified historical evidence.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Canonical item ID.
	 *
	 * @return bool
	 */
	public function has_history( $item_id ) {

		return is_string( $item_id ) && isset( $this->get()['history'][ $item_id ] );
	}

	/**
	 * Whether the checklist has a saved dismissal.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public function is_dismissed() {

		return $this->get()['dismissal'] !== null;
	}

	/**
	 * Whether the wizard recorded an explicit tracking opt-in.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public function has_wizard_opt_in() {

		return $this->get()['opt_in']['wizard'] > 0;
	}

	/**
	 * Whether the one-time saved-page scan for the calendar block has run.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public function has_embed_scan() {

		return $this->get()['embed_scan_time'] > 0;
	}

	/**
	 * Apply a side-effect-free change to fresh state with at most five attempts.
	 *
	 * @since 3.15.0
	 *
	 * @param callable $change Returns an array with state and result; may run repeatedly.
	 *
	 * @return mixed|WP_Error Only the successful attempt's result, or a persistence error.
	 */
	public function mutate( callable $change ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- The bounded CAS operation keeps validation and retry handling together.

		global $wpdb;

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$previous_serialized = $this->read_or_initialize();

			if ( is_wp_error( $previous_serialized ) ) {
				return $previous_serialized;
			}

			$next = $change( $this->normalize( maybe_unserialize( $previous_serialized ) ) );

			if (
				! is_array( $next ) ||
				! isset( $next['state'] ) ||
				! is_array( $next['state'] ) ||
				! array_key_exists( 'result', $next )
			) {
				return new WP_Error( 'checklist_state_invalid_change', __( 'Could not save checklist progress. Please try again.', 'sugar-calendar-lite' ) );
			}

			$next['state'] = $this->normalize( $next['state'] );
			$serialized    = maybe_serialize( $next['state'] );

			if ( is_wp_error( $next['result'] ) || $serialized === $previous_serialized ) {
				return $next['result'];
			}

			// The byte comparison prevents unrelated writes from being overwritten.
			$changed = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic option update; refresh its cache on success.
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = %s",
					$serialized,
					self::OPTION_NAME,
					$previous_serialized
				)
			);

			if ( $changed === false ) {
				return new WP_Error( 'checklist_state_write_failed', __( 'Could not save checklist progress. Please try again.', 'sugar-calendar-lite' ) );
			}

			if ( $changed === 1 ) {
				// The row now holds exactly these bytes, so cache them as update_option() would.
				wp_cache_set( self::OPTION_NAME, $serialized, 'options' );

				return $next['result'];
			}
		}

		return new WP_Error( 'checklist_state_contention', __( 'Checklist progress changed in another request. Please try again.', 'sugar-calendar-lite' ) );
	}

	/**
	 * Record the first verified completion of a historical item.
	 *
	 * Only a row whose catalogue entry declares `history_backed` may be recorded.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Canonical historical item ID.
	 *
	 * @return bool|WP_Error Whether this call created the evidence, or a persistence error.
	 */
	public function record_history( $item_id ) {

		if ( ! in_array( $item_id, Config::get_history_item_ids(), true ) ) {
			return new WP_Error( 'checklist_state_invalid_item', __( 'This checklist item cannot be recorded.', 'sugar-calendar-lite' ) );
		}

		$result = $this->mutate(
			static function ( $state ) use ( $item_id ) {

				$created = ! isset( $state['history'][ $item_id ] );

				if ( $created ) {
					$state['history'][ $item_id ] = time();
				}

				return [
					'state'  => $state,
					'result' => $created,
				];
			}
		);

		if ( $result === true ) {
			$this->notify_changed();
		}

		return $result;
	}

	/**
	 * Record the first explicit tracking opt-in from a supported source.
	 *
	 * @since 3.15.0
	 *
	 * @param string $source Wizard or checklist.
	 *
	 * @return bool|WP_Error Whether this call created the evidence, or a persistence error.
	 */
	public function record_opt_in( $source ) {

		if ( ! in_array( $source, [ 'wizard', 'checklist' ], true ) ) {
			return new WP_Error( 'checklist_state_invalid_source', __( 'This opt-in source cannot be recorded.', 'sugar-calendar-lite' ) );
		}

		$result = $this->mutate(
			static function ( $state ) use ( $source ) {

				$created = $state['opt_in'][ $source ] === 0;

				if ( $created ) {
					$state['opt_in'][ $source ] = time();
				}

				return [
					'state'  => $state,
					'result' => $created,
				];
			}
		);

		if ( $result === true ) {
			$this->notify_changed();
		}

		return $result;
	}

	/**
	 * Record that the one-time saved-page scan for the calendar block ran.
	 *
	 * @since 3.15.0
	 *
	 * @return bool|WP_Error Whether this call recorded the scan, or a persistence error.
	 */
	public function record_embed_scan() {

		return $this->mutate(
			static function ( $state ) {

				$created = $state['embed_scan_time'] === 0;

				if ( $created ) {
					$state['embed_scan_time'] = time();
				}

				return [
					'state'  => $state,
					'result' => $created,
				];
			}
		);
	}

	/**
	 * Announce a successfully persisted historical or source change.
	 *
	 * @since 3.15.0
	 */
	private function notify_changed() {

		/**
		 * Fires after a checklist historical fact or opt-in source is saved.
		 *
		 * @since 3.15.0
		 */
		do_action( 'sugar_calendar_setup_checklist_changed' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.
	}

	/**
	 * Record the first checklist view independently of analytics eligibility.
	 *
	 * @since 3.15.0
	 *
	 * @return bool|WP_Error Whether this call recorded the first view, or a persistence error.
	 */
	public function record_first_seen() {

		return $this->mutate(
			static function ( $state ) {

				$created = $state['first_seen_time'] === 0;

				if ( $created ) {
					$state['first_seen_time'] = time();
				}

				return [
					'state'  => $state,
					'result' => $created,
				];
			}
		);
	}

	/**
	 * Save a server snapshot once, recomputing it on every contention retry.
	 *
	 * @since 3.15.0
	 *
	 * @param callable $snapshot Accepts fresh state and returns fresh visible/completed item lists.
	 *
	 * @return array|WP_Error Created flag and persisted dismissal, or a persistence error.
	 */
	public function dismiss( callable $snapshot ) {

		return $this->mutate(
			function ( $state ) use ( $snapshot ) {

				if ( $state['dismissal'] !== null ) {
					return [
						'state'  => $state,
						'result' => [
							'created'   => false,
							'dismissal' => $state['dismissal'],
						],
					];
				}

				$current   = $snapshot( $state );
				$dismissal = $this->build_dismissal( $current );

				if ( $dismissal instanceof WP_Error ) {
					return [
						'state'  => $state,
						'result' => $dismissal,
					];
				}

				$state['dismissal'] = $dismissal;

				return [
					'state'  => $state,
					'result' => [
						'created'   => true,
						'dismissal' => $state['dismissal'],
					],
				];
			}
		);
	}

	/**
	 * Build a dismissal record from a valid server snapshot.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $current Server snapshot or error.
	 *
	 * @return array|WP_Error Dismissal record or snapshot error.
	 */
	private function build_dismissal( $current ) {

		if ( is_wp_error( $current ) ) {
			return $current;
		}

		if (
			! is_array( $current ) ||
			! isset( $current['visible_items'], $current['completed_items'] ) ||
			! is_array( $current['visible_items'] ) ||
			! is_array( $current['completed_items'] )
		) {
			return new WP_Error( 'checklist_state_invalid_snapshot', __( 'Could not dismiss the checklist. Please try again.', 'sugar-calendar-lite' ) );
		}

		$visible   = $this->normalize_items( $current['visible_items'] );
		$completed = array_values( array_intersect( $visible, $this->normalize_items( $current['completed_items'] ) ) );

		return [
			'time'            => time(),
			'completed_items' => $completed,
			'visible_items'   => $visible,
			'percent'         => count( $visible ) > 0 ? (int) round( 100 * count( $completed ) / count( $visible ) ) : 0,
		];
	}

	/**
	 * Read option bytes, initializing an absent row only for mutations.
	 *
	 * @since 3.15.0
	 *
	 * @return string|WP_Error
	 */
	private function read_or_initialize() {

		$serialized = $this->read_raw();

		if ( $serialized === null ) {
			add_option( self::OPTION_NAME, $this->get_defaults(), '', false );
			$serialized = $this->read_raw();
		}

		if ( $serialized === null ) {
			return new WP_Error( 'checklist_state_initialize_failed', __( 'Could not save checklist progress. Please try again.', 'sugar-calendar-lite' ) );
		}

		return $serialized;
	}

	/**
	 * Read exact option bytes without conflating an empty value with an absent row.
	 *
	 * @since 3.15.0
	 *
	 * @return string|null|WP_Error
	 */
	private function read_raw() {

		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-swap requires uncached stored bytes.
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION_NAME ),
			ARRAY_A
		);

		if ( $wpdb->last_error !== '' ) {
			return new WP_Error( 'checklist_state_read_failed', __( 'Could not read checklist progress. Please try again.', 'sugar-calendar-lite' ) );
		}

		return $row === null ? null : $row['option_value'];
	}

	/**
	 * Default state for one site.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function get_defaults() {

		return [
			'history'         => [],
			'opt_in'          => [
				'wizard'    => 0,
				'checklist' => 0,
			],
			'first_seen_time' => 0,
			'embed_scan_time' => 0,
			'dismissal'       => null,
			'observed'        => null,
			'viewed_day'      => '',
		];
	}

	/**
	 * Normalize known fields without writing or consulting the current catalogue.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Stored state.
	 *
	 * @return array
	 */
	private function normalize( $value ) {

		$state = $this->get_defaults();

		if ( ! is_array( $value ) ) {
			return $state;
		}

		$value  = array_merge( $state, $value );
		$opt_in = is_array( $value['opt_in'] ) ? $value['opt_in'] : [];

		foreach ( [ 'wizard', 'checklist' ] as $source ) {
			$state['opt_in'][ $source ] = $this->normalize_time( $opt_in[ $source ] ?? 0 );
		}

		$state['history']         = $this->normalize_history( $value['history'] );
		$state['first_seen_time'] = $this->normalize_time( $value['first_seen_time'] );
		$state['embed_scan_time'] = $this->normalize_time( $value['embed_scan_time'] );
		$state['dismissal']       = $this->normalize_dismissal( $value['dismissal'] );
		$state['observed']        = $this->normalize_observed( $value['observed'] );
		$day                      = $value['viewed_day'];

		if (
			is_string( $day ) &&
			preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $day, $parts ) &&
			checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] )
		) {
			$state['viewed_day'] = $day;
		}

		return $state;
	}

	/**
	 * Keep every valid first-evidence timestamp under a canonical slug.
	 *
	 * Not filtered by the running catalogue: that would erase a Pro row's evidence on the next Lite write.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $history Stored history.
	 *
	 * @return array
	 */
	private function normalize_history( $history ) {

		$normalized = [];

		if ( ! is_array( $history ) ) {
			return $normalized;
		}

		foreach ( $history as $item_id => $time ) {
			$timestamp = $this->normalize_time( $time );

			if ( $timestamp > 0 && $this->normalize_items( [ $item_id ] ) ) {
				$normalized[ $item_id ] = $timestamp;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize a saved dismissal independently of current item visibility.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $dismissal Stored dismissal.
	 *
	 * @return array|null
	 */
	private function normalize_dismissal( $dismissal ) {

		if (
			is_array( $dismissal ) &&
			isset( $dismissal['time'], $dismissal['completed_items'], $dismissal['visible_items'], $dismissal['percent'] ) &&
			is_array( $dismissal['completed_items'] ) &&
			is_array( $dismissal['visible_items'] )
		) {
			$timestamp = $this->normalize_time( $dismissal['time'] );
			$percent   = $this->normalize_time( $dismissal['percent'] );

			if (
				$timestamp > 0 &&
				$percent <= 100 &&
				( is_int( $dismissal['percent'] ) || is_string( $dismissal['percent'] ) ) &&
				(string) $percent === (string) $dismissal['percent']
			) {
				$visible = $this->normalize_items( $dismissal['visible_items'] );

				return [
					'time'            => $timestamp,
					'completed_items' => array_values( array_intersect( $visible, $this->normalize_items( $dismissal['completed_items'] ) ) ),
					'visible_items'   => $visible,
					'percent'         => $percent,
				];
			}
		}

		return null;
	}

	/**
	 * Normalize a saved observation without manufacturing item transitions.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $observed Stored observation.
	 *
	 * @return array|null
	 */
	private function normalize_observed( $observed ) {

		if (
			is_array( $observed ) &&
			isset( $observed['enabled'], $observed['items'], $observed['all_complete'] ) &&
			is_bool( $observed['enabled'] ) &&
			is_array( $observed['items'] ) &&
			is_bool( $observed['all_complete'] )
		) {
			$items = [];

			foreach ( $observed['items'] as $item_id => $complete ) {
				if ( $this->normalize_items( [ $item_id ] ) && is_bool( $complete ) ) {
					$items[ $item_id ] = $complete;
				}
			}

			return [
				'enabled'      => $observed['enabled'],
				'items'        => $items,
				'all_complete' => $observed['all_complete'],
			];
		}

		return null;
	}

	/**
	 * Normalize a nonnegative integer timestamp.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Stored timestamp.
	 *
	 * @return int
	 */
	private function normalize_time( $value ) {

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return 0;
		}

		$timestamp = filter_var( $value, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0 ] ] );

		return $timestamp === false ? 0 : $timestamp;
	}

	/**
	 * Keep distinct canonical slugs in snapshot order across edition changes.
	 *
	 * @since 3.15.0
	 *
	 * @param array $items Item IDs.
	 *
	 * @return array
	 */
	private function normalize_items( array $items ) {

		return array_values(
			array_unique(
				array_filter(
					$items,
					static function ( $item_id ) {

						return is_string( $item_id ) && preg_match( '/^[a-z][a-z0-9-]*$/D', $item_id );
					}
				)
			)
		);
	}
}
