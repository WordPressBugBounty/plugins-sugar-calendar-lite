<?php
/**
 * Calendar/tag term assignment for events written outside the editor.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Events;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;
use WP_Term;

/**
 * EventTermAssigner — owns the calendar/tag term buckets `EventWriter`
 * composes: resolving requested term ids, the default-calendar rule, and the
 * error messages a caller sees for unknown or wrong-taxonomy ids.
 *
 * @since 3.15.0
 */
final class EventTermAssigner {

	/**
	 * How many unknown term ids an error message names before counting the rest.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const MAX_REPORTED_IDS = 10;

	/**
	 * Taxonomy, input field name and singular label for each terms bucket.
	 *
	 * `label` names the bucket in a cross-taxonomy error ("12 is a calendar, not a tag").
	 *
	 * @since 3.15.0
	 *
	 * @return array Bucket name => `[ taxonomy, field, label ]`.
	 */
	public function term_buckets(): array {

		return [
			'calendars' => [
				'taxonomy' => sugar_calendar_get_calendar_taxonomy_id(),
				'field'    => 'calendar_ids',
				'label'    => __( 'calendar', 'sugar-calendar-lite' ),
			],
			'tags'      => [
				'taxonomy' => TagsHelpers::get_tags_taxonomy_id(),
				'field'    => 'tag_ids',
				'label'    => __( 'tag', 'sugar-calendar-lite' ),
			],
		];
	}

	/**
	 * The other bucket's spec, so an unknown id can be checked against it.
	 *
	 * @since 3.15.0
	 *
	 * @param string $taxonomy This bucket's taxonomy id.
	 *
	 * @return array|null
	 */
	private function other_bucket( string $taxonomy ) {

		foreach ( $this->term_buckets() as $spec ) {
			if ( $spec['taxonomy'] !== $taxonomy ) {
				return $spec;
			}
		}

		return null;
	}

