<?php
/**
 * The sanitized input of one /turn request.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WP_REST_Request;

/**
 * TurnInput — what a turn request asks for, cleaned once so the services never read the request.
 *
 * @since 3.15.0
 */
class TurnInput {

	/**
	 * Cap on question rows per submit, and on chips picked in one row.
	 *
	 * @since 3.15.0
	 */
	const MAX_PICKS = 20;

	/**
	 * The user's message, '' for a continuation or retry.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $prompt;

	/**
	 * Requested funnel, '' when the request names none.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $funnel;

	/**
	 * Whether the user confirmed a recap.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $confirmed;

	/**
	 * Picked records, `{ type, id }` pairs.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $mentions;

	/**
	 * Client context, only a sanitized `page`.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $context;

	/**
	 * Client id of the turn this prompt starts, or ''.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $turn_id;

	/**
	 * Chip picks sent by Submit Answer, one per question row.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $picks;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param string $prompt    The user's message.
	 * @param string $funnel    Requested funnel.
	 * @param bool   $confirmed Whether the user confirmed a recap.
	 * @param array  $mentions  Sanitized `{ type, id }` pairs.
	 * @param array  $context   Sanitized context.
	 * @param string $turn_id   Client id of the turn this prompt starts.
	 * @param array  $picks     Sanitized chip picks.
	 */
	public function __construct( string $prompt = '', string $funnel = '', bool $confirmed = false, array $mentions = [], array $context = [], string $turn_id = '', array $picks = [] ) {

		$this->prompt    = $prompt;
		$this->funnel    = $funnel;
		$this->confirmed = $confirmed;
		$this->mentions  = $mentions;
		$this->context   = $context;
		$this->turn_id   = $turn_id;
		$this->picks     = $picks;
	}

	/**
	 * Build from a /turn request whose args already ran their sanitize callbacks.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return TurnInput
	 */
	public static function from_request( WP_REST_Request $request ): TurnInput {

		$mentions = $request->get_param( 'entities' );
		$picks    = $request->get_param( 'picks' );

		return new self(
			(string) $request->get_param( 'prompt' ),
			(string) $request->get_param( 'funnel' ),
			(bool) $request->get_param( 'confirmed' ),
			is_array( $mentions ) ? $mentions : [],
			self::clean_context( $request->get_param( 'context' ) ),
			(string) $request->get_param( 'turn_id' ),
			is_array( $picks ) ? $picks : []
		);
	}

	/**
	 * The user's message.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function prompt(): string {

		return $this->prompt;
	}

	/**
	 * Requested funnel.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function funnel(): string {

		return $this->funnel;
	}

	/**
	 * Whether the user confirmed a recap.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	public function confirmed(): bool {

		return $this->confirmed;
	}

	/**
	 * Picked records.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function mentions(): array {

		return $this->mentions;
	}

	/**
	 * Client context.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function context(): array {

		return $this->context;
	}

	/**
	 * Client id of the turn this prompt starts, or ''.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function turn_id(): string {

		return $this->turn_id;
	}

	/**
	 * Chip picks sent by Submit Answer.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function picks(): array {

		return $this->picks;
	}

	/**
	 * Sanitize a prompt, shared by /turn's sanitize and validate callbacks so
	 * the length rule is enforced against the same value the handler reads.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Raw prompt param.
	 *
	 * @return string
	 */
	public static function sanitize_prompt( $value ): string {

		if ( ! is_string( $value ) ) {
			return '';
		}

		return trim( sanitize_textarea_field( $value ) );
	}

	/**
	 * Keep well-formed `{ type, id }` pairs, at most `Mentions::MAX_PER_TURN`.
	 *
	 * A custom sanitize callback replaces core's schema check, so the shape is enforced here.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Raw `entities` param.
	 *
	 * @return array
	 */
	public static function sanitize_mentions( $value ): array {

		if ( ! is_array( $value ) ) {
			return [];
		}

		$clean = [];

		foreach ( array_slice( $value, 0, Mentions::MAX_PER_TURN ) as $item ) {

			if ( ! is_array( $item ) || ! isset( $item['type'], $item['id'] ) || ! is_string( $item['type'] ) || ! is_numeric( $item['id'] ) ) {
				continue;
			}

			$type = sanitize_key( $item['type'] );
			$id   = absint( $item['id'] );

			if ( $type !== '' && $id > 0 ) {
				$clean[] = [
					'type' => $type,
					'id'   => $id,
				];
			}
		}

		return $clean;
	}

	/**
	 * Keep `{ indexes, other, text }` picks, one per question row.
	 *
	 * A malformed row becomes an empty pick instead of being dropped: the client pairs picks with questions by position.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $value Raw `picks` param.
	 *
	 * @return array
	 */
	public static function sanitize_picks( $value ): array {

		if ( ! is_array( $value ) ) {
			return [];
		}

		$clean = [];

		foreach ( array_slice( array_values( $value ), 0, self::MAX_PICKS ) as $row ) {
			$row   = is_array( $row ) ? $row : [];
			$other = isset( $row['other'] ) && rest_sanitize_boolean( $row['other'] );
			$text  = $other && isset( $row['text'] ) && is_string( $row['text'] ) ? sanitize_text_field( $row['text'] ) : '';

			$clean[] = [
				'indexes' => self::clean_pick_indexes( $row['indexes'] ?? [] ),
				'other'   => $other,
				'text'    => mb_substr( $text, 0, Chat::MAX_PROMPT_LENGTH ),
			];
		}

		return $clean;
	}

	/**
	 * Unique non-negative chip indexes, at most `MAX_PICKS`.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $raw Raw `indexes` of one pick.
	 *
	 * @return int[]
	 */
	private static function clean_pick_indexes( $raw ): array {

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$indexes = [];

		foreach ( $raw as $index ) {
			if ( is_numeric( $index ) && $index >= 0 ) {
				$indexes[] = (int) $index;
			}
		}

		return array_slice( array_values( array_unique( $indexes ) ), 0, self::MAX_PICKS );
	}

	/**
	 * Keep only a sanitized `page` from the client context.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $context Raw context param.
	 *
	 * @return array
	 */
	private static function clean_context( $context ): array {

		if ( ! is_array( $context ) ) {
			return [];
		}

		$out = [];

		// `context` is a bare object in the schema, so core validates nothing
		// inside it and `page` can arrive as an array.
		if ( isset( $context['page'] ) && is_scalar( $context['page'] ) ) {
			$page = substr( sanitize_key( (string) $context['page'] ), 0, 64 );

			if ( $page !== '' ) {
				$out['page'] = $page;
			}
		}

		return $out;
	}
}
