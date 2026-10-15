<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Integrations\EventSaveGuard;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Throwable;
use WP_Error;
use WP_Post;

/**
 * Pushes one Sugar Calendar event to its Google calendars: create, update, delete. No hooks, no queues.
 *
 * @since 3.16.0
 */
class GooglePusher {

	/**
	 * Pairs per relay batch-delete call.
	 *
	 * @since 3.16.0
	 *
	 * @var int
	 */
	const DELETE_CHUNK = 25;

	/**
	 * Credits service (gates creates only).
	 *
	 * @since 3.16.0
	 *
	 * @var CreditsService
	 */
	private $credits;

	/**
	 * Actions client.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleActionsClient
	 */
	private $client;

	/**
	 * Link storage.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleLinkRepository
	 */
	private $links;

	/**
	 * Event builder.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleEventBuilder
	 */
	private $builder;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param CreditsService       $credits Credits service (gates creates only).
	 * @param GoogleActionsClient  $client  Actions client.
	 * @param GoogleLinkRepository $links   Link storage.
	 * @param GoogleEventBuilder   $builder Event builder.
	 */
	public function __construct( CreditsService $credits, GoogleActionsClient $client, GoogleLinkRepository $links, GoogleEventBuilder $builder ) {

		$this->credits = $credits;
		$this->client  = $client;
		$this->links   = $links;
		$this->builder = $builder;
	}

	/**
	 * Create or update the Google copy of a published event.
	 *
	 * @since 3.16.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function push( $post_id, WP_Post $post ) {

		if ( ! $this->is_enabled() ) {
			return;
		}

		try {
			$this->push_to_targets( $post_id, $post );
		} catch ( Throwable $e ) {
			// Swallow: a Google failure must never block the post save.
			unset( $e );
		}
	}

	/**
	 * Create or update the event's copy in each target calendar.
	 *
	 * @since 3.16.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	private function push_to_targets( $post_id, WP_Post $post ) {

		$event = $this->pushable_event( $post_id, $post );

		if ( $event === null ) {
			return;
		}

		$connection = $this->get_connection();

		if ( is_wp_error( $connection ) ) {
			return;
		}

		$links = $this->get_push_links(
			$event,
			GoogleSyncMapper::push_targets( (int) $post->ID )
		);

		// Links outside the targets (unmapped or pull-only calendars) are left as they are.
		foreach ( $links as $calendar_id => $link ) {

			if ( $link ) {
				$this->update( $connection, $event, $post, $link + [ 'calendar_id' => $calendar_id ] );
			} else {
				$this->create( $connection, $event, $post, $calendar_id );
			}
		}
	}

	/**
	 * The post's event when it may go to Google, else null.
	 *
	 * @since 3.16.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return object|null
	 */
	private function pushable_event( $post_id, WP_Post $post ) {

		$event = EventSaveGuard::resolve_event_for_post( $post_id );

		if ( empty( $event->id ) ) {
			return null;
		}

		// An unpublished copy stays as it was in Google; a never-pushed draft stays off it.
		if ( $post->post_status !== 'publish' ) {
			return null;
		}

		return $this->builder->is_pushable( $event ) ? $event : null;
	}

	/**
	 * Re-push one link when the pull found Sugar Calendar's copy newer than Google's.
	 *
	 * Keeps the same gates as a save: an unpublished or unpushable event never reaches Google.
	 *
	 * @since 3.16.0
	 *
	 * @param object  $event       SCE Event object.
	 * @param WP_Post $post        The event's post.
	 * @param string  $calendar_id The calendar the copy lives in.
	 * @param array   $link        Stored link.
	 *
	 * @return bool Whether a push was attempted.
	 */
	public function push_link( $event, WP_Post $post, string $calendar_id, array $link ): bool {

		$row = GoogleSyncMapper::row_for( $calendar_id );

		// A pull-only calendar never receives pushes, even when the local copy is newer.
		if ( $row === null || ! GoogleSyncMapper::allows_push( $row['sync_direction'] ) ) {
			return false;
		}

		if ( $post->post_status !== 'publish' || ! $this->builder->is_pushable( $event ) ) {
			return false;
		}

		$connection = $this->get_connection();

		if ( is_wp_error( $connection ) ) {
			return false;
		}

		$this->update( $connection, $event, $post, $link + [ 'calendar_id' => $calendar_id ] );

		return true;
	}

