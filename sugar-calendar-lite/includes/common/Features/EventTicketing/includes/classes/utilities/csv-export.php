<?php
/**
 * Export Class
 *
 * This is the base class for all export methods. Each data export type
 * (tickets, orders, attendees) extends this class.
 *
 * @since 1.0.0
 */
namespace Sugar_Calendar\AddOn\Ticketing\Export;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * CSV_Export Class
 *
 * @since 1.0.0
 */
class CSV_Export {

	/**
	 * Our export type. Used for export-type specific filters/actions.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $export_type = 'default';

	/**
	 * Capability needed to perform the current export.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $capability = 'manage_options';

	/**
	 * Can we export?
	 *
	 * @since  1.0.0
	 *
	 * @return bool Whether we can export or not
	 */
	public function can_export() {

		/**
		 * Filters the capability needed to perform an export.
		 *
		 * @since 1.0.0
		 *
		 * @param string $capability Capability needed to perform an export.
		 */
		return (bool) current_user_can( apply_filters( 'sc_et_export_capability', $this->capability ) );
	}

	/**
	 * Set the export headers
	 *
	 * @since 1.0.0
	 */
	public function headers() {
		ignore_user_abort( true );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=sc-et-export-' . $this->export_type . '-' . date( 'm-d-Y' ) . '.csv' );
		header( 'Expires: 0' );
	}

	/**
	 * Set the CSV columns
	 *
	 * @since 1.0.0
	 *
	 * @return array $cols All the columns
	 */
	public function csv_cols() {
		$cols = array(
			'id'   => esc_html__( 'ID',   'sugar-calendar-lite' ),
			'date' => esc_html__( 'Date', 'sugar-calendar-lite' )
		);
		return $cols;
	}

	/**
	 * Retrieve the CSV columns
	 *
	 * @since 1.0.0
	 * @return array $cols Array of the columns
	 */
	public function get_csv_cols() {
		$cols = $this->csv_cols();

		/**
		 * Filters the available CSV export columns for this export.
		 *
		 * This dynamic filter is appended with the export type string, for example:
		 *
		 *     `sc_etexport_csv_cols_tickets`
		 *
		 * @since 1.0
		 *
		 * @param $cols The export columns available.
		 */
		return apply_filters( 'sc_et_export_csv_cols_' . $this->export_type, $cols );
	}

	/**
	 * Output the CSV columns
	 *
	 * @since 1.0
	 */
	public function csv_cols_out() {
		$cols = $this->get_csv_cols();
		$i = 1;

		foreach ( $cols as $col_id => $column ) {
			echo '"' . $column . '"';
			echo $i === count( $cols )
				? ''
				: ',';
			$i++;
		}

		echo "\r\n";
	}

	/**
	 * Retrieves the data being exported.
	 *
	 * @since  1.0
	 *
	 * @return array $data Data for Export
	 */
	public function get_data( $args = array() ) {

		// Just a sample data array
		$args = array(
			0 => array(
				'id'   => '',
				'data' => date( 'F j, Y' )
			),
			1 => array(
				'id'   => '',
				'data' => date( 'F j, Y' )
			)
		);

		return $args;
	}

	/**
	 * Prepares a batch of data for export.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Export data.
	 *
	 * @return array Filtered export data.
	 */
	public function prepare_data( $data = array() ) {

		/**
		 * Filters the export data.
		 *
		 * The data set will differ depending on which exporter is currently in use.
		 *
		 * @since 1.0
		 *
		 * @param array $data Export data.
		 */
		$data = apply_filters( 'sc_et_export_get_data', $data );

		/**
		 * Filters the export data for a given export type.
		 *
		 * The dynamic portion of the hook name, `$this->export_type`, refers to the export type.
		 *
		 * @since 1.0
		 *
		 * @param array $data Export data.
		 */
		$data = apply_filters( 'sc_et_export_get_data_' . $this->export_type, $data );

		return $data;
	}

	/**
	 * Output the CSV rows
	 *
	 * @since 1.0.0
	 */
	public function csv_rows_out( $args = array() ) {

		$data = $this->prepare_data( $this->get_data( $args ) );

		$cols = $this->get_csv_cols();

		// Output each row
		foreach ( $data as $row ) {
			$i = 1;
			foreach ( $row as $col_id => $column ) {
				// Make sure the column is valid
				if ( array_key_exists( $col_id, $cols ) ) {
					echo '"' . $column . '"';
					echo $i === count( $cols ) + 1 ? '' : ',';
				}

				$i++;
			}
			echo "\r\n";
		}
	}

	/**
	 * Perform the export
	 *
	 * @since 1.0.0
	 */
	public function export( $args = array() ) {

		if ( ! $this->can_export() ) {
			wp_die( esc_html__( 'You do not have permission to export data.', 'sugar-calendar-lite' ), esc_html__( 'Error', 'sugar-calendar-lite' ), array( 'response' => 403 ) );
		}

		// Set headers
		$this->headers();

		// Output CSV columns (headers)
		$this->csv_cols_out();

		// Output CSV rows
		$this->csv_rows_out( $args );

		/**
		 * Fires at the end of an export.
		 *
		 * The dynamic portion of the hook name, `$this->export_type`, refers to
		 * the export type set by the extending sub-class.
		 *
		 * @since 1.9
		 * @since 1.9.2 Renamed to 'sc_et_export_type_end' to prevent a conflict with another
		 *              dynamic hook.
		 *
		 * @param CSV_Export $this CSV_Export instance.
		 */
		do_action( "sc_et_export_{$this->export_type}_end", $this );
		exit;
	}
}
