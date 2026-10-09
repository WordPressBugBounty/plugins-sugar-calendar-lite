<?php
/**
 * The Gia AI funnel registry: slugs, default capabilities, blocked abilities
 * and chat shell copy, defined once per funnel instead of spread across
 * `Chat`, `Availability`, `ChatShell` and `Turn`.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Builds and looks up the core `Funnel` instances.
 *
 * Every string here goes through `__()` on each call rather than being
 * cached, because translation functions must not run before `init`, and a
 * static build the first time `all()` runs would risk running too early.
 *
 * @since 3.15.0
 */
class Funnels {

	/**
	 * Funnel slug: create one event. Writes drafts.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const CREATE_EVENT = 'create_event';

	/**
	 * Funnel slug: change events that already exist. Writes.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const EDIT_EVENTS = 'edit_events';

	/**
	 * Funnel slug: answer questions. Read-only — the endpoint advertises no
	 * write ability to it.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const ASK_EVENTS = 'ask_events';

	/**
	 * The core funnels, keyed by slug.
	 *
	 * @since 3.15.0
	 *
	 * @return Funnel[]
	 */
	public static function all(): array {

		return [
			self::CREATE_EVENT => self::create_event(),
			self::EDIT_EVENTS  => self::edit_events(),
			self::ASK_EVENTS   => self::ask_events(),
		];
	}

	/**
	 * One core funnel by slug.
	 *
	 * A funnel added only through the `sugar_calendar_ai_funnel_capabilities`
	 * filter has no entry here; callers treat null as "nothing to block, no
	 * chat shell copy to show" rather than an error.
	 *
	 * @since 3.15.0
	 *
	 * @param string $slug Funnel slug.
	 *
	 * @return Funnel|null
	 */
	public static function get( string $slug ) {

		return self::all()[ $slug ] ?? null;
	}

