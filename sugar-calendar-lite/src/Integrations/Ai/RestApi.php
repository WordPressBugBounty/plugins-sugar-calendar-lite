<?php
/**
 * REST layer for the Gia AI assistant.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Ai\Credits\AiUsageService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Routes under `sugar-calendar/ai/v1` that drive one `Turn::step()` per request
 * and persist a per-user session between requests.
 *
 * @since 3.15.0
 */
class RestApi {

	const REST_NAMESPACE = 'sugar-calendar/ai/v1';

	/**
	 * Turns, Stop and Clear.
	 *
	 * @since 3.15.0
	 *
	 * @var Conversation
	 */
	private $conversation;

	/**
	 * Answer ratings.
	 *
	 * @since 3.15.0
	 *
	 * @var AnswerFeedback
	 */
	private $feedback;

	/**
	 * Permission callbacks.
	 *
	 * @since 3.15.0
	 *
	 * @var AccessPolicy
	 */
	private $access;

	/**
	 * Usage service, used by /usage.
	 *
	 * @since 3.15.0
	 *
	 * @var AiUsageService
	 */
	private $usage;

	/**
	 * Per-user request budget, used by /usage.
	 *
	 * @since 3.15.0
	 *
	 * @var StepRateLimiter
	 */
	private $limiter;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param Conversation|null    $conversation Turns, Stop and Clear.
	 * @param AnswerFeedback|null  $feedback     Answer ratings.
	 * @param AccessPolicy|null    $access       Permission callbacks.
	 * @param AiUsageService|null  $usage        Usage service.
	 * @param StepRateLimiter|null $limiter      Rate limiter.
	 */
	public function __construct( ?Conversation $conversation = null, ?AnswerFeedback $feedback = null, ?AccessPolicy $access = null, ?AiUsageService $usage = null, ?StepRateLimiter $limiter = null ) {

		$store   = new SessionStore();
		$chat    = new Chat();
		$limiter = $limiter ?? new StepRateLimiter();

		$this->conversation = $conversation ?? new Conversation( $store, new Turn( $chat ), new Mentions(), $limiter );
		$this->feedback     = $feedback ?? new AnswerFeedback( $store, $chat, $limiter );
		$this->access       = $access ?? new AccessPolicy( $store );
		$this->usage        = $usage ?? new AiUsageService( $chat );
		$this->limiter      = $limiter;
	}

	/**
	 * Register the routes.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			self::REST_NAMESPACE,
			'/turn',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_turn' ],
				'permission_callback' => [ $this->access, 'can_turn' ],
				'args'                => $this->turn_args(),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/stop',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_stop' ],
				'permission_callback' => [ $this->access, 'can_manage_session' ],
				'args'                => [
					'turn_id' => self::turn_id_arg(),
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/session',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handle_get_session' ],
					'permission_callback' => [ $this->access, 'can_manage_session' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'handle_delete_session' ],
					'permission_callback' => [ $this->access, 'can_manage_session' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/usage',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_usage' ],
				'permission_callback' => [ $this->access, 'can_access' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/feedback',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_feedback' ],
				'permission_callback' => [ $this->access, 'can_manage_session' ],
				'args'                => $this->feedback_args(),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/plugin-rated',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_plugin_rated' ],
				'permission_callback' => [ $this->access, 'can_manage_session' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/intro-seen',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_intro_seen' ],
				'permission_callback' => [ $this->access, 'can_manage_session' ],
			]
		);
	}

	/**
	 * Arg schema for /turn.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function turn_args(): array {

		return [
			'funnel'    => [
				'type'     => 'string',
				'enum'     => Availability::funnels(),
				'required' => false,
			],
			'prompt'    => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => [ TurnInput::class, 'sanitize_prompt' ],
				'validate_callback' => static function ( $value ) {
					$prompt = TurnInput::sanitize_prompt( $value );
					$length = function_exists( 'mb_strlen' ) ? mb_strlen( $prompt ) : strlen( $prompt );

					return $length >= 1 && $length <= Chat::MAX_PROMPT_LENGTH;
				},
			],
			'context'   => [
				'type'     => 'object',
				'required' => false,
			],
			'confirmed' => [
				'type'     => 'boolean',
				'required' => false,
			],
			'entities'  => [
				'type'              => 'array',
				'required'          => false,
				'sanitize_callback' => [ TurnInput::class, 'sanitize_mentions' ],
			],
			'picks'     => [
				'type'              => 'array',
				'required'          => false,
				'sanitize_callback' => [ TurnInput::class, 'sanitize_picks' ],
			],
			'turn_id'   => self::turn_id_arg(),
		];
	}

	/**
	 * Arg schema for the client's turn id, shared by /turn and /stop.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private static function turn_id_arg(): array {

		return [
			'type'      => 'string',
			'required'  => false,
			'maxLength' => 64,
			'pattern'   => '^[A-Za-z0-9-]*$',
		];
	}

	/**
	 * Arg schema for /feedback.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function feedback_args(): array {

		return [
			'rating'      => [
				'required' => true,
				'type'     => 'string',
				'enum'     => [ 'up', 'down', 'none' ],
			],
			'previous'    => [
				'type'    => 'string',
				'enum'    => [ 'up', 'down', 'none' ],
				'default' => 'none',
			],
			'index'       => [
				'required'          => true,
				'type'              => 'integer',
				'validate_callback' => static function ( $value ) {

					return is_numeric( $value ) && (int) $value >= 0;
				},
			],
			// The answer's identity. `index` alone can name a different entry once
			// the stored transcript has been trimmed from the front.
			'response_id' => [
				'type'      => 'string',
				'maxLength' => 64,
				'default'   => '',
			],
		];
	}

	/**
	 * Run one relay round.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_turn( WP_REST_Request $request ) {

		return rest_ensure_response( $this->conversation->turn( get_current_user_id(), TurnInput::from_request( $request ) ) );
	}

	/**
	 * Stop the in-progress turn.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_stop( WP_REST_Request $request ) {

		return rest_ensure_response( $this->conversation->stop( get_current_user_id(), (string) $request->get_param( 'turn_id' ) ) );
	}

	/**
	 * Hydrate the session.
	 *
	 * @since 3.15.0
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_session() {

		return rest_ensure_response( $this->conversation->current( get_current_user_id() ) );
	}

	/**
	 * Clear the session.
	 *
	 * @since 3.15.0
	 *
	 * @return WP_REST_Response
	 */
	public function handle_delete_session() {

		return rest_ensure_response( $this->conversation->clear( get_current_user_id() ) );
	}

