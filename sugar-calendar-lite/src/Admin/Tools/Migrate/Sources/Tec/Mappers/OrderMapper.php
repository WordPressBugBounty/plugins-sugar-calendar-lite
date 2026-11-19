<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecOrder;

/**
 * Turns a TEC order into a Sugar Calendar order.
 *
 * @since 3.18.0
 */
class OrderMapper {

	/**
	 * Tickets Commerce order statuses mapped to the Sugar Calendar status they migrate as.
	 *
	 * A reversed (charged back) order is a refund. A status left out is an order that was
	 * never paid, such as an abandoned or failed checkout, and isn't migrated.
	 *
	 * @since 3.18.0
	 *
	 * @var string[]
	 */
	const STATUSES = [
		'completed'  => 'paid',
		'refunded'   => 'refunded',
		'reversed'   => 'refunded',
		'pending'    => 'pending',
		'approved'   => 'pending',
		'action-req' => 'pending',
	];

	/**
	 * The Sugar Calendar status a TEC order migrates as.
	 *
	 * @since 3.18.0
	 *
	 * @param TecOrder $order The TEC order.
	 *
	 * @return string Empty for an order that isn't migrated.
	 */
	public static function get_status( TecOrder $order ) {

		return self::STATUSES[ $order->get_status_slug() ] ?? '';
	}

	/**
	 * The Sugar Calendar order a TEC order becomes.
	 *
	 * @since 3.18.0
	 *
	 * @param TecOrder $order       The TEC order.
	 * @param string   $status      The Sugar Calendar order status.
	 * @param int      $sc_event_id The migrated event's ID, 0 when it was not migrated.
	 * @param string   $event_date  The TEC event's start.
	 *
	 * @return array
	 */
	public static function map( TecOrder $order, $status, $sc_event_id, $event_date ) {

		return [
			'transaction_id' => ! empty( $order->get_gateway_order_id() ) ? $order->get_gateway_order_id() : null,
			'currency'       => $order->get_currency(),
			'status'         => $status,
			'discount_id'    => '',
			'subtotal'       => $order->get_total(),
			'tax'            => '',
			'discount'       => '',
			'total'          => $order->get_total(),
			'event_id'       => empty( $sc_event_id ) ? 0 : $sc_event_id,
			'event_date'     => $event_date,
			'email'          => $order->get_email(),
			'first_name'     => $order->get_first_name(),
			'last_name'      => $order->get_last_name(),
			'date_paid'      => $order->get_purchase_time(),
		];
	}
}
