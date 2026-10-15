<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Integrations\EventSaveGuard;
use Throwable;
use WP_Post;

/**
 * Listens for the saves, deletes and series edits that must reach Google, and hands them to the pusher or the shutdown queue.
 *
 * Trash and restore change nothing in Google; only a permanent delete removes the copy.
 * A Google failure never blocks the save or the delete.
 *
 * @since 3.16.0
 */
class GooglePushListener {

	/**
	 * Pusher.
	 *
	 * @since 3.16.0
	 *
	 * @var GooglePusher
	 */
	private $pusher;

	/**
	 * Shutdown queue.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleDeferredPush
	 */
	private $queue;

	/**
	 * Link storage.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleLinkRepository
	 */
	private $links;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param GooglePusher         $pusher Pusher.
	 * @param GoogleDeferredPush   $queue  Shutdown queue.
	 * @param GoogleLinkRepository $links  Link storage.
	 */
	public function __construct( GooglePusher $pusher, GoogleDeferredPush $queue, GoogleLinkRepository $links ) {

		$this->pusher = $pusher;
		$this->queue  = $queue;
		$this->links  = $links;
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.16.0
	 */
	public function hooks() {

		add_action( 'save_post', [ $this, 'sync' ], 20, 2 );
		add_action( 'publish_future_post', [ $this, 'publish_scheduled' ], 10, 1 );
		add_action( 'before_delete_post', [ $this, 'cleanup' ], 10, 1 );
		add_action( 'sugar_calendar_online_meeting_changed', [ $this, 'on_meeting_changed' ], 10, 1 );
		add_action( 'sugar_calendar_pro_features_advanced_recurring_loader_create_standalone_event', [ $this, 'on_occurrence_split' ], 10, 2 );
		add_action( 'sugar_calendar_pro_features_advanced_recurring_event_sequence_trash_single_occurrence', [ $this, 'on_occurrence_removed' ], 10, 1 );
		add_action( 'sugar_calendar_pro_features_advanced_recurring_event_sequence_series_cut', [ $this, 'on_series_cut' ], 10, 2 );
	}

	/**
	 * Push on a genuine editor save.
	 *
	 * @since 3.16.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function sync( $post_id, $post ) {

		if ( ! EventSaveGuard::is_editor_save( $post_id, $post ) ) {
			return;
		}

		$this->pusher->push( $post_id, $post );
	}

	/**
	 * Push when cron publishes a scheduled event (no editor nonce on that save).
	 *
	 * @since 3.16.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function publish_scheduled( $post_id ) {

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! post_type_supports( $post->post_type, 'events' ) ) {
			return;
		}

		$this->pusher->push( $post_id, $post );
	}

	/**
	 * Re-push an event whose online meeting a person just created, removed or switched.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return void
	 */
	public function on_meeting_changed( $event ) {

		$post = ! empty( $event->object_id ) ? get_post( (int) $event->object_id ) : null;

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$this->pusher->push( (int) $post->ID, $post );
	}

	/**
	 * A date split off a series keeps the series' Google Meet; then both go to Google.
	 *
	 * @since 3.16.0
	 *
	 * @param object $occurrence  Pro's occurrence; only its parent ids are read.
	 * @param int    $new_post_id The standalone event's post id.
	 *
	 * @return void
	 */
	public function on_occurrence_split( $occurrence, $new_post_id ) {

		$series_id = is_object( $occurrence ) && method_exists( $occurrence, 'get_parent_sc_event_id' ) ? (int) $occurrence->get_parent_sc_event_id() : 0;
		$post      = get_post( (int) $new_post_id );

		if ( $series_id && $post instanceof WP_Post && $this->pusher->copy_series_meet( $series_id, EventSaveGuard::resolve_event_for_post( $post->ID ) ) ) {
			$this->pusher->push( $post->ID, $post );
		}

		$this->on_occurrence_removed( $occurrence );
	}

	/**
	 * Re-push a series that lost one date (split off or trashed), so Google skips it too.
	 *
	 * @since 3.16.0
	 *
	 * @param object $occurrence Pro's occurrence; only its parent post id is read.
	 *
	 * @return void
	 */
	public function on_occurrence_removed( $occurrence ) {

		$post_id = is_object( $occurrence ) && method_exists( $occurrence, 'get_parent_post_id' ) ? (int) $occurrence->get_parent_post_id() : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( $post instanceof WP_Post ) {
			$this->pusher->push( $post_id, $post );
		}
	}

	/**
	 * Queue the Google delete when the post is deleted for good.
	 *
	 * Core deletes the event row (and its meta) at priority 999, so the link is still
	 * readable here. The HTTP call waits for shutdown so a bulk delete is one relay call.
	 *
	 * @since 3.16.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function cleanup( $post_id ) {

		// Pulled events carry the same link; in the pull direction a local delete must not reach Google.
		if ( ! $this->pusher->is_enabled() ) {
			return;
		}

		if ( ! post_type_supports( get_post_type( $post_id ), 'events' ) ) {
			return;
		}

		try {
			$event = EventSaveGuard::resolve_event_for_post( $post_id );

			if ( empty( $event->id ) ) {
				return;
			}

			$this->queue_deletes( $event );
		} catch ( Throwable $e ) {
			// Swallow: a Google failure must never block the delete.
			unset( $e );
		}
	}

	/**
	 * Queue the delete of each Google copy in a calendar that takes pushes.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return void
	 */
	private function queue_deletes( $event ) {

		foreach ( $this->links->get_links( $event->id ) as $calendar_id => $link ) {
			if ( empty( $link['event_id'] ) ) {
				continue;
			}

			$row = GoogleSyncMapper::row_for( (string) $calendar_id );

			// A link whose calendar is pull-only, or no longer mapped, keeps its Google copy.
			if ( $row === null || ! GoogleSyncMapper::allows_push( $row['sync_direction'] ) ) {
				continue;
			}

			$this->queue->queue_delete( (string) $calendar_id, (string) $link['event_id'] );
		}
	}

	/**
	 * Queue a re-push when Pro cuts a pushed series ("this and following").
	 *
	 * Pro writes the end before it moves the tail rows, so the push waits for shutdown.
	 *
	 * @since 3.16.0
	 *
	 * @param int $series_event_id The cut series' SC event id.
	 * @param int $tail_event_id   The new series' SC event id, 0 when the dates from the cut on were trashed.
	 *
	 * @return void
	 */
	public function on_series_cut( $series_event_id, $tail_event_id ) {

		$event = sugar_calendar_get_event( (int) $series_event_id );

		if ( empty( $event->object_id ) || ! in_array( 'pushed', array_column( $this->links->get_links( $event->id ), 'rule' ), true ) ) {
			return;
		}

		$this->queue->queue_cut( (int) $event->object_id, (int) $event->id, (int) $tail_event_id );
	}
}