	/**
	 * Record a thumbs rating for one stored answer.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_feedback( WP_REST_Request $request ) {

		return rest_ensure_response(
			$this->feedback->rate(
				get_current_user_id(),
				(int) $request->get_param( 'index' ),
				(string) $request->get_param( 'rating' ),
				(string) $request->get_param( 'response_id' ),
				(string) $request->get_param( 'previous' )
			)
		);
	}

	/**
	 * Record that the current user clicked Rate Plugin, so the End card stops asking.
	 *
	 * @since 3.15.0
	 *
	 * @return WP_REST_Response
	 */
	public function handle_plugin_rated() {

		PluginRatedFlag::mark( get_current_user_id() );

		return rest_ensure_response( [ 'plugin_rated' => true ] );
	}

	/**
	 * Record that the current user opened the chat, so the bubble stops introducing Gia.
	 *
	 * @since 3.15.0
	 *
	 * @return WP_REST_Response
	 */
	public function handle_intro_seen() {

		IntroSeenFlag::mark( get_current_user_id() );

		return rest_ensure_response( [ 'intro_seen' => true ] );
	}

	/**
	 * Credit usage, cached per tier.
	 *
	 * @since 3.15.0
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_usage() {

		$usage = $this->fetch_usage();

		if ( is_wp_error( $usage ) ) {
			return $usage;
		}

		if ( isset( $usage['error'] ) && is_array( $usage['error'] ) ) {
			return $this->usage_error( $usage['error'] );
		}

		return rest_ensure_response( $usage );
	}

	/**
	 * Usage from the per-tier cache, falling back to one relay call.
	 *
	 * The rate limit is charged below the cache read and on its own window: a
	 * cached poll makes no relay call, so it must not spend a slot /turn needs.
	 *
	 * @since 3.15.0
	 *
	 * @return array|WP_Error The payload, `[ error => [ code, status ] ]` when the relay
	 *                        failed, or a WP_Error when the caller is rate limited.
	 */
	private function fetch_usage() {

		$cached = $this->usage->cached();

		if ( $cached !== null ) {
			return $cached;
		}

		$limited = $this->limiter->attempt( get_current_user_id(), 'usage' );

		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		return $this->usage->fetch();
	}

	/**
	 * Rebuild the /usage `WP_Error` from a cached or fresh failure.
	 *
	 * Never surfaces get_error_message(): it can carry raw provider or
	 * exception text. Only the code and status leave the server.
	 *
	 * @since 3.15.0
	 *
	 * @param array $error Shaped `[ code, status ]`.
	 *
	 * @return WP_Error
	 */
	private function usage_error( array $error ): WP_Error {

		// Defaulted, not trusted: the shape comes back from a transient an
		// earlier build or a partial write may have left in another shape.
		return new WP_Error(
			$error['code'] ?? 'sc_ai_usage_error',
			__( 'The assistant could not read your usage.', 'sugar-calendar-lite' ),
			[ 'status' => isset( $error['status'] ) ? (int) $error['status'] : 502 ]
		);
	}
}
