<?php
/**
 * The "Show me" link a turn's own tool calls imply.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Builds an Ask answer's `link_cta` from the scope calls its turn ran.
 *
 * The plugin ran those calls, so they say exactly what the answer counted. The model's own
 * `link_cta` stands only when the turn ran none (an answer from earlier data).
 *
 * @since 3.15.0
 */
class CallLink {

	/**
	 * Abilities whose input is the answer's scope, and the query type each one links as.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const SCOPE_ABILITIES = [
		AbilityAdvertiser::PREFIX . 'get-registration-stats' => 'registrations',
		AbilityAdvertiser::PREFIX . 'list-events'   => 'events',
		AbilityAdvertiser::PREFIX . 'search-events' => 'events',
	];

	/**
	 * Stats filters the Events page can show.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const PAGE_ID_FILTERS = [ 'calendar_ids', 'tag_ids' ];

	/**
	 * Keys of a list-events input, and the link filter each one becomes.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const LIST_KEYS = [
		'calendar_id'  => 'calendar_id',
		'tag_id'       => 'tag_ids',
		'status'       => 'status',
		'start_after'  => 'start',
		'start_before' => 'end',
	];

	/**
	 * Input keys of a list-events call the page has no filter for, but that don't disqualify
	 * the call the way an unmapped filter (venue_id) does. Taken from ListEvents::get_input_schema().
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const LIST_IGNORED_KEYS = [ 'orderby', 'order', 'number', 'offset' ];

	/**
	 * Keys of a search-events input, and the link filter each one becomes.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const SEARCH_KEYS = [
		'search' => 'search',
		'status' => 'status',
	];

	/**
	 * Input keys of a search-events call the page has no filter for. Taken from SearchEvents::get_input_schema().
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const SEARCH_IGNORED_KEYS = [ 'number', 'offset' ];

	/**
	 * Replace the answer's `link_cta` with the one its turn's calls imply.
	 *
	 * @since 3.15.0
	 *
	 * @param array $blocks  Relay answer blocks.
	 * @param array $history Turn history, the in-progress turn last.
	 *
	 * @return array
	 */
	public function apply( array $blocks, array $history ): array {

		$calls = $this->scope_calls( $this->current_turn( $history ) );

		// No scope call this turn: the answer reuses earlier data, so the model's link stands.
		if ( $calls === [] ) {
			return $blocks;
		}

		$blocks = array_values( array_filter( $blocks, [ $this, 'is_not_link' ] ) );
		$link   = $this->link( $calls );

		if ( $link !== null ) {
			$blocks[] = $link;
		}

		return $blocks;
	}

	/**
	 * Whether a relay block is anything but a `link_cta`.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $block Relay block.
	 *
	 * @return bool
	 */
	private function is_not_link( $block ): bool {

		return ! is_array( $block ) || ! isset( $block['type'] ) || ! is_string( $block['type'] ) || sanitize_key( $block['type'] ) !== 'link_cta';
	}

	/**
	 * The in-progress turn: every entry after the last user prompt.
	 *
	 * @since 3.15.0
	 *
	 * @param array $history Turn history.
	 *
	 * @return array
	 */
	private function current_turn( array $history ): array {

		$history = array_values( $history );
		$start   = 0;

		foreach ( $history as $index => $entry ) {
			if ( ( $entry['role'] ?? '' ) === 'user' ) {
				$start = $index + 1;
			}
		}

		return array_slice( $history, $start );
	}

	/**
	 * The turn's scope calls, each with its query type, decoded input and whether it failed.
	 *
	 * @since 3.15.0
	 *
	 * @param array $entries The in-progress turn's entries.
	 *
	 * @return array[]
	 */
	private function scope_calls( array $entries ): array {

		$failed = $this->failed_call_ids( $entries );
		$calls  = [];

		foreach ( $entries as $entry ) {
			foreach ( $this->entry_calls( $entry ) as $call ) {
				$scope_call = $this->scope_call( $call, $failed );

				if ( $scope_call !== null ) {
					$calls[] = $scope_call;
				}
			}
		}

		return $calls;
	}

