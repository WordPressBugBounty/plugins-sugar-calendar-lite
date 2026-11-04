<?php

namespace Sugar_Calendar\Integrations\Polylang;

use Sugar_Calendar\Integrations\EventSaveGuard;
use WP_Post;

/**
 * Keep the event meta of an event's Polylang translations the same.
 *
 * @since 3.17.0
 */
class PolylangMeta {

	/**
	 * Online meeting keys only the main post's editor changes; a translation's shows them read-only.
	 *
	 * @since 3.17.0
	 *
	 * @var string[]
	 */
	const MAIN_ONLY_KEYS = [ 'online_provider', 'online_visibility', 'custom_link_url' ];

	/**
	 * Events added in this request: they take the group's meta instead of giving theirs.
	 *
	 * @since 3.17.0
	 *
	 * @var int[]
	 */
	private $new_event_ids = [];

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_added_event', [ $this, 'remember_new_event' ] );

		// After every save_post:20 writer, like the Registration Form, whatever order they run in.
		add_action( 'save_post', [ $this, 'sync_saved_post' ], 30, 2 );
	}

	/**
	 * Remember an event added in this request.
	 *
	 * @since 3.17.0
	 *
	 * @param int $event_id Event ID.
	 */
	public function remember_new_event( $event_id ) {

		$this->new_event_ids[] = (int) $event_id;
	}

	/**
	 * Share a saved event's meta with its translations.
	 *
	 * @since 3.17.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function sync_saved_post( $post_id, $post ) {

		// Our own copies to translations carry no editor form.
		if ( ! PolylangApi::is_custom_fields_sync_on() || ! EventSaveGuard::is_editor_save( $post_id, $post ) ) {
			return;
		}

		$event_id     = $this->get_post_event_id( $post_id );
		$translations = $this->get_translation_event_ids( $post_id );

		if ( empty( $event_id ) || empty( $translations ) ) {
			return;
		}

		// A new translation's editor shows empty fields that read meta by event ID.
		if ( in_array( $event_id, $this->new_event_ids, true ) ) {
			$this->copy_meta( $this->get_group_source( $translations ), $event_id, $this->get_synced_keys() );

			return;
		}

		if ( ! PolylangApi::can_sync_translations( $post_id ) ) {
			return;
		}

		$this->push_meta( $event_id, $translations );
	}

	/**
	 * Copy a saved event's meta to the events of its translations.
	 *
	 * @since 3.17.0
	 *
	 * @param int   $event_id     Saved event ID.
	 * @param int[] $translations Language slug => event ID.
	 */
	private function push_meta( $event_id, $translations ) {

		$keys = $this->get_synced_keys();

		// The saved post is a translation when the group's main post is among the others.
		if ( isset( $translations[ PolylangApi::get_default_language() ] ) ) {
			$keys = array_diff( $keys, self::MAIN_ONLY_KEYS );
		}

		foreach ( $translations as $translation_id ) {
			$this->copy_meta( $event_id, $translation_id, $keys );
		}
	}

	/**
	 * Copy the shared meta of one event to another.
	 *
	 * @since 3.17.0
	 *
	 * @param int      $from_id Event ID to copy from.
	 * @param int      $to_id   Event ID to copy to.
	 * @param string[] $keys    Meta keys to copy.
	 */
	private function copy_meta( $from_id, $to_id, $keys ) {

		foreach ( $keys as $key ) {

			$value = get_event_meta( $from_id, $key, true );

			// Same rule as the event save: an empty value removes the key.
			if ( empty( $value ) ) {
				delete_event_meta( $to_id, $key );

				continue;
			}

			update_event_meta( $to_id, $key, wp_slash( $value ) );
		}
	}

	/**
	 * Get the meta keys shared by translations.
	 *
	 * @since 3.17.0
	 *
	 * @return string[]
	 */
	private function get_synced_keys() {

		return array_diff( array_keys( get_registered_meta_keys( 'sc_event' ) ), $this->get_excluded_keys() );
	}

	/**
	 * Get the meta keys another part of the integration copies.
	 *
	 * @since 3.17.0
	 *
	 * @return string[]
	 */
	protected function get_excluded_keys() {

		return [];
	}

	/**
	 * Get the event a new translation takes its meta from: the main post's, else any.
	 *
	 * @since 3.17.0
	 *
	 * @param int[] $translations Language slug => event ID.
	 *
	 * @return int
	 */
	private function get_group_source( $translations ) {

		return $translations[ PolylangApi::get_default_language() ] ?? reset( $translations );
	}

	/**
	 * Get the events of a post's translations.
	 *
	 * @since 3.17.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int[] Language slug => event ID.
	 */
	private function get_translation_event_ids( $post_id ) {

		$event_ids = [];

		foreach ( pll_get_post_translations( $post_id ) as $language => $translation_id ) {

			$event_id = (int) $translation_id === (int) $post_id ? 0 : $this->get_post_event_id( $translation_id );

			if ( ! empty( $event_id ) ) {
				$event_ids[ $language ] = $event_id;
			}
		}

		return $event_ids;
	}

	/**
	 * Get the event ID of a post, whichever event post type it is.
	 *
	 * @since 3.17.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int
	 */
	private function get_post_event_id( $post_id ) {

		return (int) sugar_calendar_get_event_by_object( $post_id, 'post', [ 'object_subtype' => get_post_type( $post_id ) ] )->id;
	}
}
