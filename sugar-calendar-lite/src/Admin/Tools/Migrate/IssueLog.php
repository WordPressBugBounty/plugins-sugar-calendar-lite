<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;

/**
 * The items a migration could not bring across, for the summary at the end.
 *
 * Kept in the importer's error store for now, so the summary reads the same as before.
 *
 * @since 3.18.0
 */
class IssueLog {

	/**
	 * The importer whose error store holds the issues.
	 *
	 * @since 3.18.0
	 *
	 * @var TheEventCalendar
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param TheEventCalendar $importer The importer whose error store holds the issues.
	 */
	public function __construct( TheEventCalendar $importer ) {

		$this->importer = $importer;
	}

	/**
	 * List an item that was not migrated.
	 *
	 * @since 3.18.0
	 *
	 * @param string $entity    The summary group.
	 * @param int    $source_id The item's ID in the source.
	 * @param string $name      The name the summary lists the item by.
	 */
	public function add( $entity, $source_id, $name ) {

		$this->importer->log_errors(
			$entity,
			[
				'id'           => $source_id,
				'context_name' => $name,
			]
		);
	}

	/**
	 * The listed items, by summary group.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_issues() {

		return $this->importer->get_errors();
	}

	/**
	 * The summary of the listed items, as the Migrate tab shows it.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function render_html() {

		return $this->importer->get_error_html_display();
	}

	/**
	 * Forget the listed items.
	 *
	 * @since 3.18.0
	 */
	public function clear() {

		delete_transient( $this->importer->get_errors_transient_key() );
	}
}
