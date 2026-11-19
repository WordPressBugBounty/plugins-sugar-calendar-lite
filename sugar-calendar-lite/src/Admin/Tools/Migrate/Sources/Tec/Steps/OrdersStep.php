<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecOrder;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers\OrderMapper;
use Sugar_Calendar\Admin\Tools\Migrate\StepResult;

/**
 * TEC orders.
 *
 * @since 3.18.0
 */
class OrdersStep extends TecStep {

	/**
	 * The kind of item, used as the progress key.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_entity() {

		return 'orders';
	}

	/**
	 * The number of orders one request migrates.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_batch_size() {

		/**
		 * Filter the number of TEC orders to import per iteration.
		 *
		 * @since 3.3.0
		 *
		 * @param int $limit The number of TEC orders to import per iteration.
		 */
		return apply_filters( 'sc_import_tec_orders_limit', 10 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Established public hook; renaming would break backward compatibility.
	}

	/**
	 * The TEC orders not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	protected function query( $count_only ) {

		return $this->reader->get_unmigrated_orders( $count_only, $count_only ? 0 : $this->get_batch_size() );
	}

	/**
	 * Copy one TEC order, or list it when it never got paid.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return StepResult
	 */
	public function migrate( $item ) {

		$tec_order_id = $item->ID;

		$order  = $this->items->get_order( $tec_order_id );
		$status = empty( $order ) ? '' : OrderMapper::get_status( $order );

		if ( $status === '' ) {
			return $this->list_not_migrated( $order );
		}

		return $this->add_order( $tec_order_id, $order, $status );
	}

	/**
	 * Write the Sugar Calendar order and record it, or list the order when the write fails.
	 *
	 * @since 3.18.0
	 *
	 * @param int      $tec_order_id The TEC order ID.
	 * @param TecOrder $order        The TEC order.
	 * @param string   $status       The Sugar Calendar order status.
	 *
	 * @return StepResult
	 */
	private function add_order( $tec_order_id, TecOrder $order, $status ) {

		[ $sc_event_id, $event_date ] = $this->get_event( $order->get_event_post_id() );

		$sc_order_id = $this->ticketing->add_order( OrderMapper::map( $order, $status, $sc_event_id, $event_date ) );

		if ( empty( $sc_order_id ) ) {
			return StepResult::not_migrated( 'orders', $order->get_first_name() . ' ' . $order->get_last_name() );
		}

		$this->id_map->save( 'orders', $tec_order_id, [ 'sc_order_id' => $sc_order_id ] );

		return StepResult::migrated();
	}

	/**
	 * List an order that is gone, or that never got paid.
	 *
	 * @since 3.18.0
	 *
	 * @param TecOrder|false $order The TEC order, false when it is gone.
	 *
	 * @return StepResult
	 */
	private function list_not_migrated( $order ) {

		$name = '';

		if ( ! empty( $order ) ) {
			/* translators: %s: purchaser name. */
			$name = sprintf( __( '%s (order never paid)', 'sugar-calendar-lite' ), trim( $order->get_first_name() . ' ' . $order->get_last_name() ) );
		}

		return StepResult::not_migrated( 'orders', $name );
	}

	/**
	 * The migrated event of an order and the TEC event's start.
	 *
	 * @since 3.18.0
	 *
	 * @param int|false $tec_event_post_id The TEC event post ID in the order.
	 *
	 * @return array The Sugar Calendar event ID, 0 when not migrated, and the start date.
	 */
	private function get_event( $tec_event_post_id ) {

		$tec_event = empty( $tec_event_post_id ) ? false : $this->items->get_event( $tec_event_post_id );

		if ( empty( $tec_event ) ) {
			return [ 0, '0000-00-00 00:00:00' ];
		}

		$sc_info = $this->lookup->find_event( 'tec_event_post_id', $tec_event_post_id );

		return [ empty( $sc_info ) ? 0 : $sc_info->sc_event_id, $tec_event->get_start_date() ];
	}
}
