<?php

namespace Sugar_Calendar\Integrations;

/**
 * Read API for an event's online-meeting details + "Show to" visibility.
 *
 * Provider-agnostic: details key off the generic `online_provider` /
 * `join_url` event meta, with the display name resolved from the capability
 * registry. Every front-end / email / receipt surface that shows the Join
 * Link reads through here, so the "does a meeting exist?" and "who may see
 * it?" rules live in exactly one place.
 *
 * @since 3.12.0
 */
class OnlineMeeting {

	/**
	 * "Show to" value: link is public to all visitors.
	 *
	 * @since 3.12.0
	 */
	const VISIBILITY_EVERYONE = 'everyone';

	/**
	 * "Show to" value: link is restricted to verified attendees.
	 *
	 * @since 3.12.0
	 */
	const VISIBILITY_ATTENDEES = 'attendees';

	/**
	 * `online_provider` value for a manually-entered Custom Link.
	 *
	 * Not a registered provider — has no relay, credits, meeting id, password,
	 * name, or icon. Its URL lives in the `custom_link_url` event meta.
	 *
	 * @since 3.12.0
	 */
	const PROVIDER_CUSTOM = 'custom';

	/**
	 * Resolve the online-meeting details for an event, or null when none.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object (anything exposing ->id).
	 *
	 * @return array|null {
	 *     @type string $provider_slug Provider slug (e.g. 'zoom').
	 *     @type string $join_url      Attendee join URL.
	 *     @type string $meeting_id    Provider meeting id.
	 *     @type string $password      Meeting password ('' when none).
	 *     @type string $provider_name Human-readable provider name.
	 *     @type string $icon_url      Provider icon URL.
	 * }
	 */
	public static function for_event( $event ) {

		$event_id = is_object( $event ) && ! empty( $event->id ) ? (int) $event->id : 0;

		if ( $event_id === 0 ) {
			return null;
		}

		$provider_slug = (string) get_event_meta( $event_id, 'online_provider', true );

		if ( $provider_slug === '' ) {
			return null;
		}

		// Custom Link: a manually-entered URL, not a registered provider. The URL
		// lives in `custom_link_url`; there is no meeting id, password, name, or icon.
		if ( $provider_slug === self::PROVIDER_CUSTOM ) {

			$custom_url = (string) get_event_meta( $event_id, 'custom_link_url', true );

			if ( $custom_url === '' ) {
				return null;
			}

			return [
				'provider_slug' => self::PROVIDER_CUSTOM,
				'join_url'      => $custom_url,
				'meeting_id'    => '',
				'password'      => '',
				'provider_name' => '',
				'icon_url'      => '',
			];
		}

		$join_url = (string) get_event_meta( $event_id, 'join_url', true );

		// No meeting unless both a provider and a join URL are present.
		if ( $join_url === '' ) {
			return null;
		}

		$provider = IntegrationCapabilityRegistry::instance()->find(
			MeetingProviderInterface::class,
			$provider_slug
		);

		$provider_name = $provider ? $provider->get_display_name() : __( 'Online', 'sugar-calendar-lite' );
		$icon_url      = SC_PLUGIN_ASSETS_URL . 'images/integrations/integration-' . $provider_slug . '.png';

		return [
			'provider_slug' => $provider_slug,
			'join_url'      => $join_url,
			'meeting_id'    => (string) get_event_meta( $event_id, 'meeting_id', true ),
			'password'      => (string) get_event_meta( $event_id, 'meeting_password', true ),
			'provider_name' => $provider_name,
			'icon_url'      => $icon_url,
		];
	}

	/**
	 * The "Show to" intent for the event's meeting.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string self::VISIBILITY_EVERYONE | self::VISIBILITY_ATTENDEES (defaults attendees).
	 */
	public static function visibility( $event ) {

		$event_id = is_object( $event ) && ! empty( $event->id ) ? (int) $event->id : 0;

		$value = $event_id ? (string) get_event_meta( $event_id, 'online_visibility', true ) : '';

		return $value === self::VISIBILITY_EVERYONE ? self::VISIBILITY_EVERYONE : self::VISIBILITY_ATTENDEES;
	}

	/**
	 * Should the Join Link be shown publicly to any visitor on the event page?
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return bool
	 */
	public static function is_public( $event ) {

		return self::visibility( $event ) === self::VISIBILITY_EVERYONE
			&& self::for_event( $event ) !== null;
	}
}