	/**
	 * One tool call as a scope call, or null when it isn't one.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $call   Tool call, as received.
	 * @param bool[] $failed Failed call ids.
	 *
	 * @return array|null
	 */
	private function scope_call( array $call, array $failed ): ?array {

		$name = is_string( $call['name'] ?? null ) ? $call['name'] : '';

		if ( ! isset( self::SCOPE_ABILITIES[ $name ] ) ) {
			return null;
		}

		return [
			'name'   => $name,
			'type'   => self::SCOPE_ABILITIES[ $name ],
			'args'   => ToolCallRunner::decode_arguments( $call['arguments'] ?? null ),
			'failed' => isset( $failed[ (string) ( $call['result_id'] ?? '' ) ] ),
		];
	}

	/**
	 * The tool calls an assistant entry made.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $entry History entry.
	 *
	 * @return array
	 */
	private function entry_calls( $entry ): array {

		if ( ! is_array( $entry ) || ( $entry['role'] ?? '' ) !== 'assistant' || ! isset( $entry['tool_calls'] ) || ! is_array( $entry['tool_calls'] ) ) {
			return [];
		}

		return array_filter( $entry['tool_calls'], 'is_array' );
	}

	/**
	 * Ids of the turn's calls whose result is an error.
	 *
	 * @since 3.15.0
	 *
	 * @param array $entries The in-progress turn's entries.
	 *
	 * @return bool[] Keyed by tool call id.
	 */
	private function failed_call_ids( array $entries ): array {

		$failed = [];

		foreach ( $entries as $entry ) {
			if ( $this->is_error_result( $entry ) ) {
				$failed[ (string) ( $entry['tool_call_id'] ?? '' ) ] = true;
			}
		}

		return $failed;
	}

	/**
	 * Whether a history entry is a tool result carrying an error.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $entry History entry.
	 *
	 * @return bool
	 */
	private function is_error_result( $entry ): bool {

		if ( ! is_array( $entry ) || ( $entry['role'] ?? '' ) !== 'tool' || ! is_string( $entry['content'] ?? null ) ) {
			return false;
		}

		return ToolCallRunner::is_error_content( $entry['content'] );
	}

	/**
	 * The one link every successful scope call agrees on, or null.
	 *
	 * @since 3.15.0
	 *
	 * @param array[] $calls Scope calls.
	 *
	 * @return array|null
	 */
	private function link( array $calls ): ?array {

		$links = [];

		foreach ( $calls as $call ) {
			if ( $call['failed'] ) {
				continue;
			}

			$filters = $this->filters( $call['name'], $call['args'] );

			// A call the page can't express (a venue, an event, two calendars) means no link at all.
			if ( $filters === null ) {
				return null;
			}

			$links[ (string) wp_json_encode( [ $call['type'], $filters ] ) ] = [ $call['type'], $filters ];
		}

		// Two different scopes are a compare, and a link to one would hide the other.
		if ( count( $links ) !== 1 ) {
			return null;
		}

		list( $type, $filters ) = reset( $links );

		return [
			'type'       => 'link_cta',
			'query_type' => $type,
			'filters'    => $filters,
			'label'      => null,
			'url'        => null,
		];
	}

	/**
	 * One call's input as link filters, or null when the page can't show it.
	 *
	 * @since 3.15.0
	 *
	 * @param string $name Ability name.
	 * @param array  $args Decoded call input.
	 *
	 * @return array|null
	 */
	private function filters( string $name, array $args ): ?array {

		if ( $name === AbilityAdvertiser::PREFIX . 'get-registration-stats' ) {
			return $this->stats_filters( $args );
		}

		if ( $name === AbilityAdvertiser::PREFIX . 'list-events' ) {
			return $this->list_filters( $args );
		}

		return $this->search_filters( $args );
	}

