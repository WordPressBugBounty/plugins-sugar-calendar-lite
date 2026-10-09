<?php
/**
 * Drives a user turn, one relay round at a time.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Ai;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Turn: step() runs one relay round plus the tool calls it asked for; run()
 * loops step() to a terminal answer for callers that can afford one long
 * request (the CLI harness).
 *
 * Product API never executes a tool, so every round is either `tool_calls`
 * or an answer. Splitting at the round lets the browser drive a turn one
 * request at a time: Stop, live status labels, and no eight-round PHP request.
 *
 * @since 3.15.0
 */
class Turn {

	/**
	 * Tool rounds one turn may take, matching the relay's
	 * `max_tool_rounds_per_turn`. The relay withholds the tool list once the
	 * budget is spent, so this only stops a runaway loop if it does not.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_TOOL_ROUNDS = 8;

	/**
	 * The ability whose success this turn records on the state.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const CREATE_EVENT_ABILITY = AbilityAdvertiser::PREFIX . 'create-event';

	/**
	 * The ability whose per-event outcomes this turn records on the state.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const UPDATE_EVENT_ABILITY = AbilityAdvertiser::PREFIX . 'update-event';

	/**
	 * Transport.
	 *
	 * @since 3.15.0
	 *
	 * @var Chat
	 */
	private $chat;

	/**
	 * Ability advertiser.
	 *
	 * @since 3.15.0
	 *
	 * @var AbilityAdvertiser
	 */
	private $advertiser;

	/**
	 * Tool executor.
	 *
	 * @since 3.15.0
	 *
	 * @var ToolCallRunner
	 */
	private $runner;

	/**
	 * Collects the records a tool round resolved.
	 *
	 * @since 3.15.0
	 *
	 * @var EntityHarvester
	 */
	private $harvester;

	/**
	 * Records what a tool round wrote.
	 *
	 * @since 3.15.0
	 *
	 * @var WriteRecorder
	 */
	private $recorder;

	/**
	 * Builds the Ask answer's "Show me" link from the turn's own calls.
	 *
	 * @since 3.15.0
	 *
	 * @var CallLink
	 */
	private $links;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param Chat|null              $chat       Transport.
	 * @param AbilityAdvertiser|null $advertiser Ability advertiser.
	 * @param ToolCallRunner|null    $runner     Tool executor.
	 * @param EntityHarvester|null   $harvester  Entity harvester.
	 * @param WriteRecorder|null     $recorder   Write recorder.
	 * @param CallLink|null          $links      Show-me link builder.
	 */
	public function __construct( ?Chat $chat = null, ?AbilityAdvertiser $advertiser = null, ?ToolCallRunner $runner = null, ?EntityHarvester $harvester = null, ?WriteRecorder $recorder = null, ?CallLink $links = null ) {

		$this->chat       = $chat ?? new Chat();
		$this->advertiser = $advertiser ?? new AbilityAdvertiser();
		$this->runner     = $runner ?? new ToolCallRunner();
		$this->harvester  = $harvester ?? new EntityHarvester();
		$this->recorder   = $recorder ?? new WriteRecorder();
		$this->links      = $links ?? new CallLink();
	}

	/**
	 * Run one relay round and execute any tool calls it returns.
	 *
	 * Mutates `$state`. A transport error returns before anything but trimming
	 * is recorded, so a Retry is step() again on the same state. The round-cap
	 * error comes after the prompt was recorded; recover with with_prompt().
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState $state Current state, advanced one round in place.
	 *
	 * @return WP_Error|null Null on success; check `$state->is_terminal()` for the answer.
	 */
	public function step( TurnState $state ) {

		$abilities = $this->advertiser->advertise();

		if ( $abilities === [] ) {
			return new WP_Error(
				'sc_ai_no_abilities',
				__( 'This site has no event abilities to work with. The assistant needs WordPress 6.9 or newer.', 'sugar-calendar-lite' )
			);
		}

		$state->trim_history( Chat::MAX_HISTORY_ENTRIES );

		$response = $this->chat->send( $state, $abilities );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$state->commit_prompt()->record_response(
			isset( $response['sessionId'] ) ? (string) $response['sessionId'] : null,
			isset( $response['responseId'] ) ? (string) $response['responseId'] : '',
			isset( $response['credits'] ) && is_array( $response['credits'] ) ? $response['credits'] : null
		);

		$tool_calls = isset( $response['tool_calls'] ) && is_array( $response['tool_calls'] ) ? $response['tool_calls'] : [];

		if ( $tool_calls === [] ) {
			$this->finish_turn( $state, $response );

			return null;
		}

		if ( $state->round() >= self::MAX_TOOL_ROUNDS ) {
			return new WP_Error(
				'sc_ai_tool_loop',
				__( 'The assistant kept asking for data without answering. Please try again.', 'sugar-calendar-lite' ),
				[ 'credits' => $state->credits() ]
			);
		}

		$this->continue_turn( $state, $tool_calls );

		return null;
	}

