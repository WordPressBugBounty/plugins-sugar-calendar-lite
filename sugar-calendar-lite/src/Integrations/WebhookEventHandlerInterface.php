<?php

namespace Sugar_Calendar\Integrations;

/**
 * Capability contract for handlers of relay-forwarded webhook events.
 *
 * Segment 3 wires IncomingWebhookHandler to dispatch by provider slug.
 *
 * @since 3.12.0
 */
interface WebhookEventHandlerInterface extends IntegrationCapabilityInterface {

	/**
	 * Process a single webhook payload from the relay.
	 *
	 * @since 3.12.0
	 *
	 * @param array $payload Decoded JSON payload.
	 *
	 * @return void
	 */
	public function handle_event( array $payload ): void;
}
