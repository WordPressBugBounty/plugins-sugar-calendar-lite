<?php
/**
 * Sanitizer for the answers the Gia AI relay returns.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\EventInputSchema;

/**
 * AnswerSanitizer — reduces a model answer to the fields and tags the client may render.
 *
 * Runs on a new answer and again on every stored one, since a transcript can outlive
 * a change to these rules (a live 24h transient across an upgrade).
 *
 * @since 3.15.0
 */
class AnswerSanitizer {

	/**
	 * Most events one recap or changes block may list.
	 *
	 * The turn's own write budget, because the confirm control authorizes the
	 * turn rather than the list: a user shown 50 of the 80 events a turn can
	 * change would be authorizing 30 they never read.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_BLOCK_EVENTS = Chat::MAX_TOOL_CALLS_PER_ROUND * Turn::MAX_TOOL_ROUNDS;

	/**
	 * Most changed fields one event entry may list.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_EVENT_CHANGES = 25;

	/**
	 * Longest accepted write-property name on a change row.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_FIELD_NAME = 64;

	/**
	 * Most value rows one recap may list.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_RECAP_ROWS = 40;

	/**
	 * Longest accepted label or note on a recap row.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_ROW_LABEL = 255;

	/**
	 * Most optional features one recap may offer.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_RECAP_OFFERS = 2;

	/**
	 * Longest accepted offer, in characters.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_OFFER_LENGTH = 120;

	/**
	 * The relay prompt's restricted tag set for `text` and `recap.html`, as a
	 * `wp_kses` allowlist. The client renders sanitized output as HTML.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	const ALLOWED_HTML = [
		'p'      => [],
		'h4'     => [],
		'ul'     => [],
		'ol'     => [],
		'li'     => [],
		'b'      => [],
		'strong' => [],
		'i'      => [],
		'em'     => [],
		'a'      => [ 'href' => true ],
		'code'   => [],
		'br'     => [],
	];

	/**
	 * Memoized `event_properties()`.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $properties = null;

	/**
	 * Memoized `multi_valued_properties()`.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $multi_valued = null;

	/**
	 * Memoized `EventsPageLink`.
	 *
	 * @since 3.15.0
	 *
	 * @var EventsPageLink|null
	 */
	private $events_page_link = null;

	/**
	 * Clean a new answer: title, text with any duplicated recap removed, notice, blocks, done.
	 *
	 * @since 3.15.0
	 *
	 * @param array $answer The terminal answer from the relay.
	 *
	 * @return array
	 */
	public function answer( array $answer ): array {

		$text   = $this->sanitize_html( isset( $answer['text'] ) ? (string) $answer['text'] : '' );
		$blocks = $this->sanitize_blocks( isset( $answer['blocks'] ) && is_array( $answer['blocks'] ) ? $answer['blocks'] : [] );

		return [
			'title'  => isset( $answer['title'] ) ? (string) $answer['title'] : '',
			'text'   => $this->strip_recap_from_text( $text, $blocks ),
			'notice' => isset( $answer['notice'] ) && is_string( $answer['notice'] ) ? $answer['notice'] : null,
			'blocks' => $blocks,
			'done'   => ! empty( $answer['done'] ),
		];
	}

	/**
	 * Re-clean a stored answer's `text` and `blocks`, leaving its other keys as stored.
	 *
	 * @since 3.15.0
	 *
	 * @param array $answer A stored transcript entry's answer.
	 *
	 * @return array
	 */
	public function stored_answer( array $answer ): array {

		$answer['text']   = $this->sanitize_html( isset( $answer['text'] ) ? (string) $answer['text'] : '' );
		$answer['blocks'] = $this->sanitize_blocks( isset( $answer['blocks'] ) && is_array( $answer['blocks'] ) ? $answer['blocks'] : [] );

		return $answer;
	}

	/**
	 * Drop a recap the model also pasted into `text`, so it renders once.
	 *
	 * The block is the copy that stays: the action row keys on it. The prompt now
	 * tells the model to keep the list out of `text`, this covers the models that
	 * do not listen.
	 *
	 * @since 3.15.0
	 *
	 * @param string $text   Sanitized answer text.
	 * @param array  $blocks Sanitized blocks.
	 *
	 * @return string
	 */
	private function strip_recap_from_text( string $text, array $blocks ): string {

		foreach ( $blocks as $block ) {
			if ( $block['type'] !== 'recap' || $block['html'] === null || trim( $block['html'] ) === '' ) {
				continue;
			}

			$html = trim( $block['html'] );

			if ( trim( $text ) === $html ) {
				return '';
			}

			if ( strpos( $text, $html ) !== false ) {
				return trim( str_replace( $html, '', $text ) );
			}
		}

		return $text;
	}