	/**
	 * Resolve the site's default calendar term, or null when there isn't a usable one.
	 *
	 * @since 3.15.0
	 *
	 * @return WP_Term|null
	 */
	private function default_calendar_term() {

		$default = absint( sugar_calendar_get_default_calendar() );

		if ( empty( $default ) ) {
			return null;
		}

		$term = get_term( $default, sugar_calendar_get_calendar_taxonomy_id() );

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Assign the site's default calendar on create when the caller never named one, matching
	 * `Admin\Events\Events::save()` (which runs only on the classic editor's `save_post`, not
	 * this REST/CLI path).
	 *
	 * Skipped for a caller who cannot assign calendar terms (the same capability
	 * `assign_bucket()` checks): otherwise a Contributor got a false "added to the default
	 * calendar" note plus a permission error about `calendar_ids`, a field they never sent
	 * (Minor 1).
	 *
	 * @since 3.15.0
	 *
	 * @param array $normalized  Normalized create input.
	 * @param array $assumptions Collects the default-calendar note, by reference.
	 *
	 * @return array
	 */
	public function apply_default_calendar_on_create( array $normalized, array &$assumptions ): array {

		if ( array_key_exists( 'calendars', $normalized['terms'] ?? [] ) ) {
			return $normalized;
		}

		if ( ! $this->can_assign_calendar_terms() ) {
			return $normalized;
		}

		$term = $this->default_calendar_term();

		if ( $term === null ) {
			return $normalized;
		}

		$normalized['terms']['calendars'] = [ (int) $term->term_id ];

		$assumptions[] = sprintf(
			/* translators: %s: default calendar name. */
			__( 'No calendar was given, so the event is in the default calendar, %s.', 'sugar-calendar-lite' ),
			self::decode( $term->name )
		);

		return $normalized;
	}

	/**
	 * Whether the current user can assign calendar terms, the same capability `assign_bucket()`
	 * checks before it writes `calendar_ids`.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function can_assign_calendar_terms(): bool {

		$object = get_taxonomy( sugar_calendar_get_calendar_taxonomy_id() );

		return ! empty( $object ) && current_user_can( $object->cap->assign_terms );
	}

	/**
	 * Keep the default calendar on a published event whose patch clears every calendar,
	 * matching `Admin\Events\Events::save()`, whose own next save would put it back anyway. A
	 * draft is left with none: that hook only ever acts on a published post.
	 *
	 * @since 3.15.0
	 *
	 * @param int   $post_id     Post id.
	 * @param array $normalized  Normalized patch input.
	 * @param array $assumptions Collects the default-calendar note, by reference.
	 * @param array $explained   Collects field paths this note already explains, by reference.
	 *
	 * @return array
	 */
	public function apply_default_calendar_on_clear( int $post_id, array $normalized, array &$assumptions, array &$explained ): array {

		$terms = $normalized['terms'] ?? [];

		if ( ! array_key_exists( 'calendars', $terms ) || ! empty( $terms['calendars'] ) ) {
			return $normalized;
		}

		if ( (string) get_post_status( $post_id ) !== 'publish' ) {
			return $normalized;
		}

		$term = $this->default_calendar_term();

		if ( $term === null ) {
			return $normalized;
		}

		$normalized['terms']['calendars'] = [ (int) $term->term_id ];

		$assumptions[] = sprintf(
			/* translators: %s: default calendar name. */
			__( 'Calendars were cleared on a published event, so it keeps the default calendar, %s.', 'sugar-calendar-lite' ),
			self::decode( $term->name )
		);

		$explained[] = 'calendar_ids';

		return $normalized;
	}

	/**
	 * Assign calendar and tag terms, ignoring buckets the caller omitted.
	 *
	 * @since 3.15.0
	 *
	 * @param int   $post_id    Post id.
	 * @param array $normalized Full normalized input.
	 *
	 * @return array `[ errors => string[], skipped => string[], unchanged => string[],
	 *               explained => string[] ]`, where `skipped` names input fields whose bucket
	 *               was not applied at all, `unchanged` names ones that resolved to the set
	 *               already assigned, so a caller can stop reporting either as changed, and
	 *               `explained` names fields whose bucket added an error message (unknown or
	 *               wrong-taxonomy ids), so a caller can skip the snapshot diff's own line.
	 */
	public function assign_terms( int $post_id, array $normalized ): array {

		$errors    = [];
		$skipped   = [];
		$unchanged = [];
		$explained = [];
		$terms     = (array) ( $normalized['terms'] ?? [] );

		foreach ( $this->term_buckets() as $bucket => $spec ) {
			// array_key_exists: a bucket the caller never mentioned is left
			// alone; an explicitly empty bucket is a request to clear it.
			if ( ! array_key_exists( $bucket, $terms ) ) {
				continue;
			}

			$changed = true;
			$before  = count( $errors );
			$applied = $this->assign_bucket( $post_id, $spec, (array) $terms[ $bucket ], $errors, $changed );

			if ( count( $errors ) > $before ) {
				$explained[] = $spec['field'];
			}

			if ( ! $applied ) {
				$skipped[] = $spec['field'];
			} elseif ( ! $changed ) {
				$unchanged[] = $spec['field'];
			}
		}

		return [
			'errors'    => $errors,
			'skipped'   => $skipped,
			'unchanged' => $unchanged,
			'explained' => $explained,
		];
	}

	/**
	 * Assign one bucket's term ids, replacing whatever the post carried.
	 *
	 * An empty id list is an explicit clear. When every id in a non-empty list
	 * is unknown, existing assignments are left alone rather than wiped by
	 * `wp_set_object_terms()`'s replace semantics.
	 *
	 * @since 3.15.0
	 *
	 * @param int   $post_id Post id.
	 * @param array $spec    Bucket spec, see term_buckets().
	 * @param array $ids     Requested term ids.
	 * @param array $errors  Collects messages, by reference.
	 * @param bool  $changed Set to whether the assigned set actually differs, by reference.
	 *
	 * @return bool Whether the bucket was applied (including a no-op resend).
	 */
	private function assign_bucket( int $post_id, array $spec, array $ids, array &$errors, &$changed ): bool {

		$changed  = true;
		$taxonomy = $spec['taxonomy'];
		$field    = $spec['field'];
		$object   = get_taxonomy( $taxonomy );

		if ( empty( $object ) ) {
			$errors[] = sprintf(
				/* translators: %s: input field name, e.g. tag_ids. */
				__( 'That taxonomy is turned off on this site, so %s was not applied.', 'sugar-calendar-lite' ),
				$field
			);

			return false;
		}

		// wp_set_object_terms() runs no capability check of its own, so the
		// gate core's own posts controller applies is applied here.
		if ( ! current_user_can( $object->cap->assign_terms ) ) {
			$errors[] = sprintf(
				/* translators: %s: input field name, e.g. calendar_ids. */
				__( 'You do not have permission to assign these terms, so %s was not applied.', 'sugar-calendar-lite' ),
				$field
			);

			return false;
		}

		$valid = $this->existing_term_ids( $ids, $spec, $errors );

		if ( ! empty( $ids ) && empty( $valid ) ) {
			return false;
		}

		$current = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
		$current = is_array( $current ) ? array_map( 'intval', $current ) : [];

		if ( $this->same_id_set( $current, $valid ) ) {
			$changed = false;

			return true;
		}

		$assigned = wp_set_object_terms( $post_id, $valid, $taxonomy );

		if ( is_wp_error( $assigned ) ) {
			$errors[] = sprintf(
				/* translators: %1$s: input field name, %2$s: the reason the assignment failed. */
				__( '%1$s could not be saved: %2$s', 'sugar-calendar-lite' ),
				$field,
				$assigned->get_error_message()
			);

			return false;
		}

		return true;
	}

	/**
	 * Whether two term id lists hold the same ids, regardless of order.
	 *
	 * @since 3.15.0
	 *
	 * @param int[] $a First list.
	 * @param int[] $b Second list.
	 *
	 * @return bool
	 */
	private function same_id_set( array $a, array $b ): bool {

		sort( $a );
		sort( $b );

		return $a === $b;
	}

	/**
	 * Keep only the ids that exist in the taxonomy, reporting the rest.
	 *
	 * One query for the whole list: the caller is a language model, so a long
	 * hallucinated list is a real input shape.
	 *
	 * @since 3.15.0
	 *
	 * @param array $ids    Requested term ids.
	 * @param array $spec   This bucket's spec, see term_buckets().
	 * @param array $errors Collects messages, by reference.
	 *
	 * @return int[]
	 */
	private function existing_term_ids( array $ids, array $spec, array &$errors ): array {

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( empty( $ids ) ) {
			return [];
		}

		$found = get_terms(
			[
				'taxonomy'   => $spec['taxonomy'],
				'include'    => $ids,
				'fields'     => 'ids',
				'hide_empty' => false,
			]
		);

		$found   = is_array( $found ) ? array_map( 'intval', $found ) : [];
		$unknown = array_values( array_diff( $ids, $found ) );

		if ( ! empty( $unknown ) ) {
			$this->report_unknown_ids( $unknown, $spec, $errors );
		}

		return array_values( array_intersect( $ids, $found ) );
	}

	/**
	 * Report ids absent from this bucket's taxonomy, naming the ones that belong to the other
	 * bucket's taxonomy instead of just calling them unknown.
	 *
	 * @since 3.15.0
	 *
	 * @param int[] $unknown Ids absent from this bucket's taxonomy.
	 * @param array $spec    This bucket's spec, see term_buckets().
	 * @param array $errors  Collects messages, by reference.
	 *
	 * @return void
	 */
	private function report_unknown_ids( array $unknown, array $spec, array &$errors ) {

		$other   = $this->other_bucket( $spec['taxonomy'] );
		$crossed = [];

		if ( $other !== null ) {
			$found_other = get_terms(
				[
					'taxonomy'   => $other['taxonomy'],
					'include'    => $unknown,
					'fields'     => 'ids',
					'hide_empty' => false,
				]
			);

			$crossed = is_array( $found_other ) ? array_map( 'intval', $found_other ) : [];
		}

		if ( ! empty( $crossed ) ) {
			$errors[] = count( $crossed ) === 1
				? sprintf(
					/* translators: %1$d: term id. %2$s: taxonomy it belongs to, e.g. calendar. %3$s: this bucket's own label, e.g. tag. %4$s: this bucket's input field name. */
					__( '%1$d is a %2$s, not a %3$s, so it was not assigned to %4$s.', 'sugar-calendar-lite' ),
					$crossed[0],
					$other['label'],
					$spec['label'],
					$spec['field']
				)
				: sprintf(
					/* translators: %1$s: comma-separated term ids. %2$s: taxonomy they belong to, e.g. calendars. %3$s: this bucket's own label, e.g. tags. %4$s: this bucket's input field name. */
					__( '%1$s are %2$s ids, not %3$s ids, so they were not assigned to %4$s.', 'sugar-calendar-lite' ),
					implode( ', ', $crossed ),
					$other['label'],
					$spec['label'],
					$spec['field']
				);
		}

		$missing = array_values( array_diff( $unknown, $crossed ) );

		if ( ! empty( $missing ) ) {
			$errors[] = $this->unknown_ids_message( $missing, $spec['field'] );
		}
	}

	/**
	 * Message naming the term ids that do not exist.
	 *
	 * @since 3.15.0
	 *
	 * @param int[]  $unknown Ids that resolved to no term.
	 * @param string $field   Input field name, e.g. tag_ids.
	 *
	 * @return string
	 */
	private function unknown_ids_message( array $unknown, string $field ): string {

		$named     = array_slice( $unknown, 0, self::MAX_REPORTED_IDS );
		$remaining = count( $unknown ) - count( $named );

		$message = sprintf(
			/* translators: %1$s: input field name, e.g. tag_ids. %2$s: comma-separated list of term ids. */
			__( '%1$s: these ids do not exist and were not assigned: %2$s.', 'sugar-calendar-lite' ),
			$field,
			implode( ', ', $named )
		);

		if ( $remaining > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: how many further ids were skipped. */
				_n( '%d more id was skipped as well.', '%d more ids were skipped as well.', $remaining, 'sugar-calendar-lite' ),
				$remaining
			);
		}

		return $message;
	}

	/**
	 * Decode stored HTML entities into plain text for a term name.
	 *
	 * Term names are stored entity-encoded, so `Rock & Roll` reads back as `Rock &amp; Roll`;
	 * these assumption notes are read by a language model, which would repeat `&amp;` in prose.
	 *
	 * @since 3.15.0
	 *
	 * @param string $value Stored term name.
	 *
	 * @return string
	 */
	private static function decode( string $value ): string {

		return html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) );
	}
}
