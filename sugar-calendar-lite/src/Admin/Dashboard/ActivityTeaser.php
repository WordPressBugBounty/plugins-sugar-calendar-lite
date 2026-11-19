<?php

namespace Sugar_Calendar\Admin\Dashboard;

use DateTimeZone;

/**
 * Example data behind the Orders tab's Stripe popup.
 *
 * @since 3.18.0
 */
class ActivityTeaser {

	/**
	 * Example payments per day, from January 22, 2026.
	 *
	 * @since 3.18.0
	 */
	const PAYMENTS = [ 5, 62, 53, 64, 72, 30, 5 ];

	/**
	 * Example range data for the Orders metric switch.
	 *
	 * @since 3.18.0
	 */
	const METRICS = [
		'orders_metrics' => [
			'payments' => [
				'value' => 358,
				'delta' => 20.0,
			],
			'sales'    => [
				'value' => 26510.0,
				'delta' => 20.0,
			],
		],
	];

	/**
	 * Example orders: number, customer, event, total, status and hours ago.
	 *
	 * @since 3.18.0
	 */
	const ORDERS = [
		[ 1024, 'Alex Rivera', 'Music Festival', 180.0, 'paid', 4 ],
		[ 1023, 'Priya Patel', 'Wine Tasting', 100.0, 'failed', 13 ],
		[ 1022, 'Marco Díaz', 'Yoga in the Park', 30.0, 'refunded', 22 ],
		[ 1021, 'Sarah Chen', 'Pitch Night', 60.0, 'pending', 24 ],
		[ 1020, 'James O’Neill', 'Coding Camp', 100.0, 'paid', 120 ],
	];

	/**
	 * The teaser's graph points, metric switch and rows, as data for the Orders template.
	 *
	 * @since 3.18.0
	 *
	 * @param ActivityRows $rows The widget's rows, so the examples read like real orders.
	 *
	 * @return array `series`, `metrics` (for activity-orders-metrics) and `rows`.
	 */
	public function get_view( ActivityRows $rows ): array {

		return [
			'series'  => $this->get_series(),
			'metrics' => $rows->prepare_order_metrics( self::METRICS, 'payments' ),
			'rows'    => $rows->prepare_order_rows( $this->get_orders() ),
		];
	}

	/**
	 * Graph points, labelled like the real graph's.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	private function get_series(): array {

		$series = [];
		$utc    = new DateTimeZone( 'UTC' );

		foreach ( self::PAYMENTS as $index => $value ) {
			// In UTC so no site time zone shifts the day.
			$label    = wp_date( 'M j', gmmktime( 0, 0, 0, 1, 22 + $index, 2026 ), $utc );
			$series[] = [
				'label'    => $label,
				'tooltip'  => $label,
				'payments' => $value,
			];
		}

		return $series;
	}

	/**
	 * Order rows in the shape ActivityRows::prepare_order_rows() reads, each carrying its own event.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	private function get_orders(): array {

		$orders = [];

		foreach ( self::ORDERS as $order ) {
			$orders[] = [
				'id'        => $order[0],
				'name'      => $order[1],
				'event'     => $order[2],
				// Gives the example event a real event's link, as Figma draws it; the teaser is inert, so it is never followed.
				'event_url' => '#',
				'total'     => $order[3],
				'status'    => $order[4],
				'time'      => gmdate( 'Y-m-d H:i:s', time() - $order[5] * HOUR_IN_SECONDS ),
			];
		}

		return $orders;
	}
}
