<?php
/**
 * Transport for the Product API AI assistant endpoints.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Vendor\ProductApi\Auth\HMACAuthStrategy;
use Sugar_Calendar\Integrations\OAuthRelay\RelayLicenseType;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;
use Throwable;
use WP_Error;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_currency;

/**
 * Chat — one HTTP round against `ai/v1/{license_type}/chat`, plus the credit
 * snapshot from `…/usage`.
 *
 * The agent loop runs ACROSS requests: Product API never executes a tool, so a
 * response carries either `tool_calls` or a terminal answer and this class has
 * no loop. `Turn` drives the rounds.
 *
 * @since 3.15.0
 */
class Chat {

	/**
	 * Tool calls this plugin will execute in one round. The surplus is rejected
	 * with a `too_many_calls` tool error rather than executed.
	 *
	 * MUST stay in sync with the relay's `config/assistant.php`
	 * `max_tool_calls_per_round` — the server states the cap in the system
	 * prompt so the model self-limits, and a plugin that enforced a lower one
	 * would silently reject calls the model was told it could make.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_TOOL_CALLS_PER_ROUND = 10;

	/**
	 * Longest tool-result string the endpoint accepts, from its
	 * `history.*.content` `max:20000` rule. A longer result is replaced with a
	 * `result_too_large` tool error, because a body that trips validation
	 * fails the whole round for no charge but no answer either.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_TOOL_RESULT_LENGTH = 20000;

	/**
	 * Most history entries the endpoint accepts, from its `history` `max:100`
	 * rule. Turn trims older completed turns to stay under it.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_HISTORY_ENTRIES = 100;

	/**
	 * Longest user prompt the relay accepts, in characters: `chat`'s `prompt`
	 * and `feedback`'s `userPrompt` share the `max:8000` rule.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_PROMPT_LENGTH = 8000;

	/**
	 * Largest encoded feedback body sent, in bytes. The endpoint aborts 413
	 * above 64 KB, and `wp_json_encode()` escapes non-ASCII six bytes to the
	 * character, so the character caps alone do not keep a Cyrillic or CJK
	 * body under it.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_FEEDBACK_BODY_BYTES = 60000;

	/**
	 * Longest `answer.text` the feedback endpoint accepts, from its `max:20000`
	 * rule.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_FEEDBACK_ANSWER_LENGTH = 20000;

	/**
	 * Longest `answer.title` the feedback endpoint accepts, from its `max:300`
	 * rule.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_FEEDBACK_TITLE_LENGTH = 300;

	/**
	 * Most `answer.blocks` entries the feedback endpoint accepts, from its
	 * `max:20` rule.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_FEEDBACK_BLOCKS = 20;

	/**
	 * Seconds to wait for a provider round. The vendor client leaves the WP
	 * default of 5s, which no LLM round completes in.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private const TIMEOUT = 90;

	/**
	 * Seconds to wait for a feedback call. A rating is one small INSERT with no
	 * provider round behind it, the caller does not surface the outcome, and the
	 * REST response waits on it, so it must not hold a worker the way a chat
	 * round may.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private const FEEDBACK_TIMEOUT = 3;

	/**
	 * Run one chat round for the state's next round.
	 *
	 * Optional body fields are sent only when set. A null or empty
	 * `pending_prompt` (a continuation round, or the recap's confirm control)
	 * is OMITTED: the endpoint treats an absent prompt after a `tool` entry as
	 * "the user said nothing new", which is what reaches its continuation path.
	 * `confirmed`, `createdEvent`, `updatedEvents` and `entities` are turn or conversation facts
	 * that compaction removes from the history, so they ride along every round.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState $state     State whose history has already been trimmed to the entry cap.
	 * @param array     $abilities Advertised abilities: `name`, `description`, `input_schema`.
	 *
	 * @return array|WP_Error Decoded response body, or the endpoint's error.
	 */
	public function send( TurnState $state, array $abilities ) {

		$body = array_merge(
			[
				'funnel'    => $state->funnel(),
				'abilities' => $abilities,
				'history'   => $state->history(),
			],
			$this->site_clock()
		);

		$currency = $this->site_currency();

		if ( $currency !== '' ) {
			$body['siteCurrency'] = $currency;
		}

		if ( $state->pending_prompt() !== null && $state->pending_prompt() !== '' ) {
			$body['userPrompt'] = $state->pending_prompt();
		}

		if ( $state->session_id() !== '' ) {
			$body['sessionId'] = $state->session_id();
		}

		if ( $state->context() !== [] ) {
			$body['context'] = $state->context();
		}

		if ( $state->confirmed() ) {
			$body['confirmed'] = true;
		}

		$created    = $state->created_event();
		$created_id = isset( $created['event_id'] ) ? (int) $created['event_id'] : 0;

		if ( $created_id > 0 ) {
			$body['createdEvent'] = [ 'eventId' => $created_id ];
		}

		if ( $state->entities() !== [] ) {
			$body['entities'] = $state->entities();
		}

		if ( $state->updated_events() !== [] ) {
			$body['updatedEvents'] = self::updated_events_body( $state->updated_events() );
		}

		return $this->request( 'post', 'chat', $body );
	}

