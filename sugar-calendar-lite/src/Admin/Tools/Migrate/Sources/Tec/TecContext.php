<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec;

use Sugar_Calendar\AddOn\Ticketing\Migrate\TicketingWriter;
use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;
use Sugar_Calendar\Admin\Tools\Migrate\IssueLog;

/**
 * What every step of one TEC migration request shares.
 *
 * @since 3.18.0
 */
final class TecContext {

	/**
	 * The TEC importer that does the work.
	 *
	 * @since 3.18.0
	 *
	 * @var TheEventCalendar
	 */
	private $tec;

	/**
	 * Writes tickets, orders and seats.
	 *
	 * @since 3.18.0
	 *
	 * @var TicketingWriter
	 */
	private $ticketing;

	/**
	 * Reads TEC's data.
	 *
	 * @since 3.18.0
	 *
	 * @var TecReader
	 */
	private $reader;

	/**
	 * Reads one TEC item.
	 *
	 * @since 3.18.0
	 *
	 * @var TecItemReader
	 */
	private $items;

	/**
	 * Records which TEC item became which Sugar Calendar item.
	 *
	 * @since 3.18.0
	 *
	 * @var TecIdMap
	 */
	private $id_map;

	/**
	 * Reads which TEC item became which Sugar Calendar item.
	 *
	 * @since 3.18.0
	 *
	 * @var TecIdLookup
	 */
	private $lookup;

	/**
	 * Lists what a migrated item could not bring along.
	 *
	 * @since 3.18.0
	 *
	 * @var IssueLog
	 */
	private $issues;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param TheEventCalendar $tec       The TEC importer.
	 * @param TicketingWriter  $ticketing The ticketing writer.
	 * @param array            $parts     Optional `reader`, `items`, `id_map` and `issues` to use instead of the real ones, so a step can be given a fake.
	 */
	public function __construct( TheEventCalendar $tec, TicketingWriter $ticketing, array $parts = [] ) {

		$this->tec       = $tec;
		$this->ticketing = $ticketing;
		$this->reader    = $parts['reader'] ?? new TecReader();
		$this->items     = $parts['items'] ?? new TecItemReader();
		$this->id_map    = $parts['id_map'] ?? new TecIdMap();
		$this->lookup    = $parts['lookup'] ?? new TecIdLookup();
		$this->issues    = $parts['issues'] ?? new IssueLog( $tec );
	}

	/**
	 * The TEC importer.
	 *
	 * @since 3.18.0
	 *
	 * @return TheEventCalendar
	 */
	public function get_tec() {

		return $this->tec;
	}

	/**
	 * The ticketing writer.
	 *
	 * @since 3.18.0
	 *
	 * @return TicketingWriter
	 */
	public function get_ticketing() {

		return $this->ticketing;
	}

	/**
	 * The TEC reader.
	 *
	 * @since 3.18.0
	 *
	 * @return TecReader
	 */
	public function get_reader() {

		return $this->reader;
	}

	/**
	 * The TEC item reader.
	 *
	 * @since 3.18.0
	 *
	 * @return TecItemReader
	 */
	public function get_items() {

		return $this->items;
	}

	/**
	 * The ID map.
	 *
	 * @since 3.18.0
	 *
	 * @return TecIdMap
	 */
	public function get_id_map() {

		return $this->id_map;
	}

	/**
	 * The ID lookup.
	 *
	 * @since 3.18.0
	 *
	 * @return TecIdLookup
	 */
	public function get_lookup() {

		return $this->lookup;
	}

	/**
	 * The issue log.
	 *
	 * @since 3.18.0
	 *
	 * @return IssueLog
	 */
	public function get_issues() {

		return $this->issues;
	}
}
