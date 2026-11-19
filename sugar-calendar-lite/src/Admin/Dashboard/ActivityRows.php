<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers as TicketingHelpers;
use Sugar_Calendar\Event;
use Sugar_Calendar\Helpers\Money;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\display_price;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\order_status_label;

/**
 * The Activity tables' rows: each attendee or order with its links, its event and its date.
 *
 * @since 3.18.0
 */
class ActivityRows {

	/**
	 * DS Labels colour per order status.
	 *
	 * @since 3.18.0
	 */
	const STATUS_COLORS = [
		'paid'     => 'green',
		'pending'  => 'yellow',
		'refunded' => 'grey',
		'failed'   => 'red',
	];

	/**
	 * Sugar Calendar's date format, read once for every row.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	private $date_format;

	/**
	 * Sugar Calendar's time format, read once for every row.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	private $time_format;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 */
	public function __construct() {

		$this->date_format = sc_get_date_format();
		$this->time_format = sc_get_time_format();
	}

	/**
	 * Display rows for the Attendees table.
	 *
	 * @since 3.18.0
	 *
	 * @param array $attendees Rows from ActivityLists::get_latest_attendees().
	 *
	 * @return array
	 */
	public function prepare_attendee_rows( array $attendees ): array {

		$rows = [];

		foreach ( $attendees as $attendee ) {
			$rows[] = array_merge(
				[
					'name'  => $attendee['name'],
					'url'   => $this->get_attendee_url( $attendee ),
					'email' => $attendee['email'],
					'date'  => $this->format_date( $attendee['time'] ),
				],
				$this->get_event_cell( (int) $attendee['event_id'], (int) $attendee['occurrence_id'] )
			);
		}

		return $rows;
	}

	/**
	 * Display rows for the Orders table.
	 *
	 * @since 3.18.0
	 *
	 * @param array $orders Rows from ActivityLists::get_latest_orders(); the teaser's carry their own `event` and `event_url`.
	 *
	 * @return array
	 */
	public function prepare_order_rows( array $orders ): array {

		// Ticketing orders are never `failed`; only the Stripe teaser's example rows are.
		$labels = [
			'failed' => __( 'Failed', 'sugar-calendar-lite' ),
		];

		$rows = [];

		foreach ( $orders as $order ) {
			$status = isset( self::STATUS_COLORS[ $order['status'] ] ) ? $order['status'] : 'pending';
			$event  = $this->get_order_event_cell( $order );

			$rows[] = array_merge(
				[
					/* translators: %1$d - order number; %2$s - customer name. */
					'label'        => sprintf( __( '#%1$d – %2$s', 'sugar-calendar-lite' ), (int) $order['id'], $order['name'] ),
					'url'          => TicketingHelpers::get_order_admin_url( (int) $order['id'] ),
					'date'         => $this->format_date( $order['time'] ),
					// "Free" as the order screens say it; a paid total keeps the store's separators, which display_price() drops.
					'total'        => $order['total'] <= 0 ? display_price( 0 ) : Money::format( (float) $order['total'] ),
					'status_label' => $labels[ $status ] ?? order_status_label( $status ),
					'status_color' => self::STATUS_COLORS[ $status ],
				],
				$event
			);
		}

		return $rows;
	}

	/**
	 * The Orders metric switch, Total Payments and Total Sales, for activity-orders-metrics. The
	 * widget prepares it from the range data and the Stripe teaser from its example numbers.
	 *
	 * @since 3.18.0
	 *
	 * @param array  $data     Range data, or the teaser's, carrying `orders_metrics`.
	 * @param string $selected Selected metric: `payments` or `sales`.
	 *
	 * @return array `metrics` (id => `label`, formatted `value`, `delta`, `icon`) and `selected`.
	 */
	public function prepare_order_metrics( array $data, string $selected ): array {

		$metrics = $data['orders_metrics'] ?? [];

		return [
			'metrics'  => [
				'payments' => [
					'label' => __( 'Total Payments', 'sugar-calendar-lite' ),
					'value' => number_format_i18n( (int) ( $metrics['payments']['value'] ?? 0 ) ),
					'delta' => $metrics['payments']['delta'] ?? null,
					'icon'  => 'fa6-regular--credit-card',
				],
				'sales'    => [
					'label' => __( 'Total Sales', 'sugar-calendar-lite' ),
					'value' => Money::format_whole( (float) ( $metrics['sales']['value'] ?? 0 ) ),
					'delta' => $metrics['sales']['delta'] ?? null,
					'icon'  => 'fa6-regular--money-bill-1',
				],
			],
			'selected' => $selected,
		];
	}

