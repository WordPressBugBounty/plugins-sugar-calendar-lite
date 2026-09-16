<?php
/**
 * The sc-events/get-registration-stats ability.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Sugar_Calendar\Integrations\Abilities\AbstractAbility;
use Sugar_Calendar\Integrations\Abilities\SignupModes;
use WP_Error;

/**
 * Site-wide registration and revenue totals for a window, split by signup mode,
 * with the change against the preceding window of the same length.
 *
 * Requires `edit_others_events`: the per-event stats abilities gate on the event
 * object, and an aggregate has no object to check, so a site-wide read needs the
 * site-wide capability or it becomes a way around them.
 *
 * @since 3.14.0
 */
class GetRegistrationStats extends AbstractAbility {

	/**
	 * Default window length when the caller sends no bounds.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const DEFAULT_START = '-30 days';

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'get-registration-stats';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'Get registration statistics', 'sugar-calendar-lite' );
	}

	/**
	 * Ability description shown to callers.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return esc_html__(
			'Site-wide registration counts and revenue for a date range, split by signup mode, with the change against the preceding range of the same length. The range filters on when each registration was placed, not on when its event runs. A percentage is null when the preceding range held nothing to compare against, which should be reported as having no comparable previous period rather than as no change.',
			'sugar-calendar-lite'
		);
	}

	/**
	 * Input JSON-schema for this ability.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'start'            => [ 'type' => 'string', 'format' => 'date-time' ],
				'end'              => [ 'type' => 'string', 'format' => 'date-time' ],
				'compare_previous' => [ 'type' => 'boolean', 'default' => true ],
			],
		];
	}

	/**
	 * Output JSON-schema for this ability.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {

		$window = [
			'type'       => 'object',
			'required'   => [ 'start', 'end' ],
			'properties' => [
				'start' => [ 'type' => 'string' ],
				'end'   => [ 'type' => 'string' ],
			],
		];

		return [
			'type'       => 'object',
			// `previous` and `change` are omitted when compare_previous is false, so
			// only the three members every response carries can be required.
			'required'   => [ 'window', 'registrations', 'revenue' ],
			'properties' => [
				'window'        => $window,
				'registrations' => [
					'type'       => 'object',
					'required'   => [ 'total', 'by_mode' ],
					'properties' => [
						'total'   => [ 'type' => 'integer' ],
						'by_mode' => [
							'type'                 => 'object',
							'additionalProperties' => [ 'type' => 'integer' ],
						],
					],
				],
				'revenue'       => [
					'type'       => 'object',
					'required'   => [ 'total', 'currency', 'by_mode' ],
					'properties' => [
						'total'    => [ 'type' => [ 'number', 'null' ] ],
						'currency' => [ 'type' => [ 'string', 'null' ] ],
						'by_mode'  => [
							'type'                 => 'object',
							'additionalProperties' => [ 'type' => 'number' ],
						],
					],
				],
				'previous'      => [
					'type'       => 'object',
					'properties' => [
						'window'              => $window,
						'registrations_total' => [ 'type' => 'integer' ],
						'revenue_total'       => [ 'type' => [ 'number', 'null' ] ],
					],
				],
				'change'        => [
					'type'       => 'object',
					'properties' => [
						'registrations_pct' => [ 'type' => [ 'number', 'null' ] ],
						'revenue_pct'       => [ 'type' => [ 'number', 'null' ] ],
					],
				],
			],
		];
	}

	/**
	 * Capability check for this ability.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission( $input = null ) {

		return $this->require_cap(
			'edit_others_events',
			esc_html__( 'You do not have permission to view site-wide registration statistics.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Aggregate the window, and optionally the one before it.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {

		$args = $this->normalize_input( $input );
		$utc  = new DateTimeZone( 'UTC' );

		$start_input = trim( (string) ( $args['start'] ?? '' ) );
		$end_input   = trim( (string) ( $args['end'] ?? '' ) );

		try {
			$end   = new DateTimeImmutable( $end_input !== '' ? $end_input : 'now', $utc );
			$start = new DateTimeImmutable( $start_input !== '' ? $start_input : self::DEFAULT_START, $utc );
		} catch ( Exception $e ) {
			return new WP_Error(
				'sc_events_invalid_input',
				esc_html__( 'Could not read the date range.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		if ( $start >= $end ) {
			return new WP_Error(
				'sc_events_invalid_input',
				esc_html__( 'The start of the range must come before its end.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		$current = $this->totals( $start, $end );

		$out = [
			'window'        => $this->window( $start, $end ),
			'registrations' => $current['registrations'],
			'revenue'       => $current['revenue'],
		];

		// Absent means true: the schema default cannot be relied on to have been applied.
		$compare = ! isset( $args['compare_previous'] ) || ! empty( $args['compare_previous'] );

		if ( ! $compare ) {
			return $out;
		}

		$length         = $end->getTimestamp() - $start->getTimestamp();
		$previous_end   = $start;
		$previous_start = $start->modify( '-' . $length . ' seconds' );
		$previous       = $this->totals( $previous_start, $previous_end );

		$out['previous'] = [
			'window'              => $this->window( $previous_start, $previous_end ),
			'registrations_total' => $previous['registrations']['total'],
			'revenue_total'       => $previous['revenue']['total'],
		];

		$out['change'] = [
			'registrations_pct' => $this->change(
				(float) $current['registrations']['total'],
				(float) $previous['registrations']['total']
			),
			'revenue_pct'       => $this->revenue_change( $current['revenue']['total'], $previous['revenue']['total'] ),
		];

		return $out;
	}

	/**
	 * Collect and sum one window's per-mode contributions.
	 *
	 * @since 3.14.0
	 *
	 * @param DateTimeImmutable $start Window start, inclusive.
	 * @param DateTimeImmutable $end   Window end, exclusive.
	 *
	 * @return array
	 */
	private function totals( DateTimeImmutable $start, DateTimeImmutable $end ): array {

		$stats = SignupModes::stats(
			$start->format( 'Y-m-d H:i:s' ),
			$end->format( 'Y-m-d H:i:s' )
		);

		$count_by_mode   = [];
		$revenue_by_mode = [];
		$currencies      = [];
		$total_count     = 0;
		$total_revenue   = 0.0;
		$has_revenue     = false;

		foreach ( $stats as $slug => $entry ) {

			$count_by_mode[ $slug ] = $entry['count'];
			$total_count           += $entry['count'];

			// Skip rather than add 0: null means the mode has no revenue concept at all.
			if ( $entry['revenue'] === null ) {
				continue;
			}

			$revenue_by_mode[ $slug ] = $entry['revenue'];
			$total_revenue           += $entry['revenue'];
			$has_revenue              = true;

			if ( $entry['currency'] !== null ) {
				$currencies[ $entry['currency'] ] = true;
			}
		}

		// A blended total across two currencies would be a lie, so it is withheld
		// unless every contributing mode reported the same one.
		$single_currency = count( $currencies ) === 1 ? (string) key( $currencies ) : null;

		return [
			'registrations' => [
				'total'   => $total_count,
				'by_mode' => $count_by_mode,
			],
			'revenue'       => [
				'total'    => $has_revenue && $single_currency !== null ? round( $total_revenue, 2 ) : null,
				'currency' => $single_currency,
				'by_mode'  => $revenue_by_mode,
			],
		];
	}

