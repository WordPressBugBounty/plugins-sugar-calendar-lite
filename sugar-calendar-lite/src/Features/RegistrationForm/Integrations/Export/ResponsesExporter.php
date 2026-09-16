<?php
/**
 * Registration responses as a Tools → Export data type.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Features\RegistrationForm\Integrations\Export;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Admin\Tools\Export\CsvExporter;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;

/**
 * ResponsesExporter — contributes the `registration_responses` dataset by filter.
 *
 * Booted only on admin + Pro (Feature::hooks()), so Lite keeps the disabled
 * placeholder checkbox and no exporter ever learns the key. Extends nothing:
 * the export tool's contract for add-on data is its filters.
 *
 * @since 3.14.0
 */
class ResponsesExporter {

	/**
	 * Export key on Pro.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const KEY = 'registration_responses';

	/**
	 * Lite placeholder key rendered by ToolsExportTab (disabled + Pro badge).
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const PLACEHOLDER_KEY = '_registration_responses';

	/**
	 * The table built while filtering the shared data, reused by the CSV callback.
	 *
	 * Safe to reuse: base_export_data() runs (and fires the data filter) before
	 * build_parts() fires the tables filter, within one exporter run, so this
	 * is always set by the time the CSV callback asks for it.
	 *
	 * @since 3.14.0
	 *
	 * @var ResponsesTable|null
	 */
	private $table = null;

	/**
	 * Register the hooks.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_admin_pages_tools_export_tab_checkboxes', [ $this, 'swap_checkbox' ] );
		add_filter( 'sugar_calendar_admin_tools_exporter_export_data', [ $this, 'add_export_data' ], 10, 3 );
		add_filter( 'sugar_calendar_admin_tools_export_csv_tables', [ $this, 'add_csv_table' ], 10, 4 );
	}

	/**
	 * Replace the Lite placeholder with the real key, keeping its position.
	 *
	 * @since 3.14.0
	 *
	 * @param array $checkboxes Key => label.
	 *
	 * @return array
	 */
	public function swap_checkbox( $checkboxes ) {

		$out = [];

		foreach ( (array) $checkboxes as $key => $label ) {
			$out[ $key === self::PLACEHOLDER_KEY ? self::KEY : $key ] = $label;
		}

		return $out;
	}

	/**
	 * Add the JSON section and build the table the CSV callback reuses.
	 *
	 * @since 3.14.0
	 *
	 * @param array $export_data Export data.
	 * @param array $keys        Selected data-type keys.
	 * @param array $range       Active date range, or [].
	 *
	 * @return array
	 */
	public function add_export_data( $export_data, $keys, $range = [] ) {

		$export_data = (array) $export_data;
		$keys        = (array) $keys;

		if ( ! in_array( self::KEY, $keys, true ) ) {
			return $export_data;
		}

		$this->table = new ResponsesTable( $this->rows( $keys, $export_data, (array) $range ) );

		$export_data[ self::KEY ] = $this->table->json_rows();

		return $export_data;
	}

	/**
	 * Add the CSV table when there is something in it.
	 *
	 * @since 3.14.0
	 *
	 * @param array $parts Entity slug => CSV string.
	 * @param array $data  Collected export data.
	 * @param array $keys  Selected data-type keys.
	 * @param array $range Active date range, or [].
	 *
	 * @return array
	 */
	public function add_csv_table( $parts, $data, $keys, $range = [] ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Filter signature.

		if ( ! in_array( self::KEY, (array) $keys, true ) || $this->table === null || ! $this->table->has_rows() ) {
			return $parts;
		}

		$parts[ self::KEY ] = CsvExporter::records_csv( $this->table->header(), $this->table->csv_rows() );

		return $parts;
	}

	/**
	 * The rows in scope: follow the exported events when Events is selected, else own created_at.
	 *
	 * Same rule the CSV orders table applies, so a reader of one table can
	 * predict the other.
	 *
	 * @since 3.14.0
	 *
	 * @param array $keys        Selected data-type keys.
	 * @param array $export_data Export data (events already range-scoped).
	 * @param array $range       Active date range, or [].
	 *
	 * @return array[]
	 */
	private function rows( array $keys, array $export_data, array $range ) {

		if ( in_array( 'events', $keys, true ) ) {
			return ResponseRepository::find_for_export( $this->event_ids_from( $export_data ) );
		}

		return ResponseRepository::find_for_export(
			null,
			(string) ( $range['start_utc'] ?? '' ),
			(string) ( $range['end_utc'] ?? '' )
		);
	}

	/**
	 * Event ids present in the events export section, in row order.
	 *
	 * @since 3.14.0
	 *
	 * @param array $export_data Export data (events already range-scoped).
	 *
	 * @return int[]
	 */
	private function event_ids_from( array $export_data ) {

		$event_ids = [];

		foreach ( (array) ( $export_data['events'] ?? [] ) as $event ) {
			if ( isset( $event->id ) ) {
				$event_ids[] = (int) $event->id;
			}
		}

		return $event_ids;
	}
}
