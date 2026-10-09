<?php
/**
 * A single Gia AI funnel: its default capability, the abilities it withholds,
 * and the chat shell copy it renders with.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object built by `Funnels`. Not a registry itself — this
 * class holds one funnel's data, `Funnels` decides which funnels exist.
 *
 * @since 3.15.0
 */
class Funnel {

	/**
	 * Funnel slug.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * The default capability that gates starting a turn in this funnel,
	 * before the `sugar_calendar_ai_funnel_capability(ies)` filters run.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $capability;

	/**
	 * Ability name => the reason string the model reads when it tries an
	 * ability this funnel withholds.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $blocked_abilities;

	/**
	 * Chat shell copy for this funnel (label, icon, nav_title, greeting,
	 * description, placeholder and, where it applies, tip/properties/
	 * properties_line). Already translated by the time it reaches here.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $ui;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param string $slug              Funnel slug.
	 * @param string $capability        Default capability that gates it.
	 * @param array  $blocked_abilities Ability name => reason, withheld in this funnel.
	 * @param array  $ui                Chat shell copy for this funnel.
	 */
	public function __construct( string $slug, string $capability, array $blocked_abilities, array $ui ) {

		$this->slug              = $slug;
		$this->capability        = $capability;
		$this->blocked_abilities = $blocked_abilities;
		$this->ui                = $ui;
	}

	/**
	 * The funnel slug.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function slug(): string {

		return $this->slug;
	}

	/**
	 * The default capability that gates this funnel.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function capability(): string {

		return $this->capability;
	}

	/**
	 * Ability name => reason, withheld in this funnel.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function blocked_abilities(): array {

		return $this->blocked_abilities;
	}

	/**
	 * The chat shell copy for this funnel, with its slug folded in.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function ui(): array {

		return array_merge( [ 'slug' => $this->slug ], $this->ui );
	}
}
