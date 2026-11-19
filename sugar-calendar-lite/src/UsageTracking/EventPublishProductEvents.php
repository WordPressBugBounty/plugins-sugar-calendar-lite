<?php

namespace Sugar_Calendar\UsageTracking;

use Throwable;
use WP_Post;

/**
 * Product Events for an event reaching published status.
 *
 * Reports `event_published` for an event that existed before it went live (a draft, a
 * pending front-end submission, a Gia draft). An event created as published is
 * reported by `event_created` only.
 *
 * @since 3.18.0
 */
class EventPublishProductEvents extends AbstractProductEvents {

	/**
	 * Post types that hold an event.
	 *
	 * @since 3.18.0
	 */
	const POST_TYPES = [ 'sc_event', 'sc_recurring_event' ];

	/**
	 * Published posts noted in this request, as post ID => [ post type, old status ].
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $published = [];

	/**
	 * Event IDs created in this request, which `event_created` already reports.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $created = [];

	/**
	 * Register hooks.
	 *
	 * @since 3.18.0
	 */
	protected function hooks() {

		add_action( 'transition_post_status', [ $this, 'note_published' ], 10, 3 );
		add_action( 'sugar_calendar_added_event', [ $this, 'note_created' ] );
	}

	/**
	 * Note a post moving into publish; the event is built on shutdown, after the row is saved.
	 *
	 * @since 3.18.0
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function note_published( $new_status, $old_status, $post ) {

		if ( $new_status !== 'publish' || in_array( $old_status, [ 'publish', 'new' ], true ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, self::POST_TYPES, true ) || ! $this->can_track_creation() ) {
			return;
		}

		if ( empty( $this->published ) ) {
			$this->defer(
				function () {
					$this->track_published_events();
				}
			);
		}

		$this->published[ (int) $post->ID ] = [ $post->post_type, $old_status ];
	}

	/**
	 * Note an event created in this request, whether or not `event_created` reports it.
	 *
	 * @since 3.18.0
	 *
	 * @param int $event_id Sugar Calendar event ID.
	 */
	public function note_created( $event_id ) {

		$this->created[ (int) $event_id ] = true;
	}

	/**
	 * Track every noted post.
	 *
	 * @since 3.18.0
	 */
	private function track_published_events() {

		$published       = $this->published;
		$this->published = [];

		foreach ( $published as $post_id => $noted ) {
			try {
				$this->track_event_published( $post_id, $noted[0], $noted[1] );
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}
	}

	/**
	 * Track one event going live from its saved state.
	 *
	 * @since 3.18.0
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $post_type   Post type, which is the event's object subtype.
	 * @param string $from_status Post status before the publish.
	 */
	private function track_event_published( $post_id, $post_type, $from_status ) {

		$sc_event = sugar_calendar_get_event_by_object( $post_id, 'post', [ 'object_subtype' => $post_type ] );

		if ( ! is_object( $sc_event ) || empty( $sc_event->id ) || isset( $this->created[ (int) $sc_event->id ] ) ) {
			return;
		}

		$properties_builder = new EventProperties();

		if ( ! $properties_builder->should_track( (int) $sc_event->id ) ) {
			return;
		}

		$properties                = $properties_builder->build( $sc_event );
		$properties['from_status'] = $from_status;

		$this->track_event( 'event_published', $properties );
	}
}
