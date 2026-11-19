<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps;

use Sugar_Calendar\AddOn\Ticketing\Migrate\TicketingWriter;
use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;
use Sugar_Calendar\Admin\Tools\Migrate\IssueLog;
use Sugar_Calendar\Admin\Tools\Migrate\MigrationStep;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecContext;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecIdLookup;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecIdMap;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecItemReader;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecReader;

/**
 * A TEC step: what is left to migrate, and how one item migrates.
 *
 * @since 3.18.0
 */
abstract class TecStep implements MigrationStep {

	/**
	 * The TEC importer that does the work.
	 *
	 * @since 3.18.0
	 *
	 * @var TheEventCalendar
	 */
	protected $tec;

	/**
	 * Reads TEC's data.
	 *
	 * @since 3.18.0
	 *
	 * @var TecReader
	 */
	protected $reader;

	/**
	 * Reads one TEC item.
	 *
	 * @since 3.18.0
	 *
	 * @var TecItemReader
	 */
	protected $items;

	/**
	 * Records which TEC item became which Sugar Calendar item.
	 *
	 * @since 3.18.0
	 *
	 * @var TecIdMap
	 */
	protected $id_map;

	/**
	 * Reads which TEC item became which Sugar Calendar item.
	 *
	 * @since 3.18.0
	 *
	 * @var TecIdLookup
	 */
	protected $lookup;

	/**
	 * Lists what a migrated item could not bring along.
	 *
	 * @since 3.18.0
	 *
	 * @var IssueLog
	 */
	protected $issues;

	/**
	 * Writes tickets, orders and seats.
	 *
	 * @since 3.18.0
	 *
	 * @var TicketingWriter
	 */
	protected $ticketing;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param TecContext $context What the steps of one request share.
	 */
	public function __construct( TecContext $context ) {

		$this->tec       = $context->get_tec();
		$this->ticketing = $context->get_ticketing();
		$this->reader    = $context->get_reader();
		$this->items     = $context->get_items();
		$this->id_map    = $context->get_id_map();
		$this->lookup    = $context->get_lookup();
		$this->issues    = $context->get_issues();
	}

	/**
	 * The TEC items not migrated yet, or their count.
	 *
	 * @since 3.18.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	abstract protected function query( $count_only );

	/**
	 * The number of items not migrated yet.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function count_pending() {

		$result = $this->query( true );

		return empty( $result[0]->context_to_import_count ) ? 0 : absint( $result[0]->context_to_import_count );
	}

	/**
	 * The next items to migrate, empty when the step is done.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_batch() {

		return $this->query( false );
	}

	/**
	 * The source ID of one batch item.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item A batch item.
	 *
	 * @return int
	 */
	public function get_item_id( $item ) {

		return (int) $item->ID;
	}

	/**
	 * The step's own fields in a batch response, after the batch ran.
	 *
	 * @since 3.18.0
	 *
	 * @param array $response The batch response.
	 *
	 * @return array
	 */
	public function get_response( $response ) {

		return $response;
	}
}
