<?php
/**
 * Programmatic create/update for Sugar Calendar events.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Events;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * EventWriter — owns the post row, the wp_sc_events row, event meta and term
 * assignment for an event written outside the editor.
 *
 * Input is an already normalized and sanitized array: `row` (event table
 * columns), `meta` (event meta keys), `terms` (`calendars`/`tags` => term id
 * lists, a bucket present only when the caller named it), `assumptions` and
 * `extra` (untouched contributor input, handed to the write action).
 * `EventInputSchema::normalize()` produces that shape for the abilities, but
 * nothing here depends on the abilities layer.
 *
 * @since 3.14.0
 */
class EventWriter {

	/**
	 * How many unknown term ids an error message names before counting the rest.
	 *
	 * @since 3.14.0
	 * @deprecated 3.15.0 Use EventTermAssigner::MAX_REPORTED_IDS.
	 *
	 * @var int
	 */
	const MAX_REPORTED_IDS = EventTermAssigner::MAX_REPORTED_IDS;

	/**
	 * Calendar/tag term assignment.
	 *
	 * @since 3.15.0
	 *
	 * @var EventTermAssigner
	 */
	private $terms;

	/**
	 * Composes the term assigner, so a caller can substitute its own.
	 *
	 * @since 3.15.0
	 *
	 * @param EventTermAssigner|null $terms Term assigner. Defaults to a new instance.
	 */
	public function __construct( ?EventTermAssigner $terms = null ) {

		$this->terms = $terms ?? new EventTermAssigner();
	}

	/**
	 * Create a draft event.
	 *
	 * Status is always draft: publishing stays a human action in the editor.
	 *
	 * @since 3.14.0
	 *
	 * @param array $normalized Normalized input, see the class docblock.
	 *
	 * @return array|WP_Error `[ event_id, post_id, applied, errors, explained ]` or an error.
	 */
	public function create( array $normalized ) {

		$assumptions = [];
		$normalized  = $this->terms->apply_default_calendar_on_create( $normalized, $assumptions );

		$row = isset( $normalized['row'] ) ? (array) $normalized['row'] : [];

		// The event row has no default status of its own (BerlinDB writes the
		// column NULL); without this, sugar_calendar_get_events() finds the
		// event under neither 'draft' nor 'any' even though the post is correct.
		$row['status'] = 'draft';

		$post_id = wp_insert_post(
			[
				'post_type'    => sugar_calendar_get_event_post_type_id(),
				'post_title'   => wp_slash( $row['title'] ?? '' ),
				'post_content' => wp_slash( $row['content'] ?? '' ),
				'post_excerpt' => wp_slash( $row['excerpt'] ?? '' ),
				'post_status'  => 'draft',
				'post_author'  => get_current_user_id(),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'sc_events_write_failed',
				__( 'The event could not be saved.', 'sugar-calendar-lite' ),
				[ 'status' => 500 ]
			);
		}

		// Excerpt has no counterpart column in wp_sc_events; it was written to
		// the post above, so it must not also ride into the event row as
		// unregistered (and therefore silently dropped) meta.
		unset( $row['excerpt'] );

		$event_id = sugar_calendar_add_event( $this->to_save( $row, $normalized, (int) $post_id ) );

		if ( empty( $event_id ) ) {
			// Roll back rather than leave a post with no event row (see #636).
			wp_delete_post( (int) $post_id, true );

			return new WP_Error(
				'sc_events_write_failed',
				__( 'The event record could not be created.', 'sugar-calendar-lite' ),
				[ 'status' => 500 ]
			);
		}

		$terms = $this->terms->assign_terms( (int) $post_id, $normalized );

		$result = [
			'event_id'    => (int) $event_id,
			'post_id'     => (int) $post_id,
			// Contributors fill `applied` through the by-reference $result on
			// the write action; core writes nothing into it.
			'applied'     => [],
			'assumptions' => $assumptions,
			'errors'      => $terms['errors'],
			// calendar_ids/tag_ids whose unknown/wrong-taxonomy ids assign_terms()
			// already named in errors, so the snapshot diff does not double them.
			'explained'   => $terms['explained'],
		];

		$this->fire_write_action( (int) $event_id, $normalized, $result );

		return $result;
	}