	/**
	 * The core funnel => default capability map, unfiltered.
	 *
	 * `Availability::capability_map()` layers the
	 * `sugar_calendar_ai_funnel_capabilities` filter on top of this.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public static function capabilities(): array {

		$capabilities = [];

		foreach ( self::all() as $slug => $funnel ) {
			$capabilities[ $slug ] = $funnel->capability();
		}

		return $capabilities;
	}

	/**
	 * The create-event funnel.
	 *
	 * @since 3.15.0
	 *
	 * @return Funnel
	 */
	private static function create_event(): Funnel {

		return new Funnel(
			self::CREATE_EVENT,
			'create_events',
			[],
			[
				'label'           => __( 'Create a new event', 'sugar-calendar-lite' ),
				'icon'            => 'plus',
				'nav_title'       => __( 'Create New Event with Gia', 'sugar-calendar-lite' ),
				'greeting'        => __( 'What kind of Event do you want to create?', 'sugar-calendar-lite' ),
				'description'     => __( 'Select any properties that apply to your event and then describe the other specific details like date, time, duration, and cost.', 'sugar-calendar-lite' ),
				'placeholder'     => __( 'Create an event for a yoga class next Friday, from 5pm to 7pm at the community center, for the elderly, specializing in joint movement and stamina. Add it to my default calendar.', 'sugar-calendar-lite' ),
				'tip'             => self::tip(),
				'properties'      => self::event_properties(),
				/* translators: %1$s - comma-separated list of the selected event properties. */
				'properties_line' => __( 'Event features: %1$s.', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * The edit-events funnel.
	 *
	 * @since 3.15.0
	 *
	 * @return Funnel
	 */
	private static function edit_events(): Funnel {

		return new Funnel(
			self::EDIT_EVENTS,
			'edit_events',
			[
				// The relay's "never create" rule for this funnel is prompt-only,
				// and create-event stays in its tool list. Blocking it here also
				// keeps `TurnState::$created_event` null, so `createdEvent` never
				// rides an edit round, where the relay would misread it.
				AbilityAdvertiser::PREFIX . 'create-event' => __( 'Not executed: this conversation edits existing events and cannot create one. Tell the user to start a "Create a new event" chat for that.', 'sugar-calendar-lite' ),
			],
			[
				'label'           => __( 'Edit existing event', 'sugar-calendar-lite' ),
				'icon'            => 'pen',
				'nav_title'       => __( 'Edit Events', 'sugar-calendar-lite' ),
				'greeting'        => __( 'What would you like to edit?', 'sugar-calendar-lite' ),
				'description'     => __( 'Select any suggestions that apply to your requirements and then describe the other specific details like date, time, duration, and cost.', 'sugar-calendar-lite' ),
				'placeholder'     => __( 'Help me improve my recent events description, title and pricing to be more enticing', 'sugar-calendar-lite' ),
				'tip'             => self::tip(),
				'properties'      => self::edit_properties(),
				/* translators: %1$s - comma-separated list of the selected edit suggestions. */
				'properties_line' => __( 'Edit type: %1$s.', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * The ask-events funnel.
	 *
	 * @since 3.15.0
	 *
	 * @return Funnel
	 */
	private static function ask_events(): Funnel {

		return new Funnel(
			self::ASK_EVENTS,
			'read_calendar',
			[],
			[
				'label'           => __( 'Ask about events', 'sugar-calendar-lite' ),
				'icon'            => 'chart-line',
				'nav_title'       => __( 'Ask About Events', 'sugar-calendar-lite' ),
				'greeting'        => __( 'What would you like to know about your events?', 'sugar-calendar-lite' ),
				'description'     => __( 'Select any properties that apply to your requirements and then describe what you\'d like to know.', 'sugar-calendar-lite' ),
				'placeholder'     => __( 'Tell me about how my recent events have performed compared to previous ones', 'sugar-calendar-lite' ),
				'tip'             => self::tip(),
				'properties'      => self::ask_properties(),
				/* translators: %1$s - comma-separated list of the selected topics. */
				'properties_line' => __( 'Focus on: %1$s.', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * The "press Tab" tip shown under each funnel's start composer. Rendered as
	 * HTML by the client, so the translation is held to the same tag.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	private static function tip(): string {

		return wp_kses(
			sprintf(
				/* translators: %1$s and %2$s are opening/closing <strong> tags around "Tip"; %3$s and %4$s around "Tab". */
				__( '%1$sTip%2$s: press %3$sTab%4$s to start from this example.', 'sugar-calendar-lite' ),
				'<strong>',
				'</strong>',
				'<strong>',
				'</strong>'
			),
			[ 'strong' => [] ]
		);
	}

	/**
	 * The create funnel's start-screen property chips: hints the user can add
	 * to the first prompt.
	 *
	 * A chip needs a write path in the create-event schema; a feature whose write path
	 * isn't always available adds its own chip through the filter (ticketing, Pro's Recurring).
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private static function event_properties(): array {

		$properties = [
			[
				'slug'  => 'online',
				'label' => __( 'Online', 'sugar-calendar-lite' ),
				'icon'  => 'globe',
			],
			[
				'slug'  => 'free',
				'label' => __( 'Free Event', 'sugar-calendar-lite' ),
				'icon'  => 'users',
			],
			[
				'slug'  => 'featured',
				'label' => __( 'Featured Event', 'sugar-calendar-lite' ),
				'icon'  => 'star',
			],
			[
				'slug'  => 'all_day',
				'label' => __( 'All Day', 'sugar-calendar-lite' ),
				'icon'  => 'sun',
			],
		];

		/**
		 * Filter the create funnel's start-screen property chips.
		 *
		 * @since 3.15.0
		 *
		 * @param array $properties List of `slug`, `label`, `icon`.
		 */
		$properties = apply_filters( 'sugar_calendar_integrations_ai_funnels_event_properties', $properties );

		return is_array( $properties ) ? array_values( $properties ) : [];
	}

	/**
	 * The edit funnel's suggestion chips (Figma 14786:85576). Prompt text
	 * only, like the create chips; the relay decides what "Bulk Edit" means.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private static function edit_properties(): array {

		return [
			[
				'slug'  => 'improve',
				'label' => __( 'Suggest Improvements', 'sugar-calendar-lite' ),
				'icon'  => 'thumbs-up',
			],
			[
				'slug'  => 'bulk',
				'label' => __( 'Bulk Edit', 'sugar-calendar-lite' ),
				'icon'  => 'pen',
			],
		];
	}

	/**
	 * The ask funnel's topic chips (Figma 14786:83001). Prompt text only, like
	 * the create chips; a topic whose data needs an add-on (ticketing) adds its
	 * own chip through the filter.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private static function ask_properties(): array {

		$properties = [
			[
				'slug'  => 'registrations',
				'label' => __( 'Registrations', 'sugar-calendar-lite' ),
				'icon'  => 'users',
			],
			[
				'slug'  => 'upcoming',
				'label' => __( 'Upcoming Events', 'sugar-calendar-lite' ),
				'icon'  => 'calendar-days',
			],
			[
				'slug'  => 'last_30_days',
				'label' => __( 'Last 30 Days', 'sugar-calendar-lite' ),
				'icon'  => 'clock',
			],
		];

		/**
		 * Filter the ask funnel's start-screen topic chips.
		 *
		 * @since 3.15.0
		 *
		 * @param array $properties List of `slug`, `label`, `icon`.
		 */
		$properties = apply_filters( 'sugar_calendar_integrations_ai_funnels_ask_properties', $properties );

		return is_array( $properties ) ? array_values( $properties ) : [];
	}
}
