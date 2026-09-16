<?php

namespace Sugar_Calendar\Integrations\Elementor\LoopGrid;

use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Event as CoreEvent;

/**
 * Fetches upcoming events for a Loop Grid, deduped to one card per recurring
 * series.
 *
 * @since 3.14.0
 */
class UpcomingEvents {

	/**
	 * Over-fetch multiplier, so a dense series can't crowd out other events.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const OVERFETCH_MULTIPLIER = 3;

	/**
	 * Pages' worth of distinct posts the fetch window covers. Page-independent,
	 * so the pager's advertised total doesn't shift as the visitor pages deeper.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const MAX_FETCH_PAGE = 20;

	/**
	 * Absolute row ceiling. `posts_per_page` has no upper bound of its own.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const MAX_FETCH_ROWS = 500;

	/**
	 * Batches to fetch when one series dominates. The query has no per-series
	 * cap, so a dense series can fill a batch before other events are reached.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const MAX_FETCH_BATCHES = 3;

	/**
	 * Fetch upcoming events and dedupe to one entry per series.
	 *
	 * @since 3.14.0
	 *
	 * @param array $sce_args      Args for BaseHelpers::get_upcoming_events_list_with_recurring().
	 * @param array $sce_atts      Attributes for the same call.
	 * @param int   $target_number Cards the grid is configured to show per page.
	 *
	 * @return CoreEvent[] Deduped events, keyed by object_id.
	 */
	public static function fetch( $sce_args, $sce_atts, $target_number ) {

		$target_number = absint( $target_number );

		if ( $target_number < 1 ) {
			return [];
		}

		$window = self::resolve_fetch_window( $target_number, $sce_args );

		// The most distinct posts a pager capped at self::MAX_FETCH_PAGE pages
		// can ever display, and the real stopping point for collecting them.
		// Clamped: `posts_per_page` is admin-configurable with no upper bound,
		// and without the ceiling a large value grows `post__in` past what the
		// fetch could return (and overflows once the product exceeds PHP_INT_MAX).
		$distinct_needed = min( $target_number * self::MAX_FETCH_PAGE, self::MAX_FETCH_ROWS );

		return self::dedupe_by_object_id(
			self::collect_raw_events( $sce_args, $sce_atts, $window, $distinct_needed ),
			$distinct_needed
		);
	}

	/**
	 * Read raw occurrence rows in batches until enough distinct posts are seen.
	 *
	 * @since 3.14.0
	 *
	 * @param array $sce_args        Args for the fetch.
	 * @param array $sce_atts        Attributes for the fetch.
	 * @param int   $window          Raw rows to request per batch.
	 * @param int   $distinct_needed Distinct posts worth collecting.
	 *
	 * @return CoreEvent[] Raw rows, in date order, duplicates included.
	 */
	private static function collect_raw_events( $sce_args, $sce_atts, $window, $distinct_needed ) {

		$sce_args['number'] = $window;

		$offset     = absint( $sce_args['offset'] ?? 0 );
		$raw_events = [];
		$seen_ids   = [];

		for ( $batch = 0; $batch < self::MAX_FETCH_BATCHES; $batch++ ) {

			$batch_args           = $sce_args;
			$batch_args['offset'] = $offset;

			$batch_events = BaseHelpers::get_upcoming_events_list_with_recurring( $batch_args, $sce_atts );

			$raw_events = array_merge( $raw_events, $batch_events );

			foreach ( $batch_events as $event ) {
				$seen_ids[ (int) $event->object_id ] = true;
			}

			// A short read means the date-ordered result is exhausted, which
			// also covers an empty batch. The helper asks the DB for
			// `number + 1` rows, so a full read comes back one longer than the
			// window we set.
			if ( count( $batch_events ) <= $window ) {
				break;
			}

			if ( count( $seen_ids ) >= $distinct_needed ) {
				break;
			}

			$offset += count( $batch_events );
		}

		return $raw_events;
	}

	/**
	 * Size the raw-row window for one batch.
	 *
	 * A `number` already set by the
	 * `sugar_calendar_integrations_elementor_loop_grid_query_args` filter is
	 * honored, but still can't exceed the absolute ceiling.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $target_number Cards the grid shows per page.
	 * @param array $sce_args      Args, possibly carrying a filtered `number`.
	 *
	 * @return int
	 */
	private static function resolve_fetch_window( $target_number, $sce_args ) {

		if ( isset( $sce_args['number'] ) && is_numeric( $sce_args['number'] ) ) {
			// Floored at 1: a filtered 0 would leave the helper substituting its
			// own default row count, so the short-read check below could never
			// match it and every batch would run.
			return max( 1, min( absint( $sce_args['number'] ), self::MAX_FETCH_ROWS ) );
		}

		return min(
			$target_number * self::OVERFETCH_MULTIPLIER * self::MAX_FETCH_PAGE,
			self::MAX_FETCH_ROWS
		);
	}

	/**
	 * Dedupe events to one per object_id, keeping the earliest occurrence.
	 *
	 * @since 3.14.0
	 *
	 * @param CoreEvent[] $events Events, ordered ascending by upcoming date.
	 * @param int         $limit  Maximum unique posts to keep.
	 *
	 * @return CoreEvent[] Keyed by object_id.
	 */
	private static function dedupe_by_object_id( $events, $limit ) {

		$limit = absint( $limit );

		if ( $limit < 1 ) {
			return [];
		}

		$unique_events = [];

		foreach ( $events as $event ) {

			if ( ! isset( $unique_events[ $event->object_id ] ) ) {
				$unique_events[ $event->object_id ] = $event;
			}

			if ( count( $unique_events ) >= $limit ) {
				break;
			}
		}

		return $unique_events;
	}
}
