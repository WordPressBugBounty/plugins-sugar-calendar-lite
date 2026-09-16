<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Event_Query;
use Sugar_Calendar\Tasks\Tasks;
use Throwable;
use WP_Site;

/**
 * Email lifecycle decisions taken when the plugin is activated on a site.
 *
 * The Modern template is only correct for a site that has never run Sugar
 * Calendar. An upgrade must keep rendering what it rendered yesterday, so every
 * uncertain answer here is deliberately read as "this site already exists" and
 * nothing is written.
 *
 * A network-wide activation classifies the activation site in the request and
 * hands every other site of that network to Action Scheduler, one page at a
 * time, behind a durable per-site cursor. A site the background work has not
 * reached yet stores nothing, and nothing resolves to Plain Text, so an
 * interrupted rollout can never change what an existing site renders.
 *
 * @since 3.14.0
 */
class Installation {

	/**
	 * Action Scheduler hook that classifies one page of a network's sites.
	 *
	 * Callback signature: `( int $network_id, string $run_id, int $expected_cursor )`.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public const NETWORK_BATCH_HOOK = 'sugar_calendar_install_network_email_defaults_batch';

	/**
	 * Network option holding the temporary rollout cursor.
	 *
	 * Operational state only, deleted as soon as a page proves the run is
	 * finished. Deliberately private: nothing outside this class may depend on
	 * its name or shape.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private const NETWORK_PROGRESS_OPTION = 'sugar_calendar_email_defaults_network_progress';

	/**
	 * Sites read by one background batch before it is filtered.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	private const DEFAULT_BATCH_SIZE = 50;

	/**
	 * Largest page a filter can ask for.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	private const MAX_BATCH_SIZE = 500;

	/**
	 * Seconds one batch may spend classifying sites before it hands over.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	private const BATCH_TIME_LIMIT = 20;

	/**
	 * Attempts an unexpectedly failing site gets before the run moves past it.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	private const MAX_SITE_FAILURES = 3;

	/**
	 * Seconds before a retry of the same expected cursor runs.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	private const RETRY_DELAY = 60;

	/**
	 * Every key a usable rollout state must carry.
	 *
	 * @since 3.14.0
	 *
	 * @var string[]
	 */
	private const PROGRESS_KEYS = [
		'version',
		'run_id',
		'cursor',
		'activation_site_id',
		'scheduler_site_id',
		'failed_site_id',
		'failure_count',
		'updated_at',
	];

	/**
	 * Option names that prove Sugar Calendar has already run on a site.
	 *
	 * Current and pre-3.0 names both count. Raw row presence is what matters,
	 * so a stored `false` is evidence just like any other value.
	 *
	 * @since 3.14.0
	 *
	 * @var string[]
	 */
	private const EVIDENCE_OPTIONS = [
		'sugar_calendar_activated_time',
		'sugar_calendar_activated',
		'sugar_calendar_version',
		'sugar_calendar_is_first_activation',
		'sugar_calendar',
		'sc_version',
		'sc_completed_upgrades',
		'sc_et_settings',
		'default_sc_event_category',
		'default_term_sc_event_category',
	];

	/**
	 * Plugin basename, used to read the network activation list.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $plugin_base;

	/**
	 * Constructor.
	 *
	 * @since 3.14.0
	 *
	 * @param string $plugin_base Plugin basename.
	 */
	public function __construct( $plugin_base ) {

		$this->plugin_base = (string) $plugin_base;
	}

	/**
	 * Whether the current site holds no durable Sugar Calendar data.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_new_installation() {

		if ( $this->has_existing_installation_option() ) {
			return false;
		}

		if ( $this->has_existing_event() ) {
			return false;
		}

		return ! $this->has_existing_calendar();
	}

	/**
	 * Store the Modern template default when the current site is proven new.
	 *
	 * Activation writes its own option rows, and those rows are themselves
	 * evidence. The caller therefore classifies first and passes the result in.
	 *
	 * @since 3.14.0
	 *
	 * @param bool|null $is_new_installation Classification captured before the
	 *                                       caller's own writes. Null to classify now.
	 *
	 * @return bool Whether the default was stored.
	 */
	public function install_current_site_defaults( $is_new_installation = null ) {

		if ( $is_new_installation === null ) {
			$is_new_installation = $this->is_new_installation();
		}

		return $is_new_installation
			? Styling::install_defaults()
			: false;
	}

