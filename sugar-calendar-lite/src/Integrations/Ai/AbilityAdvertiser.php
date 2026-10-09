<?php
/**
 * Builds the `abilities` payload the assistant endpoint expects.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\Abilities;
use Sugar_Calendar\Integrations\Abilities\AbstractAbility;

/**
 * AbilityAdvertiser — advertises every `sc-events/` ability this site can
 * execute.
 *
 * The list is a capability SIGNAL, not a request: the endpoint allowlists the
 * names against the funnel's scopes and replaces our descriptions with its own
 * prompt text, so an ability no scope claims is dropped silently and an
 * unadvertised one can never be called.
 *
 * Abilities are advertised without a per-user permission pre-check. Several
 * abilities decide permission from their input (`create-tag` keys off the
 * calendar), so a check with no input would be wrong in both directions; the
 * ability re-checks at execution time and the prompt tells the model to report
 * a refusal plainly.
 *
 * @since 3.15.0
 */
class AbilityAdvertiser {

	/**
	 * Ability-name prefix that marks an ability as ours.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	public const PREFIX = AbstractAbility::CATEGORY . '/';

	/**
	 * Advertise the site's `sc-events/` abilities.
	 *
	 * @since 3.15.0
	 *
	 * @return array List of `[ name, description, input_schema ]`. Empty when the
	 *               host WordPress build ships no Abilities API.
	 */
	public function advertise(): array {

		if ( ! Abilities::is_available() || ! function_exists( 'wp_get_abilities' ) ) {
			return [];
		}

		$advertised = [];

		foreach ( wp_get_abilities() as $ability ) {

			$name = $ability->get_name();

			if ( strpos( $name, self::PREFIX ) !== 0 ) {
				continue;
			}

			$advertised[] = [
				'name'         => $name,
				'description'  => $ability->get_description(),
				'input_schema' => $ability->get_input_schema(),
			];
		}

		return $advertised;
	}
}
