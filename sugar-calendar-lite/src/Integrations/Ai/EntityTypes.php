<?php
/**
 * The record types Gia AI harvests from tool results and offers in the
 * composer's insert menu.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * One registry replacing the two that used to describe entity kinds
 * separately (`Turn::entity_map()`, `Mentions::types()`). Each row carries
 * what both consumers need, so a contributor adds one row instead of two
 * that have to stay in step by hand.
 *
 * @since 3.15.0
 */
class EntityTypes {

	/**
	 * Normalized rows, memoized per instance.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $rows;

	/**
	 * Every registered type, normalized.
	 *
	 * @since 3.15.0
	 *
	 * @return array Type slug => row (`collection`, `name`, `harvest`, `seed`, plus the
	 *               optional insert-menu keys: `label`, `icon`, `position`,
	 *               `list`, `list_input`, `search`, `search_input`, `get`).
	 */
	public function all(): array {

		if ( $this->rows !== null ) {
			return $this->rows;
		}

		$prefix = AbilityAdvertiser::PREFIX;

		$types = [
			'calendar' => [
				'collection' => 'calendars',
				'name'       => 'name',
				'harvest'    => [
					$prefix . 'list-calendars'  => [
						'collection' => 'calendars',
						'id'         => 'id',
					],
					$prefix . 'create-calendar' => [
						'collection' => null,
						'id'         => 'term_id',
					],
				],
				'label'      => __( 'Add Calendar', 'sugar-calendar-lite' ),
				'icon'       => 'sc:icon-[fa6-solid--calendar]',
				'position'   => 40,
				'list'       => $prefix . 'list-calendars',
				'search'     => $prefix . 'list-calendars',
				'get'        => $prefix . 'get-calendar',
				'seed'       => true,
			],
			'tag'      => [
				'collection' => 'tags',
				'name'       => 'name',
				'harvest'    => [
					$prefix . 'list-tags'  => [
						'collection' => 'tags',
						'id'         => 'id',
					],
					$prefix . 'create-tag' => [
						'collection' => null,
						'id'         => 'term_id',
					],
				],
				'label'      => __( 'Add Tags', 'sugar-calendar-lite' ),
				'icon'       => 'sc:icon-[fa6-solid--tags]',
				'position'   => 50,
				'list'       => $prefix . 'list-tags',
				'search'     => $prefix . 'list-tags',
				'get'        => $prefix . 'get-tag',
			],
			'event'    => [
				'collection'   => 'events',
				'name'         => 'title',
				'harvest'      => [
					$prefix . 'list-events' => [
						'collection' => 'events',
						'id'         => 'id',
					],
					$prefix . 'get-event'   => [
						'collection' => null,
						'id'         => 'id',
					],
				],
				'label'        => __( 'Add Events', 'sugar-calendar-lite' ),
				'icon'         => 'sc:icon-[fa6-solid--calendar-plus]',
				'position'     => 10,
				'list'         => $prefix . 'list-events',
				// The default (published, oldest start first) hides the drafts Gia itself creates.
				'list_input'   => [
					'status'  => 'any',
					'orderby' => 'date_modified',
					'order'   => 'DESC',
				],
				'search'       => $prefix . 'search-events',
				'search_input' => [ 'status' => 'any' ],
				'get'          => $prefix . 'get-event',
			],
		];

		/**
		 * Filters the entity types Gia AI harvests from tool results and offers
		 * in the composer's insert menu.
		 *
		 * A contributor that registers list/get/create abilities for its own
		 * record type adds one row here, keyed by its type slug. Replaces the
		 * former `sugar_calendar_integrations_ai_turn_entity_map` and
		 * `sugar_calendar_integrations_ai_mention_types` filters.
		 *
		 * `seed: true` on an insert-menu row lists its records into a new
		 * conversation before the first round; its list ability must accept `number`.
		 *
		 * @since 3.15.0
		 *
		 * @param array $types Type slug => row (`collection`, `name`, `harvest`,
		 *                     `seed`, plus the optional insert-menu keys).
		 */
		$types = apply_filters( 'sugar_calendar_integrations_ai_entity_types', $types ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$this->rows = $this->normalize( is_array( $types ) ? $types : [] );

		return $this->rows;
	}

	/**
	 * `Turn::harvest_entities()`'s map: ability name => where its output holds
	 * the entities to remember.
	 *
	 * @since 3.15.0
	 *
	 * @return array Ability name => `[ 'type', 'collection', 'id', 'name' ]`.
	 */
	public function harvest_map(): array {

		$map = [];

		foreach ( $this->all() as $type => $row ) {
			foreach ( $row['harvest'] as $ability => $spec ) {
				$map[ $ability ] = [
					'type'       => $type,
					'collection' => $spec['collection'],
					'id'         => $spec['id'],
					// Most abilities of a type share one name field, but a create ability's
					// output can shape it differently than its list/get siblings (e.g.
					// create-venue returns `name`, list-venues returns `title`).
					'name'       => $spec['name'] ?? $row['name'],
				];
			}
		}

		return $map;
	}

	/**
	 * `Mentions::types()`'s rows: the insertable types, in dropdown order.
	 *
	 * @since 3.15.0
	 *
	 * @return array Entity type => row (`label`, `icon`, `position`, `list`,
	 *               `list_input`, `search`, `search_input`, `get`, `collection`, `name`, `seed`).
	 */
	public function menu_types(): array {

		$menu = [];

		foreach ( $this->all() as $type => $row ) {

			if ( $row['label'] === '' ) {
				continue;
			}

			$menu[ $type ] = [
				'label'        => $row['label'],
				'icon'         => $row['icon'],
				'position'     => $row['position'],
				'list'         => $row['list'],
				'list_input'   => $row['list_input'],
				'search'       => $row['search'],
				'search_input' => $row['search_input'],
				'get'          => $row['get'],
				'collection'   => $row['collection'],
				'name'         => $row['name'],
				'seed'         => $row['seed'],
			];
		}

		uasort(
			$menu,
			function ( $a, $b ) {
				return $a['position'] <=> $b['position'];
			}
		);

		return $menu;
	}

	/**
	 * Keep well-formed rows, fill optional keys.
	 *
	 * @since 3.15.0
	 *
	 * @param array $types Filtered types.
	 *
	 * @return array
	 */
	private function normalize( array $types ): array {

		$clean = [];

		foreach ( $types as $type => $row ) {

			if ( ! is_string( $type ) || sanitize_key( $type ) !== $type || ! $this->is_row( $row ) ) {
				continue;
			}

			$clean[ $type ] = array_merge(
				[
					'collection' => (string) $row['collection'],
					'name'       => (string) $row['name'],
					'harvest'    => $this->clean_harvest( $row['harvest'] ?? [] ),
					'seed'       => ! empty( $row['seed'] ),
				],
				$this->clean_menu_fields( $row )
			);
		}

		return $clean;
	}

	/**
	 * Clean one row's optional insert-menu keys, filling defaults.
	 *
	 * @since 3.15.0
	 *
	 * @param array $row Candidate row.
	 *
	 * @return array `label`, `icon`, `position`, `list`, `list_input`, `search`, `search_input`, `get`.
	 */
	private function clean_menu_fields( array $row ): array {

		return [
			'label'        => isset( $row['label'] ) && is_string( $row['label'] ) ? $row['label'] : '',
			'icon'         => isset( $row['icon'] ) ? (string) $row['icon'] : '',
			'position'     => isset( $row['position'] ) ? (int) $row['position'] : 100,
			'list'         => isset( $row['list'] ) ? (string) $row['list'] : '',
			'list_input'   => isset( $row['list_input'] ) && is_array( $row['list_input'] ) ? $row['list_input'] : [],
			'search'       => isset( $row['search'] ) ? (string) $row['search'] : '',
			'search_input' => isset( $row['search_input'] ) && is_array( $row['search_input'] ) ? $row['search_input'] : [],
			'get'          => isset( $row['get'] ) ? (string) $row['get'] : '',
		];
	}

	/**
	 * Clean one row's `harvest` sub-map.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $harvest Candidate `harvest` value.
	 *
	 * @return array Ability name => `[ 'collection' => string|null, 'id' => string, 'name' => string|null ]`.
	 */
	private function clean_harvest( $harvest ): array {

		if ( ! is_array( $harvest ) ) {
			return [];
		}

		$clean = [];

		foreach ( $harvest as $ability => $spec ) {

			if ( ! is_string( $ability ) || $ability === '' || ! is_array( $spec ) || empty( $spec['id'] ) || ! is_string( $spec['id'] ) ) {
				continue;
			}

			$clean[ $ability ] = [
				'collection' => isset( $spec['collection'] ) && is_string( $spec['collection'] ) && $spec['collection'] !== '' ? $spec['collection'] : null,
				'id'         => $spec['id'],
				// Overrides the type's own `name` field, for an ability whose output
				// shapes the name differently than its list/get siblings.
				'name'       => isset( $spec['name'] ) && is_string( $spec['name'] ) && $spec['name'] !== '' ? $spec['name'] : null,
			];
		}

		return $clean;
	}

	/**
	 * Whether a filtered row carries the required keys.
	 *
	 * `collection` and `name` are always required; the insert-menu keys
	 * (`label`, `list`, `search`, `get`) are all-or-nothing, so a harvest-only
	 * row can omit them entirely.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $row Candidate row.
	 *
	 * @return bool
	 */
	private function is_row( $row ): bool {

		if ( ! is_array( $row ) ) {
			return false;
		}

		foreach ( [ 'collection', 'name' ] as $key ) {
			if ( ! isset( $row[ $key ] ) || ! is_string( $row[ $key ] ) || $row[ $key ] === '' ) {
				return false;
			}
		}

		if ( ! isset( $row['label'] ) ) {
			return true;
		}

		foreach ( [ 'label', 'list', 'search', 'get' ] as $key ) {
			if ( ! isset( $row[ $key ] ) || ! is_string( $row[ $key ] ) || $row[ $key ] === '' ) {
				return false;
			}
		}

		return true;
	}
}
