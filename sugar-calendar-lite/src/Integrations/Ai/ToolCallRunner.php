<?php
/**
 * Executes the tool calls one assistant round asked for.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Throwable;
use WP_Ability;
use WP_Error;

/**
 * ToolCallRunner — runs a round's `tool_calls` through the Abilities API and
 * returns one result per call, ready to be replayed as history.
 *
 * @since 3.15.0
 */
class ToolCallRunner {

	/**
	 * The status-label argument Product API adds to every tool schema.
	 *
	 * It belongs to the endpoint, not to the ability: it is a present-continuous
	 * string to show the user while the call runs. Every `sc-events/` ability
	 * declares `additionalProperties: false`, so passing it through fails the
	 * call with `ability_invalid_input` — stripping it is load-bearing, not
	 * defensive.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const LABEL_ARG = '_label';

	/**
	 * The argument a create ability dedupes a retried call on.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const IDEMPOTENCY_ARG = 'idempotency_key';

	/**
	 * Execute a round's tool calls, in order.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $tool_calls The round's `tool_calls`, as received.
	 * @param array  $blocked    Ability name => reason. A blocked call is not
	 *                           executed and returns that reason as an error result.
	 * @param array  $once       Ability name => reason. The same block, armed after
	 *                           that name's first successful call in this round.
	 * @param string $scope      Conversation the calls belong to, or ''. Scopes `idempotency_key`.
	 *
	 * @return array List of `[ tool_call_id, name, content, label, is_error ]`,
	 *               one per call, in the same order.
	 */
	public function run( array $tool_calls, array $blocked = [], array $once = [], string $scope = '' ): array {

		$results = [];
		$spent   = [];

		foreach ( array_values( $tool_calls ) as $index => $call ) {

			if ( ! is_array( $call ) ) {
				continue;
			}

			$name      = isset( $call['name'] ) ? (string) $call['name'] : '';
			$arguments = self::decode_arguments( $call['arguments'] ?? null );

			// `result_id` is the provider's call_id and the only value the
			// endpoint matches a tool result against. `id` is the item id and
			// will silently orphan the result.
			$result = [
				'tool_call_id' => isset( $call['result_id'] ) ? (string) $call['result_id'] : '',
				'name'         => $name,
				'label'        => $this->extract_label( $arguments ),
				'is_error'     => true,
			];

			if ( $index >= Chat::MAX_TOOL_CALLS_PER_ROUND ) {
				$result['content'] = $this->error_content(
					'too_many_calls',
					sprintf(
						/* translators: %d - the number of tool calls allowed in one round. */
						__( 'Not executed: at most %d tool calls run per round. Re-emit this call in a later round.', 'sugar-calendar-lite' ),
						Chat::MAX_TOOL_CALLS_PER_ROUND
					)
				);

				$results[] = $result;

				continue;
			}

			if ( isset( $blocked[ $name ] ) || isset( $spent[ $name ] ) ) {
				$message           = isset( $blocked[ $name ] ) ? $blocked[ $name ] : $spent[ $name ];
				$result['content'] = $this->error_content( 'ability_blocked', (string) $message );
				$results[]         = $result;

				continue;
			}

			$executed = array_merge( $result, $this->execute( $name, $arguments, $scope ) );

			if ( isset( $once[ $name ] ) && empty( $executed['is_error'] ) ) {
				$spent[ $name ] = (string) $once[ $name ];
			}

			$results[] = $executed;
		}

		return $results;
	}

	/**
	 * Execute one ability.
	 *
	 * @since 3.15.0
	 *
	 * @param string $name      Ability name.
	 * @param array  $arguments Decoded call arguments, `_label` included.
	 * @param string $scope     Conversation the call belongs to, or ''.
	 *
	 * @return array `content` and `is_error`.
	 */
	private function execute( string $name, array $arguments, string $scope ): array {

		$ability = $this->resolve_ability( $name );

		if ( $ability === null ) {
			return $this->error_result( 'unknown_ability', __( 'This site has no such ability.', 'sugar-calendar-lite' ) );
		}

		unset( $arguments[ self::LABEL_ARG ] );

		$key = $arguments[ self::IDEMPOTENCY_ARG ] ?? '';

		// The model builds the key from the event's title and time, so without the chat in it
		// a new chat creating the same event got the draft an earlier chat made.
		if ( $scope !== '' && is_string( $key ) && $key !== '' ) {
			$arguments[ self::IDEMPOTENCY_ARG ] = md5( $scope . "\0" . $key );
		}

		$output = $this->run_ability( $ability, $arguments );

		if ( is_wp_error( $output ) ) {
			return $this->error_result( $output->get_error_code(), $output->get_error_message() );
		}

		return $this->result_within_limit( (string) wp_json_encode( $output ) );
	}

