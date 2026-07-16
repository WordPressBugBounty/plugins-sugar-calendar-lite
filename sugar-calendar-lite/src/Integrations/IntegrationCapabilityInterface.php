<?php

namespace Sugar_Calendar\Integrations;

/**
 * Marker interface for capabilities a registered integration can advertise.
 *
 * Sub-interfaces (MeetingProviderInterface, WebhookEventHandlerInterface)
 * extend this with their specific contract.
 *
 * @since 3.12.0
 */
interface IntegrationCapabilityInterface {

	/**
	 * The provider's slug (e.g. 'zoom').
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_provider_slug(): string;

	/**
	 * Human-readable name.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_display_name(): string;

	/**
	 * Is the capability ready to be used right now?
	 *
	 * Typical implementation: integration enabled AND a connection in
	 * 'active' status exists AND credits are available.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_available(): bool;
}
