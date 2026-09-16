<?php
/**
 * Flat table of registration responses for the export tool.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Features\RegistrationForm\Integrations\Export;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Features\RegistrationForm\SchemaRepository;

/**
 * ResponsesTable — fixed columns plus one column per form-field label.
 *
 * Column headers are labels, not field ids, because the file is read by
 * people. The same label in two events shares a column; the same label twice
 * inside one event, or a label matching a fixed column, gets a " (n)" suffix so
 * the header never repeats. Answers whose field id has left the schema are
 * skipped (master design §4).
 *
 * @since 3.14.0
 */
class ResponsesTable {

	/**
	 * Columns every row carries, in order.
	 *
	 * Deliberately excludes `token` (the after-checkout write credential) and
	 * `reminder_sent_at` (internal dedupe).
	 *
	 * @since 3.14.0
	 *
	 * @var string[]
	 */
	const FIXED_COLUMNS = [
		'response_id',
		'event_id',
		'event_title',
		'context',
		'context_id',
		'attendee_key',
		'attendee_id',
		'name',
		'email',
		'status',
		'created_at',
		'updated_at',
	];

	/**
	 * Separator for multi-value (checkbox) answers. Matches the admin panels.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const MULTI_VALUE_JOIN = ', ';

	/**
	 * Hydrated response rows.
	 *
	 * @since 3.14.0
	 *
	 * @var array[]
	 */
	private $rows;

	/**
	 * Answer column labels in header order.
	 *
	 * @since 3.14.0
	 *
	 * @var string[]
	 */
	private $columns = [];

	/**
	 * Per event: field id => answer column label.
	 *
	 * @since 3.14.0
	 *
	 * @var array<int, array<string, string>>
	 */
	private $field_columns = [];

	/**
	 * Event id => title.
	 *
	 * @since 3.14.0
	 *
	 * @var array<int, string>
	 */
	private $titles = [];

	/**
	 * Response id => [ name, email ].
	 *
	 * @since 3.14.0
	 *
	 * @var array<int, array{name: string, email: string}>
	 */
	private $identities = [];

	/**
	 * Constructor.
	 *
	 * @since 3.14.0
	 *
	 * @param array[] $rows Hydrated response rows (ResponseRepository shape).
	 */
	public function __construct( array $rows ) {

		$this->rows = array_values( $rows );

		if ( empty( $this->rows ) ) {
			return;
		}

		$event_ids = array_values( array_unique( array_map( 'intval', array_column( $this->rows, 'event_id' ) ) ) );

		$this->titles     = $this->load_titles( $event_ids );
		$this->identities = RespondentIdentity::resolve( $this->rows );

		$this->build_columns( $event_ids );
	}

	/**
	 * Whether there is anything to export.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function has_rows() {

		return ! empty( $this->rows );
	}

	/**
	 * The full header: fixed columns then answer labels.
	 *
	 * @since 3.14.0
	 *
	 * @return string[]
	 */
	public function header() {

		return array_merge( self::FIXED_COLUMNS, $this->columns );
	}

	/**
	 * Rows for the CSV, keyed exactly by header().
	 *
	 * @since 3.14.0
	 *
	 * @return array[]
	 */
	public function csv_rows() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$out = [];

		foreach ( $this->rows as $row ) {
			$record = $this->fixed_cells( $row, $row['attendee_id'] === null ? '' : (int) $row['attendee_id'] );

			foreach ( $this->columns as $column ) {
				$record[ $column ] = '';
			}

			$map = $this->field_columns[ (int) $row['event_id'] ] ?? [];

			foreach ( (array) $row['answers'] as $field_id => $value ) {
				// No column means the field left the schema: an orphaned answer.
				if ( ! isset( $map[ $field_id ] ) ) {
					continue;
				}

				$record[ $map[ $field_id ] ] = $this->cell( $value );
			}

			$out[] = $record;
		}