	/**
	 * Where a latest-attendees row opens: its order.
	 *
	 * @since 3.18.0
	 *
	 * @param array $attendee Row from ActivityLists::get_latest_attendees().
	 *
	 * @return string
	 */
	protected function get_attendee_url( array $attendee ): string {

		return TicketingHelpers::get_order_admin_url( (int) ( $attendee['order_id'] ?? 0 ) );
	}

	/**
	 * A post's edit screen.
	 *
	 * @since 3.18.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string Empty for a post the user can't open in the editor.
	 */
	protected function get_edit_url( int $post_id ): string {

		return (string) get_edit_post_link( $post_id, 'raw' );
	}

	/**
	 * Where a row's event cell opens: the event's editor.
	 *
	 * @since 3.18.0
	 *
	 * @param Event $event         The row's event.
	 * @param int   $occurrence_id The date the row is for, 0 for none.
	 *
	 * @return string Empty for an event the user can't open in the editor.
	 */
	protected function get_event_edit_url( Event $event, int $occurrence_id ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The Pro subclass opens a recurring event's date.

		return $this->get_edit_url( (int) $event->object_id );
	}

	/**
	 * The event cell: its title ("(No title)" when empty, as the Events list says) and editor, or a dash
	 * for a deleted event, as the Orders list shows it.
	 *
	 * @since 3.18.0
	 *
	 * @param int $event_id      Sugar Calendar event ID.
	 * @param int $occurrence_id The date the row is for, 0 for none.
	 *
	 * @return array `event` and `event_url` (empty without an event).
	 */
	private function get_event_cell( int $event_id, int $occurrence_id ): array {

		$event = sugar_calendar_get_event( $event_id );

		if ( empty( $event ) || empty( $event->object_id ) ) {
			return [
				'event'     => '—',
				'event_url' => '',
			];
		}

		return [
			'event'     => (string) $event->title !== '' ? $event->title : __( '(No title)', 'sugar-calendar-lite' ),
			'event_url' => $this->get_event_edit_url( $event, $occurrence_id ),
		];
	}

	/**
	 * An order row's event cell; the teaser's example rows carry their own.
	 *
	 * @since 3.18.0
	 *
	 * @param array $order Row from ActivityLists::get_latest_orders(), or a teaser row.
	 *
	 * @return array `event` and `event_url`.
	 */
	private function get_order_event_cell( array $order ): array {

		if ( ! isset( $order['event'] ) ) {
			return $this->get_event_cell( (int) $order['event_id'], (int) $order['occurrence_id'] );
		}

		return [
			'event'     => $order['event'],
			'event_url' => $order['event_url'],
		];
	}

	/**
	 * Registration date in the site's time zone and Sugar Calendar's date and time formats.
	 *
	 * @since 3.18.0
	 *
	 * @param string $utc UTC `Y-m-d H:i:s`.
	 *
	 * @return string
	 */
	private function format_date( string $utc ): string {

		$timestamp = strtotime( $utc . ' UTC' );

		return sprintf(
			/* translators: %1$s - date; %2$s - time. */
			__( '%1$s at %2$s', 'sugar-calendar-lite' ),
			wp_date( $this->date_format, $timestamp ),
			wp_date( $this->time_format, $timestamp )
		);
	}
}
