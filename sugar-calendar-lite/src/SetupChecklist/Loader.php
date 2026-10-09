<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;
use Sugar_Calendar\Admin\Tools\Smtp;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Options;
use Sugar_Calendar\PartnerPlugins\Catalog;
use Sugar_Calendar\PartnerPlugins\Installer;
use Sugar_Calendar\UsageTracking\ChecklistProductEvents;
use Sugar_Calendar\UsageTracking\OptIn;
use WP_Post;

/**
 * Wire the shared Setup Checklist model and lifecycle consumers.
 *
 * @since 3.15.0
 */
class Loader {

	/**
	 * Non-expiring transient holding the last computed menu progress.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const PROGRESS_TRANSIENT = 'sugar_calendar_checklist_progress';

	/**
	 * Whether completion may have changed during this request.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $changed = false;

	/**
	 * Whether the progress transient was written during this request.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $progress_written = false;

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
	 * Saved calendar block recorder and one-time bootstrap scan.
	 *
	 * @since 3.15.0
	 *
	 * @var EmbeddedCalendar
	 */
	private $embedded_calendar;

	/**
	 * Checklist Product Events tracker.
	 *
	 * @since 3.15.0
	 *
	 * @var ChecklistProductEvents
	 */
	private $tracker;

	/**
	 * Construct collaborators and register their hooks.
	 *
	 * @since 3.15.0
	 */
	public function init() {

		$state             = new State();
		$catalog           = new Catalog();
		$embedded_calendar = new EmbeddedCalendar( $state );
		$smtp              = new Smtp( $catalog );
		$detector          = new CompletionDetector( $state, $catalog, $smtp );

		$this->state             = $state;
		$this->checklist         = new Checklist( $detector, $catalog );
		$this->embedded_calendar = $embedded_calendar;

		$stripe_connect  = new StripeConnect();
		$editor          = new Editor();
		$request         = new Request();
		$item_actions    = new ItemActions( $this->checklist, $request, new PageSearch() );
		$lifecycle_tasks = new LifecycleTasks( $state, $this->checklist );
		$partner_actions = new PartnerActions( $catalog, new Installer(), $request );
		$ajax            = new Ajax(
			$state,
			$request,
			[
				'item_action'      => [ $item_actions, 'run' ],
				'pages'            => [ $item_actions, 'search_pages' ],
				'refresh'          => [ $lifecycle_tasks, 'refresh' ],
				'dismiss'          => [ $lifecycle_tasks, 'dismiss' ],
				'archive_observed' => [ $lifecycle_tasks, 'archive_observed' ],
				'growth_tool'      => [ $partner_actions, 'install_growth_tool' ],
			]
		);

		// Each row's click is claimed on the item-action filter by the class that owns its service.
		( new HistoryObserver( $state ) )->hooks();
		$embedded_calendar->hooks();
		$stripe_connect->hooks();
		$ajax->hooks();
		$editor->hooks();
		$partner_actions->hooks();
		( new Signups( $state, new OptIn( $state ) ) )->hooks();
		$this->tracker = new ChecklistProductEvents( $state, $this->checklist );
		$this->tracker->init();
		$this->hooks();
	}

	/**
	 * Register the lifecycle hooks and every hook that can change completion.
	 *
	 * @since 3.15.0
	 */
	private function hooks() {

		add_action( 'sugar_calendar_setup_checklist_refreshed', [ $this, 'write_progress' ] );
		add_action( 'switch_blog', [ $this, 'reset' ] );
		add_action( 'shutdown', [ $this, 'observe_at_shutdown' ], 5 );

		$invalidating_hooks = [
			'sugar_calendar_added_event',
			'sugar_calendar_updated_event',
			'activated_plugin',
			'deactivated_plugin',
			'sugar_calendar_plugin_activated',
			'deleted_plugin',
			'upgrader_process_complete',
			'sugar_calendar_setup_checklist_changed',
			'sugar_calendar_setup_checklist_dismissed',
			'delete_option_' . State::OPTION_NAME,
			'update_option_' . Options::OPTION_NAME,
			'add_option_' . Options::OPTION_NAME,
			'update_option_wp_mail_smtp',
			'update_option_sc_et_settings',
			'add_option_sc_et_settings',
			'add_option_' . TheEventCalendar::SC_TEC_MIGRATION_OPTION_KEY,
			'update_option_' . TheEventCalendar::SC_TEC_MIGRATION_OPTION_KEY,
			'sugar_calendar_oauth_connections_changed',
		];

		foreach ( CompletionDetector::STRIPE_OPTIONS as $option ) {
			$invalidating_hooks[] = 'add_option_' . $option;
			$invalidating_hooks[] = 'update_option_' . $option;
			$invalidating_hooks[] = 'delete_option_' . $option;
		}

		foreach ( $invalidating_hooks as $hook ) {
			add_action( $hook, [ $this, 'invalidate_progress' ] );
		}

		add_action( 'wp_after_insert_post', [ $this, 'invalidate_for_post' ], 10, 2 );
		add_action( 'trashed_post', [ $this, 'invalidate_for_post' ] );
		add_action( 'untrashed_post', [ $this, 'invalidate_for_post' ] );
		add_action( 'deleted_post', [ $this, 'invalidate_for_post' ], 10, 2 );
	}