	/**
	 * Render a window as ISO 8601 UTC bounds.
	 *
	 * @since 3.14.0
	 *
	 * @param DateTimeImmutable $start Window start.
	 * @param DateTimeImmutable $end   Window end.
	 *
	 * @return array
	 */
	private function window( DateTimeImmutable $start, DateTimeImmutable $end ): array {

		return [
			'start' => $start->format( 'Y-m-d\TH:i:s\Z' ),
			'end'   => $end->format( 'Y-m-d\TH:i:s\Z' ),
		];
	}

	/**
	 * Percentage change in revenue, or null when either side has no comparable total.
	 *
	 * A null total is withheld on purpose by totals() (no revenue-bearing mode, or two
	 * currencies that must not be blended), so it cannot be read as zero earnings here.
	 *
	 * @since 3.14.0
	 *
	 * @param float|null $current  Current window revenue total.
	 * @param float|null $previous Preceding window revenue total.
	 *
	 * @return float|null
	 */
	private function revenue_change( $current, $previous ) {

		if ( $current === null || $previous === null ) {
			return null;
		}

		return $this->change( (float) $current, (float) $previous );
	}

	/**
	 * Percentage change, or null when there is no baseline to divide by.
	 *
	 * Null rather than 0: reporting "no change" for growth from nothing is wrong,
	 * and dividing by zero would have the caller announce an infinite increase.
	 *
	 * @since 3.14.0
	 *
	 * @param float $current  Current window total.
	 * @param float $previous Preceding window total.
	 *
	 * @return float|null
	 */
	private function change( float $current, float $previous ) {

		if ( $previous <= 0.0 ) {
			return null;
		}

		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}
}
