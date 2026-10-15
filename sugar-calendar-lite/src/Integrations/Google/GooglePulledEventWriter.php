<?php

namespace Sugar_Calendar\Integrations\Google;

use Sugar_Calendar\Helpers;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\MeetingProviderInterface;
use WP_Post;

/**
 * Writes a pulled Google item into Sugar Calendar: create, update, trash, link, Meet link.
 *
 * @since 3.16.0
 */
class GooglePulledEventWriter {

	/**
	 * The recurrence fields the pull writes.
	 *
	 * @since 3.16.0
	 *
	 * @var string[]
	 */
	const RECURRENCE_KEYS = [
		'recurrence',
		'recurrence_interval',
		'recurrence_count',
		'recurrence_end',
		'recurrence_end_tz',
		'recurrence_byday',
		'recurrence_bymonthday',
		'recurrence_bypos',
		'recurrence_bymonth',
	];

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
	 * @param GoogleLinkRepository $links   Link storage.
	 * @param GoogleEventBuilder   $builder Event builder.
	 */
	public function __construct( GoogleLinkRepository $links, GoogleEventBuilder $builder ) {

		$this->links   = $links;
		$this->builder = $builder;
	}

	/**
	 * Create the post and the event row.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $data        Mapped fields.
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return int Event id, or 0.
	 */
	public function create_event( array $data, array $item, string $calendar_id ) {

		$status  = $this->pulled_status( $item, $calendar_id );
		$post_id = $this->insert_post( $data, $status );

		if ( ! $post_id ) {
			return 0;
		}

		$row      = $this->event_row( $data, $post_id, $status );
		$event_id = sugar_calendar_add_event( $row );

		if ( ! $event_id ) {
			wp_delete_post( (int) $post_id, true );

			return 0;
		}

		$term = GoogleSyncMapper::pull_calendar_term( GoogleSyncMapper::row_for( $calendar_id ) );

		if ( $term > 0 ) {
			wp_set_object_terms( (int) $post_id, [ $term ], sugar_calendar_get_calendar_taxonomy_id() );
		}

		$this->fire_pulled( (int) $event_id, $row, null );

		return (int) $event_id;
	}

	/**
	 * Insert the event post.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $data   Mapped fields.
	 * @param string $status Post status.
	 *
	 * @return int Post id, or 0.
	 */
	private function insert_post( array $data, string $status ): int {

		$post_id = wp_insert_post(
			[
				'post_title'   => $data['title'],
				'post_content' => $data['content'],
				'post_status'  => $status,
				'post_type'    => sugar_calendar_get_event_post_type_id(),
			],
			true
		);

		return is_wp_error( $post_id ) ? 0 : (int) $post_id;
	}

