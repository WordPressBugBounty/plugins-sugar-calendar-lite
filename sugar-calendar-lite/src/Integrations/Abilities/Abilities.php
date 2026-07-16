<?php
/**
 * WordPress Abilities API integration for Sugar Calendar Events (SCE).
 *
 * @package Sugar_Calendar
 * @subpackage Integrations\Abilities
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\Ability\GetCalendar;
use Sugar_Calendar\Integrations\Abilities\Ability\GetEvent;
use Sugar_Calendar\Integrations\Abilities\Ability\GetEventStats;
use Sugar_Calendar\Integrations\Abilities\Ability\ListCalendars;
use Sugar_Calendar\Integrations\Abilities\Ability\ListEvents;
use Sugar_Calendar\Integrations\Abilities\Ability\SearchEvents;

/**
 * Abilities — registry for the read-only `sc-events/` abilities. Builds the
 * shared collaborators, instantiates each ability, and registers the category
 * and the abilities with the WordPress Abilities API. Silent no-op when the
 * API is not loaded.
 *
 * See docs/superpowers/specs/2026-06-18-sce-abilities-api-oop-refactor-design.md.
 *
 * @since 3.12.0
 */
class Abilities {

	/**
	 * The registered ability instances.
	 *
	 * @since 3.12.0
	 *
	 * @var AbstractAbility[]
	 */
	private $abilities = [];

	/**
	 * Initialize the integration. Silent no-op when the Abilities API is not loaded.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function init(): void {

		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->abilities = $this->build_abilities();

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init',            [ $this, 'register_abilities' ] );
	}

	/**
	 * Construct the shared collaborators and the six ability instances.
	 *
	 * @since 3.12.0
	 *
	 * @return AbstractAbility[]
	 */
	private function build_abilities(): array {

		$events    = new EventRepository();
		$formatter = new Formatter();

		return [
			new ListEvents( $events, $formatter ),
			new GetEvent( $events, $formatter ),
			new SearchEvents( $events, $formatter ),
			new ListCalendars( $events, $formatter ),
			new GetCalendar( $events, $formatter ),
			new GetEventStats( $events, $formatter ),
		];
	}

	/**
	 * Register the `sc-events` ability category.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function register_category(): void {

		wp_register_ability_category(
			AbstractAbility::CATEGORY,
			[
				'label'       => esc_html__( 'Sugar Calendar — Events', 'sugar-calendar-lite' ),
				'description' => esc_html__(
					'Read-only abilities for discovering events and calendars in Sugar Calendar.',
					'sugar-calendar-lite'
				),
			]
		);
	}

	/**
	 * Register every ability.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {

		foreach ( $this->abilities as $ability ) {
			$ability->register();
		}
	}
}
