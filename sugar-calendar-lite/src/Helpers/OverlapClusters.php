<?php

namespace Sugar_Calendar\Helpers;

use DateInterval;
use DateTimeImmutable;

/**
 * Compute SCB-style overlap layout for intra-day events.
 *
 * Groups time-overlapping events into clusters and assigns each a column,
 * a per-cluster column count (N), and a nesting depth. Callers render those
 * into geometry (proportional columns + nesting indent) so concurrent events
 * divide the time cell instead of burying each other under a fixed indent.
 *
 * The algorithm is a faithful port of Sugar Calendar Bookings'
 * CalendarHelpers::get_appointment_clusters(): events that start in the same
 * hour take parallel columns, events that overlap but start in a different hour
 * share a column with increased nesting, and non-overlapping events reuse a
 * freed column.
 *
 * Timezone-agnostic: callers resolve event times into DateTimeImmutable in the
 * appropriate zone (admin → site, block → visitor) before calling.
 *
 * @since 3.12.0
 */
class OverlapClusters {

	/**
	 * Subgrid rounding interval, in minutes.
	 *
	 * Start/end times snap to this finer grid for overlap and nesting tests,
	 * mirroring SCB's 5-minute subgrid.
	 *
	 * @since 3.12.0
	 */
	const SUBGRID_MINUTES = 5;

	/**
	 * Assign overlap layout to a set of same-day events.
	 *
	 * @since 3.12.0
	 *
	 * @param array             $items      Each item: [
	 *                                          'ref'   => mixed,             // opaque, returned untouched
	 *                                          'start' => DateTimeImmutable, // event start
	 *                                          'end'   => DateTimeImmutable, // event end
	 *                                      ].
	 * @param DateTimeImmutable $slot_start Visible cell/day start (grid is clamped to this).
	 * @param DateTimeImmutable $slot_end   Visible cell/day end.
	 *
	 * @return array List of nodes, each: [
	 *                   'ref'     => mixed,
	 *                   'column'  => int, // 1-based column index within the cluster
	 *                   'columns' => int, // N: column count for this node's cluster
	 *                   'nesting' => int, // staircase depth within the column
	 *               ].
	 */
	public static function build( array $items, DateTimeImmutable $slot_start, DateTimeImmutable $slot_end ) {

		$nodes = self::build_nodes( $items, $slot_start, $slot_end );

		// Sort by start (finer grid), then by longer duration first.
		usort(
			$nodes,
			static function ( $a, $b ) {

				if ( $a['sub_start'] !== $b['sub_start'] ) {
					return $a['sub_start'] <=> $b['sub_start'];
				}

				return $b['duration'] <=> $a['duration'];
			}
		);

		$clusters = self::group_into_clusters( $nodes );

		$out = [];

		foreach ( $clusters as $cluster ) {

			$assigned = self::assign_columns( $cluster );

			foreach ( $assigned as $node ) {
				$out[] = [
					'ref'     => $node['ref'],
					'column'  => $node['column'],
					'columns' => $node['columns'],
					'nesting' => $node['nesting'],
				];
			}
		}

		return $out;
	}

	/**
	 * Build positioning nodes from raw items.
	 *
	 * @since 3.12.0
	 *
	 * @param array             $items      Items (see build()).
	 * @param DateTimeImmutable $slot_start Slot start.
	 * @param DateTimeImmutable $slot_end   Slot end.
	 *
	 * @return array Nodes with grid/subgrid timestamps and zeroed layout fields.
	 */
	private static function build_nodes( array $items, DateTimeImmutable $slot_start, DateTimeImmutable $slot_end ) {

		$slot_start_ts = $slot_start->getTimestamp();
		$slot_end_ts   = $slot_end->getTimestamp();

		$nodes = [];

		foreach ( $items as $item ) {

			$start = $item['start'];
			$end   = $item['end'];

			// Hour-snapped grid slot, clamped to the visible slot.
			$grid_start = max( self::floor_hour( $start )->getTimestamp(), $slot_start_ts );
			$grid_end   = min( self::ceil_hour( $end )->getTimestamp(), $slot_end_ts );

			// Finer subgrid slot, clamped inside the grid slot.
			$sub_start = max( self::round_minutes( $start, false )->getTimestamp(), $grid_start );
			$sub_end   = min( self::round_minutes( $end, true )->getTimestamp(), $grid_end );

			$duration = ( $sub_end - $sub_start ) / 60;

			// Drop zero/negative-duration nodes (nothing to lay out).
			if ( $duration <= 0 ) {
				continue;
			}

			$nodes[] = [
				'ref'        => $item['ref'],
				'grid_start' => $grid_start,
				'sub_start'  => $sub_start,
				'sub_end'    => $sub_end,
				'duration'   => $duration,
				'column'     => 1,
				'columns'    => 1,
				'nesting'    => 0,
			];
		}

		return $nodes;
	}

	/**
	 * Group start-sorted nodes into clusters of (transitively) overlapping nodes.
	 *
	 * Adjacency counts as overlap so back-to-back events that touch still share
	 * a cluster, matching SCB.
	 *
	 * @since 3.12.0
	 *
	 * @param array $nodes Start-sorted nodes.
	 *
	 * @return array List of clusters, each [ 'nodes' => array ].
	 */
	private static function group_into_clusters( array $nodes ) {

		$clusters      = [];
		$cluster_start = null;
		$cluster_end   = null;

		foreach ( $nodes as $node ) {

			if (
				$cluster_start === null
				|| ! self::overlaps( $node['sub_start'], $node['sub_end'], $cluster_start, $cluster_end, true )
			) {
				$cluster_start = $node['sub_start'];
				$cluster_end   = $node['sub_end'];

				$clusters[] = [ 'nodes' => [ $node ] ];
			} else {
				$cluster_start = min( $cluster_start, $node['sub_start'] );
				$cluster_end   = max( $cluster_end, $node['sub_end'] );

				$clusters[ count( $clusters ) - 1 ]['nodes'][] = $node;
			}
		}

		return $clusters;
	}