	/**
	 * Read the live authoritative snapshot; a pure read that writes nothing.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function get_snapshot() {

		return $this->get_current()['snapshot'];
	}

	/**
	 * Read the checklist page data and persist the progress it was built from.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function get_view_data() {

		$this->maybe_bootstrap_embed_calendar();

		$current   = $this->get_current();
		$view_data = $this->checklist->get_view_data();

		$this->write_progress( $current['snapshot'] );

		return $view_data;
	}

	/**
	 * The shared per-site State.
	 *
	 * Dismissal guards call get_fresh_dismissal() on it themselves, so the uncached read
	 * stays visible at the call site.
	 *
	 * @since 3.15.0
	 *
	 * @return State
	 */
	public function get_state() {

		return $this->state;
	}

	/**
	 * Read the cached progress, or compute it once and cache it.
	 *
	 * @since 3.15.0
	 *
	 * @return array `total`, `completed_count` and `percent`.
	 */
	public function get_progress() {

		$cached = get_transient( self::PROGRESS_TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['total'], $cached['completed_count'], $cached['percent'] ) ) {
			return [
				'total'           => (int) $cached['total'],
				'completed_count' => (int) $cached['completed_count'],
				'percent'         => (int) $cached['percent'],
			];
		}

		global $wpdb;

		// Menu registration precedes migrations and redirects; keep database errors in the log.
		$show_errors = $wpdb->hide_errors();

		try {
			$this->maybe_bootstrap_embed_calendar();

			$current = $this->get_current();

			$this->write_progress( $current['snapshot'] );

			return $current['snapshot']['progress'];
		} finally {
			$wpdb->show_errors( $show_errors );
		}
	}

	/**
	 * Persist the progress of a live snapshot for the menu badge.
	 *
	 * Cron, CLI and the front end never write derived checklist data.
	 *
	 * @since 3.15.0
	 *
	 * @param array|null $snapshot Snapshot to persist; the memoized one when null.
	 */
	public function write_progress( $snapshot = null ) {

		if ( $this->progress_written || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( $this->state->is_dismissed() ) {
			return;
		}

		if ( ! is_array( $snapshot ) || ! isset( $snapshot['progress'] ) ) {
			$snapshot = $this->get_current()['snapshot'];
		}

		$progress = $snapshot['progress'];

		set_transient(
			self::PROGRESS_TRANSIENT,
			[
				'total'           => (int) $progress['total'],
				'completed_count' => (int) $progress['completed_count'],
				'percent'         => (int) $progress['percent'],
			],
			0
		);

		$this->progress_written = true;
	}

	/**
	 * Drop the cached progress and the request memo after a possible change.
	 *
	 * @since 3.15.0
	 */
	public function invalidate_progress() {

		delete_transient( self::PROGRESS_TRANSIENT );

		$this->checklist->invalidate();

		$this->changed          = true;
		$this->progress_written = false;
	}

	/**
	 * Observe the final request state live, only when something could have changed.
	 *
	 * The memo is discarded first: it may predate a concurrent request's change,
	 * and persisting it would regress the tracker's baseline.
	 *
	 * @since 3.15.0
	 */
	public function observe_at_shutdown() {

		if ( ! $this->changed ) {
			return;
		}

		$this->checklist->invalidate();

		if ( $this->tracker->observe() === true ) {
			$this->write_progress();
		}
	}

	/**
	 * Invalidate for saved, trashed, restored or deleted posts of watched types.
	 *
	 * @since 3.15.0
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object when the hook supplies one.
	 */
	public function invalidate_for_post( $post_id, $post = null ) {

		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}

		if (
			! $post instanceof WP_Post ||
			$post->post_status === 'auto-draft' ||
			$post->post_type === 'revision' ||
			! in_array( $post->post_type, $this->get_watched_post_types(), true )
		) {
			return;
		}

		$this->invalidate_progress();
	}

	/**
	 * Post types whose lifecycle can change a completion predicate.
	 *
	 * @since 3.15.0
	 *
	 * @return string[]
	 */
	private function get_watched_post_types() {

		/**
		 * Filters the post types whose saves, trashes and deletions invalidate checklist progress.
		 *
		 * Pro adds its speaker and venue post types here.
		 *
		 * @since 3.15.0
		 *
		 * @param string[] $post_types Post type names.
		 */
		$post_types = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.
			'sugar_calendar_setup_checklist_watched_post_types',
			array_merge( BaseHelpers::get_event_post_types(), [ 'page', 'tribe_events' ] )
		);

		return array_values( array_unique( array_filter( (array) $post_types, 'is_string' ) ) );
	}

	/**
	 * Run the one-time embed scan before a live pass: admin, interactive, `manage_options`, not dismissed.
	 *
	 * @since 3.15.0
	 */
	private function maybe_bootstrap_embed_calendar() {

		if (
			! is_admin() ||
			wp_doing_ajax() ||
			wp_doing_cron() ||
			( defined( 'WP_CLI' ) && WP_CLI ) ||
			! current_user_can( 'manage_options' ) ||
			$this->state->is_dismissed()
		) {
			return;
		}

		$this->embedded_calendar->bootstrap();
	}

	/**
	 * Clear request-local model state after switching sites.
	 *
	 * @since 3.15.0
	 */
	public function reset() {

		$this->checklist->invalidate();

		$this->progress_written = false;
	}

	/**
	 * Read cached State and the memoized snapshot built from it.
	 *
	 * @since 3.15.0
	 *
	 * @return array Snapshot and normalized State.
	 */
	private function get_current() {

		$state = $this->state->get();

		$this->checklist->adopt_state( $state );

		return [
			'snapshot' => $this->checklist->get_snapshot(),
			'state'    => $state,
		];
	}
}
