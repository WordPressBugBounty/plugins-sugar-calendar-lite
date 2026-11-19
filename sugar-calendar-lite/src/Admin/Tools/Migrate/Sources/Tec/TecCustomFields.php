<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecEvent;
use Sugar_Calendar\Options;

/**
 * Copies TEC's additional fields onto migrated events, when custom fields are on.
 *
 * @since 3.18.0
 */
class TecCustomFields {

	/**
	 * The TEC additional field keys, once read.
	 *
	 * @since 3.18.0
	 *
	 * @var string[]|null
	 */
	private $names = null;

	/**
	 * Copy a TEC event's additional fields to its Sugar Calendar event.
	 *
	 * @since 3.18.0
	 *
	 * @param int      $sc_event_post_id The Sugar Calendar event post ID.
	 * @param TecEvent $tec_event        The TEC event.
	 */
	public function import( $sc_event_post_id, TecEvent $tec_event ) {

		$names = $this->get_names();

		if ( empty( $names ) || ! $tec_event->has_meta() ) {
			return;
		}

		foreach ( $names as $name ) {
			$value = $tec_event->get_meta( $name );

			if ( $value === false ) {
				continue;
			}

			// update_post_meta() unslashes, so a slash-free value keeps its line breaks and backslashes.
			update_post_meta( $sc_event_post_id, sanitize_key( $name ), wp_slash( $value ) );
		}
	}

	/**
	 * The TEC additional field keys, cached for 12 hours.
	 *
	 * @since 3.18.0
	 *
	 * @return string[]
	 */
	private function get_names() {

		if ( ! is_null( $this->names ) ) {
			return $this->names;
		}

		$cached = get_transient( 'sc_migration_tec_custom_fields' );

		if ( $cached !== false ) {
			$this->names = json_decode( $cached, true ) ?? [];

			return $this->names;
		}

		$tec_options = Options::get( 'custom_fields' ) ? get_option( 'tribe_events_calendar_options' ) : [];
		$this->names = [];

		if ( ! empty( $tec_options['custom-fields'] ) ) {
			$this->names = wp_list_pluck( $tec_options['custom-fields'], 'name' );

			set_transient( 'sc_migration_tec_custom_fields', wp_json_encode( $this->names ), 12 * HOUR_IN_SECONDS );
		}

		return $this->names;
	}
}
