<?php
/**
 * The sc-events/get-event-stats ability.
 *
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Sugar_Calendar\Integrations\Abilities\AbstractAbility;
use WP_Error;
use WP_Term;

/**
 * Aggregate event counts: total, by calendar, and by month within a date range.
 * Requires `edit_posts`.
 *
 * @since 3.12.0
 */
class GetEventStats extends AbstractAbility {

	/**
	 * Soft cap on the number of events scanned in PHP.
	 *
	 * @since 3.12.0
	 *
	 * @var int
	 */
	private const STATS_EVENT_SCAN_CAP = 1000;

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'get-event-stats';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'Get Event Stats', 'sugar-calendar-lite' );
	}

	/**
	 * Human-readable ability description.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return esc_html__(
			'Aggregate event counts: total, by calendar, and by month within a date range.',
			'sugar-calendar-lite'
		);
	}

	/**
	 * Input JSON-schema for this ability.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'start_after'  => [ 'type' => 'string', 'format' => 'date-time' ],
				'start_before' => [ 'type' => 'string', 'format' => 'date-time' ],
				'calendar_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
			],
		];
	}

	/**
	 * Output JSON-schema for this ability.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {

		return [ 'type' => 'object' ];
	}

	/**
	 * Capability check for this ability.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission( $input = null ) {

		return $this->require_cap(
			'edit_posts',
			esc_html__( 'You do not have permission to view events.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Execute the aggregation and return event statistics.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {

		$args = $this->normalize_input( $input );

		// Apply defaults: current month start through 1 year out (UTC).
		if ( empty( $args['start_after'] ) ) {
			$args['start_after'] = ( new DateTimeImmutable( 'first day of this month 00:00:00', new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d\TH:i:s\Z' );
		}

		if ( empty( $args['start_before'] ) ) {
			try {
				$start_after_dt = new DateTimeImmutable( (string) $args['start_after'], new DateTimeZone( 'UTC' ) );
			} catch ( Exception $e ) {
				return new WP_Error(
					'sc_events_invalid_input',
					esc_html__( 'Invalid start_after datetime.', 'sugar-calendar-lite' ),
					[ 'status' => 400 ]
				);
			}

			$args['start_before'] = $start_after_dt->modify( '+1 year' )->format( 'Y-m-d\TH:i:s\Z' );
		}

		$query_args = $this->events->build_query_args(
			[
				'status'       => 'publish',
				'start_after'  => $args['start_after'],
				'start_before' => $args['start_before'],
			]
		);

		if ( is_wp_error( $query_args ) ) {
			return $query_args;
		}

		if ( ! empty( $args['calendar_id'] ) ) {
			$post_ids = $this->events->resolve_calendar_post_ids( absint( $args['calendar_id'] ) );

			if ( empty( $post_ids ) ) {
				return $this->empty_stats( $args );
			}

			$query_args['object_id__in'] = $post_ids;
		}

		// Cap PHP-side aggregation work.
		$query_args['number']  = self::STATS_EVENT_SCAN_CAP;
		$query_args['orderby'] = 'start';
		$query_args['order']   = 'ASC';

		$query  = $this->events->get_event_query( $query_args );
		$events = $query->items ?? [];
		$total  = isset( $query->found_items ) ? $query->found_items : $this->events->count( $query_args );

		// Build by_month: group by ISO YYYY-MM of UTC start.
		$by_month_counts = [];

		foreach ( $events as $event ) {
			$iso = $this->formatter->iso8601( (string) $event->start, (string) $event->start_tz );

			if ( '' === $iso ) {
				continue;
			}

			$key                     = substr( $iso, 0, 7 );
			$by_month_counts[ $key ] = ( $by_month_counts[ $key ] ?? 0 ) + 1;
		}

		ksort( $by_month_counts );

		$by_month = [];

		foreach ( $by_month_counts as $year_month => $count ) {
			$by_month[] = [
				'year_month' => $year_month,
				'count'      => $count,
			];
		}

		// Build by_calendar: one batched calendar lookup, count per term.
		$post_ids = array_filter(
			array_map(
				static function ( $event ) {
					return (int) $event->object_id;
				},
				$events
			)
		);

		$calendar_map = $this->events->resolve_calendar_map( array_values( array_unique( $post_ids ) ) );
		$cal_counts   = [];
		$cal_meta     = [];

		foreach ( $calendar_map as $terms ) {
			foreach ( $terms as $term ) {
				if ( ! ( $term instanceof WP_Term ) ) {
					continue;
				}

				$tid                = (int) $term->term_id;
				$cal_counts[ $tid ] = ( $cal_counts[ $tid ] ?? 0 ) + 1;
				$cal_meta[ $tid ]   = [
					'calendar_id'   => $tid,
					'calendar_slug' => (string) $term->slug,
					'calendar_name' => (string) $term->name,
				];
			}
		}

		arsort( $cal_counts );

		$by_calendar = [];

		foreach ( $cal_counts as $tid => $count ) {
			$by_calendar[] = array_merge( $cal_meta[ $tid ], [ 'count' => $count ] );
		}

		return [
			'total'       => $total,
			'scanned'     => count( $events ),
			'truncated'   => count( $events ) >= self::STATS_EVENT_SCAN_CAP,
			'by_calendar' => $by_calendar,
			'by_month'    => $by_month,
			'range'       => [
				'start_after'  => (string) $args['start_after'],
				'start_before' => (string) $args['start_before'],
			],
		];
	}

	/**
	 * Empty-result stats shape (used when a calendar filter matches no events).
	 *
	 * @since 3.12.0
	 *
	 * @param array $args Normalized args (for the range echo).
	 *
	 * @return array
	 */
	private function empty_stats( array $args ): array {

		return [
			'total'       => 0,
			'scanned'     => 0,
			'truncated'   => false,
			'by_calendar' => [],
			'by_month'    => [],
			'range'       => [
				'start_after'  => (string) $args['start_after'],
				'start_before' => (string) $args['start_before'],
			],
		];
	}
}
