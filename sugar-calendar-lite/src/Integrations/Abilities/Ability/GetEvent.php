<?php
/**
 * The sc-events/get-event ability.
 *
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Event;
use Sugar_Calendar\Integrations\Abilities\AbstractAbility;
use Sugar_Calendar\Integrations\Abilities\EventSnapshot;
use Sugar_Calendar\Integrations\Abilities\Formatter;

/**
 * Get a single event by its Sugar Calendar event ID, or by the WP post ID its
 * editor URL carries. Per-event `read_event` permission; a missing event
 * answers 404, never 403.
 *
 * @since 3.12.0
 */
class GetEvent extends AbstractAbility {

	/**
	 * `EventSnapshot::for_event()` keys this ability merges into its output. `signup` and
	 * `location` are already in the shared shape; other owners add their own key through the
	 * `sugar_calendar_integrations_abilities_get_event_detail` filter's `$snapshot` argument.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	private const DETAIL_KEYS = [
		'ticketing',
		'online_link',
		'online_visibility',
		'online_provider',
		'online_join_url',
	];

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'get-event';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return esc_html__( 'Get Event', 'sugar-calendar-lite' );
	}

	/**
	 * Human-readable ability description.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return esc_html__( 'Get a single event by its Sugar Calendar event ID, or by the post ID from its editor URL.', 'sugar-calendar-lite' );
	}

	/**
	 * Input JSON-schema for this ability.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {

		// No `required`: either id identifies the event, which a single
		// required key cannot express. run-time validation answers 400 when
		// neither arrives.
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'id'      => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Sugar Calendar event id, as returned by sc-events/list-events and sc-events/search-events.', 'sugar-calendar-lite' ),
				],
				'post_id' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'WP post id, the number in the post= parameter of an event editor URL such as /wp-admin/post.php?post=1304&action=edit. Use this when the user names an event by its editor link. It is not the same number as id.', 'sugar-calendar-lite' ),
				],
			],
		];
	}

	/**
	 * Output JSON-schema for this ability.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {

		return Formatter::event_schema();
	}

	/**
	 * Per-event permission check. Resolves the SC event ID to a WP post ID
	 * before current_user_can( 'read_event', $post_id ).
	 *
	 * A missing event answers 404, not 403: reporting "no permission" for an id
	 * that does not exist sends the caller looking for an access problem that
	 * isn't there. Existence is only disclosed to callers who already passed the
	 * ability's coarse gate.
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input. Expected key: `id` or `post_id`.
	 *
	 * @return true|\WP_Error
	 */
	public function check_permission( $input = null ) {

		$args = $this->normalize_input( $input );
		$id   = $this->resolve_event_id_input( $args, 'id' );

		if ( is_wp_error( $id ) ) {
			return $this->defer_lookup_error( $id, 'read_calendar' );
		}

		$query     = $this->events->get_event_query( [ 'id' => $id, 'number' => 1 ] );
		$event     = ! empty( $query->items ) ? $query->items[0] : null;
		$object_id = $event ? absint( $event->object_id ) : 0;

		if ( ! $object_id ) {
			return $this->defer_lookup_error( $this->not_found(), 'read_calendar' );
		}

		if ( ! current_user_can( 'read_event', $object_id ) ) {
			return $this->forbidden(
				esc_html__( 'You do not have permission to view this event.', 'sugar-calendar-lite' )
			);
		}

		return true;
	}

	/**
	 * Permission has already validated existence + read access, so a missing
	 * event here is defensive (race with deletion between callbacks).
	 *
	 * @since 3.12.0
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {

		$args = $this->normalize_input( $input );
		$id   = $this->resolve_event_id_input( $args, 'id' );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$query = $this->events->get_event_query( [ 'id' => $id, 'number' => 1 ] );
		$event = $query->items[0] ?? null;

		if ( ! $event ) {
			return $this->not_found();
		}

		$post_ids = [ (int) $event->object_id ];

		$out = $this->formatter->event( $event, $this->events->resolve_calendar_map( $post_ids ), $this->events->resolve_tag_map( $post_ids ) );

		// read_event is plain `read` on a published event, and the detail carries what the
		// front end withholds (an attendees-only join URL, the form, capacity), so it takes
		// the editor's right.
		if ( ! current_user_can( 'edit_post', (int) $event->object_id ) ) {
			return $out;
		}

		$snapshot = EventSnapshot::for_event( (int) $event->id );

		foreach ( self::DETAIL_KEYS as $key ) {
			if ( array_key_exists( $key, $snapshot ) ) {
				$out[ $key ] = $snapshot[ $key ];
			}
		}

		/**
		 * Filters get-event's detail output, for a feature adding its own edit-only fields.
		 *
		 * Only reached past the `edit_post` gate above, so a filter here sees what the
		 * public/attendee shape withholds. Takes the resolved snapshot as a third argument,
		 * so an owner can read its own key back off it instead of re-resolving the event.
		 *
		 * @since 3.15.0
		 *
		 * @param array $out      Output built so far.
		 * @param Event $event    The event it was built from.
		 * @param array $snapshot `EventSnapshot::for_event()`'s output for this event.
		 */
		$filtered = apply_filters( 'sugar_calendar_integrations_abilities_get_event_detail', $out, $event, $snapshot );

		return is_array( $filtered ) ? $filtered : $out;
	}
}
