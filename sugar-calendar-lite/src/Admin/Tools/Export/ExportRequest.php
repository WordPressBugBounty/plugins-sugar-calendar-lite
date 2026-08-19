<?php

namespace Sugar_Calendar\Admin\Tools\Export;

use DateTime;

/**
 * One submitted export form, read and validated.
 *
 * Owns everything about the request: the guards, reading `$_POST`, and settling
 * the date range into a pair the exporters can use. `ToolsExportTab` renders the
 * form and hands control here; nothing below this class touches a superglobal.
 *
 * @since 3.13.0
 */
final class ExportRequest {

	/**
	 * Nonce action for the export form.
	 *
	 * The canonical definition. `ToolsExportTab::EXPORT_NONCE_ACTION` has been the
	 * public name for this since 3.3.0 and now points here, so the two cannot drift.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'sc_admin_tools_export_nonce';

	/**
	 * Export formats this tool accepts. The first is the default.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const FORMATS = [ 'json', 'csv' ];

	/**
	 * Selected data-type keys.
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	private $keys;

	/**
	 * Requested format slug.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	private $format;

	/**
	 * Range start bound, 'Y-m-d' or ''.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	private $date_start;

	/**
	 * Range end bound, 'Y-m-d' or ''.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	private $date_end;

	/**
	 * Constructor. Private: instances come from from_request().
	 *
	 * @since 3.13.0
	 *
	 * @param array  $keys       Selected data-type keys.
	 * @param string $format     Requested format slug.
	 * @param string $date_start Range start bound.
	 * @param string $date_end   Range end bound.
	 */
	private function __construct( array $keys, string $format, string $date_start, string $date_end ) {

		$this->keys       = $keys;
		$this->format     = $format;
		$this->date_start = $date_start;
		$this->date_end   = $date_end;
	}

	/**
	 * Whether this request is an export submission at all.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	public static function is_submitted(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST['sc_admin_tools_export'] );
	}

	/**
	 * Enforce the nonce and the capability, or die explaining which failed.
	 *
	 * @since 3.13.0
	 */
	public static function verify() {

		$nonce = isset( $_POST[ self::NONCE_ACTION ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_ACTION ] ) )
			: '';

		// wp_verify_nonce, not check_admin_referer: the latter dies inside itself,
		// so this message was unreachable and the user got WordPress's generic
		// expired-link page instead.
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die(
				esc_html__( 'Invalid request.', 'sugar-calendar-lite' ),
				esc_html__( 'Error', 'sugar-calendar-lite' ),
				[ 'response' => 403 ]
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to export data.', 'sugar-calendar-lite' ),
				esc_html__( 'Error', 'sugar-calendar-lite' ),
				[ 'response' => 403 ]
			);
		}
	}

	/**
	 * Read the submitted form into a request object.
	 *
	 * @since 3.13.0
	 *
	 * @return self|false The request, or false when nothing exportable was selected.
	 */
	public static function from_request() {

		// Nonce + capability are enforced by verify() before this runs.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if (
			empty( $_POST['sc_admin_tools_export_data'] ) ||
			! is_array( $_POST['sc_admin_tools_export_data'] )
		) {
			return false;
		}

		$keys = array_map( 'sanitize_key', (array) wp_unslash( $_POST['sc_admin_tools_export_data'] ) );

		// Custom Fields only adds columns to the events table, so without Events
		// there is nothing for it to attach to. Dropping it here means the pairing
		// holds however the form was submitted, and a lone Custom Fields leaves
		// nothing selected — the same inline notice as ticking nothing at all.
		if ( ! in_array( 'events', $keys, true ) ) {
			$keys = array_values( array_diff( $keys, [ 'custom_fields' ] ) );
		}

		if ( empty( $keys ) ) {
			return false;
		}

		$format = isset( $_POST['sc_admin_tools_export_format'] )
			? sanitize_key( wp_unslash( $_POST['sc_admin_tools_export_format'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		list( $date_start, $date_end ) = self::pair_dates(
			self::read_date( 'sc_admin_tools_export_date_start' ),
			self::read_date( 'sc_admin_tools_export_date_end' )
		);

		return new self(
			$keys,
			in_array( $format, self::FORMATS, true ) ? $format : self::FORMATS[0],
			$date_start,
			$date_end
		);
	}

	/**
	 * Selected data-type keys.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	public function keys(): array {

		return $this->keys;
	}

	/**
	 * Requested format slug.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public function format(): string {

		return $this->format;
	}

	/**
	 * Range start bound.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public function date_start(): string {

		return $this->date_start;
	}

	/**
	 * Range end bound.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public function date_end(): string {

		return $this->date_end;
	}

	/**
	 * The request as the args array ExporterService takes.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	public function to_args(): array {

		return [
			'keys'       => $this->keys,
			'format'     => $this->format,
			'date_start' => $this->date_start,
			'date_end'   => $this->date_end,
		];
	}

	/**
	 * Read one posted date bound, sanitized and validated as Y-m-d.
	 *
	 * @since 3.13.0
	 *
	 * @param string $field Posted field name.
	 *
	 * @return string Validated date, or '' when absent or malformed.
	 */
	private static function read_date( string $field ): string {

		// Nonce + capability are enforced by verify() before this runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

		return self::validate_date( $value );
	}

	/**
	 * Normalize the submitted date range: complete a half-filled one, order the pair.
	 *
	 * The range picker can submit one bound on its own: clicking a date and then
	 * dismissing the calendar leaves the visible field empty while the hidden
	 * start input keeps that date. Every consumer builds its two date clauses
	 * independently, so a lone start would filter `>= date` with no upper bound —
	 * an export of that day and everything after it, from a form showing no date
	 * selected. One bound therefore means that one day, which is also what
	 * picking a single date looks like it should do.
	 *
	 * The pair is then ordered earliest-first, so the range means the same period
	 * however the two dates arrive — a reversed pair would otherwise ask for
	 * "on or after the later date AND on or before the earlier one", which can
	 * never match and surfaces as a misleading "nothing to export".
	 *
	 * @since 3.13.0
	 *
	 * @param string $start Validated start bound ('' when absent).
	 * @param string $end   Validated end bound ('' when absent).
	 *
	 * @return array Tuple: [ $start, $end ].
	 */
	private static function pair_dates( string $start, string $end ): array {

		if ( $start !== '' && $end === '' ) {
			$end = $start;
		}

		if ( $end !== '' && $start === '' ) {
			$start = $end;
		}

		// Whichever order the two dates arrive in, the earlier one is the start.
		// Both are validated Y-m-d here, so a plain string compare orders them.
		if ( $start > $end ) {
			return [ $end, $start ];
		}

		return [ $start, $end ];
	}

	/**
	 * Validate a sanitized date string as Y-m-d (or return empty).
	 *
	 * @since 3.13.0
	 *
	 * @param string $value Sanitized date string.
	 *
	 * @return string
	 */
	private static function validate_date( string $value ): string {

		if ( $value === '' ) {
			return '';
		}

		$date = DateTime::createFromFormat( 'Y-m-d', $value );

		return ( $date && $date->format( 'Y-m-d' ) === $value ) ? $value : '';
	}
}