	/**
	 * Record the answer and collapse the finished turn.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState $state    Current state.
	 * @param array     $response Decoded relay body with no `tool_calls`.
	 *
	 * @return void
	 */
	private function finish_turn( TurnState $state, array $response ) {

		// The relay's prompt guard sends a `refusal` code and no copy, so the sentence is translated here.
		$refused = ! empty( $response['refusal'] );
		$answer  = $refused ? self::refusal_answer() : $this->to_answer( $response );

		if ( $refused ) {
			$state->mark_refused();
		}

		// The turn's own calls decide an Ask answer's "Show me" link.
		if ( $state->funnel() === Funnels::ASK_EVENTS ) {
			$answer['blocks'] = $this->links->apply( $answer['blocks'], $state->history() );
		}

		$this->report_write( $state );

		$state->finish( $answer );
	}

	/**
	 * Events the closing turn created or updated, and updates it failed.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState $state Current state.
	 *
	 * @return int[] `[ updated, failed ]`; a create counts as one updated event.
	 */
	private function turn_write_counts( TurnState $state ): array {

		if ( $state->turn_created() ) {
			return [ 1, 0 ];
		}

		$write = $state->last_write();

		if ( ! is_array( $write ) || $state->turn_seq() !== (int) ( $write['turn'] ?? -1 ) ) {
			return [ 0, 0 ];
		}

		return [ count( $write['updated'] ?? [] ), count( $write['failed'] ?? [] ) ];
	}

	/**
	 * Count a turn that wrote and mark the session's first write.
	 *
	 * Runs before finish(): the write record's `turn` and the create flag belong
	 * to the turn that is closing, and finish() clears the round's results.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState $state Current state.
	 *
	 * @return void
	 */
	private function report_write( TurnState $state ) {

		$created = $state->turn_created();

		list( $updated, $failed ) = $this->turn_write_counts( $state );

		// A turn whose update calls all failed wrote nothing.
		if ( ! $created && $updated === 0 ) {
			return;
		}

		$action = $created ? 'create' : 'update';
		$first  = $state->writes() === 0;

		$state->count_write( $updated );

		if ( ! $first ) {
			return;
		}

		$state->mark_converted( $action, $updated, $failed );
	}

	/**
	 * Execute the round's tool calls and append the round to history.
	 *
	 * One conversation creates one event. After Stop the tool round is gone
	 * from history and the model may try again, so the guard lives here, not
	 * in the relay prompt.
	 *
	 * @since 3.15.0
	 *
	 * @param TurnState $state      Current state.
	 * @param array     $tool_calls The round's `tool_calls`, as received.
	 *
	 * @return void
	 */
	private function continue_turn( TurnState $state, array $tool_calls ) {

		// One create per conversation AND per round: `$blocked` covers later
		// rounds, `$once` covers two create calls inside this same batch, which
		// would otherwise both run and orphan the second event.
		$once    = [ self::CREATE_EVENT_ABILITY => __( 'An event was already created in this conversation. Use update-event to change it.', 'sugar-calendar-lite' ) ];
		$blocked = [];

		if ( $state->created_event() !== null ) {
			$blocked[ self::CREATE_EVENT_ABILITY ] = $once[ self::CREATE_EVENT_ABILITY ];
		}

		// Each funnel declares which abilities it withholds and why (see
		// `Funnels`); the edit funnel blocks create-event so `createdEvent`
		// never rides an edit round, where the relay would misread it.
		$funnel = Funnels::get( $state->funnel() );

		if ( $funnel !== null ) {
			$blocked = array_merge( $blocked, $funnel->blocked_abilities() );
		}

		$results = $this->runner->run( $tool_calls, $blocked, $once, $state->session_id() );

		$state->remember_harvest( $this->harvester->harvest( $results ) );

		$this->recorder->record( $state, $tool_calls, $results );

		$created = $this->recorder->created_event( $results );

		$state->record_tool_round( $this->to_history( $tool_calls, $results ), $results )
			->remember_created_event( $created );

		if ( $created !== null ) {
			$state->mark_turn_created();
		}
	}

