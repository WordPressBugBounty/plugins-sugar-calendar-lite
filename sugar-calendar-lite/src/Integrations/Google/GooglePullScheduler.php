<?php

namespace Sugar_Calendar\Integrations\Google;

use ActionScheduler_Store;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Tasks\Tasks;
use Throwable;
use WP_Error;

/**
 * Queues and runs the background Google pulls, one page per run, one run per calendar at a time.
 *
 * A run lists one page of changes, applies its items and stores the next page token (queueing the
 * next run) or the sync token once the last page is done.
 *
 * @since 3.16.0
 */
class GooglePullScheduler {

	/**
	 * Action Scheduler hook.
	 *
	 * @since 3.16.0
	 *
	 * @var string
	 */
	const ACTION = 'sugar_calendar_google_pull';

	/**
	 * Actions client.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleActionsClient
	 */
	private $client;

	/**
	 * Applies one pulled item.
	 *
	 * @since 3.16.0
	 *
	 * @var GooglePullApplier
	 */
	private $applier;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleActionsClient $client  Actions client.
	 * @param GooglePullApplier   $applier Applies one pulled item.
	 */
	public function __construct( GoogleActionsClient $client, GooglePullApplier $applier ) {

		$this->client  = $client;
		$this->applier = $applier;
	}

	/**
	 * Register the Action Scheduler callback.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( self::ACTION, [ $this, 'handle' ], 10, 1 );
	}

	/**
	 * Queue one pull for a calendar, unless one is already pending.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return void
	 */
	public static function enqueue( string $calendar_id ) {

		if ( ! function_exists( 'as_enqueue_async_action' ) || ! Tasks::is_usable() ) {
			return;
		}

		// Pending only: a running pull listed its page before this ping, so it must not swallow it.
		$pending = as_get_scheduled_actions(
			[
				'hook'     => self::ACTION,
				'args'     => [ $calendar_id ],
				'group'    => Tasks::GROUP,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 1,
			],
			'ids'
		);

		if ( ! empty( $pending ) ) {
			return;
		}

		as_enqueue_async_action( self::ACTION, [ $calendar_id ], Tasks::GROUP );
	}

	/**
	 * One page of changes.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return void
	 */
	public function handle( $calendar_id ) {

		$calendar_id = (string) $calendar_id;

		if ( ! $this->is_enabled_for( $calendar_id ) ) {
			return;
		}

		$row = OAuthConnectionModel::find_active_by_provider( 'google' );

		if ( $row === null ) {
			return;
		}

		$this->pull_locked( $row, $calendar_id );
	}

	/**
	 * Pull one page under a per-calendar lock.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $row         Active connection row.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return void
	 */
	private function pull_locked( array $row, string $calendar_id ) {

		$lock = 'sugar_calendar_google_pull_lock_' . md5( $calendar_id );

		// A ping that lands mid-pull is deferred, never dropped.
		if ( get_transient( $lock ) ) {
			as_schedule_single_action( time() + 30, self::ACTION, [ $calendar_id ], Tasks::GROUP );

			return;
		}

		set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );

		try {
			$this->pull_page( new GoogleOAuthConnection( $row ), $calendar_id );
		} catch ( Throwable $e ) {
			// Swallow: a failed pull must never crash the background action.
			unset( $e );
		} finally {
			delete_transient( $lock );
		}
	}

	/**
	 * Fetch and apply one page, then store the next page or sync token.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleOAuthConnection $connection  Active connection.
	 * @param string                $calendar_id Google calendar id.
	 *
	 * @return void
	 */
	private function pull_page( GoogleOAuthConnection $connection, string $calendar_id ) {

		$state      = array_merge(
			[
				'sync_token' => '',
				'page_token' => '',
			],
			GoogleWatchManager::get_state()[ $calendar_id ] ?? []
		);
		$sync_token = (string) $state['sync_token'];
		$result     = $this->client->list_changes( $connection, $calendar_id, $sync_token, (string) $state['page_token'] );

		if ( is_wp_error( $result ) ) {
			$this->handle_list_error( $result, $calendar_id );

			return;
		}

		$this->apply_items( (array) ( $result['items'] ?? [] ), $calendar_id, $sync_token === '' );
		$this->store_page_tokens( $result, $calendar_id, $sync_token );
	}

	/**
	 * A dead sync token starts over from a full sync; anything else waits for the next Google ping.
	 *
	 * @since 3.16.0
	 *
	 * @param WP_Error $error       List error.
	 * @param string   $calendar_id Google calendar id.
	 *
	 * @return void
	 */
	private function handle_list_error( WP_Error $error, string $calendar_id ) {

		// Out of credits included: the next ping retries; no re-enqueue loop.
		if ( $error->get_error_code() !== 'full_sync_required' ) {
			return;
		}

		GoogleWatchManager::update_state(
			$calendar_id,
			[
				'sync_token' => '',
				'page_token' => '',
			]
		);
		self::enqueue( $calendar_id );
	}

	/**
	 * Apply a page of Google items, series before their moved or cancelled dates.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $items       Google event resources.
	 * @param string $calendar_id Google calendar id.
	 * @param bool   $full_sync   Whether this page belongs to the first full sync.
	 *
	 * @return void
	 */
	private function apply_items( array $items, string $calendar_id, bool $full_sync ) {

		$instances = array_filter(
			$items,
			function ( $item ) {
				return ! empty( ( (array) $item )['recurringEventId'] );
			}
		);

		// A moved or cancelled date needs its series first, and Google lists them in no set order.
		foreach ( array_merge( array_diff_key( $items, $instances ), $instances ) as $item ) {
			try {
				$this->applier->apply( (array) $item, $calendar_id, $full_sync );
			} catch ( Throwable $e ) {
				// Swallow: one bad item must not stop the rest of the page.
				unset( $e );
			}
		}
	}

	/**
	 * Store the next page token and queue it, or the sync token once the last page is done.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $result      Google list body.
	 * @param string $calendar_id Google calendar id.
	 * @param string $sync_token  Sync token the page was fetched with.
	 *
	 * @return void
	 */
	private function store_page_tokens( array $result, string $calendar_id, string $sync_token ) {

		if ( ! empty( $result['nextPageToken'] ) ) {
			GoogleWatchManager::update_state( $calendar_id, [ 'page_token' => (string) $result['nextPageToken'] ] );
			self::enqueue( $calendar_id );

			return;
		}

		GoogleWatchManager::update_state(
			$calendar_id,
			[
				'sync_token' => (string) ( $result['nextSyncToken'] ?? $sync_token ),
				'page_token' => '',
			]
		);
	}

	/**
	 * Whether this calendar is mapped for pulling, read fresh from the database.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return bool
	 */
	private function is_enabled_for( string $calendar_id ): bool {

		return in_array( $calendar_id, GoogleSyncMapper::pull_calendars(), true );
	}
}