	/**
	 * The relay's `updatedEvents` list, from TurnState::$updated_events.
	 *
	 * `previous` is cast to an object so an event whose values were all skipped still sends `{}`,
	 * not `[]`. `skipped` stays on the server.
	 *
	 * @since 3.15.0
	 *
	 * @param array $updated TurnState::$updated_events.
	 *
	 * @return array
	 */
	private static function updated_events_body( array $updated ): array {

		$events = [];

		foreach ( $updated as $event_id => $entry ) {
			$events[] = [
				'eventId'  => (int) $event_id,
				'title'    => isset( $entry['title'] ) ? (string) $entry['title'] : null,
				'previous' => (object) ( $entry['previous'] ?? [] ),
			];
		}

		return $events;
	}

	/**
	 * The site's local wall clock, zone and the user's locale, read fresh for
	 * every round.
	 *
	 * Not cached on the turn: a conversation can outlive an hour or a profile
	 * change, and a stale clock resolves "tomorrow" to the wrong day. The
	 * browser's clock and language are never used, since events are stored in
	 * the site's zone and the relay otherwise defaults to English.
	 *
	 * @since 3.15.0
	 *
	 * @return array `siteTime`, `siteTimezone` and `locale`, each only when non-empty.
	 */
	private function site_clock(): array {

		$clock  = [];
		$time   = wp_date( 'Y-m-d\TH:i:s' );
		$zone   = wp_timezone_string();
		$locale = get_user_locale();

		if ( is_string( $time ) && $time !== '' ) {
			$clock['siteTime'] = $time;
		}

		if ( $zone !== '' ) {
			$clock['siteTimezone'] = $zone;
		}

		if ( is_string( $locale ) && $locale !== '' ) {
			$clock['locale'] = $locale;
		}

		return $clock;
	}

	/**
	 * The store's ISO 4217 currency code, or '' when Event Ticketing is not loaded.
	 *
	 * Sent as a body field because the relay replaces the abilities' own field
	 * descriptions, so a price's currency cannot reach the model through the schema.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	private function site_currency(): string {

		$features = sugar_calendar()->get_common_features();

		if ( empty( $features ) || empty( $features->get_feature( 'EventTicketing' ) ) || ! function_exists( 'Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_currency' ) ) {
			return '';
		}

		$currency = strtoupper( (string) get_currency() );

		return preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : '';
	}

	/**
	 * Credit snapshot for the notice bar. Costs no credits, so it doubles as
	 * the cheap check that auth and the base URL are wired correctly.
	 *
	 * @since 3.15.0
	 *
	 * @return array|WP_Error
	 */
	public function usage() {

		return $this->request( 'get', 'usage' );
	}

