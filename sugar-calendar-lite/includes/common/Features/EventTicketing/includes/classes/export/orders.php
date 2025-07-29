<?php
/**
 * Orders Export Class
 *
 * This class handles exporting order data.
 *
 * @since 1.0.0
 */
namespace Sugar_Calendar\AddOn\Ticketing\Export;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Database;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions;

/**
 * Orders_Export Class.
 *
 * @since 1.0.0
 */
class Orders_Export extends CSV_Export {

	/**
	 * Our export type. Used for export-type specific filters/actions.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $export_type = 'orders';

	/**
	 * Set the CSV columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array All the columns.
	 */
	public function csv_cols() {

		// Setup column names.
		$retval = [

			// Order.
			'id'               => esc_html__( 'Order ID', 'sugar-calendar-lite' ),
			'transaction_id'   => esc_html__( 'Transaction ID', 'sugar-calendar-lite' ),
			'status'           => esc_html__( 'Status', 'sugar-calendar-lite' ),
			'currency'         => esc_html__( 'Currency', 'sugar-calendar-lite' ),
			'subtotal'         => esc_html__( 'Subtotal', 'sugar-calendar-lite' ),
			'discount'         => esc_html__( 'Discount', 'sugar-calendar-lite' ),
			'tax'              => esc_html__( 'Tax', 'sugar-calendar-lite' ),
			'total'            => esc_html__( 'Total', 'sugar-calendar-lite' ),

			// Customer.
			'first_name'       => esc_html__( 'First Name', 'sugar-calendar-lite' ),
			'last_name'        => esc_html__( 'Last Name', 'sugar-calendar-lite' ),
			'email'            => esc_html__( 'Email', 'sugar-calendar-lite' ),

			// Event.
			'event_id'         => esc_html__( 'Event ID', 'sugar-calendar-lite' ),
			'event_name'       => esc_html__( 'Event Name', 'sugar-calendar-lite' ),
			'event_start_date' => esc_html__( 'Event Start Date', 'sugar-calendar-lite' ),
			'event_start_time' => esc_html__( 'Event Start Time', 'sugar-calendar-lite' ),
			'event_end_date'   => esc_html__( 'Event End Date', 'sugar-calendar-lite' ),
			'event_end_time'   => esc_html__( 'Event End Time', 'sugar-calendar-lite' ),

			// Dates.
			'date_created'     => esc_html__( 'Order Date', 'sugar-calendar-lite' ),
			'date_paid'        => esc_html__( 'Date Paid', 'sugar-calendar-lite' ),
			'date_modified'    => esc_html__( 'Date Modified', 'sugar-calendar-lite' ),

			// Additional.
			'checkout_type'    => esc_html__( 'Checkout Type', 'sugar-calendar-lite' ),
			'ticket_count'     => esc_html__( 'Ticket Count', 'sugar-calendar-lite' ),
		];

		// Return.
		return $retval;
	}

	/**
	 * Retrieves the data being exported.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Array of query arguments.
	 * @return array Data for Export.
	 */
	public function get_data( $args = [] ) {

		// Query for Orders.
		$this->query = new Database\Order_Query( $args );

		// Bail if no Orders.
		if ( empty( $this->query->items ) ) {
			return [];
		}

		// Default return value.
		$retval = [];

		// Get formats early (outside of loop).
		$date_format = sc_get_date_format();
		$time_format = sc_get_time_format();

		// Loop through Orders.
		foreach ( $this->query->items as $key => $order ) {

			// Reset Event data.
			$event_name = $event_id = '';
			$event_start_date = $event_start_time = '';
			$event_end_date = $event_end_time = '';

			// Event for Order.
			if ( ! empty( $order->event_id ) ) {

				// Query for Event.
				$event = sugar_calendar_get_event( $order->event_id );

				// Format Event data.
				if ( ! empty( $event ) ) {
					$event_id         = $event->id;
					$event_name       = $event->title;
					$event_start_date = date_i18n( $date_format, strtotime( $event->start ) );
					$event_start_time = date_i18n( $time_format, strtotime( $event->start ) );
					$event_end_date   = date_i18n( $date_format, strtotime( $event->end ) );
					$event_end_time   = date_i18n( $time_format, strtotime( $event->end ) );
				}
			}

			// Get ticket count for this order.
			$ticket_count = max( 1, count( Functions\get_order_tickets( $order->id ) ) );

			// Create the row to export.
			$retval[ $key ] = [

				// Order.
				'id'               => $order->id,
				'transaction_id'   => $order->transaction_id,
				'status'           => $order->status,
				'currency'         => $order->currency,
				'subtotal'         => $order->subtotal,
				'discount'         => $order->discount,
				'tax'              => $order->tax,
				'total'            => $order->total,

				// Customer.
				'first_name'       => $order->first_name,
				'last_name'        => $order->last_name,
				'email'            => $order->email,

				// Event.
				'event_id'         => $event_id,
				'event_name'       => $event_name,
				'event_start_date' => $event_start_date,
				'event_start_time' => $event_start_time,
				'event_end_date'   => $event_end_date,
				'event_end_time'   => $event_end_time,

				// Dates.
				'date_created'     => date_i18n( $date_format . ' ' . $time_format, strtotime( $order->date_created ) ),
				'date_paid'        => ! empty( $order->date_paid ) ? date_i18n( $date_format . ' ' . $time_format, strtotime( $order->date_paid ) ) : '',
				'date_modified'    => date_i18n( $date_format . ' ' . $time_format, strtotime( $order->date_modified ) ),

				// Additional.
				'checkout_type'    => $order->checkout_type,
				'ticket_count'     => $ticket_count,
			];
		}

		// Return.
		return $retval;
	}
}
