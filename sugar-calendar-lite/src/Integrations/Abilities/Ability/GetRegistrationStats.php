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
use Sugar_Calendar\Integrations\Abilities\RegistrationScope;
use Sugar_Calendar\Integrations\Abilities\SignupModes;
use WP_Error;

/**
 * Registration and revenue totals for a window, site-wide or for chosen events, split by signup mode,
 * with the change against the preceding window of the same length.
 *
 * Requires `edit_others_events`: the per-event stats abilities gate on the event
 * object, and an aggregate has no object to check, so a site-wide read needs the
 * site-wide capability or it becomes a way around them. Calendar, tag, venue and speaker
 * filters are aggregates and need only this; `event_ids` also needs `edit_post` on each event.
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
	 * Filter resolution, built on first use.
	 *
	 * @since 3.15.0
	 *
	 * @var RegistrationScope|null
	 */
	private $scope;

	/**
	 * The filter resolver.
	 *
	 * @since 3.15.0
	 *
	 * @return RegistrationScope
	 */
	private function scope(): RegistrationScope {

		if ( $this->scope === null ) {
			$this->scope = new RegistrationScope( $this->events );
		}

		return $this->scope;
	}

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
			'Registration counts and revenue for a date range, split by signup mode, with the change against the preceding range of the same length. The range filters on when each registration was placed, not on when its event runs. Site-wide unless the id-list filters narrow it: several ids in one filter count events in any of them, each event once, and several filters narrow together. The scope in the result says what was counted. A percentage is null when the preceding range held nothing to compare against, which should be reported as having no comparable previous period rather than as no change.',
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
			'properties'           => array_merge(
				[
					'start'            => [ 'type' => 'string', 'format' => 'date-time' ],
					'end'              => [ 'type' => 'string', 'format' => 'date-time' ],
					'compare_previous' => [ 'type' => 'boolean', 'default' => true ],
				],
				$this->scope()->properties()
			),
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
			// only the members every response carries can be required.
			'required'   => [ 'window', 'scope', 'registrations', 'revenue' ],
			'properties' => [
				'window'        => $window,
				'scope'         => [
					'type'       => 'object',
					'required'   => [ 'site_wide', 'filters', 'events', 'uncounted_modes' ],
					'properties' => [
						'site_wide'       => [ 'type' => 'boolean' ],
						'filters'         => [
							'type'                 => 'object',
							'additionalProperties' => [
								'type'  => 'array',
								'items' => [
									'type'       => 'object',
									'properties' => [
										'id'   => [ 'type' => 'integer' ],
										'name' => [ 'type' => 'string' ],
									],
								],
							],
						],
						'events'          => [ 'type' => [ 'integer', 'null' ] ],
						'uncounted_modes' => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
					],
				],
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
			// An input with its own offset ignores $utc, and the providers compare UTC columns.
			$end   = ( new DateTimeImmutable( $end_input !== '' ? $end_input : 'now', $utc ) )->setTimezone( $utc );
			$start = ( new DateTimeImmutable( $start_input !== '' ? $start_input : self::DEFAULT_START, $utc ) )->setTimezone( $utc );
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

		$scope = $this->scope()->resolve( $args );

		if ( is_wp_error( $scope ) ) {
			return $scope;
		}

		$event_ids = $scope['event_ids'];

		$current = $this->totals( $start, $end, $event_ids );

		$out = [
			'window'        => $this->window( $start, $end ),
			'scope'         => [
				'site_wide'       => $event_ids === null,
				// An object even when empty, so a site-wide result reads `{}`, not `[]`.
				'filters'         => (object) $scope['filters'],
				'events'          => $event_ids === null ? null : count( $event_ids ),
				'uncounted_modes' => $current['uncounted'],
			],
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
		$previous       = $this->totals( $previous_start, $previous_end, $event_ids );

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
	 * @param DateTimeImmutable $start     Window start, inclusive.
	 * @param DateTimeImmutable $end       Window end, exclusive.
	 * @param int[]|null        $event_ids Events to count, or null for site-wide.
	 *
	 * @return array
	 */
	private function totals( DateTimeImmutable $start, DateTimeImmutable $end, ?array $event_ids ): array {

		$from = $start->format( 'Y-m-d H:i:s' );
		$to   = $end->format( 'Y-m-d H:i:s' );

		$stats = $event_ids === null
			? SignupModes::stats( $from, $to )
			: SignupModes::scoped_stats( $from, $to, $event_ids );

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
				// An object even when empty, so a fully-uncounted window reads `{}`, not `[]`.
				'by_mode' => (object) $count_by_mode,
			],
			'revenue'       => [
				'total'    => $has_revenue && $single_currency !== null ? round( $total_revenue, 2 ) : null,
				'currency' => $single_currency,
				'by_mode'  => (object) $revenue_by_mode,
			],
			// A declared mode that did not answer is left out, not read as zero.
			'uncounted'     => array_values( array_diff( array_keys( SignupModes::available() ), array_keys( $stats ) ) ),
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
