<?php

namespace Sugar_Calendar\Integrations;

/**
 * Registry of integration capabilities.
 *
 * Process-wide singleton via instance(). Bookings uses this to power
 * generic consumers (MeetingManager, booking-form rules); Segment 1 of
 * SCE only registers — Segment 2 adds the EventMeetingManager that
 * queries it.
 *
 * @since 3.12.0
 */
class IntegrationCapabilityRegistry {

	/**
	 * Capabilities indexed by interface FQN, then by provider slug.
	 *
	 * @since 3.12.0
	 *
	 * @var array<string, array<string, IntegrationCapabilityInterface>>
	 */
	private $capabilities = [];

	/**
	 * Process-wide singleton accessor.
	 *
	 * @since 3.12.0
	 *
	 * @return self
	 */
	public static function instance(): self {

		static $instance = null;

		if ( $instance === null ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Register a capability under every capability interface it implements.
	 *
	 * @since 3.12.0
	 *
	 * @param IntegrationCapabilityInterface $capability Capability.
	 *
	 * @return void
	 */
	public function register( IntegrationCapabilityInterface $capability ) {

		foreach ( class_implements( $capability ) as $interface ) {

			if ( ! is_subclass_of( $interface, IntegrationCapabilityInterface::class ) && $interface !== IntegrationCapabilityInterface::class ) {
				continue;
			}

			$this->capabilities[ $interface ][ $capability->get_provider_slug() ] = $capability;
		}
	}

	/**
	 * Get all capabilities of a given interface type.
	 *
	 * @since 3.12.0
	 *
	 * @param string $interface Capability interface FQN.
	 *
	 * @return array<string, IntegrationCapabilityInterface>
	 */
	public function get( string $interface ): array {

		return $this->capabilities[ $interface ] ?? [];
	}

	/**
	 * Find a capability by interface + provider slug.
	 *
	 * @since 3.12.0
	 *
	 * @param string $interface     Capability interface FQN.
	 * @param string $provider_slug Provider slug.
	 *
	 * @return IntegrationCapabilityInterface|null
	 */
	public function find( string $interface, string $provider_slug ) {

		return $this->capabilities[ $interface ][ $provider_slug ] ?? null;
	}
}