	/**
	 * Look up an ability the site may run from a model-produced name.
	 *
	 * The relay allowlist is remote; this is the site's own boundary. Core and
	 * third-party abilities must never run from a model-produced name.
	 *
	 * @since 3.15.0
	 *
	 * @param string $name Ability name.
	 *
	 * @return WP_Ability|null
	 */
	private function resolve_ability( string $name ): ?WP_Ability {

		if ( strpos( $name, AbilityAdvertiser::PREFIX ) !== 0 || ! function_exists( 'wp_has_ability' ) || ! wp_has_ability( $name ) ) {
			return null;
		}

		return wp_get_ability( $name );
	}

	/**
	 * Run the ability, turning an exception into an error the model can read.
	 *
	 * The relay already charged the round, so the turn must not abort. The raw
	 * message is logged rather than forwarded: it could carry paths or SQL.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_Ability $ability   Resolved ability.
	 * @param array      $arguments Call arguments without `_label`.
	 *
	 * @return mixed|WP_Error
	 */
	private function run_ability( WP_Ability $ability, array $arguments ) {

		try {
			return $ability->execute( $arguments );
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( '[SC Gia] %s threw: %s', $ability->get_name(), $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return new WP_Error( 'ability_exception', __( 'The ability failed unexpectedly.', 'sugar-calendar-lite' ) );
		}
	}

	/**
	 * Reject a result the endpoint's `history.*.content` rule would refuse.
	 *
	 * A body over the limit fails the whole round, so the model gets something
	 * it can retry smaller instead.
	 *
	 * @since 3.15.0
	 *
	 * @param string $content JSON-encoded ability output.
	 *
	 * @return array `content` and `is_error`.
	 */
	private function result_within_limit( string $content ): array {

		if ( strlen( $content ) > Chat::MAX_TOOL_RESULT_LENGTH ) {
			return $this->error_result(
				'result_too_large',
				__( 'The result was too large to return. Ask for fewer items or a narrower date range.', 'sugar-calendar-lite' )
			);
		}

		return [
			'content'  => $content,
			'is_error' => false,
		];
	}

	/**
	 * A failed call's result pair.
	 *
	 * @since 3.15.0
	 *
	 * @param string $code    Machine-readable failure code.
	 * @param string $message Human-readable reason.
	 *
	 * @return array `content` and `is_error`.
	 */
	private function error_result( string $code, string $message ): array {

		return [
			'content'  => $this->error_content( $code, $message ),
			'is_error' => true,
		];
	}

	/**
	 * The status label the model attached to this call.
	 *
	 * @since 3.15.0
	 *
	 * @param array $arguments Decoded call arguments.
	 *
	 * @return string Empty when the model omitted it.
	 */
	private function extract_label( array $arguments ): string {

		$label = $arguments[ self::LABEL_ARG ] ?? '';

		return is_string( $label ) ? $label : '';
	}

	/**
	 * Decode a tool call's arguments, tolerating the provider's string or array shape.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $arguments Raw `arguments` value.
	 *
	 * @return array
	 */
	public static function decode_arguments( $arguments ): array {

		if ( is_array( $arguments ) ) {
			return $arguments;
		}

		if ( ! is_string( $arguments ) || $arguments === '' ) {
			return [];
		}

		$decoded = json_decode( $arguments, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * A tool result the model reads as a failure.
	 *
	 * @since 3.15.0
	 *
	 * @param string $code    Machine-readable failure code.
	 * @param string $message Human-readable reason.
	 *
	 * @return string JSON.
	 */
	private function error_content( string $code, string $message ): string {

		return (string) wp_json_encode(
			[
				'error' => [
					'code'    => $code,
					'message' => $message,
				],
			]
		);
	}

	/**
	 * The error code in a tool result's content, or '' when it isn't the
	 * `error_content()` shape.
	 *
	 * @since 3.15.0
	 *
	 * @param string $content A tool result's `content`.
	 *
	 * @return string
	 */
	public static function error_code( string $content ): string {

		$output = json_decode( $content, true );

		return is_array( $output ) && isset( $output['error']['code'] ) && is_string( $output['error']['code'] ) ? $output['error']['code'] : '';
	}

	/**
	 * Whether a tool result's content is the `error_content()` shape.
	 *
	 * @since 3.15.0
	 *
	 * @param string $content A tool result's `content`.
	 *
	 * @return bool
	 */
	public static function is_error_content( string $content ): bool {

		$output = json_decode( $content, true );

		return is_array( $output ) && isset( $output['error'] );
	}
}
