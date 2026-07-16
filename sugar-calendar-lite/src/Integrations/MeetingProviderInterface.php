<?php

namespace Sugar_Calendar\Integrations;

use WP_Error;

/**
 * Capability contract for online-meeting providers.
 *
 * Segment 1 defines this; Segment 2 wires EventMeetingManager to call
 * its methods on save_post / before_delete_post.
 *
 * @since 3.12.0
 */
interface MeetingProviderInterface extends IntegrationCapabilityInterface {

	/**
	 * Create the provider-side meeting for an event.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array{provider:string,meeting_id:string,join_url:string,password?:string}|WP_Error
	 */
	public function create_meeting( $event );

	/**
	 * Update an existing provider-side meeting.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return array|WP_Error
	 */
	public function update_meeting( $event );

	/**
	 * Delete the provider-side meeting.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return true|WP_Error
	 */
	public function delete_meeting( $event );

	/**
	 * "Start as host" link for the event's meeting, or '' when none exists.
	 *
	 * Provider-specific URL shape (Zoom: https://zoom.us/s/{meeting_id}).
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string
	 */
	public function get_host_url( $event ): string;

	/**
	 * Meta keys this provider stores in wp_sc_eventmeta.
	 *
	 * @since 3.12.0
	 *
	 * @return string[]
	 */
	public function get_meeting_meta_keys(): array;
}
