<?php

namespace Sugar_Calendar\UsageTracking;

use Sugar_Calendar\Tasks\Task;
use Sugar_Calendar\Tasks\Tasks;

/**
 * One-time backfill of the calendar embed marker for content saved before it existed.
 *
 * Each run handles one batch by ID cursor and schedules the next, so the weekly
 * snapshot never scans post content.
 *
 * @since 3.17.0
 */
class CalendarEmbedBackfillTask extends Task {

	/**
	 * Action name for this task.
	 *
	 * @since 3.17.0
	 */
	const ACTION = 'sugar_calendar_backfill_calendar_embeds';

	/**
	 * Option holding the last processed post ID.
	 *
	 * @since 3.17.0
	 */
	const CURSOR_OPTION = 'sugar_calendar_calendar_embed_backfill_cursor';

	/**
	 * Option set once the whole site has been scanned.
	 *
	 * @since 3.17.0
	 */
	const DONE_OPTION = 'sugar_calendar_calendar_embed_backfill_done';

	/**
	 * Posts scanned per run.
	 *
	 * @since 3.17.0
	 */
	const BATCH_SIZE = 100;

	/**
	 * Class constructor.
	 *
	 * @since 3.17.0
	 */
	public function __construct() {

		parent::__construct( self::ACTION );
	}

	/**
	 * Register the action handler and schedule the first batch.
	 *
	 * @since 3.17.0
	 */
	public function init() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_action( self::ACTION, [ $this, 'process' ] );

		if ( get_option( self::DONE_OPTION ) || Tasks::is_scheduled( self::ACTION ) !== false ) {
			return;
		}

		$this->once( time() + MINUTE_IN_SECONDS )->unique()->register();
	}

	/**
	 * Scan one batch and schedule the next, or finish.
	 *
	 * @since 3.17.0
	 */
	public function process() {

		if ( get_option( self::DONE_OPTION ) ) {
			return;
		}

		$rows = $this->get_batch( (int) get_option( self::CURSOR_OPTION, 0 ) );

		if ( $rows === null ) {
			return;
		}

		$this->sync_batch( $rows );

		if ( count( $rows ) < self::BATCH_SIZE ) {
			update_option( self::DONE_OPTION, 1, false );
			delete_option( self::CURSOR_OPTION );

			return;
		}

		update_option( self::CURSOR_OPTION, (int) end( $rows )->ID, false );

		$this->once( time() + MINUTE_IN_SECONDS )->register();
	}

	/**
	 * Refresh the marker for every post of a batch.
	 *
	 * @since 3.17.0
	 *
	 * @param array $rows Post rows with ID and post_content.
	 */
	private function sync_batch( $rows ) {

		update_meta_cache( 'post', wp_list_pluck( $rows, 'ID' ) );

		$marker = new CalendarEmbedMarker();

		foreach ( $rows as $row ) {
			$marker->sync( (int) $row->ID, $row->post_content );
		}
	}

	/**
	 * Get the next posts after the cursor.
	 *
	 * @since 3.17.0
	 *
	 * @param int $cursor Last processed post ID.
	 *
	 * @return array|null Null when the query failed.
	 */
	private function get_batch( $cursor ) {

		global $wpdb;

		$types        = CalendarEmbedMarker::get_post_types();
		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$args         = array_merge( $types, [ $cursor, self::BATCH_SIZE ] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				WHERE post_type IN ( {$placeholders} )
				AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				AND ID > %d
				ORDER BY ID ASC
				LIMIT %d",
				$args
			)
		);
		// phpcs:enable

		if ( $wpdb->last_error !== '' || ! is_array( $rows ) ) {
			return null;
		}

		return $rows;
	}
}
