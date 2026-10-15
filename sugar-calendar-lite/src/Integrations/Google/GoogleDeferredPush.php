<?php

namespace Sugar_Calendar\Integrations\Google;

use WP_Post;

/**
 * Work collected during a request and pushed once on shutdown: Google deletes and series cuts.
 *
 * @since 3.16.0
 */
class GoogleDeferredPush {

	/**
	 * Pusher.
	 *
	 * @since 3.16.0
	 *
	 * @var GooglePusher
	 */
	private $pusher;

	/**
	 * Event builder, whose per-request cache a cut can outdate.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleEventBuilder
	 */
	private $builder;

	/**
	 * Deletes collected during this request, flushed once on shutdown.
	 *
	 * @since 3.16.0
	 *
	 * @var array
	 */
	private $pending_deletes = [];

	/**
	 * Series cut during this request, keyed by post id, pushed once on shutdown.
	 *
	 * @since 3.16.0
	 *
	 * @var array[] Each [ 'series' => old SC event id, 'tail' => new series' SC event id or 0 ].
	 */
	private $pending_cuts = [];

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param GooglePusher       $pusher  Pusher.
	 * @param GoogleEventBuilder $builder Event builder, whose per-request cache a cut can outdate.
	 */
	public function __construct( GooglePusher $pusher, GoogleEventBuilder $builder ) {

		$this->pusher  = $pusher;
		$this->builder = $builder;
	}

	/**
	 * Queue one Google delete; the HTTP call waits for shutdown so a bulk delete is one relay call.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id     Google calendar id.
	 * @param string $google_event_id Google event id.
	 *
	 * @return void
	 */
	public function queue_delete( string $calendar_id, string $google_event_id ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if ( $this->pending_deletes === [] ) {
			add_action( 'shutdown', [ $this, 'flush_deletes' ] );
		}

		$this->pending_deletes[] = [
			'calendarId' => $calendar_id,
			'eventId'    => $google_event_id,
		];
	}

	/**
	 * Queue a series cut for a push on shutdown, once Pro has moved the dates to the tail series.
	 *
	 * @since 3.16.0
	 *
	 * @param int $post_id   The cut series' post id.
	 * @param int $series_id The cut series' SC event id.
	 * @param int $tail_id   The tail series' SC event id, or 0.
	 *
	 * @return void
	 */
	public function queue_cut( int $post_id, int $series_id, int $tail_id ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if ( $this->pending_cuts === [] ) {
			add_action( 'shutdown', [ $this, 'flush_cuts' ] );
		}

		$this->pending_cuts[ $post_id ] = [
			'series' => $series_id,
			'tail'   => $tail_id,
		];
	}

	/**
	 * Push each series cut during this request.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function flush_cuts() {

		$cuts               = $this->pending_cuts;
		$this->pending_cuts = [];

		// The builder's skipped-dates cache may predate the cut.
		$this->builder->forget_skipped();

		foreach ( $cuts as $post_id => $cut ) {
			$post = get_post( $post_id );

			if ( $post instanceof WP_Post ) {
				$this->pusher->push( $post_id, $post );
			}

			$this->push_tail( $cut );
		}
	}

	/**
	 * Give the tail series the cut series' Google Meet and push it.
	 *
	 * @since 3.16.0
	 *
	 * @param array $cut Queued cut: [ 'series' => SC event id, 'tail' => SC event id or 0 ].
	 *
	 * @return void
	 */
	private function push_tail( array $cut ) {

		$tail      = $cut['tail'] ? sugar_calendar_get_event( $cut['tail'] ) : null;
		$tail_post = ! empty( $tail->object_id ) ? get_post( (int) $tail->object_id ) : null;

		// The tail series keeps the cut series' Google Meet, as a single split date does.
		if ( $tail_post instanceof WP_Post && $this->pusher->copy_series_meet( $cut['series'], $tail ) ) {
			$this->pusher->push( $tail_post->ID, $tail_post );
		}
	}

	/**
	 * Send the queued deletes.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function flush_deletes() {

		if ( $this->pending_deletes === [] ) {
			return;
		}

		$pairs                 = $this->pending_deletes;
		$this->pending_deletes = [];

		$this->pusher->delete_pairs( $pairs );
	}
}