	/**
	 * Store site-local email defaults when a network-active plugin gains a site.
	 *
	 * @since 3.14.0
	 *
	 * @param WP_Site $new_site New site object.
	 * @param array   $args     Site initialization arguments.
	 */
	public function install_new_site_defaults( $new_site, $args = [] ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The public callback must accept the two-argument core hook signature.

		if (
			! ( $new_site instanceof WP_Site ) ||
			! $this->is_plugin_network_active( (int) $new_site->network_id )
		) {
			return;
		}

		switch_to_blog( (int) $new_site->blog_id );

		try {
			$this->install_current_site_defaults();
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Hand every other site of a network to the background queue.
	 *
	 * Returns as soon as the durable state exists and one action has been
	 * attempted. Nothing here enumerates or switches to a secondary site: a
	 * network activation request must not walk the network.
	 *
	 * @since 3.14.0
	 *
	 * @param int $activation_site_id Site classified synchronously by the caller.
	 * @param int $network_id         Network being activated.
	 *
	 * @return bool Whether an action is outstanding for the run.
	 */
	public function start_network_defaults( $activation_site_id, $network_id ) {

		if ( ! is_multisite() ) {
			return false;
		}

		$network_id = (int) $network_id;

		if ( $network_id < 1 ) {
			return false;
		}

		$progress = [
			'version'            => 1,
			'run_id'             => wp_generate_uuid4(),
			'cursor'             => 0,
			'activation_site_id' => (int) $activation_site_id,
			'scheduler_site_id'  => get_current_blog_id(),
			'failed_site_id'     => 0,
			'failure_count'      => 0,
			'updated_at'         => time(),
		];

		// Store progress before the queue is touched, so a failed enqueue is
		// recoverable rather than a silently dropped rollout.
		if ( ! add_network_option( $network_id, self::NETWORK_PROGRESS_OPTION, $progress ) ) {

			// A run already owns this network. Resume it exactly as stored:
			// resetting any field here would replay sites or lose a cursor.
			$progress = $this->get_network_progress( $network_id );

			if ( $progress === false ) {
				return false;
			}
		}

		return $this->queue_network_batch( $network_id, $progress['run_id'], (int) $progress['cursor'] );
	}

	/**
	 * Classify one page of a network's sites and queue the next page.
	 *
	 * @since 3.14.0
	 *
	 * @param int    $network_id      Network being processed.
	 * @param string $run_id          Rollout the action belongs to.
	 * @param int    $expected_cursor Cursor the action was queued for.
	 */
	public function process_network_defaults_batch( $network_id, $run_id, $expected_cursor ) {

		if ( ! is_multisite() ) {
			return;
		}

		$network_id      = (int) $network_id;
		$run_id          = (string) $run_id;
		$expected_cursor = (int) $expected_cursor;
		$progress        = $this->get_network_progress( $network_id );

		if (
			$progress === false ||
			$progress['run_id'] !== $run_id ||
			(int) $progress['cursor'] !== $expected_cursor ||
			get_current_blog_id() !== (int) $progress['scheduler_site_id'] ||
			! $this->is_plugin_network_active( $network_id )
		) {
			// A stale, duplicate, misrouted, or no-longer-network-active action
			// must change nothing at all.
			return;
		}

		$site_ids = $this->get_network_site_ids( $network_id, $expected_cursor );

		if ( $site_ids === null ) {

			// A failed enumeration is not an empty completion page.
			$this->record_network_failure( $network_id, $progress, 0 );
			$this->schedule_network_retry( $network_id, $run_id, $expected_cursor );

			return;
		}

		if ( $site_ids === [] ) {
			delete_network_option( $network_id, self::NETWORK_PROGRESS_OPTION );

			return;
		}

		$this->classify_network_sites( $network_id, $progress, $site_ids );
	}

	/**
	 * Re-queue an unfinished rollout on an ordinary request.
	 *
	 * Runs on `action_scheduler_init` so a lost action — an activation request
	 * without a usable queue, a suppressed enqueue, a process that died between
	 * a checkpoint and its continuation — heals itself.
	 *
	 * @since 3.14.0
	 */
	public function recover_network_defaults() {

		if ( ! is_multisite() ) {
			return;
		}

		$network_id = get_current_network_id();
		$progress   = $this->get_network_progress( $network_id );

		if (
			$progress === false ||
			get_current_blog_id() !== (int) $progress['scheduler_site_id'] ||
			! $this->is_plugin_network_active( $network_id )
		) {
			return;
		}

		$this->queue_network_batch( $network_id, $progress['run_id'], (int) $progress['cursor'] );
	}

	/**
	 * Classify one enumerated page, checkpointing after every safe outcome.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $network_id Network being processed.
	 * @param array $progress   Current rollout state, updated in place.
	 * @param int[] $site_ids   Enumerated site IDs, ascending.
	 */
	private function classify_network_sites( $network_id, array &$progress, array $site_ids ) {

		$run_id  = (string) $progress['run_id'];
		$started = time();

		foreach ( $site_ids as $site_id ) {

			$site_id = (int) $site_id;
			$outcome = $this->classify_network_site( $network_id, $progress, $site_id );

			if ( $outcome === false ) {
				$this->schedule_network_retry( $network_id, $run_id, (int) $progress['cursor'] );

				return;
			}

			if ( ! $this->checkpoint_network_cursor( $network_id, $progress, $site_id ) ) {

				// Revisiting an already classified site is idempotent, so the
				// unchanged cursor is what a retry must resume from.
				$this->schedule_network_retry( $network_id, $run_id, (int) $progress['cursor'] );

				return;
			}

			// A site out of attempts keeps Plain Text, which is the safe
			// outcome. End the page so the run continues past it.
			if ( $outcome === null || ( time() - $started ) >= self::BATCH_TIME_LIMIT ) {
				break;
			}
		}

		$this->queue_network_batch( $network_id, $run_id, (int) $progress['cursor'] );
	}

	/**
	 * Classify one enumerated site.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $network_id Network being processed.
	 * @param array $progress   Current rollout state, updated in place.
	 * @param int   $site_id    Site ID.
	 *
	 * @return bool|null True on a safe outcome, false when the same cursor must
	 *                   be retried, null when the site ran out of attempts.
	 */
	private function classify_network_site( $network_id, array &$progress, $site_id ) {

		// The activation site was classified in the activation request, and a
		// site deleted after enumeration has nothing left to classify. Both are
		// safe checkpoints.
		if ( $site_id === (int) $progress['activation_site_id'] || get_site( $site_id ) === null ) {
			return true;
		}

		try {
			$this->install_switched_site_defaults( $site_id );

			return true;
		} catch ( Throwable $exception ) {
			$failures = $this->record_network_failure( $network_id, $progress, $site_id );
		}

		return $failures < self::MAX_SITE_FAILURES
			? false
			: null;
	}

	/**
	 * Classify one site with the caller's blog restored either way.
	 *
	 * @since 3.14.0
	 *
	 * @param int $site_id Site ID.
	 */
	private function install_switched_site_defaults( $site_id ) {

		switch_to_blog( (int) $site_id );

		try {
			$this->install_current_site_defaults();
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Read the next page of a network's site IDs with a keyset query.
	 *
	 * Keyset rather than offset pagination: a site deleted mid-rollout shifts
	 * an offset and silently skips its neighbour.
	 *
	 * @since 3.14.0
	 *
	 * @param int $network_id Network ID.
	 * @param int $cursor     Highest site ID already handled.
	 *
	 * @return int[]|null Null when the query itself failed.
	 */
	private function get_network_site_ids( $network_id, $cursor ) {

		global $wpdb;

		$batch_size       = $this->get_network_batch_size( $network_id );
		$wpdb->last_error = '';

		// `$wpdb->blogs.site_id` is the WordPress network ID.
		$site_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A resumable keyset page over the network table, which has no cached API equivalent.
			$wpdb->prepare(
				"SELECT blog_id FROM {$wpdb->blogs}
				 WHERE site_id = %d AND blog_id > %d
				 ORDER BY blog_id ASC LIMIT %d",
				(int) $network_id,
				(int) $cursor,
				$batch_size
			)
		);

		if ( ! empty( $wpdb->last_error ) || ! is_array( $site_ids ) ) {
			return null;
		}

		return array_map( 'intval', $site_ids );
	}

	/**
	 * How many sites one background batch reads.
	 *
	 * @since 3.14.0
	 *
	 * @param int $network_id Network ID.
	 *
	 * @return int
	 */
	private function get_network_batch_size( $network_id ) {

		/**
		 * Filters how many sites one background email-defaults batch reads.
		 *
		 * Values are clamped between 1 and 500. A non-numeric return is
		 * ignored in favour of the default.
		 *
		 * @since 3.14.0
		 *
		 * @param int $batch_size Sites per batch.
		 * @param int $network_id Network being processed.
		 */
		$filtered = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Established public filter name; it describes the network rollout rather than the class that reads it.
			'sugar_calendar_network_email_defaults_batch_size',
			self::DEFAULT_BATCH_SIZE,
			(int) $network_id
		);

		return is_numeric( $filtered )
			? min( self::MAX_BATCH_SIZE, max( 1, (int) $filtered ) )
			: self::DEFAULT_BATCH_SIZE;
	}

	/**
	 * Persist a site as handled and clear the failure fields.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $network_id Network ID.
	 * @param array $progress   Current rollout state, updated in place.
	 * @param int   $site_id    Site that was just handled.
	 *
	 * @return bool
	 */
	private function checkpoint_network_cursor( $network_id, array &$progress, $site_id ) {

		$checkpoint                   = $progress;
		$checkpoint['cursor']         = (int) $site_id;
		$checkpoint['failed_site_id'] = 0;
		$checkpoint['failure_count']  = 0;
		$checkpoint['updated_at']     = time();

		if ( ! $this->save_network_progress( $network_id, $checkpoint ) ) {
			return false;
		}

		$progress = $checkpoint;

		return true;
	}

	/**
	 * Count one failure against a site, or against enumeration when zero.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $network_id Network ID.
	 * @param array $progress   Current rollout state, updated in place.
	 * @param int   $site_id    Failing site, or 0 for the enumeration itself.
	 *
	 * @return int Failures recorded in a row for that subject.
	 */
	private function record_network_failure( $network_id, array &$progress, $site_id ) {

		$site_id  = (int) $site_id;
		$failures = (int) $progress['failed_site_id'] === $site_id
			? (int) $progress['failure_count'] + 1
			: 1;

		$progress['failed_site_id'] = $site_id;
		$progress['failure_count']  = $failures;
		$progress['updated_at']     = time();

		$this->save_network_progress( $network_id, $progress );

		return $failures;
	}

	/**
	 * Write rollout state for a network.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $network_id Network ID.
	 * @param array $progress   Rollout state.
	 *
	 * @return bool
	 */
	private function save_network_progress( $network_id, array $progress ) {

		if ( update_network_option( (int) $network_id, self::NETWORK_PROGRESS_OPTION, $progress ) ) {
			return true;
		}

		// An unchanged write reports false, which is not a failure.
		return get_network_option( (int) $network_id, self::NETWORK_PROGRESS_OPTION, false ) === $progress;
	}

	/**
	 * Read rollout state, but only when it is one this version can drive.
	 *
	 * @since 3.14.0
	 *
	 * @param int $network_id Network ID.
	 *
	 * @return array|false
	 */
	private function get_network_progress( $network_id ) {

		$progress = get_network_option( (int) $network_id, self::NETWORK_PROGRESS_OPTION, false );

		return $this->is_valid_network_progress( $progress )
			? $progress
			: false;
	}

	/**
	 * Whether stored rollout state is exactly the shape this version drives.
	 *
	 * Anything else — a newer version written by a later release, a truncated
	 * array, a wrong scalar type — is left untouched for inspection rather than
	 * repaired or replaced.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $progress Stored value.
	 *
	 * @return bool
	 */
	private function is_valid_network_progress( $progress ) {

		if ( ! is_array( $progress ) || array_diff( self::PROGRESS_KEYS, array_keys( $progress ) ) !== [] ) {
			return false;
		}

		if ( $progress['version'] !== 1 ) {
			return false;
		}

		if ( ! is_string( $progress['run_id'] ) || ! wp_is_uuid( $progress['run_id'], 4 ) ) {
			return false;
		}

		$counters = $this->are_progress_integers(
			$progress,
			[ 'cursor', 'failed_site_id', 'failure_count', 'updated_at' ],
			0
		);
		$site_ids = $this->are_progress_integers(
			$progress,
			[ 'activation_site_id', 'scheduler_site_id' ],
			1
		);

		return $counters && $site_ids;
	}

	/**
	 * Whether every named rollout field is an integer at or above a minimum.
	 *
	 * @since 3.14.0
	 *
	 * @param array    $progress Rollout state.
	 * @param string[] $keys     Fields to check.
	 * @param int      $minimum  Smallest accepted value.
	 *
	 * @return bool
	 */
	private function are_progress_integers( array $progress, array $keys, $minimum ) {

		foreach ( $keys as $key ) {
			if ( ! is_int( $progress[ $key ] ) || $progress[ $key ] < $minimum ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Queue the next page of a rollout.
	 *
	 * @since 3.14.0
	 *
	 * @param int    $network_id Network ID.
	 * @param string $run_id     Rollout identifier.
	 * @param int    $cursor     Cursor the queued action must resume from.
	 *
	 * @return bool Whether an action is outstanding for those arguments.
	 */
	private function queue_network_batch( $network_id, $run_id, $cursor ) {

		if ( ! $this->is_scheduler_usable() ) {
			return false;
		}

		$args = $this->get_network_batch_args( $network_id, $run_id, $cursor );

		// Action Scheduler's own uniqueness flag matches on hook and group
		// only, so it would suppress a genuine continuation whenever any batch
		// action of any network is pending or running — including the action
		// executing this code. Compare the arguments explicitly instead.
		if ( as_has_scheduled_action( self::NETWORK_BATCH_HOOK, $args, Tasks::GROUP ) ) {
			return true;
		}

		return ! empty( as_enqueue_async_action( self::NETWORK_BATCH_HOOK, $args, Tasks::GROUP ) );
	}

	/**
	 * Schedule a delayed retry of the same cursor.
	 *
	 * Deliberately unguarded: the action running right now carries these exact
	 * arguments until it returns, so any existence or uniqueness check would
	 * suppress the retry it is asking for.
	 *
	 * @since 3.14.0
	 *
	 * @param int    $network_id Network ID.
	 * @param string $run_id     Rollout identifier.
	 * @param int    $cursor     Cursor to resume from.
	 *
	 * @return bool
	 */
	private function schedule_network_retry( $network_id, $run_id, $cursor ) {

		if ( ! $this->is_scheduler_usable() ) {
			return false;
		}

		return ! empty(
			as_schedule_single_action(
				time() + self::RETRY_DELAY,
				self::NETWORK_BATCH_HOOK,
				$this->get_network_batch_args( $network_id, $run_id, $cursor ),
				Tasks::GROUP
			)
		);
	}

	/**
	 * The batch action arguments, in their established order.
	 *
	 * @since 3.14.0
	 *
	 * @param int    $network_id Network ID.
	 * @param string $run_id     Rollout identifier.
	 * @param int    $cursor     Cursor to resume from.
	 *
	 * @return array
	 */
	private function get_network_batch_args( $network_id, $run_id, $cursor ) {

		return [
			(int) $network_id,
			(string) $run_id,
			(int) $cursor,
		];
	}

	/**
	 * Whether Action Scheduler can accept work right now.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	private function is_scheduler_usable() {

		$has_api = function_exists( 'as_has_scheduled_action' ) &&
			function_exists( 'as_enqueue_async_action' ) &&
			function_exists( 'as_schedule_single_action' );

		return $has_api && class_exists( Tasks::class ) && Tasks::is_usable();
	}

	/**
	 * Whether this plugin is active for a target network.
	 *
	 * @since 3.14.0
	 *
	 * @param int $network_id Network ID.
	 *
	 * @return bool
	 */
	private function is_plugin_network_active( $network_id ) {

		$plugins = get_network_option( $network_id, 'active_sitewide_plugins', [] );

		return is_array( $plugins ) && array_key_exists( $this->plugin_base, $plugins );
	}

	/**
	 * Whether raw option storage holds current or legacy installation evidence.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	private function has_existing_installation_option() {

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( self::EVIDENCE_OPTIONS ), '%s' ) );
		$option_id    = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Activation requires raw row presence, including false values and options hidden by pre_option filters.
			$wpdb->prepare(
				"SELECT option_id FROM {$wpdb->options} WHERE option_name IN ({$placeholders}) LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Controlled table and placeholder list; all option names are prepared.
				self::EVIDENCE_OPTIONS
			)
		);

		return $option_id !== null || ! empty( $wpdb->last_error );
	}

	/**
	 * Whether the current site holds any event row, of any subtype.
	 *
	 * The site-local table can legitimately be absent on a site the plugin has
	 * never installed on, which proves emptiness rather than failure. Anything
	 * else that stops the answer from being definitive means existing.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	private function has_existing_event() {

		global $wpdb;

		$events = new Event_Query();
		$table  = $events->get_table_name();

		$existing_table = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A missing site-local event table proves that the untouched site has no event rows.
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
		);

		if ( ! empty( $wpdb->last_error ) ) {
			return true;
		}

		if ( $existing_table !== $table ) {
			return false;
		}

		// The public database API, not the event convenience getter, whose
		// subtype and ghost-occurrence defaults can hide real rows.
		$rows = $events->get_results( [ 'id' ], [], 1, null, ARRAY_A );

		if ( ! empty( $wpdb->last_error ) || ! is_array( $rows ) ) {
			return true;
		}

		return $rows !== [];
	}

	/**
	 * Whether raw taxonomy storage holds a calendar.
	 *
	 * Read directly because the taxonomy is not guaranteed to be registered
	 * while an activation hook runs.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	private function has_existing_calendar() {

		global $wpdb;

		$calendar_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Activation-only one-row check before taxonomy registration.
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s LIMIT 1",
				sugar_calendar_get_calendar_taxonomy_id()
			)
		);

		return $calendar_id !== null || ! empty( $wpdb->last_error );
	}
}
