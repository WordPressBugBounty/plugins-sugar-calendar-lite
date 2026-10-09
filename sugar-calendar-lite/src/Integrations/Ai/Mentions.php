<?php
/**
 * Records a user can insert into a Gia AI prompt.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Throwable;

/**
 * The data types behind the composer's "+" menu, and the server-side
 * resolution of the records a prompt points at.
 *
 * @since 3.15.0
 */
class Mentions {

	/**
	 * Most mentions resolved per turn; each one runs a `get-*` ability.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_PER_TURN = 10;

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
	 * Insertable types, in dropdown order.
	 *
	 * @since 3.15.0
	 *
	 * @return array Entity type => row (`label`, `icon`, `position`, `list`, `list_input`,
	 *               `search`, `search_input`, `get`, `collection`, `name`).
	 */
	public function types(): array {

		return $this->types->menu_types();
	}

	/**
	 * Types the current user can browse: every ability registered, and the list runnable.
	 *
	 * @since 3.15.0
	 *
	 * @return array Entity type => row.
	 */
	public function available_types(): array {

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return [];
		}

		$available = [];

		foreach ( $this->types() as $type => $row ) {

			if ( ! wp_has_ability( $row['list'] ) || ! wp_has_ability( $row['search'] ) || ! wp_has_ability( $row['get'] ) ) {
				continue;
			}

			// Calendars and tags need manage_event_calendars, which a Contributor who can open Gia lacks.
			if ( wp_get_ability( $row['list'] )->check_permissions( $row['list_input'] ) !== true ) {
				continue;
			}

			$available[ $type ] = $row;
		}

		return $available;
	}

	/**
	 * The records a new conversation starts out knowing, from each seeded type's list ability.
	 *
	 * Lets the first round offer calendar and venue names as chips without a listing
	 * round of its own. A type the user cannot list is skipped, as in the insert menu.
	 *
	 * @since 3.15.0
	 *
	 * @return array Entity type => list of `[ 'id', 'name' ]`, for `TurnState::remember_entities()`.
	 */
	public function seed(): array {

		$seeded = [];

		foreach ( $this->available_types() as $type => $row ) {

			if ( ! $row['seed'] ) {
				continue;
			}

			try {
				$output = wp_get_ability( $row['list'] )->execute( array_merge( $row['list_input'], [ 'number' => EntityMemory::MAX_ENTITIES_PER_TYPE ] ) );
			} catch ( Throwable $e ) {
				continue;
			}

			$items = is_array( $output ) && is_array( $output[ $row['collection'] ] ?? null ) ? $output[ $row['collection'] ] : [];

			foreach ( $items as $item ) {
				if ( is_array( $item ) && isset( $item['id'], $item[ $row['name'] ] ) ) {
					$seeded[ $type ][] = [
						'id'   => $item['id'],
						'name' => $item[ $row['name'] ],
					];
				}
			}
		}

		return $seeded;
	}

	/**
	 * Resolve client mentions to `TurnState::$entities` rows, dropping any the user cannot read.
	 *
	 * @since 3.15.0
	 *
	 * @param array $mentions List of `[ 'type' => string, 'id' => int ]`.
	 *
	 * @return array Entity type => list of `[ 'id' => int, 'name' => string ]`.
	 */
	public function resolve( array $mentions ): array {

		$types    = $mentions === [] ? [] : $this->available_types();
		$resolved = [];
		$seen     = [];

		foreach ( array_slice( $mentions, 0, self::MAX_PER_TURN ) as $mention ) {

			$type = isset( $mention['type'] ) && is_string( $mention['type'] ) ? $mention['type'] : '';
			$id   = isset( $mention['id'] ) ? absint( $mention['id'] ) : 0;

			if ( ! isset( $types[ $type ] ) || $id < 1 || isset( $seen[ $type ][ $id ] ) ) {
				continue;
			}

			$seen[ $type ][ $id ] = true;

			$name = $this->name_of( $types[ $type ], $id );

			if ( $name !== '' ) {
				$resolved[ $type ][] = [
					'id'   => $id,
					'name' => $name,
				];
			}
		}

		return $resolved;
	}

	/**
	 * The record's name through its `get` ability, or '' when it cannot be read.
	 *
	 * @since 3.15.0
	 *
	 * @param array $row Type row.
	 * @param int   $id  Record id.
	 *
	 * @return string
	 */
	private function name_of( array $row, int $id ): string {

		try {
			$output = wp_get_ability( $row['get'] )->execute( [ 'id' => $id ] );
		} catch ( Throwable $e ) {
			return '';
		}

		// A trashed event still passes read_event for its author; it is not a record to point at.
		if ( ! is_array( $output ) || ( $output['status'] ?? '' ) === 'trash' ) {
			return '';
		}

		$name = $output[ $row['name'] ] ?? '';

		return is_scalar( $name ) ? trim( (string) $name ) : '';
	}
}