	/**
	 * File the user's rating of one answer. Costs no credits.
	 *
	 * The relay upserts on `(site, responseId)`, so re-sending a rating is safe
	 * and re-sending the SAME rating is a no-op that does not inflate its change
	 * counter. `$previous` is advisory: the relay derives the counter from the
	 * row it already holds, so a wrong value here cannot corrupt it.
	 *
	 * Content is sent on every call whatever the rating. The relay decides what
	 * to keep — every down-vote, plus a deterministic sample of up-votes — so
	 * the sampling rate stays a relay config value that needs no plugin release
	 * to change.
	 *
	 * Named arguments rather than positional: `funnel`, `rating` and `previous`
	 * are all bare strings the relay would accept transposed.
	 *
	 * @since 3.15.0
	 *
	 * @param array $args {
	 *     The rating.
	 *
	 *     @type string      $session_id   Relay session id the answer belongs to.
	 *     @type string      $response_id  Relay response id naming the answer. Required: it is the identity.
	 *     @type int         $answer_index The answer's transcript position, stored as ordering and debug data.
	 *     @type string      $funnel       One of the FUNNEL_* slugs.
	 *     @type string      $rating       The new rating: 'up', 'down', or 'none' to clear.
	 *     @type string      $previous     The rating this replaces. Default 'none'.
	 *     @type string|null $user_prompt  The prompt that produced the answer, or null.
	 *     @type array       $answer       The rated answer: `title`, `text`, `blocks`.
	 *     @type array       $context      Conversation context: `exchanges` and `createdEvent`.
	 * }
	 *
	 * @return array|WP_Error `[ rating, changes ]`, or the endpoint's error.
	 */
	public function feedback( array $args ) {

		$args = array_merge(
			[
				'session_id'   => '',
				'response_id'  => '',
				'answer_index' => 0,
				'funnel'       => '',
				'rating'       => '',
				'previous'     => 'none',
				'user_prompt'  => null,
				'answer'       => [],
				'context'      => [],
			],
			$args
		);

		$context = is_array( $args['context'] ) ? $args['context'] : [];
		$prompt  = is_string( $args['user_prompt'] ) ? $args['user_prompt'] : '';

		$body = [
			'sessionId'   => (string) $args['session_id'],
			'responseId'  => (string) $args['response_id'],
			'answerIndex' => (int) $args['answer_index'],
			'funnel'      => (string) $args['funnel'],
			'rating'      => (string) $args['rating'],
			'previous'    => (string) $args['previous'],
			'answer'      => $this->feedback_answer( is_array( $args['answer'] ) ? $args['answer'] : [] ),
			'context'     => [
				'exchanges'    => isset( $context['exchanges'] ) ? (int) $context['exchanges'] : 0,
				'createdEvent' => ! empty( $context['createdEvent'] ),
			],
		];

		if ( $prompt !== '' ) {
			$body['userPrompt'] = self::truncate( $prompt, self::MAX_PROMPT_LENGTH );
		}

		return $this->request( 'post', 'feedback', self::within_body_budget( $body ) );
	}

	/**
	 * Shed content until the encoded body fits the endpoint's byte cap.
	 *
	 * The prompt goes first: the answer is what a down-vote is about. Each pass
	 * halves the answer text, and the loop ends once nothing is left to cut.
	 *
	 * @since 3.15.0
	 *
	 * @param array $body Assembled feedback body.
	 *
	 * @return array
	 */
	private static function within_body_budget( array $body ): array {

		while ( self::encoded_length( $body ) > self::MAX_FEEDBACK_BODY_BYTES ) {

			if ( isset( $body['userPrompt'] ) ) {
				unset( $body['userPrompt'] );

				continue;
			}

			$text = (string) $body['answer']['text'];

			if ( $text === '' ) {
				break;
			}

			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

			$body['answer']['text'] = self::truncate( $text, (int) floor( $length / 2 ) );
		}

		return $body;
	}

	/**
	 * Bytes the vendored client will put on the wire for a body.
	 *
	 * @since 3.15.0
	 *
	 * @param array $body Request body.
	 *
	 * @return int
	 */
	private static function encoded_length( array $body ): int {

		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $body ) : json_encode( $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		return is_string( $encoded ) ? strlen( $encoded ) : 0;
	}

