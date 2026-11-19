<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\AddOn\Ticketing\Database\Order_Query;
use Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query;

/**
 * The ticketing tables the Dashboard's numbers and Activity lists read, and the one rule each for
 * what counts as a paid order and an attendee record in them.
 *
 * @since 3.18.0
 */
trait TicketingTables {

	/**
	 * The orders and tickets tables.
	 *
	 * @since 3.18.0
	 *
	 * @return array `[ orders, tickets ]`.
	 */
	protected function get_ticketing_tables(): array {

		return [ ( new Order_Query() )->get_table_name(), ( new Ticket_Query() )->get_table_name() ];
	}

	/**
	 * What counts as a paid order. The attendee rule, the order windows, the has-more probe and
	 * the order totals all apply it, so widening it reaches every read at once.
	 *
	 * @since 3.18.0
	 *
	 * @param string $alias Orders alias; empty when the orders table is the only one in the query.
	 *
	 * @return string A WHERE condition.
	 */
	protected function get_paid_order_rule( string $alias = '' ): string {

		return ( $alias === '' ? '' : "{$alias}." ) . "status = 'paid'";
	}

	/**
	 * The orders the checkout join can match: a WooCommerce ticket carries `WOO-<checkout_id>`,
	 * never an order id. A newest-orders window and its has-more probe both apply it, so a store
	 * full of WooCommerce orders neither empties the window nor sends it to the every-order read.
	 *
	 * @since 3.18.0
	 *
	 * @return string A WHERE condition on the orders table alone.
	 */
	protected function get_checkout_order_rule(): string {

		return "checkout_type <> 'woocommerce'";
	}

	/**
	 * What counts as an attendee record, on an orders alias `o` and a tickets alias `t`: an active
	 * ticket on a paid order. Every attendee count and list applies it, so the stat card, the
	 * graph and the Attendees tab agree.
	 *
	 * @since 3.18.0
	 *
	 * @return string A WHERE condition.
	 */
	protected function get_paid_attendee_rule(): string {

		return $this->get_paid_order_rule( 'o' ) . " AND t.status = 'active'";
	}

	/**
	 * Orders joined to their checkout tickets, as `o` and `t`.
	 *
	 * A WooCommerce ticket's `order_id` is `WOO-<checkout_id>`, which matches no order id, so
	 * WooCommerce rows stay out of this join.
	 *
	 * @since 3.18.0
	 *
	 * @param string $orders   Orders source: the table by default, or a derived table in parentheses.
	 * @param string $order_id The order id the tickets join on; `o.id` by default.
	 *
	 * @return string The FROM clause's tables.
	 */
	protected function get_checkout_tickets_from( string $orders = '', string $order_id = 'o.id' ): string {

		[ $table, $tickets ] = $this->get_ticketing_tables();

		$orders = $orders === '' ? $table : $orders;

		return "{$orders} AS o INNER JOIN {$tickets} AS t ON t.order_id = {$order_id}";
	}
}