	/**
	 * Copy the series' Google Meet meta onto the event split off it; a Zoom meeting or Custom Link is never touched.
	 *
	 * @since 3.16.0
	 *
	 * @param int    $series_id SC event id of the series.
	 * @param object $event     The standalone event.
	 *
	 * @return bool Whether anything was copied.
	 */
	public function copy_series_meet( int $series_id, $event ): bool {

		if ( empty( $event->id ) || (string) get_event_meta( $series_id, 'meeting_provider', true ) !== 'google' ) {
			return false;
		}

		$meeting_provider = (string) get_event_meta( $event->id, 'meeting_provider', true );
		$online_provider  = (string) get_event_meta( $event->id, 'online_provider', true );

		if ( ! in_array( $meeting_provider, [ '', 'google' ], true ) || ! in_array( $online_provider, [ '', 'google' ], true ) ) {
			return false;
		}

		foreach ( array_merge( GoogleMeetIntegration::META_KEYS, [ 'meeting_sync_hash', 'meeting_kind', 'online_provider' ] ) as $key ) {
			update_event_meta( $event->id, $key, get_event_meta( $series_id, $key, true ) );
		}

		return true;
	}

	/**
	 * Whether any mapped calendar receives pushes.
	 *
	 * @since 3.16.0
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {

		foreach ( GoogleSyncMapper::rows() as $row ) {
			if ( GoogleSyncMapper::allows_push( $row['sync_direction'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete Google copies, in relay-sized batches. A failure is swallowed: a delete must never block.
	 *
	 * @since 3.16.0
	 *
	 * @param array $pairs Each [ 'calendarId' => string, 'eventId' => string ].
	 *
	 * @return void
	 */
	public function delete_pairs( array $pairs ) {

		try {
			$connection = $this->get_connection();

			if ( is_wp_error( $connection ) ) {
				return;
			}

			foreach ( array_chunk( $pairs, self::DELETE_CHUNK ) as $chunk ) {
				$this->client->delete_events( $connection, $chunk );
			}
		} catch ( Throwable $e ) {
			// Swallow: a Google failure must never block the delete.
			unset( $e );
		}
	}

	/**
	 * Create the Google copy (credit-gated) and store the link.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleOAuthConnection $connection  Active connection.
	 * @param object                $event       SCE Event object.
	 * @param WP_Post               $post        The event's post.
	 * @param string                $calendar_id Target Google calendar id.
	 *
	 * @return void
	 */
	private function create( GoogleOAuthConnection $connection, $event, WP_Post $post, string $calendar_id ) {

		if ( $calendar_id === '' ) {
			return;
		}

		// Create-only credits gate; the next save retries.
		if ( $this->credits->is_out_of_credits() ) {
			return;
		}

		$body   = $this->builder->build( $event, $post );
		$result = $this->client->create_event( $connection, $calendar_id, $body );

		if ( is_wp_error( $result ) || empty( $result['id'] ) ) {
			return;
		}

		$this->links->store_link(
			$event->id,
			$calendar_id,
			[
				'event_id' => (string) $result['id'],
				'etag'     => (string) ( $result['etag'] ?? '' ),
				'hash'     => $this->builder->fingerprint( $event, $post ),
				'updated'  => (string) ( $result['updated'] ?? '' ),
				'rule'     => $this->builder->recurrence_rules( $event ) !== [] ? 'pushed' : '',
			]
		);
	}

	/**
	 * Fingerprint-gated update. A gone copy (not_found) is dropped and created again.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleOAuthConnection $connection Active connection.
	 * @param object                $event      SCE Event object.
	 * @param WP_Post               $post       The event's post.
	 * @param array                 $link       Link to the Google copy, see make_link(), plus the calendar_id it lives in.
	 *
	 * @return void
	 */
	private function update( GoogleOAuthConnection $connection, $event, WP_Post $post, array $link ) {

		$calendar_id = (string) $link['calendar_id'];
		$series_id   = (string) $link['event_id'];
		$hash        = $this->builder->fingerprint( $event, $post, $series_id );

		if ( $hash === ( $link['hash'] ?? '' ) ) {
			return;
		}

		$rules  = $this->builder->recurrence_rules( $event, $series_id );
		$pushed = ( $link['rule'] ?? '' ) === 'pushed';
		$result = $this->client->update_event( $connection, $calendar_id, $series_id, $this->update_body( $event, $post, $series_id, $rules === [] && $pushed ) );

		if ( is_wp_error( $result ) ) {
			// A gone copy is dropped and created again; any other failure leaves the stored hash so the next save retries.
			if ( $result->get_error_code() === 'not_found' ) {
				$this->links->remove_link( $event->id, $calendar_id );
				$this->create( $connection, $event, $post, $calendar_id );
			}

			return;
		}

		$this->links->store_link( $event->id, $calendar_id, $this->link_after_update( $link, $result, $hash, $rules ) );
	}

