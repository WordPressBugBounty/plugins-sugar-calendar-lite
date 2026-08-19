<?php

namespace Sugar_Calendar\Features\RegistrationForm;

/**
 * Removes response rows when their host is gone: order deleted, event deleted.
 *
 * Only the whole-context deletes here revoke a token, since it's a per-context
 * credential shared by every row; deleting a single attendee's row does not.
 * A refund deliberately keeps its rows, so there is no refund handler here.
 *
 * @since 3.13.0
 */
class Cleanup {

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_ticketing_order_deleted', [ $this, 'delete_order_responses' ] );

		// before_delete_post, not trashed_post: trashing is reversible, so
		// responses must survive it.
		add_action( 'before_delete_post', [ $this, 'delete_event_responses' ] );

		add_action( 'before_delete_post', [ $this, 'delete_rsvp_responses' ] );

		add_action( 'sugar_calendar_rsvp_additional_attendees_deleted', [ $this, 'delete_rsvp_attendee_responses' ], 10, 2 );
	}

	/**
	 * Delete the responses of a deleted order.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 */
	public function delete_order_responses( $order_id ) {

		ResponseRepository::delete_for_context( 'order', (int) $order_id );
	}

	/**
	 * Delete every response of a deleted event.
	 *
	 * Resolves the post id to the Sugar Calendar event id first, since response
	 * rows key on the latter (the dual-ID trap). Gated on post-type support for
	 * 'events' rather than 'sc_event' by name, so Pro's sc_recurring_event is
	 * covered too.
	 *
	 * @since 3.13.0
	 *
	 * @param int $post_id Post id.
	 */
	public function delete_event_responses( $post_id ) {

		$post_id = (int) $post_id;

		if ( $post_id <= 0 || ! post_type_supports( get_post_type( $post_id ), 'events' ) ) {
			return;
		}

		// object_subtype must be passed explicitly, or sugar_calendar_get_events()
		// defaults to 'sc_event' and a Pro sc_recurring_event post resolves to an
		// empty Event, whose responses would survive the deletion.
		$event = sugar_calendar_get_event_by_object(
			$post_id,
			'post',
			[ 'object_subtype' => get_post_type( $post_id ) ]
		);

		if ( empty( $event->id ) ) {
			return;
		}

		ResponseRepository::delete_for_event( (int) $event->id );
	}

	/**
	 * Delete every response of a deleted RSVP.
	 *
	 * A separate callback, not a branch in delete_event_responses(): sc-rsvp
	 * registers its post type without 'events' support, so it never passes that
	 * method's guard. Unlike there, context_id here is the sc_rsvp post id
	 * directly, no dual-ID lookup needed.
	 *
	 * @since 3.13.0
	 *
	 * @param int $post_id Post id.
	 */
	public function delete_rsvp_responses( $post_id ) {

		$post_id = (int) $post_id;

		// Literal 'sc_rsvp', not Sugar_Calendar_Rsvp\RsvpHandler::POST_TYPE:
		// core must not reference an add-on class that may not be loaded.
		if ( $post_id <= 0 || get_post_type( $post_id ) !== 'sc_rsvp' ) {
			return;
		}

		ResponseRepository::delete_for_context( 'rsvp', $post_id );
	}

	/**
	 * Delete the responses of attendees removed from an RSVP.
	 *
	 * RsvpCheckout's reconcile only hangs off the frontend AJAX submit hook, so
	 * a guest removed via the admin RSVP edit screen (which saves through
	 * save_post) left a dangling response row pointing at a deleted attendee.
	 * Idempotent: on the frontend path the reconcile already removed it.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $rsvp_post_id The RSVP post id the attendees belonged to.
	 * @param array $attendee_ids The attendee ids that were deleted.
	 */
	public function delete_rsvp_attendee_responses( $rsvp_post_id, $attendee_ids ) {

		$rsvp_post_id = (int) $rsvp_post_id;
		$attendee_ids = array_filter( array_map( 'absint', (array) $attendee_ids ) );

		if ( $rsvp_post_id <= 0 || empty( $attendee_ids ) ) {
			return;
		}

		$row_ids = [];

		foreach ( ResponseRepository::get_for_rsvp( $rsvp_post_id ) as $row ) {

			if ( $row['attendee_id'] !== null && in_array( $row['attendee_id'], $attendee_ids, true ) ) {
				$row_ids[] = $row['id'];
			}
		}

		ResponseRepository::delete( $row_ids );
	}
}
