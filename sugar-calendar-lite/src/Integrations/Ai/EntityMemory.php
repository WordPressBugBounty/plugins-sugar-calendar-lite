<?php
/**
 * Entities a conversation has resolved, plus the current turn's own picks.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * EntityMemory: the entity ledger, kept separate from TurnState's round loop.
 *
 * @since 3.15.0
 */
final class EntityMemory {

	/**
	 * Most entities remembered per type.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_ENTITIES_PER_TYPE = 20;

	/**
	 * Longest entity name kept. The relay renders 120 and rejects the request past 200.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_ENTITY_NAME = 120;

	/**
	 * Entities the conversation has resolved, most recent first: entity type
	 * (e.g. `calendar`, `venue`) => list of `[ 'id' => int, 'name' => string ]`.
	 *
	 * Survives compaction and Stop, unlike `history`: a finished turn's
	 * list-calendars result is gone from the wire history, but the model still
	 * needs the id it resolved. Only Clear, which discards the whole state, ends it.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $entities = [];

	/**
	 * Records the user picked for the current turn, same shape as `$entities`.
	 *
	 * Re-remembered after every harvest, so a same-type listing cannot push them
	 * out of the capped list mid-turn. Replaced on every prompt.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $mentions = [];

	/**
	 * Remember entities the model resolved, most recent mention first.
	 *
	 * On a duplicate `id` the newer mention wins: a renamed calendar resolved again
	 * this round should not be described by the name it had two turns ago.
	 *
	 * @since 3.15.0
	 *
	 * @param array $found Same shape as `$entities`: type => list of `[ 'id', 'name' ]`.
	 *
	 * @return EntityMemory
	 */
	public function remember_entities( array $found ): EntityMemory {

		foreach ( $found as $type => $items ) {

			$type  = sanitize_key( (string) $type );
			$clean = is_array( $items ) ? $this->clean_entities( $items ) : [];

			if ( $type === '' || $clean === [] ) {
				continue;
			}

			$clean_ids = array_column( $clean, 'id' );
			$existing  = isset( $this->entities[ $type ] ) && is_array( $this->entities[ $type ] ) ? $this->entities[ $type ] : [];
			$kept      = [];

			foreach ( $existing as $item ) {
				if ( ! in_array( $item['id'] ?? null, $clean_ids, true ) ) {
					$kept[] = $item;
				}
			}

			$this->entities[ $type ] = array_slice( array_merge( $clean, $kept ), 0, self::MAX_ENTITIES_PER_TYPE );
		}

		return $this;
	}

	/**
	 * Keep this turn's picked records and put them first among the remembered entities.
	 *
	 * Called once per prompt, so a turn without tokens clears it. `with_prompt()`
	 * leaves it alone because a retried turn goes through it too.
	 *
	 * @since 3.15.0
	 *
	 * @param array $resolved Same shape as `$entities`.
	 *
	 * @return EntityMemory
	 */
	public function remember_mentions( array $resolved ): EntityMemory {

		$this->mentions = $resolved;

		return $this->remember_entities( $resolved );
	}

	/**
	 * This turn's mentions not already among the remembered entities, for
	 * re-adding after a harvest that may have evicted them.
	 *
	 * Only the evicted ones come back, so a fresher harvested name for the same
	 * id is never overwritten by the prompt-time name in `$mentions`.
	 *
	 * @since 3.15.0
	 *
	 * @return array Same shape as `$mentions`.
	 */
	public function missing_mentions(): array {

		$missing = [];

		foreach ( $this->mentions as $type => $items ) {

			$present = array_column( is_array( $this->entities[ $type ] ?? null ) ? $this->entities[ $type ] : [], 'id' );
			$kept    = array_values(
				array_filter(
					is_array( $items ) ? $items : [],
					static function ( $item ) use ( $present ) {

						return ! in_array( $item['id'] ?? null, $present, true );
					}
				)
			);

			if ( $kept !== [] ) {
				$missing[ $type ] = $kept;
			}
		}

		return $missing;
	}

	/**
	 * Fold a tool round's harvested entities in, keeping this turn's own picks from
	 * falling out of the capped list mid-turn.
	 *
	 * @since 3.15.0
	 *
	 * @param array $harvested Entities named in the round's results, same shape as `$entities`.
	 *
	 * @return EntityMemory
	 */
	public function remember_harvest( array $harvested ): EntityMemory {

		$this->remember_entities( $harvested );

		return $this->remember_entities( $this->missing_mentions() );
	}

	/**
	 * Keep the usable `[ id, name ]` pairs of one type, first mention of an id wins.
	 *
	 * @since 3.15.0
	 *
	 * @param array $items Entities as harvested, most recent first.
	 *
	 * @return array
	 */
	private function clean_entities( array $items ): array {

		$clean = [];
		$seen  = [];

		foreach ( $items as $item ) {

			$id   = isset( $item['id'] ) && is_numeric( $item['id'] ) ? (int) $item['id'] : 0;
			$name = isset( $item['name'] ) ? trim( (string) $item['name'] ) : '';
			$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, self::MAX_ENTITY_NAME ) : substr( $name, 0, self::MAX_ENTITY_NAME );

			if ( $id <= 0 || $name === '' || isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;
			$clean[]     = [
				'id'   => $id,
				'name' => $name,
			];
		}

		return $clean;
	}

	/**
	 * Remembered entities, type => list of `[ id, name ]`.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function entities(): array {

		return $this->entities;
	}

	/**
	 * Serialize for storage between requests.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function to_array(): array {

		return [
			'entities' => $this->entities,
			'mentions' => $this->mentions,
		];
	}

	/**
	 * Rebuild from a TurnState payload's `entities`/`mentions` keys.
	 *
	 * @since 3.15.0
	 *
	 * @param array $data Serialized state.
	 *
	 * @return EntityMemory
	 */
	public static function from_array( array $data ): EntityMemory {

		$memory = new self();

		$memory->entities = isset( $data['entities'] ) && is_array( $data['entities'] ) ? $data['entities'] : [];
		$memory->mentions = isset( $data['mentions'] ) && is_array( $data['mentions'] ) ? $data['mentions'] : [];

		return $memory;
	}
}