	/**
	 * Reduce model HTML to the allowed tag set.
	 *
	 * @since 3.15.0
	 *
	 * @param string $html Model output.
	 *
	 * @return string
	 */
	private function sanitize_html( string $html ): string {

		return wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Keep only the block fields the relay schema declares, sanitized per field.
	 *
	 * @since 3.15.0
	 *
	 * @param array $blocks Model blocks.
	 *
	 * @return array
	 */
	private function sanitize_blocks( array $blocks ): array {

		$clean = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['type'] ) || ! is_string( $block['type'] ) ) {
				continue;
			}

			$type = sanitize_key( $block['type'] );
			$html = $this->block_string( $block, 'html' );

			$field = $this->block_field( $block );

			$clean[] = [
				'type'       => $type,
				'html'       => $html === null ? null : $this->sanitize_html( $html ),
				'question'   => $this->block_string( $block, 'question' ),
				'options'    => $this->block_options( $block ),
				'field'      => $field,
				'multiple'   => $this->block_multiple( $block, $field ),
				'label'      => $this->block_string( $block, 'label' ),
				'query_type' => $this->block_string( $block, 'query_type' ),
				'filters'    => isset( $block['filters'] ) && is_array( $block['filters'] ) ? $block['filters'] : null,
				'url'        => null,
				'events'     => $this->block_events( $block ),
				'rows'       => $this->block_rows( $block ),
				'offers'     => $type === 'recap' ? $this->block_offers( $block ) : null,
			];
		}

		return $this->with_link_url( $clean );
	}

	/**
	 * Set the Events page link on the answer's single `link_cta` block, if any.
	 *
	 * Two blocks mean a multi-scope answer, and a link to one scope would hide the other.
	 * Rebuilt from the stored filters on every pass, so a deleted calendar or tag drops it.
	 *
	 * @since 3.15.0
	 *
	 * @param array $clean Sanitized blocks.
	 *
	 * @return array
	 */
	private function with_link_url( array $clean ): array {

		$indexes = array_keys( array_column( $clean, 'type' ), 'link_cta', true );

		if ( count( $indexes ) !== 1 ) {
			return $clean;
		}

		if ( $this->events_page_link === null ) {
			$this->events_page_link = new EventsPageLink();
		}

		$index = $indexes[0];

		$clean[ $index ]['url'] = $this->events_page_link->url( $clean[ $index ]['query_type'], $clean[ $index ]['filters'] );

		return $clean;
	}

	/**
	 * Read a block field, keeping it only when the relay sent a string.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $block One model block.
	 * @param string $key   Field name.
	 *
	 * @return string|null
	 */
	private function block_string( array $block, string $key ): ?string {

		return isset( $block[ $key ] ) && is_string( $block[ $key ] ) ? $block[ $key ] : null;
	}

	/**
	 * Read a block field as readable text, whatever scalar the relay sent.
	 *
	 * The relay declares a change row's values strings, but a model answering
	 * about a toggle or an id may send the JSON boolean or number instead, and
	 * block_string() would blank it: a recap row showing a field name and no
	 * values is the one thing the user cannot authorize. block_options() casts
	 * scalars the same way.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $block One model block or event entry.
	 * @param string $key   Field name.
	 *
	 * @return string
	 */
	private function block_scalar( array $block, string $key ): string {

		$value = $block[ $key ] ?? null;

		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'sugar-calendar-lite' ) : __( 'No', 'sugar-calendar-lite' );
		}

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Keep the non-empty scalar options a chip row can render.
	 *
	 * @since 3.15.0
	 *
	 * @param array $block One model block.
	 *
	 * @return array
	 */
	private function block_options( array $block ): array {

		$raw     = isset( $block['options'] ) && is_array( $block['options'] ) ? $block['options'] : [];
		$options = [];

		foreach ( $raw as $option ) {
			if ( is_scalar( $option ) && trim( (string) $option ) !== '' ) {
				$options[] = (string) $option;
			}
		}

		return $options;
	}

	/**
	 * Read the write property a chip row asks the value of.
	 *
	 * Anything the event write abilities do not declare is dropped: the name
	 * decides whether the row renders as a multi-select, so an unrecognized one
	 * must not reach the client at all.
	 *
	 * @since 3.15.0
	 *
	 * @param array $block One model block.
	 *
	 * @return string|null
	 */
	private function block_field( array $block ): ?string {

		$field = $this->block_string( $block, 'field' );

		// The relay omits the field on every question that fills no property,
		// which is most of them, and sanitize_key() refuses null.
		if ( $field === null ) {
			return null;
		}

		$field = sanitize_key( $field );

		return array_key_exists( $field, $this->event_properties() ) ? $field : null;
	}

	/**
	 * Whether a chip row may take several picks.
	 *
	 * The relay's flag is permission to narrow, never to widen: the model keeps
	 * a question single where it reads as single ("which calendar should I move
	 * it to"), but only the property's own schema can make one plural. A model
	 * offering three picks for a scalar would have two of them dropped by the
	 * write with nothing to show the user.
	 *
	 * @since 3.15.0
	 *
	 * @param array       $block One model block.
	 * @param string|null $field The validated property name, or null.
	 *
	 * @return bool
	 */
	private function block_multiple( array $block, ?string $field ): bool {

		if ( $field === null || ( isset( $block['multiple'] ) && $block['multiple'] === false ) ) {
			return false;
		}

		return in_array( $field, $this->multi_valued_properties(), true );
	}

	/**
	 * Keep the value rows a recap of one event's values renders as its table.
	 *
	 * The relay also sends them flattened into `html`, which stays the fallback
	 * when no row survives. A value carries the write's own ceiling, as on a
	 * change row, since a create recap shows a composed description in full.
	 *
	 * @since 3.15.0
	 *
	 * @param array $block One model block.
	 *
	 * @return array|null
	 */
	private function block_rows( array $block ): ?array {

		if ( ! isset( $block['rows'] ) || ! is_array( $block['rows'] ) ) {
			return null;
		}

		$rows = array_values( array_filter( array_map( [ $this, 'block_row' ], $block['rows'] ) ) );

		return $rows === [] ? null : array_slice( $rows, 0, self::MAX_RECAP_ROWS );
	}

	/**
	 * One recap row, or null when it has no label or no value to show.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $row One model row.
	 *
	 * @return array|null
	 */
	private function block_row( $row ): ?array {

		if ( ! is_array( $row ) ) {
			return null;
		}

		$label = $this->clamp( $this->block_scalar( $row, 'label' ), self::MAX_ROW_LABEL );
		$value = $this->clamp( $this->block_scalar( $row, 'value' ), EventInputSchema::DESCRIPTION_MAX );
		$note  = $this->clamp( $this->block_scalar( $row, 'note' ), self::MAX_ROW_LABEL );

		if ( $label === '' || $value === '' ) {
			return null;
		}

		return [
			'label' => $label,
			'value' => $value,
			'note'  => $note === '' ? null : $note,
		];
	}

	/**
	 * Keep the optional features a recap offers under its table, as plain text.
	 *
	 * @since 3.15.0
	 *
	 * @param array $block One model block.
	 *
	 * @return array|null
	 */
	private function block_offers( array $block ): ?array {

		$raw    = isset( $block['offers'] ) && is_array( $block['offers'] ) ? $block['offers'] : [];
		$offers = [];

		foreach ( $raw as $offer ) {
			$offer = is_string( $offer ) ? $this->clamp( sanitize_text_field( $offer ), self::MAX_OFFER_LENGTH ) : '';

			if ( $offer !== '' ) {
				$offers[] = $offer;
			}
		}

		return $offers === [] ? null : array_slice( $offers, 0, self::MAX_RECAP_OFFERS );
	}

	/**
	 * Keep the per-event change list a recap or changes block renders.
	 *
	 * One shape serves both: the edit funnel's recap, shown before the write,
	 * and the changes block, shown after it. An entry with no usable event id
	 * is dropped rather than rendered, because the changes block links each
	 * one to its event and a row that cannot link is a change the user has no
	 * way to go and check.
	 *
	 * @since 3.15.0
	 *
	 * @param array $block One model block.
	 *
	 * @return array|null
	 */
	private function block_events( array $block ): ?array {

		if ( ! isset( $block['events'] ) || ! is_array( $block['events'] ) ) {
			return null;
		}

		$events = [];

		foreach ( $block['events'] as $event ) {
			// Cast rather than absint(): a negative id is malformed, and folding
			// it to its absolute value would link the row to an unrelated event.
			$event_id = is_array( $event ) && is_numeric( $event['event_id'] ?? null ) ? (int) $event['event_id'] : 0;

			if ( $event_id <= 0 ) {
				continue;
			}

			$events[] = [
				'event_id' => $event_id,
				'title'    => $this->block_scalar( $event, 'title' ),
				'changes'  => $this->block_event_changes( $event ),
			];

			if ( count( $events ) === self::MAX_BLOCK_EVENTS ) {
				break;
			}
		}

		return $events === [] ? null : $events;
	}

	/**
	 * Keep one event entry's field rows.
	 *
	 * `field` is the write property's own name, kept as the relay sent it: the
	 * client maps it to a label and falls back to the raw name, so reducing an
	 * unrecognized one to a key would cost the fallback its only input. The
	 * values carry the write's own ceiling, so the recap never shows less than
	 * what the write would store.
	 *
	 * @since 3.15.0
	 *
	 * @param array $event One event entry.
	 *
	 * @return array
	 */
	private function block_event_changes( array $event ): array {

		$raw     = isset( $event['changes'] ) && is_array( $event['changes'] ) ? $event['changes'] : [];
		$changes = [];

		foreach ( $raw as $change ) {
			if ( ! is_array( $change ) ) {
				continue;
			}

			$field = trim( (string) $this->block_string( $change, 'field' ) );

			if ( $field === '' ) {
				continue;
			}

			// The write's own ceiling, not a display one: a composed description
			// belongs in the recap in full, because that is the only place the
			// user reads it before it becomes the event.
			$from = $this->clamp( $this->block_scalar( $change, 'from' ), EventInputSchema::DESCRIPTION_MAX );
			$to   = $this->clamp( $this->block_scalar( $change, 'to' ), EventInputSchema::DESCRIPTION_MAX );

			// A field name with no values on either side is the one row the user
			// cannot authorize, so drop it rather than render a bare label.
			if ( $from === '' && $to === '' ) {
				continue;
			}

			$changes[] = [
				'field' => $this->clamp( $field, self::MAX_FIELD_NAME ),
				'from'  => $from,
				'to'    => $to,
			];

			if ( count( $changes ) === self::MAX_EVENT_CHANGES ) {
				break;
			}
		}

		return $changes;
	}

	/**
	 * Cut a model string to a length, counting characters rather than bytes.
	 *
	 * @since 3.15.0
	 *
	 * @param string $value One model string.
	 * @param int    $max   Longest accepted length, in characters.
	 *
	 * @return string
	 */
	private function clamp( string $value, int $max ): string {

		return mb_strlen( $value ) > $max ? mb_substr( $value, 0, $max ) : $value;
	}

	/**
	 * The event write abilities' input properties, built once per instance.
	 *
	 * `Session::from_array()` re-sanitizes every persisted transcript entry on each
	 * read and `GET /session` polls every few seconds, so building this per block
	 * would run the seven-contributor filter chain tens of times a poll.
	 * Contributors register at boot, so nothing invalidates it.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function event_properties(): array {

		if ( $this->properties === null ) {
			$this->properties = EventInputSchema::properties();
		}

		return $this->properties;
	}

	/**
	 * The write properties that hold a list, such as `calendar_ids`.
	 *
	 * Read from the write abilities' own schema so a contributor that adds an
	 * array property earns a multi-select row without touching this file, and
	 * one that adds a scalar cannot. A property added directly on an ability
	 * class rather than through the schema filter is invisible here and stays
	 * single, which is safe but silent.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function multi_valued_properties(): array {

		if ( $this->multi_valued !== null ) {
			return $this->multi_valued;
		}

		$this->multi_valued = [];

		foreach ( $this->event_properties() as $name => $schema ) {
			$type = $schema['type'] ?? null;

			// JSON Schema allows a list of types, as in [ 'array', 'null' ].
			if ( $type === 'array' || ( is_array( $type ) && in_array( 'array', $type, true ) ) ) {
				$this->multi_valued[] = $name;
			}
		}

		return $this->multi_valued;
	}
}
