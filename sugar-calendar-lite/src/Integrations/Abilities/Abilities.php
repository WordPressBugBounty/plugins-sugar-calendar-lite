<?php
/**
 * WordPress Abilities API integration for Sugar Calendar Events (SCE).
 *
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
use Throwable;

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
	 * Deliberately does NOT build the ability list here. Core bootstraps on
	 * `plugins_loaded` priority 8 ("before priority 10 to make sure add-ons
	 * are loaded after us" — `requirements-check.php`), so this method runs
	 * before the Event Ticketing (`plugins_loaded:30`) and RSVP
	 * (`plugins_loaded:10`) add-ons have hooked their own
	 * `sugar_calendar_abilities_register` contribution. Building the list
	 * here would fire that filter too early and silently drop both add-ons'
	 * abilities. `register_abilities()` builds the list lazily instead, on
	 * `wp_abilities_api_init` — WordPress's own Abilities API hook, which
	 * fires well after every plugin's `plugins_loaded` callback has run.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function init(): void {

		if ( ! self::is_available() ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init',            [ $this, 'register_abilities' ] );
	}

	/**
	 * Whether the host WordPress build ships the Abilities API.
	 *
	 * Single source of truth for the `wp_register_ability` gate — both this
	 * class and the Tools page "AI" tab (`ToolsAiTab`) call this method
	 * rather than checking `function_exists()` independently.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	public static function is_available(): bool {

		return function_exists( 'wp_register_ability' );
	}

	/**
	 * Construct the shared collaborators and the six core event/calendar
	 * abilities, then let Pro features and separate add-on plugins
	 * (Event Ticketing, RSVP) contribute their own abilities via the
	 * `sugar_calendar_abilities_register` filter.
	 *
	 * A module that isn't active never calls that filter — its own bootstrap
	 * code, which is the only place that hooks it, simply never runs. So
	 * there is no `is_pro()`/`class_exists()` gate here: the filter's
	 * absence from a given module IS the gate. This class never references
	 * `Sugar_Calendar\Pro\*` or any add-on plugin's classes.
	 *
	 * @since 3.12.0
	 *
	 * @return AbstractAbility[]
	 */
	private function build_abilities(): array {

		$events    = new EventRepository();
		$formatter = new Formatter();

		$abilities = [
			new ListEvents( $events, $formatter ),
			new GetEvent( $events, $formatter ),
			new SearchEvents( $events, $formatter ),
			new ListCalendars( $events, $formatter ),
			new GetCalendar( $events, $formatter ),
			new GetEventStats( $events, $formatter ),
		];

		/**
		 * Filters the list of registered abilities. Pro features and
		 * separate add-on plugins append their own `AbstractAbility`
		 * instances here from their own boot — see
		 * docs/superpowers/specs/2026-07-16-abilities-venues-speakers-rsvp-design.md.
		 *
		 * @since 3.13.0
		 *
		 * @param AbstractAbility[] $abilities Abilities registered so far.
		 * @param array             $context   Shared collaborators: `events` (EventRepository), `formatter` (Formatter).
		 */
		try {
			$filtered = apply_filters(
				'sugar_calendar_abilities_register',
				$abilities,
				[
					'events'    => $events,
					'formatter' => $formatter,
				]
			);
		} catch ( Throwable $e ) {
			// A malformed third-party contribution must not take down every
			// core ability alongside it — fall back to the pre-filter list.
			return $abilities;
		}

		// A malformed third-party contribution returning a non-array must not
		// take down every core ability alongside it — fall back to the
		// pre-filter list rather than let register_abilities()'s foreach
		// TypeError on a non-iterable.
		return is_array( $filtered ) ? $filtered : $abilities;
	}

	/**
	 * Coerce a `sugar_calendar_abilities_register` filter callback's
	 * `$abilities` argument to an array. Shared by every Pro feature/add-on
	 * provider that hooks this filter — `$abilities` isn't type-hinted at
	 * the filter callback level (a malformed earlier-priority callback on
	 * the same filter could hand a contributor a non-array; type-hinting
	 * `array` there would `TypeError` instead of degrading gracefully), so
	 * each contributor coerces it before appending its own abilities.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $abilities Abilities registered so far, per the filter.
	 *
	 * @return AbstractAbility[]
	 */
	public static function coerce_contributed_abilities( $abilities ): array {

		return is_array( $abilities ) ? $abilities : [];
	}

	/**
	 * Register the `sc-events` ability category.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function register_category(): void {

		// The Abilities API ships with WordPress 6.9+. Guard the call so the
		// static Plugin Check (and older WP) never hits an undefined function;
		// the hook that invokes this only fires when the API is present.
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

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
	 * Build the ability list, then register every ability. Building the list
	 * here — on `wp_abilities_api_init` rather than at `init()` time — is
	 * what guarantees every Pro feature and add-on plugin has already had
	 * the chance to hook `sugar_calendar_abilities_register`, regardless of
	 * its own `plugins_loaded` priority (see `init()`'s docblock).
	 *
	 * A malformed contribution from a Pro feature or add-on plugin must not
	 * take down the core abilities registered alongside it in this same
	 * loop — this try/catch is new defensive code, justified specifically
	 * because that filter turns "core-only, trusted code" into "core + Pro
	 * + separate add-on plugins."
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {

		$this->abilities = $this->build_abilities();

		foreach ( $this->abilities as $ability ) {

			try {
				$ability->register();
			} catch ( Throwable $e ) {
				// Best-effort — one malformed ability must not take down the rest.
				continue;
			}
		}
	}
}