	/**
	 * The update body, with an explicit empty rule when one we pushed must be cleared.
	 *
	 * @since 3.16.0
	 *
	 * @param object  $event      SCE Event object.
	 * @param WP_Post $post       The event's post.
	 * @param string  $series_id  The copy's Google id.
	 * @param bool    $clear_rule Whether to clear the rule we pushed earlier.
	 *
	 * @return array
	 */
	private function update_body( $event, WP_Post $post, string $series_id, bool $clear_rule ): array {

		$body = $this->builder->build( $event, $post, $series_id );

		// A rule we pushed earlier is cleared explicitly; one Google owns is left alone.
		if ( $clear_rule ) {
			$body['recurrence'] = [];
		}

		return $body;
	}

	/**
	 * The link to store after an update: Google's new etag and stamp, the hash sent, and the rule's owner.
	 * The owner stays 'pushed' when we sent a rule, becomes '' when we cleared ours, and null otherwise (keeps the stored one).
	 *
	 * @since 3.16.0
	 *
	 * @param array  $link   Stored link, every make_link() key present (get_links() guarantees it).
	 * @param array  $result Google's response to the update.
	 * @param string $hash   Fingerprint sent.
	 * @param array  $rules  Recurrence lines sent.
	 *
	 * @return array
	 */
	private function link_after_update( array $link, array $result, string $hash, array $rules ): array {

		$pushed = $link['rule'] === 'pushed';

		return [
			'event_id' => (string) $link['event_id'],
			'etag'     => (string) ( $result['etag'] ?? $link['etag'] ),
			'hash'     => $hash,
			'updated'  => (string) ( $result['updated'] ?? '' ),
			'rule'     => $rules !== [] ? 'pushed' : ( $pushed ? '' : null ),
		];
	}

	/**
	 * Links to push to, one per target calendar: the saved link, else null.
	 * With no saved links at all, the Google id in the uuid of an imported event is used instead.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event        SCE Event object.
	 * @param array  $calendar_ids Target Google calendar ids.
	 *
	 * @return array
	 */
	private function get_push_links( $event, array $calendar_ids ): array {

		$saved     = $this->links->get_links( $event->id );
		$from_uuid = $saved === [] ? $this->make_link_from_uuid( $event ) : null;
		$links     = [];

		foreach ( $calendar_ids as $calendar_id ) {
			$links[ $calendar_id ] = $saved[ $calendar_id ] ?? $from_uuid;
		}

		return $links;
	}

	/**
	 * Build a link from the uuid of an event that was born in Google, so push updates that event instead of creating a copy.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array|null
	 */
	private function make_link_from_uuid( $event ): ?array {

		$uuid   = (string) ( $event->uuid ?? '' );
		$suffix = '@google.com';

		// Google gives such events a uuid of "<google id>@google.com".
		if ( ! str_ends_with( $uuid, $suffix ) ) {
			return null;
		}

		$google_id = substr( $uuid, 0, -strlen( $suffix ) );

		return $google_id === '' ? null : $this->links->make_link( [ 'event_id' => $google_id ] );
	}

	/**
	 * Resolve the active Google connection.
	 *
	 * @since 3.16.0
	 *
	 * @return GoogleOAuthConnection|WP_Error
	 */
	private function get_connection() {

		$row = OAuthConnectionModel::find_active_by_provider( 'google' );

		if ( $row === null ) {
			return new WP_Error( 'google_no_connection', 'No active Google connection.' );
		}

		return new GoogleOAuthConnection( $row );
	}
}
