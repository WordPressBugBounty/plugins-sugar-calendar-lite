<?php
/**
 * Base contract for a single sc-events ability.
 *
 * @package Sugar_Calendar
 * @subpackage Integrations\Abilities
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Event;
use WP_Error;

/**
 * AbstractAbility — base for a single sc-events ability. Subclasses declare
 * their identity (slug/label/description), schemas, permission rule, and
 * execute logic; this base assembles the wp_register_ability() call (Template
 * Method) and provides shared input/meta/permission/formatting helpers backed
 * by the two injected collaborators.
 *
 * @since 3.12.0
 */
abstract class AbstractAbility {

	/**
	 * Ability namespace prefix.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	protected const NAMESPACE_PREFIX = 'sc-events';

	/**
	 * Ability category slug. Public so the registry can reuse it as the single
	 * source of truth when registering the category.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	public const CATEGORY = 'sc-events';

	/**
	 * Data-access collaborator.
	 *
	 * @since 3.12.0
	 *
	 * @var EventRepository
	 */
	protected $events;

	/**
	 * Output-shaping collaborator.
	 *
	 * @since 3.12.0
	 *
	 * @var Formatter
	 */
	protected $formatter;

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 *
	 * @param EventRepository $events    Data-access collaborator.
	 * @param Formatter       $formatter Output-shaping collaborator.
	 */
	public function __construct( EventRepository $events, Formatter $formatter ) {

		$this->events    = $events;
		$this->formatter = $formatter;
	}

	/**
	 * Ability slug, appended to the namespace prefix (e.g. 'list-events').
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	abstract protected function get_slug(): string;

	/**
	 * Human-readable label.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	abstract protected function get_label(): string;

	/**
	 * Human-readable description.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	abstract protected function get_description(): string;

	/**
	 * JSON-schema for the ability input.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	abstract protected function get_input_schema(): array;

	/**
	 * JSON-schema for the ability output.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	abstract protected function get_output_schema(): array;

	/**
	 * Permission callback.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|WP_Error
	 */
	abstract public function check_permission( $input = null );

	/**
	 * Execute callback.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	abstract public function execute( $input = null );

	/**
	 * Register this ability with the WordPress Abilities API (Template Method).
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function register(): void {

		wp_register_ability(
			static::NAMESPACE_PREFIX . '/' . $this->get_slug(),
			[
				'label'               => $this->get_label(),
				'description'         => $this->get_description(),
				'category'            => static::CATEGORY,
				'execute_callback'    => [ $this, 'execute' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'input_schema'        => $this->get_input_schema(),
				'output_schema'       => $this->get_output_schema(),
				'meta'                => $this->meta(),
			]
		);
	}

	/**
	 * Coerce ability input into an array regardless of source shape.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Raw input from the Abilities API runtime.
	 *
	 * @return array
	 */
	protected function normalize_input( $input ): array {

		if ( is_array( $input ) ) {
			return $input;
		}

		if ( is_object( $input ) ) {
			return (array) $input;
		}

		return [];
	}

	/**
	 * Common ability metadata applied to every registration.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	protected function meta(): array {

		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
			'mcp'          => [
				'public' => true,
			],
		];
	}

	/**
	 * Build the standard forbidden (403) error.
	 *
	 * @since 3.12.0
	 *
	 * @param string $message Error message.
	 *
	 * @return WP_Error
	 */
	protected function forbidden( string $message ): WP_Error {

		return new WP_Error( 'sc_events_forbidden', $message, [ 'status' => 403 ] );
	}

	/**
	 * Coarse capability gate.
	 *
	 * @since 3.12.0
	 *
	 * @param string $cap     Capability to check.
	 * @param string $message Forbidden message when the check fails.
	 *
	 * @return true|WP_Error
	 */
	protected function require_cap( string $cap, string $message ) {

		return current_user_can( $cap ) ? true : $this->forbidden( $message );
	}

	/**
	 * Format an event list into the canonical collection shape with a single
	 * batched calendar lookup. Shared by list-events and search-events.
	 *
	 * @since 3.12.0
	 *
	 * @param Event[] $events Event objects from Event_Query->items.
	 *
	 * @return array
	 */
	protected function format_collection( array $events ): array {

		if ( empty( $events ) ) {
			return [];
		}

		$post_ids = [];

		foreach ( $events as $event ) {
			$post_ids[] = (int) $event->object_id;
		}

		$post_ids     = array_values( array_filter( array_unique( $post_ids ) ) );
		$calendar_map = $this->events->resolve_calendar_map( $post_ids );

		return $this->formatter->events( $events, $calendar_map );
	}
}