	/**
	 * Run a turn to completion in one call.
	 *
	 * @since 3.15.0
	 *
	 * @param string $funnel      One of the Funnels::* slugs.
	 * @param string $user_prompt The user's message.
	 * @param array  $history     Prior conversation entries, oldest first, the
	 *                            `history` a previous run() returned.
	 * @param string $session_id  Session id from a previous turn, if any.
	 * @param array  $context     Optional relay request context.
	 *
	 * @return array|WP_Error `answer`, `history`, `sessionId`, `credits`, `rounds`, `trace`.
	 *                        `history` is the whole conversation including this
	 *                        turn's prompt and reply, so it is what a follow-up
	 *                        turn passes back in.
	 */
	public function run( string $funnel, string $user_prompt, array $history = [], string $session_id = '', array $context = [] ) {

		$state = ( new TurnState( $funnel, $context ) )->continue_from( $history, $session_id )->with_prompt( $user_prompt );

		$trace = [];

		do {
			$error = $this->step( $state );

			if ( is_wp_error( $error ) ) {
				$error->add_data( array_merge( (array) $error->get_error_data(), [ 'trace' => $trace ] ) );

				return $error;
			}

			if ( ! $state->is_terminal() ) {
				$trace[] = $state->last_results();
			}
		} while ( ! $state->is_terminal() );

		return [
			'answer'    => $state->answer(),
			'history'   => $state->history(),
			'sessionId' => $state->session_id(),
			'credits'   => $state->credits(),
			'rounds'    => $state->round() + 1,
			'trace'     => $trace,
		];
	}

	/**
	 * Shape a terminal response as the answer the UI renders.
	 *
	 * @since 3.15.0
	 *
	 * @param array $response Decoded relay body.
	 *
	 * @return array `title`, `text`, `notice`, `blocks`, `done`.
	 */
	private function to_answer( array $response ): array {

		$blocks = $response['blocks'] ?? null;

		return [
			'title'  => isset( $response['title'] ) ? (string) $response['title'] : '',
			'text'   => isset( $response['text'] ) ? (string) $response['text'] : '',
			'notice' => isset( $response['notice'] ) && is_string( $response['notice'] ) ? $response['notice'] : null,
			'blocks' => is_array( $blocks ) ? $blocks : [],
			// Gia's goodbye. Error envelopes and older relays send no `done`.
			'done'   => ! empty( $response['done'] ),
		];
	}

	/**
	 * The answer for a prompt the relay's guard refused.
	 *
	 * @since 3.15.0
	 *
	 * @return array `title`, `text`, `notice`, `blocks`, `done`.
	 */
	private static function refusal_answer(): array {

		return [
			'title'  => '',
			'text'   => __( 'Sorry, I can\'t help with that. I\'m here to help you create, edit and find your events.', 'sugar-calendar-lite' ),
			'notice' => null,
			'blocks' => [],
			'done'   => false,
		];
	}

	/**
	 * The continuation-round history for one completed tool round: the
	 * assistant entry that made the calls, then one entry per result.
	 *
	 * @since 3.15.0
	 *
	 * @param array $tool_calls The calls, replayed as received so the provider's
	 *                          reasoning items stay attached to them.
	 * @param array $results    Results from ToolCallRunner::run().
	 *
	 * @return array
	 */
	private function to_history( array $tool_calls, array $results ): array {

		$entries = [
			[
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => $tool_calls,
			],
		];

		foreach ( $results as $result ) {
			$entries[] = [
				'role'         => 'tool',
				'tool_call_id' => $result['tool_call_id'],
				'name'         => $result['name'],
				'content'      => $result['content'],
			];
		}

		return $entries;
	}
}
