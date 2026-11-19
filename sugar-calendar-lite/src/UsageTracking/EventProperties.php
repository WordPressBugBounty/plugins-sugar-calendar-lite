<?php

namespace Sugar_Calendar\UsageTracking;

use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\MeetingProviderInterface;
use Sugar_Calendar\Integrations\OnlineMeeting;

/**
 * Properties shared by the event Product Events (`event_created`, `event_published`).
 *
 * Both events describe the saved event the same way, so the keys, their filter and
 * the "report this event at all" check live here once.
 *
 * @since 3.18.0
 */
class EventProperties {

	/**
	 * Whether this event is reported at all; Pro drops detached occurrences.
	 *
	 * @since 3.18.0
	 *
	 * @param int $event_id Sugar Calendar event ID.
	 *
	 * @return bool
	 */
	public function should_track( $event_id ) {

		/**
		 * Filters whether to report this event creation at all.
		 *
		 * @since 3.15.0
		 *
		 * @param bool $track    Whether to report it.
		 * @param int  $event_id Sugar Calendar event ID.
		 */
		return (bool) apply_filters( 'sugar_calendar_usage_tracking_product_events_track_event_created', true, $event_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Build the properties of one event.
	 *
	 * @since 3.18.0
	 *
	 * @param object      $sc_event    Sugar Calendar event.
	 * @param string|null $created_via How the event was created, left out when null.
	 *
	 * @return array
	 */
	public function build( $sc_event, $created_via = null ) {

		$event_id   = (int) $sc_event->id;
		$properties = $this->base_properties( $sc_event );

		if ( $created_via !== null ) {
			$properties['created_via'] = $created_via;
		}

		/**
		 * Filters the `event_created` properties, for data only Pro or an add-on can read.
		 *
		 * @since 3.15.0
		 *
		 * @param array $properties Event properties.
		 * @param int   $event_id   Sugar Calendar event ID.
		 */
		return (array) apply_filters( 'sugar_calendar_usage_tracking_product_events_event_created_properties', $properties, $event_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * The keys every event has, before the creation path and Pro's additions.
	 *
	 * @since 3.18.0
	 *
	 * @param object $sc_event Sugar Calendar event.
	 *
	 * @return array
	 */
	private function base_properties( $sc_event ) {

		$event_id = (int) $sc_event->id;

		return [
			'event_id'           => $event_id,
			'calendar_id'        => $this->get_first_calendar_id( $sc_event ),
			'status'             => $sc_event->status,
			'is_recurring'       => ! empty( $sc_event->recurrence ),
			'has_venue'          => ! empty( $sc_event->venue_id ),
			'has_description'    => ! empty( $sc_event->content ),
			'has_ticketing'      => ! empty( get_event_meta( $event_id, 'tickets', true ) ),
			'has_rsvp'           => ! empty( get_event_meta( $event_id, 'rsvp_enable', true ) ),
			'has_speaker'        => false,
			'recurrence_type'    => $this->get_recurrence_type( $sc_event ),
			'online_meeting'     => $this->get_online_meeting( $event_id ),
			'has_tags'           => $this->has_tags( $sc_event ),
			'has_custom_url'     => ! empty( get_event_meta( $event_id, 'url', true ) ),
			'ticket_types_count' => 0,
		];
	}

	/**
	 * First calendar assigned to the event, or '' when none.
	 *
	 * @since 3.15.0
	 *
	 * @param object $sc_event Sugar Calendar event.
	 *
	 * @return int|string
	 */
	private function get_first_calendar_id( $sc_event ) {

		if ( empty( $sc_event->object_id ) ) {
			return '';
		}

		$terms = wp_get_object_terms( $sc_event->object_id, 'sc_event_category', [ 'fields' => 'ids' ] );

		return ! is_wp_error( $terms ) && ! empty( $terms ) ? $terms[0] : '';
	}

	/**
	 * Recurrence type: none, advanced, or the frequency.
	 *
	 * @since 3.15.0
	 *
	 * @param object $sc_event Sugar Calendar event.
	 *
	 * @return string
	 */
	private function get_recurrence_type( $sc_event ) {

		if ( empty( $sc_event->recurrence ) ) {
			return 'none';
		}

		foreach ( SnapshotData::ADVANCED_RECURRENCE_META_KEYS as $meta_key ) {
			if ( ! empty( get_event_meta( $sc_event->id, $meta_key, true ) ) ) {
				return 'advanced';
			}
		}

		return sanitize_key( $sc_event->recurrence );
	}

	/**
	 * Online meeting provider slug: 'none', 'custom', a registered meeting provider, or 'other'.
	 *
	 * @since 3.15.0
	 *
	 * @param int $event_id Sugar Calendar event ID.
	 *
	 * @return string
	 */
	private function get_online_meeting( $event_id ) {

		$provider = sanitize_key( (string) get_event_meta( $event_id, 'online_provider', true ) );

		if ( $provider === '' ) {
			return 'none';
		}

		if (
			$provider === OnlineMeeting::PROVIDER_CUSTOM ||
			IntegrationCapabilityRegistry::instance()->find( MeetingProviderInterface::class, $provider ) !== null
		) {
			return $provider;
		}

		return 'other';
	}

	/**
	 * Whether the event's post has any tag.
	 *
	 * @since 3.15.0
	 *
	 * @param object $sc_event Sugar Calendar event.
	 *
	 * @return bool
	 */
	private function has_tags( $sc_event ) {

		if ( empty( $sc_event->object_id ) ) {
			return false;
		}

		$terms = wp_get_object_terms( $sc_event->object_id, TagsHelpers::get_tags_taxonomy_id(), [ 'fields' => 'ids' ] );

		return ! is_wp_error( $terms ) && ! empty( $terms );
	}
}
