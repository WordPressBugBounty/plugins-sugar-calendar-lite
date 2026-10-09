<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Admin\Pages\SettingsGeneralTab;
use Sugar_Calendar\Helpers\ActionResult;
use WP_Error;

/**
 * The refresh, dismiss and archive_observed tasks: the checklist's own lifecycle, not a row's.
 *
 * @since 3.15.0
 */
class LifecycleTasks {

	/**
	 * Per-site state.
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
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param State     $state     Per-site state.
	 * @param Checklist $checklist Authoritative checklist model.
	 */
	public function __construct( State $state, Checklist $checklist ) {

		$this->state     = $state;
		$this->checklist = $checklist;
	}

	/**
	 * Refresh authoritative state without recording a page view.
	 *
	 * @since 3.15.0
	 *
	 * @param array $state Undismissed State from `Ajax::handle()`.
	 *
	 * @return array
	 */
	public function refresh( $state ) {

		$snapshot = $this->get_snapshot( $state );

		/**
		 * Fires after an authorized action refreshes the checklist model.
		 *
		 * @since 3.15.0
		 *
		 * @param array $snapshot Authoritative snapshot.
		 */
		do_action( 'sugar_calendar_setup_checklist_refreshed', $snapshot ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- the Loader rewrites the progress transient and the tracker observes here.

		return array_merge( ActionResult::build( 'success', '', '', false ), [ 'snapshot' => $this->public_snapshot( $state, $snapshot ) ] );
	}

	/**
	 * Atomically dismiss the checklist with a fresh server snapshot.
	 *
	 * @since 3.15.0
	 *
	 * @return array|WP_Error
	 */
	public function dismiss() {

		$dismissed = $this->state->dismiss(
			function ( $state ) {

				$this->checklist->adopt_state( $state );

				return $this->checklist->get_snapshot();
			}
		);

		if ( is_wp_error( $dismissed ) ) {
			return $dismissed;
		}

		if ( ! empty( $dismissed['created'] ) ) {
			/**
			 * Fires after the exact winning dismissal record is persisted.
			 *
			 * @since 3.15.0
			 *
			 * @param array $dismissal Persisted dismissal record.
			 */
			do_action( 'sugar_calendar_setup_checklist_dismissed', $dismissed['dismissal'] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.
		}

		return ActionResult::build( 'success', '', SettingsGeneralTab::get_url(), false );
	}

	/**
	 * Reconcile a previously recorded genuine archive visit.
	 *
	 * @since 3.15.0
	 *
	 * @param array $state Undismissed State from `Ajax::handle()`.
	 *
	 * @return array|WP_Error
	 */
	public function archive_observed( $state ) {

		$snapshot = $this->get_snapshot( $state );

		if ( ! isset( $state['history'][ Config::ITEM_VISIT_EVENTS_PAGE ] ) ) {
			return new WP_Error( 'checklist_archive_not_observed', __( 'The events page visit could not be verified.', 'sugar-calendar-lite' ) );
		}

		/** This action is documented in LifecycleTasks::refresh(). */
		// phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName, WPForms.Comments.PHPDocHooks.RequiredHookDocumentation
		do_action( 'sugar_calendar_setup_checklist_refreshed', $snapshot );

		return ActionResult::build( 'success', '', '', false );
	}

	/**
	 * Adopt the request's State into the model and return the snapshot it yields.
	 *
	 * @since 3.15.0
	 *
	 * @param array $state Undismissed State from `Ajax::handle()`.
	 *
	 * @return array
	 */
	private function get_snapshot( $state ) {

		$this->checklist->adopt_state( $state );

		return $this->checklist->get_snapshot();
	}

	/**
	 * Public fields used by the focus-refresh client.
	 *
	 * @since 3.15.0
	 *
	 * @param array $state    Current State.
	 * @param array $snapshot Current snapshot.
	 *
	 * @return array
	 */
	private function public_snapshot( $state, $snapshot ) {

		return [
			'visible_items'   => $snapshot['visible_items'],
			'completed_items' => $snapshot['completed_items'],
			'is_complete'     => $snapshot['is_complete'],
			'dismissed'       => $state['dismissal'] !== null,
		];
	}
}