	/**
	 * The rated answer, trimmed to what the endpoint accepts.
	 *
	 * Trimmed rather than left to the endpoint because the whole call is
	 * fire-and-forget: a 422 on a long answer would lose the rating silently,
	 * and a truncated snippet still reads.
	 *
	 * @since 3.15.0
	 *
	 * @param array $answer Stored answer entry.
	 *
	 * @return array
	 */
	private function feedback_answer( array $answer ): array {

		$blocks = isset( $answer['blocks'] ) && is_array( $answer['blocks'] ) ? $answer['blocks'] : [];
		$types  = [];

		// Only `type` is sent. A block's `html` can repeat the whole answer, which
		// is what would push a long recap's body past the endpoint's 64KB cap, and
		// the far side reads nothing else off a block.
		foreach ( array_slice( array_values( $blocks ), 0, self::MAX_FEEDBACK_BLOCKS ) as $block ) {
			$types[] = [ 'type' => isset( $block['type'] ) ? (string) $block['type'] : '' ];
		}

		return [
			'title'  => self::truncate( isset( $answer['title'] ) ? (string) $answer['title'] : '', self::MAX_FEEDBACK_TITLE_LENGTH ),
			'text'   => self::truncate( isset( $answer['text'] ) ? (string) $answer['text'] : '', self::MAX_FEEDBACK_ANSWER_LENGTH ),
			'blocks' => $types,
		];
	}

	/**
	 * Cut a string to a character budget, counting characters rather than bytes
	 * so a multibyte prompt is measured the way the endpoint measures it.
	 *
	 * @since 3.15.0
	 *
	 * @param string $value  Raw value.
	 * @param int    $length Maximum length.
	 *
	 * @return string
	 */
	private static function truncate( string $value, int $length ): string {

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}

		return substr( $value, 0, $length );
	}

	/**
	 * Send a signed request to an assistant endpoint.
	 *
	 * `protected` rather than private so a test double can capture the body
	 * without a live relay: the field names here are a contract with the
	 * endpoint's validator, and a typo in one is a silent 422.
	 *
	 * @since 3.15.0
	 *
	 * @param string $method   Either 'get' or 'post'.
	 * @param string $endpoint Endpoint name under `ai/v1/{license_type}/`.
	 * @param array  $body     JSON body, for POST.
	 *
	 * @return array|WP_Error
	 */
	protected function request( string $method, string $endpoint, array $body = [] ) {

		try {
			$client = ProductApi::client()->auth_strategy( ProductApi::get( HMACAuthStrategy::class ) );
		} catch ( Throwable $e ) {
			// Both editions configure ProductApi (Pro in includes/pro/Pro.php, Lite in
			// OAuthRelay\Loader); this only guards a build without the vendored client.
			return new WP_Error( 'sc_ai_not_configured', $e->getMessage() );
		}

		$path = '/ai/v1/' . RelayLicenseType::get() . '/' . $endpoint;

		$request = $method === 'post'
			? $client->post( $path )->json( $body )
			: $client->get( $path );

		$request = $request->timeout( self::timeout_for( $endpoint ) );

		$response = $request->send();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = $response->get_body();

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'sc_ai_bad_response',
				__( 'The assistant returned a response we could not read.', 'sugar-calendar-lite' ),
				[ 'status' => $response->get_status_code() ]
			);
		}

		if ( ! $response->is_successful() ) {
			return $this->to_error( $decoded, $response->get_status_code() );
		}

		return $decoded;
	}

	/**
	 * Seconds to wait on one endpoint.
	 *
	 * Only `feedback` differs: it is one small write with no provider round
	 * behind it, and its caller does not surface the outcome, so it must not
	 * hold the request open the way a chat round may.
	 *
	 * @since 3.15.0
	 *
	 * @param string $endpoint Endpoint name.
	 *
	 * @return int
	 */
	private static function timeout_for( string $endpoint ): int {

		return $endpoint === 'feedback' ? self::FEEDBACK_TIMEOUT : self::TIMEOUT;
	}

	/**
	 * Turn the endpoint's `{ error: { code, message }, credits }` body into a
	 * WP_Error. The vendor Response::get_errors() only understands the
	 * `errors[]` shape, so it would flatten every failure into `unknown`.
	 *
	 * @since 3.15.0
	 *
	 * @param array $body   Decoded response body.
	 * @param int   $status HTTP status code.
	 *
	 * @return WP_Error
	 */
	private function to_error( array $body, int $status ): WP_Error {

		$error = isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : [];

		return new WP_Error(
			isset( $error['code'] ) ? (string) $error['code'] : 'sc_ai_http_' . $status,
			isset( $error['message'] ) ? (string) $error['message'] : __( 'The assistant could not answer.', 'sugar-calendar-lite' ),
			[
				'status'  => $status,
				'credits' => $body['credits'] ?? null,
			]
		);
	}
}