		return $out;
	}

	/**
	 * Rows for the JSON section: fixed fields plus the raw answers map.
	 *
	 * @since 3.14.0
	 *
	 * @return array[]
	 */
	public function json_rows() {

		$out = [];

		foreach ( $this->rows as $row ) {
			$record = $this->fixed_cells( $row, $row['attendee_id'] === null ? null : (int) $row['attendee_id'] );

			$record['answers'] = (array) $row['answers'];

			$out[] = $record;
		}

		return $out;
	}

	/**
	 * The fixed columns of one row, in FIXED_COLUMNS order.
	 *
	 * `attendee_id` has no single correct empty value (CSV wants '', JSON
	 * wants null), so it is a required parameter rather than a placeholder a
	 * caller might forget to overwrite — a missing argument fails loudly
	 * instead of silently exporting null.
	 *
	 * @since 3.14.0
	 *
	 * @param array           $row         Hydrated response row.
	 * @param int|string|null $attendee_id Format-specific empty value already resolved by the caller.
	 *
	 * @return array
	 */
	private function fixed_cells( array $row, $attendee_id ) {

		$identity = $this->identities[ (int) $row['id'] ] ?? [
			'name'  => '',
			'email' => '',
		];

		return [
			'response_id'  => (int) $row['id'],
			'event_id'     => (int) $row['event_id'],
			'event_title'  => $this->titles[ (int) $row['event_id'] ] ?? '',
			'context'      => (string) $row['context'],
			'context_id'   => (int) $row['context_id'],
			'attendee_key' => (string) ( $row['attendee_key'] ?? '' ),
			'attendee_id'  => $attendee_id,
			'name'         => $identity['name'],
			'email'        => $identity['email'],
			'status'       => (string) $row['status'],
			'created_at'   => (string) $row['created_at'],
			'updated_at'   => (string) $row['updated_at'],
		];
	}

	/**
	 * One answer as a cell string.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $value Stored answer.
	 *
	 * @return string
	 */
	private function cell( $value ) {

		if ( is_array( $value ) ) {
			return implode( self::MULTI_VALUE_JOIN, array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Build the answer columns from the schemas of the events present.
	 *
	 * Events in row order, fields in schema order; first appearance fixes a
	 * column's position.
	 *
	 * @since 3.14.0
	 *
	 * @param int[] $event_ids Distinct event ids, in row order.
	 */
	private function build_columns( array $event_ids ) {

		// One meta query for all events instead of one per SchemaRepository::get().
		update_eventmeta_cache( $event_ids );

		$fixed = array_fill_keys( self::FIXED_COLUMNS, true );
		$known = [];

		foreach ( $event_ids as $event_id ) {
			$schema = SchemaRepository::get( $event_id );
			$fields = isset( $schema['fields'] ) && is_array( $schema['fields'] ) ? $schema['fields'] : [];

			$seen_in_event = [];

			foreach ( $fields as $field ) {
				$this->add_field_column( $event_id, $field, $seen_in_event, $known, $fixed );
			}
		}
	}

	/**
	 * Resolve one schema field's column and register it in every map that
	 * tracks it, skipping fields with no id or no label.
	 *
	 * `$known` is keyed by the folded (lowercased) column, not the original
	 * casing — otherwise "Meal preference" and "meal preference" register as
	 * two columns. On a hit, the stored display name is reused for
	 * `field_columns` too, or a row would be keyed by a column absent from
	 * header() and export empty (see class docblock).
	 *
	 * @since 3.14.0
	 *
	 * @param int   $event_id      Event the field belongs to.
	 * @param mixed $field         Schema field definition.
	 * @param array $seen_in_event Columns already claimed by this event's fields, by reference.
	 * @param array $known         Folded column => display name, across all events, by reference.
	 * @param array $fixed         Fixed column names.
	 */
	private function add_field_column( $event_id, $field, array &$seen_in_event, array &$known, array $fixed ) {

		$id    = isset( $field['id'] ) ? (string) $field['id'] : '';
		$label = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';

		if ( $id === '' || $label === '' ) {
			return;
		}

		$column = $this->unique_column( $label, $seen_in_event, $fixed );
		$folded = strtolower( $column );

		$seen_in_event[ $folded ] = true;

		if ( ! isset( $known[ $folded ] ) ) {
			$known[ $folded ] = $column;
			$this->columns[]  = $column;
		}

		$this->field_columns[ $event_id ][ $id ] = $known[ $folded ];
	}

	/**
	 * The label, suffixed with " (n)" until it clashes with nothing in this event or the fixed set.
	 *
	 * The membership test folds case so a label like "Email" or "Status" is
	 * recognised as clashing with the lowercase fixed columns and with a
	 * same-event sibling differing only by case; the returned candidate keeps
	 * the label's original casing for the header.
	 *
	 * @since 3.14.0
	 *
	 * @param string $label         Field label.
	 * @param array  $seen_in_event Columns already claimed by this event's fields (lowercased keys).
	 * @param array  $fixed         Fixed column names (already lowercase).
	 *
	 * @return string
	 */
	private function unique_column( $label, array $seen_in_event, array $fixed ) {

		$candidate = $label;
		$n         = 2;

		while ( isset( $seen_in_event[ strtolower( $candidate ) ] ) || isset( $fixed[ strtolower( $candidate ) ] ) ) {
			$candidate = $label . ' (' . $n . ')';

			++$n;
		}

		return $candidate;
	}

	/**
	 * Event titles in one query.
	 *
	 * Read directly rather than from the exporter's `events` section so the
	 * column is filled whether or not Events was selected.
	 *
	 * @since 3.14.0
	 *
	 * @param int[] $event_ids Event ids.
	 *
	 * @return array<int, string>
	 */
	private function load_titles( array $event_ids ) {

		global $wpdb;

		if ( empty( $event_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk export read.
			$wpdb->prepare(
				'SELECT id, title FROM ' . $wpdb->prefix . 'sc_events WHERE id IN (' . $placeholders . ')', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name from $wpdb->prefix; placeholders generated from a count and bound by prepare() below.
				$event_ids
			)
		);

		$titles = [];

		foreach ( (array) $rows as $row ) {
			$titles[ (int) $row->id ] = (string) $row->title;
		}

		return $titles;
	}
}
