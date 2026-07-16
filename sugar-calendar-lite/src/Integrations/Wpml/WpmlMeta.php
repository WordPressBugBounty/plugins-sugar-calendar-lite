<?php

namespace Sugar_Calendar\Integrations\Wpml;

/**
 * WPML Meta Integration.
 *
 * Handles language columns, translation flags, and admin styles
 * for the Sugar Calendar events meta data.
 *
 * @since 3.12.0
 */
class WpmlMeta {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		// Translate event object_id to current language after events are queried.
		add_filter( 'sugar_calendar_event_construct', [ $this, 'translate_event_object_id' ] );
	}

	/**
	 * Translate event object_id to the current language.
	 *
	 * When events are queried (e.g., on a translated venue page), we want to
	 * display the translated version of the event post, not the original.
	 *
	 * @since 3.12.0
	 *
	 * @param Event $event Event object being constructed.
	 *
	 * @return Event Modified event with translated object_id.
	 */
	public function translate_event_object_id( $event ) {

		// Only process if we have a valid object_id.
		if ( empty( $event->object_id ) || empty( $event->object_subtype ) ) {
			return $event;
		}

		// Get the translated version of this event post.
		$translated_object_id = Wpml::get_current_translated_post_id(
			$event->object_id,
			$event->object_subtype
		);

		// Update the event's object_id if a translation exists.
		if ( ! empty( $translated_object_id ) && (int) $translated_object_id !== (int) $event->object_id ) {

			$event->object_id = $translated_object_id;

			// Also update title and content from the translated post.
			$translated_post = get_post( $translated_object_id );

			if ( $translated_post ) {
				$event->title   = $translated_post->post_title;
				$event->content = $translated_post->post_content;
			}
		}

		return $event;
	}
}
