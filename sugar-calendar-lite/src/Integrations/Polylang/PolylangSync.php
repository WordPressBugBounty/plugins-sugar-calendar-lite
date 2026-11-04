<?php

namespace Sugar_Calendar\Integrations\Polylang;

use Sugar_Calendar\Event;

/**
 * Keep the dates of an event's Polylang translations the same.
 *
 * @since 3.17.0
 */
class PolylangSync {

	/**
	 * Event columns shared by translations.
	 *
	 * @since 3.17.0
	 *
	 * @var string[]
	 */
	const DATE_COLUMNS = [ 'start', 'start_tz', 'end', 'end_tz', 'all_day' ];

	/**
	 * Whether the dates are being copied to the translations right now.
	 *
	 * @since 3.17.0
	 *
	 * @var bool
	 */
	private $is_syncing = false;

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_admin_event_metabox_get_post_event_data', [ $this, 'copy_dates_into_new_translation' ] );
		add_action( 'sugar_calendar_added_event', [ $this, 'sync_saved_event' ], 10, 2 );
		add_action( 'sugar_calendar_updated_event', [ $this, 'sync_saved_event' ], 10, 2 );

		// An unchanged event row fires no update, but Polylang still copies custom fields on every save.
		add_action( 'save_post', [ $this, 'sync_saved_post' ], 20 );
	}

	/**
	 * Open a new translation with the dates of the event it translates.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event Event shown in the editor.
	 *
	 * @return Event
	 */
	public function copy_dates_into_new_translation( $event ) {

		if ( ! empty( $event->id ) ) {
			return $event;
		}

		$request = $this->get_new_translation_request();

		if ( empty( $request ) ) {
			return $event;
		}

		$source = $this->get_post_event( $request['from_post'] );

		return empty( $source->id ) ? $event : new Event( $this->localize_values( $this->get_synced_values( $source ), $request['new_lang'] ) );
	}

	/**
	 * Copy an event's new dates to its translations, whatever wrote them.
	 *
	 * @since 3.17.0
	 *
	 * @param int   $event_id Event ID.
	 * @param array $data     Data that was saved.
	 */
	public function sync_saved_event( $event_id, $data ) {

		// Editor saves sync once, from sync_saved_post().
		if ( doing_action( 'save_post' ) || ! $this->should_sync() || ! array_intersect_key( (array) $data, array_flip( $this->get_synced_fields() ) ) ) {
			return;
		}

		$this->sync_dates_to_translations( sugar_calendar_get_event( $event_id ) );
	}

	/**
	 * Copy a saved event post's dates to its translations.
	 *
	 * @since 3.17.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function sync_saved_post( $post_id ) {

		if ( ! $this->should_sync() || ! post_type_supports( (string) get_post_type( $post_id ), 'events' ) ) {
			return;
		}

		$this->sync_dates_to_translations( $this->get_post_event( $post_id ) );
	}

	/**
	 * Copy an event's dates to the events of its translations.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event Saved event.
	 */
	private function sync_dates_to_translations( $event ) {

		if ( empty( $event->id ) || $event->object_type !== 'post' || ! post_type_supports( (string) $event->object_subtype, 'events' ) ) {
			return;
		}

		if ( ! PolylangApi::can_sync_translations( (int) $event->object_id ) ) {
			return;
		}

		$values           = $this->get_synced_values( $event );
		$this->is_syncing = true;

		foreach ( $this->get_translation_events( $event ) as $language => $translation ) {
			$this->copy_to_translation( $event, $translation, $this->localize_values( $values, $language ) );
		}

		$this->is_syncing = false;
	}

	/**
	 * Write the shared values into a translation's event.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event       Saved event the values come from.
	 * @param Event $translation Translation's event.
	 * @param array $values      Shared values, in the translation's language.
	 */
	protected function copy_to_translation( $event, $translation, $values ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- $event is for subclasses that copy more than the row.

		sugar_calendar_update_event( $translation->id, $values, $translation );
	}

	/**
	 * Adapt the shared values to a translation's language.
	 *
	 * @since 3.17.0
	 *
	 * @param array  $values   Shared values.
	 * @param string $language Language slug of the translation.
	 *
	 * @return array
	 */
	protected function localize_values( $values, $language ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $language is for subclasses with values that point to translated posts.

		return $values;
	}

	/**
	 * Get the events of a saved event's translations.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event Saved event.
	 *
	 * @return Event[] Language slug => event.
	 */
	private function get_translation_events( $event ) {

		$events = [];

		foreach ( pll_get_post_translations( $event->object_id ) as $language => $post_id ) {

			if ( (int) $post_id === (int) $event->object_id ) {
				continue;
			}

			$translation = $this->get_post_event( $post_id );

			if ( ! empty( $translation->id ) ) {
				$events[ $language ] = $translation;
			}
		}

		return $events;
	}

	/**
	 * Get the event fields shared by translations.
	 *
	 * @since 3.17.0
	 *
	 * @return string[]
	 */
	protected function get_synced_fields() {

		return self::DATE_COLUMNS;
	}

	/**
	 * Get an event's shared values.
	 *
	 * @since 3.17.0
	 *
	 * @param Event $event Event.
	 *
	 * @return array
	 */
	private function get_synced_values( $event ) {

		$values = [];

		foreach ( $this->get_synced_fields() as $field ) {
			$values[ $field ] = $event->{$field};
		}

		return $values;
	}

	/**
	 * Get the event of a post, whichever event post type it is.
	 *
	 * @since 3.17.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return Event
	 */
	protected function get_post_event( $post_id ) {

		return sugar_calendar_get_event_by_object( $post_id, 'post', [ 'object_subtype' => get_post_type( $post_id ) ] );
	}

	/**
	 * Get the post a new translation is created from and its language, on Polylang's "+" editor.
	 *
	 * @since 3.17.0
	 *
	 * @return array `from_post` (post ID) and `new_lang` (language slug), or empty.
	 */
	public static function get_new_translation_request() {

		$links = PolylangApi::pll()->links ?? null;

		// Only Polylang's admin links know the "+" request.
		if ( ! is_object( $links ) || ! method_exists( $links, 'get_data_from_new_post_translation_request' ) ) {
			return [];
		}

		// Polylang 3.7 requires the post type; 3.8+ takes no argument and ignores it.
		$data = $links->get_data_from_new_post_translation_request( (string) get_post_type() );

		if ( empty( $data['from_post']->ID ) || empty( $data['new_lang']->slug ) ) {
			return [];
		}

		return [
			'from_post' => (int) $data['from_post']->ID,
			'new_lang'  => (string) $data['new_lang']->slug,
		];
	}

	/**
	 * Whether to copy dates now: Polylang's "Custom fields" sync is on and no copy is running.
	 *
	 * @since 3.17.0
	 *
	 * @return bool
	 */
	private function should_sync() {

		// Our own copies fire the save hooks again.
		return ! $this->is_syncing && PolylangApi::is_custom_fields_sync_on();
	}
}
