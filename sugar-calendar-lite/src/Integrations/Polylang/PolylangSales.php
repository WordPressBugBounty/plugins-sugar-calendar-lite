<?php

namespace Sugar_Calendar\Integrations\Polylang;

use Sugar_Calendar\Event;

/**
 * Sell tickets and show the online meeting of a translation on its main post's event.
 *
 * The main post is the group's default-language post.
 *
 * @since 3.17.0
 */
class PolylangSales {

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_add_on_ticketing_frontend_loader_event_object', [ $this, 'get_sales_event' ], 20 );
		add_filter( 'sugar_calendar_addon_ticketing_enqueue_event_object', [ $this, 'get_sales_event' ], 20 );
		add_filter( 'sugar_calendar_integrations_online_meeting_event', [ $this, 'get_meeting_event' ] );
		add_filter( 'sugar_calendar_integrations_admin_online_meeting_section_event', [ $this, 'get_meeting_event' ] );
		add_filter( 'sugar_calendar_admin_email_notifications_attendees_event_id', [ $this, 'get_attendees_event_id' ] );

		// After the online meeting section's own save callback.
		add_filter( 'sugar_calendar_event_to_save', [ $this, 'keep_main_online_meeting' ], 20 );
	}

	/**
	 * Get the event a translation sells tickets on, with the translation's own title.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event Event shown on the page.
	 *
	 * @return Event
	 */
	public function get_sales_event( $event ) {

		$main = $this->get_main_sales_event( $event );

		if ( $main === null ) {
			return $event;
		}

		// A copy, so the translation's title shows on its page without reaching the cached main event.
		$main        = clone $main;
		$main->title = $event->title;

		return $main;
	}

	/**
	 * Get the event whose online meeting a translation shows.
	 *
	 * @since 3.17.0
	 *
	 * @param object $event Event.
	 *
	 * @return object
	 */
	public function get_meeting_event( $event ) {

		// A "+" editor has no event yet, so its main comes from the post it translates.
		$main = empty( $event->id ) ? $this->get_new_translation_main_event() : $this->get_main_event( $event );

		return $main ?? $event;
	}

	/**
	 * Leave the online meeting fields out of a translation's save, as its editor shows them read-only.
	 *
	 * @since 3.17.0
	 *
	 * @param array $to_save Event data to save.
	 *
	 * @return array
	 */
	public function keep_main_online_meeting( $to_save ) {

		$post_id = empty( $to_save['object_id'] ) ? 0 : (int) $to_save['object_id'];

		if ( $this->get_main_event( (object) [ 'object_id' => $post_id ] ) === null ) {
			return $to_save;
		}

		return array_diff_key( $to_save, array_flip( PolylangMeta::MAIN_ONLY_KEYS ) );
	}

	/**
	 * Get the event a translation's ticket and RSVP attendees are stored on.
	 *
	 * @since 3.17.0
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return int
	 */
	public function get_attendees_event_id( $event_id ) {

		$event = sugar_calendar_get_event( (int) $event_id );
		$main  = empty( $event->id ) ? null : $this->get_main_sales_event( $event );

		return $main === null ? (int) $event_id : (int) $main->id;
	}

	/**
	 * Get the main post's event a translation sells on, if it sells there.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event Event.
	 *
	 * @return Event|null
	 */
	protected function get_main_sales_event( $event ) {

		// Recurring events still sell on their own series.
		if ( ! empty( $event->recurrence ) || ! empty( $event->occurrence_id ) ) {
			return null;
		}

		return $this->get_main_event( $event );
	}

	/**
	 * Get the event of a translation's main post, when sync is on and the event is a translation.
	 *
	 * @since 3.17.0
	 *
	 * @param object $event Event.
	 *
	 * @return Event|null
	 */
	protected function get_main_event( $event ) {

		$post_id = empty( $event->object_id ) ? 0 : (int) $event->object_id;
		$main    = empty( $post_id ) ? null : $this->get_group_main_event( $post_id );

		// The main post's own event has no other main.
		return $main === null || (int) $main->object_id === $post_id ? null : $main;
	}

	/**
	 * Get the main post's event of the group a "+" editor adds a translation to.
	 *
	 * @since 3.17.0
	 *
	 * @return Event|null
	 */
	private function get_new_translation_main_event() {

		$request = PolylangSync::get_new_translation_request();

		return empty( $request ) ? null : $this->get_group_main_event( $request['from_post'] );
	}

	/**
	 * Get the event of the default-language post in a post's translation group, when sync is on.
	 *
	 * @since 3.17.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return Event|null
	 */
	private function get_group_main_event( $post_id ) {

		$main_id = PolylangApi::is_custom_fields_sync_on() ? (int) pll_get_post( $post_id, PolylangApi::get_default_language() ) : 0;

		if ( empty( $main_id ) ) {
			return null;
		}

		$main = sugar_calendar_get_event_by_object( $main_id, 'post', [ 'object_subtype' => get_post_type( $main_id ) ] );

		return empty( $main->id ) ? null : $main;
	}
}