	/**
	 * Patch an existing event.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $event_id   Sugar Calendar event id.
	 * @param array $normalized Normalized patch, see the class docblock.
	 *
	 * @return array|WP_Error `[ post_id, previous, applied, errors, explained ]` or an error.
	 */
	public function update( int $event_id, array $normalized ) {

		$event = sugar_calendar_get_event( $event_id );

		if ( empty( $event->id ) ) {
			return new WP_Error(
				'sc_events_invalid_input',
				__( 'That event could not be found.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		/**
		 * Filters whether the writer may patch this event.
		 *
		 * Core writes single events only. A feature that keeps another kind of event in step
		 * with its row, such as a recurring series and its occurrences, answers for its own.
		 *
		 * @since 3.15.0
		 *
		 * @param bool   $supported Whether the event can be patched.
		 * @param object $event     The event before the patch.
		 */
		$supported = (bool) apply_filters(
			'sugar_calendar_events_event_writer_supports_event',
			(string) $event->object_subtype === sugar_calendar_get_event_post_type_id(),
			$event
		);

		if ( ! $supported ) {
			return new WP_Error(
				'sc_events_unsupported_event',
				__( 'Recurring events have to be edited in the event editor, so their repeats stay in step.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		$post_id     = (int) $event->object_id;
		$assumptions = [];
		$explained   = [];
		$normalized  = $this->terms->apply_default_calendar_on_clear( $post_id, $normalized, $assumptions, $explained );
		$row         = isset( $normalized['row'] ) ? (array) $normalized['row'] : [];
		$meta        = isset( $normalized['meta'] ) ? (array) $normalized['meta'] : [];

		// Never let a patch move the event's status.
		unset( $row['status'] );

		// Excerpt has no counterpart column in wp_sc_events, so it must not go
		// through the generic column-diff/write path below (which would treat
		// it as an unregistered, silently-dropped eventmeta write). It is
		// still passed to apply_post_fields() further down, which writes it
		// straight to the post.
		$event_row = $row;

		unset( $event_row['excerpt'] );

		$changed  = $this->changed_columns( $event, $event_row );
		$previous = $this->previous_values( $event, $changed, $normalized );

		// Diff it by value the way the columns above are, so a patch resending
		// the stored excerpt is not reported as a change or offered as an undo.
		if ( array_key_exists( 'excerpt', $row ) ) {
			$stored = (string) get_post_field( 'post_excerpt', $post_id );

			if ( $stored !== (string) $row['excerpt'] ) {
				$previous['excerpt'] = $stored;
			}
		}

		$errors = $this->apply_post_fields( $post_id, $row );

		// Direct meta writes persist regardless of whether any column changes.
		foreach ( $meta as $key => $value ) {
			update_event_meta( $event_id, $key, wp_slash( $value ) );
		}

		$written = $this->write_columns( $event, $changed, $post_id );

		if ( is_wp_error( $written ) ) {
			return $written;
		}

		$terms     = $this->terms->assign_terms( $post_id, $normalized );
		$errors    = array_merge( $errors, $terms['errors'] );
		$explained = array_merge( $explained, $terms['explained'] );

		// A bucket nothing could be applied for did not change, so it must not
		// be reported as changed or offered as an undo. Same for a bucket that
		// was applied but landed on the same set it started from (a resend).
		foreach ( array_merge( $terms['skipped'], $terms['unchanged'] ) as $field ) {
			unset( $previous[ $field ] );
		}

		$result = [
			'post_id'     => $post_id,
			'previous'    => $previous,
			// Contributors fill `applied` through the by-reference $result on
			// the write action; core writes nothing into it.
			'applied'     => [],
			'assumptions' => $assumptions,
			'errors'      => $errors,
			// Field paths whose refusal is already explained in `errors`/
			// `assumptions`, so EventSnapshot::diff() does not double-report them.
			'explained'   => $explained,
		];

		$this->fire_write_action( $event_id, $normalized, $result );

		return $result;
	}

	/**
	 * Write the changed columns to the event row.
	 *
	 * @since 3.14.0
	 *
	 * @param object $event   Event before the patch.
	 * @param array  $changed Columns that differ, column => new value.
	 * @param int    $post_id Post id the event belongs to.
	 *
	 * @return true|WP_Error
	 */
	private function write_columns( $event, array $changed, int $post_id ) {

		if ( empty( $changed ) ) {
			return true;
		}

		// Every other sugar_calendar_update_event()/add_event() caller passes
		// these three keys, and listeners on sugar_calendar_updated_event read
		// them to identify what changed. They always compare equal to what is
		// stored, so Query::update_item()'s column diff drops them and they
		// never turn a meta-only patch into a row write.
		$changed['object_id']      = $post_id;
		$changed['object_type']    = (string) $event->object_type;
		$changed['object_subtype'] = (string) $event->object_subtype;

		$event_id = (int) $event->id;
		$updated  = sugar_calendar_update_event( $event_id, $changed, $event );

		// BerlinDB's Query::update_item() saves any meta riding the data
		// array, then returns false whenever no *column* differs from
		// what is already stored — a falsy return here does not mean the
		// write failed, so only a read-back can tell the two apart.
		if ( empty( $updated ) && ! $this->columns_match( $event_id, $changed ) ) {
			return new WP_Error(
				'sc_events_write_failed',
				__( 'The event could not be updated.', 'sugar-calendar-lite' ),
				[ 'status' => 500 ]
			);
		}

		return true;
	}

	/**
	 * Write the patched title, content and excerpt back to the event's post.
	 *
	 * Values are slashed because `wp_update_post()` unslashes; the same
	 * unslashed values go to the event row, which `$wpdb` expects raw.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $post_id Post id.
	 * @param array $row     Normalized row values.
	 *
	 * @return string[] One message when the post could not be updated.
	 */
	private function apply_post_fields( int $post_id, array $row ): array {

		$map = [
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
		];

		$post = [];

		foreach ( $map as $key => $field ) {
			if ( isset( $row[ $key ] ) ) {
				$post[ $field ] = wp_slash( $row[ $key ] );
			}
		}

		if ( empty( $post ) ) {
			return [];
		}

		$post['ID'] = $post_id;

		$updated = wp_update_post( $post );

		// The event row is written separately, so a failure here leaves the
		// post and the event record out of step (#636).
		if ( is_wp_error( $updated ) || empty( $updated ) ) {
			return [
				__( 'The title, description and excerpt could not be saved to the event post, so the post may still show the old text. Send the patch again.', 'sugar-calendar-lite' ),
			];
		}

		return [];
	}

	/**
	 * Admin edit URL for an event's post.
	 *
	 * @since 3.14.0
	 *
	 * @param int $post_id Post id.
	 *
	 * @return string
	 */
	public static function edit_url( int $post_id ): string {

		$url = get_edit_post_link( $post_id, 'raw' );

		/**
		 * Filters the editor URL the writer reports for an event's post.
		 *
		 * A feature whose events are edited somewhere core does not know, such as a recurring
		 * series opened through one of its dates, answers for its own.
		 *
		 * @since 3.15.0
		 *
		 * @param string $url     Editor URL, or empty when core has none for this user.
		 * @param int    $post_id Post id.
		 */
		$url = apply_filters( 'sugar_calendar_events_event_writer_edit_url', is_string( $url ) ? $url : '', $post_id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Build the data array for sugar_calendar_add_event/update_event.
	 *
	 * Registered meta keys (location) ride this array and persist through the
	 * event store's registered-key filter. They are slashed because the meta
	 * engine behind it unslashes, the same as `update_event_meta()`.
	 *
	 * @since 3.14.0
	 *
	 * @param array $row        Row fields from the normalized input.
	 * @param array $normalized Full normalized input.
	 * @param int   $post_id    Post id the event belongs to.
	 *
	 * @return array
	 */
	private function to_save( array $row, array $normalized, int $post_id ): array {

		$meta = isset( $normalized['meta'] ) ? (array) $normalized['meta'] : [];

		return array_merge(
			$row,
			array_map( 'wp_slash', $meta ),
			[
				'object_id'      => $post_id,
				'object_type'    => 'post',
				'object_subtype' => sugar_calendar_get_event_post_type_id(),
			]
		);
	}

	/**
	 * Normalize a column value for string comparison.
	 *
	 * BerlinDB event columns are untyped strings at runtime (e.g. `all_day` is
	 * `'0'`/`'1'`); a bare `(string)` cast turns `false` into `''`, not `'0'`,
	 * which would misreport an unchanged boolean as changed.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $value Column value, from either side of the comparison.
	 *
	 * @return string
	 */
	private function column_value( $value ): string {

		return is_bool( $value ) ? (string) (int) $value : (string) $value;
	}

	/**
	 * Which of the patch's row columns actually differ from the stored event.
	 *
	 * @since 3.14.0
	 *
	 * @param object $event Event before the patch.
	 * @param array  $row   Row fields the patch intends to write.
	 *
	 * @return array Column => new value, for columns that differ.
	 */
	private function changed_columns( $event, array $row ): array {

		$changed = [];

		foreach ( $row as $column => $value ) {
			if ( $this->column_value( $value ) !== $this->column_value( $event->{$column} ?? '' ) ) {
				$changed[ $column ] = $value;
			}
		}

		return $changed;
	}

	/**
	 * Verify a set of intended column values against the event as stored.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $event_id Sugar Calendar event id.
	 * @param array $expected Column => intended value.
	 *
	 * @return bool Whether every column matches the stored event.
	 */
	private function columns_match( int $event_id, array $expected ): bool {

		$event = sugar_calendar_get_event( $event_id );

		foreach ( $expected as $column => $value ) {
			if ( $this->column_value( $value ) !== $this->column_value( $event->{$column} ?? '' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Capture the pre-patch value of every field the patch actually changes.
	 *
	 * Only changed fields: replaying a full snapshot as an undo would revert a
	 * concurrent edit nobody asked to change. Values come back in *input*
	 * shape rather than storage shape, so the map validates against the input
	 * schema of the ability that produced it when it is replayed as an undo
	 * (`all_day` is a real boolean here, where the column holds `'0'`/`'1'`).
	 *
	 * @since 3.14.0
	 *
	 * @param object $event      Event before the patch.
	 * @param array  $changed    Columns that differ, column => new value.
	 * @param array  $normalized Full normalized input.
	 *
	 * @return array Keyed by ability input field name.
	 */
	private function previous_values( $event, array $changed, array $normalized ): array {

		$map = [
			'title'    => 'title',
			'content'  => 'description',
			'start'    => 'start',
			'end'      => 'end',
			'all_day'  => 'all_day',
			'start_tz' => 'timezone',
			// end_tz shares the single `timezone` input field, which writes
			// both columns, so either column changing surfaces as one entry.
			'end_tz'   => 'timezone',
		];

		$previous = [];

		foreach ( $map as $column => $field ) {
			if ( ! array_key_exists( $column, $changed ) || array_key_exists( $field, $previous ) ) {
				continue;
			}

			$value = $event->{$column} ?? '';

			$previous[ $field ] = $column === 'all_day' ? (bool) (int) $value : (string) $value;
		}

		$previous = array_merge( $previous, $this->previous_location( $event, $normalized ) );

		$terms = (array) ( $normalized['terms'] ?? [] );

		foreach ( $this->terms->term_buckets() as $bucket => $spec ) {
			// array_key_exists, not empty(): the caller explicitly clearing a
			// bucket (an empty array) still needs its pre-patch value captured,
			// or replaying `previous` later could never undo the clear.
			if ( ! array_key_exists( $bucket, $terms ) ) {
				continue;
			}

			$object_terms = wp_get_object_terms( (int) $event->object_id, $spec['taxonomy'], [ 'fields' => 'ids' ] );

			$previous[ $spec['field'] ] = is_array( $object_terms ) ? array_map( 'intval', $object_terms ) : [];
		}

		return $previous;
	}

	/**
	 * Pre-patch `location` value, only when the patch actually changes it.
	 *
	 * Split out of `previous_values()` so a patch resending the stored text is not reported as
	 * a change or offered as an undo, without pushing that method over its complexity budget.
	 *
	 * @since 3.15.0
	 *
	 * @param object $event      Event before the patch.
	 * @param array  $normalized Full normalized input.
	 *
	 * @return array `[ 'location' => string ]`, or empty.
	 */
	private function previous_location( $event, array $normalized ): array {

		if ( ! isset( $normalized['meta']['location'] ) ) {
			return [];
		}

		$stored = (string) get_event_meta( (int) $event->id, 'location', true );

		return $stored !== (string) $normalized['meta']['location'] ? [ 'location' => $stored ] : [];
	}

	/**
	 * Let add-ons apply their own slice of the input.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $event_id   Sugar Calendar event id (NOT the post id).
	 * @param array $normalized Full normalized input.
	 * @param array $result     Result so far, by reference for contributors.
	 *
	 * @return void
	 */
	private function fire_write_action( int $event_id, array $normalized, array &$result ) {

		/**
		 * Fires after an ability has written an event, for add-on slices.
		 *
		 * The id is the Sugar Calendar event id, not the post id: contributors
		 * write to wp_sc_eventmeta, which is keyed by event id.
		 *
		 * A contributor's own input rides `$normalized['extra']` — every input
		 * key the core schema's whitelist did not consume, put there so a
		 * property an add-on registers via the
		 * `sugar_calendar_integrations_abilities_event_input_schema` filter is
		 * not silently dropped before reaching this hook. Those values are
		 * validated against the contributor's own schema but not sanitized,
		 * and core capability-checked none of them: whatever a contributor
		 * persists, it sanitizes and gates itself.
		 *
		 * `$result['applied']` and `$result['errors']` are flat lists of
		 * translated strings read by the caller. A contributor must never
		 * throw. Failures belong in `errors`, so an incomplete config does not
		 * undo a successfully created event.
		 *
		 * Callback signature, three positional arguments (so `add_action()` needs
		 * `$accepted_args = 3`, and `$result` has to be taken by reference to
		 * add to it):
		 * `function ( int $event_id, array $normalized, array &$result )`.
		 *
		 * @since 3.14.0
		 *
		 * @param array $args `[ $event_id, $normalized, &$result ]`, delivered to
		 *                    the callback as the three separate arguments above.
		 */
		do_action_ref_array( 'sugar_calendar_events_event_writer_after_write', [ $event_id, $normalized, &$result ] );
	}
}