	/**
	 * Overwrite content and dates; never the status an admin chose, except to restore a trashed copy.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event       Existing event.
	 * @param array  $data        Mapped fields.
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return int Event id, or 0.
	 */
	public function update_event( $event, array $data, array $item, string $calendar_id ) {

		$post_id = (int) $event->object_id;

		$this->restore_if_trashed( $event, $item, $calendar_id );

		$data = $this->keep_local_series( $event, $data );

		// post_content written even when empty: a description cleared in Google must clear here.
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_title'   => $data['title'],
				'post_content' => $data['content'],
			]
		);

		$written = $this->event_row( $data, $post_id, (string) $event->status );

		if ( ! $this->update_row( $event, $written ) ) {
			return 0;
		}

		$this->fire_pulled( (int) $event->id, $written, $event );

		return (int) $event->id;
	}

	/**
	 * Restore a trashed event with the pulled status.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event       Existing event.
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return void
	 */
	private function restore_if_trashed( $event, array $item, string $calendar_id ) {

		$post_id = (int) $event->object_id;

		if ( get_post_status( $post_id ) !== 'trash' ) {
			return;
		}

		$status = $this->pulled_status( $item, $calendar_id );

		wp_untrash_post( $post_id );
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => $status,
			]
		);

		sugar_calendar_update_event( (int) $event->id, [ 'status' => $status ] );
	}

	/**
	 * Drop the recurrence fields when Google lost a rule the event still has.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event Existing event.
	 * @param array  $data  Mapped fields.
	 *
	 * @return array
	 */
	private function keep_local_series( $event, array $data ): array {

		// Google dropped its rule or sent one Pro cannot hold: keep the local series; a later part may un-convert it.
		if ( $event->recurrence !== '' && ( $data['recurrence'] ?? '' ) === '' ) {
			foreach ( self::RECURRENCE_KEYS as $key ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}

	/**
	 * Write the row; false only when the event is gone.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event   Existing event.
	 * @param array  $written Row fields, incl. the ones not updated here.
	 *
	 * @return bool
	 */
	private function update_row( $event, array $written ): bool {

		$row = $written;

		unset( $row['status'], $row['object_id'], $row['object_type'], $row['object_subtype'] );

		// The row query returns false when no column changed; the event is still there.
		if ( sugar_calendar_update_event( (int) $event->id, $row ) ) {
			return true;
		}

		$fresh = sugar_calendar_get_event( (int) $event->id );

		return ! empty( $fresh->id );
	}

	/**
	 * The event-row fields, sanitized the way the importers do.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $data    Mapped fields.
	 * @param int    $post_id Post id.
	 * @param string $status  Post status.
	 *
	 * @return array
	 */
	private function event_row( array $data, int $post_id, string $status ): array {

		$all_day = ! empty( $data['all_day'] );

		$row = [
			'object_id'      => $post_id,
			'object_type'    => 'post',
			'object_subtype' => sugar_calendar_get_event_post_type_id(),
			'title'          => sanitize_text_field( $data['title'] ),
			'content'        => wp_kses_post( $data['content'] ),
			'status'         => sanitize_text_field( $status ),
			'start'          => Helpers::sanitize_start( $data['start_date'], $data['end_date'], $all_day ),
			'start_tz'       => Helpers::sanitize_timezone( $data['start_tz'], $data['end_tz'], $all_day ? '1' : '' ),
			'end'            => Helpers::sanitize_end( $data['end_date'], $data['start_date'], $all_day ),
			'end_tz'         => Helpers::sanitize_timezone( $data['end_tz'], $data['start_tz'], $all_day ? '1' : '' ),
			'all_day'        => Helpers::sanitize_all_day( $all_day, $data['start_date'], $data['end_date'] ),
			'uuid'           => $data['uuid'],
		];

		// Absent keys are left as stored (the kept local series).
		foreach ( self::RECURRENCE_KEYS as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$row[ $key ] = $data[ $key ];
			}
		}

		return $row;
	}

	/**
	 * Tell listeners (Pro's recurring feature) that a Google event was written.
	 *
	 * @since 3.16.0
	 *
	 * @param int         $event_id  Event id.
	 * @param array       $row       Row written, incl. object_id, title, start, end and the recurrence fields.
	 * @param object|null $old_event The event before the write, null on create.
	 *
	 * @return void
	 */
	private function fire_pulled( int $event_id, array $row, $old_event ) {

		/**
		 * Fire after a Google event was written into Sugar Calendar.
		 *
		 * @since 3.16.0
		 *
		 * @param int         $event_id  Event id.
		 * @param array       $row       Row written, incl. object_id, title, start, end and the recurrence fields.
		 * @param object|null $old_event The event before the write, null on create.
		 */
		do_action( 'sugar_calendar_google_pulled_event', $event_id, $row, $old_event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * The post status a pulled event is created or restored with.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return string
	 */
	private function pulled_status( array $item, string $calendar_id ): string {

		/**
		 * Filter the post status a pulled Google event is created with.
		 *
		 * @since 3.16.0
		 *
		 * @param string $status      Default 'publish'.
		 * @param array  $item        Google event resource.
		 * @param string $calendar_id Google calendar id.
		 */
		$status = (string) apply_filters( 'sugar_calendar_google_pull_event_status', 'publish', $item, $calendar_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return get_post_status_object( $status ) ? $status : 'publish';
	}

	/**
	 * Store the link for an event just written from a Google item, hashed as push would hash it.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event       Event written.
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 * @param string $rule        Who owns the repeat rule: 'pushed', 'google' or ''.
	 *
	 * @return void
	 */
	public function store_pulled_link( $event, array $item, string $calendar_id, string $rule ) {

		$google_id = (string) $item['id'];
		$post      = get_post( (int) $event->object_id );

		$this->links->store_link(
			$event->id,
			$calendar_id,
			[
				'event_id' => $google_id,
				'etag'     => (string) ( $item['etag'] ?? '' ),
				'hash'     => $post instanceof WP_Post ? $this->builder->fingerprint( $event, $post, $google_id ) : '',
				'updated'  => (string) ( $item['updated'] ?? '' ),
				'rule'     => $rule,
			]
		);
	}

	/**
	 * Trash a pulled event whose Google copy was cancelled.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event       Existing event.
	 * @param string $calendar_id Google calendar id.
	 * @param string $google_id   Google event id.
	 *
	 * @return void
	 */
	public function trash_event( $event, string $calendar_id, string $google_id ) {

		wp_trash_post( (int) $event->object_id );

		// Core syncs the row status for sc_event posts only; a series row would keep its old one.
		sugar_calendar_update_event( (int) $event->id, [ 'status' => 'trash' ] );

		// Keep the id so Google's own Undo still matches, but drop the hash: an untouched
		// restore fingerprints identically, and push would skip it as a no-op change.
		$link = $this->links->get_links( $event->id )[ $calendar_id ] ?? [];

		$this->links->store_link(
			$event->id,
			$calendar_id,
			[
				'event_id' => $google_id,
				'etag'     => (string) ( $link['etag'] ?? '' ),
				'hash'     => '',
				'updated'  => (string) ( $link['updated'] ?? '' ),
			]
		);
	}

	/**
	 * Mirror the Google copy's Meet onto the event: set it, clear it, or leave another provider alone.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event     SCE Event object.
	 * @param string $meet_code Validated Meet code from the builder, or '' when the copy has no Meet.
	 *
	 * @return void
	 */
	public function apply_meet_link( $event, string $meet_code ) {

		$provider = IntegrationCapabilityRegistry::instance()->find( MeetingProviderInterface::class, 'google' );

		if ( ! $provider instanceof MeetingProviderInterface || empty( $event->id ) ) {
			return;
		}

		$meeting_provider = (string) get_event_meta( $event->id, 'meeting_provider', true );
		$online_provider  = (string) get_event_meta( $event->id, 'online_provider', true );

		if ( $meet_code === '' ) {
			if ( $meeting_provider === 'google' ) {
				$this->clear_meet( $provider, $event );
			}

			return;
		}

		// A Zoom meeting or a Custom Link is never overwritten by the pull.
		if ( ! in_array( $meeting_provider, [ '', 'google' ], true ) || ! in_array( $online_provider, [ '', 'google' ], true ) ) {
			return;
		}

		if ( (string) get_event_meta( $event->id, 'meeting_id', true ) === $meet_code ) {
			return;
		}

		$this->store_meet( $provider, $event, $meet_code );
	}

	/**
	 * Remove the Google Meet from the event.
	 *
	 * @since 3.16.0
	 *
	 * @param MeetingProviderInterface $provider Google Meet provider.
	 * @param object                   $event    SCE Event object.
	 *
	 * @return void
	 */
	private function clear_meet( MeetingProviderInterface $provider, $event ) {

		foreach ( array_merge( $provider->get_meeting_meta_keys(), [ 'meeting_sync_hash', 'meeting_kind', 'online_provider' ] ) as $key ) {
			delete_event_meta( $event->id, $key );
		}
	}

	/**
	 * Set the Google Meet on the event.
	 *
	 * @since 3.16.0
	 *
	 * @param MeetingProviderInterface $provider  Google Meet provider.
	 * @param object                   $event     SCE Event object.
	 * @param string                   $meet_code Meet code.
	 *
	 * @return void
	 */
	private function store_meet( MeetingProviderInterface $provider, $event, string $meet_code ) {

		$signature = $provider->get_sync_signature( $event );

		update_event_meta( $event->id, 'meeting_provider', 'google' );
		update_event_meta( $event->id, 'meeting_id', $meet_code );
		update_event_meta( $event->id, 'join_url', 'https://meet.google.com/' . $meet_code );
		update_event_meta( $event->id, 'meeting_password', '' );
		update_event_meta( $event->id, 'meeting_settings', '{}' );
		update_event_meta( $event->id, 'meeting_sync_hash', $signature['fingerprint'] );
		update_event_meta( $event->id, 'meeting_kind', $signature['kind'] );
		update_event_meta( $event->id, 'online_provider', 'google' );
	}
}
