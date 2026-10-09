<?php
/**
 * Collects the records a tool round resolved.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * EntityHarvester.
 *
 * @since 3.15.0
 */
class EntityHarvester {

	/**
	 * Entity type registry.
	 *
	 * @since 3.15.0
	 *
	 * @var EntityTypes
	 */
	private $types;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param EntityTypes|null $types Entity type registry.
	 */
	public function __construct( ?EntityTypes $types = null ) {

		$this->types = $types ?? new EntityTypes();
	}

	/**
	 * Entities named in this round's tool results, for `TurnState::remember_entities()`.
	 *
	 * Finished turns are compacted out of history, so this is what carries a
	 * resolved id ("the first one") into a later turn.
	 *
	 * @since 3.15.0
	 *
	 * @param array $results Results from ToolCallRunner::run().
	 *
	 * @return array Type => list of `[ 'id', 'name' ]`.
	 */
	public function harvest( array $results ): array {

		$map   = $this->types->harvest_map();
		$found = [];

		foreach ( $results as $result ) {

			$name = $result['name'] ?? '';

			if ( ! empty( $result['is_error'] ) || ! isset( $map[ $name ] ) ) {
				continue;
			}

			$spec = $map[ $name ];

			foreach ( $this->result_rows( $result, $spec['collection'] ) as $row ) {

				if ( ! is_array( $row ) || empty( $row[ $spec['id'] ] ) || ! isset( $row[ $spec['name'] ] ) ) {
					continue;
				}

				$found[ $spec['type'] ][] = [
					'id'   => (int) $row[ $spec['id'] ],
					'name' => (string) $row[ $spec['name'] ],
				];
			}
		}

		return $found;
	}

	/**
	 * The item rows inside one tool result: the named collection, or the whole
	 * output for a single-object ability.
	 *
	 * @since 3.15.0
	 *
	 * @param array       $result     One result from ToolCallRunner::run().
	 * @param string|null $collection Output key holding the list, or null for a single object.
	 *
	 * @return array
	 */
	private function result_rows( array $result, ?string $collection ): array {

		$output = json_decode( (string) ( $result['content'] ?? '' ), true );

		if ( ! is_array( $output ) ) {
			return [];
		}

		if ( $collection === null ) {
			return [ $output ];
		}

		return is_array( $output[ $collection ] ?? null ) ? $output[ $collection ] : [];
	}
}
