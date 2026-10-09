<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Admin\Pages\SettingsGeneralTab;
use WP_Error;

/**
 * The Setup Checklist's authenticated AJAX boundary.
 *
 * Checks the request, reads State once and hands it to the task's handler; the handlers
 * live in `ItemActions`, `LifecycleTasks` and `PartnerActions`.
 *
 * @since 3.15.0
 */
class Ajax {

	/**
	 * Per-site state.
	 *
	 * @since 3.15.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Submitted-field reader and validator.
	 *
	 * @since 3.15.0
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * The closed task set: each task name and the callable that handles it.
	 *
	 * @since 3.15.0
	 *
	 * @var callable[]
	 */
	private $tasks;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param State      $state   Per-site state.
	 * @param Request    $request Submitted-field reader and validator.
	 * @param callable[] $tasks   Handlers keyed by task name; each receives the undismissed State, or null for `dismiss`.
	 */
	public function __construct( State $state, Request $request, array $tasks ) {

		$this->state   = $state;
		$this->request = $request;
		$this->tasks   = $tasks;
	}

	/**
	 * Register the authenticated AJAX entry point.
	 *
	 * @since 3.15.0
	 */
	public function hooks() {

		add_action( 'wp_ajax_sugar_calendar_setup_checklist', [ $this, 'handle' ] );
	}

	/**
	 * Validate and dispatch one closed checklist task.
	 *
	 * @since 3.15.0
	 */
	public function handle() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh,Generic.Metrics.CyclomaticComplexity.MaxExceeded -- each branch is a request boundary guard.

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: '';

		if ( $request_method !== 'post' ) {
			$this->send_error( new WP_Error( 'checklist_method_not_allowed', __( 'This checklist action requires a POST request.', 'sugar-calendar-lite' ) ), 405 );
		}

		if ( ! $this->has_scalar_nonce() || check_ajax_referer( 'sugar_calendar_setup_checklist', 'nonce', false ) === false ) {
			$this->send_error( new WP_Error( 'checklist_invalid_nonce', __( 'This checklist request has expired. Refresh the page and try again.', 'sugar-calendar-lite' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( new WP_Error( 'checklist_forbidden', __( 'You are not allowed to manage the setup checklist.', 'sugar-calendar-lite' ) ), 403 );
		}

		$task = $this->request->post_key( 'task' );

		if ( is_wp_error( $task ) || ! isset( $this->tasks[ $task ] ) ) {
			$this->send_error( new WP_Error( 'checklist_unknown_task', __( 'This checklist action is not supported.', 'sugar-calendar-lite' ) ) );
		}

		$valid_fields = $this->request->validate_post_fields( $task );

		if ( is_wp_error( $valid_fields ) ) {
			$this->send_error( $valid_fields );
		}

		// One uncached read serves the request; the handlers adopt it rather than read again.
		$state = null;

		if ( $task !== 'dismiss' ) {
			$state = $this->state->get_fresh();

			if ( is_wp_error( $state ) ) {
				$this->send_error( $state );
			}

			if ( $state['dismissal'] !== null ) {
				$this->send_error( $this->dismissed_error() );
			}
		}

		$result = call_user_func( $this->tasks[ $task ], $state );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Build the immutable-dismissal response for stale requests.
	 *
	 * @since 3.15.0
	 *
	 * @return WP_Error
	 */
	private function dismissed_error() {

		return new WP_Error(
			'checklist_dismissed',
			__( 'The setup checklist has been dismissed.', 'sugar-calendar-lite' ),
			[ 'redirect_url' => SettingsGeneralTab::get_url() ]
		);
	}

	/**
	 * Send one plain standard JSON error envelope.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_Error $error  Error to send.
	 * @param int      $status HTTP status.
	 */
	private function send_error( WP_Error $error, $status = 400 ) {

		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : [];
		$data = array_intersect_key( $data, array_flip( [ 'plugin_state', 'redirect_url' ] ) );
		$data = array_merge(
			[
				'code'    => (string) $error->get_error_code(),
				'message' => wp_strip_all_tags( $error->get_error_message() ),
			],
			$data
		);

		wp_send_json_error( $data, $status );
	}

	/**
	 * Whether the nonce boundary contains only the expected scalar POST value.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function has_scalar_nonce() {

		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- These scalar guards must run before nonce verification.
		$valid = isset( $_POST['nonce'], $_REQUEST['nonce'] ) &&
			is_string( $_POST['nonce'] ) &&
			is_string( $_REQUEST['nonce'] ) &&
			$_POST['nonce'] === $_REQUEST['nonce'];
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended

		return $valid;
	}
}
