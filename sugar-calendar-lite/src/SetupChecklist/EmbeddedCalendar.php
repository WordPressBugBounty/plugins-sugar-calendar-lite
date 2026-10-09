<?php

namespace Sugar_Calendar\SetupChecklist;

use WP_Post;
use WP_Query;

/**
 * Record the calendar block fact from saved page content.
 *
 * @since 3.15.0
 */
class EmbeddedCalendar {

	/**
	 * Exact calendar block name.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private const BLOCK_NAME = 'sugar-calendar/block';

	/**
	 * Page query flag.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private const QUERY_FLAG = 'sugar_calendar_checklist_page_scan';

	/**
	 * Page query cursor.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private const QUERY_CURSOR = 'sugar_calendar_checklist_page_cursor';

	/**
	 * Page query failure flag.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private const QUERY_FAILED = 'sugar_calendar_checklist_page_scan_failed';

	/**
	 * Number of pages inspected at once.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 100;

	/**
	 * Whether the current scan ended on a database error.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $discovery_failed = false;

	/**
	 * Per-site checklist state.
	 *
	 * @since 3.15.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param State $state Per-site checklist state.
	 */
	public function __construct( State $state ) {

		$this->state = $state;
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.15.0
	 */
	public function hooks() {

		add_action( 'wp_after_insert_post', [ $this, 'record_saved_page' ], 10, 2 );
	}

	/**
	 * Record the embed fact when a saved page contains the calendar block.
	 *
	 * Runs in every context: the block editor saves pages through REST, and a CLI or import
	 * save costs the same single block parse. Trash and delete change nothing; the fact is permanent.
	 *
	 * @since 3.15.0
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Saved post.
	 */
	public function record_saved_page( $post_id, $post ) {

		if ( ! $this->page_contains_calendar( $post ) || $this->state->has_history( Config::ITEM_EMBED_CALENDAR ) ) {
			return;
		}

		$this->state->record_history( Config::ITEM_EMBED_CALENDAR );
	}

	/**
	 * Scan saved pages once per site so embeds that predate the checklist count.
	 *
	 * Callers own the context guards. A database error records nothing, so the next call retries.
	 *
	 * @since 3.15.0
	 */
	public function bootstrap() {

		if ( $this->state->has_history( Config::ITEM_EMBED_CALENDAR ) || $this->state->has_embed_scan() ) {
			return;
		}

		$page_id = $this->discover_page_id();

		if ( $this->discovery_failed ) {
			return;
		}

		$recorded = $page_id > 0 ? $this->state->record_history( Config::ITEM_EMBED_CALENDAR ) : true;

		if ( is_wp_error( $recorded ) ) {
			return;
		}

		$this->state->record_embed_scan();
	}

	/**
	 * Recursively inspect parsed blocks for the exact calendar block.
	 *
	 * @since 3.15.0
	 *
	 * @param array $blocks Parsed blocks.
	 *
	 * @return bool
	 */
	public function contains_calendar( $blocks ) {

		foreach ( (array) $blocks as $block ) {

			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( isset( $block['blockName'] ) && $block['blockName'] === self::BLOCK_NAME ) {
				return true;
			}

			if ( ! empty( $block['innerBlocks'] ) && $this->contains_calendar( $block['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add the ascending ID cursor to checklist page scans only.
	 *
	 * @since 3.15.0
	 *
	 * @param string   $where Query WHERE clause.
	 * @param WP_Query $query Query object.
	 *
	 * @return string
	 */
	public function filter_page_cursor( $where, $query ) {

		if ( ! $query instanceof WP_Query || $query->get( self::QUERY_FLAG ) !== true ) {
			return $where;
		}

		global $wpdb;

		$cursor = max( 0, (int) $query->get( self::QUERY_CURSOR ) );

		return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $cursor );
	}

	/**
	 * Keep checklist batches from priming every scanned post in the object cache.
	 *
	 * @since 3.15.0
	 *
	 * @param bool     $split_the_query Whether WordPress should split the query.
	 * @param WP_Query $query           Query object.
	 *
	 * @return bool
	 */
	public function filter_split_the_query( $split_the_query, $query ) {

		if ( $query instanceof WP_Query && $query->get( self::QUERY_FLAG ) === true ) {
			return false;
		}

		return $split_the_query;
	}

	/**
	 * Preserve a checklist page-query error before later queries clear it.
	 *
	 * @since 3.15.0
	 *
	 * @param WP_Post[] $posts Query results.
	 * @param WP_Query  $query Query object.
	 *
	 * @return WP_Post[]
	 */
	public function capture_page_scan_error( $posts, $query ) {

		if ( ! $query instanceof WP_Query || $query->get( self::QUERY_FLAG ) !== true ) {
			return $posts;
		}

		global $wpdb;

		if ( $wpdb->last_error !== '' ) {
			$query->set( self::QUERY_FAILED, true );
		}

		return $posts;
	}

	/**
	 * Run exact discovery in bounded page batches.
	 *
	 * @since 3.15.0
	 *
	 * @return int Page ID, or zero when the scan is exhausted.
	 */
	private function discover_page_id() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- the scan filters live only for the loop; every other query on the request pays nothing.

		$this->discovery_failed = false;

		add_filter( 'posts_where', [ $this, 'filter_page_cursor' ], 10, 2 );
		add_filter( 'split_the_query', [ $this, 'filter_split_the_query' ], 10, 2 );
		add_filter( 'posts_results', [ $this, 'capture_page_scan_error' ], PHP_INT_MIN, 2 );

		try {
			return $this->scan_pages();
		} finally {
			remove_filter( 'posts_where', [ $this, 'filter_page_cursor' ], 10 );
			remove_filter( 'split_the_query', [ $this, 'filter_split_the_query' ], 10 );
			remove_filter( 'posts_results', [ $this, 'capture_page_scan_error' ], PHP_INT_MIN );
		}
	}

	/**
	 * Walk saved pages in ID order until one contains the calendar block.
	 *
	 * @since 3.15.0
	 *
	 * @return int Page ID, or zero when no qualifying page exists.
	 */
	private function scan_pages() {

		$cursor = 0;

		do {
			$query = new WP_Query(
				[
					'post_type'              => 'page',
					'post_status'            => Config::SAVED_POST_STATUSES,
					'posts_per_page'         => self::BATCH_SIZE,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'cache_results'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'suppress_filters'       => false,
					self::QUERY_FLAG         => true,
					self::QUERY_CURSOR       => $cursor,
				]
			);

			if ( $query->get( self::QUERY_FAILED ) === true ) {
				$this->discovery_failed = true;

				return 0;
			}

			$pages      = $query->posts;
			$page_count = count( $pages );

			foreach ( $pages as $page ) {
				$cursor = (int) $page->ID;

				if ( $this->page_contains_calendar( $page ) ) {
					return $cursor;
				}
			}
		} while ( $page_count === self::BATCH_SIZE );

		return 0;
	}

	/**
	 * Whether a post is a qualifying saved page with the calendar block.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $post Post value.
	 *
	 * @return bool
	 */
	private function page_contains_calendar( $post ) {

		return $post instanceof WP_Post &&
			$post->post_type === 'page' &&
			in_array( $post->post_status, Config::SAVED_POST_STATUSES, true ) &&
			$this->contains_calendar( parse_blocks( $post->post_content ) );
	}
}
