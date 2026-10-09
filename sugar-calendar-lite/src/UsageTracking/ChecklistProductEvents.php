<?php

namespace Sugar_Calendar\UsageTracking;

use Sugar_Calendar\SetupChecklist\Checklist;
use Sugar_Calendar\SetupChecklist\State;
use Sugar_Calendar\SetupWizard\RestApi as SetupWizardRestApi;
use WP_REST_Request;

/**
 * Track Setup Checklist interactions and completion transitions.
 * Atomic claims are saved before enqueue and are not replayed after client failure.
 *
 * @since 3.15.0
 */
class ChecklistProductEvents extends AbstractProductEvents {

	/**
	 * Per-site checklist state.
	 *
	 * @since 3.15.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Authoritative checklist model.
	 *
	 * @since 3.15.0
	 *
	 * @var Checklist
	 */
	private $checklist;

	/**
	 * Whether the current checklist AJAX request passed its validation boundary.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $checklist_ajax_authorized = false;

	/**
	 * Whether ordinary REST authentication reached the pre-callback boundary.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $rest_request_authorized = false;

	/**
	 * Whether the current REST request belongs to the hosted setup wizard.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $setup_wizard_request = false;

	/**
	 * Whether the hosted setup wizard authenticated its bound administrator.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $setup_wizard_authorized = false;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param State     $state     Per-site checklist state.
	 * @param Checklist $checklist Authoritative checklist model.
	 */
	public function __construct( State $state, Checklist $checklist ) {

		$this->state     = $state;
		$this->checklist = $checklist;
	}

	/**
	 * Register event and observation hooks.
	 *
	 * Lifecycle and shutdown observation live in SetupChecklist\Loader, which
	 * invalidates on change and observes once, live, at shutdown.
	 *
	 * @since 3.15.0
	 */
	protected function hooks() {

		add_filter( 'rest_request_before_callbacks', [ $this, 'observe_rest_request' ], 10, 3 );
		add_action( 'sugar_calendar_setup_wizard_request_authorized', [ $this, 'observe_setup_wizard_request' ] );

		add_action( 'sugar_calendar_setup_checklist_refreshed', [ $this, 'observe_checklist_request' ], 10, 1 );
		add_action( 'sugar_calendar_setup_checklist_item_cta_clicked', [ $this, 'cta_clicked' ], 10, 2 );
		add_action( 'sugar_calendar_setup_checklist_dismissed', [ $this, 'dismissed' ] );
		add_action( 'sugar_calendar_setup_checklist_viewed', [ $this, 'viewed' ] );
		add_filter( 'sugar_calendar_usage_tracking_get_data', [ $this, 'filter_usage_tracking_data' ] );
	}

	/**
	 * Add the six checklist fields to usage tracking data.
	 *
	 * @since 3.15.0
	 *
	 * @param array $data Usage tracking data.
	 *
	 * @return array
	 */
	public function filter_usage_tracking_data( $data ) {

		$state = $this->state->get();

		$this->checklist->adopt_state( $state );

		$snapshot = $this->checklist->get_snapshot();

		$data['sugar_calendar_checklist_completed_items']       = implode( ',', $snapshot['completed_items'] );
		$data['sugar_calendar_checklist_visible_items']         = implode( ',', $snapshot['visible_items'] );
		$data['sugar_calendar_checklist_dismissed']             = $state['dismissal'] !== null;
		$data['sugar_calendar_checklist_dismissed_time']        = (int) ( $state['dismissal']['time'] ?? 0 );
		$data['sugar_calendar_checklist_progress_at_dismissal'] = (int) ( $state['dismissal']['percent'] ?? 0 );
		$data['sugar_calendar_checklist_first_seen_time']       = (int) $state['first_seen_time'];

		return $data;
	}

	/**
	 * Respect the usage-tracking load opt-out, since this tracker boots on its own.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	protected function is_enabled() {

		/**
		 * This filter is documented in UsageTracking::load().
		 *
		 * @since 3.0.0
		 *
		 * @param bool $allowed Whether usage tracking may load.
		 */
		$load_allowed = (bool) apply_filters( 'sugar_calendar_usage_tracking_load_allowed', true ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return $load_allowed && parent::is_enabled();
	}

	/**
	 * Observe current completion and enqueue any claimed transitions.
	 *
	 * @since 3.15.0
	 *
	 * @return bool|\WP_Error Whether observation ran, or a persistence error.
	 */
	public function observe() {

		if ( ! $this->is_observation_allowed() ) {
			return false;
		}

		$claims = $this->claim_transitions();

		if ( is_wp_error( $claims ) ) {
			return $claims;
		}

		$this->enqueue_claims( $claims );

		return true;
	}