	/**
	 * A search-events input as link filters, or null when the page can't show it.
	 *
	 * @since 3.15.0
	 *
	 * @param array $args Decoded call input.
	 *
	 * @return array|null
	 */
	private function search_filters( array $args ): ?array {

		if ( $this->has_unmapped_key( $args, self::SEARCH_KEYS, self::SEARCH_IGNORED_KEYS ) ) {
			return null;
		}

		return $this->pairs( $this->renamed( $args, self::SEARCH_KEYS ) );
	}

	/**
	 * A get-registration-stats input as link filters.
	 *
	 * @since 3.15.0
	 *
	 * @param array $args Decoded call input.
	 *
	 * @return array|null
	 */
	private function stats_filters( array $args ): ?array {

		$calendars = wp_parse_id_list( $args['calendar_ids'] ?? [] );

		// The page's calendar filter takes one calendar.
		if ( $this->has_other_ids( $args ) || count( $calendars ) > 1 ) {
			return null;
		}

		$values = $this->renamed(
			$args,
			[
				'start' => 'start',
				'end'   => 'end',
			]
		);

		$values['calendar_id'] = implode( ',', $calendars );
		$values['tag_ids']     = implode( ',', wp_parse_id_list( $args['tag_ids'] ?? [] ) );

		return $this->pairs( $values );
	}

	/**
	 * Whether a stats input filters by ids the page has no filter for (events, venues, speakers).
	 *
	 * @since 3.15.0
	 *
	 * @param array $args Decoded call input.
	 *
	 * @return bool
	 */
	private function has_other_ids( array $args ): bool {

		foreach ( $args as $key => $value ) {
			if ( is_string( $key ) && substr( $key, -4 ) === '_ids' && ! in_array( $key, self::PAGE_ID_FILTERS, true ) && ! empty( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A list-events input as link filters.
	 *
	 * @since 3.15.0
	 *
	 * @param array $args Decoded call input.
	 *
	 * @return array|null
	 */
	private function list_filters( array $args ): ?array {

		if ( $this->has_unmapped_key( $args, self::LIST_KEYS, self::LIST_IGNORED_KEYS ) ) {
			return null;
		}

		return $this->pairs( $this->renamed( $args, self::LIST_KEYS ) );
	}

	/**
	 * Whether an input carries a non-empty key the page can't show: neither a mapped
	 * filter nor an ignored paging/order key.
	 *
	 * `ToolCallRunner::LABEL_ARG` is always present (the relay adds it to every tool
	 * call) and belongs to neither the page nor the ability, so it never disqualifies a call.
	 *
	 * @since 3.15.0
	 *
	 * @param array    $args    Decoded call input.
	 * @param string[] $keys    Input key => link filter key.
	 * @param string[] $ignored Input keys that don't disqualify the call.
	 *
	 * @return bool
	 */
	private function has_unmapped_key( array $args, array $keys, array $ignored ): bool {

		foreach ( $args as $key => $value ) {

			if ( $value === null || $value === [] || $value === '' ) {
				continue;
			}

			if ( ! isset( $keys[ $key ] ) && $key !== ToolCallRunner::LABEL_ARG && ! in_array( $key, $ignored, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Input values under their link filter keys.
	 *
	 * @since 3.15.0
	 *
	 * @param array    $args Decoded call input.
	 * @param string[] $keys Input key => link filter key.
	 *
	 * @return array
	 */
	private function renamed( array $args, array $keys ): array {

		$values = [];

		foreach ( $keys as $from => $to ) {
			$values[ $to ] = $args[ $from ] ?? null;
		}

		return $values;
	}

	/**
	 * Non-empty values as `{ key, value }` string pairs, or null for a value that isn't a scalar.
	 *
	 * @since 3.15.0
	 *
	 * @param array $values Values by filter key.
	 *
	 * @return array|null
	 */
	private function pairs( array $values ): ?array {

		$pairs = [];

		foreach ( $values as $key => $value ) {
			if ( $value === null || $value === [] || $value === '' ) {
				continue;
			}

			// Dropping a value we can't read would widen the page.
			if ( ! is_scalar( $value ) ) {
				return null;
			}

			$pairs[] = [
				'key'   => $key,
				'value' => (string) $value,
			];
		}

		return $pairs;
	}
}