	/**
	 * Assign column, nesting, and the cluster column count to each node.
	 *
	 * @since 3.12.0
	 *
	 * @param array $cluster Cluster [ 'nodes' => array ].
	 *
	 * @return array Nodes with column/columns/nesting set.
	 */
	private static function assign_columns( array $cluster ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded

		$nodes    = $cluster['nodes'];
		$previous = null;
		$columns  = 1;

		foreach ( $nodes as $n => $node ) {

			if ( $previous !== null ) {

				// Default: inherit the previous node's column, no nesting.
				$node['column']  = $previous['column'];
				$node['nesting'] = 0;

				if ( $node['grid_start'] === $previous['grid_start'] ) {

					// Same start hour: a new parallel column.
					++$node['column'];
					++$columns;

				} elseif ( self::overlaps( $node['sub_start'], $node['sub_end'], $previous['sub_start'], $previous['sub_end'] ) ) {

					// Overlaps, different start hour: nest within the column.
					$node['nesting'] = $previous['nesting'] + 1;

				} else {

					// No overlap with the previous node: reuse an overlapping
					// node's column if any, else fall back to column 1.
					$found = false;
					$i     = $n - 1;

					while ( $i >= 0 && ! $found ) {

						$check = $nodes[ $i ];

						if ( self::overlaps( $node['sub_start'], $node['sub_end'], $check['sub_start'], $check['sub_end'] ) ) {
							$node['column']  = $check['column'];
							$node['nesting'] = $check['nesting'] + 1;
							$found           = true;
						}

						--$i;
					}

					if ( ! $found ) {
						$node['column']  = 1;
						$node['nesting'] = 0;
					}
				}

				$nodes[ $n ] = $node;
			}

			$previous = $node;
		}

		// Normalize columns so the minimum is 1.
		$min_column = 1;

		foreach ( $nodes as $node ) {
			if ( $node['column'] < $min_column ) {
				$min_column = $node['column'];
			}
		}

		if ( $min_column < 1 ) {
			$shift = 1 - $min_column;

			foreach ( $nodes as $n => $node ) {
				$nodes[ $n ]['column'] = $node['column'] + $shift;
			}
		}

		// Column count is the larger of the parallel-column tally and the
		// highest column index any node landed on.
		$max_column = 1;

		foreach ( $nodes as $node ) {
			if ( $node['column'] > $max_column ) {
				$max_column = $node['column'];
			}
		}

		$columns = max( $columns, $max_column );

		foreach ( $nodes as $n => $node ) {
			$nodes[ $n ]['columns'] = $columns;
		}

		return $nodes;
	}

	/**
	 * Whether two intervals overlap.
	 *
	 * @since 3.12.0
	 *
	 * @param int  $a_start  Interval A start timestamp.
	 * @param int  $a_end    Interval A end timestamp.
	 * @param int  $b_start  Interval B start timestamp.
	 * @param int  $b_end    Interval B end timestamp.
	 * @param bool $adjacent Treat touching intervals as overlapping.
	 *
	 * @return bool
	 */
	private static function overlaps( $a_start, $a_end, $b_start, $b_end, $adjacent = false ) {

		if ( $adjacent ) {
			return $a_start <= $b_end && $b_start <= $a_end;
		}

		return $a_start < $b_end && $b_start < $a_end;
	}

	/**
	 * Round a datetime down to the top of its hour (in the datetime's own zone).
	 *
	 * @since 3.12.0
	 *
	 * @param DateTimeImmutable $dt Datetime.
	 *
	 * @return DateTimeImmutable
	 */
	private static function floor_hour( DateTimeImmutable $dt ) {

		return $dt->setTime( (int) $dt->format( 'G' ), 0, 0 );
	}

	/**
	 * Round a datetime up to the next hour boundary, leaving on-hour times unchanged.
	 *
	 * @since 3.12.0
	 *
	 * @param DateTimeImmutable $dt Datetime.
	 *
	 * @return DateTimeImmutable
	 */
	private static function ceil_hour( DateTimeImmutable $dt ) {

		$floor = self::floor_hour( $dt );

		if ( $floor->getTimestamp() === $dt->getTimestamp() ) {
			return $floor;
		}

		return $floor->add( new DateInterval( 'PT1H' ) );
	}

	/**
	 * Round a datetime to the subgrid interval.
	 *
	 * @since 3.12.0
	 *
	 * @param DateTimeImmutable $dt   Datetime.
	 * @param bool              $ceil Round up when true, down when false.
	 *
	 * @return DateTimeImmutable
	 */
	private static function round_minutes( DateTimeImmutable $dt, $ceil ) {

		$step = self::SUBGRID_MINUTES * 60;
		$ts   = $dt->getTimestamp();

		$rounded = $ceil
			? (int) ( ceil( $ts / $step ) * $step )
			: (int) ( floor( $ts / $step ) * $step );

		return $dt->setTimestamp( $rounded );
	}

	/**
	 * Format a number for an inline CSS value, trimming trailing zeros.
	 *
	 * Shared by the admin and block renderers so the fractional column offsets
	 * (e.g. 0.3333) emit identically across surfaces.
	 *
	 * @since 3.12.0
	 *
	 * @param float $value Numeric value.
	 *
	 * @return string
	 */
	public static function format_number( $value ) {

		$formatted = rtrim( rtrim( sprintf( '%.4f', $value ), '0' ), '.' );

		return ( $formatted === '' ) ? '0' : $formatted;
	}
}
