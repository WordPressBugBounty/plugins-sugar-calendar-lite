<?php
/**
 * Ability: patch an existing event.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Event;
use Sugar_Calendar\Events\EventWriter;
use Sugar_Calendar\Integrations\Abilities\AbstractWriteAbility;
use Sugar_Calendar\Integrations\Abilities\EventInputSchema;
use Sugar_Calendar\Integrations\Abilities\EventSnapshot;
use Sugar_Calendar\Integrations\Abilities\SignupModes;

/**
 * UpdateEvent — patches one event and reports the previous value of every
 * field it touched, which is what makes the chat's "undo" this same ability
 * replayed with that map. A detail-object leaf is reported as `ticketing.price`;
 * replaying it means sending it back nested, with the event's `signup`.
 *
 * @since 3.14.0
 */
class UpdateEvent extends AbstractWriteAbility {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'update-event';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return __( 'Update Event', 'sugar-calendar-lite' );
	}

	/**
	 * Human-readable ability description.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return __(
			'Changes the given fields on one event and returns their previous values. Does not publish or unpublish the event.',
			'sugar-calendar-lite'
		);
	}

	/**
	 * Input JSON-schema for this ability.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {

		$properties = EventInputSchema::properties( true );

		$properties['event_id'] = [
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => __( 'Sugar Calendar event id, as returned by sc-events/list-events and sc-events/search-events.', 'sugar-calendar-lite' ),
		];

		$properties['post_id'] = [
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => __( 'WP post id, the number in the post= parameter of an event editor URL such as /wp-admin/post.php?post=1304&action=edit. Use this when the user names an event by its editor link. It is not the same number as event_id.', 'sugar-calendar-lite' ),
		];

		// No `required`: either id identifies the event, which a single
		// required key cannot express. run-time validation answers 400 when
		// neither arrives.
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $properties,
		];
	}

	/**
	 * Output JSON-schema for this ability.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {

		return [
			'type'       => 'object',
			'properties' => [
				'event_id'    => [ 'type' => 'integer' ],
				'edit_url'    => [ 'type' => 'string' ],
				'updated'     => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'previous'    => [ 'type' => 'object' ],
				'assumptions' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'applied'     => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'errors'      => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'event'       => [ 'type' => 'object' ],
			],
		];
	}

	/**
	 * Per-event gate: the same capability needed to edit the event by hand.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return true|\WP_Error
	 */
	public function check_permission( $input = null ) {

		$args     = $this->normalize_input( $input );
		$event_id = $this->resolve_event_id_input( $args, 'event_id' );

		if ( is_wp_error( $event_id ) ) {
			return $this->defer_lookup_error( $event_id, 'edit_events' );
		}

		return $this->defer_lookup_error(
			$this->check_per_event_permission(
				$event_id,
				__( 'You do not have permission to edit this event.', 'sugar-calendar-lite' )
			),
			'edit_events'
		);
	}

	/**
	 * Whether a normalized patch carries anything writable.
	 *
	 * A terms bucket counts even when explicitly empty — that's a request to
	 * clear it, which is a real change `empty()` alone would miss.
	 *
	 * @since 3.14.0
	 *
	 * @param array $normalized Output of EventInputSchema::normalize().
	 *
	 * @return bool
	 */
	private function changes_something( array $normalized ): bool {

		$terms = isset( $normalized['terms'] ) ? (array) $normalized['terms'] : [];

		return ! empty( $normalized['row'] )
			|| ! empty( $normalized['meta'] )
			|| ! empty( $normalized['extra'] )
			|| array_key_exists( 'calendars', $terms )
			|| array_key_exists( 'tags', $terms );
	}

	/**
	 * The pre-write snapshot EventSnapshot::changed_leaves() compares against, or `[]`.
	 *
	 * Only a detail object (`ticketing`, `rsvp`) is read from it, so any other patch skips the read.
	 *
	 * @since 3.15.0
	 *
	 * @param array $requested Patch input, without either id key.
	 * @param int   $event_id  Sugar Calendar event id.
	 *
	 * @return array
	 */
	private static function before_snapshot( array $requested, int $event_id ): array {

		foreach ( $requested as $value ) {

			if ( is_array( $value ) && $value !== [] && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
				return EventSnapshot::for_event( $event_id );
			}
		}

		return [];
	}

	/**
	 * Fill the date group from the stored event so a patch stays a patch.
	 *
	 * `normalize()` rewrites the date columns together as soon as a start is present, so a
	 * start-only patch would otherwise reset the end to the start and clear `all_day`. The fill
	 * happens here rather than in `EventInputSchema` because the schema does no database reads.
	 *
	 * `timezone` is deliberately not filled here (unlike start/end/all_day): the two stored zone
	 * columns can differ, and a single filled value would collapse them to one via
	 * `normalize()`'s single `timezone` field. `build_normalized()` restores both stored columns
	 * afterward instead, whenever the caller never mentioned `timezone`.
	 *
	 * @since 3.14.0
	 * @since 3.15.0 Preserves the stored duration on a start-only patch; refuses turning
	 *                  all-day off with no times instead of silently doing nothing.
	 *
	 * @param array $args        Patch input, without either id key.
	 * @param Event $event       Stored event.
	 * @param array $assumptions Collects the duration-preserving note, by reference.
	 *
	 * @return array|\WP_Error
	 */
	private function fill_date_group( array $args, Event $event, array &$assumptions ) {

		$group = [ 'start', 'end', 'all_day' ];

		if ( ! array_intersect( $group, array_keys( $args ) ) && ! array_key_exists( 'timezone', $args ) ) {
			return $args;
		}

		$start          = $event->start;
		$end            = $event->end;
		$stored_all_day = $event->all_day;

		// Legacy rows store a zero date rather than an empty one, and the strict
		// parser refuses it. A zero start has no stand-in, so say so rather than
		// filling an unparseable value and blaming the end date for it.
		if ( ! isset( $args['start'] ) && $event->is_empty_date( $start ) ) {
			return $this->invalid_input(
				__( 'This event has no usable start date, so send a start date along with the change.', 'sugar-calendar-lite' )
			);
		}

		$refused = $this->refuse_all_day_off_without_times( $args, $stored_all_day );

		if ( $refused !== null ) {
			return $refused;
		}

		$args = $this->keep_length_on_start_only_patch( $args, $event, $start, $end, $stored_all_day, $assumptions );

		$stored = [
			'start'   => $start,
			'all_day' => $stored_all_day,
		];

		// A zero end is left out entirely: normalize() reads an absent end as
		// "ends when it starts", which is what the zero date meant.
		if ( ! $event->is_empty_date( $end ) ) {
			$stored['end'] = $end;
		}

		foreach ( $group as $field ) {
			if ( ! isset( $args[ $field ] ) && isset( $stored[ $field ] ) ) {
				$args[ $field ] = $stored[ $field ];
			}
		}

		return $args;
	}

	/**
	 * Refuse turning all-day off with no times sent.
	 *
	 * The stored range on an all-day event is the 00:00:00-23:59:59 convention, not a real
	 * start, end or duration to keep, so turning all-day off needs both times restated.
	 *
	 * @since 3.15.0
	 *
	 * @param array $args           Patch input, without either id key.
	 * @param bool  $stored_all_day Whether the event was all-day before the patch.
	 *
	 * @return \WP_Error|null
	 */
	private function refuse_all_day_off_without_times( array $args, bool $stored_all_day ) {

		if (
			array_key_exists( 'all_day', $args )
			&& $args['all_day'] === false
			&& $stored_all_day
			&& ( ! isset( $args['start'] ) || ! isset( $args['end'] ) )
		) {
			return $this->invalid_input(
				__( 'To turn off all-day, also send the start and end times.', 'sugar-calendar-lite' )
			);
		}

		return null;
	}

	/**
	 * Keep the event's length on a start-only patch, anchoring an all-day start to its own day.
	 *
	 * The end moves with the start rather than being filled in as the old, now possibly-earlier,
	 * stored end. An event that stays all-day anchors the new start to `Y-m-d 00:00:00` before
	 * adding the stored length, or a start sent with a time of day would shift the whole range
	 * onto two days instead of keeping the one it patched.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $args           Patch input, without either id key.
	 * @param Event  $event          Stored event.
	 * @param string $start          Stored start.
	 * @param string $end            Stored end.
	 * @param bool   $stored_all_day Whether the event was all-day before the patch.
	 * @param array  $assumptions    Collects the duration-preserving note, by reference.
	 *
	 * @return array
	 */
	private function keep_length_on_start_only_patch( array $args, Event $event, string $start, string $end, bool $stored_all_day, array &$assumptions ): array {

		if ( ! isset( $args['start'] ) || isset( $args['end'] ) || $event->is_empty_date( $start ) || $event->is_empty_date( $end ) ) {
			return $args;
		}

		$start_ts      = strtotime( $start );
		$end_ts        = strtotime( $end );
		$new_start_ts  = strtotime( (string) $args['start'] );
		$stays_all_day = array_key_exists( 'all_day', $args ) ? (bool) $args['all_day'] : $stored_all_day;

		if ( $stays_all_day && $new_start_ts !== false ) {
			$new_start_ts = strtotime( gmdate( 'Y-m-d 00:00:00', $new_start_ts ) );
		}

		if ( $start_ts !== false && $end_ts !== false && $new_start_ts !== false && $end_ts >= $start_ts ) {
			$args['end']   = gmdate( 'Y-m-d H:i:s', $new_start_ts + ( $end_ts - $start_ts ) );
			$assumptions[] = __( 'The end moved with the start, so the event keeps its length.', 'sugar-calendar-lite' );
		}

		return $args;
	}

	/**
	 * Reject a patch whose signup detail belongs to a mode not chosen, or that would
	 * switch away from a mode that still holds data.
	 *
	 * Reads the current signup mode before the write happens, and hands it back via
	 * `$previous_signup` so the caller can report the change afterward without a
	 * second read.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $args            Patch input, without either id key.
	 * @param int    $event_id        Sugar Calendar event id.
	 * @param string $previous_signup Set to the mode read before the write, by reference.
	 *
	 * @return null|\WP_Error
	 */
	private function check_signup_preconditions( array $args, int $event_id, string &$previous_signup ) {

		$previous_signup = '';

		$mismatch_error = $this->signup_mismatch_error( $args );

		if ( $mismatch_error !== null ) {
			return $mismatch_error;
		}

		$requested_signup = isset( $args['signup'] ) ? (string) $args['signup'] : '';

		if ( $requested_signup === '' ) {
			return null;
		}

		$previous_signup = SignupModes::current( $event_id );

		if ( $previous_signup === $requested_signup ) {
			return null;
		}

		// Ask every declared mode about in-use, not just current()'s single winner: an event
		// carrying legacy data in more than one mode has a second mode invisible to current().
		foreach ( array_keys( SignupModes::available() ) as $mode ) {

			if ( $mode === $requested_signup ) {
				continue;
			}

			$reason = SignupModes::in_use_reason( $mode, $event_id );

			if ( $reason !== '' ) {
				return $this->signup_in_use( $reason );
			}
		}

		return null;
	}

	/**
	 * Load the target event, then check its signup preconditions against the patch.
	 *
	 * Folds the lookup and the precondition check into one call so `run()` stays a flat list
	 * of steps rather than interleaving them.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $args            Patch input, without either id key.
	 * @param int    $event_id        Sugar Calendar event id.
	 * @param string $previous_signup Set to the mode read before the write, by reference.
	 *
	 * @return Event|\WP_Error
	 */
	private function resolve_event( array $args, int $event_id, string &$previous_signup ) {

		$event = sugar_calendar_get_event( $event_id );

		if ( empty( $event->id ) ) {
			return $this->not_found();
		}

		$signup_error = $this->check_signup_preconditions( $args, $event_id, $previous_signup );

		if ( $signup_error !== null ) {
			return $signup_error;
		}

		return $event;
	}

	/**
	 * Fill the date group, normalize the patch, and reject it if nothing writable remains.
	 *
	 * @since 3.14.0
	 * @since 3.15.0 Restores both stored time zone columns exactly when the caller never
	 *                  sent `timezone` and all-day status is not changing, so an unrelated
	 *                  date/all_day edit can't collapse or clear an asymmetric zone pair.
	 *
	 * @param array $args  Patch input, without either id key.
	 * @param Event $event Stored event.
	 *
	 * @return array|\WP_Error
	 */
	private function build_normalized( array $args, Event $event ) {

		$timezone_sent  = array_key_exists( 'timezone', $args );
		$stored_all_day = (bool) $event->all_day;
		$assumptions    = [];

		$filled = $this->fill_date_group( $args, $event, $assumptions );

		if ( is_wp_error( $filled ) ) {
			return $filled;
		}

		$normalized = EventInputSchema::normalize( $filled, true );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		if ( ! $timezone_sent && array_key_exists( 'all_day', $normalized['row'] ?? [] ) ) {
			$normalized = $this->keep_stored_zones( $normalized, $event, $stored_all_day );
		}

		if ( ! empty( $assumptions ) ) {
			$normalized['assumptions'] = array_merge( $assumptions, (array) ( $normalized['assumptions'] ?? [] ) );
		}

		if ( ! $this->changes_something( $normalized ) ) {
			return $this->invalid_input(
				__( 'Nothing in that patch could be applied. Name a field this ability can change.', 'sugar-calendar-lite' )
			);
		}

		return $normalized;
	}

	/**
	 * Keep the stored zones when the patch sent none, and say what happened to them.
	 *
	 * normalize() only sees the patch, so it reports "follows the site's time zone" even when the
	 * stored zone is kept. Turning all-day on drops the zone, which the caller never asked for.
	 *
	 * @since 3.15.0
	 *
	 * @param array $normalized     Normalized patch.
	 * @param Event $event          The event before the patch.
	 * @param bool  $stored_all_day Whether the event was all-day before the patch.
	 *
	 * @return array
	 */
	private function keep_stored_zones( array $normalized, Event $event, bool $stored_all_day ): array {

		$all_day   = (bool) $normalized['row']['all_day'];
		$stored_tz = (string) $event->start_tz;

		if ( $all_day === $stored_all_day ) {
			$normalized['row']['start_tz'] = $stored_tz;
			$normalized['row']['end_tz']   = (string) $event->end_tz;

			if ( $stored_tz !== '' ) {
				$normalized['assumptions'] = array_values( array_diff( (array) ( $normalized['assumptions'] ?? [] ), [ EventInputSchema::floating_zone_assumption() ] ) );
			}

			return $normalized;
		}

		if ( $all_day && $stored_tz !== '' ) {
			$normalized['assumptions'][] = sprintf(
				/* translators: %s: the time zone the event had, such as Europe/Kyiv. */
				__( 'All-day events have no time zone, so the event\'s %s time zone was removed.', 'sugar-calendar-lite' ),
				$stored_tz
			);
		}

		return $normalized;
	}

	/**
	 * Take the event's current signup mode when a detail object arrives without `signup`.
	 *
	 * "Change the price" should not also have to say "keep selling tickets". A detail object for a
	 * mode the event does not use is still refused, here rather than by the shared mismatch check,
	 * whose message blames a `signup` this request never sent.
	 *
	 * @since 3.15.0
	 *
	 * @param array $args     Patch input, without either id key.
	 * @param int   $event_id Sugar Calendar event id.
	 *
	 * @return array|\WP_Error
	 */
	private function infer_signup( array $args, int $event_id ) {

		$mode = array_key_exists( 'signup', $args ) ? '' : SignupModes::detail_mode( $args );

		if ( $mode === '' ) {
			return $args;
		}

		if ( SignupModes::current( $event_id ) === $mode ) {
			$args['signup'] = $mode;

			return $args;
		}

		// A missing event is resolve_event()'s error to report, not a signup one.
		if ( empty( sugar_calendar_get_event( $event_id )->id ) ) {
			return $args;
		}

		$modes = SignupModes::available();

		return $this->invalid_input(
			sprintf(
				/* translators: %1$s: input property name, for example ticketing. %2$s: signup value, for example tickets. */
				__( 'This event does not use %1$s. To turn it on, send signup %2$s together with %1$s.', 'sugar-calendar-lite' ),
				$modes[ $mode ],
				$mode
			)
		);
	}

	/**
	 * Normalize the patch and apply it, reporting the previous value of every
	 * field it touched.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args Normalized ability input.
	 *
	 * @return array|\WP_Error
	 */
	protected function run( array $args ) {

		$event_id = $this->resolve_event_id_input( $args, 'event_id' );

		if ( is_wp_error( $event_id ) ) {
			return $event_id;
		}

		// Both id keys name the event rather than patch it, so neither may
		// reach the patch or count towards "nothing to change".
		unset( $args['event_id'], $args['post_id'] );

		// What the diff compares the stored event against, kept under its own
		// name so a later refactor can't quietly widen it past the raw request.
		$requested = $args;

		if ( empty( $args ) ) {
			return $this->invalid_input(
				__( 'Nothing to change. Name at least one field to update.', 'sugar-calendar-lite' )
			);
		}

		$args = $this->infer_signup( $args, $event_id );

		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$previous_signup = '';
		$event           = $this->resolve_event( $args, $event_id, $previous_signup );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		$requested_signup = isset( $args['signup'] ) ? (string) $args['signup'] : '';

		$normalized = $this->build_normalized( $args, $event );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$before = self::before_snapshot( $requested, $event_id );

		$writer = new EventWriter();
		$result = $writer->update( $event_id, $normalized );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// `previous` is writer-owned, but the writer never sees `signup`: it rides
		// the contributors' `extra` bucket, so the ability reports the change itself.
		if ( $requested_signup !== '' && $previous_signup !== $requested_signup ) {
			$result['previous']['signup'] = $previous_signup;
		}

		return $this->payload( $event_id, $normalized, $result, $requested, $before );
	}

	/**
	 * Shape the ability output from the writer's result.
	 *
	 * @since 3.14.0
	 * @since 3.15.0 Takes the pre-write snapshot and reports changed detail leaves in `previous`.
	 *
	 * @param int   $event_id   Event id that was patched.
	 * @param array $normalized Output of EventInputSchema::normalize().
	 * @param array $result     Output of EventWriter::update().
	 * @param array $requested  Patch input as the caller sent it, without either id key.
	 * @param array $before     EventSnapshot::for_event() taken before the write.
	 *
	 * @return array
	 */
	private function payload( int $event_id, array $normalized, array $result, array $requested, array $before ): array {

		$post_id     = (int) ( $result['post_id'] ?? 0 );
		$snapshot    = EventSnapshot::for_event( $event_id );
		$previous    = (array) ( $result['previous'] ?? [] );
		$previous    = array_merge( $previous, self::underived_leaves( EventSnapshot::changed_leaves( $requested, $before, $snapshot ), $previous, (string) ( $snapshot['signup'] ?? '' ) ) );
		// Field paths a contributor already explained in errors/assumptions this
		// write, so the diff below does not add a second, contradicting line.
		$explained   = array_values( array_unique( (array) ( $result['explained'] ?? [] ) ) );
		$errors      = array_merge( self::collect( $normalized, $result, 'errors' ), EventSnapshot::diff( $requested, $snapshot, $explained ) );
		$assumptions = self::collect( $normalized, $result, 'assumptions' );
		$updated     = array_keys( $previous );
		$errors      = self::note_stale_meeting_time( $event_id, $updated, $errors );

		$payload = [
			'event_id'    => $event_id,
			'edit_url'    => EventWriter::edit_url( $post_id ),
			'updated'     => $updated,
			'previous'    => $previous,
			'assumptions' => array_values( $assumptions ),
			'applied'     => array_values( (array) ( $result['applied'] ?? [] ) ),
			'errors'      => array_values( $errors ),
			'event'       => $snapshot,
		];

		/**
		 * Filters an update-event ability's payload, for a feature reporting its own
		 * outcome of the same write (a recurring series' own date rebuild, etc.).
		 *
		 * @since 3.15.0
		 *
		 * @param array $payload  Payload built so far, see the keys above.
		 * @param int   $event_id Sugar Calendar event id that was patched.
		 */
		$filtered = apply_filters( 'sugar_calendar_integrations_abilities_update_event_payload', $payload, $event_id );

		return is_array( $filtered ) ? $filtered : $payload;
	}

	/**
	 * Warn that a changed time was not carried to the event's online meeting.
	 *
	 * `EventMeetingManager::sync()` only patches a meeting on a later `save_post`, which
	 * this ability never triggers, so a time change here silently leaves the meeting on its
	 * old schedule (Minor 9).
	 *
	 * @since 3.15.0
	 *
	 * @param int    $event_id Sugar Calendar event id that was patched.
	 * @param array  $updated  Input field names whose value changed.
	 * @param array  $errors   Errors collected so far.
	 *
	 * @return array
	 */
	private static function note_stale_meeting_time( int $event_id, array $updated, array $errors ): array {

		if ( ! array_intersect( [ 'start', 'end', 'all_day', 'timezone' ], $updated ) ) {
			return $errors;
		}

		if ( (string) get_event_meta( $event_id, 'meeting_provider', true ) === '' ) {
			return $errors;
		}

		$errors[] = __( 'The online meeting was not updated with the new time. Open the event in the editor and save it to update the meeting.', 'sugar-calendar-lite' );

		return $errors;
	}

	/**
	 * Drop the derived leaves a contributor's own `previous` already covers.
	 *
	 * A leaf is dropped only on an exact match or a match on its bare parent key (a contributor
	 * that reports the whole object, such as `recurrence`, rather than dotted leaves). A `signup`
	 * switch instead drops only the leaves under the newly enabled mode's own property: those
	 * always diff from an absent "before" (a snapshot omits a detail object while its mode is
	 * off) and report `null`, a shape the mode's own schema refuses on replay.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $leaves   Output of EventSnapshot::changed_leaves().
	 * @param array  $previous Previous values the write reported.
	 * @param string $new_mode The event's signup mode after this write.
	 *
	 * @return array
	 */
	private static function underived_leaves( array $leaves, array $previous, string $new_mode ): array {

		if ( array_key_exists( 'signup', $previous ) ) {
			$property = SignupModes::available()[ $new_mode ] ?? '';

			if ( $property === '' ) {
				return $leaves;
			}

			return array_filter(
				$leaves,
				static function ( $key ) use ( $property ) {

					return explode( '.', (string) $key, 2 )[0] !== $property;
				},
				ARRAY_FILTER_USE_KEY
			);
		}

		return array_filter(
			$leaves,
			static function ( $key ) use ( $previous ) {

				$key = (string) $key;

				return ! array_key_exists( $key, $previous ) && ! array_key_exists( explode( '.', $key, 2 )[0], $previous );
			},
			ARRAY_FILTER_USE_KEY
		);
	}
}