	/**
	 * Authorize shutdown observation once per genuine REST request.
	 *
	 * In-process `rest_do_request()` preloads never define REST_REQUEST. A dispatch nested
	 * in `rest_api_init` runs before the outer request is authenticated, so it is skipped.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed           $response Request response or error accumulated so far.
	 * @param array           $handler  Matched route handler.
	 * @param WP_REST_Request $request  REST request.
	 *
	 * @return mixed
	 */
	public function observe_rest_request( $response, $handler, $request ) {

		if (
			! defined( 'REST_REQUEST' ) ||
			! REST_REQUEST ||
			doing_action( 'rest_api_init' ) ||
			$this->rest_request_authorized ||
			$this->setup_wizard_request
		) {
			return $response;
		}

		$is_setup_wizard = $request instanceof WP_REST_Request &&
			strpos( $request->get_route(), '/' . SetupWizardRestApi::URL_PREFIX . '/' ) === 0;

		if ( $is_setup_wizard ) {
			$this->setup_wizard_request = true;

			return $response;
		}

		if ( ! is_wp_error( $response ) ) {
			$this->rest_request_authorized = true;
		}

		return $response;
	}

	/**
	 * Authorize shutdown observation after the hosted wizard verifies its bound user.
	 *
	 * @since 3.15.0
	 */
	public function observe_setup_wizard_request() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->setup_wizard_authorized = true;
	}

	/**
	 * Allow observation after a validated checklist refresh.
	 *
	 * @since 3.15.0
	 *
	 * @param array $snapshot Authoritative snapshot supplied by the action.
	 */
	public function observe_checklist_request( $snapshot ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->checklist_ajax_authorized = true;

		$this->observe();
	}

	/**
	 * Record an authorized checklist view and claim its daily event.
	 *
	 * @since 3.15.0
	 */
	public function viewed() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// No dismissal read here: observe() and claim_view() refuse a dismissed checklist inside their writes, and a failed read fails record_first_seen().
		$recorded = $this->state->record_first_seen();

		if ( is_wp_error( $recorded ) || ! $this->is_observation_allowed() ) {
			return;
		}

		if ( is_wp_error( $this->observe() ) ) {
			return;
		}

		if ( $this->claim_view() === true ) {
			$this->track_event( 'checklist_viewed', [] );
		}
	}

	/**
	 * Track a CTA click on a visible item before its action runs.
	 *
	 * @since 3.15.0
	 *
	 * @param string     $item_id Canonical checklist item ID.
	 * @param array|null $state   The undismissed State the request already read; read fresh when absent.
	 */
	public function cta_clicked( $item_id, $state = null ) {

		$this->checklist_ajax_authorized = true;

		if (
			! current_user_can( 'manage_options' ) ||
			! $this->is_observation_allowed() ||
			! $this->is_current_item( $item_id, $state )
		) {
			return;
		}

		$observed = $this->observe();

		if ( is_wp_error( $observed ) || ! $this->is_enabled() ) {
			return;
		}

		$this->track_event( 'checklist_item_cta_clicked', [ 'item' => $item_id ] );
	}

	/**
	 * Track the dismissal using the persisted record.
	 *
	 * @since 3.15.0
	 *
	 * @param array $record Persisted dismissal record.
	 */
	public function dismissed( $record ) {

		$this->checklist_ajax_authorized = true;

		if ( ! current_user_can( 'manage_options' ) || ! $this->is_observation_allowed() || ! is_array( $record ) ) {
			return;
		}

		// Only the persisted winning record is tracked; a failed read tracks nothing.
		if ( $this->state->get_fresh_dismissal() !== $record ) {
			return;
		}

		$this->track_event(
			'checklist_dismissed',
			[
				'items_completed' => $record['completed_items'],
				'items_visible'   => $record['visible_items'],
			]
		);
	}

	/**
	 * Persist the current observation and return the transitions this request claimed.
	 *
	 * @since 3.15.0
	 *
	 * @return array|\WP_Error Item claims and the all-complete claim, or an error.
	 */
	private function claim_transitions() {

		$attempt = 0;

		return $this->state->mutate(
			function ( $state ) use ( &$attempt ) {

				$no_claims = [
					'items'        => [],
					'all_complete' => false,
				];

				if ( $state['dismissal'] !== null ) {
					return [
						'state'  => $state,
						'result' => $no_claims,
					];
				}

				// A retry means another request wrote State; the memo may predate its change.
				if ( $attempt++ > 0 ) {
					$this->checklist->invalidate();
				}

				$current = $this->get_current_completion( $state );

				$enabled = $this->is_enabled();
				$claims  = $this->get_transition_claims( $state['observed'], $current, $enabled );

				$state['observed'] = [
					'enabled'      => $enabled,
					'items'        => $current['items'],
					'all_complete' => $current['all_complete'],
				];

				return [
					'state'  => $state,
					'result' => $claims,
				];
			}
		);
	}

	/**
	 * Compare the previous observation with the current completion map.
	 *
	 * @since 3.15.0
	 *
	 * @param array|null $previous Previous normalized observation.
	 * @param array      $current  Current completion map.
	 * @param bool       $enabled  Whether telemetry is currently enabled.
	 *
	 * @return array Item claims and the all-complete claim.
	 */
	private function get_transition_claims( $previous, $current, $enabled ) {

		$claims   = [
			'items'        => [],
			'all_complete' => false,
		];
		$baseline = $previous === null || ! $previous['enabled'] || ! $enabled;

		if ( $baseline ) {
			return $claims;
		}

		foreach ( $current['items'] as $item_id => $complete ) {
			if (
				array_key_exists( $item_id, $previous['items'] ) &&
				$previous['items'][ $item_id ] === false &&
				$complete === true
			) {
				$claims['items'][] = $item_id;
			}
		}

		$claims['all_complete'] = ! $previous['all_complete'] && $current['all_complete'];

		return $claims;
	}

	/**
	 * Compute the visible item completion map from the given State.
	 *
	 * @since 3.15.0
	 *
	 * @param array $state Fresh normalized State from the mutation retry.
	 *
	 * @return array Current item booleans and all-complete state.
	 */
	private function get_current_completion( $state ) {

		$this->checklist->adopt_state( $state );

		$snapshot  = $this->checklist->get_snapshot();
		$completed = array_fill_keys( $snapshot['completed_items'], true );
		$items     = [];

		foreach ( $snapshot['visible_items'] as $item_id ) {
			$items[ $item_id ] = isset( $completed[ $item_id ] );
		}

		return [
			'items'        => $items,
			'all_complete' => ! empty( $snapshot['is_complete'] ),
		];
	}

	/**
	 * Enqueue the claimed completion events.
	 *
	 * Claims are already persisted, so a transport failure loses the event rather than
	 * replaying it. A dismissed checklist claims nothing, so there is no dismissal re-read.
	 *
	 * @since 3.15.0
	 *
	 * @param array $claims Owned transition claims.
	 */
	private function enqueue_claims( $claims ) {

		foreach ( $claims['items'] as $item_id ) {
			$this->track_event( 'checklist_item_completed', [ 'item' => $item_id ] );
		}

		if ( ! empty( $claims['all_complete'] ) ) {
			$this->track_event( 'checklist_completed', [] );
		}
	}

	/**
	 * Claim today's checklist view while tracking is enabled.
	 *
	 * @since 3.15.0
	 *
	 * @return bool|\WP_Error Whether this request claimed the view, or an error.
	 */
	private function claim_view() {

		return $this->state->mutate(
			function ( $state ) {

				if ( $state['dismissal'] !== null || ! $this->is_enabled() ) {
					return [
						'state'  => $state,
						'result' => false,
					];
				}

				$day     = gmdate( 'Y-m-d' );
				$claimed = $state['viewed_day'] !== $day;

				if ( $claimed ) {
					$state['viewed_day'] = $day;
				}

				return [
					'state'  => $state,
					'result' => $claimed,
				];
			}
		);
	}

	/**
	 * Whether an item is in the current visible checklist set.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed      $item_id Candidate item ID.
	 * @param array|null $state   State the request already read; read fresh when absent.
	 *
	 * @return bool
	 */
	private function is_current_item( $item_id, $state = null ) {

		if ( ! is_string( $item_id ) ) {
			return false;
		}

		$state = is_array( $state ) ? $state : $this->state->get_fresh();

		// A failed read counts as not current, so no click event is recorded.
		if ( is_wp_error( $state ) || $state['dismissal'] !== null ) {
			return false;
		}

		$current = $this->get_current_completion( $state );

		return array_key_exists( $item_id, $current['items'] );
	}

	/**
	 * Whether local observation is allowed independently of telemetry consent.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function is_observation_allowed() {

		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}

		if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		if (
			defined( 'REST_REQUEST' ) &&
			REST_REQUEST &&
			! $this->rest_request_authorized &&
			! $this->setup_wizard_authorized
		) {
			return false;
		}

		if ( $this->setup_wizard_request && ! $this->setup_wizard_authorized ) {
			return false;
		}

		return ! $this->is_unvalidated_checklist_ajax();
	}

	/**
	 * Whether this is a checklist AJAX request that has not been validated yet.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function is_unvalidated_checklist_ajax() {

		if ( ! wp_doing_ajax() || $this->checklist_ajax_authorized ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This only narrows observation; Ajax::handle() owns request validation.
		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] )
			? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		return $action === 'sugar_calendar_setup_checklist';
	}
}
